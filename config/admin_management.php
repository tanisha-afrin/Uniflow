<?php
require_once "database.php";
require_once "auth.php";
require_once __DIR__ . '/admin_management_access.php';

if (!isset($_SESSION["admin_id"])) {
    header("Location: ../login.php");
    exit();
}

$current_admin_id = (int) $_SESSION["admin_id"];

$current_admin = getAdminManagementActor($conn, $current_admin_id);

if (!$current_admin) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

$is_main_admin = (($current_admin["admin_type"] ?? "admin") === "main_admin")
    && (int)$current_admin["is_active"] === 1
    && (int)$current_admin["must_change_password"] !== 1;
if ((int)$current_admin["must_change_password"] === 1) {
    header("Location: ../change_password.php");
    exit();
}
if (!adminCanManageAccounts($current_admin)) {
    header('Location: ../index.php');
    exit();
}
$can_manage_accounts = true;
if (empty($_SESSION['admin_status_csrf'])) {
    $_SESSION['admin_status_csrf'] = bin2hex(random_bytes(32));
}
if (empty($_SESSION['admin_delete_csrf'])) {
    $_SESSION['admin_delete_csrf'] = bin2hex(random_bytes(32));
}
if (empty($_SESSION['admin_management_csrf'])) {
    $_SESSION['admin_management_csrf'] = bin2hex(random_bytes(32));
}

$accountFilter = $is_main_admin ? '' : "WHERE admin_type = 'admin'";
$result = $conn->query(
    "SELECT admin_id, name, email, role, admin_type, is_active,
            can_manage_admins, created_at
     FROM admins {$accountFilter}
     ORDER BY CASE WHEN can_manage_admins = 1 THEN 0 ELSE 1 END,
              role, admin_id"
);

$admins = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $admins[] = $row;
    }
}

$auditResult = $conn->query(
    "SELECT audit.audit_id, audit.action, audit.details_json, audit.created_at,
            actor.name AS actor_name, target.name AS target_name
     FROM admin_account_audit AS audit
     LEFT JOIN admins AS actor ON actor.admin_id = audit.actor_admin_id
     LEFT JOIN admins AS target ON target.admin_id = audit.target_admin_id
     WHERE target.admin_type = 'admin'
     ORDER BY audit.created_at DESC, audit.audit_id DESC
     LIMIT 30"
);
$accountAudit = [];
if ($auditResult) {
    while ($row = $auditResult->fetch_assoc()) {
        $row['details'] = json_decode($row['details_json'], true) ?: [];
        $accountAudit[] = $row;
    }
}

$dashboard_link = "../proctorial/dashboard.php";
if (($current_admin["admin_type"] ?? "admin") === "main_admin") {
    $dashboard_link = "../system_admin/dashboard.php";
} elseif ($current_admin["role"] === "technical") {
    $dashboard_link = "../technical/dashboard.php";
} elseif ($current_admin["role"] === "administrative") {
    $dashboard_link = "../administrative/dashboard.php";
} elseif ($current_admin["role"] === "proctorial") {
    $dashboard_link = "../proctorial/dashboard.php";
}

$main_count = 0;
$manager_count = 0;
foreach ($admins as $admin) {
    if ($admin["admin_type"] === "main_admin") {
        $main_count++;
    }
    if ((int)$admin["can_manage_admins"] === 1) {
        $manager_count++;
    }
}
$normal_count = count($admins) - $main_count - $manager_count;
$adminAddFeedback = $_SESSION['admin_add_feedback'] ?? [];
unset($_SESSION['admin_add_feedback']);
$adminAddError = $adminAddFeedback['error'] ?? '';
$adminAddValues = $adminAddFeedback;
$showAddAdminForm = isset($_GET['add']) || $adminAddError !== '';
$adminCreated = isset($_GET['created']);

