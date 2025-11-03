<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

// Prosta konfiguracja
$to = 'www.matimat14.pl@gmail.com'; // <-- adres docelowy (ukryty po stronie serwera)
$cooldownSeconds = 45;

// 1) Metoda i podstawowe zabezpieczenia
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'error'=>'Niedozwolona metoda.']); exit;
}

// 2) CSRF
if (empty($_POST['csrf']) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'Nieprawidłowy token CSRF.']); exit;
}

// 3) Honeypot
if (!empty($_POST['company'])) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'Wykryto próbę spamu.']); exit;
}

// 4) Cooldown (na sesji)
if (!empty($_SESSION['last_submit']) && (time() - $_SESSION['last_submit']) < $cooldownSeconds) {
  $wait = $cooldownSeconds - (time() - $_SESSION['last_submit']);
  echo json_encode(['ok'=>false,'error'=>"Odczekaj jeszcze {$wait}s przed kolejną wysyłką."]); exit;
}

// 5) Walidacja
$nick = isset($_POST['nick']) ? trim($_POST['nick']) : '';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

if (mb_strlen($nick) < 2 || mb_strlen($nick) > 64) {
  echo json_encode(['ok'=>false,'error'=>'Podaj poprawny nick/imię (2–64 znaki).']); exit;
}
if (mb_strlen($message) < 5 || mb_strlen($message) > 3000) {
  echo json_encode(['ok'=>false,'error'=>'Wiadomość powinna mieć 5–3000 znaków.']); exit;
}

// 6) Składanie wiadomości e-mail
$subject = 'Kontakt z formularza (matimat14)';
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

$body = "Nowa wiadomość z formularza kontaktowego:\n\n".
        "Nick/Imię: {$nick}\n".
        "IP: {$ip}\n".
        "UA: {$ua}\n\n".
        "Treść:\n{$message}\n";

// Nagłówki — ustaw najlepiej adres z własnej domeny (żeby serwer nie odrzucał)
$from = 'no-reply@matimat14.pl';
$headers = "From: matimat14 <{$from}>\r\n".
           "Reply-To: {$from}\r\n".
           "Content-Type: text/plain; charset=UTF-8\r\n".
           "X-Mailer: PHP/" . phpversion();

// 7) Wysyłka
$sent = @mail($to, "=?UTF-8?B?".base64_encode($subject)."?=", $body, $headers);

if ($sent) {
  $_SESSION['last_submit'] = time();
  echo json_encode(['ok'=>true]); exit;
} else {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Nie udało się wysłać wiadomości (mail).']); exit;
}