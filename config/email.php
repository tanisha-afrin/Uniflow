<?php

/*
 * UniFlow Gmail SMTP sender
 *
 * One Gmail account is used as the UniFlow sender.
 * The Personal Email entered during staff account setup is the recipient.
 *
 * IMPORTANT:
 * - Use the Gmail address that owns the Google App Password.
 * - Use a Google App Password, NOT the normal Gmail password.
 * - You do NOT need an App Password for the admin's Personal Email.
 */


/* =========================================================
   GMAIL SMTP SETTINGS
========================================================= */

const UNIFLOW_SMTP_HOST = 'smtp.gmail.com';
const UNIFLOW_SMTP_PORT = 587;


/*
 * PUT YOUR UNIFLOW SENDER GMAIL HERE.
 *
 * Example:
 * define('UNIFLOW_SMTP_USERNAME', 'uniflow.admin@gmail.com');
 */
define(
    'UNIFLOW_SMTP_USERNAME',
    'uniflow.admin@gmail.com'
);


/*
 * PUT THE GOOGLE APP PASSWORD OF THAT SAME GMAIL HERE.
 *
 * IMPORTANT:
 * This is NOT your normal Gmail password.
 *
 * Example:
 * define('UNIFLOW_SMTP_PASSWORD', 'abcdefghijklmnop');
 */
define(
    'UNIFLOW_SMTP_PASSWORD',
    'zsyvwmpzoinbkhqn'
);


const UNIFLOW_SMTP_SENDER_NAME = 'UniFlow Administration';


/*
 * LOCAL UNIFlow LOGIN PAGE
 */
define(
    'UNIFLOW_LOGIN_URL',
    'http://localhost/Uniflow/login.php'
);

function uniflowPasswordChangeUrl(string $token): string
{
    return rtrim(dirname(UNIFLOW_LOGIN_URL), '/')
        . '/change_password.php?token='
        . rawurlencode($token);
}


/* =========================================================
   READ SMTP RESPONSE
========================================================= */

function smtpRead($socket): string
{
    $response = '';

    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;

        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }

    return $response;
}


/* =========================================================
   GET SMTP RESPONSE CODE
========================================================= */

function smtpCode(string $response): int
{
    return (int) substr(trim($response), 0, 3);
}


/* =========================================================
   SEND SMTP COMMAND
========================================================= */

function smtpCommand($socket, string $command, array $expectedCodes): void
{
    fwrite($socket, $command . "\r\n");

    $response = smtpRead($socket);

    if (!in_array(smtpCode($response), $expectedCodes, true)) {
        throw new RuntimeException(
            'SMTP command rejected with response: ' . trim($response)
        );
    }
}


/* =========================================================
   SEND EMAIL THROUGH GMAIL SMTP
========================================================= */

