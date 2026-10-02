<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit;
}

$stmt = $conn->prepare('SELECT name, email, admin_type, is_active, must_change_password FROM admins WHERE admin_id = ? LIMIT 1');
$adminId = (int)$_SESSION['admin_id'];
$stmt->bind_param('i', $adminId);
$stmt->execute();
$systemAdmin = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$systemAdmin || $systemAdmin['admin_type'] !== 'main_admin' || (int)$systemAdmin['is_active'] !== 1 || (int)$systemAdmin['must_change_password'] === 1) {
    http_response_code(403);
    exit('System Admin access required.');
}

$counts = [];
foreach ([
    'admins' => 'SELECT COUNT(*) FROM admins',
    'students' => 'SELECT COUNT(*) FROM students'
] as $key => $query) {
    $counts[$key] = (int)$conn->query($query)->fetch_row()[0];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Admin | UniFlow</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{--orange:#f97316;--orange-dark:#c2410c;--orange-light:#fff7ed;--white:#fff;--text:#3f2a20;--muted:#8a6d60;--border:rgba(154,52,18,.14);--shadow:0 16px 45px rgba(120,53,15,.09)}
        *{box-sizing:border-box}
        body{margin:0;color:var(--text);font-family:"Segoe UI",Arial,sans-serif;background:#fffaf5}
        a{color:inherit;text-decoration:none}
        .navbar{position:sticky;top:0;z-index:5;min-height:76px;padding:10px 5%;display:flex;align-items:center;justify-content:space-between;gap:24px;background:rgba(255,255,255,.94);backdrop-filter:blur(16px);border-bottom:1px solid var(--border);box-shadow:0 8px 30px rgba(50,25,10,.06)}
        .logo{display:flex;align-items:center;gap:11px;font-family:"Plus Jakarta Sans",sans-serif;font-size:22px;font-weight:800;white-space:nowrap;color:#1d120d;letter-spacing:-.5px}.logo-icon{width:48px;height:48px;border-radius:15px;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;background:linear-gradient(145deg,#ff8a3d,#e65100);box-shadow:0 7px 0 #c84400,0 16px 28px rgba(255,106,0,.28);transform:rotate(-5deg) translateY(-1px);transition:.25s ease}.logo:hover .logo-icon{transform:rotate(5deg) translateY(-3px) scale(1.04)}
        .nav-right{display:flex;align-items:center;justify-content:flex-end;gap:9px;flex-wrap:wrap}.welcome-text{padding:9px 14px;border:1px solid var(--border);border-radius:11px;background:var(--orange-light);color:var(--muted);font-size:13px}.welcome-text strong{color:var(--text)}
        .nav-button{min-height:40px;padding:9px 14px;display:inline-flex;align-items:center;justify-content:center;border:1px solid var(--border);border-radius:10px;background:#fff;color:var(--text);font-size:12px;font-weight:800;transition:.18s ease}.nav-button:hover{border-color:var(--orange);color:var(--orange-dark);transform:translateY(-1px)}.nav-button.primary{border-color:var(--orange);background:var(--orange);color:#fff;box-shadow:0 4px 0 var(--orange-dark)}
        .container{width:92%;max-width:1320px;margin:0 auto;padding:34px 0 65px}
        .hero{position:relative;overflow:hidden;min-height:290px;margin-bottom:26px;padding:32px 40px;display:flex;align-items:center;border:1px solid var(--border);border-radius:24px;background:linear-gradient(135deg,#fff 0%,#fff8f1 100%);box-shadow:var(--shadow)}.hero::after{content:"";position:absolute;width:360px;height:360px;right:-130px;top:-185px;border:38px solid rgba(249,115,22,.07);border-radius:50%;pointer-events:none}.hero-content{position:relative;z-index:3;max-width:800px}.hero-badge{display:inline-flex;align-items:center;gap:8px;padding:8px 13px;border:1px solid #fed7aa;border-radius:999px;background:var(--orange-light);color:var(--orange-dark);font-size:11px;font-weight:900;letter-spacing:.5px;text-transform:uppercase;box-shadow:0 5px 0 #f7dcc8,0 10px 25px rgba(255,106,0,.08)}.hero-badge::before{content:"";width:6px;height:6px;flex:0 0 6px;border-radius:50%;background:var(--orange)}.hero h1{margin:17px 0 10px;color:#2b1710;font-size:36px;line-height:1.12}.hero h1 span{color:var(--orange-dark)}.hero-description{max-width:690px;margin:0;color:var(--muted);font-size:14px;line-height:1.7}.hero-object{position:absolute;z-index:2;border-radius:28px;pointer-events:none;animation:systemFloat 5s ease-in-out infinite;transform-style:preserve-3d}.object-one{width:175px;height:175px;right:100px;top:55px;background:linear-gradient(145deg,#fb923c,#ea580c);transform:rotate(25deg);box-shadow:18px 18px 0 #c2410c,25px 30px 40px rgba(234,88,12,.22)}.object-two{width:70px;height:70px;right:285px;bottom:42px;background:#fff;border:9px solid var(--orange);transform:rotate(18deg);box-shadow:0 15px 30px rgba(249,115,22,.15);animation-delay:-1.5s}.object-three{width:38px;height:38px;right:55px;bottom:45px;border-radius:50%;background:var(--orange);box-shadow:10px 10px 0 rgba(234,88,12,.25);animation-delay:-3s}@keyframes systemFloat{0%,100%{translate:0 0;rotate:0deg}50%{translate:0 -10px;rotate:2deg}}
        .stats-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:15px;margin-bottom:30px}.stat-card{position:relative;overflow:hidden;min-height:145px;padding:20px;border:1px solid var(--border);border-radius:16px;background:#fff;box-shadow:var(--shadow)}.stat-card::before{content:"";position:absolute;inset:0 0 auto;height:4px;background:var(--orange)}.stat-label{color:var(--muted);font-size:12px;font-weight:700}.stat-number{margin-top:16px;color:#2b1710;font-size:32px;font-weight:900}.stat-title{margin-top:7px;color:var(--text);font-size:12px;font-weight:700}
        .section{margin:0 0 22px;padding:23px;border:1px solid var(--border);border-radius:18px;background:rgba(255,255,255,.94);box-shadow:var(--shadow)}.section-header{display:flex;align-items:center;justify-content:space-between;gap:15px;margin-bottom:16px}.section-title h2{margin:0;font-size:18px}.section-title p{margin:6px 0 0;color:var(--muted);font-size:12px}.section-badge{padding:7px 11px;border:1px solid #fed7aa;border-radius:999px;background:var(--orange-light);color:var(--orange-dark);font-size:11px;font-weight:900}
        .module-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:11px}.module-link{display:flex;align-items:center;justify-content:space-between;gap:15px;min-height:68px;padding:15px 17px;border:1px solid var(--border);border-radius:12px;background:#fff;color:var(--text);font-size:13px;font-weight:800;transition:.18s ease}.module-link:hover{border-color:#f9a56f;background:#fffaf6;transform:translateY(-1px)}.module-link.primary{border-color:var(--orange);background:var(--orange);color:#fff;box-shadow:0 4px 0 var(--orange-dark)}.module-link span{color:var(--orange-dark);font-size:18px}.module-link.primary span{color:#fff}
        @media(max-width:950px){.stats-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:760px){.hero{min-height:330px;padding:35px 28px}.object-one{width:115px;height:115px;right:30px;top:55px}.object-two{right:155px;bottom:30px}.object-three{right:25px}}
        @media(max-width:650px){.navbar{padding:10px 4%;gap:12px}.nav-right{gap:5px}.welcome-text{display:none}.nav-button{padding:8px 10px;font-size:11px}.container{padding-top:24px}.hero{min-height:350px;padding:30px 23px}.hero h1{font-size:29px}.hero-object{opacity:.28}.object-one{right:24px;top:auto;bottom:22px}.object-two{right:130px;bottom:24px}.object-three{right:20px;bottom:24px}.stats-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}.stat-card{min-height:125px;padding:16px}.stat-number{font-size:27px}.section{padding:17px}.module-grid{grid-template-columns:1fr}}
        .stats-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
    </style>
<link rel="stylesheet" href="../css/buttons.css">
</head>
<body>
<header class="navbar">
    <a class="logo" href="dashboard.php"><span class="logo-icon" aria-hidden="true">U</span><span class="logo-word">UniFlow</span></a>
    <div class="nav-right">
        <div class="welcome-text">Welcome, <strong><?= e($systemAdmin['name']) ?></strong></div>
        <a class="nav-button" href="../index.php">Home</a>
        <a class="nav-button primary" href="logout.php">Logout</a>
    </div>
</header>
<main class="container">
    <section class="hero">
        <div class="hero-content">
            <span class="hero-badge">IT / System Administration</span>
            <h1>System <span>Dashboard</span></h1>
            <p class="hero-description">Manage UniFlow accounts, portal permissions, invitations and system settings. University offices remain responsible for admissions, university services and staff appointments.</p>
        </div>
        <div class="hero-object object-one" aria-hidden="true"></div>
        <div class="hero-object object-two" aria-hidden="true"></div>
        <div class="hero-object object-three" aria-hidden="true"></div>
    </section>

    <section class="stats-grid" aria-label="System totals">
        <div class="stat-card"><div class="stat-label">Staff accounts</div><div class="stat-number"><?= $counts['admins'] ?></div><div class="stat-title">UniFlow administrator accounts</div></div>
        <div class="stat-card"><div class="stat-label">Student login accounts</div><div class="stat-number"><?= $counts['students'] ?></div><div class="stat-title">UniFlow system accounts</div></div>
    </section>

    <section class="section">
        <div class="section-header">
            <div class="section-title"><h2>Service permission assignment</h2><p>University Administration, Student Affairs or Proctorial selects the authorized staff member. System Admin provisions the UniFlow account and assigns the permission.</p></div>
            <span class="section-badge">Account + access</span>
        </div>
        <div class="module-grid">
            <a class="module-link primary" href="../config/admin_management.php?add=1#admin-form"><span>Assign Lost &amp; Found Moderator Permission</span><span>→</span></a>
        </div>
    </section>

    <section class="section">
        <div class="section-header">
            <div class="section-title"><h2>System controls</h2><p>Manage system accounts, portal permissions and platform policy.</p></div>
        </div>
        <div class="module-grid">
            <a class="module-link" href="../student/register.php"><span>Student Login Account Management</span><span>→</span></a>
            <a class="module-link" href="../config/admin_management.php?add=1#admin-form"><span>Staff Account Invitations</span><span>→</span></a>
            <a class="module-link" href="settings.php"><span>System Settings</span><span>→</span></a>
        </div>
    </section>
</main>
</body>
</html>
