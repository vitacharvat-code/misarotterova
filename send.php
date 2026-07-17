<?php
/**
 * Zpracování kontaktního formuláře – Míša Rotterová
 * Odesílá zprávu z formuláře na e-mail níže.
 * Odesílatel (From) musí být adresa na doméně, jinak Gmail bere mail jako spam.
 */

// ── NASTAVENÍ ────────────────────────────────────────────────
$RECIPIENT   = 'misa.rotterova@gmail.com';   // kam zprávy chodí
$FROM        = 'info@misarotterova.cz';       // odesílatel (adresa na doméně)
$FROM_NAME   = 'Web misarotterova.cz';
// ─────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');

function fail($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'message' => $msg]);
    exit;
}
function ok($msg) {
    echo json_encode(['ok' => true, 'message' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Neplatný požadavek.', 405);
}

// Honeypot – pokud je vyplněný, je to bot. Předstíráme úspěch.
if (!empty($_POST['website'])) {
    ok('Děkuji, zpráva byla odeslána.');
}

// Vstupy
$name    = trim($_POST['name']    ?? '');
$email   = trim($_POST['email']   ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

// Validace
if ($name === '' || $email === '' || $message === '') {
    fail('Vyplňte prosím jméno, e-mail a zprávu.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fail('Zadejte prosím platný e-mail.');
}
// Ochrana proti vkládání hlaviček (header injection)
foreach ([$name, $email, $subject] as $v) {
    if (preg_match('/[\r\n]/', $v)) {
        fail('Neplatný vstup.');
    }
}

// Rozumné délkové limity (proti zahlcení)
if (mb_strlen($name) > 100 || mb_strlen($subject) > 150 || mb_strlen($message) > 5000) {
    fail('Zpráva je příliš dlouhá.');
}

// Anti-spam: příliš mnoho odkazů = skoro jistě spam
if (preg_match_all('~https?://~i', $message) > 4) {
    // předstíráme úspěch, ať spammer nezkouší dál
    ok('Děkuji, zpráva byla odeslána.');
}

// Sestavení e-mailu
$mailSubject = $subject !== ''
    ? 'Web – ' . $subject
    : 'Nová zpráva z webu misarotterova.cz';

$body  = "Nová zpráva z kontaktního formuláře na misarotterova.cz\n\n";
$body .= "Jméno:   $name\n";
$body .= "E-mail:  $email\n";
$body .= "Předmět: " . ($subject !== '' ? $subject : '(neuvedeno)') . "\n";
$body .= "----------------------------------------\n\n";
$body .= $message . "\n";

// Hlavičky – UTF-8, správný From a Reply-To (na odesílatele lze rovnou odpovědět)
$encodedSubject = '=?UTF-8?B?' . base64_encode($mailSubject) . '?=';
$encodedFromName = '=?UTF-8?B?' . base64_encode($FROM_NAME) . '?=';

$headers  = "From: $encodedFromName <$FROM>\r\n";
$headers .= "Reply-To: $name <$email>\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "Content-Transfer-Encoding: 8bit\r\n";
$headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

// 5. parametr zajistí, že obálkový odesílatel je na doméně (lepší doručení)
$sent = @mail($RECIPIENT, $encodedSubject, $body, $headers, '-f' . $FROM);

if ($sent) {
    ok('Děkuji, zpráva byla odeslána. Ozvu se co nejdříve.');
} else {
    fail('Zprávu se nepodařilo odeslat. Zkuste to prosím znovu, nebo napište přímo na ' . $RECIPIENT . '.', 500);
}
