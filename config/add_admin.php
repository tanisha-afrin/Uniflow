<?php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/email.php';
require_once __DIR__ . '/admin_management_access.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit();
}

$currentAdminId = (int)$_SESSION['admin_id'];
$currentAdmin = getAdminManagementActor($conn, $currentAdminId);
$isMainAdmin = ($currentAdmin['admin_type'] ?? 'admin') === 'main_admin';

if ($currentAdmin && (int)$currentAdmin['must_change_password'] === 1) {
    header('Location: ../change_password.php');
    exit();
}
if (!$currentAdmin) {
    header('Location: ../login.php');
    exit();
}
if (!adminCanManageAccounts($currentAdmin)) {
    http_response_code(403);
    exit('Administrator account management access required.');
}
if (empty($_SESSION['admin_management_csrf'])) {
    $_SESSION['admin_management_csrf'] = bin2hex(random_bytes(32));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && !hash_equals((string)($_SESSION['admin_management_csrf'] ?? ''), (string)($_POST['csrf_token'] ?? ''))) {
    http_response_code(403);
    exit('Invalid security token. Reload Admin Management and try again.');
}

/* Ensure this existing database can support the invitation workflow. */
function ensureInvitationSchema(mysqli $conn): void
{
    $columns = [
        'personal_email' => 'VARCHAR(150) NULL',
        'admin_type' => "ENUM('main_admin','admin') NOT NULL DEFAULT 'admin'",
        'is_active' => 'TINYINT(1) NOT NULL DEFAULT 1',
        'must_change_password' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'password_expires_at' => 'DATETIME NULL',
        'invited_at' => 'DATETIME NULL',
        'activation_token_hash' => 'VARCHAR(255) NULL',
        'activation_expires_at' => 'DATETIME NULL',
        'activation_used' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'password_change_token_hash' => 'VARCHAR(64) NULL',
        'password_change_expires_at' => 'DATETIME NULL'
    ];

    foreach ($columns as $name => $definition) {
        $safeName = $conn->real_escape_string($name);
        $check = $conn->query("SHOW COLUMNS FROM admins LIKE '{$safeName}'");
        if ($check && $check->num_rows === 0) {
            $conn->query("ALTER TABLE admins ADD COLUMN {$name} {$definition}");
        }
    }

    $conn->query("CREATE TABLE IF NOT EXISTS admin_access (
        access_id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        access_area ENUM('technical','administrative','proctorial','lost_found') NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_admin_access (admin_id, access_area),
        FOREIGN KEY (admin_id) REFERENCES admins(admin_id) ON DELETE CASCADE
    ) ENGINE=InnoDB");
}

ensureInvitationSchema($conn);

$error = '';
$success = '';