function gmailSendTransactionalEmail(
    string $to,
    string $toName,
    string $subject,
    string $html,
    string $text
): bool {

    /*
     * Check sender Gmail
     */
    if (!filter_var(UNIFLOW_SMTP_USERNAME, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException(
            'INVITATION_SEND_FAILED: sender Gmail address is invalid.'
        );
    }


    /*
     * Check App Password
     */
    if (
        UNIFLOW_SMTP_PASSWORD === '' ||
        UNIFLOW_SMTP_PASSWORD === 'YOUR_GMAIL_APP_PASSWORD' ||
        UNIFLOW_SMTP_PASSWORD === 'তোমার_Google_App_Password'
    ) {
        throw new RuntimeException(
            'INVITATION_SEND_FAILED: Gmail App Password is not configured.'
        );
    }


    /*
     * Check recipient email
     */
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException(
            'INVITATION_SEND_FAILED: Personal Email address is invalid.'
        );
    }


    /*
     * Check OpenSSL
     */
    if (!extension_loaded('openssl')) {
        throw new RuntimeException(
            'INVITATION_SEND_FAILED: PHP OpenSSL extension is not enabled.'
        );
    }


    /*
     * Connect to Gmail SMTP
     */
    $socket = @fsockopen(
        UNIFLOW_SMTP_HOST,
        UNIFLOW_SMTP_PORT,
        $errno,
        $errstr,
        20
    );


    if (!$socket) {
        throw new RuntimeException(
            'INVITATION_SEND_FAILED: Could not connect to Gmail SMTP. '
            . 'Check internet connection or whether port 587 is blocked.'
        );
    }


    stream_set_timeout($socket, 20);


    try {

        /* Gmail greeting */
        $greeting = smtpRead($socket);

        if (smtpCode($greeting) !== 220) {
            throw new RuntimeException(
                'Gmail SMTP greeting failed: ' . trim($greeting)
            );
        }


        /* EHLO */
        $hostname = $_SERVER['SERVER_NAME'] ?? 'localhost';

        $hostname = preg_replace(
            '/[^A-Za-z0-9.\-]/',
            '',
            $hostname
        );

        if ($hostname === '') {
            $hostname = 'localhost';
        }


        smtpCommand(
            $socket,
            'EHLO ' . $hostname,
            [250]
        );


        /* STARTTLS */
        smtpCommand(
            $socket,
            'STARTTLS',
            [220]
        );


        /* Enable TLS */
        $cryptoOk = stream_socket_enable_crypto(
            $socket,
            true,
            STREAM_CRYPTO_METHOD_TLS_CLIENT
        );


        if ($cryptoOk !== true) {
            throw new RuntimeException(
                'Gmail TLS/STARTTLS handshake failed.'
            );
        }


        /* EHLO again after TLS */
        smtpCommand(
            $socket,
            'EHLO ' . $hostname,
            [250]
        );


        /* =====================================================
           LOGIN
        ===================================================== */

        smtpCommand(
            $socket,
            'AUTH LOGIN',
            [334]
        );


        smtpCommand(
            $socket,
            base64_encode(UNIFLOW_SMTP_USERNAME),
            [334]
        );


        smtpCommand(
            $socket,
            base64_encode(UNIFLOW_SMTP_PASSWORD),
            [235]
        );


        /* =====================================================
           MAIL FROM
        ===================================================== */

        smtpCommand(
            $socket,
            'MAIL FROM:<' . UNIFLOW_SMTP_USERNAME . '>',
            [250]
        );


        /* =====================================================
           RECIPIENT
        ===================================================== */

        smtpCommand(
            $socket,
            'RCPT TO:<' . $to . '>',
            [250, 251]
        );


        /* =====================================================
           DATA
        ===================================================== */

        smtpCommand(
            $socket,
            'DATA',
            [354]
        );


        /* =====================================================
           EMAIL HEADERS
        ===================================================== */

        $encodedSubject =
            '=?UTF-8?B?' .
            base64_encode($subject) .
            '?=';


        $encodedName =
            '=?UTF-8?B?' .
            base64_encode($toName) .
            '?=';


        $boundary =
            '=_UniFlow_' .
            bin2hex(random_bytes(12));


        $headers = [];


        $headers[] =
            'From: ' .
            UNIFLOW_SMTP_SENDER_NAME .
            ' <' .
            UNIFLOW_SMTP_USERNAME .
            '>';


        $headers[] =
            'To: ' .
            $encodedName .
            ' <' .
            $to .
            '>';


        $headers[] =
            'Subject: ' .
            $encodedSubject;


        $headers[] =
            'Date: ' .
            date(DATE_RFC2822);


        $headers[] =
            'Message-ID: <' .
            bin2hex(random_bytes(16)) .
            '@localhost>';


        $headers[] =
            'MIME-Version: 1.0';


        $headers[] =
            'Content-Type: multipart/alternative; boundary="' .
            $boundary .
            '"';


        /* =====================================================
           EMAIL BODY
        ===================================================== */

        $body =
            implode("\r\n", $headers) .
            "\r\n\r\n";


        /* Plain text version */
        $body .=
            '--' .
            $boundary .
            "\r\n";


        $body .=
            "Content-Type: text/plain; charset=UTF-8\r\n";


        $body .=
            "Content-Transfer-Encoding: base64\r\n\r\n";


        $body .=
            chunk_split(
                base64_encode($text)
            ) .
            "\r\n";


        /* HTML version */
        $body .=
            '--' .
            $boundary .
            "\r\n";


        $body .=
            "Content-Type: text/html; charset=UTF-8\r\n";


        $body .=
            "Content-Transfer-Encoding: base64\r\n\r\n";


        $body .=
            chunk_split(
                base64_encode($html)
            ) .
            "\r\n";


        /* End MIME */
        $body .=
            '--' .
            $boundary .
            "--\r\n";


        /* SMTP dot-stuffing */
        $body = preg_replace(
            '/(^|\r\n)\./',
            '$1..',
            $body
        );


        fwrite(
            $socket,
            $body . "\r\n.\r\n"
        );


        /* Server response */
        $response = smtpRead($socket);


        if (smtpCode($response) !== 250) {
            throw new RuntimeException(
                'Gmail rejected the message: ' .
                trim($response)
            );
        }


        /* Quit */
        smtpCommand(
            $socket,
            'QUIT',
            [221]
        );


        fclose($socket);

        return true;


    } catch (Throwable $e) {

        fclose($socket);

        throw new RuntimeException(
            'INVITATION_SEND_FAILED: ' .
            $e->getMessage(),
            0,
            $e
        );
    }
}


