<?php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');

function gt_poll_json($status, $payload) {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    gt_poll_json(405, ['ok' => false]);
}

$allowedOrigins = [
    'https://garage-temperli.zantua-ai.com',
    'https://garagetemperli.ch',
    'https://www.garagetemperli.ch',
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin === '' || !in_array($origin, $allowedOrigins, true)) {
    gt_poll_json(403, ['ok' => false]);
}

$contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
if (strpos($contentType, 'application/json') === false) {
    gt_poll_json(415, ['ok' => false]);
}

$contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 1024) {
    gt_poll_json(413, ['ok' => false]);
}
$raw = file_get_contents('php://input');
if ($raw === false || strlen($raw) === 0 || strlen($raw) > 1024) {
    gt_poll_json(400, ['ok' => false]);
}
$data = json_decode($raw, true);
if (!is_array($data)) {
    gt_poll_json(400, ['ok' => false]);
}
foreach (array_keys($data) as $key) {
    if ($key !== 'sessionId') {
        gt_poll_json(400, ['ok' => false]);
    }
}
$sessionId = trim((string)($data['sessionId'] ?? ''));
if (!preg_match('/^[A-Za-z0-9_-]{4,96}$/', $sessionId)) {
    gt_poll_json(422, ['ok' => false]);
}

// Cheap server-side protection against aggressive browser polling.
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$windowSeconds = 300;
$maxRequests = 45;
$bucket = (string)floor(time() / $windowSeconds);
$ratePath = sys_get_temp_dir() . '/gt_poll_' . hash('sha256', $ip . '|' . $sessionId . '|' . $bucket) . '.count';
$fh = @fopen($ratePath, 'c+');
if (!$fh || !flock($fh, LOCK_EX)) {
    if ($fh) fclose($fh);
    gt_poll_json(503, ['ok' => false]);
}
rewind($fh);
$count = (int)trim((string)stream_get_contents($fh));
$count++;
ftruncate($fh, 0);
rewind($fh);
fwrite($fh, (string)$count);
fflush($fh);
flock($fh, LOCK_UN);
fclose($fh);
if ($count > $maxRequests) {
    gt_poll_json(429, ['ok' => false]);
}

$upstreamUrl = trim((string)getenv('TEMPERLI_N8N_POLL_URL'));
$widgetKey = trim((string)getenv('TEMPERLI_WIDGET_KEY'));
if ($upstreamUrl === '' || $widgetKey === '') {
    gt_poll_json(503, ['ok' => false]);
}
if (!filter_var($upstreamUrl, FILTER_VALIDATE_URL) || stripos($upstreamUrl, 'https://') !== 0 || !function_exists('curl_init')) {
    gt_poll_json(503, ['ok' => false]);
}

$conversationId = 'gtc-' . substr(hash('sha256', $sessionId), 0, 32);
$payload = json_encode(['conversationId' => $conversationId], JSON_UNESCAPED_SLASHES);
if ($payload === false) {
    gt_poll_json(500, ['ok' => false]);
}

$ch = curl_init($upstreamUrl);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_CONNECTTIMEOUT => 4,
    CURLOPT_TIMEOUT => 12,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-Widget-Key: ' . $widgetKey,
    ],
    CURLOPT_POSTFIELDS => $payload,
]);
$response = curl_exec($ch);
$status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$curlError = curl_errno($ch);
curl_close($ch);
if ($curlError !== 0 || $response === false || $status < 200 || $status >= 300) {
    gt_poll_json(502, ['ok' => false]);
}
$decoded = json_decode($response, true);
if (!is_array($decoded)) {
    gt_poll_json(502, ['ok' => false]);
}

$rawReplies = isset($decoded['replies']) && is_array($decoded['replies']) ? $decoded['replies'] : [];
$rawIds = isset($decoded['replyIds']) && is_array($decoded['replyIds']) ? $decoded['replyIds'] : [];
$replies = [];
$replyIds = [];
$seenReplyIds = [];

// Preserve reply/id correlation. Only expose a reply if it can be acknowledged with a valid matching ID.
foreach (array_slice($rawReplies, 0, 20, true) as $index => $reply) {
    if (!is_string($reply) || trim($reply) === '' || !array_key_exists($index, $rawIds)) {
        continue;
    }

    $id = $rawIds[$index];
    $validId = is_int($id)
        || (is_string($id) && preg_match('/^[A-Za-z0-9_-]{1,96}$/', $id));
    if (!$validId) {
        continue;
    }

    $idKey = gettype($id) . ':' . (string)$id;
    if (isset($seenReplyIds[$idKey])) {
        continue;
    }
    $seenReplyIds[$idKey] = true;

    $cleanReply = function_exists('mb_substr')
        ? mb_substr(trim($reply), 0, 5000)
        : substr(trim($reply), 0, 5000);
    $replies[] = $cleanReply;
    $replyIds[] = $id;
}

gt_poll_json(200, [
    'ok' => true,
    'replies' => $replies,
    'replyIds' => $replyIds,
]);
