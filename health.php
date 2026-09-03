<?php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

$runtimeOk = function_exists('curl_init') && is_writable(sys_get_temp_dir());
$configured = trim((string)getenv('TEMPERLI_N8N_URL')) !== ''
    && trim((string)getenv('TEMPERLI_N8N_POLL_URL')) !== ''
    && trim((string)getenv('TEMPERLI_N8N_READ_URL')) !== ''
    && trim((string)getenv('TEMPERLI_WIDGET_KEY')) !== '';

if (!$runtimeOk || !$configured) {
    http_response_code(503);
    echo json_encode(['ok' => false]);
    exit;
}

http_response_code(200);
echo json_encode(['ok' => true]);
