<?php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');

function gt_json($status, $payload) {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function gt_strlen($value) {
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

function gt_substr($value, $start, $length) {
    return function_exists('mb_substr') ? mb_substr($value, $start, $length) : substr($value, $start, $length);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    gt_json(405, ['ok' => false, 'message' => 'Methode nicht erlaubt.']);
}

$allowedOrigins = [
    'https://garage-temperli.zantua-ai.com',
    'https://garagetemperli.ch',
    'https://www.garagetemperli.ch',
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin === '' || !in_array($origin, $allowedOrigins, true)) {
    gt_json(403, ['ok' => false, 'message' => 'Anfrage nicht erlaubt.']);
}

$contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
if (strpos($contentType, 'application/json') === false) {
    gt_json(415, ['ok' => false, 'message' => 'Ungültiges Format.']);
}

$contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 4096) {
    gt_json(413, ['ok' => false, 'message' => 'Anfrage zu gross.']);
}

$raw = file_get_contents('php://input');
if ($raw === false || strlen($raw) === 0 || strlen($raw) > 4096) {
    gt_json(400, ['ok' => false, 'message' => 'Ungültige Anfrage.']);
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    gt_json(400, ['ok' => false, 'message' => 'Ungültige Anfrage.']);
}

// Browser metadata is accepted for backward compatibility but never trusted for tenant selection.
$allowedKeys = ['message', 'sessionId', 'tenant', 'page', 'origin'];
foreach (array_keys($data) as $key) {
    if (!in_array($key, $allowedKeys, true)) {
        gt_json(400, ['ok' => false, 'message' => 'Unbekanntes Feld.']);
    }
}

$message = trim((string)($data['message'] ?? ''));
$sessionId = trim((string)($data['sessionId'] ?? ''));
if ($message === '' || gt_strlen($message) > 800) {
    gt_json(422, ['ok' => false, 'message' => 'Nachricht fehlt oder ist zu lang.']);
}
if (!preg_match('/^[A-Za-z0-9_-]{4,96}$/', $sessionId)) {
    gt_json(422, ['ok' => false, 'message' => 'Ungültige Sitzung.']);
}

// Rate limiting occurs before any n8n/model call. Do not trust client proxy/IP headers.
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$windowSeconds = 60;
$maxRequests = 12;
$bucket = (string)floor(time() / $windowSeconds);
$ratePath = sys_get_temp_dir() . '/gt_ai_' . hash('sha256', $ip . '|' . $bucket) . '.count';
$fh = @fopen($ratePath, 'c+');
if (!$fh) {
    gt_json(503, ['ok' => false, 'message' => 'Die KI ist gerade nicht erreichbar.']);
}
if (!flock($fh, LOCK_EX)) {
    fclose($fh);
    gt_json(503, ['ok' => false, 'message' => 'Die KI ist gerade nicht erreichbar.']);
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
    gt_json(429, ['ok' => false, 'message' => 'Zu viele Anfragen. Bitte kurz warten.']);
}

// Reuse the existing live Werkstatt-Assistent contract, but keep its widget key server-side.
$upstreamUrl = trim((string)getenv('TEMPERLI_N8N_URL'));
$widgetKey = trim((string)getenv('TEMPERLI_WIDGET_KEY'));
if ($upstreamUrl === '' || $widgetKey === '') {
    gt_json(503, ['ok' => false, 'message' => 'Die KI-Verbindung ist noch nicht freigeschaltet.']);
}
if (!filter_var($upstreamUrl, FILTER_VALIDATE_URL) || stripos($upstreamUrl, 'https://') !== 0) {
    gt_json(503, ['ok' => false, 'message' => 'Die KI-Verbindung ist nicht korrekt konfiguriert.']);
}
if (!function_exists('curl_init')) {
    gt_json(503, ['ok' => false, 'message' => 'Die KI ist gerade nicht erreichbar.']);
}

try {
    $requestId = bin2hex(random_bytes(8));
} catch (Throwable $e) {
    $requestId = substr(hash('sha256', microtime(true) . '|' . $ip . '|' . $sessionId), 0, 16);
}

// Never let the browser choose n8n tenant identifiers. The server-held widget key resolves the tenant.
// Derive stable, restricted IDs from the browser session so server-side history and handoff stay consistent.
$sessionHash = substr(hash('sha256', $sessionId), 0, 32);
$conversationId = 'gtc-' . $sessionHash;
$visitorId = 'gtv-' . $sessionHash;
$messageId = 'gtm-' . $requestId;

$payload = json_encode([
    'message' => $message,
    'conversationId' => $conversationId,
    'visitorId' => $visitorId,
    'messageId' => $messageId,
    'channel' => 'web',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($payload === false) {
    gt_json(500, ['ok' => false, 'message' => 'Die Anfrage konnte nicht verarbeitet werden.']);
}

$ch = curl_init($upstreamUrl);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_CONNECTTIMEOUT => 4,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-Widget-Key: ' . $widgetKey,
        'X-Request-ID: ' . $requestId,
    ],
    CURLOPT_POSTFIELDS => $payload,
]);

$response = curl_exec($ch);
$status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$curlError = curl_errno($ch);
curl_close($ch);

if ($curlError !== 0 || $response === false || $status < 200 || $status >= 300) {
    gt_json(502, ['ok' => false, 'message' => 'Die KI ist gerade nicht erreichbar. Bitte versuchen Sie es erneut.']);
}

$decoded = json_decode($response, true);
if (!is_array($decoded)) {
    gt_json(502, ['ok' => false, 'message' => 'Die KI-Antwort konnte nicht verarbeitet werden.']);
}

$answer = null;
foreach (['answer', 'reply', 'output', 'message'] as $key) {
    if (isset($decoded[$key]) && is_string($decoded[$key]) && trim($decoded[$key]) !== '') {
        $answer = trim($decoded[$key]);
        break;
    }
}
if ($answer === null) {
    gt_json(502, ['ok' => false, 'message' => 'Die KI-Antwort konnte nicht verarbeitet werden.']);
}

$answer = gt_substr($answer, 0, 5000);
gt_json(200, [
    'ok' => true,
    'answer' => $answer,
    'conversationId' => $conversationId,
    'escalate' => !empty($decoded['escalate']),
    'requestId' => $requestId,
]);
