<?php

require_once "database.php";
require_once "auth.php";

/* =========================
   LOGOUT
========================= */
if (isset($_GET["logout"])) {
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            "",
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();

    header("Location: ../index.php");
    exit;
}

admin_required($admin_role);


/* =========================
   SETTINGS
========================= */

$is_proctorial = ($admin_role === "proctorial");


/* =========================
   PORTAL TITLE
========================= */

if ($admin_role === "technical") {

    $portal_title = "Technical";

} elseif ($admin_role === "administrative") {

    $portal_title = "Administrative";

} elseif ($admin_role === "proctorial") {

    $portal_title = "Proctorial";

} else {

    $portal_title = "Admin";

}


/* =========================
   DEPARTMENT CONTACT
========================= */

$administrative_phone = "01745053047";
$technical_phone = "01778510080";


/* =========================
   PORTAL FILTER
========================= */

$selected_portal = $_GET["portal"] ?? "all";


/*
   PROCTORIAL ADMIN
   ----------------
   Proctorial dashboard is ONLY
   for Proctorial reports.

   Technical, Administrative,
   All Reports and Lost & Found
   are not available here.
*/

if ($is_proctorial) {

    $selected_portal = "proctorial";

} else {

    /*
       Technical / Administrative
       dashboard behavior remains
       unchanged.
    */

    $allowed_portals = [
        "all",
        "technical",
        "administrative",
        "proctorial"
    ];

    if (!in_array($selected_portal, $allowed_portals, true)) {

        $selected_portal = "all";

    }


    /*
       Technical / Administrative
       admins can only see their own
       portal or Lost & Found.
    */

    if ($selected_portal !== "all" && !admin_has_access($selected_portal)) {

        $selected_portal = $admin_role;

    }

}

$report_category = $selected_portal === "all"
    ? $admin_role
    : $selected_portal;

$available_report_portals = array_values(array_unique(array_merge(
    [$admin_role],
    getAdminAccessAreas($conn, (int)$_SESSION["admin_id"])
)));
$available_report_portals = array_values(array_filter(
    $available_report_portals,
    static fn($portal) => $portal !== "lost_found"
));
$portal_labels = [
    "technical" => "Technical",
    "administrative" => "Administrative",
    "proctorial" => "Proctorial"
];
$active_portal_title = $portal_labels[$report_category] ?? $portal_title;


/* =========================
   STATUS FILTER
========================= */

$selected_status = $_GET["status"] ?? "all";

$allowed_statuses = [
    "all",
    "Pending",
    "In Progress",
    "Resolved"
];

if (!in_array($selected_status, $allowed_statuses, true)) {

    $selected_status = "all";

}


/* =========================
   STATISTICS
========================= */

$total_reports = 0;
$pending_reports = 0;
$resolved_reports = 0;
$in_progress_reports = 0;


/* =========================
   TOTAL REPORTS
========================= */

if ($is_proctorial) {

    /*
       ONLY PROCTORIAL REPORTS
    */

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM reports
        WHERE category = 'proctorial'
    ");

    $stmt->execute();

} else {

    /*
       Existing Technical /
       Administrative behavior.
    */

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM reports
        WHERE category = ?
    ");

    $stmt->bind_param(
        "s",
        $report_category
    );

    $stmt->execute();

}


$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {

    $total_reports = (int)$row["total"];

}

$stmt->close();


/* =========================
   PENDING REPORTS
========================= */

if ($is_proctorial) {

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM reports
        WHERE category = 'proctorial'
        AND status = 'Pending'
    ");

    $stmt->execute();

} else {

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM reports
        WHERE category = ?
        AND status = 'Pending'
    ");

    $stmt->bind_param(
        "s",
        $report_category
    );

    $stmt->execute();

}


$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {

    $pending_reports = (int)$row["total"];

}

$stmt->close();


/* =========================
   RESOLVED REPORTS
========================= */

if ($is_proctorial) {

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM reports
        WHERE category = 'proctorial'
        AND status = 'Resolved'
    ");

    $stmt->execute();

} else {

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM reports
        WHERE category = ?
        AND status = 'Resolved'
    ");

    $stmt->bind_param(
        "s",
        $report_category
    );

    $stmt->execute();

}


$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {

    $resolved_reports = (int)$row["total"];

}

$stmt->close();


/* =========================
   IN PROGRESS REPORTS
========================= */

if ($is_proctorial) {

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM reports
        WHERE category = 'proctorial'
        AND status = 'In Progress'
    ");

    $stmt->execute();

} else {

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM reports
        WHERE category = ?
        AND status = 'In Progress'
    ");

    $stmt->bind_param(
        "s",
        $report_category
    );

    $stmt->execute();

}


$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {

    $in_progress_reports = (int)$row["total"];

}

$stmt->close();


/* =========================
   REPORTS
========================= */

$reports = [];


/*
   PROCTORIAL
   ----------
   Proctorial dashboard ONLY
   fetches Proctorial reports.
*/

