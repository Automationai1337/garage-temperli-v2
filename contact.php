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

if (!empty($data['website'])) {
    echo json_encode(['ok' => true, 'message' => 'Danke.']);
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$window = 900;
$maxRequests = 5;
$bucketId = (string)floor(time() / $window);
$rateFile = sys_get_temp_dir() . '/gt_contact_' . hash('sha256', $ip . '|' . $bucketId) . '.count';
$fh = @fopen($rateFile, 'c+');
if (!$fh) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'message' => 'Anfrage kann gerade nicht verarbeitet werden. Bitte rufen Sie uns an.']);
    exit;
}
flock($fh, LOCK_EX);
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
    http_response_code(503);
    echo json_encode(['ok' => false, 'message' => 'Die Nachricht konnte gerade nicht versendet werden. Bitte rufen Sie uns unter 044 725 43 82 an.']);
    exit;
}

echo json_encode(['ok' => true, 'message' => 'Testanfrage erfolgreich gesendet.']);