$oldName = trim($_POST['name'] ?? '');
$oldUniversityEmail = trim($_POST['university_email'] ?? '');
$oldPersonalEmail = trim($_POST['personal_email'] ?? '');
$oldPasswordMode = $_POST['password_mode'] ?? 'automated';
$returnToManagement = $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['return_to'] ?? '') === 'admin_management';
$oldAccess = $_POST['access'] ?? [];
if (!is_array($oldAccess)) $oldAccess = [];
$oldLostFoundAuthorized = ($_POST['lost_found_authorized'] ?? '') === '1';
$oldCanManageAdmins = $isMainAdmin && ($_POST['can_manage_admins'] ?? '') === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $oldName;
    $universityEmail = strtolower($oldUniversityEmail);
    $personalEmail = strtolower($oldPersonalEmail);
    $passwordMode = $oldPasswordMode === 'manual' ? 'manual' : 'automated';
    $manualPassword = $_POST['manual_password'] ?? '';
    $accessAreas = array_values(array_unique(array_intersect(
        $oldAccess,
        ['technical', 'administrative', 'proctorial', 'lost_found']
    )));

    if ($name === '') {
        $error = 'Please enter the staff member full name.';
    } elseif (!filter_var($universityEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid University Email for portal login.';
    } elseif (!filter_var($personalEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid Personal Email for the invitation.';
    } elseif ($passwordMode === 'manual' && strlen($manualPassword) < 8) {
        $error = 'Manual password must be at least 8 characters.';
    } elseif (empty($accessAreas)) {
        $error = 'Please select at least one portal access area.';
    } elseif (in_array('lost_found', $accessAreas, true) && !$oldLostFoundAuthorized) {
        $error = 'Confirm that the relevant university authority selected this staff member before assigning Lost & Found moderator permission.';
    } elseif ($oldCanManageAdmins && !in_array('administrative', $accessAreas, true)) {
        $error = 'An Administrative Account Manager must have Administrative Portal access.';
    }

    if ($error === '') {
        $stmt = $conn->prepare('SELECT admin_id FROM admins WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $universityEmail);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($existing) $error = 'This University Email is already registered as a staff account.';
    }

    if ($error === '' && strcasecmp($universityEmail, $personalEmail) === 0) {
        $error = 'University Email and Personal Email should be different.';
    }

    if ($error === '') {
        $role = 'lost_found';
        foreach (['technical', 'administrative', 'proctorial'] as $candidate) {
            if (in_array($candidate, $accessAreas, true)) {
                $role = $candidate;
                break;
            }
        }
        if ($oldCanManageAdmins) $role = 'administrative';

        $initialPassword = $passwordMode === 'manual'
            ? $manualPassword
            : rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
        $passwordHash = password_hash($initialPassword, PASSWORD_DEFAULT);
        $passwordChangeToken = bin2hex(random_bytes(32));
        $passwordChangeTokenHash = hash('sha256', $passwordChangeToken);

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare('INSERT INTO admins
                (name, email, personal_email, password, role, admin_type, is_active, can_manage_admins, must_change_password, password_expires_at, invited_at, activation_token_hash, activation_expires_at, activation_used, password_change_token_hash, password_change_expires_at)
                VALUES (?, ?, ?, ?, ?, \'admin\', 1, ?, 0, NULL, NOW(), NULL, NULL, 0, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))');
            $stmt->bind_param('sssssis', $name, $universityEmail, $personalEmail, $passwordHash, $role, $oldCanManageAdmins, $passwordChangeTokenHash);
            if (!$stmt->execute()) throw new RuntimeException('Could not create administrator.');
            $newAdminId = $stmt->insert_id;
            $stmt->close();

            $accessStmt = $conn->prepare('INSERT INTO admin_access (admin_id, access_area) VALUES (?, ?)');
            foreach ($accessAreas as $area) {
                $accessStmt->bind_param('is', $newAdminId, $area);
                if (!$accessStmt->execute()) throw new RuntimeException('Could not save portal access.');
            }
            $accessStmt->close();

            if (!writeAdminAccountAudit($conn, $currentAdminId, $newAdminId, 'created', [
                'name' => $name,
                'email' => $universityEmail,
                'role' => $role,
                'access' => $accessAreas,
                'can_manage_admins' => $oldCanManageAdmins
            ])) {
                throw new RuntimeException('Could not write administrator account audit record.');
            }

            if (!sendAdminCredentialsEmail(
                $personalEmail,
                $name,
                $universityEmail,
                $initialPassword,
                $accessAreas,
                uniflowPasswordChangeUrl($passwordChangeToken)
            )) {
                throw new RuntimeException('Could not send administrator login details.');
            }

            $conn->commit();
            header('Location: admin_management.php?created=1');
            exit();
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('UniFlow add admin failed: ' . $e->getMessage());
            $error = 'Could not create the administrator account or send its email. Check the UniFlow sender email configuration and try again.';
        }
    }
}

if ($returnToManagement && $error !== '') {
    $_SESSION['admin_add_feedback'] = [
        'error' => $error,
        'name' => $oldName,
        'university_email' => $oldUniversityEmail,
        'personal_email' => $oldPersonalEmail,
        'password_mode' => $oldPasswordMode,
        'lost_found_authorized' => $oldLostFoundAuthorized,
        'can_manage_admins' => $oldCanManageAdmins,
        'access' => array_values(array_intersect(
            $oldAccess,
            ['technical', 'administrative', 'proctorial', 'lost_found']
        ))
    ];
    header('Location: admin_management.php?add=1#admin-form');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Staff Account | UniFlow</title>
<style>
:root {
    --orange: #ff6a00;
    --orange-2: #ff8a3d;
    --orange-dark: #e65300;
    --orange-soft: #fff0e3;
    --cream: #fffaf6;
    --ink: #2a211c;
    --muted: #806f64;
    --line: #f1dfd1;
    --white: #ffffff;
    --shadow: 0 24px 70px rgba(96, 48, 15, .12);
}

* { box-sizing: border-box; }

html { scroll-behavior: smooth; }

body {
    margin: 0;
    min-height: 100vh;
    overflow-x: hidden;
    font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
    color: var(--ink);
    background:
        radial-gradient(circle at 8% 12%, rgba(255, 138, 61, .15), transparent 24%),
        radial-gradient(circle at 92% 18%, rgba(255, 106, 0, .11), transparent 26%),
        linear-gradient(135deg, #fff8f2 0%, #fff 48%, #fff6ed 100%);
}

body::before {
    content: "";
    position: fixed;
    inset: 0;
    pointer-events: none;
    background-image: radial-gradient(rgba(255,106,0,.10) 1px, transparent 1px);
    background-size: 24px 24px;
    mask-image: linear-gradient(to bottom, rgba(0,0,0,.35), transparent 72%);
}

/* ---------- Floating 3D objects ---------- */
.orb, .cube, .ring {
    position: fixed;
    pointer-events: none;
    z-index: 0;
}

.orb {
    border-radius: 50%;
    filter: blur(.2px);
    background: radial-gradient(circle at 30% 25%, #fff 0 8%, #ffb36e 18%, #ff7a1a 54%, #e65300 100%);
    box-shadow: inset -18px -22px 35px rgba(143, 48, 0, .20), 0 35px 70px rgba(255,106,0,.15);
}

.orb.one { width: 150px; height: 150px; right: -55px; top: 125px; opacity: .55; animation: float1 7s ease-in-out infinite; }
.orb.two { width: 82px; height: 82px; left: 3%; bottom: 12%; opacity: .35; animation: float2 6s ease-in-out infinite; }

.ring {
    width: 180px;
    height: 180px;
    right: 9%;
    bottom: 7%;
    border: 20px solid rgba(255,106,0,.12);
    border-radius: 50%;
    transform: rotate(-20deg) perspective(400px) rotateY(45deg);
    box-shadow: 0 20px 50px rgba(255,106,0,.08);
}

.cube {
    width: 74px;
    height: 74px;
    left: 8%;
    top: 135px;
    border-radius: 18px;
    background: linear-gradient(145deg, #ff9b52, #f45c00);
    transform: rotate(22deg) skewY(-5deg);
    box-shadow: 15px 18px 0 rgba(197, 69, 0, .12), 0 25px 55px rgba(255,106,0,.18);
    animation: float2 8s ease-in-out infinite;
}

@keyframes float1 { 50% { transform: translateY(-18px) rotate(8deg); } }
@keyframes float2 { 50% { transform: translateY(14px) rotate(7deg); } }

/* ---------- Navigation ---------- */
.navbar {
    position: sticky;
    top: 0;
    z-index: 20;
    min-height: 76px;
    padding: 0 clamp(20px, 6vw, 84px);
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: rgba(255,255,255,.86);
    border-bottom: 1px solid rgba(241,223,209,.9);
    backdrop-filter: blur(18px);
    box-shadow: 0 8px 30px rgba(89,43,14,.06);
}

.logo {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 25px;
    font-weight: 900;
    letter-spacing: -.7px;
}

.logo-mark {
    width: 48px;
    height: 48px;
    display: grid;
    place-items: center;
    color: #fff;
    border-radius: 15px;
    background: linear-gradient(145deg, #ff984d, var(--orange));
    box-shadow: 0 6px 0 var(--orange-dark), 0 15px 28px rgba(255,106,0,.22);
    transform: rotate(-4deg);
}

.back-button {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--orange-dark);
    background: #fff;
    border: 1px solid #ffd9bf;
    padding: 11px 17px;
    border-radius: 14px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 800;
    box-shadow: 0 7px 20px rgba(255,106,0,.09);
    transition: .22s ease;
}

.back-button:hover { transform: translateY(-2px); box-shadow: 0 12px 26px rgba(255,106,0,.14); }

/* ---------- Main ---------- */
.page {
    position: relative;
    z-index: 2;
    width: min(1120px, calc(100% - 40px));
    margin: 0 auto;
    padding: 54px 0 90px;
}

.page-title {
    text-align: center;
    max-width: 780px;
    margin: 0 auto 34px;
}

.eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 13px;
    border: 1px solid #ffd4b5;
    border-radius: 999px;
    color: var(--orange-dark);
    background: rgba(255,255,255,.82);
    box-shadow: 0 8px 24px rgba(255,106,0,.08);
    font-size: 11px;
    font-weight: 900;
    letter-spacing: 2.4px;
}

.eyebrow::before { content: "✦"; font-size: 13px; }

.page-title h1 {
    margin: 15px 0 8px;
    font-size: clamp(34px, 5vw, 58px);
    line-height: 1;
    letter-spacing: -2.5px;
}

.page-title h1 span { color: var(--orange); }

.page-title p {
    margin: 0 auto;
    color: var(--muted);
    font-size: 16px;
    line-height: 1.7;
}

/* ---------- Form shell ---------- */
.form-card {
    position: relative;
    overflow: hidden;
    padding: clamp(24px, 4vw, 44px);
    border: 1px solid rgba(239,211,193,.95);
    border-radius: 32px;
    background: rgba(255,255,255,.91);
    box-shadow: var(--shadow);
    backdrop-filter: blur(16px);
}

.form-card::before {
    content: "";
    position: absolute;
    left: 0;
    right: 0;
    top: 0;
    height: 6px;
    background: linear-gradient(90deg, var(--orange-dark), var(--orange-2), var(--orange));
}

.form-card::after {
    content: "";
    position: absolute;
    width: 230px;
    height: 230px;
    right: -110px;
    top: -125px;
    border-radius: 50%;
    background: rgba(255,138,61,.12);
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 24px;
}

.form-group { min-width: 0; }
.form-group.full { grid-column: 1 / -1; }

label {
    display: block;
    margin: 0 0 9px 3px;
    font-size: 13px;
    font-weight: 850;
    color: #49372c;
}

.input-wrap { position: relative; }
.input-wrap::before {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    z-index: 2;
    opacity: .6;
    font-size: 16px;
}
.input-wrap.user::before { content: "◉"; }
.input-wrap.mail::before { content: "✉"; }
.input-wrap.lock::before { content: "◆"; font-size: 11px; }

input[type="text"], input[type="email"], input[type="password"] {
    width: 100%;
    height: 54px;
    padding: 0 17px 0 45px;
    border: 1px solid #ead7c8;
    border-radius: 16px;
    outline: none;
    color: var(--ink);
    background: #fff;
    font-size: 15px;
    box-shadow: inset 0 1px 0 rgba(255,255,255,.9), 0 7px 18px rgba(82,45,21,.04);
    transition: .2s ease;
}

input:focus {
    border-color: #ff984d;
    box-shadow: 0 0 0 4px rgba(255,106,0,.10), 0 12px 25px rgba(255,106,0,.08);
    transform: translateY(-1px);
}

/* ---------- Access area ---------- */
.access-box {
    position: relative;
    z-index: 2;
    padding: 26px;
    border: 1px solid #ffd8bc;
    border-radius: 24px;
    background: linear-gradient(145deg, #fffaf6, #fff3e9);
    box-shadow: inset 0 1px 0 #fff, 0 15px 35px rgba(255,106,0,.07);
}

.access-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 20px;
}

.access-title { margin: 0; font-size: 20px; font-weight: 900; }
.access-subtitle { margin: 5px 0 0; color: var(--muted); font-size: 13px; line-height: 1.5; }
.access-hint {
    flex: 0 0 auto;
    padding: 7px 10px;
    border-radius: 999px;
    background: #fff;
    border: 1px solid #f6d3bb;
    color: var(--orange-dark);
    font-size: 11px;
    font-weight: 800;
}

.checkbox-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 13px;
}

.checkbox-item { position: relative; }
.checkbox-item input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.checkbox-item label {
    min-height: 108px;
    margin: 0;
    padding: 18px;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    justify-content: space-between;
    gap: 9px;
    cursor: pointer;
    border: 1px solid #ecd8ca;
    border-radius: 18px;
    background: #fff;
    color: #3b2d25;
    box-shadow: 0 9px 20px rgba(86,43,17,.05);
    transition: .22s ease;
}

.checkbox-item label::before {
    content: "";
    width: 31px;
    height: 31px;
    display: grid;
    place-items: center;
    border-radius: 10px;
    border: 1px solid #efd6c5;
    background: #fffaf6;
    box-shadow: inset 0 -3px 0 rgba(255,106,0,.04);
    transition: .22s ease;
}

.checkbox-item label span { font-size: 13px; font-weight: 850; }
.checkbox-item input:checked + label {
    border-color: #ff9a59;
    background: linear-gradient(145deg, #fff, #fff0e5);
    transform: translateY(-4px);
    box-shadow: 0 16px 28px rgba(255,106,0,.13), inset 0 0 0 1px rgba(255,106,0,.08);
}
.checkbox-item input:checked + label::before {
    content: "✓";
    color: #fff;
    border-color: var(--orange);
    background: linear-gradient(145deg, #ff984d, var(--orange));
    box-shadow: 0 4px 0 var(--orange-dark);
}

/* ---------- Automatic Security Info ---------- */
.security-box {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 18px 20px;
    border: 1px solid #ffd4b5;
    border-radius: 18px;
    background: linear-gradient(145deg, #fffaf6, #fff1e6);
    box-shadow: 0 10px 25px rgba(255,106,0,.06);
}

.security-icon {
    width: 42px;
    height: 42px;
    flex: 0 0 42px;
    display: grid;
    place-items: center;
    border-radius: 13px;
    background: linear-gradient(145deg, #ff984d, var(--orange));
    box-shadow: 0 4px 0 var(--orange-dark);
    font-size: 19px;
}

.security-box strong {
    display: block;
    margin-bottom: 5px;
    font-size: 14px;
    color: #3b2d25;
}

.security-box p {
    margin: 0;
    color: var(--muted);
    font-size: 13px;
    line-height: 1.55;
}

.security-box small {
    display: block;
    margin-top: 7px;
    color: #b85b20;
    font-size: 12px;
    line-height: 1.45;
    font-weight: 700;
}

/* ---------- Error ---------- */
.error {
    display: flex;
    align-items: center;
    gap: 11px;
    margin-bottom: 24px;
    padding: 14px 16px;
    border: 1px solid #ffc18f;
    border-radius: 15px;
    color: #b84400;
    background: #fff2e7;
    font-weight: 700;
}
.error::before { content: "!"; width: 25px; height: 25px; display:grid; place-items:center; border-radius:50%; background:#ffdfc7; }

/* ---------- Actions ---------- */
.form-actions {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 12px;
    margin-top: 30px;
    padding-top: 24px;
    border-top: 1px solid #f2e3d8;
}

.cancel-button, .save-button {
    min-height: 50px;
    padding: 0 22px;
    border-radius: 15px;
    font-size: 14px;
    font-weight: 900;
    text-decoration: none;
    cursor: pointer;
    transition: .2s ease;
}

.cancel-button {
    display: inline-flex;
    align-items: center;
    background: #fff;
    color: #66564d;
    border: 1px solid #e8d9cf;
}
.cancel-button:hover { transform: translateY(-2px); border-color: #ffbf94; }

.save-button {
    border: 0;
    color: #fff;
    background: linear-gradient(145deg, #ff9346, var(--orange));
    box-shadow: 0 5px 0 var(--orange-dark), 0 16px 28px rgba(255,106,0,.22);
}
.save-button:hover { transform: translateY(-3px); box-shadow: 0 8px 0 var(--orange-dark), 0 20px 34px rgba(255,106,0,.25); }
.save-button:active { transform: translateY(2px); box-shadow: 0 2px 0 var(--orange-dark), 0 10px 20px rgba(255,106,0,.18); }

@media (max-width: 850px) {
    .checkbox-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (max-width: 680px) {
    .navbar { min-height: 70px; padding: 0 18px; }
    .logo { font-size: 21px; }
    .logo-mark { width: 42px; height: 42px; border-radius: 13px; }
    .back-button { padding: 10px 12px; font-size: 12px; }
    .page { width: min(100% - 26px, 1120px); padding-top: 38px; }
    .form-grid { grid-template-columns: 1fr; }
    .form-group.full { grid-column: auto; }
    .access-head { flex-direction: column; }
    .checkbox-grid { grid-template-columns: 1fr 1fr; }
    .form-actions { flex-direction: column-reverse; }
    .cancel-button, .save-button { width: 100%; justify-content: center; text-align: center; }
    .ring { right: -80px; }
}

@media (max-width: 420px) {
    .checkbox-grid { grid-template-columns: 1fr; }
    .checkbox-item label { min-height: 86px; }
}

/* New two-email form refinements */
.form-card { width:min(100%, 1180px); }
.form-grid { grid-template-columns:1fr 1fr; }
.form-group.full { grid-column:1 / -1; }
.help { margin-top:7px; color:#9a7a69; font-size:12px; line-height:1.45; }
.email-note { color:#c15b1b; font-weight:800; }
.page-title { max-width:980px; }
.checkbox-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
.checkbox-item label { min-height:118px; }
.password-mode-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; }
.password-mode-option { display:flex; align-items:flex-start; gap:12px; margin:0; padding:17px; border:1px solid #ead7c8; border-radius:15px; background:#fff; cursor:pointer; }
.password-mode-option input { flex:0 0 auto; width:18px; height:18px; margin:2px 0 0; accent-color:var(--orange); }
.password-mode-option span { display:grid; gap:5px; }
.password-mode-option strong { color:var(--ink); font-size:14px; }
.password-mode-option small { color:var(--muted); font-size:12px; line-height:1.45; font-weight:500; }
.password-mode-option:focus-within { outline:3px solid rgba(255,106,0,.18); border-color:var(--orange); }
#manual-password-field[hidden] { display:none; }
@media(max-width:850px){ .form-grid{grid-template-columns:1fr;} .form-group.full{grid-column:auto;} }
@media(max-width:560px){ .password-mode-grid{grid-template-columns:1fr;} }
</style>
<link rel="stylesheet" href="../css/buttons.css">
</head>
<body>
<div class="orb one"></div><div class="orb two"></div><div class="cube"></div><div class="ring"></div>
<nav class="navbar">
  <div class="logo"><span class="logo-mark">U</span><span>UniFlow</span></div>
  <a href="admin_management.php" class="back-button">← Back to Admin List</a>
</nav>
<main class="page">
<section class="page-title">
  <div class="eyebrow">ADMINISTRATION</div>
  <h1>Create Staff <span>Account</span></h1>
    <p>Create a UniFlow staff account and assign system permissions after the responsible university office selects the staff member.</p>
</section>
<section class="form-card">
<?php if ($error !== '' && $error !== 'INVITATION_SEND_FAILED'): ?><div class="error">⚠ <?= e($error) ?></div><?php endif; ?>
<form method="POST" action="">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['admin_management_csrf'], ENT_QUOTES, 'UTF-8') ?>">
<div class="form-grid">
<div class="form-group">
<label for="admin-name">Staff Full Name</label>
<div class="input-wrap user"><input id="admin-name" type="text" name="name" value="<?= e($oldName) ?>" placeholder="Enter staff member full name" required></div>
<div class="help">Use the staff member's official name.</div>
</div>
<div class="form-group">
<label for="university-email">Staff University Email <span class="email-note">(Login Email)</span></label>
<div class="input-wrap mail"><input id="university-email" type="email" name="university_email" value="<?= e($oldUniversityEmail) ?>" placeholder="admin@university.edu" required></div>
<div class="help">This exact email will be used to log in to UniFlow after activation.</div>
</div>
<div class="form-group full">
<label for="personal-email">Personal Email <span class="email-note">(Invitation Email)</span></label>
<div class="input-wrap mail"><input id="personal-email" type="email" name="personal_email" value="<?= e($oldPersonalEmail) ?>" placeholder="admin@gmail.com" required></div>
<div class="help">The login details will be sent here. This address is <strong>not</strong> used for portal login; the recipient does not need to provide an email key.</div>
</div>
<div class="form-group full">
<label>Initial Password</label>
<div class="password-mode-grid" role="radiogroup" aria-label="Initial password setup">
<label class="password-mode-option" for="password-mode-automated"><input id="password-mode-automated" type="radio" name="password_mode" value="automated" <?= $oldPasswordMode !== 'manual' ? 'checked' : '' ?>><span><strong>Automated</strong><small>UniFlow creates a strong password and includes it in the email.</small></span></label>
<label class="password-mode-option" for="password-mode-manual"><input id="password-mode-manual" type="radio" name="password_mode" value="manual" <?= $oldPasswordMode === 'manual' ? 'checked' : '' ?>><span><strong>Manual: Create your own</strong><small>Set the password that will be sent to the administrator.</small></span></label>
</div>
</div>
<div id="manual-password-field" class="form-group full" <?= $oldPasswordMode === 'manual' ? '' : 'hidden' ?>>
<label for="manual-password">Create Password</label>
<div class="input-wrap lock"><input id="manual-password" type="password" name="manual_password" minlength="8" autocomplete="new-password" placeholder="Enter at least 8 characters" <?= $oldPasswordMode === 'manual' ? 'required' : '' ?>></div>
<div class="help">Use at least 8 characters. This password is stored securely as a hash and sent to the Personal Email.</div>
</div>
<div class="form-group full"><div class="help">The login email also includes an optional, one-time link to change this initial password. The administrator can keep the original password instead.</div></div>
<div class="form-group full">
<div class="security-box"><div class="security-icon">✉</div><div><strong>What happens after account creation?</strong><p>UniFlow creates the account and emails the University Email and initial password to the Personal Email above.</p><small>The staff member uses the University Email to log in.</small></div></div>
</div>
<div class="form-group full">
<div class="access-box"><div class="access-head"><div><h2 class="access-title">UniFlow Portal Permissions</h2><p class="access-subtitle">Grant only the system access needed for the staff member's university-assigned duties.</p></div><div class="access-hint">MULTI-SELECT</div></div>
<div class="checkbox-grid">
<div class="checkbox-item"><input id="access-technical" type="checkbox" name="access[]" value="technical" <?= in_array('technical',$oldAccess,true)?'checked':'' ?>><label for="access-technical"><span>⚙ Technical Portal</span><small>Technical administration and issue management</small></label></div>
<div class="checkbox-item"><input id="access-administrative" type="checkbox" name="access[]" value="administrative" <?= in_array('administrative',$oldAccess,true)?'checked':'' ?>><label for="access-administrative"><span>▣ Administrative Portal</span><small>Administrative services and issue management</small></label></div>
<div class="checkbox-item"><input id="access-proctorial" type="checkbox" name="access[]" value="proctorial" <?= in_array('proctorial',$oldAccess,true)?'checked':'' ?>><label for="access-proctorial"><span>◈ Proctorial Portal</span><small>Proctorial administration and issue management</small></label></div>
<div class="checkbox-item"><input id="access-lost-found" type="checkbox" name="access[]" value="lost_found" <?= in_array('lost_found',$oldAccess,true)?'checked':'' ?>><label for="access-lost-found"><span>⌕ Lost &amp; Found Moderator Permission</span><small>Moderation tools for an authorized staff member selected by the university</small></label></div>
</div>
<div class="help" style="margin-top:12px">University Administration, Student Affairs or Proctorial selects the authorized staff member first. System Admin creates the UniFlow account and assigns this permission.</div>
<label class="help" style="display:flex;align-items:flex-start;gap:8px;margin-top:10px;color:var(--ink)"><input type="checkbox" name="lost_found_authorized" value="1" <?= $oldLostFoundAuthorized ? 'checked' : '' ?> style="margin-top:2px"> I confirm the relevant university authority has selected this person as an authorized Lost &amp; Found moderator.</label>
</div></div>
</div>
</div>
<div class="form-actions"><a href="admin_management.php" class="cancel-button">Cancel</a><button type="submit" class="save-button">Create Staff Account &amp; Send Login Details&nbsp; →</button></div>
</form>
</section></main>
<script>
const manualPasswordMode = document.getElementById('password-mode-manual');
const manualPasswordField = document.getElementById('manual-password-field');
const manualPasswordInput = document.getElementById('manual-password');

function updatePasswordMode() {
    const isManual = manualPasswordMode.checked;
    manualPasswordField.hidden = !isManual;
    manualPasswordInput.required = isManual;
    if (!isManual) manualPasswordInput.value = '';
}

document.querySelectorAll('input[name="password_mode"]').forEach((input) => {
    input.addEventListener('change', updatePasswordMode);
});
updatePasswordMode();
</script>
</body></html>
