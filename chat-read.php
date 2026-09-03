<?php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');

function gt_read_json($status, $payload) {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    gt_read_json(405, ['ok' => false]);
}

$allowedOrigins = [
    'https://garage-temperli.zantua-ai.com',
    'https://garagetemperli.ch',
    'https://www.garagetemperli.ch',
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin === '' || !in_array($origin, $allowedOrigins, true)) {
    gt_read_json(403, ['ok' => false]);
}

$contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
if (strpos($contentType, 'application/json') === false) {
    gt_read_json(415, ['ok' => false]);
}

$contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 2048) {
    gt_read_json(413, ['ok' => false]);
}
$raw = file_get_contents('php://input');
if ($raw === false || strlen($raw) === 0 || strlen($raw) > 2048) {
    gt_read_json(400, ['ok' => false]);
}
$data = json_decode($raw, true);
if (!is_array($data)) {
    gt_read_json(400, ['ok' => false]);
}
foreach (array_keys($data) as $key) {
    if (!in_array($key, ['sessionId', 'ids'], true)) {
        gt_read_json(400, ['ok' => false]);
    }
}

$sessionId = trim((string)($data['sessionId'] ?? ''));
if (!preg_match('/^[A-Za-z0-9_-]{4,96}$/', $sessionId)) {
    gt_read_json(422, ['ok' => false]);
}
$ids = isset($data['ids']) && is_array($data['ids']) ? array_slice($data['ids'], 0, 20) : [];
$cleanIds = [];
foreach ($ids as $id) {
    if (is_int($id) || (is_string($id) && preg_match('/^[A-Za-z0-9_-]{1,96}$/', $id))) {
        $cleanIds[] = $id;
    }
}
if (!$cleanIds) {
    gt_read_json(422, ['ok' => false]);
}

$upstreamUrl = trim((string)getenv('TEMPERLI_N8N_READ_URL'));
$widgetKey = trim((string)getenv('TEMPERLI_WIDGET_KEY'));
if ($upstreamUrl === '' || $widgetKey === '') {
    gt_read_json(503, ['ok' => false]);
}
if (!filter_var($upstreamUrl, FILTER_VALIDATE_URL) || stripos($upstreamUrl, 'https://') !== 0 || !function_exists('curl_init')) {
    gt_read_json(503, ['ok' => false]);
}

$conversationId = 'gtc-' . substr(hash('sha256', $sessionId), 0, 32);
$payload = json_encode([
    'conversationId' => $conversationId,
    'ids' => $cleanIds,
], JSON_UNESCAPED_SLASHES);
if ($payload === false) {
    gt_read_json(500, ['ok' => false]);
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
    gt_read_json(502, ['ok' => false]);
}
$decoded = json_decode($response, true);
if (!is_array($decoded) || empty($decoded['ok'])) {
    gt_read_json(502, ['ok' => false]);
}

gt_read_json(200, ['ok' => true]);