/* =========================================================
   ADMIN LOGIN DETAILS EMAIL
========================================================= */

function sendAdminCredentialsEmail(
    string $personalEmail,
    string $adminName,
    string $universityEmail,
    string $initialPassword,
    array $accessAreas,
    string $passwordChangeUrl
): bool {


    /* =====================================================
       PORTAL NAMES
    ===================================================== */

    $names = [];


    foreach ($accessAreas as $area) {

        $names[] = [

            'technical' =>
                'Technical Portal',

            'administrative' =>
                'Administrative Portal',

            'proctorial' =>
                'Proctorial Portal',

            'lost_found' =>
                'Lost & Found Moderator Permission'

        ][$area] ?? $area;
    }


    $portalText =
        implode(', ', $names);


    /* =====================================================
       SAFE HTML VALUES
    ===================================================== */

    $safeName =
        htmlspecialchars(
            $adminName,
            ENT_QUOTES,
            'UTF-8'
        );


    $safeUniversityEmail =
        htmlspecialchars(
            $universityEmail,
            ENT_QUOTES,
            'UTF-8'
        );


    $safeInitialPassword =
        htmlspecialchars(
            $initialPassword,
            ENT_QUOTES,
            'UTF-8'
        );


    $safePortals =
        htmlspecialchars(
            $portalText,
            ENT_QUOTES,
            'UTF-8'
        );


    $safeLoginUrl =
        htmlspecialchars(
            UNIFLOW_LOGIN_URL,
            ENT_QUOTES,
            'UTF-8'
        );


    $safePasswordChangeUrl =
        htmlspecialchars(
            $passwordChangeUrl,
            ENT_QUOTES,
            'UTF-8'
        );


    /* =====================================================
       HTML EMAIL
    ===================================================== */

    $html =
        "<!doctype html>" .
        "<html>" .
        "<body style='margin:0;background:#fff7f0;font-family:Arial,sans-serif;color:#2b211c'>" .

        "<div style='max-width:620px;margin:30px auto;background:#fff;border:1px solid #f1dfd1;border-radius:18px;overflow:hidden'>" .

        "<div style='background:#ff6a00;color:#fff;padding:26px 30px'>" .

        "<h2 style='margin:0'>Welcome to UniFlow</h2>" .

        "<p style='margin:8px 0 0'>Staff Account Login Details</p>" .

        "</div>" .

        "<div style='padding:30px'>" .

        "<p>Hello <strong>{$safeName}</strong>,</p>" .

        "<p>" .
        "A UniFlow staff account has been created for you. " .
        "Use the following details to sign in:" .
        "</p>" .

        "<div style='background:#fff7ed;border:1px solid #fed7aa;border-radius:12px;padding:18px;margin:20px 0'>" .

        "<p>" .
        "<strong>University Login Email</strong><br>" .
        "{$safeUniversityEmail}" .
        "</p>" .

        "<p>" .
        "<strong>Password</strong><br>" .
        "<code style='font-size:16px'>" .
        "{$safeInitialPassword}" .
        "</code>" .
        "</p>" .

        "<p>" .
        "<strong>Assigned UniFlow Permissions</strong><br>" .
        "{$safePortals}" .
        "</p>" .

        "</div>" .

        "<p>" .
        "Your personal email is only used to receive these login details. " .
        "Do not use the personal email as your UniFlow login email." .
        "</p>" .

        "<p style='text-align:center;margin:28px 0'>" .

        "<a href='{$safeLoginUrl}' " .
        "style='display:inline-block;background:#ff6a00;color:#fff;text-decoration:none;padding:14px 24px;border-radius:10px;font-weight:bold'>" .

        "Sign in to UniFlow" .

        "</a>" .

        "</p>" .

        "<p>You can keep this password, or choose another one using the optional link below. The link expires in 7 days; until you use it, this password continues to work.</p>" .

        "<p style='text-align:center;margin:22px 0'><a href='{$safePasswordChangeUrl}' style='display:inline-block;background:#fff;color:#e95700;border:1px solid #ffb981;text-decoration:none;padding:12px 20px;border-radius:10px;font-weight:bold'>Choose a different password</a></p>" .

        "</div>" .

        "</div>" .

        "</body>" .
        "</html>";


    /* =====================================================
       PLAIN TEXT EMAIL
    ===================================================== */

    $text =
        "UniFlow Staff Account Login Details\n\n" .

        "Hello {$adminName},\n\n" .

        "University Login Email: {$universityEmail}\n" .

        "Password: {$initialPassword}\n" .

        "Assigned UniFlow Permissions: {$portalText}\n\n" .

        "Sign in here:\n" .
        UNIFLOW_LOGIN_URL .
        "\n\n" .

        "You can keep this password, or choose another one using this optional link within 7 days:\n" .
        $passwordChangeUrl .
        "\n\n" .

        "Use your University Email as the login email. " .
        "Your personal email is only the delivery address.";


    /* =====================================================
       SEND TO PERSONAL EMAIL
    ===================================================== */

    return gmailSendTransactionalEmail(

        /*
         * IMPORTANT:
         * This is the Personal Email entered
         * during staff account setup.
         */
        $personalEmail,

        $adminName,

        'Your UniFlow Staff Account Login Details',

        $html,

        $text
    );
}
// Student delivery email. Uses the same configured UniFlow sender Gmail.
if (!function_exists('sendStudentCredentialsEmail')) {
    function sendStudentCredentialsEmail(
        string $personalEmail,
        string $studentName,
        string $studentId,
        string $universityEmail,
        string $initialPassword,
        string $passwordChangeUrl
    ): bool {
        $safeName = htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8');
        $safeId = htmlspecialchars($studentId, ENT_QUOTES, 'UTF-8');
        $safeUniversityEmail = htmlspecialchars($universityEmail, ENT_QUOTES, 'UTF-8');
        $safePassword = htmlspecialchars($initialPassword, ENT_QUOTES, 'UTF-8');
        $safeLoginUrl = htmlspecialchars(UNIFLOW_LOGIN_URL, ENT_QUOTES, 'UTF-8');
        $safePasswordChangeUrl = htmlspecialchars($passwordChangeUrl, ENT_QUOTES, 'UTF-8');

        $html = "<!doctype html><html><body style='margin:0;background:#fff7f0;font-family:Arial,sans-serif;color:#2b211c'>"
            . "<div style='max-width:620px;margin:30px auto;background:#fff;border:1px solid #f1dfd1;border-radius:18px;overflow:hidden'>"
            . "<div style='background:#ff6a00;color:#fff;padding:26px 30px'><h2 style='margin:0'>Welcome to UniFlow</h2><p style='margin:8px 0 0'>Student Login Details</p></div>"
            . "<div style='padding:30px'><p>Hello <strong>{$safeName}</strong>,</p>"
            . "<p>Your UniFlow student login account has been created. It provides access to UniFlow and does not represent university admission or enrollment.</p>"
            . "<div style='background:#fff7ed;border:1px solid #fed7aa;border-radius:12px;padding:18px;margin:20px 0'>"
            . "<p><strong>Student ID</strong><br>{$safeId}</p>"
            . "<p><strong>University Login Email</strong><br>{$safeUniversityEmail}</p>"
            . "<p><strong>Password</strong><br><code style='font-size:16px'>{$safePassword}</code></p></div>"
            . "<p>Your Personal Email is only the delivery address. Use the University Email above to log in.</p>"
            . "<p style='text-align:center;margin:28px 0'><a href='{$safeLoginUrl}' style='display:inline-block;background:#ff6a00;color:#fff;text-decoration:none;padding:14px 24px;border-radius:10px;font-weight:bold'>Sign in to UniFlow</a></p>"
            . "<p>You can keep this password, or choose another one using the optional link below. The link expires in 7 days; until you use it, this password continues to work.</p>"
            . "<p style='text-align:center;margin:22px 0'><a href='{$safePasswordChangeUrl}' style='display:inline-block;background:#fff;color:#e95700;border:1px solid #ffb981;text-decoration:none;padding:12px 20px;border-radius:10px;font-weight:bold'>Choose a different password</a></p></div></div></body></html>";

        $text = "UniFlow Student Login Details\n\n"
            . "Hello {$studentName},\n\n"
            . "Student ID: {$studentId}\n"
            . "University Login Email: {$universityEmail}\n"
            . "Password: {$initialPassword}\n\n"
            . "This account provides access to UniFlow and does not represent university admission or enrollment.\n\n"
            . "Sign in here: " . UNIFLOW_LOGIN_URL . "\n\n"
            . "Keep the emailed password or choose another one using this optional link within 7 days:\n"
            . $passwordChangeUrl . "\n\n"
            . "Use your University Email as the login email. Your Personal Email is only the delivery address.";

        return gmailSendTransactionalEmail(
            $personalEmail,
            $studentName,
            'Your UniFlow Student Login Details',
            $html,
            $text
        );
    }
}
