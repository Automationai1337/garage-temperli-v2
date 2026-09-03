<?php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
header('Referrer-Policy: same-origin');

function gt_contact_json($status, $payload) {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function gt_contact_cut($value, $max) {
    return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    gt_contact_json(405, ['ok' => false, 'message' => 'Methode nicht erlaubt.']);
}

$allowedOrigins = [
    'https://garage-temperli.zantua-ai.com',
    'https://garagetemperli.ch',
    'https://www.garagetemperli.ch'
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin === '' || !in_array($origin, $allowedOrigins, true)) {
    gt_contact_json(403, ['ok' => false, 'message' => 'Anfrage nicht erlaubt.']);
}

$contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
if (strpos($contentType, 'application/json') === false) {
    gt_contact_json(415, ['ok' => false, 'message' => 'Ungültiges Format.']);
}

$contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 12000) {
    gt_contact_json(413, ['ok' => false, 'message' => 'Anfrage zu gross.']);
}

$raw = file_get_contents('php://input');
if ($raw === false || strlen($raw) === 0 || strlen($raw) > 12000) {
    gt_contact_json(400, ['ok' => false, 'message' => 'Ungültige Anfrage.']);
}
$data = json_decode($raw, true);
if (!is_array($data)) {
    gt_contact_json(400, ['ok' => false, 'message' => 'Ungültige Anfrage.']);
}

$allowedKeys = ['name', 'phone', 'email', 'vehicle', 'service', 'date', 'time', 'message', 'website'];
foreach (array_keys($data) as $key) {
    if (!in_array($key, $allowedKeys, true)) {
        gt_contact_json(400, ['ok' => false, 'message' => 'Unbekanntes Feld.']);
    }
}

// Honeypot: acknowledge bot submissions without creating an external send.
if (!empty($data['website'])) {
    gt_contact_json(200, ['ok' => true, 'message' => 'Danke.']);
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$window = 900;
$maxRequests = 5;
$bucketId = (string)floor(time() / $window);
$rateFile = sys_get_temp_dir() . '/gt_contact_' . hash('sha256', $ip . '|' . $bucketId) . '.count';
$fh = @fopen($rateFile, 'c+');
if (!$fh || !flock($fh, LOCK_EX)) {
    if ($fh) fclose($fh);
    gt_contact_json(503, ['ok' => false, 'message' => 'Anfrage kann gerade nicht verarbeitet werden. Bitte rufen Sie uns an.']);
}
rewind($fh);
$current = (int)trim((string)stream_get_contents($fh));
$current++;
ftruncate($fh, 0);
rewind($fh);
fwrite($fh, (string)$current);
fflush($fh);
flock($fh, LOCK_UN);
fclose($fh);
if ($current > $maxRequests) {
    gt_contact_json(429, ['ok' => false, 'message' => 'Zu viele Anfragen. Bitte versuchen Sie es später erneut oder rufen Sie uns an.']);
}

$clean = static function ($value, $max = 300) {
    $value = trim((string)$value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
    if ($value === null) $value = '';
    return gt_contact_cut($value, $max);
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
    gt_contact_json(422, ['ok' => false, 'message' => 'Bitte Name, Telefon, Fahrzeug und Anliegen ausfüllen.']);
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    gt_contact_json(422, ['ok' => false, 'message' => 'Bitte eine gültige E-Mail-Adresse eingeben.']);
}

$allowedServices = [
    'Service & Wartung',
    'Pneus & Räder',
    'Reparatur / Diagnose',
    'Klimaservice',
    'Andere Anfrage'
];
if (!in_array($service, $allowedServices, true)) {
    gt_contact_json(422, ['ok' => false, 'message' => 'Bitte ein gültiges Anliegen auswählen.']);
}
if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    gt_contact_json(422, ['ok' => false, 'message' => 'Bitte ein gültiges Wunschdatum angeben.']);
}
if ($time !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
    gt_contact_json(422, ['ok' => false, 'message' => 'Bitte eine gültige Wunschzeit angeben.']);
}

// TESTPHASE: Empfänger bleibt vorübergehend intern bei Zantua AI.
$recipient = 'kontakt@zantua-ai.com';
$subject = '[TEST] Garage Temperli – neue Website-Anfrage';
$body = "TESTANFRAGE – noch nicht an Garage Temperli weitergeleitet\n\n"
      . "Name: {$name}\nTelefon: {$phone}\nE-Mail: " . ($email ?: 'nicht angegeben') . "\n"
      . "Fahrzeug / VIN / Typenschein: {$vehicle}\nAnliegen: {$service}\n"
      . "Wunschdatum: " . ($date ?: 'offen') . "\nWunschzeit: " . ($time ?: 'offen') . "\n\n"
      . "Nachricht:\n" . ($message ?: '—') . "\n";

$headers = [
    'From: Garage Temperli Website <kontakt@zantua-ai.com>',
    'Content-Type: text/plain; charset=UTF-8',
    'X-Mailer: ZantuaAI-Temperli-Web'
];
if ($email !== '') $headers[] = 'Reply-To: ' . $email;

$sent = @mail($recipient, $subject, $body, implode("\r\n", $headers));
if (!$sent) {
    gt_contact_json(503, ['ok' => false, 'message' => 'Die Nachricht konnte gerade nicht versendet werden. Bitte rufen Sie uns unter 044 725 43 82 an.']);
}

gt_contact_json(200, ['ok' => true, 'message' => 'Testanfrage erfolgreich gesendet.']);
