<?php
/**
 * Nimmt das Kontaktformular entgegen und schickt es per E-Mail weiter.
 * Läuft auf jedem Webspace mit PHP. Muss im selben Ordner wie kontakt.html liegen.
 */

// ---- Einstellungen ----
$empfaenger = 'info@hoffrichter-gruen.de';   // Hierhin gehen die Anfragen
$mindestSekunden = 3;                         // Schneller abgeschickte Formulare gelten als Roboter
$hoechstSekunden = 60 * 60 * 24;              // Formular, das länger als 24 h offen war, wird abgelehnt

header('Content-Type: application/json; charset=utf-8');

function antwort(int $status, bool $ok, string $fehler = ''): void
{
    http_response_code($status);
    echo json_encode($ok ? ['ok' => true] : ['ok' => false, 'fehler' => $fehler]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    antwort(405, false, 'Nur POST erlaubt.');
}

// Hilfsfunktion: Text säubern, Zeilenumbrüche entfernen (Schutz vor Header-Injection)
function einzeilig(string $s): string
{
    return trim(preg_replace('/[\r\n\t]+/', ' ', strip_tags($s)));
}

// ---- Spam-Schutz ----
// 1) Verstecktes Feld ausgefüllt = Roboter. Stillschweigend "Erfolg" melden, nichts senden.
if (!empty($_POST['website'])) {
    antwort(200, true);
}
// 2) Zeitstempel prüfen
$t = (int) ($_POST['t'] ?? 0);
$vergangen = time() - (int) floor($t / 1000);
if ($t <= 0 || $vergangen < $mindestSekunden || $vergangen > $hoechstSekunden) {
    antwort(400, false, 'Zeitprüfung fehlgeschlagen.');
}

// ---- Eingaben prüfen ----
$name = einzeilig($_POST['name'] ?? '');
$email = einzeilig($_POST['email'] ?? '');
$tel = einzeilig($_POST['tel'] ?? '');
$nachricht = trim(strip_tags($_POST['nachricht'] ?? ''));

if ($name === '' || $nachricht === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    antwort(400, false, 'Bitte alle Pflichtfelder korrekt ausfüllen.');
}
if (empty($_POST['datenschutz'])) {
    antwort(400, false, 'Bitte stimmen Sie der Datenschutzerklärung zu.');
}
if (mb_strlen($name) > 120 || mb_strlen($tel) > 60 || mb_strlen($nachricht) > 5000) {
    antwort(400, false, 'Eingabe zu lang.');
}

// ---- Mail zusammenbauen ----
$host = preg_replace('/^www\./', '', $_SERVER['SERVER_NAME'] ?? 'localhost');
$absender = 'webformular@' . $host; // Muss eine Adresse der eigenen Domain sein, sonst lehnen viele Anbieter die Mail ab

$betreff = 'Neue Anfrage über die Website von ' . $name;
$text = "Neue Anfrage über das Kontaktformular\n\n"
    . "Name:    $name\n"
    . "E-Mail:  $email\n"
    . 'Telefon: ' . ($tel !== '' ? $tel : '-') . "\n\n"
    . "Nachricht:\n$nachricht\n\n"
    . '— Gesendet am ' . date('d.m.Y H:i') . " Uhr\n";

$header = [
    'From' => "Website <$absender>",
    'Reply-To' => $email,
    'MIME-Version' => '1.0',
    'Content-Type' => 'text/plain; charset=utf-8',
    'X-Mailer' => 'PHP/' . phpversion(),
];

$ok = mail(
    $empfaenger,
    '=?UTF-8?B?' . base64_encode($betreff) . '?=',
    $text,
    $header
);

if (!$ok) {
    antwort(500, false, 'Mailversand fehlgeschlagen.');
}
antwort(200, true);
