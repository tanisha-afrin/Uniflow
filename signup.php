<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

$email = '';
$name = '';
$studentCode = '';
$accountType = 'student';
$portal = 'technical';
$error = '';
$success = '';
$staffCodeRequired = uniFlowEnvironmentValue('UNIFLOW_STAFF_SIGNUP_CODE') !== '';
$signupCsrf = uniFlowCsrfToken();
$portalOptions = [
    'technical' => 'Technical',
    'administrative' => 'Administrative',
    'proctorial' => 'Proctorial',
    'lost_found' => 'Lost & Found Moderator'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedType = $_POST['account_type'] ?? '';
    $accountType = is_string($postedType) ? $postedType : '';
    $postedName = $_POST['name'] ?? '';
    $name = is_string($postedName) ? trim($postedName) : '';
    $postedEmail = $_POST['email'] ?? '';
    $email = is_string($postedEmail) ? strtolower(trim($postedEmail)) : '';
    $postedCode = $_POST['student_code'] ?? '';
    $studentCode = is_string($postedCode) ? trim($postedCode) : '';
    $postedPortal = $_POST['portal'] ?? '';
    $portal = is_string($postedPortal) ? $postedPortal : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirmPassword = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';

    if (!uniFlowCsrfIsValid($_POST['csrf_token'] ?? null)) {
        $error = 'The request expired. Reload the page and try again.';
    } elseif (!uniFlowRateLimit($conn, 'signup-ip', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 20, 900)) {
        $error = 'Too many signup attempts. Please wait 15 minutes and try again.';
    } elseif ($email !== '' && !uniFlowRateLimit($conn, 'signup-email', $email, 5, 3600)) {
        $error = 'Too many signup attempts for this email. Please wait one hour and try again.';
    } elseif (!in_array($accountType, ['student', 'staff'], true)) {
        $error = 'Choose Student or Staff as the account type.';
    } elseif ($name === '') {
        $error = 'Please enter your full name.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid University Email.';
    } elseif ($accountType === 'student' && $studentCode === '') {
        $error = 'Enter your Student ID.';
    } elseif ($accountType === 'staff' && !array_key_exists($portal, $portalOptions)) {
        $error = 'Choose one staff portal.';
    } elseif ($accountType === 'staff'
        && $staffCodeRequired
        && (!is_string($_POST['staff_signup_code'] ?? null)
            || !hash_equals(uniFlowEnvironmentValue('UNIFLOW_STAFF_SIGNUP_CODE'), $_POST['staff_signup_code']))) {
        $error = 'The Staff access code is incorrect.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $stmt = $conn->prepare('SELECT student_id FROM students WHERE email = ? LIMIT 1');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $studentMatch = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $stmt = $conn->prepare('SELECT admin_id FROM admins WHERE email = ? LIMIT 1');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $staffMatch = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $studentCodeMatch = null;
            if ($accountType === 'student') {
                $stmt = $conn->prepare('SELECT student_id FROM students WHERE student_code = ? LIMIT 1');
                $stmt->bind_param('s', $studentCode);
                $stmt->execute();
                $studentCodeMatch = $stmt->get_result()->fetch_assoc();
                $stmt->close();
            }

            if ($studentMatch || $staffMatch) {
                $error = 'This University Email is already registered.';
            } elseif ($studentCodeMatch) {
                $error = 'This Student ID is already registered.';
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $conn->begin_transaction();
                if ($accountType === 'student') {
                    $stmt = $conn->prepare(
                        'INSERT INTO students (student_code, name, email, password) VALUES (?, ?, ?, ?)'
                    );
                    $stmt->bind_param('ssss', $studentCode, $name, $email, $passwordHash);
                    if (!$stmt->execute()) {
                        throw new RuntimeException('Student signup insert failed.');
                    }
                    $newUserId = $stmt->insert_id;
                    $stmt->close();
                } else {
                    $stmt = $conn->prepare(
                        'INSERT INTO admins (name, email, password, role) VALUES (?, ?, ?, ?)'
                    );
                    $stmt->bind_param('ssss', $name, $email, $passwordHash, $portal);
                    if (!$stmt->execute()) {
                        throw new RuntimeException('Staff signup insert failed.');
                    }
                    $newUserId = $stmt->insert_id;
                    $stmt->close();

                    $accessStmt = $conn->prepare(
                        'INSERT INTO admin_access (admin_id, access_area) VALUES (?, ?)'
                    );
                    $accessStmt->bind_param('is', $newUserId, $portal);
                    if (!$accessStmt->execute()) {
                        throw new RuntimeException('Staff portal access assignment failed.');
                    }
                    $accessStmt->close();
                }
                $conn->commit();

                session_regenerate_id(true);
                unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_role']);
                unset($_SESSION['student_id'], $_SESSION['student_name'], $_SESSION['student_code']);

                if ($accountType === 'student') {
                    $_SESSION['student_id'] = (int)$newUserId;
                    $_SESSION['student_name'] = $name;
                    $_SESSION['student_code'] = $studentCode;
                    $_SESSION['user_type'] = 'student';
                    header('Location: student/dashboard.php');
                } else {
                    $_SESSION['admin_id'] = (int)$newUserId;
                    $_SESSION['admin_name'] = $name;
                    $_SESSION['admin_role'] = $portal;
                    $_SESSION['user_type'] = 'admin';
                    $destination = match ($portal) {
                        'technical' => 'technical/dashboard.php',
                        'administrative' => 'administrative/dashboard.php',
                        'proctorial' => 'proctorial/dashboard.php',
                        'lost_found' => 'lost_found/index.php'
                    };
                    header('Location: ' . $destination);
                }
                exit();
            }
        } catch (Throwable $exception) {
            try {
                $conn->rollback();
            } catch (Throwable $ignored) {
            }
            error_log('UniFlow public signup failed: ' . $exception->getMessage());
            $error = 'Could not create the account. Please check the details and try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Sign Up | UniFlow</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

:root {
    --orange: #f97316;
    --orange-main: #ea580c;
    --orange-dark: #c2410c;
    --orange-deep: #9a3412;
    --orange-light: #fb923c;
    --orange-soft: #fff7ed;
    --cream: #fffaf3;
    --white: #ffffff;
    --text: #2a1a14;
    --text-soft: #6c5145;
    --muted: #938077;
    --border: rgba(154,52,18,.12);
    --danger: #dc2626;
}

body {

    min-height: 100vh;

    font-family:
        Inter,
        "Segoe UI",
        Arial,
        sans-serif;

    color: var(--text);

    display: flex;
    align-items: center;
    justify-content: center;

    padding: 28px;

    overflow-x: hidden;

    background:
        radial-gradient(
            circle at 5% 15%,
            rgba(249,115,22,.18),
            transparent 27%
        ),
        radial-gradient(
            circle at 95% 85%,
            rgba(194,65,12,.13),
            transparent 28%
        ),
        linear-gradient(
            135deg,
            #fffefb 0%,
            #fffaf3 40%,
            #fff1df 75%,
            #ffe5ca 100%
        );

    position: relative;
}

body::before {

    content: "";

    position: fixed;

    inset: 0;

    pointer-events: none;

    background-image:
        linear-gradient(
            rgba(154,52,18,.045) 1px,
            transparent 1px
        ),
        linear-gradient(
            90deg,
            rgba(154,52,18,.045) 1px,
            transparent 1px
        );

    background-size: 42px 42px;

    mask-image:
        linear-gradient(
            to bottom,
            black,
            transparent 90%
        );

    opacity: .7;
}

.rays {

    position: fixed;

    width: 850px;
    height: 850px;

    left: -360px;
    top: -360px;

    border-radius: 50%;

    background:
        repeating-conic-gradient(
            from 10deg,
            rgba(249,115,22,.12) 0deg,
            rgba(249,115,22,.12) 8deg,
            transparent 8deg,
            transparent 20deg
        );

    animation:
        rotateRays 28s linear infinite;

    pointer-events: none;

    z-index: 0;
}

@keyframes rotateRays {

    from {
        transform: rotate(0deg);
    }

    to {
        transform: rotate(360deg);
    }
}

.orb {

    position: fixed;

    border-radius: 50%;

    pointer-events: none;

    z-index: 0;

    filter: blur(1px);
}

.orb-one {

    width: 210px;
    height: 210px;

    right: 9%;
    top: 12%;

    background:
        radial-gradient(
            circle at 30% 30%,
            rgba(255,255,255,.85),
            rgba(249,115,22,.16),
            transparent 70%
        );

    animation:
        floatOne 8s ease-in-out infinite;
}

.orb-two {

    width: 150px;
    height: 150px;

    left: 7%;
    bottom: 12%;

    background:
        radial-gradient(
            circle,
            rgba(249,115,22,.10),
            transparent 70%
        );

    animation:
        floatTwo 10s ease-in-out infinite;
}

@keyframes floatOne {

    0%,100% {
        transform: translate(0,0);
    }

    50% {
        transform: translate(-35px,30px);
    }
}

@keyframes floatTwo {

    0%,100% {
        transform: translate(0,0);
    }

    50% {
        transform: translate(30px,-25px);
    }
}

.login-layout {

    position: relative;

    z-index: 5;

    width: min(1100px, 100%);

    display: grid;

    grid-template-columns:
        minmax(0, 1fr)
        minmax(420px, 500px);

    align-items: center;

    gap: 60px;
}

.login-info {
    padding: 30px 10px;
}

.brand {

    display: inline-flex;

    align-items: center;

    gap: 12px;

    margin-bottom: 32px;
}

.brand-icon {

    width: 48px;
    height: 48px;

    display: grid;

    place-items: center;

    border-radius: 16px;

    background:
        linear-gradient(
            145deg,
            var(--orange),
            var(--orange-deep)
        );

    color: white;

    font-size: 20px;

    font-weight: 900;

    box-shadow:
        0 14px 30px
        rgba(234,88,12,.25);
}

.brand-name {

    font-size: 26px;

    font-weight: 900;

    letter-spacing: -.06em;
}

.brand-name span {

    display: block;

    margin-top: 3px;

    color: var(--muted);

    font-size: 11px;

    letter-spacing: .05em;

    text-transform: uppercase;
}

.info-kicker {

    display: inline-flex;

    align-items: center;

    gap: 8px;

    padding: 8px 12px;

    border-radius: 999px;

    background: rgba(255,255,255,.72);

    border: 1px solid var(--border);

    color: var(--orange-dark);

    font-size: 11px;

    font-weight: 850;

    text-transform: uppercase;

    letter-spacing: .05em;
}

.info-kicker::before {

    content: "";

    width: 7px;
    height: 7px;

    border-radius: 50%;

    background: var(--orange);

    box-shadow:
        0 0 0 5px
        rgba(249,115,22,.10);
}

.login-info h1 {

    margin-top: 22px;

    max-width: 600px;

    font-size:
        clamp(38px, 4.5vw, 60px);

    line-height: 1.1;

    letter-spacing: -.04em;

    font-weight: 950;
}

.login-info h1 span {

    display: block;

    background:
        linear-gradient(
            120deg,
            var(--orange-deep),
            var(--orange-main),
            var(--orange-light)
        );

    -webkit-background-clip: text;

    background-clip: text;

    color: transparent;
}

.info-text {

    max-width: 570px;

    margin-top: 22px;

    color: var(--text-soft);

    font-size: 15px;

    line-height: 1.8;
}

.portal-preview {

    margin-top: 30px;

    display: flex;

    flex-wrap: wrap;

    gap: 10px;
}

.preview-pill {

    display: inline-flex;

    align-items: center;

    gap: 8px;

    padding: 10px 13px;

    border-radius: 13px;

    background: rgba(255,255,255,.66);

    border: 1px solid var(--border);

    color: var(--text-soft);

    font-size: 12px;

    font-weight: 750;

    box-shadow:
        0 9px 25px
        rgba(120,53,15,.05);
}

.preview-pill::before {

    content: "";

    width: 7px;
    height: 7px;

    border-radius: 50%;

    background:
        linear-gradient(
            135deg,
            var(--orange-light),
            var(--orange-dark)
        );
}

.login-card {

    position: relative;

    width: 100%;

    padding: 38px;

    border-radius: 31px;

    background: rgba(255,255,255,.88);

    border:
        1px solid
        rgba(255,255,255,.9);

    backdrop-filter: blur(22px);

    -webkit-backdrop-filter: blur(22px);

    box-shadow:
        0 35px 90px rgba(80,50,20,.14),
        0 10px 28px rgba(80,50,20,.06),
        inset 0 1px 0
        rgba(255,255,255,.95);

    animation:
        cardEnter .65s ease;
}

@keyframes cardEnter {

    from {
        opacity: 0;
        transform:
            translateY(25px)
            scale(.97);
    }

    to {
        opacity: 1;
        transform:
            translateY(0)
            scale(1);
    }
}

.login-card::before {

    content: "";

    position: absolute;

    width: 170px;
    height: 170px;

    right: -70px;
    bottom: -90px;

    border-radius: 50%;

    background:
        radial-gradient(
            circle,
            rgba(249,115,22,.14),
            transparent 70%
        );

    pointer-events: none;
}

.card-logo {

    display: flex;

    align-items: center;

    justify-content: center;

    gap: 10px;

    font-size: 23px;

    font-weight: 900;

    letter-spacing: -.045em;

    margin-bottom: 24px;
}

.card-logo-mark {

    width: 42px;
    height: 42px;

    display: grid;

    place-items: center;

    border-radius: 14px;

    color: white;

    background:
        linear-gradient(
            145deg,
            var(--orange-light),
            var(--orange-deep)
        );

    box-shadow:
        0 10px 22px
        rgba(249,115,22,.24);
}

.access-badge {

    width: fit-content;

    margin:
        0 auto 14px;

    padding:
        7px 12px;

    border-radius: 999px;

    background: var(--orange-soft);

    border:
        1px solid #fed7aa;

    color: var(--orange-dark);

    font-size: 11px;

    font-weight: 850;

    letter-spacing: .05em;

    text-transform: uppercase;
}

.card-title {

    text-align: center;

    margin-bottom: 23px;
}

.card-title h2 {

    font-size: 28px;

    letter-spacing: -.03em;

    line-height: 1.1;

    margin-bottom: 8px;
}

.card-title p {

    color: var(--muted);

    font-size: 13px;

    line-height: 1.6;
}

.error-box {

    display: flex;

    align-items: center;

    gap: 10px;

    margin-bottom: 18px;

    padding: 12px 14px;

    border-radius: 13px;

    background: #fff1f1;

    border:
        1px solid #ffd3d3;

    color: var(--danger);

    font-size: 13px;

    font-weight: 650;
}

.error-icon {

    width: 22px;
    height: 22px;

    flex: 0 0 22px;

    display: grid;

    place-items: center;

    border-radius: 50%;

    background: var(--danger);

    color: white;

    font-size: 11px;

    font-weight: 900;
}

.form-group {

    margin-bottom: 17px;
}

.form-group label {

    display: block;

    margin-bottom: 7px;

    color: #44332c;

    font-size: 13px;

    font-weight: 800;
}

.form-group input {

    width: 100%;

    height: 52px;

    padding: 0 15px;

    border:
        1px solid var(--border);

    border-radius: 14px;

    outline: none;

    background:
        rgba(255,255,255,.92);

    color: var(--text);

    font-size: 13px;

    transition: all .2s ease;
}

.form-group input::placeholder {
    color: #aaa;
}

.form-group input:hover {
    border-color:
        rgba(154,52,18,.22);
}

.form-group input:focus {

    border-color:
        var(--orange);

    box-shadow:
        0 0 0 4px
        rgba(249,115,22,.10);

    transform:
        translateY(-1px);
}

.password-wrap {
    position: relative;
}

.password-wrap input {
    padding-right: 58px;
}

.password-toggle {

    position: absolute;

    top: 50%;

    right: 7px;

    transform:
        translateY(-50%);

    width: 40px;
    height: 36px;

    border: none;

    border-radius: 10px;

    cursor: pointer;

    background:
        var(--orange-soft);

    color:
        var(--orange-dark);

    font-size: 14px;
}

.login-button {

    width: 100%;

    height: 54px;

    margin-top: 3px;

    border: none;

    border-radius: 15px;

    cursor: pointer;

    color: #ffffff;

    background:
        linear-gradient(
            135deg,
            #f97316 0%,
            #ea580c 55%,
            #c2410c 100%
        );

    font-size: 15px;

    font-weight: 850;

    box-shadow:
        0 13px 30px
        rgba(234,88,12,.28);

    transition: all .22s ease;
}

.login-button:hover {

    transform:
        translateY(-2px);

    box-shadow:
        0 18px 38px
        rgba(234,88,12,.36);
}

.card-footer {

    margin-top: 21px;

    padding-top: 18px;

    border-top:
        1px solid
        rgba(154,52,18,.08);

    text-align: center;

    font-size: 13px;

    color: var(--text-soft);
}

.card-footer a {

    color:
        var(--orange-dark);

    font-weight: 750;

    text-decoration: none;
}

.card-footer a:hover {
    text-decoration: underline;
}

.home-link {

    display: block;

    text-align: center;

    margin-top: 12px;

    font-size: 12px;

    color: var(--muted);

    text-decoration: none;
}

.home-link:hover {
    color: var(--orange-dark);
}

@media (max-width: 950px) {

    .login-layout {

        grid-template-columns: 1fr;

        max-width: 530px;

        gap: 20px;
    }

    .login-info {

        text-align: center;

        padding-bottom: 0;
    }

    .login-info h1,
    .info-text {

        margin-left: auto;

        margin-right: auto;
    }

    .portal-preview {

        justify-content: center;
    }
}

@media (max-width: 600px) {

    body {
        padding: 15px;
    }

    .login-info {
        display: none;
    }

    .login-card {

        padding: 28px 22px;

        border-radius: 24px;
    }

    .card-title h2 {
        font-size: 25px;
    }

    .rays {

        width: 600px;
        height: 600px;
    }
}

</style>

<link rel="stylesheet" href="css/buttons.css">
<style>.form-group select{width:100%;height:52px;padding:0 15px;border:1px solid var(--border);border-radius:14px;outline:none;background:rgba(255,255,255,.82);color:var(--text);font-size:14px;font-weight:600}.form-group select:focus{border-color:var(--orange);box-shadow:0 0 0 4px rgba(249,115,22,.12)}</style>
</head>

<body>

<div class="rays"></div>

<div class="orb orb-one"></div>
<div class="orb orb-two"></div>

<div class="login-layout">

    <section class="login-info">

        <div class="brand">

            <div class="brand-icon">
                UF
            </div>

            <div class="brand-name">
                UniFlow
                <span>Student &amp; Staff Signup</span>
            </div>

        </div>

        <div class="info-kicker">
            Create Your Account
        </div>

        <h1>
            Join UniFlow.
            <span>Create your account.</span>
        </h1>

        <p class="info-text">
            Choose Student or Staff, enter your university details,
            and set your own password. Staff accounts receive access
            only to the portal selected during signup.
        </p>

        <div class="portal-preview">

            <div class="preview-pill">
                Student Portal
            </div>

            <div class="preview-pill">
                Technical Staff
            </div>

            <div class="preview-pill">
                Administrative Staff
            </div>

            <div class="preview-pill">
                Proctorial Staff
            </div>

            <div class="preview-pill">
                Lost &amp; Found
            </div>

        </div>

    </section>


    <section class="login-card">

        <div class="card-logo">

            <div class="card-logo-mark">
                U
            </div>

            UniFlow

        </div>

        <div class="access-badge">
            Student &amp; Staff Signup
        </div>

        <div class="card-title">

            <h2>
                Create Account
            </h2>

            <p>
                Sign up for your UniFlow account
            </p>

        </div>


        <?php if ($error): ?>

            <div class="error-box">

                <div class="error-icon">
                    !
                </div>

                <div>
                    <?= e($error) ?>
                </div>

            </div>

        <?php endif; ?>

        <form method="POST" autocomplete="on">
    <input type="hidden" name="csrf_token" value="<?= e($signupCsrf) ?>">
    <div class="form-group">
        <label for="account-type">Account Type</label>
        <select id="account-type" name="account_type" required>
            <option value="student" <?= $accountType === 'student' ? 'selected' : '' ?>>Student</option>
            <option value="staff" <?= $accountType === 'staff' ? 'selected' : '' ?>>Staff</option>
        </select>
    </div>
    <div class="form-group">
        <label for="name">Full Name</label>
        <input id="name" type="text" name="name" value="<?= e($name) ?>" placeholder="Enter your full name" required autocomplete="name">
    </div>
    <div class="form-group" id="student-id-group">
        <label for="student-code">Student ID</label>
        <input id="student-code" type="text" name="student_code" value="<?= e($studentCode) ?>" placeholder="Enter your Student ID" autocomplete="off">
    </div>
    <div class="form-group" id="portal-group" hidden>
        <label for="portal">Staff Portal</label>
        <select id="portal" name="portal">
            <?php foreach ($portalOptions as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $portal === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php if ($staffCodeRequired): ?>
    <div class="form-group" id="staff-code-group" hidden>
        <label for="staff-signup-code">Staff access code</label>
        <input id="staff-signup-code" type="password" name="staff_signup_code" placeholder="Enter the staff access code" autocomplete="off">
    </div>
    <?php endif; ?>
    <div class="form-group">
        <label for="email">University Email</label>
        <input id="email" type="email" name="email" value="<?= e($email) ?>" placeholder="Enter your university email" required autocomplete="email">
    </div>
    <div class="form-group">
        <label for="password">Password</label>
        <div class="password-wrap">
            <input id="password" type="password" name="password" placeholder="At least 8 characters" required autocomplete="new-password">
            <button type="button" class="password-toggle" onclick="togglePassword('password', this)" aria-label="Toggle Password Visibility">👁</button>
        </div>
    </div>
    <div class="form-group">
        <label for="confirm-password">Confirm Password</label>
        <div class="password-wrap">
            <input id="confirm-password" type="password" name="confirm_password" placeholder="Re-enter your password" required autocomplete="new-password">
            <button type="button" class="password-toggle" onclick="togglePassword('confirm-password', this)" aria-label="Toggle Password Visibility">👁</button>
        </div>
    </div>
    <button type="submit" class="login-button">Create Account ›</button>
    <a href="login.php" class="forgot-password-link" style="display:block;margin:16px auto 0;color:#c2410c;text-align:center;font-size:13px;font-weight:800;text-decoration:none;">Already have an account? Sign in</a>
    <a href="index.php" class="home-link">← Back to Homepage</a>
</form>

    </section>

</div>


<script>

function togglePassword(inputId, button) {
    const input = document.getElementById(inputId);
    input.type = input.type === 'password' ? 'text' : 'password';
    button.textContent = input.type === 'password' ? '👁' : '🙈';
}

const accountTypeSelect = document.getElementById('account-type');
const studentIdGroup = document.getElementById('student-id-group');
const studentCodeInput = document.getElementById('student-code');
const portalGroup = document.getElementById('portal-group');
const portalSelect = document.getElementById('portal');
const staffCodeGroup = document.getElementById('staff-code-group');
const staffCodeInput = document.getElementById('staff-signup-code');

function updateSignupFields() {
    const isStaff = accountTypeSelect.value === 'staff';
    studentIdGroup.hidden = isStaff;
    studentCodeInput.required = !isStaff;
    portalGroup.hidden = !isStaff;
    portalSelect.required = isStaff;
    if (staffCodeGroup) {
        staffCodeGroup.hidden = !isStaff;
        staffCodeInput.required = isStaff;
    }
}

accountTypeSelect.addEventListener('change', updateSignupFields);
updateSignupFields();</script>

</body>

</html>
