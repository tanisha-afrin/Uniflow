<?php
// UniFlow sender check. Run from the project folder:
//   C:\xampp\php\php.exe check_email.php you@gmail.com
// Sends one test email and prints the REAL reason if it fails.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/config/email.php';

$to = $argv[1] ?? UNIFLOW_SMTP_USERNAME;
echo "SMTP user     : " . (UNIFLOW_SMTP_USERNAME !== '' ? UNIFLOW_SMTP_USERNAME : '(EMPTY - .env not read or not set)') . "\n";
echo "SMTP password : " . (UNIFLOW_SMTP_PASSWORD !== '' ? str_repeat('*', strlen(UNIFLOW_SMTP_PASSWORD)) . ' (' . strlen(UNIFLOW_SMTP_PASSWORD) . ' chars)' : '(EMPTY)') . "\n";
echo "OpenSSL       : " . (extension_loaded('openssl') ? 'enabled' : 'DISABLED') . "\n";
echo "Sending to    : $to\n\n";

try {
    gmailSendTransactionalEmail($to, 'Test', 'UniFlow sender test', '<p>It works.</p>', 'It works.');
    echo "SUCCESS: email sent.\n";
} catch (Throwable $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
}
