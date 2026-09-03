<?php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
header('Referrer-Policy: same-origin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Methode nicht erlaubt.']);
    exit;
}

$allowedOrigins = [
    'https://garage-temperli.zantua-ai.com',
    'https://garagetemperli.ch',
    'https://www.garagetemperli.ch'
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin === '' || !in_array($origin, $allowedOrigins, true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Anfrage nicht erlaubt.']);
    exit;
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') === false) {
    http_response_code(415);
    echo json_encode(['ok' => false, 'message' => 'Ungültiges Format.']);
    exit;
}

$raw = file_get_contents('php://input');
if ($raw === false || strlen($raw) > 16000) {
    http_response_code(413);
    echo json_encode(['ok' => false, 'message' => 'Anfrage zu gross.']);
    exit;
}
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Ungültige Anfrage.']);
    exit;
}

$action = $_GET['action'] ?? 'chat';
$upstreams = [
    'chat' => 'https://botsgenerator.app.n8n.cloud/webhook/werkstatt-chat',
    'poll' => 'https://botsgenerator.app.n8n.cloud/webhook/werkstatt-chat-poll',
    'read' => 'https://botsgenerator.app.n8n.cloud/webhook/werkstatt-chat-read',
];
if (!isset($upstreams[$action])) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Unbekannte Aktion.']);
    exit;
}

// Never commit the tenant/widget key. Configure GT_WIDGET_KEY only in the hosting environment.
$widgetKey = getenv('GT_WIDGET_KEY');
if (!is_string($widgetKey) || strlen($widgetKey) < 16) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'message' => 'KI-Verbindung ist serverseitig noch nicht konfiguriert.']);
    exit;
}

if (!function_exists('curl_init')) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'message' => 'KI-Verbindung ist auf diesem Server nicht verfügbar.']);
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ch = curl_init($upstreams[$action]);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-Widget-Key: ' . $widgetKey,
        'Origin: ' . $origin,
        'X-Forwarded-For: ' . $ip,
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 4,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_FOLLOWLOCATION => false,
]);
$response = curl_exec($ch);
$status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false || $curlError !== '') {
    http_response_code(502);
    echo json_encode(['ok' => false, 'message' => 'KI-Dienst ist gerade nicht erreichbar.']);
    exit;
}

if ($status < 200 || $status >= 300) {
    http_response_code($status >= 400 && $status <= 599 ? $status : 502);
    echo json_encode(['ok' => false, 'message' => 'KI-Anfrage konnte nicht verarbeitet werden.']);
    exit;
}

$decoded = json_decode($response, true);
if (!is_array($decoded)) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'message' => 'Ungültige Antwort vom KI-Dienst.']);
    exit;
}

echo json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
