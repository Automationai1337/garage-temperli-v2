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

function gt_health_https_url($value) {
    $value = trim((string)$value);
    if ($value === '' || !filter_var($value, FILTER_VALIDATE_URL)) {
        return false;
    }
    $scheme = strtolower((string)parse_url($value, PHP_URL_SCHEME));
    return $scheme === 'https';
}

$runtimeOk = function_exists('curl_init') && is_writable(sys_get_temp_dir());
$chatUrl = trim((string)getenv('TEMPERLI_N8N_URL'));
$pollUrl = trim((string)getenv('TEMPERLI_N8N_POLL_URL'));
$readUrl = trim((string)getenv('TEMPERLI_N8N_READ_URL'));
$widgetKey = trim((string)getenv('TEMPERLI_WIDGET_KEY'));
$configured = gt_health_https_url($chatUrl)
    && gt_health_https_url($pollUrl)
    && gt_health_https_url($readUrl)
    && $widgetKey !== '';

if (!$runtimeOk || !$configured) {
    http_response_code(503);
    echo json_encode(['ok' => false]);
    exit;
}

http_response_code(200);
echo json_encode(['ok' => true]);
