<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
header('Cache-Control: no-store, max-age=0');

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$resetMode = (($_GET['mode'] ?? $_POST['mode'] ?? '') === 'reset');
$tokenHash = $token !== '' ? hash('sha256', $token) : '';
$tokenPurpose = '';
$accountType = '';
$account = null;
$error = '';
$success = '';

if ($token !== '') {
    // Optional password-change links work for student and administrator accounts.
    $stmt = $conn->prepare(
        'SELECT student_id, name, email, student_code
         FROM students
         WHERE password_change_token_hash = ?
           AND password_change_expires_at >= NOW()
         LIMIT 1'
    );
    $stmt->bind_param('s', $tokenHash);
    $stmt->execute();
    $account = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($account) {
        $accountType = 'student';
        $tokenPurpose = $resetMode ? 'reset' : 'optional';
    }

    if (!$account) {
        $stmt = $conn->prepare(
            'SELECT admin_id, name, email, role, admin_type, is_active
             FROM admins
             WHERE password_change_token_hash = ?
               AND password_change_expires_at >= NOW()
               AND is_active = 1
             LIMIT 1'
        );
        $stmt->bind_param('s', $tokenHash);
        $stmt->execute();
        $account = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($account) {
            $accountType = 'admin';
            $tokenPurpose = $resetMode ? 'reset' : 'optional';
        }
    }

    // Keep existing mandatory first-login administrator invitations working.
    if (!$account) {
        $stmt = $conn->prepare(
            'SELECT admin_id, name, email, role, admin_type, activation_expires_at, activation_used
             FROM admins
             WHERE activation_token_hash = ?
               AND is_active = 1
             LIMIT 1'
        );
        $stmt->bind_param('s', $tokenHash);
        $stmt->execute();
        $account = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($account
            && (int)$account['activation_used'] === 0
            && !empty($account['activation_expires_at'])
            && strtotime($account['activation_expires_at']) >= time()) {
            $accountType = 'admin';
            $tokenPurpose = 'invitation';
        } else {
            $account = null;
        }
    }

    if (!$account) {
        $error = $resetMode
            ? 'This reset link is invalid, expired, or already used. Request a new password reset link.'
            : 'This password link is invalid, expired, or already used. Your current password still works if you chose not to change it.';
    }
} elseif (isset($_SESSION['admin_id'])) {
    $adminId = (int)$_SESSION['admin_id'];
    $stmt = $conn->prepare(
        'SELECT admin_id, name, email, role, admin_type, must_change_password
         FROM admins WHERE admin_id = ? AND is_active = 1 LIMIT 1'
    );
    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $account = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$account) {
        session_destroy();
        header('Location: login.php');
        exit();
    }
    $accountType = 'admin';
    $tokenPurpose = 'session';
} elseif (isset($_SESSION['student_id'])) {
    $studentId = (int)$_SESSION['student_id'];
    $stmt = $conn->prepare(
        'SELECT student_id, name, email, student_code
         FROM students WHERE student_id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $account = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$account) {
        session_destroy();
        header('Location: login.php');
        exit();
    }
    $accountType = 'student';
    $tokenPurpose = 'session';
} else {
    header('Location: login.php');
    exit();
}

