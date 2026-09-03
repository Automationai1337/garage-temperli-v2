<?php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

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
if ($origin !== '' && !in_array($origin, $allowedOrigins, true)) {
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
if ($raw === false || strlen($raw) > 12000) {
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

// Honeypot: echte Besucher lassen dieses Feld leer.
if (!empty($data['website'])) {
    echo json_encode(['ok' => true, 'message' => 'Danke.']);
    exit;
}

// Serverseitiges Rate Limit nur auf REMOTE_ADDR, nicht auf frei setzbare Proxy-Header.
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$bucket = hash('sha256', $ip . '|' . date('Y-m-d-H-i'));
$rateFile = sys_get_temp_dir() . '/gt_contact_' . $bucket . '.json';
$now = time();
$window = 900;
$maxRequests = 5;
$state = ['start' => $now, 'count' => 0];
if (is_file($rateFile)) {
    $loaded = json_decode((string)@file_get_contents($rateFile), true);
    if (is_array($loaded)) $state = array_merge($state, $loaded);
}
if (($now - (int)$state['start']) > $window) $state = ['start' => $now, 'count' => 0];
$state['count'] = (int)$state['count'] + 1;
@file_put_contents($rateFile, json_encode($state), LOCK_EX);
if ($state['count'] > $maxRequests) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'message' => 'Zu viele Anfragen. Bitte versuchen Sie es später erneut oder rufen Sie uns an.']);
    exit;
}

$clean = static function ($value, $max = 300) {
    $value = trim((string)$value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
    return mb_substr($value, 0, $max);
};

$name = $clean($data['name'] ?? '', 100);
$phone = $clean($data['phone'] ?? '', 60);
$email = $clean($data['email'] ?? '', 160);
$vehicle = $clean($data['vehicle'] ?? '', 180);
$service = $clean($data['service'] ?? '', 120);
$date = $clean($data['date'] ?? '', 30);
$time = $clean($data['time'] ?? '', 30);
$message = $clean($data['message'] ?? '', 1800);

if ($name === '' || $phone === '' || $vehicle === '' || $service === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Bitte Name, Telefon, Fahrzeug und Anliegen ausfüllen.']);
    exit;
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Bitte eine gültige E-Mail-Adresse eingeben.']);
    exit;
}

$subject = 'Neue Website-Anfrage – Garage Temperli';
$body = "Neue Anfrage über die Garage-Temperli-Website\n\n"
      . "Name: {$name}\n"
      . "Telefon: {$phone}\n"
      . "E-Mail: " . ($email ?: 'nicht angegeben') . "\n"
      . "Fahrzeug / VIN / Typenschein: {$vehicle}\n"
      . "Anliegen: {$service}\n"
      . "Wunschdatum: " . ($date ?: 'offen') . "\n"
      . "Wunschzeit: " . ($time ?: 'offen') . "\n\n"
      . "Nachricht:\n" . ($message ?: '—') . "\n";

$headers = [
    'From: Garage Temperli Website <kontakt@zantua-ai.com>',
    'Content-Type: text/plain; charset=UTF-8',
    'X-Mailer: ZantuaAI-Temperli-Web'
];
if ($email !== '') $headers[] = 'Reply-To: ' . $email;

$sent = @mail('info@garagetemperli.ch', $subject, $body, implode("\r\n", $headers));
if (!$sent) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'message' => 'Die Nachricht konnte gerade nicht versendet werden. Bitte rufen Sie uns unter 044 725 43 82 an.']);
    exit;
}

echo json_encode(['ok' => true, 'message' => 'Vielen Dank. Ihre Anfrage wurde an Garage Temperli gesendet.']);
