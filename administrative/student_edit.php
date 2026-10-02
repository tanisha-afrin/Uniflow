<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('is_admin_logged_in') || !is_admin_logged_in()) {
    header('Location: ../login.php');
    exit;
}

$currentAdminId = (int)($_SESSION['admin_id'] ?? 0);
$stmt = $conn->prepare('SELECT is_active, must_change_password, admin_type FROM admins WHERE admin_id = ? LIMIT 1');
$stmt->bind_param('i', $currentAdminId);
$stmt->execute();
$currentAdmin = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$currentAdmin || ($currentAdmin['admin_type'] ?? '') !== 'main_admin' || (int)$currentAdmin['is_active'] !== 1) {
    header('Location: dashboard.php');
    exit;
}

if ((int)$currentAdmin['must_change_password'] === 1) {
    header('Location: ../change_password.php');
    exit;
}

// Keep the student-management schema compatible with older databases.
$check = $conn->query("SHOW COLUMNS FROM students LIKE 'personal_email'");
if ($check && $check->num_rows === 0) {
    $conn->query("ALTER TABLE students ADD COLUMN personal_email VARCHAR(150) NULL AFTER email");
}

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$studentId = (int)($_GET['id'] ?? $_POST['student_id'] ?? 0);
if ($studentId <= 0) {
    header('Location: student_management.php');
    exit;
}

$error = '';
$success = '';

if (isset($_GET['remove']) && $_GET['remove'] === '1') {
    $stmt = $conn->prepare('DELETE FROM students WHERE student_id = ? LIMIT 1');
    $stmt->bind_param('i', $studentId);
    if ($stmt->execute()) {
        $stmt->close();
        header('Location: student_management.php?removed=1');
        exit;
    }
    $stmt->close();
    $error = 'Could not remove the student account.';
}

