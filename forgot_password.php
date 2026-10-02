<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/email.php';
header('Cache-Control: no-store, max-age=0');

$email = '';
$message = '';
$error = '';

if (empty($_SESSION['forgot_password_csrf'])) {
    $_SESSION['forgot_password_csrf'] = bin2hex(random_bytes(32));
}

function uniFlowPasswordResetAllowed(mysqli $conn, string $email): bool
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $normalizedEmail = strtolower($email);
    $rateKeys = [
        'email:' . hash('sha256', $normalizedEmail),
        'ip:' . hash('sha256', $ip),
        'origin:' . hash('sha256', $ip . "\0" . $normalizedEmail)
    ];
    sort($rateKeys, SORT_STRING);

    try {
        $conn->query(
            'CREATE TABLE IF NOT EXISTS password_reset_rate_limits (
                rate_key VARCHAR(80) NOT NULL PRIMARY KEY,
                last_requested_at DATETIME NOT NULL,
                INDEX idx_password_reset_rate_time (last_requested_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $conn->begin_transaction();
        $insert = $conn->prepare(
            "INSERT IGNORE INTO password_reset_rate_limits (rate_key, last_requested_at)
             VALUES (?, '2000-01-01 00:00:00')"
        );
        if (!$insert) {
            throw new RuntimeException('Could not prepare password reset rate limit.');
        }
        foreach ($rateKeys as $rateKey) {
            $insert->bind_param('s', $rateKey);
            if (!$insert->execute()) {
                throw new RuntimeException('Could not save password reset rate limit.');
            }
        }
        $insert->close();

        $check = $conn->prepare(
            'SELECT last_requested_at >= DATE_SUB(NOW(), INTERVAL 60 SECOND) AS is_recent
             FROM password_reset_rate_limits
             WHERE rate_key = ?
             FOR UPDATE'
        );
        if (!$check) {
            throw new RuntimeException('Could not check password reset rate limit.');
        }
        $allowed = true;
        foreach ($rateKeys as $rateKey) {
            $check->bind_param('s', $rateKey);
            if (!$check->execute()) {
                throw new RuntimeException('Could not check password reset rate limit.');
            }
            $row = $check->get_result()->fetch_assoc();
            if (!$row || (int)$row['is_recent'] === 1) {
                $allowed = false;
            }
        }
        $check->close();

        if ($allowed) {
            $update = $conn->prepare(
                'UPDATE password_reset_rate_limits
                 SET last_requested_at = NOW()
                 WHERE rate_key = ?'
            );
            if (!$update) {
                throw new RuntimeException('Could not update password reset rate limit.');
            }
            foreach ($rateKeys as $rateKey) {
                $update->bind_param('s', $rateKey);
                if (!$update->execute()) {
                    throw new RuntimeException('Could not update password reset rate limit.');
                }
            }
            $update->close();
        }

        $conn->commit();
        try {
            $conn->query(
                'DELETE FROM password_reset_rate_limits
                 WHERE last_requested_at < DATE_SUB(NOW(), INTERVAL 1 DAY)
                 LIMIT 500'
            );
        } catch (Throwable $cleanupError) {
            error_log('UniFlow password reset rate limit cleanup failed: ' . $cleanupError->getMessage());
        }
        return $allowed;
    } catch (Throwable $exception) {
        try {
            $conn->rollback();
        } catch (Throwable $ignored) {
        }
        error_log('UniFlow password reset rate limit failed: ' . $exception->getMessage());

        $sessionKey = 'forgot_password_last_request';
        $lastRequest = (int)($_SESSION[$sessionKey] ?? 0);
        if (time() - $lastRequest < 60) {
            return false;
        }
        $_SESSION[$sessionKey] = time();
        return true;
    }
}

function uniFlowSendPasswordReset(mysqli $conn, string $accountType, array $account): void
{
    $table = $accountType === 'student' ? 'students' : 'admins';
    $idColumn = $accountType === 'student' ? 'student_id' : 'admin_id';
    $accountId = (int)($account[$idColumn] ?? 0);
    if ($accountId <= 0) {
        return;
    }

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $update = $conn->prepare(
        "UPDATE {$table}
         SET password_change_token_hash = ?,
             password_change_expires_at = DATE_ADD(NOW(), INTERVAL 60 MINUTE)
         WHERE {$idColumn} = ?" . ($accountType === 'admin' ? ' AND is_active = 1' : '')
    );
    if (!$update) {
        throw new RuntimeException('Could not prepare password reset token.');
    }
    $update->bind_param('si', $tokenHash, $accountId);
    if (!$update->execute() || $update->affected_rows !== 1) {
        $update->close();
        return;
    }
    $update->close();

    $recipient = trim((string)($account['personal_email'] ?? ''));
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        $recipient = trim((string)($account['email'] ?? ''));
    }
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        $recipient = '';
    }

    try {
        if ($recipient === '') {
            throw new RuntimeException('No valid password reset delivery email is configured.');
        }

        $resetUrl = uniflowPasswordChangeUrl($token) . '&mode=reset';
        $safeName = htmlspecialchars((string)($account['name'] ?? 'UniFlow user'), ENT_QUOTES, 'UTF-8');
        $safeLoginEmail = htmlspecialchars((string)($account['email'] ?? ''), ENT_QUOTES, 'UTF-8');
        $safeResetUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');
        $html = "<!doctype html><html><body style='margin:0;background:#fff7f0;font-family:Arial,sans-serif;color:#2b211c'>"
            . "<div style='max-width:620px;margin:30px auto;background:#fff;border:1px solid #f1dfd1;border-radius:18px;overflow:hidden'>"
            . "<div style='background:#f97316;color:#fff;padding:24px 30px'><h2 style='margin:0'>Reset your UniFlow password</h2></div>"
            . "<div style='padding:30px'><p>Hello <strong>{$safeName}</strong>,</p>"
            . "<p>A password reset was requested for the UniFlow login email <strong>{$safeLoginEmail}</strong>.</p>"
            . "<p style='text-align:center;margin:28px 0'><a href='{$safeResetUrl}' style='display:inline-block;background:#f97316;color:#fff;text-decoration:none;padding:14px 24px;border-radius:10px;font-weight:bold'>Choose a New Password</a></p>"
            . "<p>This one-time link expires in 60 minutes. If you did not request a reset, ignore this email; your current password will remain unchanged.</p>"
            . "</div></div></body></html>";
        $text = "Reset your UniFlow password\n\n"
            . "Hello {$account['name']},\n\n"
            . "A password reset was requested for the UniFlow login email {$account['email']}.\n\n"
            . "Choose a new password using this one-time link (expires in 60 minutes):\n"
            . $resetUrl . "\n\n"
            . "If you did not request a reset, ignore this email. Your current password will remain unchanged.";

        if (!gmailSendTransactionalEmail(
            $recipient,
            (string)($account['name'] ?? 'UniFlow user'),
            'Reset your UniFlow password',
            $html,
            $text
        )) {
            throw new RuntimeException('The password reset email was not sent.');
        }
    } catch (Throwable $exception) {
        $clear = $conn->prepare(
            "UPDATE {$table}
             SET password_change_token_hash = NULL, password_change_expires_at = NULL
             WHERE {$idColumn} = ? AND password_change_token_hash = ?"
        );
        if ($clear) {
            $clear->bind_param('is', $accountId, $tokenHash);
            $clear->execute();
            $clear->close();
        }
        error_log('UniFlow password reset email failed for account ID ' . $accountId . ': ' . $exception->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedEmail = $_POST['email'] ?? '';
    $email = is_string($postedEmail) ? trim($postedEmail) : '';
    $postedCsrf = $_POST['csrf_token'] ?? '';

    if (!is_string($postedCsrf)
        || !hash_equals((string)$_SESSION['forgot_password_csrf'], $postedCsrf)) {
        $error = 'The request expired. Reload this page and try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid University Login Email.';
    } else {
        $message = 'If an active UniFlow account matches that login email, reset instructions will be sent to its saved delivery email.';

        if (uniFlowPasswordResetAllowed($conn, $email)) {
            try {
                $matches = [];
                $studentStmt = $conn->prepare(
                    'SELECT student_id, name, email, personal_email
                     FROM students WHERE email = ? LIMIT 1'
                );
                if ($studentStmt) {
                    $studentStmt->bind_param('s', $email);
                    $studentStmt->execute();
                    $student = $studentStmt->get_result()->fetch_assoc();
                    $studentStmt->close();
                    if ($student) {
                        $matches[] = ['student', $student];
                    }
                }

                $adminStmt = $conn->prepare(
                    'SELECT admin_id, name, email, personal_email, is_active
                     FROM admins WHERE email = ? AND is_active = 1 LIMIT 1'
                );
                if ($adminStmt) {
                    $adminStmt->bind_param('s', $email);
                    $adminStmt->execute();
                    $admin = $adminStmt->get_result()->fetch_assoc();
                    $adminStmt->close();
                    if ($admin) {
                        $matches[] = ['admin', $admin];
                    }
                }

                foreach ($matches as [$accountType, $account]) {
                    uniFlowSendPasswordReset($conn, $accountType, $account);
                }
            } catch (Throwable $exception) {
                error_log('UniFlow password reset request failed: ' . $exception->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | UniFlow</title>
    <link rel="stylesheet" href="css/buttons.css">
    <style>
        *{box-sizing:border-box}
        body{min-height:100vh;margin:0;padding:28px;display:grid;place-items:center;font-family:Inter,Arial,sans-serif;color:#2b211c;background:radial-gradient(circle at 12% 18%,rgba(249,115,22,.18),transparent 27%),radial-gradient(circle at 90% 80%,rgba(234,88,12,.12),transparent 25%),linear-gradient(135deg,#fffefb,#fff4e7)}
        .reset-card{width:min(100%,520px);padding:34px;border:1px solid #f1dfd1;border-radius:22px;background:rgba(255,255,255,.96);box-shadow:0 8px 0 #f0dfd2,0 24px 58px rgba(89,43,13,.12)}
        .brand{display:inline-flex;align-items:center;gap:10px;margin-bottom:26px;color:#2b211c;font-weight:900;text-decoration:none}
        .brand-mark{width:40px;height:40px;display:grid;place-items:center;border-radius:13px;background:linear-gradient(145deg,#ff8a3d,#e65100);color:#fff;box-shadow:0 5px 0 #c84400;font-weight:900}
        h1{margin:0 0 10px;font-size:27px;letter-spacing:-.5px}
        .intro{margin:0 0 22px;color:#806f64;font-size:14px;line-height:1.65}
        label{display:block;margin-bottom:8px;font-size:13px;font-weight:800}
        input[type=email]{width:100%;min-height:48px;padding:12px 14px;border:1px solid #ead8cc;border-radius:12px;background:#fff;font:500 14px Inter,Arial,sans-serif;color:#2b211c}
        input[type=email]:focus{outline:3px solid rgba(249,115,22,.2);border-color:#f97316}
        .notice{margin:0 0 18px;padding:13px 15px;border:1px solid #fed7aa;border-radius:12px;background:#fff7ed;color:#7c3e17;font-size:13px;line-height:1.55}
        .error{border-color:#f4c7c3;background:#fff2f0;color:#a3312b}
        .actions{display:flex;align-items:center;gap:12px;margin-top:22px}
        .actions button,.actions a{min-height:46px;display:inline-flex;align-items:center;justify-content:center}
        .actions button{flex:1}
        .back-link{padding:11px 16px;border:1px solid #f1dfd1;border-radius:13px;background:#fff;color:#2b211c;font-size:13px;font-weight:800;text-decoration:none;box-shadow:0 5px 0 #f0dfd2}
        .back-link:hover{color:#c2410c;border-color:#ffd5b6}
        @media(max-width:520px){body{padding:16px}.reset-card{padding:24px}.actions{align-items:stretch;flex-direction:column-reverse}.actions>*{width:100%}}
    </style>
</head>
<body>
    <main class="reset-card">
        <a class="brand" href="login.php"><span class="brand-mark" aria-hidden="true">U</span>UniFlow</a>
        <h1>Forgot your password?</h1>
        <p class="intro">Enter the University Login Email for your student or staff account. We’ll send a one-time reset link to the delivery email saved for that account.</p>

        <?php if ($error !== ''): ?>
            <div class="notice error" role="alert"><?= e($error) ?></div>
        <?php elseif ($message !== ''): ?>
            <div class="notice" role="status"><?= e($message) ?></div>
        <?php endif; ?>

        <form method="post" action="forgot_password.php" autocomplete="on">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['forgot_password_csrf']) ?>">
            <label for="email">University Login Email</label>
            <input id="email" type="email" name="email" value="<?= e($email) ?>" autocomplete="email" placeholder="name@university.edu" required>
            <div class="actions">
                <button type="submit">Send Reset Link</button>
                <a class="back-link" href="login.php">Back to Sign In</a>
            </div>
        </form>
    </main>
</body>
</html>
