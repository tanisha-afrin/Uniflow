<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

$error = '';
$email = '';
$rawReturn = $_POST['return_to'] ?? $_GET['return_to'] ?? '';
$returnTo = $rawReturn === 'lost_found' ? 'lost_found' : '';

function loginRedirectForAdmin(mysqli $conn, int $adminId, string $role, string $adminType): string
{
    if ($adminType === 'main_admin') return 'administrative/dashboard.php';
    $areas = getAdminAccessAreas($conn, $adminId);
    if (count($areas) === 1) {
        return match ($areas[0]) {
            'technical' => 'technical/dashboard.php',
            'administrative' => 'administrative/dashboard.php',
            'proctorial' => 'proctorial/dashboard.php',
            'lost_found' => 'lost_found/index.php',
            default => 'index.php'
        };
    }
    if ($role === 'technical') return 'technical/dashboard.php';
    if ($role === 'administrative') return 'administrative/dashboard.php';
    if ($role === 'proctorial') return 'proctorial/dashboard.php';
    return 'index.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($email === '' || $password === '') {
        $error = 'Please enter both email and password.';
    } else {
        $student = null; $admin = null;
        $stmt = $conn->prepare('SELECT student_id,name,student_code,password FROM students WHERE email = ? LIMIT 1');
        if ($stmt) { $stmt->bind_param('s',$email); $stmt->execute(); $student=$stmt->get_result()->fetch_assoc(); $stmt->close(); }
        $stmt = $conn->prepare('SELECT admin_id,name,email,role,admin_type,is_active,password,must_change_password,password_expires_at FROM admins WHERE email = ? LIMIT 1');
        if ($stmt) { $stmt->bind_param('s',$email); $stmt->execute(); $admin=$stmt->get_result()->fetch_assoc(); $stmt->close(); }
        $studentOk = $student && password_verify($password,$student['password']);
        $adminPasswordOk = $admin && password_verify($password,$admin['password']);
        $adminActive = $admin && (int)($admin['is_active'] ?? 1) === 1;
        $adminOk = $adminPasswordOk && $adminActive;
        if ($adminPasswordOk && !$adminActive) {
            $error = 'Your staff account is inactive. Contact the Administrative team.';
        } elseif ($studentOk && !$adminPasswordOk) {
            session_regenerate_id(true);
            unset($_SESSION['admin_id'],$_SESSION['admin_name'],$_SESSION['admin_role'],$_SESSION['admin_type']);
            $_SESSION['student_id']=(int)$student['student_id']; $_SESSION['student_name']=$student['name']; $_SESSION['student_code']=$student['student_code']; $_SESSION['user_type']='student';
            header('Location: ' . ($returnTo === 'lost_found' ? 'lost_found/index.php' : 'student/dashboard.php')); exit();
        }
        elseif ($adminOk && !$studentOk) {
            if ((int)$admin['must_change_password'] === 1 && !empty($admin['password_expires_at']) && strtotime($admin['password_expires_at']) < time()) {
                $error='Your invitation has expired. Please ask the Administrative team to send a new invitation.';
            } else {
                session_regenerate_id(true);
                unset($_SESSION['student_id'],$_SESSION['student_name'],$_SESSION['student_code']);
                $_SESSION['admin_id']=(int)$admin['admin_id']; $_SESSION['admin_name']=$admin['name']; $_SESSION['admin_role']=$admin['role']; $_SESSION['admin_type']=$admin['admin_type'] ?? 'admin'; $_SESSION['user_type']='admin';
                if ((int)$admin['must_change_password'] === 1) {
                    if ($returnTo === 'lost_found') $_SESSION['return_to'] = 'lost_found';
                    header('Location: change_password.php');
                    exit();
                }
                if ($returnTo === 'lost_found') { header('Location: lost_found/index.php'); exit(); }
                header('Location: ' . loginRedirectForAdmin($conn,(int)$admin['admin_id'],$admin['role'],$admin['admin_type'] ?? 'admin')); exit();
            }
        } elseif ($studentOk && $adminPasswordOk) {
            $error='This email is linked to more than one account. Please use a unique email.';
        } elseif (!$studentOk && !$adminOk) {
            $error='Invalid email or password.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Login | UniFlow</title>

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
                <span>Secure Login Portal</span>
            </div>

        </div>

        <div class="info-kicker">
            Smart Account Access
        </div>

        <h1>
            Welcome Back!
            <span>Sign in to UniFlow.</span>
        </h1>

        <p class="info-text">
            Enter your registered email and password.
            UniFlow automatically identifies whether you are
            a student or an administrator and sends you to
            the correct portal.
        </p>

        <div class="portal-preview">

            <div class="preview-pill">
                Student Portal
            </div>

            <div class="preview-pill">
                Admin Management
            </div>

            <div class="preview-pill">
                Technical Admin
            </div>

            <div class="preview-pill">
                Administrative Admin
            </div>

            <div class="preview-pill">
                Proctorial Admin
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
            Automatic Authentication
        </div>

        <div class="card-title">

            <h2>
                Sign In
            </h2>

            <p>
                Use your UniFlow account credentials
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

            <input
                type="hidden"
                name="return_to"
                value="<?= e($returnTo) ?>"
            >


            <div class="form-group">

                <label for="email">
                    Email Address
                </label>

                <input
                    id="email"
                    type="email"
                    name="email"
                    value="<?= e($email) ?>"
                    placeholder="Enter your email address"
                    required
                    autocomplete="email"
                >

            </div>


            <div class="form-group">

                <label for="password">
                    Password
                </label>

                <div class="password-wrap">

                    <input
                        id="password"
                        type="password"
                        name="password"
                        placeholder="Enter your password"
                        required
                        autocomplete="current-password"
                    >

                    <button
                        type="button"
                        class="password-toggle"
                        onclick="togglePassword()"
                        aria-label="Toggle Password Visibility"
                    >
                        👁
                    </button>

                </div>

            </div>


            <button
                type="submit"
                class="login-button"
            >
                Log In ›
            </button>

            <a href="forgot_password.php" style="display:block;margin:17px auto 0;color:#c2410c;text-align:center;font-size:13px;font-weight:800;text-decoration:none;">Forgot Password?</a>
            <a href="register.php" style="display:block;margin:13px auto 0;color:#c2410c;text-align:center;font-size:13px;font-weight:800;text-decoration:none;">New here? Create a student or staff account</a>

            <a
                href="index.php"
                class="home-link"
            >
                ← Back to Homepage
            </a>

        </form>

    </section>

</div>


<script>

function togglePassword() {

    const input =
        document.getElementById('password');

    const button =
        document.querySelector('.password-toggle');

    if (input.type === 'password') {

        input.type = 'text';

        button.textContent = '🙈';

    } else {

        input.type = 'password';

        button.textContent = '👁';
    }
}

</script>

</body>

</html>