$stmt = $conn->prepare('SELECT student_id, student_code, name, email, personal_email FROM students WHERE student_id = ? LIMIT 1');
$stmt->bind_param('i', $studentId);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    header('Location: student_management.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    $name = trim($_POST['name'] ?? '');
    $studentCode = trim($_POST['student_code'] ?? '');
    $universityEmail = strtolower(trim($_POST['university_email'] ?? ''));
    $personalEmail = strtolower(trim($_POST['personal_email'] ?? ''));
    $newPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($name === '') {
        $error = 'Please enter the student name.';
    } elseif ($studentCode === '') {
        $error = 'Please enter the Student ID.';
    } elseif (!filter_var($universityEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid University Email.';
    } elseif ($personalEmail !== '' && !filter_var($personalEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid Personal Email.';
    } elseif ($personalEmail !== '' && strcasecmp($universityEmail, $personalEmail) === 0) {
        $error = 'University Email and Personal Email should be different.';
    } elseif ($newPassword !== '' && strlen($newPassword) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($newPassword !== '' && $newPassword !== $confirmPassword) {
        $error = 'New password and confirmation password do not match.';
    }

    if ($error === '') {
        $stmt = $conn->prepare('SELECT student_id FROM students WHERE (email = ? OR student_code = ?) AND student_id <> ? LIMIT 1');
        $stmt->bind_param('ssi', $universityEmail, $studentCode, $studentId);
        $stmt->execute();
        $duplicate = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($duplicate) {
            $error = 'Another student already uses this University Email or Student ID.';
        }
    }

    if ($error === '') {
        if ($newPassword !== '') {
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('UPDATE students SET name = ?, student_code = ?, email = ?, personal_email = ?, password = ? WHERE student_id = ?');
            $stmt->bind_param('sssssi', $name, $studentCode, $universityEmail, $personalEmail, $passwordHash, $studentId);
        } else {
            $stmt = $conn->prepare('UPDATE students SET name = ?, student_code = ?, email = ?, personal_email = ? WHERE student_id = ?');
            $stmt->bind_param('ssssi', $name, $studentCode, $universityEmail, $personalEmail, $studentId);
        }

        if ($stmt->execute()) {
            $stmt->close();
            header('Location: student_management.php?updated=1');
            exit;
        }
        $stmt->close();
        $error = 'Could not update the student account.';
    }

    // Keep typed values visible after a validation error.
    $student['name'] = $name;
    $student['student_code'] = $studentCode;
    $student['email'] = $universityEmail;
    $student['personal_email'] = $personalEmail;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Student | UniFlow</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--orange:#ff6a00;--orange-dark:#e95700;--orange-light:#fff3e8;--orange-soft:#ffe4cf;--ink:#2f211a;--muted:#8a7467;--line:#f1e4da;--shadow:0 20px 55px rgba(255,106,0,.10);--shadow-sm:0 8px 25px rgba(255,106,0,.08)}
html{scroll-behavior:smooth}
body{min-height:100vh;font-family:Inter,Arial,sans-serif;background:radial-gradient(circle at 5% 12%,rgba(249,115,22,.13),transparent 23%),radial-gradient(circle at 95% 8%,rgba(255,154,85,.11),transparent 25%),radial-gradient(circle at 50% 100%,rgba(249,115,22,.08),transparent 30%),linear-gradient(135deg,#fffaf5 0%,#fff 52%,#fff7ef 100%);color:var(--ink);overflow-x:hidden}
body:before{content:"";position:fixed;width:340px;height:340px;right:-170px;top:180px;border:1px solid rgba(249,115,22,.10);border-radius:50%;box-shadow:0 0 0 35px rgba(249,115,22,.025),0 0 0 70px rgba(249,115,22,.018);pointer-events:none}
a{text-decoration:none;color:inherit}
.bg-one,.bg-two,.bg-three{position:fixed;pointer-events:none;z-index:0}
.bg-one{width:250px;height:250px;right:-80px;top:120px;border-radius:70px;background:linear-gradient(145deg,#ffad76,#f97316);opacity:.10;transform:rotate(28deg);box-shadow:inset -25px -25px 45px rgba(194,65,12,.14);animation:float 7s ease-in-out infinite}
.bg-two{width:190px;height:190px;left:-105px;bottom:80px;border:30px solid #ff8a3d;border-radius:50%;opacity:.075;animation:float2 8s ease-in-out infinite}
.bg-three{width:95px;height:95px;right:20%;bottom:75px;border-radius:50%;background:#ff8a3d;opacity:.06;animation:float 6s ease-in-out infinite reverse}
@keyframes float{50%{transform:translateY(-12px) rotate(33deg)}}
@keyframes float2{50%{transform:translateY(14px) rotate(7deg)}}
.topbar{height:70px;background:rgba(255,255,255,.94);border-bottom:1px solid #eadfd7;display:flex;align-items:center;justify-content:space-between;padding:0 42px;position:relative;z-index:2;box-shadow:0 8px 24px rgba(75,38,17,.05)}
.brand{font:800 27px 'Plus Jakarta Sans',sans-serif;color:#1d120d;letter-spacing:-1px}
.nav{display:flex;align-items:center;gap:12px;font-size:13px;font-weight:800}
.nav a{padding:10px 14px;border:1px solid transparent;border-radius:11px;color:#1d120d;transition:.18s ease}
.nav a:hover{color:var(--orange);border-color:#ffd9bd;background:#fff8f2}
.page{width:calc(100% - 100px);max-width:1180px;margin:0 auto;padding:42px 0 70px;position:relative;z-index:1}
.eyebrow{display:inline-flex;padding:8px 13px;border-radius:999px;background:linear-gradient(135deg,#fff3e7,#fffaf6);border:1px solid #ffd9bd;color:var(--orange-dark);font-size:11px;font-weight:800;letter-spacing:.6px;text-transform:uppercase;box-shadow:0 8px 20px rgba(249,115,22,.08)}
h1{margin-top:14px;font:800 clamp(34px,5vw,56px)/1.05 'Plus Jakarta Sans',sans-serif;letter-spacing:-2.1px;color:#2f211a}h1 span{color:var(--orange)}
.subtitle{margin-top:10px;color:var(--muted);font-size:14px;line-height:1.7;max-width:720px}
.form-card{margin-top:30px;background:rgba(255,255,255,.94);border:1px solid var(--line);border-radius:24px;box-shadow:var(--shadow);padding:28px;backdrop-filter:blur(12px)}
.form-head{display:flex;align-items:center;gap:14px;padding-bottom:20px;border-bottom:1px solid var(--line);margin-bottom:24px}
.icon{width:48px;height:48px;border-radius:15px;display:grid;place-items:center;background:var(--orange-light);color:var(--orange);font-size:21px;font-weight:900;box-shadow:0 8px 20px rgba(249,115,22,.08)}
.form-head h2{font:800 20px 'Plus Jakarta Sans',sans-serif}.form-head p{margin-top:4px;color:var(--muted);font-size:12px}
.alert{padding:13px 15px;border-radius:13px;margin-bottom:20px;font-size:12px;font-weight:700}.alert.error{background:#fff1ef;border:1px solid #ffd0ca;color:#b42318}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.field.full{grid-column:1/-1}.field label{display:block;margin-bottom:8px;color:#5e4b41;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.45px}.field input{width:100%;height:48px;border:1px solid #eadbd0;border-radius:12px;background:#fffaf7;color:var(--ink);padding:0 14px;font:600 13px Inter,Arial,sans-serif;outline:none;transition:.18s ease}.field input:focus{border-color:#ff9a55;box-shadow:0 0 0 4px rgba(249,115,22,.09);background:#fff}
.field small{display:block;margin-top:7px;color:#9a887d;font-size:10px;line-height:1.5}
.password-box{padding:18px;border-radius:17px;background:linear-gradient(135deg,#fff8f1,#fff);border:1px solid #f3dfd0}.password-title{font:800 13px 'Plus Jakarta Sans',sans-serif;color:#3a2a22;margin-bottom:5px}.password-note{font-size:11px;color:var(--muted);margin-bottom:15px}
.actions{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:25px;padding-top:20px;border-top:1px solid var(--line)}
.btn{display:inline-flex;align-items:center;justify-content:center;min-height:45px;padding:11px 17px;border-radius:12px;font-size:12px;font-weight:800;border:1px solid transparent;transition:.18s ease;cursor:pointer}.btn.cancel{color:#6f6057;background:#fff;border-color:#eadfd7}.btn.cancel:hover{background:#fff7f1;transform:translateY(-1px)}.btn.save{color:#fff;background:linear-gradient(135deg,#ff8a3d,#e85d0f);box-shadow:0 10px 22px rgba(249,115,22,.20)}.btn.save:hover{transform:translateY(-2px);box-shadow:0 14px 28px rgba(249,115,22,.27)}
.danger{color:#b42318;font-size:11px;font-weight:700}.danger-link{display:inline-flex;margin-top:18px;color:#c2410c;font-size:11px;font-weight:800}
@media(max-width:800px){.page{width:calc(100% - 40px)}.topbar{padding:0 20px}.grid{grid-template-columns:1fr}.field.full{grid-column:auto}.nav a:nth-child(2){display:none}}
@media(max-width:560px){.topbar{height:auto;padding:15px 20px;gap:10px;align-items:flex-start;flex-direction:column}.nav{width:100%;justify-content:flex-end}.form-card{padding:20px}.actions{flex-direction:column-reverse;align-items:stretch}.btn{width:100%}}
</style>
<link rel="stylesheet" href="../css/buttons.css">
</head>
<body>
<div class="bg-one"></div><div class="bg-two"></div><div class="bg-three"></div>
<header class="topbar">
    <a href="dashboard.php" class="brand">UniFlow</a>
    <nav class="nav">
        <a href="dashboard.php">Dashboard</a>
        <a href="student_management.php">Student Management</a>
        <a href="logout.php">Logout</a>
    </nav>
</header>
<main class="page">
    <div class="eyebrow">Student Account Management</div>
    <h1>Edit <span>Student</span></h1>
    <p class="subtitle">Update the student's UniFlow login account details. This does not change the university's admission or enrollment records.</p>

    <section class="form-card">
        <div class="form-head">
            <div class="icon">U</div>
            <div><h2>Student Account Details</h2><p>Student ID #<?= e($student['student_id']) ?> · Changes are saved to the UniFlow account.</p></div>
        </div>

        <?php if ($error !== ''): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

        <form method="POST" action="student_edit.php?id=<?= (int)$studentId ?>">
            <input type="hidden" name="student_id" value="<?= (int)$studentId ?>">
            <div class="grid">
                <div class="field">
                    <label>Student Name</label>
                    <input type="text" name="name" value="<?= e($student['name']) ?>" required>
                </div>
                <div class="field">
                    <label>Student ID</label>
                    <input type="text" name="student_code" value="<?= e($student['student_code']) ?>" required>
                </div>
                <div class="field">
                    <label>University Email</label>
                    <input type="email" name="university_email" value="<?= e($student['email']) ?>" required>
                </div>
                <div class="field">
                    <label>Personal Email</label>
                    <input type="email" name="personal_email" value="<?= e($student['personal_email'] ?? '') ?>" placeholder="student@gmail.com">
                </div>
                <div class="field full">
                    <div class="password-box">
                        <div class="password-title">Change Password</div>
                        <div class="password-note">Leave these fields blank if the current password should remain unchanged.</div>
                        <div class="grid">
                            <div class="field">
                                <label>New Password</label>
                                <input type="password" name="password" placeholder="Minimum 8 characters" autocomplete="new-password">
                            </div>
                            <div class="field">
                                <label>Confirm New Password</label>
                                <input type="password" name="confirm_password" placeholder="Re-enter new password" autocomplete="new-password">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="actions">
                <a href="student_management.php" class="btn cancel">← Back to Student Management</a>
                <button type="submit" class="btn save">Save Account Changes</button>
            </div>
        </form>
        <a class="danger-link" href="student_edit.php?id=<?= (int)$studentId ?>&remove=1" onclick="return confirm('Remove this student account? This cannot be undone.');">Remove this student account</a>
    </section>
</main>
</body>
</html>
