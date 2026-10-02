<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

$error = '';
$success = '';
$accountType = 'student';
$name = '';
$studentCode = '';
$email = '';
$staffRole = 'technical';
$allowedStaffRoles = [
    'technical' => 'Technical',
    'administrative' => 'Administrative',
    'proctorial' => 'Proctorial',
    'lost_found' => 'Lost & Found',
];

if (empty($_SESSION['signup_csrf'])) {
    $_SESSION['signup_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accountType = (string)($_POST['account_type'] ?? '');
    $name = trim((string)($_POST['name'] ?? ''));
    $studentCode = trim((string)($_POST['student_code'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $staffRole = (string)($_POST['staff_role'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $passwordConfirmation = (string)($_POST['password_confirmation'] ?? '');
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!is_string($csrfToken) || !hash_equals($_SESSION['signup_csrf'], $csrfToken)) {
        $error = 'Your session expired. Refresh this page and try again.';
    } elseif (!in_array($accountType, ['student', 'staff'], true)) {
        $error = 'Choose Student or Staff as the account type.';
    } elseif ($name === '' || strlen($name) > 100) {
        $error = 'Enter your full name (up to 100 characters).';
    } elseif ($accountType === 'student' && ($studentCode === '' || strlen($studentCode) > 50)) {
        $error = 'Enter a valid Student ID (up to 50 characters).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        $error = 'Enter a valid university email address.';
    } elseif ($accountType === 'staff' && !isset($allowedStaffRoles[$staffRole])) {
        $error = 'Choose a valid staff portal.';
    } elseif (strlen($password) < 8) {
        $error = 'Choose a password with at least 8 characters.';
    } elseif ($password !== $passwordConfirmation) {
        $error = 'The password confirmation does not match.';
    } else {
        $studentCheck = $conn->prepare('SELECT student_id FROM students WHERE email = ? LIMIT 1');
        $studentCheck->bind_param('s', $email);
        $studentCheck->execute();
        $studentExists = $studentCheck->get_result()->num_rows > 0;
        $studentCheck->close();

        $adminCheck = $conn->prepare('SELECT admin_id FROM admins WHERE email = ? LIMIT 1');
        $adminCheck->bind_param('s', $email);
        $adminCheck->execute();
        $adminExists = $adminCheck->get_result()->num_rows > 0;
        $adminCheck->close();

        $studentCodeExists = false;
        if ($accountType === 'student') {
            $codeCheck = $conn->prepare('SELECT student_id FROM students WHERE student_code = ? LIMIT 1');
            $codeCheck->bind_param('s', $studentCode);
            $codeCheck->execute();
            $studentCodeExists = $codeCheck->get_result()->num_rows > 0;
            $codeCheck->close();
        }

        if ($studentExists || $adminExists) {
            $error = 'An account already uses this email. Sign in or use Forgot Password.';
        } elseif ($studentCodeExists) {
            $error = 'This Student ID is already registered.';
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            if ($accountType === 'student') {
                $stmt = $conn->prepare(
                    'INSERT INTO students (student_code, name, email, password) VALUES (?, ?, ?, ?)'
                );
                $stmt->bind_param('ssss', $studentCode, $name, $email, $passwordHash);
                try {
                    $stmt->execute();
                    $stmt->close();
                    unset($_SESSION['signup_csrf']);
                    $success = 'Your student account is ready. You can now sign in.';
                } catch (mysqli_sql_exception $exception) {
                    $stmt->close();
                    if ((int)$exception->getCode() === 1062) {
                        $error = 'This email or Student ID is already registered.';
                    } else {
                        error_log('[UniFlow] Student self-registration failed: ' . $exception->getMessage());
                        $error = 'Could not create your account. Please try again later.';
                    }
                }
            } else {
                $conn->begin_transaction();
                try {
                    $stmt = $conn->prepare(
                        "INSERT INTO admins (name, email, password, role, admin_type, is_active, can_manage_admins, must_change_password)
                         VALUES (?, ?, ?, ?, 'admin', 1, 0, 0)"
                    );
                    $stmt->bind_param('ssss', $name, $email, $passwordHash, $staffRole);
                    $stmt->execute();
                    $adminId = (int)$conn->insert_id;
                    $stmt->close();

                    $accessStmt = $conn->prepare(
                        'INSERT INTO admin_access (admin_id, access_area) VALUES (?, ?)'
                    );
                    $accessStmt->bind_param('is', $adminId, $staffRole);
                    $accessStmt->execute();
                    $accessStmt->close();
                    $conn->commit();
                    unset($_SESSION['signup_csrf']);
                    $success = 'Your staff account is ready. You can now sign in.';
                } catch (Throwable $exception) {
                    $conn->rollback();
                    error_log('[UniFlow] Staff self-registration failed: ' . $exception->getMessage());
                    if ($exception instanceof mysqli_sql_exception && (int)$exception->getCode() === 1062) {
                        $error = 'An account already uses this email.';
                    } else {
                        $error = 'Could not submit your staff account request. Please try again later.';
                    }
                }
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
    <title>Create Account | UniFlow</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        *{box-sizing:border-box} :root{--orange:#f97316;--dark:#c2410c;--ink:#2d1d16;--muted:#8b766b;--line:#f0dfd5}
        body{min-height:100vh;margin:0;padding:32px;display:flex;align-items:center;justify-content:center;overflow-x:hidden;color:var(--ink);font-family:Inter,"Segoe UI",sans-serif;background:radial-gradient(circle at 5% 10%,rgba(255,135,55,.13),transparent 25%),radial-gradient(circle at 98% 82%,rgba(249,115,22,.12),transparent 25%),linear-gradient(135deg,#fffdf9,#fff3e5)}
        body:before{content:"";position:fixed;inset:0;pointer-events:none;background-image:linear-gradient(rgba(154,52,18,.035) 1px,transparent 1px),linear-gradient(90deg,rgba(154,52,18,.035) 1px,transparent 1px);background-size:40px 40px;mask-image:linear-gradient(#000,transparent 90%)}
        .layout{position:relative;z-index:1;width:min(1120px,100%);display:grid;grid-template-columns:1fr 440px;align-items:center;gap:54px}
        .intro{padding:20px 0}.brand{display:flex;align-items:center;gap:12px;font-family:"Plus Jakarta Sans",sans-serif;font-weight:900;font-size:22px}.mark{width:48px;height:48px;display:grid;place-items:center;border-radius:15px;color:#fff;background:linear-gradient(145deg,#fb923c,#c2410c);box-shadow:0 6px 0 #a93807,0 14px 25px #ea580c33;font-size:20px}.brand small{display:block;margin-top:3px;color:var(--muted);font:800 9px Inter,sans-serif;letter-spacing:.08em}.eyebrow{display:inline-flex;margin-top:42px;padding:9px 13px;border:1px solid #fed7aa;border-radius:999px;background:#fffaf4;color:var(--dark);font-size:10px;font-weight:900;letter-spacing:.06em;text-transform:uppercase}.intro h1{max-width:560px;margin:22px 0 16px;font:900 clamp(38px,5vw,58px)/1.12 "Plus Jakarta Sans",sans-serif;letter-spacing:-.05em}.intro h1 span{display:block;color:#e85a0c}.intro p{max-width:530px;color:var(--muted);font-size:14px;line-height:1.8}.pills{display:flex;flex-wrap:wrap;gap:9px;margin-top:25px}.pill{padding:10px 12px;border:1px solid var(--line);border-radius:12px;background:#fff;font-size:11px;font-weight:700;color:#7a6257}.pill:before{content:"";display:inline-block;width:6px;height:6px;margin-right:8px;border-radius:50%;background:var(--orange)}
        .card{padding:30px;border:1px solid #ffffff;border-radius:28px;background:rgba(255,255,255,.91);box-shadow:0 28px 75px rgba(79,44,23,.14),inset 0 1px #fff}.card-brand{display:flex;justify-content:center;align-items:center;gap:10px;margin-bottom:19px;font:900 21px "Plus Jakarta Sans",sans-serif}.card-brand .mark{width:38px;height:38px;border-radius:12px;font-size:16px}.badge{width:max-content;margin:auto;padding:7px 12px;border:1px solid #fed7aa;border-radius:999px;color:var(--dark);background:#fffaf4;font-size:9px;font-weight:900;letter-spacing:.06em;text-transform:uppercase}.card h2{margin:13px 0 3px;text-align:center;font:800 23px "Plus Jakarta Sans",sans-serif}.subtitle{margin:0 0 19px;text-align:center;color:var(--muted);font-size:11px}.notice{margin:12px 0;padding:11px 12px;border-radius:10px;font-size:12px;line-height:1.5}.notice.error{background:#fff1f0;color:#b42318}.notice.success{background:#edf8f0;color:#28623a}
        label{display:block;margin:12px 0 6px;font-size:11px;font-weight:800}.field{width:100%;height:44px;padding:0 12px;border:1px solid var(--line);border-radius:11px;background:#fff;color:var(--ink);font:12px Inter,sans-serif}.field:focus{outline:2px solid #fdba74;border-color:var(--orange)}.password-wrap{position:relative}.password-wrap .field{padding-right:48px}.toggle{position:absolute;right:5px;top:5px;width:34px;height:34px;border:1px solid var(--line);border-radius:9px;background:#fff8f2;cursor:pointer;color:#8c4a23}.submit{width:100%;height:47px;margin-top:18px;border:0;border-radius:11px;background:linear-gradient(100deg,#fb7416,#ce4606);box-shadow:0 5px 0 #a93807;color:#fff;font-size:12px;font-weight:900;cursor:pointer}.signin,.home{display:block;text-align:center;text-decoration:none}.signin{margin-top:18px;color:#c2410c;font-size:11px;font-weight:900}.home{margin-top:11px;color:var(--muted);font-size:11px}.hidden{display:none!important}
        @media(max-width:850px){body{padding:22px}.layout{grid-template-columns:1fr;max-width:500px;gap:14px}.intro{padding:0 4px}.eyebrow{margin-top:19px}.intro h1{font-size:36px;margin:14px 0 8px}.intro p{margin:0;font-size:12px}.pills{margin-top:13px}.pill{padding:8px 10px;font-size:10px}.card{padding:25px;margin-bottom:16px}}
        @media(max-width:460px){body{padding:15px}.intro h1{font-size:31px}.card{padding:22px 19px;border-radius:22px}}
    </style>
</head>
<body>
<main class="layout">
    <section class="intro">
        <div class="brand"><span class="mark">UF</span><span>UniFlow<small>STUDENT &amp; STAFF SIGNUP</small></span></div>
        <div class="eyebrow">● &nbsp; Create your account</div>
        <h1>Join UniFlow.<span>Create your account.</span></h1>
        <p>Choose Student or Staff, enter your university details, and set your own password. Your account will be ready to sign in after registration.</p>
        <div class="pills"><span class="pill">Student Portal</span><span class="pill">Technical Staff</span><span class="pill">Administrative Staff</span><span class="pill">Proctorial Staff</span><span class="pill">Lost &amp; Found</span></div>
    </section>
    <section class="card">
        <div class="card-brand"><span class="mark">U</span> UniFlow</div>
        <div class="badge">Student &amp; Staff Signup</div>
        <h2>Create Account</h2>
        <p class="subtitle">Sign up for your UniFlow account</p>

        <?php if ($error !== ''): ?><div class="notice error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <?php if ($success !== ''): ?><div class="notice success" role="status"><?= e($success) ?></div><?php endif; ?>

        <?php if ($success === ''): ?>
        <form method="post" autocomplete="on">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['signup_csrf']) ?>">
            <label for="account-type">Account Type</label>
            <select class="field" id="account-type" name="account_type" required>
                <option value="student" <?= $accountType === 'student' ? 'selected' : '' ?>>Student</option>
                <option value="staff" <?= $accountType === 'staff' ? 'selected' : '' ?>>Staff</option>
            </select>

            <label for="name">Full Name</label>
            <input class="field" id="name" name="name" type="text" maxlength="100" value="<?= e($name) ?>" autocomplete="name" placeholder="Enter your full name" required>

            <div id="student-id-field" <?= $accountType === 'staff' ? 'class="hidden"' : '' ?>>
                <label for="student-code">Student ID</label>
                <input class="field" id="student-code" name="student_code" type="text" maxlength="50" value="<?= e($studentCode) ?>" placeholder="Enter your Student ID" <?= $accountType === 'student' ? 'required' : '' ?>>
            </div>

            <div id="staff-portal-field" <?= $accountType === 'student' ? 'class="hidden"' : '' ?>>
                <label for="staff-role">Staff Portal</label>
                <select class="field" id="staff-role" name="staff_role">
                    <?php foreach ($allowedStaffRoles as $role => $label): ?>
                        <option value="<?= e($role) ?>" <?= $staffRole === $role ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <label for="email">University Email</label>
            <input class="field" id="email" name="email" type="email" maxlength="150" value="<?= e($email) ?>" autocomplete="email" placeholder="Enter your university email" required>

            <label for="password">Password</label>
            <div class="password-wrap"><input class="field" id="password" name="password" type="password" minlength="8" autocomplete="new-password" placeholder="At least 8 characters" required><button class="toggle" type="button" data-toggle="password" aria-label="Show password">◉</button></div>

            <label for="password-confirmation">Confirm Password</label>
            <div class="password-wrap"><input class="field" id="password-confirmation" name="password_confirmation" type="password" minlength="8" autocomplete="new-password" placeholder="Re-enter your password" required><button class="toggle" type="button" data-toggle="password-confirmation" aria-label="Show password">◉</button></div>

            <button class="submit" type="submit">Create Account ›</button>
        </form>
        <?php endif; ?>

        <a class="signin" href="login.php">Already have an account? Sign in</a>
        <a class="home" href="index.php">← Back to Homepage</a>
    </section>
</main>
<script>
const accountType = document.getElementById('account-type');
const studentField = document.getElementById('student-id-field');
const studentCode = document.getElementById('student-code');
const staffPortalField = document.getElementById('staff-portal-field');
const staffRole = document.getElementById('staff-role');

function updateAccountFields() {
    const isStudent = accountType.value === 'student';
    studentField.classList.toggle('hidden', !isStudent);
    studentCode.required = isStudent;
    staffPortalField.classList.toggle('hidden', isStudent);
    staffRole.required = !isStudent;
}

accountType.addEventListener('change', updateAccountFields);
updateAccountFields();
document.querySelectorAll('[data-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.toggle);
        input.type = input.type === 'password' ? 'text' : 'password';
        button.setAttribute('aria-label', input.type === 'password' ? 'Show password' : 'Hide password');
    });
});
</script>
</body>
</html>