if ($is_proctorial) {

    if ($selected_status !== "all") {

        $stmt = $conn->prepare("
            SELECT
                r.*,
                s.name AS student_name,
                s.student_code,
                s.email AS student_email

            FROM reports r

            INNER JOIN students s
                ON r.student_id = s.student_id

            WHERE r.category = 'proctorial'
            AND r.status = ?

            ORDER BY r.created_at DESC
        ");

        $stmt->bind_param(
            "s",
            $selected_status
        );

    } else {

        $stmt = $conn->prepare("
            SELECT
                r.*,
                s.name AS student_name,
                s.student_code,
                s.email AS student_email

            FROM reports r

            INNER JOIN students s
                ON r.student_id = s.student_id

            WHERE r.category = 'proctorial'

            ORDER BY r.created_at DESC
        ");

    }

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $reports[] = $row;

    }

    $stmt->close();


} else {

    /*
       TECHNICAL / ADMINISTRATIVE
       --------------------------
       Existing behavior remains
       unchanged.
    */

    if ($selected_status !== "all") {

        $stmt = $conn->prepare("
            SELECT
                r.*,
                s.name AS student_name,
                s.student_code,
                s.email AS student_email

            FROM reports r

            INNER JOIN students s
                ON r.student_id = s.student_id

            WHERE r.category = ?
            AND r.status = ?

            ORDER BY r.created_at DESC
        ");

        $stmt->bind_param(
            "ss",
            $report_category,
            $selected_status
        );

    } else {

        $stmt = $conn->prepare("
            SELECT
                r.*,
                s.name AS student_name,
                s.student_code,
                s.email AS student_email

            FROM reports r

            INNER JOIN students s
                ON r.student_id = s.student_id

            WHERE r.category = ?

            ORDER BY r.created_at DESC
        ");

        $stmt->bind_param(
            "s",
            $report_category
        );

    }

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $reports[] = $row;

    }

    $stmt->close();

}

?>


<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
<?= e($active_portal_title) ?> Dashboard | UniFlow
</title>


<link
    rel="preconnect"
    href="https://fonts.googleapis.com"
>

<link
    rel="preconnect"
    href="https://fonts.gstatic.com"
    crossorigin
>

<link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap"
    rel="stylesheet"
>


<style>

:root {

    --orange: #f97316;
    --orange-dark: #ea580c;
    --orange-light: #fff7ed;
    --orange-soft: #ffedd5;

    --white: #ffffff;
    --cream: #fffaf5;

    --text: #24160f;
    --muted: #7c7068;

    --border: #f1dfd0;

    --shadow:
        0 15px 45px rgba(77, 39, 15, 0.08);

    --shadow-hover:
        0 22px 55px rgba(77, 39, 15, 0.13);

}


* {

    margin: 0;
    padding: 0;

    box-sizing: border-box;

}


html {

    scroll-behavior: smooth;

}


body {

    font-family:
        "Inter",
        Arial,
        sans-serif;

    background:
        linear-gradient(
            180deg,
            #fffaf5 0%,
            #fff7f0 100%
        );

    color:
        var(--text);

    min-height:
        100vh;

}


a {

    text-decoration:
        none;

    color:
        inherit;

}


/* =========================================================
   NAVBAR
========================================================= */

.navbar {

    position:
        sticky;

    top:
        0;

    z-index:
        1000;

    width:
        100%;

    padding:
        14px 5%;

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    gap:
        25px;

    background:
        rgba(255,255,255,0.92);

    backdrop-filter:
        blur(18px);

    border-bottom:
        1px solid var(--border);

    box-shadow:
        0 8px 30px rgba(50,25,10,0.06);

}


.logo {

    display:
        flex;

    align-items:
        center;

    gap:
        11px;

    font-family:
        "Plus Jakarta Sans",
        sans-serif;

    font-size:
        22px;

    font-weight:
        800;

    white-space:
        nowrap;

}


.logo-icon {

    width:
        45px;

    height:
        45px;

    border-radius:
        14px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    color:
        var(--white);

    background:
        linear-gradient(
            145deg,
            var(--orange),
            var(--orange-dark)
        );

    box-shadow:
        0 6px 0 #c2410c,
        0 12px 25px rgba(249,115,22,0.25);

    font-weight:
        800;

    transform:
        rotate(-3deg);

}


.nav-right {

    display:
        flex;

    align-items:
        center;

    justify-content:
        flex-end;

    gap:
        9px;

    flex-wrap:
        wrap;

}


.welcome-text {

    padding:
        9px 14px;

    border-radius:
        12px;

    background:
        var(--orange-light);

    border:
        1px solid var(--border);

    color:
        var(--muted);

    font-size:
        13px;

}


.welcome-text strong {

    color:
        var(--text);

}


.nav-button {

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    padding:
        10px 15px;

    border-radius:
        11px;

    background:
        var(--white);

    border:
        1px solid var(--border);

    color:
        var(--text);

    font-size:
        13px;

    font-weight:
        700;

    transition:
        0.2s ease;

}


.nav-button:hover {

    color:
        var(--orange-dark);

    border-color:
        var(--orange);

    transform:
        translateY(-2px);

    box-shadow:
        0 8px 18px rgba(249,115,22,0.10);

}


.nav-button.primary {

    color:
        var(--white);

    background:
        var(--orange);

    border-color:
        var(--orange);

    box-shadow:
        0 4px 0 var(--orange-dark);

}


.nav-button.primary:hover {

    color:
        var(--white);

    background:
        var(--orange-dark);

    border-color:
        var(--orange-dark);

}


/* =========================================================
   MAIN
========================================================= */

.container {

    width:
        92%;

    max-width:
        1500px;

    margin:
        0 auto;

    padding:
        35px 0 70px;

}


/* =========================================================
   HERO
========================================================= */

.hero {

    position:
        relative;

    overflow:
        hidden;

    min-height:
        300px;

    display:
        flex;

    align-items:
        center;

    margin-bottom:
        28px;

    padding:
        48px;

    border:
        1px solid var(--border);

    border-radius:
        30px;

    background:
        linear-gradient(
            135deg,
            #ffffff 0%,
            #fffaf5 100%
        );

    box-shadow:
        var(--shadow);

}


.hero::before {

    content:
        "";

    position:
        absolute;

    width:
        420px;

    height:
        420px;

    right:
        -150px;

    top:
        -170px;

    border-radius:
        50%;

    background:
        rgba(249,115,22,0.08);

}


.hero-content {

    position:
        relative;

    z-index:
        5;

    max-width:
        780px;

}


.hero-badge {

    display:
        inline-flex;

    align-items:
        center;

    gap:
        8px;

    padding:
        8px 13px;

    margin-bottom:
        17px;

    border-radius:
        30px;

    color:
        var(--orange-dark);

    background:
        var(--orange-light);

    border:
        1px solid #fed7aa;

    font-size:
        12px;

    font-weight:
        800;

    text-transform:
        uppercase;

    letter-spacing:
        0.4px;

}


.hero-badge::before {

    content:
        "●";

    color:
        var(--orange);

}


.hero h1 {

    font-family:
        "Plus Jakarta Sans",
        sans-serif;

    font-size:
        clamp(34px, 5vw, 58px);

    line-height:
        1.05;

    letter-spacing:
        -1.8px;

    margin-bottom:
        17px;

}


.hero h1 span {

    color:
        var(--orange);

}


.hero-description {

    max-width:
        700px;

    color:
        var(--muted);

    line-height:
        1.8;

    font-size:
        15px;

}


.access-message {

    display:
        inline-flex;

    align-items:
        center;

    gap:
        8px;

    margin-top:
        22px;

    padding:
        11px 15px;

    border-radius:
        12px;

    color:
        var(--orange-dark);

    background:
        var(--orange-light);

    border:
        1px solid #fed7aa;

    font-size:
        13px;

    font-weight:
        700;

}


/* =========================================================
   3D OBJECTS
========================================================= */

.hero-object {

    position:
        absolute;

    z-index:
        2;

    border-radius:
        28px;

    pointer-events:
        none;

}


.object-one {

    width:
        175px;

    height:
        175px;

    right:
        100px;

    top:
        55px;

    background:
        linear-gradient(
            145deg,
            #fb923c,
            #ea580c
        );

    transform:
        rotate(25deg);

    box-shadow:
        18px 18px 0 #c2410c,
        25px 30px 40px rgba(234,88,12,0.22);

}


.object-two {

    width:
        70px;

    height:
        70px;

    right:
        285px;

    bottom:
        42px;

    background:
        #ffffff;

    border:
        9px solid var(--orange);

    transform:
        rotate(18deg);

    box-shadow:
        0 15px 30px rgba(249,115,22,0.15);

}


.object-three {

    width:
        38px;

    height:
        38px;

    right:
        55px;

    bottom:
        45px;

    border-radius:
        50%;

    background:
        var(--orange);

    box-shadow:
        10px 10px 0 rgba(234,88,12,0.25);

}


/* =========================================================
   STATISTICS
========================================================= */

.stats-grid {

    display:
        grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap:
        18px;

    margin-bottom:
        28px;

}


.stat-card {

    position:
        relative;

    overflow:
        hidden;

    padding:
        23px;

    border:
        1px solid var(--border);

    border-radius:
        21px;

    background:
        var(--white);

    box-shadow:
        var(--shadow);

    transition:
        0.25s ease;

}


.stat-card::before {

    content:
        "";

    position:
        absolute;

    top:
        0;

    left:
        0;

    width:
        100%;

    height:
        4px;

    background:
        var(--orange);

}


.stat-card:hover {

    transform:
        translateY(-5px);

    box-shadow:
        var(--shadow-hover);

}


.stat-card.clickable {

    cursor:
        pointer;

}


.stat-card.clickable:hover {

    border-color:
        var(--orange);

    transform:
        translateY(-7px);

}


.stat-card.active-stat {

    border:
        2px solid var(--orange);

    background:
        linear-gradient(
            145deg,
            #fffaf5,
            #fff3e8
        );

}


.stat-top {

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    margin-bottom:
        20px;

}


.stat-icon {

    width:
        46px;

    height:
        46px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    border-radius:
        14px;

    color:
        var(--orange-dark);

    background:
        var(--orange-light);

    border:
        1px solid #fed7aa;

    font-size:
        18px;

    font-weight:
        800;

}


.stat-label {

    color:
        var(--muted);

    font-size:
        12px;

    font-weight:
        600;

}


.stat-number {

    font-family:
        "Plus Jakarta Sans",
        sans-serif;

    font-size:
        34px;

    line-height:
        1;

    font-weight:
        800;

    margin-bottom:
        7px;

}


.stat-title {

    color:
        var(--muted);

    font-size:
        13px;

}


/* =========================================================
   FILTER MESSAGE
========================================================= */

.active-filter {

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    gap:
        15px;

    margin-bottom:
        20px;

    padding:
        14px 17px;

    border-radius:
        14px;

    background:
        var(--orange-light);

    border:
        1px solid #fed7aa;

}


.active-filter-text {

    color:
        var(--orange-dark);

    font-size:
        13px;

    font-weight:
        800;

}


.clear-filter {

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    padding:
        7px 11px;

    border-radius:
        8px;

    background:
        var(--white);

    border:
        1px solid #fed7aa;

    color:
        var(--orange-dark);

    font-size:
        11px;

    font-weight:
        800;

}


.clear-filter:hover {

    background:
        var(--orange);

    color:
        var(--white);

}


/* =========================================================
   SECTION
========================================================= */

.section {

    margin-bottom:
        28px;

    padding:
        28px;

    border:
        1px solid var(--border);

    border-radius:
        25px;

    background:
        rgba(255,255,255,0.97);

    box-shadow:
        var(--shadow);

}


.section-header {

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    gap:
        20px;

    margin-bottom:
        24px;

}


.section-title h2 {

    font-family:
        "Plus Jakarta Sans",
        sans-serif;

    font-size:
        23px;

    margin-bottom:
        6px;

}


.section-title p {

    color:
        var(--muted);

    font-size:
        13px;

}


.section-badge {

    padding:
        9px 14px;

    border-radius:
        30px;

    color:
        var(--orange-dark);

    background:
        var(--orange-light);

    border:
        1px solid #fed7aa;

    font-size:
        12px;

    font-weight:
        800;

    white-space:
        nowrap;

}


/* =========================================================
   TABLE
========================================================= */

.table-wrapper {

    width:
        100%;

    overflow-x:
        auto;

    border:
        1px solid var(--border);

    border-radius:
        17px;

}


table {

    width:
        100%;

    min-width:
        1050px;

    border-collapse:
        collapse;

}


thead {

    background:
        #fff8f1;

}


th {

    padding:
        15px;

    text-align:
        left;

    color:
        var(--orange-dark);

    font-size:
        11px;

    font-weight:
        800;

    text-transform:
        uppercase;

    letter-spacing:
        0.5px;

    border-bottom:
        1px solid var(--border);

    white-space:
        nowrap;

}


td {

    padding:
        16px 15px;

    font-size:
        13px;

    border-bottom:
        1px solid #f6e9df;

    vertical-align:
        middle;

}


tbody tr {

    transition:
        0.15s ease;

}


tbody tr:hover {

    background:
        #fffaf6;

}


tbody tr:last-child td {

    border-bottom:
        none;

}


.student-name {

    font-weight:
        700;

    margin-bottom:
        4px;

}


.student-code {

    color:
        var(--muted);

    font-size:
        11px;

}


.category-badge {

    display:
        inline-flex;

    padding:
        6px 9px;

    border-radius:
        20px;

    color:
        var(--orange-dark);

    background:
        var(--orange-light);

    border:
        1px solid #fed7aa;

    font-size:
        10px;

    font-weight:
        800;

}


.status {

    display:
        inline-flex;

    align-items:
        center;

    gap:
        6px;

    padding:
        6px 10px;

    border-radius:
        20px;

    font-size:
        11px;

    font-weight:
        800;

}


.status::before {

    content:
        "●";

    font-size:
        8px;

}


.status-pending {

    color:
        #a16207;

    background:
        #fffaf0;

    border:
        1px solid #f5d99a;

}


.status-progress {

    color:
        var(--orange-dark);

    background:
        var(--orange-light);

    border:
        1px solid #fed7aa;

}


.status-resolved {

    color:
        #c2410c;

    background:
        #fff1e8;

    border:
        1px solid #fdba74;

}


.view-button {

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    padding:
        8px 12px;

    border-radius:
        9px;

    color:
        var(--white);

    background:
        var(--orange);

    box-shadow:
        0 3px 0 var(--orange-dark);

    font-size:
        11px;

    font-weight:
        800;

    transition:
        0.2s ease;

    white-space:
        nowrap;

}


.view-button:hover {

    background:
        var(--orange-dark);

    transform:
        translateY(-1px);

}


/* =========================================================
   EMPTY
========================================================= */

.empty {

    padding:
        55px 20px;

    text-align:
        center;

    border:
        1px dashed var(--border);

    border-radius:
        17px;

    background:
        #fffaf6;

}


.empty-icon {

    width:
        65px;

    height:
        65px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    margin:
        0 auto 15px;

    border-radius:
        20px;

    background:
        var(--orange-light);

    font-size:
        28px;

}


.empty h3 {

    font-family:
        "Plus Jakarta Sans",
        sans-serif;

    margin-bottom:
        7px;

}


.empty p {

    color:
        var(--muted);

    font-size:
        13px;

}


/* =========================================================
   MODAL
========================================================= */

.modal-overlay {

    display:
        none;

    position:
        fixed;

    inset:
        0;

    z-index:
        2000;

    align-items:
        center;

    justify-content:
        center;

    padding:
        20px;

    background:
        rgba(35, 20, 10, 0.52);

    backdrop-filter:
        blur(5px);

}


.modal-box {

    width:
        100%;

    max-width:
        490px;

    padding:
        30px;

    border-radius:
        23px;

    background:
        var(--white);

    border:
        1px solid var(--border);

    box-shadow:
        0 30px 80px rgba(0,0,0,0.2);

}


.modal-icon {

    width:
        50px;

    height:
        50px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    margin-bottom:
        15px;

    border-radius:
        15px;

    color:
        var(--orange-dark);

    background:
        var(--orange-light);

    font-size:
        21px;

}


.modal-box h2 {

    font-family:
        "Plus Jakarta Sans",
        sans-serif;

    margin-bottom:
        9px;

}


.modal-box p {

    color:
        var(--muted);

    line-height:
        1.7;

    font-size:
        13px;

    margin-bottom:
        15px;

}


.contact-number {

    padding:
        13px;

    margin-bottom:
        20px;

    border-radius:
        12px;

    color:
        var(--orange-dark);

    background:
        var(--orange-light);

    border:
        1px solid #fed7aa;

    font-size:
        13px;

    font-weight:
        700;

}


.close-button {

    padding:
        10px 17px;

    border:
        none;

    border-radius:
        10px;

    color:
        var(--white);

    background:
        var(--orange);

    box-shadow:
        0 3px 0 var(--orange-dark);

    cursor:
        pointer;

    font-weight:
        800;

}


.close-button:hover {

    background:
        var(--orange-dark);

}


/* =========================================================
   FOOTER
========================================================= */

.footer {

    padding:
        15px 0;

    text-align:
        center;

    color:
        var(--muted);

    font-size:
        12px;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1050px) {

    .stats-grid {

        grid-template-columns:
            repeat(2, 1fr);

    }

}


@media (max-width: 800px) {

    .navbar {

        align-items:
            flex-start;

        flex-direction:
            column;

    }


    .nav-right {

        width:
            100%;

        justify-content:
            flex-start;

    }


    .hero {

        min-height:
            330px;

        padding:
            35px 28px;

    }


    .object-one {

        width:
            115px;

        height:
            115px;

        right:
            30px;

        top:
            55px;

    }


    .object-two {

        right:
            155px;

        bottom:
            30px;

    }


    .object-three {

        right:
            25px;

    }


    .section {

        padding:
            20px;

    }

}


@media (max-width: 600px) {

    .container {

        width:
            94%;

        padding-top:
            22px;

    }


    .nav-right {

        display:
            grid;

        grid-template-columns:
            1fr 1fr;

    }


    .welcome-text {

        grid-column:
            1 / -1;

    }


    .nav-button {

        width:
            100%;

    }


    .hero {

        padding:
            30px 22px;

    }


    .hero h1 {

        font-size:
            34px;

        letter-spacing:
            -1px;

    }


    .hero-description {

        font-size:
            13px;

    }


    .stats-grid {

        grid-template-columns:
            1fr;

    }


    .section-header {

        align-items:
            flex-start;

        flex-direction:
            column;

    }


    .section-badge {

        align-self:
            flex-start;

    }


    .active-filter {

        align-items:
            flex-start;

        flex-direction:
            column;

    }


    .object-one {

        opacity:
            0.35;

    }

}


/* =========================================================
   UNIFLOW ADMIN DASHBOARD — PREMIUM 3D OVERRIDE
========================================================= */

:root{
    --orange:#ff6a00;
    --orange2:#ff9a52;
    --orange-dark:#e65100;
    --cream:#fffaf6;
    --cream2:#fff1e5;
    --white:#fff;
    --ink:#24150d;
    --muted:#7d6e64;
    --line:#f2dccb;
    --shadow:0 18px 55px rgba(89,43,13,.10);
    --shadow3d:0 24px 55px rgba(89,43,13,.16),0 8px 0 rgba(230,81,0,.10);
}


body{
    background:
      radial-gradient(circle at 8% 8%,rgba(255,106,0,.13),transparent 24%),
      radial-gradient(circle at 92% 22%,rgba(255,154,82,.14),transparent 25%),
      linear-gradient(180deg,#fffdfb 0%,#fff7f0 48%,#fffaf6 100%) !important;
    overflow-x:hidden;
}


body::before,
body::after{
    content:"";
    position:fixed;
    border-radius:50%;
    pointer-events:none;
    z-index:-1;
    filter:blur(1px);
}


body::before{
    width:230px;
    height:230px;
    left:-90px;
    bottom:8%;
    background:rgba(255,106,0,.07);
}


body::after{
    width:180px;
    height:180px;
    right:-65px;
    top:38%;
    background:rgba(255,154,82,.08);
}


.navbar{
    padding:14px 5.5% !important;
    background:rgba(255,255,255,.84) !important;
    border-bottom:1px solid rgba(242,220,203,.9) !important;
    box-shadow:0 10px 35px rgba(69,34,12,.08) !important;
}


.logo{
    letter-spacing:-.5px;
}


.logo-icon{
    width:48px !important;
    height:48px !important;
    border-radius:15px !important;
    background:linear-gradient(145deg,#ff8a3d,#e65100) !important;
    box-shadow:
        0 7px 0 #c84400,
        0 16px 28px rgba(255,106,0,.28) !important;
    transform:rotate(-5deg) translateY(-1px) !important;
    transition:.25s ease;
}


.logo:hover .logo-icon{
    transform:rotate(5deg) translateY(-3px) scale(1.04) !important;
}


.nav-button{
    background:rgba(255,255,255,.96) !important;
    box-shadow:0 5px 0 #f0dfd2 !important;
    transition:.22s ease !important;
}


.nav-button:hover{
    transform:translateY(-4px) !important;
    box-shadow:
        0 9px 18px rgba(255,106,0,.15),
        0 3px 0 #f0dfd2 !important;
}


.nav-button.primary{
    background:linear-gradient(145deg,#ff7a1a,#e65100) !important;
    box-shadow:
        0 5px 0 #c84400,
        0 12px 25px rgba(255,106,0,.18) !important;
}


.container{
    max-width:1540px !important;
    padding-top:42px !important;
}


.hero{
    min-height:335px !important;
    border:1px solid #f3d8c2 !important;
    background:
      radial-gradient(circle at 90% 15%,rgba(255,106,0,.13),transparent 23%),
      linear-gradient(135deg,#fff 0%,#fffaf6 60%,#fff0e4 100%) !important;
    box-shadow:var(--shadow3d) !important;
    transform-style:preserve-3d;
    isolation:isolate;
}


.hero::after{
    content:"";
    position:absolute;
    left:8%;
    bottom:18px;
    width:180px;
    height:14px;
    border-radius:50%;
    background:rgba(93,44,13,.09);
    filter:blur(8px);
}


.hero-content{
    transform:translateZ(25px);
}


.hero-badge{
    box-shadow:
        0 5px 0 #f7dcc8,
        0 10px 25px rgba(255,106,0,.08);
}


.hero h1{
    text-shadow:0 4px 0 rgba(255,106,0,.08);
}


.hero h1 span{
    display:inline-block;
    background:linear-gradient(90deg,#ff6a00,#e65100);
    -webkit-background-clip:text;
    background-clip:text;
    color:transparent !important;
}


.hero-description{
    max-width:720px !important;
}


.access-message{
    box-shadow:0 5px 0 #f4dcc9;
}


.hero-object{
    animation:ufFloat 5s ease-in-out infinite;
    transform-style:preserve-3d;
}


.object-one{
    background:linear-gradient(145deg,#ff9a52,#e65100) !important;
    box-shadow:
        20px 20px 0 #c84400,
        28px 34px 45px rgba(230,81,0,.23) !important;
}


.object-two{
    animation-delay:-1.5s;
}


.object-three{
    animation-delay:-3s;
}


@keyframes ufFloat{
    0%,100%{
        translate:0 0;
        rotate:0deg;
    }

    50%{
        translate:0 -10px;
        rotate:2deg;
    }
}


.section{
    border:1px solid #f1d9c5 !important;
    box-shadow:var(--shadow) !important;
    background:rgba(255,255,255,.92) !important;
    backdrop-filter:blur(10px);
    position:relative;
    overflow:hidden;
}


.section::before{
    content:"";
    position:absolute;
    width:190px;
    height:190px;
    right:-95px;
    top:-95px;
    border-radius:50%;
    background:rgba(255,106,0,.07);
    pointer-events:none;
}


.section-header,
.section-title,
.section-badge{
    position:relative;
    z-index:2;
}


.section-badge{
    box-shadow:0 5px 0 #f4ddca;
}


.stats-grid{
    gap:20px !important;
}


.stat-card{
    min-height:175px;
    border:1px solid #efd8c5 !important;
    box-shadow:
        0 10px 0 rgba(242,220,203,.55),
        0 16px 35px rgba(89,43,13,.07) !important;
    transform-style:preserve-3d;
}


.stat-card:hover{
    transform:
        translateY(-9px)
        rotateX(2deg)
        rotateY(-1.5deg) !important;

    box-shadow:
        0 15px 0 rgba(230,81,0,.08),
        0 25px 45px rgba(89,43,13,.13) !important;
}


.stat-card.active-stat{
    box-shadow:
        0 12px 0 rgba(230,81,0,.11),
        0 24px 42px rgba(255,106,0,.13) !important;
}


.stat-icon{
    box-shadow:0 5px 0 #f4ddca;
    transform:translateZ(15px);
}


.stat-number{
    font-size:38px !important;
    color:#2a170d;
    text-shadow:0 3px 0 #ffe6d4;
}


.active-filter{
    box-shadow:0 5px 0 #f4ddca;
}


.table-wrapper{
    border:1px solid #efd8c5 !important;
    box-shadow:
        inset 0 1px 0 #fff,
        0 10px 25px rgba(89,43,13,.05);
}


thead{
    background:
        linear-gradient(
            180deg,
            #fff7ef,
            #fff0e3
        ) !important;
}


th{
    padding:17px 15px !important;
}


td{
    padding:17px 15px !important;
}


tbody tr{
    transition:.18s ease !important;
}


tbody tr:hover{
    background:#fff7f0 !important;
    transform:scale(1.002);
}


.view-button{
    background:
        linear-gradient(
            145deg,
            #ff7a1a,
            #e65100
        ) !important;

    box-shadow:
        0 4px 0 #c84400,
        0 8px 18px rgba(255,106,0,.15) !important;
}


.view-button:hover{
    transform:translateY(-3px) !important;
}


.category-badge,
.status{
    box-shadow:0 3px 0 rgba(242,220,203,.7);
}


.empty{
    box-shadow:
        inset 0 0 0 1px rgba(242,220,203,.45);
}


.empty-icon{
    box-shadow:0 6px 0 #f4ddca;
}


.modal-box{
    box-shadow:
        0 35px 90px rgba(45,21,8,.28),
        0 8px 0 rgba(230,81,0,.10) !important;
}


.footer{
    padding-top:8px !important;
    padding-bottom:30px !important;
}

.report-portal-switcher{
    display:flex;
    flex-wrap:wrap;
    gap:9px;
    margin:0 0 22px;
}

.report-portal-switcher a{
    display:inline-flex;
    align-items:center;
    min-height:40px;
    padding:8px 14px;
    border:1px solid #efd8c5;
    border-radius:10px;
    background:rgba(255,255,255,.9);
    color:#6f5140;
    font-size:12px;
    font-weight:800;
    box-shadow:0 4px 0 rgba(242,220,203,.55);
}

.report-portal-switcher a.active{
    border-color:#f7b889;
    background:#fff0e3;
    color:#b94b08;
}


@media(max-width:1050px){

    .stats-grid{
        grid-template-columns:
            repeat(2,1fr) !important;
    }

}


@media(max-width:650px){

    .container{
        width:94% !important;
        padding-top:24px !important;
    }

    .hero{
        padding:30px 23px !important;
        min-height:350px !important;
    }

    .hero-object{
        opacity:.28;
    }

    .stats-grid{
        grid-template-columns:
            1fr !important;
    }

    .section{
        padding:20px !important;
    }

}

</style>

</head>


<body>


<!-- =====================================================
     NAVBAR
===================================================== -->

<nav class="navbar">


<div class="logo">

<div class="logo-icon">
U
</div>

UniFlow

</div>


<div class="nav-right">


<div class="welcome-text">

Welcome,
<strong>
<?= e($_SESSION["admin_name"] ?? "Admin") ?>
</strong>

</div>


<a
    class="nav-button"
    href="../index.php"
>

Home

</a>

<?php if ($admin_role === "administrative"): ?>
<a class="nav-button" href="../config/admin_management.php">Admin Management</a>
<?php endif; ?>

<a
    class="nav-button primary"
    href="<?= e(basename($_SERVER["PHP_SELF"])) ?>?logout=1"
>

Logout

</a>


</div>

</nav>



<!-- =====================================================
     MAIN
===================================================== -->

<main class="container">


<!-- =====================================================
     HERO
===================================================== -->

<section class="hero">


<div class="hero-content">


<div class="hero-badge">

<?php if ($is_proctorial): ?>

Proctorial Committee Dashboard

<?php else: ?>

<?= e($active_portal_title) ?> Admin Dashboard

<?php endif; ?>

</div>


<h1>

<?= e($active_portal_title) ?>

<span>
Dashboard
</span>

</h1>


<?php if ($is_proctorial): ?>

<p class="hero-description">

Monitor and manage Proctorial reports
submitted by students through the UniFlow
Proctorial Committee dashboard.

</p>


<div class="access-message">

Proctorial Report Monitoring Enabled

</div>


<?php else: ?>

<p class="hero-description">

Manage student reports, monitor report status,
and keep your UniFlow services organized from
one modern dashboard.

</p>

<?php endif; ?>


</div>


<div class="hero-object object-one"></div>

<div class="hero-object object-two"></div>

<div class="hero-object object-three"></div>


</section>



<?php if (!$is_proctorial && count($available_report_portals) > 1): ?>
<nav class="report-portal-switcher" aria-label="Report portal">
    <?php foreach ($available_report_portals as $portal): ?>
        <?php if (isset($portal_labels[$portal])): ?>
            <a class="<?= ($selected_portal === "all" ? $admin_role : $selected_portal) === $portal ? "active" : "" ?>" href="<?= e(basename($_SERVER["PHP_SELF"])) ?>?portal=<?= e($portal) ?>">
                <?= e($portal_labels[$portal]) ?>
            </a>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>
<?php endif; ?>


<!-- =====================================================
     STATISTICS
===================================================== -->

<section class="stats-grid">


<!-- TOTAL -->

<a
    href="<?= e(basename($_SERVER["PHP_SELF"])) ?>?portal=<?= e($selected_portal) ?>&status=all"
    class="stat-card clickable <?= $selected_status === "all" ? "active-stat" : "" ?>"
>

<div class="stat-top">

<div class="stat-label">
Overview
</div>

</div>


<div class="stat-number">
<?= $total_reports ?>
</div>


<div class="stat-title">

<?= $is_proctorial
    ? "Total Proctorial Reports"
    : "Total Reports"
?>

</div>

</a>



<!-- PENDING -->

<a
    href="<?= e(basename($_SERVER["PHP_SELF"])) ?>?portal=<?= e($selected_portal) ?>&status=Pending"
    class="stat-card clickable <?= $selected_status === "Pending" ? "active-stat" : "" ?>"
>

<div class="stat-top">

<div class="stat-label">
Needs Attention
</div>

</div>


<div class="stat-number">
<?= $pending_reports ?>
</div>


<div class="stat-title">
Pending Reports
</div>

</a>



<!-- IN PROGRESS -->

<a
    href="<?= e(basename($_SERVER["PHP_SELF"])) ?>?portal=<?= e($selected_portal) ?>&status=In%20Progress"
    class="stat-card clickable <?= $selected_status === "In Progress" ? "active-stat" : "" ?>"
>

<div class="stat-top">

<div class="stat-label">
Processing
</div>

</div>


<div class="stat-number">
<?= $in_progress_reports ?>
</div>


<div class="stat-title">
In Progress
</div>

</a>



<!-- RESOLVED -->

<a
    href="<?= e(basename($_SERVER["PHP_SELF"])) ?>?portal=<?= e($selected_portal) ?>&status=Resolved"
    class="stat-card clickable <?= $selected_status === "Resolved" ? "active-stat" : "" ?>"
>

<div class="stat-top">

<div class="stat-label">
Completed
</div>

</div>


<div class="stat-number">
<?= $resolved_reports ?>
</div>


<div class="stat-title">
Resolved Reports
</div>

</a>


</section>



<!-- =====================================================
     REPORT SECTION
===================================================== -->

<section class="section">


<?php if ($selected_status !== "all"): ?>

<div class="active-filter">

<div class="active-filter-text">

Showing only
<?= e($selected_status) ?>

<?php if ($is_proctorial): ?>

Proctorial reports

<?php else: ?>

reports

<?php endif; ?>

</div>


<a
    class="clear-filter"
    href="<?= e(basename($_SERVER["PHP_SELF"])) ?>?portal=<?= e($selected_portal) ?>"
>

Show All Reports

</a>

</div>

<?php endif; ?>


<div class="section-header">


<div class="section-title">

<h2>

<?php

if ($is_proctorial) {

    echo "Proctorial Reports";

} elseif ($selected_portal !== "all") {

    echo e($active_portal_title) . " Reports";

} elseif ($admin_role === "technical") {

    echo "Technical Reports";

} elseif ($admin_role === "administrative") {

    echo "Administrative Reports";

} else {

    echo "Student Reports";

}

?>

</h2>


<p>

<?php

if ($selected_status !== "all") {

    if ($is_proctorial) {

        echo e($selected_status) .
             " Proctorial reports are shown below.";

    } else {

        echo e($selected_status) .
             " reports are shown below.";

    }

} elseif ($is_proctorial) {

    echo "Proctorial problems submitted by students.";

} else {

    echo "Recent reports submitted by students.";

}

?>

</p>

</div>


<div class="section-badge">

<?= count($reports) ?>

Reports

</div>


</div>



<?php if (count($reports) > 0): ?>


<div class="table-wrapper">


<table>


<thead>

<tr>

<th>
Student
</th>

<th>
Email
</th>

<?php if ($is_proctorial): ?>

<th>
Portal
</th>

<?php endif; ?>

<th>
Title
</th>

<th>
Description
</th>

<th>
Status
</th>

<th>
Date
</th>

<th>
Action
</th>

</tr>

</thead>


<tbody>


<?php foreach ($reports as $report): ?>


<tr>


<!-- STUDENT -->

<td>

<div class="student-name">

<?= e(
    $report["student_name"]
) ?>

</div>


<div class="student-code">

<?= e(
    $report["student_code"]
) ?>

</div>

</td>



<!-- EMAIL -->

<td>

<?= e(
    $report["student_email"]
) ?>

</td>



<!-- PORTAL -->

<?php if ($is_proctorial): ?>

<td>

<span class="category-badge">

Proctorial

</span>

</td>

<?php endif; ?>



<!-- TITLE -->

<td>

<?= e(
    $report["title"]
    ?? "N/A"
) ?>

</td>



<!-- DESCRIPTION -->

<td>

<?php

$description =
    $report["description"]
    ?? "";

if (strlen($description) > 80) {

    $description =
        substr(
            $description,
            0,
            80
        )
        . "...";

}

?>

<?= e($description) ?>

</td>



<!-- STATUS -->

<td>

<?php

$status =
    $report["status"]
    ?? "Pending";

$status_class =
    "status-pending";


if (
    strtolower($status)
    === "in progress"
) {

    $status_class =
        "status-progress";

} elseif (
    strtolower($status)
    === "resolved"
) {

    $status_class =
        "status-resolved";

}

?>


<span
    class="status <?= $status_class ?>"
>

<?= e($status) ?>

</span>

</td>



<!-- DATE -->

<td>

<?php

if (!empty($report["created_at"])) {

    echo e(
        date(
            "d M Y",
            strtotime(
                $report["created_at"]
            )
        )
    );

} else {

    echo "N/A";

}

?>

</td>



<!-- ACTION -->

<td>

<?php if (!$is_proctorial): ?>


<a
    class="view-button"
    href="../<?= e($report_category) ?>/update.php?id=<?= (int)$report["report_id"] ?>"
>

View / Update

</a>


<?php else: ?>


<a
    class="view-button"
    href="../proctorial/update.php?id=<?= (int)$report["report_id"] ?>"
>

View / Update

</a>


<?php endif; ?>

</td>


</tr>


<?php endforeach; ?>


</tbody>

</table>

</div>


<?php else: ?>


<div class="empty">

<div class="empty-icon"></div>


<h3>

<?php if ($selected_status !== "all"): ?>

No <?= e($selected_status) ?> Proctorial Reports

<?php else: ?>

No Proctorial Reports Yet

<?php endif; ?>

</h3>


<p>

<?php if ($selected_status !== "all"): ?>

There are currently no
<?= e($selected_status) ?>
Proctorial reports.

<?php else: ?>

No Proctorial reports are currently available.

<?php endif; ?>

</p>

</div>


<?php endif; ?>


</section>



<!-- =====================================================
     FOOTER
===================================================== -->

<footer class="footer">

UniFlow Admin Portal
<br>

<span>
Orange &amp; White Management Interface
</span>

</footer>


</main>


</body>

</html>