if (empty($_SESSION['password_change_csrf'])) {
    $_SESSION['password_change_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $account) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!is_string($csrfToken)
        || !hash_equals((string)$_SESSION['password_change_csrf'], $csrfToken)) {
        $error = 'Security token expired. Reload the page and try again.';
    } else {
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if (strlen($newPassword) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } else {
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

            if ($tokenPurpose === 'optional' && $accountType === 'student') {
                $stmt = $conn->prepare(
                    'UPDATE students
                     SET password = ?, password_change_token_hash = NULL, password_change_expires_at = NULL
                     WHERE student_id = ? AND password_change_token_hash = ? AND password_change_expires_at >= NOW()'
                );
                $stmt->bind_param('sis', $passwordHash, $account['student_id'], $tokenHash);
            } elseif ($tokenPurpose === 'optional' && $accountType === 'admin') {
                $stmt = $conn->prepare(
                    'UPDATE admins
                     SET password = ?, password_change_token_hash = NULL, password_change_expires_at = NULL
                     WHERE admin_id = ? AND password_change_token_hash = ? AND password_change_expires_at >= NOW() AND is_active = 1'
                );
                $stmt->bind_param('sis', $passwordHash, $account['admin_id'], $tokenHash);
            } elseif ($tokenPurpose === 'reset' && $accountType === 'student') {
                $stmt = $conn->prepare(
                    'UPDATE students
                     SET password = ?, password_change_token_hash = NULL, password_change_expires_at = NULL
                     WHERE student_id = ? AND password_change_token_hash = ? AND password_change_expires_at >= NOW()'
                );
                $stmt->bind_param('sis', $passwordHash, $account['student_id'], $tokenHash);
            } elseif ($tokenPurpose === 'reset' && $accountType === 'admin') {
                $used = 1;
                $stmt = $conn->prepare(
                    'UPDATE admins
                     SET password = ?, must_change_password = 0, password_expires_at = NULL,
                         activation_token_hash = NULL, activation_expires_at = NULL,
                         activation_used = ?, password_change_token_hash = NULL,
                         password_change_expires_at = NULL
                     WHERE admin_id = ? AND password_change_token_hash = ?
                       AND password_change_expires_at >= NOW() AND is_active = 1'
                );
                $stmt->bind_param('siis', $passwordHash, $used, $account['admin_id'], $tokenHash);
            } elseif ($tokenPurpose === 'invitation') {
                $used = 1;
                $stmt = $conn->prepare(
                    'UPDATE admins
                     SET password = ?, must_change_password = 0, password_expires_at = NULL,
                         activation_token_hash = NULL, activation_expires_at = NULL,
                         activation_used = ?, password_change_token_hash = NULL,
                         password_change_expires_at = NULL
                     WHERE admin_id = ? AND activation_token_hash = ? AND activation_used = 0
                       AND activation_expires_at >= NOW() AND is_active = 1'
                );
                $stmt->bind_param('siis', $passwordHash, $used, $account['admin_id'], $tokenHash);
            } elseif ($accountType === 'student') {
                $stmt = $conn->prepare(
                    'UPDATE students
                     SET password = ?, password_change_token_hash = NULL, password_change_expires_at = NULL
                     WHERE student_id = ?'
                );
                $stmt->bind_param('si', $passwordHash, $account['student_id']);
            } else {
                $used = 1;
                $stmt = $conn->prepare(
                    'UPDATE admins
                     SET password = ?, must_change_password = 0, password_expires_at = NULL,
                         activation_token_hash = NULL, activation_expires_at = NULL,
                         activation_used = ?, password_change_token_hash = NULL,
                         password_change_expires_at = NULL
                     WHERE admin_id = ? AND is_active = 1'
                );
                $stmt->bind_param('sii', $passwordHash, $used, $account['admin_id']);
            }

            if ($stmt && $stmt->execute() && $stmt->affected_rows === 1) {
                $stmt->close();

                if ($tokenPurpose === 'optional') {
                    $success = 'Password changed. Sign in with your University Email and the new password. Your current password remains active unless and until you submit this form.';
                    $account = null;
                } elseif ($tokenPurpose === 'reset') {
                    $success = 'Password reset successfully. Sign in with your University Login Email and new password.';
                    $account = null;
                } elseif ($tokenPurpose === 'invitation') {
                    session_regenerate_id(true);
                    $_SESSION['admin_id'] = (int)$account['admin_id'];
                    $_SESSION['admin_name'] = $account['name'];
                    $_SESSION['admin_role'] = $account['role'];
                    $_SESSION['admin_type'] = $account['admin_type'];
                    $_SESSION['user_type'] = 'admin';
                    if (($_SESSION['return_to'] ?? '') === 'lost_found') {
                        unset($_SESSION['return_to']);
                        header('Location: lost_found/index.php');
                        exit();
                    }
                    if (($account['admin_type'] ?? '') === 'main_admin') {
                        header('Location: system_admin/dashboard.php');
                        exit();
                    }
                    $areas = getAdminAccessAreas($conn, (int)$account['admin_id']);
                    $target = count($areas) === 1 ? $areas[0] : ($account['role'] ?? 'technical');
                    $url = match ($target) {
                        'technical' => 'technical/dashboard.php',
                        'administrative' => 'administrative/dashboard.php',
                        'proctorial' => 'proctorial/dashboard.php',
                        'lost_found' => 'lost_found/index.php',
                        default => 'index.php'
                    };
                    header('Location: ' . $url);
                    exit();
                } elseif ($accountType === 'student') {
                    header('Location: student/dashboard.php');
                    exit();
                } else {
                    if (($_SESSION['return_to'] ?? '') === 'lost_found') {
                        unset($_SESSION['return_to']);
                        header('Location: lost_found/index.php');
                        exit();
                    }
                    if (($account['admin_type'] ?? '') === 'main_admin') {
                        header('Location: system_admin/dashboard.php');
                        exit();
                    }
                    $areas = getAdminAccessAreas($conn, (int)$account['admin_id']);
                    $target = count($areas) === 1 ? $areas[0] : ($account['role'] ?? 'technical');
                    $url = match ($target) {
                        'technical' => 'technical/dashboard.php',
                        'administrative' => 'administrative/dashboard.php',
                        'proctorial' => 'proctorial/dashboard.php',
                        'lost_found' => 'lost_found/index.php',
                        default => 'index.php'
                    };
                    header('Location: ' . $url);
                    exit();
                }
            } else {
                if ($stmt) $stmt->close();
                $error = 'The password link may have expired or been used already. Reload the page or continue with your current password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Change Password | UniFlow</title>
<style>
body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;box-sizing:border-box;font-family:Arial,sans-serif;background:linear-gradient(135deg,#fff8f2,#fff,#fff2e7);color:#2b211c}.card{width:min(100%,520px);box-sizing:border-box;background:#fff;border:1px solid #f1dfd1;border-radius:22px;padding:36px;box-shadow:0 25px 70px rgba(96,48,15,.12)}h1{margin:0 0 8px}p{color:#806f64;line-height:1.6}.notice{background:#fff7ed;border:1px solid #fed7aa;padding:14px;border-radius:12px;margin:18px 0}.error,.success{padding:13px;border-radius:10px;margin:18px 0}.error{background:#fff0f0;border:1px solid #ffc5c5;color:#b42318}.success{background:#eef9f0;border:1px solid #bce3c3;color:#24653a}label{display:block;font-weight:700;margin:16px 0 7px}input{width:100%;box-sizing:border-box;padding:14px;border:1px solid #ead8cc;border-radius:12px;font-size:15px}button,.button{width:100%;display:block;box-sizing:border-box;text-align:center;margin-top:22px;padding:15px;border:0;border-radius:12px;background:#ff6a00;color:#fff;text-decoration:none;font-weight:800;font-size:15px;cursor:pointer}.secondary{color:#e95700;text-align:center;display:block;margin-top:16px}
</style>
<link rel="stylesheet" href="css/buttons.css">
</head>
<body>
<main class="card">
    <h1>Change your UniFlow password</h1>
    <?php if ($account): ?>
        <p>Hello <strong><?= e($account['name']) ?></strong>. Choose a new password for your UniFlow account.</p>
        <div class="notice"><strong>University Login Email:</strong><br><?= e($account['email']) ?></div>
        <?php if ($tokenPurpose === 'optional'): ?>
            <p>This email link is optional. If you prefer the password provided by your university, close this page and keep using it. The link expires in 7 days.</p>
        <?php elseif ($tokenPurpose === 'reset'): ?>
            <p>Choose a new password below. This one-time reset link expires in 60 minutes.</p>
        <?php endif; ?>
    <?php endif; ?>
    <?php if ($error !== ''): ?><div class="error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success !== ''): ?>
        <div class="success" role="status"><?= e($success) ?></div>
        <a class="button" href="login.php">Go to sign in</a>
    <?php elseif ($account): ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['password_change_csrf']) ?>">
            <?php if ($token !== ''): ?><input type="hidden" name="token" value="<?= e($token) ?>"><?php endif; ?>
            <?php if ($resetMode): ?><input type="hidden" name="mode" value="reset"><?php endif; ?>
            <label for="new-password">New Password</label>
            <input id="new-password" type="password" name="new_password" minlength="8" autocomplete="new-password" required>
            <label for="confirm-password">Confirm Password</label>
            <input id="confirm-password" type="password" name="confirm_password" minlength="8" autocomplete="new-password" required>
            <button type="submit">Save New Password</button>
        </form>
        <?php if ($tokenPurpose === 'optional'): ?><a class="secondary" href="login.php">Keep current password and return to sign in</a><?php endif; ?>
    <?php else: ?>
        <?php if ($success === ''): ?><a class="button" href="login.php">Return to sign in</a><?php endif; ?>
    <?php endif; ?>
</main>
</body>
</html>