function initials($name) {
    $parts = preg_split('/\s+/', trim($name));
    $out = "";
    foreach (array_slice($parts, 0, 2) as $part) {
        $out .= strtoupper(substr($part, 0, 1));
    }
    return $out ?: "A";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Management | UniFlow</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">

<style>
:root{
    --orange:#ff6a00;
    --orange-2:#ff8533;
    --orange-dark:#e95700;
    --orange-pale:#fffaf5;
    --orange-light:#fff3e8;
    --orange-soft:#ffe4cf;
    --white:#fff;
    --ink:#2f211a;
    --muted:#8a7467;
    --line:#f1e4da;
    --shadow:0 20px 55px rgba(255,106,0,.10);
    --shadow-sm:0 8px 25px rgba(255,106,0,.08);
}
*{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{
    min-height:100vh;
    font-family:Inter,Arial,sans-serif;
    color:var(--ink);
    background:#fffaf5;
}
a{text-decoration:none;color:inherit}

.navbar{
    position:sticky;top:0;z-index:100;
    min-height:76px;padding:0 4%;
    display:flex;align-items:center;justify-content:space-between;
    background:rgba(255,255,255,.90);
    backdrop-filter:blur(18px);
    border-bottom:1px solid rgba(255,106,0,.13);
}
.logo{
    display:flex;align-items:center;gap:10px;
    font:800 25px "Plus Jakarta Sans",sans-serif;
    letter-spacing:-.8px;color:var(--ink);
}
.logo-mark{
    width:40px;height:40px;border-radius:13px;
    display:grid;place-items:center;
    color:#fff;font-weight:800;
    background:linear-gradient(145deg,var(--orange-2),var(--orange-dark));
    box-shadow:0 8px 20px rgba(255,106,0,.22);
}
.logo span{color:var(--orange)}
.nav-right{display:flex;align-items:center;gap:9px}
.nav-button{
    min-height:41px;padding:9px 15px;border-radius:11px;
    display:inline-flex;align-items:center;justify-content:center;
    font-size:12px;font-weight:800;transition:.22s ease;
}
.back-button{background:#fff;border:1px solid var(--line)}
.back-button:hover{border-color:var(--orange-soft);background:var(--orange-light);color:var(--orange-dark);transform:translateY(-1px)}
.logout-button{
    color:#fff;background:linear-gradient(135deg,var(--orange),var(--orange-dark));
    box-shadow:0 7px 18px rgba(255,106,0,.18)
}
.logout-button:hover{transform:translateY(-1px);box-shadow:0 10px 24px rgba(255,106,0,.25)}

.page{position:relative;overflow:hidden;padding:48px 4% 70px}
.container{position:relative;z-index:2;max-width:1480px;margin:auto}

.decor{
    position:absolute;pointer-events:none;z-index:0;
    border-radius:30px;background:var(--orange);opacity:.045;
}
.decor.one{width:260px;height:260px;right:-100px;top:95px;transform:rotate(32deg)}
.decor.two{width:180px;height:180px;left:-100px;bottom:130px;border:28px solid var(--orange);background:transparent;border-radius:50%}
.decor.three{width:85px;height:85px;right:18%;bottom:80px;transform:rotate(25deg)}

.hero{
    display:grid;grid-template-columns:1fr auto;gap:30px;align-items:end;
    margin-bottom:25px;
}
.hero-copy{max-width:850px}
.badge{
    display:inline-flex;align-items:center;gap:7px;
    padding:7px 12px;margin-bottom:14px;border-radius:999px;
    color:var(--orange-dark);background:var(--orange-light);
    border:1px solid var(--orange-soft);
    font-size:10px;font-weight:800;letter-spacing:.8px;text-transform:uppercase;
}
h1{
    font:800 clamp(35px,4.5vw,56px)/1.04 "Plus Jakarta Sans",sans-serif;
    letter-spacing:-2px;
}
h1 span{color:var(--orange)}
.hero-copy p{margin-top:13px;max-width:690px;color:var(--muted);font-size:14px;line-height:1.75}
.add-button{
    display:inline-flex;align-items:center;gap:9px;
    padding:14px 20px;border-radius:13px;color:#fff;
    background:linear-gradient(135deg,var(--orange),var(--orange-dark));
    font-size:13px;font-weight:800;
    box-shadow:0 10px 25px rgba(255,106,0,.22);
    transition:.22s ease;
}
.add-button:hover{transform:translateY(-2px);box-shadow:0 14px 32px rgba(255,106,0,.28)}

.notice{
    display:flex;align-items:center;gap:15px;
    padding:17px 19px;margin-bottom:23px;
    background:rgba(255,255,255,.94);
    border:1px solid var(--line);border-left:4px solid var(--orange);
    border-radius:16px;box-shadow:var(--shadow-sm);
}
.notice-icon{
    flex:0 0 42px;width:42px;height:42px;border-radius:13px;
    display:grid;place-items:center;background:var(--orange-light);
    color:var(--orange);font-size:19px;
}
.notice strong{display:block;font-size:13px;color:var(--orange-dark);font-weight:800}
.notice p{margin-top:4px;color:var(--muted);font-size:12px;line-height:1.6}

.stats{
    display:grid;grid-template-columns:repeat(3,1fr);gap:15px;margin-bottom:24px
}
.stat{
    position:relative;overflow:hidden;
    padding:20px;border:1px solid var(--line);border-radius:18px;
    background:rgba(255,255,255,.96);box-shadow:var(--shadow-sm);
    transition:.22s ease;
}
.stat:hover{transform:translateY(-3px);border-color:var(--orange-soft);box-shadow:var(--shadow)}
.stat:after{
    content:"";position:absolute;width:85px;height:85px;right:-30px;bottom:-35px;
    border-radius:50%;background:var(--orange);opacity:.06
}
.stat-icon{
    width:40px;height:40px;border-radius:12px;display:grid;place-items:center;
    background:var(--orange-light);color:var(--orange);font-size:18px;margin-bottom:14px
}
.stat-number{font:800 27px "Plus Jakarta Sans",sans-serif}
.stat-label{margin-top:3px;color:var(--muted);font-size:11px;font-weight:700}

.table-card{
    overflow:hidden;background:rgba(255,255,255,.97);
    border:1px solid var(--line);border-radius:21px;box-shadow:var(--shadow)
}
.table-top{
    display:flex;align-items:center;justify-content:space-between;gap:15px;
    padding:21px 23px;border-bottom:1px solid var(--line)
}
.table-title{display:flex;align-items:center;gap:12px}
.table-icon{
    width:42px;height:42px;border-radius:12px;display:grid;place-items:center;
    color:var(--orange);background:var(--orange-light);font-size:19px
}
.table-title h2{font:800 19px "Plus Jakarta Sans",sans-serif}
.table-title p{margin-top:3px;color:var(--muted);font-size:11px}
.count-pill{
    padding:7px 11px;border-radius:999px;color:var(--orange-dark);
    background:var(--orange-light);border:1px solid var(--orange-soft);
    font-size:10px;font-weight:800;white-space:nowrap
}
.table-wrap{overflow-x:auto}
table{width:100%;min-width:920px;border-collapse:collapse}
thead{background:linear-gradient(90deg,#fff5ea,#fff)}
th{
    padding:14px 17px;text-align:left;color:var(--orange-dark);
    font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.7px;
    border-bottom:1px solid var(--line)
}
td{
    padding:16px 17px;color:#5e5049;font-size:12px;
    border-bottom:1px solid #f7eee7;vertical-align:middle
}
tbody tr{transition:.18s ease}
tbody tr:hover{background:#fffaf6}
tbody tr:last-child td{border-bottom:0}
.row-number{width:40px;color:#aaa;font-weight:800}
.profile{display:flex;align-items:center;gap:11px}
.avatar{
    flex:0 0 40px;width:40px;height:40px;border-radius:12px;
    display:grid;place-items:center;color:#fff;font-size:12px;font-weight:800;
    background:linear-gradient(145deg,var(--orange-2),var(--orange));
    box-shadow:0 5px 13px rgba(255,106,0,.18)
}
.name{color:var(--ink);font-size:12px;font-weight:800}
.email{margin-top:3px;color:#96867d;font-size:11px}
.role-badge,.type-badge,.protected,.normal-badge{
    display:inline-flex;align-items:center;gap:5px;
    padding:6px 10px;border-radius:999px;font-size:10px;font-weight:800
}
.role-badge{color:var(--orange-dark);background:var(--orange-light);border:1px solid var(--orange-soft)}
.type-badge{color:#fff;background:var(--orange);box-shadow:0 4px 11px rgba(255,106,0,.15)}
.normal-badge{color:#75675f;background:#fff;border:1px solid var(--line)}
.date{color:#8d7e75;font-size:11px;white-space:nowrap}
.actions{display:flex;gap:7px;white-space:nowrap}
.actions form{margin:0}
.actions button{font-family:inherit;cursor:pointer}
.action{
    display:inline-flex;align-items:center;justify-content:center;
    padding:7px 10px;border-radius:9px;font-size:10px;font-weight:800;transition:.18s ease
}
.edit{color:#fff;background:var(--orange);border:1px solid var(--orange)}
.edit:hover{background:var(--orange-dark);transform:translateY(-1px)}
.remove{color:var(--orange-dark);background:#fff;border:1px solid var(--orange-soft)}
.remove:hover{background:var(--orange-light);transform:translateY(-1px)}
.protected{color:#999;background:#faf8f6;border:1px solid var(--line)}
.account-state{display:inline-flex;padding:6px 10px;border-radius:999px;font-size:10px;font-weight:800}
.account-state.active{background:#eaf7ef;color:#28623a}
.account-state.inactive{background:#f3eee9;color:#76695f}
.view-only{color:#aaa;font-size:10px;font-weight:700}
.empty{text-align:center;padding:75px 20px}
.empty-icon{
    width:66px;height:66px;margin:0 auto 15px;border-radius:19px;
    display:grid;place-items:center;background:var(--orange-light);
    color:var(--orange);font-size:27px
}
.empty h3{font:800 18px "Plus Jakarta Sans",sans-serif}
.empty p{margin-top:6px;color:var(--muted);font-size:12px}
.footer{
    display:flex;justify-content:space-between;gap:15px;margin-top:19px;
    color:#a08f85;font-size:10px
}
.footer strong{color:var(--orange-dark)}

@media(max-width:900px){
    .hero{grid-template-columns:1fr}
    .add-button{width:100%}
    .stats{grid-template-columns:1fr}
}
@media(max-width:620px){
    .navbar{padding:13px 20px;align-items:flex-start;flex-direction:column;gap:12px}
    .nav-right{width:100%}.nav-button{flex:1}
    .page{padding:30px 20px 55px}
    h1{font-size:34px;letter-spacing:-1.2px}
    .notice{align-items:flex-start;padding:15px}
    .table-top{padding:17px}.table-title p{display:none}
    .footer{flex-direction:column}
}

/* =========================================================
   UNIFLOW ADMIN MANAGEMENT — PREMIUM 3D VISUAL OVERRIDE
   UI ONLY: existing PHP / database / permissions preserved
========================================================= */

:root{
    --uf-o:#f97316;
    --uf-o2:#ff9a55;
    --uf-od:#e85d0f;
    --uf-pale:#fff7ef;
    --uf-soft:#ffe8d5;
    --uf-white:#fff;
    --uf-ink:#2b211c;
    --uf-muted:#8a786d;
    --uf-line:#f2e2d5;
    --uf-shadow:0 22px 60px rgba(231,93,15,.11);
    --uf-shadow2:0 12px 32px rgba(231,93,15,.09);
}

html{scroll-behavior:smooth}

body{
    background:
      radial-gradient(circle at 5% 12%,rgba(249,115,22,.13),transparent 23%),
      radial-gradient(circle at 95% 8%,rgba(255,154,85,.11),transparent 25%),
      radial-gradient(circle at 50% 100%,rgba(249,115,22,.08),transparent 30%),
      linear-gradient(135deg,#fffaf5 0%,#fff 52%,#fff7ef 100%);
    overflow-x:hidden;
}

body::before{
    content:"";
    position:fixed;
    width:340px;height:340px;
    right:-170px;top:180px;
    border:1px solid rgba(249,115,22,.10);
    border-radius:50%;
    box-shadow:
      0 0 0 35px rgba(249,115,22,.025),
      0 0 0 70px rgba(249,115,22,.018);
    pointer-events:none;
    z-index:0;
}

.navbar{
    min-height:78px;
    padding:0 clamp(20px,4vw,64px);
    background:rgba(255,255,255,.82);
    border-bottom:1px solid rgba(249,115,22,.13);
    box-shadow:0 8px 30px rgba(75,38,17,.06);
}

.logo{
    position:relative;
    font-size:27px;
    letter-spacing:-1px;
    text-shadow:0 4px 18px rgba(249,115,22,.15);
    transition:.25s ease;
}
.logo:hover{transform:translateY(-2px) scale(1.02)}

.logo::after{
    content:"";
    position:absolute;
    left:0;bottom:-7px;
    width:24px;height:3px;
    border-radius:99px;
    background:linear-gradient(90deg,var(--uf-o),transparent);
}

.nav-right{gap:10px}

.nav-button{
    border-radius:12px;
    min-height:43px;
    padding:10px 16px;
    box-shadow:0 6px 18px rgba(50,30,15,.04);
}
.back-button:hover{box-shadow:0 9px 22px rgba(249,115,22,.10)}
.logout-button{
    background:linear-gradient(135deg,var(--uf-o),var(--uf-od));
    box-shadow:0 10px 24px rgba(249,115,22,.22);
}
.logout-button:hover{box-shadow:0 14px 30px rgba(249,115,22,.29)}

.page{
    padding:52px clamp(20px,4vw,64px) 78px;
}

.shape{filter:blur(.1px)}
.shape-one{
    width:270px;height:270px;
    right:-105px;top:105px;
    border-radius:65px;
    background:linear-gradient(145deg,#ffad76,#f97316);
    opacity:.095;
    transform:rotate(31deg);
    box-shadow:
      inset -24px -24px 45px rgba(194,65,12,.16),
      18px 25px 55px rgba(249,115,22,.08);
    animation:ufFloat 7s ease-in-out infinite;
}
.shape-two{
    width:205px;height:205px;
    left:-110px;bottom:95px;
    border-width:32px;
    opacity:.075;
    animation:ufFloat2 8s ease-in-out infinite;
}
.shape-three{
    width:105px;height:105px;
    right:20%;bottom:80px;
    opacity:.075;
    box-shadow:12px 18px 35px rgba(249,115,22,.08);
    animation:ufFloat 6s ease-in-out infinite reverse;
}

.content{max-width:1480px}

.header{
    align-items:flex-end;
    margin-bottom:28px;
}

.hero-badge{
    padding:8px 13px;
    border-radius:999px;
    background:linear-gradient(135deg,#fff3e7,#fffaf6);
    border:1px solid #ffd9bd;
    box-shadow:0 8px 20px rgba(249,115,22,.08);
    color:var(--uf-od);
}

.header-left h1{
    font-size:clamp(36px,4.6vw,58px);
    letter-spacing:-2.2px;
    text-shadow:0 5px 25px rgba(43,33,28,.05);
}
.header-left h1 span{
    display:inline-block;
    color:var(--uf-o);
    text-shadow:0 8px 28px rgba(249,115,22,.16);
}
.header-left p{max-width:720px}

.add-button{
    min-height:49px;
    padding:14px 20px;
    border-radius:14px;
    border:0;
    font-family:inherit;
    cursor:pointer;
    background:linear-gradient(135deg,#ff8a3d,#e85d0f);
    box-shadow:
      0 12px 26px rgba(249,115,22,.22),
      inset 0 1px 0 rgba(255,255,255,.25);
}
.add-button:hover{
    transform:translateY(-4px) rotateX(2deg);
    box-shadow:
      0 18px 35px rgba(249,115,22,.28),
      inset 0 1px 0 rgba(255,255,255,.3);
}

.notice{
    position:relative;
    overflow:hidden;
    padding:19px 21px;
    border-radius:18px;
    border:1px solid #f1dfd0;
    border-left:4px solid var(--uf-o);
    background:rgba(255,255,255,.88);
    box-shadow:var(--uf-shadow2);
    backdrop-filter:blur(12px);
}
.notice::after{
    content:"";
    position:absolute;
    width:130px;height:130px;
    right:-55px;top:-70px;
    border-radius:50%;
    background:var(--uf-o);
    opacity:.045;
}
.notice-icon{
    width:43px;height:43px;
    flex-basis:43px;
    border-radius:13px;
    background:linear-gradient(145deg,#fff0e3,#fff9f4);
    box-shadow:
      inset 0 1px 0 #fff,
      0 7px 17px rgba(249,115,22,.08);
}

.summary-grid{
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:18px;
    margin-bottom:28px;
}

.summary-card{
    min-height:148px;
    padding:22px;
    border-radius:20px;
    border:1px solid rgba(242,226,213,.95);
    background:linear-gradient(145deg,rgba(255,255,255,.98),rgba(255,250,246,.95));
    box-shadow:var(--uf-shadow2);
    transform-style:preserve-3d;
}
.summary-card::before{
    content:"";
    position:absolute;
    inset:0;
    background:linear-gradient(120deg,rgba(255,255,255,.6),transparent 48%);
    pointer-events:none;
}
.summary-card:hover{
    transform:translateY(-7px) rotateX(2deg) rotateY(-1deg);
    border-color:#ffcda9;
    box-shadow:0 22px 42px rgba(249,115,22,.13);
}
.summary-icon{
    width:43px;height:43px;
    border-radius:13px;
    background:linear-gradient(145deg,#fff0e3,#fff9f5);
    box-shadow:
      inset 0 1px 0 #fff,
      0 8px 18px rgba(249,115,22,.08);
}
.summary-number{
    font-size:30px;
    color:var(--uf-ink);
    text-shadow:0 3px 15px rgba(249,115,22,.08);
}

.table-card{
    border-radius:23px;
    border:1px solid rgba(241,224,211,.95);
    background:rgba(255,255,255,.91);
    box-shadow:var(--uf-shadow);
    backdrop-filter:blur(15px);
}

.table-header{
    padding:23px 25px;
    background:linear-gradient(135deg,rgba(255,255,255,.98),rgba(255,247,239,.88));
}
.table-header-icon{
    width:45px;height:45px;
    border-radius:14px;
    background:linear-gradient(145deg,#fff0e3,#fff9f5);
    box-shadow:0 9px 20px rgba(249,115,22,.09);
}
.table-header h2{font-size:20px}
.admin-count{
    padding:8px 12px;
    background:#fff3e8;
    border-color:#ffd7bb;
}

thead{
    background:linear-gradient(90deg,#fff4e9,#fffdfa,#fff4e9);
}
th{
    padding:15px 18px;
    font-size:10px;
}
td{
    padding:17px 18px;
    font-size:13px;
}
tbody tr{
    position:relative;
    transition:.22s ease;
}
tbody tr:hover{
    background:linear-gradient(90deg,#fffaf6,#fff5eb,#fffaf6);
    box-shadow:inset 4px 0 0 var(--uf-o);
}
.avatar{
    width:42px;height:42px;
    flex-basis:42px;
    border-radius:13px;
    background:linear-gradient(145deg,#ff9a55,#e85d0f);
    box-shadow:
      0 8px 18px rgba(249,115,22,.20),
      inset 0 1px 0 rgba(255,255,255,.28);
    transform:translateZ(10px);
}
.admin-name{font-size:13.5px}

.badge{
    padding:7px 11px;
    background:linear-gradient(135deg,#fff0e3,#fff8f3);
    border-color:#ffd8bd;
    box-shadow:0 4px 12px rgba(249,115,22,.05);
}
.main-badge{
    padding:7px 11px;
    background:linear-gradient(135deg,#ff8b40,#e85d0f);
    box-shadow:0 7px 15px rgba(249,115,22,.18);
}
.admin-badge{
    padding:7px 11px;
    background:#fff;
    border-color:#eadfd6;
}
.action-button{
    min-height:34px;
    border-radius:10px;
    padding:8px 12px;
}
.edit-button{
    background:linear-gradient(135deg,#ff8b40,#e85d0f);
    box-shadow:0 6px 14px rgba(249,115,22,.14);
}
.edit-button:hover,.remove-button:hover{
    transform:translateY(-2px);
    box-shadow:0 9px 18px rgba(249,115,22,.13);
}
.remove-button{
    background:#fff;
    box-shadow:0 4px 10px rgba(50,30,15,.04);
}
.action-disabled{
    border-radius:10px;
    background:linear-gradient(135deg,#faf7f4,#fff);
}
.footer-info{
    margin-top:23px;
    padding:0 3px;
}

@keyframes ufFloat{
    0%,100%{transform:rotate(31deg) translate3d(0,0,0)}
    50%{transform:rotate(35deg) translate3d(0,-13px,0)}
}
@keyframes ufFloat2{
    0%,100%{transform:translate3d(0,0,0)}
    50%{transform:translate3d(8px,-12px,0)}
}

@media (max-width:950px){
    .summary-grid{grid-template-columns:1fr}
    .summary-card{min-height:130px}
}
@media (max-width:650px){
    .page{padding:32px 18px 55px}
    .header-left h1{font-size:35px;letter-spacing:-1.3px}
    .navbar{padding:14px 18px}
    .table-card{border-radius:18px}
    .table-header{padding:18px}
}

.admin-form-card{
    margin-bottom:24px;
    overflow:hidden;
    border:1px solid rgba(241,224,211,.95);
    border-radius:23px;
    background:rgba(255,255,255,.94);
    box-shadow:var(--uf-shadow);
    backdrop-filter:blur(15px);
}
.admin-form-card[hidden]{display:none}
.admin-form-field[hidden]{display:none}
.admin-form-header{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:16px;
    padding:20px 24px;
    border-bottom:1px solid var(--uf-line);
    background:linear-gradient(135deg,#fff,rgba(255,247,239,.9));
}
.admin-form-header h2{font:800 19px "Plus Jakarta Sans",sans-serif}
.admin-form-header p{margin-top:4px;color:var(--uf-muted);font-size:12px}
.admin-form-body{padding:24px}
.admin-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}
.admin-form-field{min-width:0;display:flex;flex-direction:column;gap:7px}
.admin-form-field.full{grid-column:1/-1}
.admin-form-field>label{color:var(--uf-ink);font-size:12px;font-weight:800}
.admin-form-field input[type="text"],
.admin-form-field input[type="email"],
.admin-form-field input[type="password"]{
    width:100%;height:45px;padding:0 13px;
    border:1px solid var(--uf-line);border-radius:11px;
    background:#fff;color:var(--uf-ink);font:500 13px Inter,Arial,sans-serif;
    outline:none;transition:.18s ease;
}
.admin-form-field input:focus{border-color:#ff9a55;box-shadow:0 0 0 3px rgba(249,115,22,.10)}
.admin-form-help{color:var(--uf-muted);font-size:11px;line-height:1.5}
.admin-password-modes{display:inline-flex;flex-wrap:wrap;gap:3px;width:max-content;max-width:100%;padding:4px;border:1px solid var(--uf-line);border-radius:11px;background:#fff8f2}
.admin-password-mode{display:flex;align-items:center;gap:7px;min-height:34px;padding:6px 10px;border:1px solid transparent;border-radius:8px;cursor:pointer;font-size:12px;font-weight:800}
.admin-password-mode:has(input:checked){border-color:#f2c6a8;background:#fff;box-shadow:0 2px 7px rgba(89,43,14,.07)}
.admin-password-mode input{width:14px;height:14px;margin:0;accent-color:var(--uf-o)}
.admin-access-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}
.admin-access-option{display:flex;align-items:center;gap:9px;padding:11px 12px;border:1px solid var(--uf-line);border-radius:10px;background:#fff;color:var(--uf-ink);font-size:12px;font-weight:700;cursor:pointer}
.admin-access-option input{width:15px;height:15px;margin:0;accent-color:var(--uf-o)}
.admin-form-actions{display:flex;justify-content:flex-end;gap:9px;margin-top:22px;padding-top:18px;border-top:1px solid var(--uf-line)}
.admin-form-close,.admin-form-submit{min-height:40px;padding:9px 15px;border-radius:10px;font:800 12px Inter,Arial,sans-serif;cursor:pointer}
.admin-form-close{border:1px solid var(--uf-line);background:#fff;color:var(--uf-ink)}
.admin-form-submit{border:0;background:linear-gradient(135deg,#ff8a3d,#e85d0f);color:#fff;box-shadow:0 7px 16px rgba(249,115,22,.18)}
.admin-feedback{margin:16px 24px 0;padding:12px 14px;border-radius:10px;font-size:12px;font-weight:700}
.admin-feedback.error{border:1px solid #ffc9b9;background:#fff4f0;color:#a63c1f}
.admin-feedback.success{border:1px solid #b9e5c8;background:#f1fbf4;color:#256b3b}
@media(max-width:650px){
    .admin-form-grid{grid-template-columns:1fr}
    .admin-form-field.full{grid-column:auto}
    .admin-form-header,.admin-form-body{padding:17px}
    .admin-access-grid{grid-template-columns:1fr}
    .admin-form-actions{flex-direction:column-reverse}
    .admin-form-close,.admin-form-submit{width:100%}
}

body{background:#fffaf5;}
body::before{
    width:auto;height:auto;inset:0;right:auto;top:0;
    border:0;border-radius:0;box-shadow:none;
    background-image:radial-gradient(rgba(255,106,0,.10) 1px,transparent 1px);
    background-size:24px 24px;
    mask-image:linear-gradient(to bottom,rgba(0,0,0,.35),transparent 72%);
}
.navbar{
    min-height:76px;
    padding:0 4%;
    background:rgba(255,255,255,.94);
    box-shadow:0 8px 25px rgba(80,40,15,.06);
}
.logo{gap:10px;font-size:27px;letter-spacing:0;text-shadow:none;color:#1d120d}.logo::before{content:"";display:inline-block;width:36px;height:36px;border-radius:11px;background:#f97316;box-shadow:0 5px 0 #c2410c,0 10px 20px rgba(249,115,22,.16)}
.page{padding:42px 4% 60px}
.header{align-items:flex-end;margin-bottom:23px}
.header-left h1{font-size:clamp(35px,4.5vw,56px);letter-spacing:-1.5px;text-shadow:none}
.header-left h1 span{text-shadow:none}
.notice{
    padding:15px 18px;
    border-radius:14px;
    background:rgba(255,255,255,.96);
    box-shadow:0 7px 20px rgba(80,40,15,.05);
    backdrop-filter:none;
}
.notice::after{opacity:.025}
.summary-grid{gap:16px;margin-bottom:23px}
.summary-card{
    min-height:92px;
    padding:17px 19px;
    border-radius:16px;
    background:rgba(255,255,255,.98);
    box-shadow:0 7px 20px rgba(80,40,15,.05);
    transform:none;
}
.summary-card::before,.summary-icon{display:none}
.summary-card:hover{
    transform:translateY(-2px);
    border-color:var(--uf-soft);
    box-shadow:0 10px 23px rgba(249,115,22,.08);
}
.summary-number{font-size:24px;text-shadow:none}
.table-card,.admin-form-card{
    border-radius:18px;
    background:rgba(255,255,255,.97);
    box-shadow:0 10px 28px rgba(80,40,15,.06);
    backdrop-filter:none;
}
.table-header,.admin-form-header{padding:19px 22px}
.table-header{background:#fff}
thead{background:#fff7f1}
th{padding:13px 16px}
td{padding:14px 16px}
tbody tr:hover{background:#fffaf6;box-shadow:none}
.avatar{width:36px;height:36px;flex-basis:36px;border-radius:11px;transform:none}
.admin-form-body{padding:22px}
.admin-form-submit{background:linear-gradient(135deg,#ff8a3d,#e85d0f)}
@media(max-width:650px){
    .page{padding:32px 18px 55px}
    .header-left h1{font-size:35px;letter-spacing:-1.2px}
    .navbar{padding:12px 18px}
}

/* Match the Student Management page background and UniFlow header. */
body{
    background:
      radial-gradient(circle at 8% 12%,rgba(255,138,61,.15),transparent 24%),
      radial-gradient(circle at 92% 18%,rgba(255,106,0,.11),transparent 26%),
      linear-gradient(135deg,#fff8f2 0%,#fff 48%,#fff6ed 100%);
}
body::before{
    content:"";
    position:fixed;
    inset:0;
    width:auto;height:auto;right:auto;top:0;
    border:0;border-radius:0;box-shadow:none;
    background-image:radial-gradient(rgba(255,106,0,.10) 1px,transparent 1px);
    background-size:24px 24px;
    mask-image:linear-gradient(to bottom,rgba(0,0,0,.35),transparent 72%);
}
.logo{
    display:flex;align-items:center;gap:11px;
    font:800 22px "Plus Jakarta Sans",sans-serif;
    letter-spacing:0;text-shadow:none;color:var(--ink);
}
.logo::before,.logo::after{content:none;display:none}
.logo:hover{transform:none}
.logo .logo-icon{
    width:45px;height:45px;flex:0 0 45px;
    display:flex;align-items:center;justify-content:center;
    border-radius:14px;color:#fff;
    background:linear-gradient(145deg,#f97316,#ea580c);
    box-shadow:0 6px 0 #c2410c,0 12px 25px rgba(249,115,22,.22);
    font-weight:800;transform:rotate(-3deg);
}
.logo-word{color:var(--ink)!important}
.orb,.cube,.ring{position:fixed;pointer-events:none;z-index:0}
.orb{
    border-radius:50%;
    background:radial-gradient(circle at 30% 25%,#fff 0 8%,#ffb36e 18%,#ff7a1a 54%,#e65300 100%);
    box-shadow:inset -18px -22px 35px rgba(143,48,0,.20),0 35px 70px rgba(255,106,0,.15);
}
.orb.one{width:150px;height:150px;right:-55px;top:125px;opacity:.55;animation:float1 7s ease-in-out infinite}
.orb.two{width:82px;height:82px;left:3%;bottom:12%;opacity:.35;animation:float2 6s ease-in-out infinite}
.ring{width:180px;height:180px;right:9%;bottom:7%;border:20px solid rgba(255,106,0,.12);border-radius:50%;transform:rotate(-20deg) perspective(400px) rotateY(45deg);box-shadow:0 20px 50px rgba(255,106,0,.08)}
.cube{width:74px;height:74px;left:8%;top:135px;border-radius:18px;background:linear-gradient(145deg,#ff9b52,#f45c00);transform:rotate(22deg) skewY(-5deg);box-shadow:15px 18px 0 rgba(197,69,0,.12),0 25px 55px rgba(255,106,0,.18);animation:float2 8s ease-in-out infinite}
@keyframes float1{50%{transform:translateY(-18px) rotate(8deg)}}
@keyframes float2{50%{transform:translateY(14px) rotate(7deg)}}
@media(max-width:650px){.logo{font-size:22px}.logo .logo-icon{width:45px;height:45px;flex-basis:45px}}

</style>


    <link rel="stylesheet" href="../css/visual-3d.css">
<link rel="stylesheet" href="../css/buttons.css">
</head>

<body>
<div class="orb one" aria-hidden="true"></div>
<div class="orb two" aria-hidden="true"></div>
<div class="cube" aria-hidden="true"></div>
<div class="ring" aria-hidden="true"></div>
<nav class="navbar">
    <a class="logo" href="<?= e($dashboard_link) ?>"><span class="logo-icon" aria-hidden="true">U</span><span class="logo-word">UniFlow</span></a>
    <div class="nav-right">
        <a class="nav-button back-button" href="<?= e($dashboard_link) ?>">Dashboard</a>
        <a class="nav-button logout-button" href="../index.php">Logout</a>
    </div>
</nav>

<main class="page">
    <div class="container">
        <section class="hero">
            <div class="hero-copy">
                <div class="badge"><?= $is_main_admin ? 'System Admin Control Center' : 'Administrative Account Manager' ?></div>
                <h1>Admin <span>Management</span></h1>
                <p>
                    <?= $is_main_admin
                        ? 'Appoint Administrative account managers, provision UniFlow staff accounts and assign portal permissions after the responsible university office selects the staff member.'
                        : 'Manage portal administrator accounts assigned to your office. System Admin controls who can manage administrator accounts.' ?>
                </p>
            </div>

            <?php if ($can_manage_accounts): ?>
                <button class="add-button" id="add-admin-toggle" type="button" aria-controls="admin-form" aria-expanded="<?= $showAddAdminForm ? 'true' : 'false' ?>">
                    <?= $showAddAdminForm ? 'Hide Form' : 'Create Portal Admin' ?>
                </button>
            <?php endif; ?>
        </section>

        <div class="notice">
            <div class="notice-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 18h20"/><path d="M3 7l4.5 4.5L12 5l4.5 6.5L21 7l-1.5 8h-15L3 7z"/></svg></div>
            <div>
                <strong><?= $is_main_admin ? "System Account Controls" : "Delegated Admin Account Controls" ?></strong>
                <p>
                    <?php if ($is_main_admin): ?>
                        System Admin appoints Administrative account managers and controls system settings. Appointed managers can manage regular portal administrator accounts. System Admin accounts remain protected.
                    <?php else: ?>
                        You can add and edit regular portal administrator accounts and deactivate or reactivate their access. System Admin and appointed account managers remain protected; only System Admin can appoint another manager.
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <?php if ($adminCreated): ?>
            <div class="admin-feedback success" role="status">Portal administrator account created and login details were sent to the Personal Email.</div>
        <?php endif; ?>

        <?php if (isset($_GET['updated'])): ?>
            <div class="admin-feedback success" role="status">Administrator account updated.</div>
        <?php endif; ?>

        <?php if (isset($_GET['status_updated'])): ?>
            <div class="admin-feedback success" role="status">Staff account status updated.</div>
        <?php endif; ?>

        <?php if (isset($_GET['status_unchanged'])): ?>
            <div class="admin-feedback error" role="alert">Account status was unchanged. Check that the selected account is within your management access.</div>
        <?php endif; ?>

        <section class="stats">
            <div class="stat">
                <div class="stat-number"><?= count($admins) ?></div>
                <div class="stat-label">Staff Accounts</div>
            </div>
            <div class="stat">
                <div class="stat-number"><?= $is_main_admin ? $main_count : $manager_count ?></div>
                <div class="stat-label"><?= $is_main_admin ? 'System Admin Accounts' : 'Account Managers' ?></div>
            </div>
            <div class="stat">
                <div class="stat-number"><?= $is_main_admin ? $manager_count : $normal_count ?></div>
                <div class="stat-label"><?= $is_main_admin ? 'Appointed Account Managers' : 'Regular Portal Admins' ?></div>
            </div>
        </section>

        <?php if ($can_manage_accounts): ?>
        <section class="admin-form-card" id="admin-form" <?= $showAddAdminForm ? '' : 'hidden' ?>>
            <div class="admin-form-header">
                <div>
                    <h2>Create Portal Admin Account</h2>
                    <p>Create an administrator login and assign only the portal access needed for the staff member's duties.</p>
                </div>
            </div>
            <?php if ($adminAddError !== ''): ?>
                <div class="admin-feedback error" role="alert"><?= e($adminAddError) ?></div>
            <?php endif; ?>
            <form class="admin-form-body" method="post" action="add_admin.php">
                <input type="hidden" name="return_to" value="admin_management">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['admin_management_csrf']) ?>">
                <div class="admin-form-grid">
                    <div class="admin-form-field">
                        <label for="new-admin-name">Staff Full Name</label>
                        <input id="new-admin-name" type="text" name="name" value="<?= e($adminAddValues['name'] ?? '') ?>" placeholder="Enter staff member's full name" required>
                        <span class="admin-form-help">Use the staff member's official name.</span>
                    </div>
                    <div class="admin-form-field">
                        <label for="new-admin-university-email">Staff University Email (Login Email)</label>
                        <input id="new-admin-university-email" type="email" name="university_email" value="<?= e($adminAddValues['university_email'] ?? '') ?>" placeholder="admin@university.edu" required>
                    </div>
                    <div class="admin-form-field full">
                        <label for="new-admin-personal-email">Personal Email (Login Details Delivery)</label>
                        <input id="new-admin-personal-email" type="email" name="personal_email" value="<?= e($adminAddValues['personal_email'] ?? '') ?>" placeholder="admin@gmail.com" required>
                        <span class="admin-form-help">The account login details will be sent here. It is not used to sign in.</span>
                    </div>
                    <div class="admin-form-field full">
                        <label>Initial Password</label>
                        <div class="admin-password-modes" role="radiogroup" aria-label="Initial password setup">
                            <label class="admin-password-mode"><input type="radio" name="password_mode" value="automated" <?= ($adminAddValues['password_mode'] ?? 'automated') !== 'manual' ? 'checked' : '' ?>> Automated</label>
                            <label class="admin-password-mode"><input type="radio" name="password_mode" value="manual" <?= ($adminAddValues['password_mode'] ?? '') === 'manual' ? 'checked' : '' ?>> Manual</label>
                        </div>
                        <span class="admin-form-help">The email includes this initial password and an optional, one-time link to change it. The recipient can keep the original password.</span>
                    </div>
                    <div class="admin-form-field full" id="admin-manual-password-field" <?= ($adminAddValues['password_mode'] ?? '') === 'manual' ? '' : 'hidden' ?>>
                        <label for="new-admin-manual-password">Manual Password</label>
                        <input id="new-admin-manual-password" type="password" name="manual_password" minlength="8" autocomplete="new-password" placeholder="At least 8 characters" <?= ($adminAddValues['password_mode'] ?? '') === 'manual' ? 'required' : '' ?>>
                    </div>
                    <div class="admin-form-field full">
                            <label>UniFlow Portal Permissions</label>
                            <span class="admin-form-help">Grant only the system access needed for the staff member's university-assigned duties.</span>
                        <div class="admin-access-grid">
                            <?php foreach ([
                                'technical' => 'Technical Portal',
                                'administrative' => 'Administrative Portal',
                                'proctorial' => 'Proctorial Portal',
                                'lost_found' => 'Lost & Found Moderator Permission'
                            ] as $accessValue => $accessLabel): ?>
                                <label class="admin-access-option">
                                    <input type="checkbox" name="access[]" value="<?= e($accessValue) ?>" <?= in_array($accessValue, $adminAddValues['access'] ?? [], true) ? 'checked' : '' ?>>
                                    <?= e($accessLabel) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="admin-form-help" style="margin-top:10px">For Lost &amp; Found moderation, University Administration, Student Affairs or Proctorial must first select an authorized staff member. System Admin assigns this permission after that selection.</div>
                        <label class="admin-form-help" style="display:flex;align-items:flex-start;gap:8px;margin-top:10px;color:var(--uf-ink)">
                            <input type="checkbox" name="lost_found_authorized" value="1" <?= !empty($adminAddValues['lost_found_authorized']) ? 'checked' : '' ?> style="margin-top:2px">
                            I confirm the relevant university authority has selected this person as an authorized Lost &amp; Found moderator.
                        </label>
                    </div>
                    <?php if ($is_main_admin): ?>
                    <div class="admin-form-field full">
                        <label>Administrative Account Manager</label>
                        <label class="admin-access-option">
                            <input type="checkbox" name="can_manage_admins" value="1" <?= !empty($adminAddValues['can_manage_admins']) ? 'checked' : '' ?>> Appoint this staff member to manage portal administrator accounts
                        </label>
                        <span class="admin-form-help">This appointment requires Administrative Portal access. Account Managers can add, edit and deactivate regular admins, but cannot appoint another manager or manage System Admins.</span>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="admin-form-actions">
                    <button class="admin-form-close" type="button" id="close-admin-form">Cancel</button>
                    <button class="admin-form-submit" type="submit">Create Staff Account &amp; Send Login Details</button>
                </div>
            </form>
        </section>
        <?php endif; ?>

        <section class="table-card">
            <div class="table-top">
                <div class="table-title">
                    <div class="table-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg></div>
                    <div>
                        <h2>UniFlow Staff Accounts</h2>
        <p>View account status and assigned portal permissions</p>
                    </div>
                </div>
                <span class="count-pill"><?= count($admins) ?> account(s)</span>
            </div>

            <?php if (count($admins) > 0): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Staff Account</th>
                            <th>Primary Portal</th>
                            <th>Account Type</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($admins as $index => $admin): ?>
                        <tr>
                            <td class="row-number"><?= $index + 1 ?></td>

                            <td>
                                <div class="profile">
                                    <div class="avatar"><?= e(initials($admin["name"])) ?></div>
                                    <div>
                                        <div class="name"><?= e($admin["name"]) ?></div>
                                        <div class="email"><?= e($admin["email"]) ?></div>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <span class="role-badge">
                                    <?= $admin["admin_type"] === "main_admin" ? "System Admin" : e(match ($admin["role"]) { "lost_found" => "Lost & Found Moderator", "technical" => "Technical", "administrative" => "Administrative", "proctorial" => "Proctorial", default => $admin["role"] }) ?>
                                </span>
                            </td>

                            <td>
                                    <?php if ($admin["admin_type"] === "main_admin"): ?>
                                        <span class="type-badge">Main Admin</span>
                                    <?php elseif ((int)$admin["can_manage_admins"] === 1): ?>
                                        <span class="type-badge">Admin Manager</span>
                                    <?php else: ?>
                                        <span class="normal-badge">Admin</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <span class="account-state <?= (int)$admin["is_active"] === 1 ? 'active' : 'inactive' ?>">
                                    <?= (int)$admin["is_active"] === 1 ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>

                            <td class="date">
                                <?= e(date("d M Y", strtotime($admin["created_at"]))) ?>
                            </td>

                            <td>
                                <?php if (adminCanManageTarget($current_admin, $admin)): ?>
                                    <div class="actions">
                                        <a class="action edit" href="edit_admin.php?id=<?= (int)$admin["admin_id"] ?>">Edit</a>
                                        <form method="POST" action="admin_status.php" onsubmit="return confirm('<?= (int)$admin["is_active"] === 1 ? 'Deactivate' : 'Activate' ?> this administrator?')">
                                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['admin_status_csrf']) ?>">
                                            <input type="hidden" name="admin_id" value="<?= (int)$admin["admin_id"] ?>">
                                            <input type="hidden" name="is_active" value="<?= (int)$admin["is_active"] === 1 ? 0 : 1 ?>">
                                            <button class="action <?= (int)$admin["is_active"] === 1 ? 'remove' : 'edit' ?>" type="submit">
                                                <?= (int)$admin["is_active"] === 1 ? 'Deactivate' : 'Activate' ?>
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span class="protected"><?= $admin["admin_type"] === "main_admin" || (int)$admin["can_manage_admins"] === 1 ? 'Protected' : 'Your account' ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="empty">
                    <div class="empty-icon">+</div>
        <h3>No portal administrator accounts found</h3>
        <p>Create an account after the responsible university office selects the staff member.</p>
                </div>
            <?php endif; ?>
        </section>

        <section class="table-card" style="margin-top:22px">
            <div class="table-top">
                <div class="table-title">
                    <div class="table-icon">↻</div>
                    <div>
                        <h2>Recent Account Activity</h2>
                        <p>Latest account creation, edits and access status changes</p>
                    </div>
                </div>
                <span class="count-pill">Last <?= count($accountAudit) ?> change(s)</span>
            </div>
            <?php if ($accountAudit): ?>
            <div class="table-wrap">
                <table style="min-width:760px">
                    <thead><tr><th>Action</th><th>Account</th><th>Changed By</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($accountAudit as $audit): ?>
                        <?php
                            $auditName = $audit['details']['name'] ?? $audit['target_name'] ?? 'Account';
                            $auditEmail = $audit['details']['email'] ?? '';
                            $auditActor = $audit['actor_name'] ?? 'Former account';
                            $auditAction = ucfirst(str_replace('_', ' ', $audit['action']));
                        ?>
                        <tr>
                            <td><span class="role-badge"><?= e($auditAction) ?></span></td>
                            <td>
                                <div class="name"><?= e($auditName) ?></div>
                                <?php if ($auditEmail !== ''): ?><div class="email"><?= e($auditEmail) ?></div><?php endif; ?>
                            </td>
                            <td><?= e($auditActor) ?></td>
                            <td class="date"><?= e(date('d M Y H:i', strtotime($audit['created_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="empty"><h3>No account activity yet</h3><p>Account changes will be recorded here.</p></div>
            <?php endif; ?>
        </section>

        <div class="footer">
            <span>UniFlow Administration System</span>
            <span>Logged in as: <strong><?= e($current_admin["name"]) ?></strong></span>
        </div>
    </div>
</main>
<script>
const addAdminToggle = document.getElementById('add-admin-toggle');
const addAdminForm = document.getElementById('admin-form');
const closeAdminForm = document.getElementById('close-admin-form');
const manualAdminPasswordField = document.getElementById('admin-manual-password-field');
const manualAdminPasswordInput = document.getElementById('new-admin-manual-password');

if (addAdminToggle && addAdminForm) {
    function setAdminFormVisible(visible) {
        addAdminForm.hidden = !visible;
        addAdminToggle.setAttribute('aria-expanded', String(visible));
        addAdminToggle.textContent = visible ? 'Hide Form' : 'Create Portal Admin';
        if (visible) addAdminForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    addAdminToggle.addEventListener('click', () => {
        setAdminFormVisible(addAdminForm.hidden);
    });
    closeAdminForm.addEventListener('click', () => setAdminFormVisible(false));

    function updateAdminPasswordMode() {
        const manualSelected = document.querySelector('input[name="password_mode"]:checked')?.value === 'manual';
        manualAdminPasswordField.hidden = !manualSelected;
        manualAdminPasswordInput.required = manualSelected;
        if (!manualSelected) manualAdminPasswordInput.value = '';
    }

    document.querySelectorAll('input[name="password_mode"]').forEach((input) => {
        input.addEventListener('change', updateAdminPasswordMode);
    });
    updateAdminPasswordMode();
}
</script>
</body>
</html>
