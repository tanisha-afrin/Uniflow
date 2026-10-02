<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit;
}

$adminId = (int)$_SESSION['admin_id'];
$stmt = $conn->prepare('SELECT admin_type, is_active, must_change_password FROM admins WHERE admin_id = ? LIMIT 1');
$stmt->bind_param('i', $adminId);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$admin || $admin['admin_type'] !== 'main_admin' || (int)$admin['is_active'] !== 1 || (int)$admin['must_change_password'] === 1) {
    http_response_code(403);
    exit('System Admin access required.');
}

if (empty($_SESSION['system_settings_csrf'])) {
    $_SESSION['system_settings_csrf'] = bin2hex(random_bytes(32));
}

$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['system_settings_csrf'], $_POST['csrf_token'] ?? '')) {
        $error = 'Security token expired. Reload the page and try again.';
    } else {
        $enabled = isset($_POST['student_account_creation_enabled']) ? '1' : '0';
        $stmt = $conn->prepare(
            'INSERT INTO system_settings (setting_key, setting_value, updated_by)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)'
        );
        $settingKey = 'student_account_creation_enabled';
        $stmt->bind_param('ssi', $settingKey, $enabled, $adminId);
        if ($stmt->execute()) {
            $success = 'System settings saved.';
        } else {
            $error = 'Could not save system settings.';
        }
        $stmt->close();
    }
}

$settingStmt = $conn->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1');
$settingKey = 'student_account_creation_enabled';
$settingStmt->bind_param('s', $settingKey);
$settingStmt->execute();
$setting = $settingStmt->get_result()->fetch_assoc();
$settingStmt->close();
$studentAccountCreationEnabled = ($setting['setting_value'] ?? '1') === '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings | UniFlow</title>
    <style>
        :root{--orange:#ed5d0e;--ink:#2a1a14;--muted:#806f64;--line:#eadfd8;--paper:#fffaf6}
        *{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at 90% 0%,rgba(255,138,61,.12),transparent 26%),var(--paper);color:var(--ink);font-family:"DM Sans","Segoe UI",sans-serif}a{color:inherit;text-decoration:none}
        .topbar{height:72px;padding:0 max(5vw,24px);display:flex;align-items:center;justify-content:space-between;background:#fffffff0;border-bottom:1px solid var(--line)}.brand{font-weight:800}.brand small{display:block;color:var(--muted);font-size:10px}.topbar nav{display:flex;gap:10px}.topbar nav a{padding:9px 13px;border:1px solid var(--line);border-radius:8px;font-size:12px;font-weight:800}
        main{width:min(900px,92%);margin:38px auto}.eyebrow{color:#bd4c0a;font-size:10px;font-weight:900;letter-spacing:1px;text-transform:uppercase}h1{margin:8px 0;font-size:30px}main>p{color:var(--muted);line-height:1.6}
        .notice{margin:16px 0;padding:12px 14px;border-radius:8px;font-size:13px;font-weight:700}.notice.success{background:#edf8f0;color:#28623a}.notice.error{background:#fff0f0;color:#a3312b}
        .setting-row{display:flex;align-items:center;justify-content:space-between;gap:24px;padding:20px;background:#fff;border:1px solid var(--line);border-radius:10px}.setting-row h2{margin:0 0 5px;font-size:15px}.setting-row p{margin:0;color:var(--muted);font-size:12px;line-height:1.55}
        .toggle{display:inline-flex;align-items:center;gap:10px;flex:0 0 auto;font-size:12px;font-weight:800}.toggle input{width:20px;height:20px;accent-color:var(--orange)}button{margin-top:16px;padding:11px 15px;border:0;border-radius:8px;background:var(--orange);color:#fff;font-weight:800;cursor:pointer}
        @media(max-width:600px){.topbar{height:auto;min-height:65px;padding:10px 4%;gap:12px}.setting-row{align-items:flex-start;flex-direction:column}h1{font-size:25px}}
    </style>
<link rel="stylesheet" href="../css/buttons.css">
</head>
<body>
<header class="topbar">
    <a class="brand" href="dashboard.php">UniFlow <small>SYSTEM ADMIN</small></a>
    <nav><a href="dashboard.php">System Overview</a><a href="../config/admin_management.php">Admin Management</a></nav>
</header>
<main>
    <span class="eyebrow">System controls</span>
    <h1>UniFlow Settings</h1>
    <p>Control whether System Admins may create UniFlow student login accounts. This setting does not control university admission or enrollment.</p>
    <?php if ($success !== ''): ?><div class="notice success" role="status"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="notice error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['system_settings_csrf']) ?>">
        <section class="setting-row">
            <div><h2>Student login account creation</h2><p>This controls UniFlow accounts created from student details supplied by the university. Admissions and enrollment remain university responsibilities.</p></div>
            <label class="toggle"><input type="checkbox" name="student_account_creation_enabled" value="1" <?= $studentAccountCreationEnabled ? 'checked' : '' ?>> Enabled</label>
        </section>
        <button type="submit">Save settings</button>
    </form>
</main>
</body>
</html>
