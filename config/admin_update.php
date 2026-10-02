<?php

require_once "../config/database.php";
require_once "../config/auth.php";


/*
|--------------------------------------------------------------------------
| GET ADMIN ROLE
|--------------------------------------------------------------------------
*/

if (!isset($admin_role) || empty($admin_role)) {
    $admin_role = $_SESSION["admin_role"] ?? "";
}


/*
|--------------------------------------------------------------------------
| CHECK LOGIN
|--------------------------------------------------------------------------
*/

admin_required($admin_role);


/*
|--------------------------------------------------------------------------
| PORTAL SETTINGS
|--------------------------------------------------------------------------
*/

$portal_title = ucfirst($admin_role);

$portal_description = "";

if ($admin_role === "technical") {

    $portal_description =
        "Review and manage technical problems submitted by students.";

} elseif ($admin_role === "administrative") {

    $portal_description =
        "Review and manage administrative problems submitted by students.";

} elseif ($admin_role === "proctorial") {

    $portal_description =
        "Review and manage proctorial reports submitted by students.";

} else {

    $portal_description =
        "Review and manage student reports.";

}


/*
|--------------------------------------------------------------------------
| GET REPORT ID
|--------------------------------------------------------------------------
*/

$report_id = isset($_GET["id"])
    ? intval($_GET["id"])
    : 0;

$error = "";
$success = "";


/*
|--------------------------------------------------------------------------
| GET REPORT
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "SELECT
        r.*,
        s.name AS student_name,
        s.student_code,
        s.email AS student_email

     FROM reports r

     INNER JOIN students s
     ON r.student_id = s.student_id

     WHERE r.report_id = ?
     AND r.category = ?"
);

$stmt->bind_param(
    "is",
    $report_id,
    $admin_role
);

$stmt->execute();

$report = $stmt
    ->get_result()
    ->fetch_assoc();

$stmt->close();


/*
|--------------------------------------------------------------------------
| REPORT NOT FOUND
|--------------------------------------------------------------------------
*/

if (!$report) {

    die("
        <!DOCTYPE html>

        <html lang='en'>

        <head>

            <meta charset='UTF-8'>

            <meta
                name='viewport'
                content='width=device-width, initial-scale=1.0'
            >

            <title>Report Not Found | UniFlow</title>

            <style>

                * {
                    box-sizing: border-box;
                }

                body {
                    margin: 0;
                    min-height: 100vh;

                    display: flex;
                    align-items: center;
                    justify-content: center;

                    padding: 25px;

                    font-family:
                        Arial,
                        sans-serif;

                    background:
                        linear-gradient(
                            135deg,
                            #fffaf5,
                            #fff3e8
                        );

                    color: #24160f;
                }

                .error-card {
                    width: 100%;
                    max-width: 500px;

                    padding: 45px 30px;

                    text-align: center;

                    background: #ffffff;

                    border: 1px solid #f1dfd0;

                    border-radius: 25px;

                    box-shadow:
                        0 25px 60px
                        rgba(77,39,15,0.10);
                }

                .error-icon {
                    width: 70px;
                    height: 70px;

                    margin: 0 auto 20px;

                    display: flex;
                    align-items: center;
                    justify-content: center;

                    border-radius: 20px;

                    color: #ea580c;

                    background: #fff7ed;

                    border: 1px solid #fed7aa;

                    font-size: 30px;
                }

                h2 {
                    margin: 0 0 10px;

                    font-size: 26px;
                }

                p {
                    color: #7c7068;

                    line-height: 1.7;

                    font-size: 14px;

                    margin-bottom: 25px;
                }

                a {
                    display: inline-flex;

                    align-items: center;
                    justify-content: center;

                    padding: 12px 20px;

                    border-radius: 11px;

                    background: #f97316;

                    color: #ffffff;

                    text-decoration: none;

                    font-weight: 700;

                    box-shadow:
                        0 4px 0 #c2410c;
                }

                a:hover {
                    background: #ea580c;
                }

            </style>

            <link rel='stylesheet' href='../css/visual-3d.css'>
<link rel="stylesheet" href="../css/buttons.css">
</head>

        <body>

            <div class='error-card'>

                <div class='error-icon'>
                    !
                </div>

                <h2>
                    Report Not Found
                </h2>

                <p>
                    The requested report could not be found
                    or you do not have access to it.
                </p>

                <a href='../{$admin_role}/dashboard.php'>
                    ← Back to Dashboard
                </a>

            </div>

        </body>

        </html>
    ");

}


/*
|--------------------------------------------------------------------------
| UPDATE REPORT
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $status = $_POST["status"] ?? "";

    $message = trim(
        $_POST["message"] ?? ""
    );


    $allowed_statuses = [
        "Pending",
        "In Progress",
        "Resolved",
        "Rejected"
    ];


    if (
        !in_array(
            $status,
            $allowed_statuses,
            true
        )
    ) {

        $error = "Invalid status.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | UPDATE REPORT
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare(
            "UPDATE reports
             SET status = ?
             WHERE report_id = ?
             AND category = ?"
        );

        $stmt->bind_param(
            "sis",
            $status,
            $report_id,
            $admin_role
        );

        $stmt->execute();

        $stmt->close();


        /*
        |--------------------------------------------------------------------------
        | SAVE UPDATE HISTORY
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare(
            "INSERT INTO report_updates
            (
                report_id,
                admin_id,
                status,
                message
            )
            VALUES (?, ?, ?, ?)"
        );

        $admin_id =
            (int) $_SESSION["admin_id"];

        $stmt->bind_param(
            "iiss",
            $report_id,
            $admin_id,
            $status,
            $message
        );

        $stmt->execute();

        $stmt->close();


        $success =
            "Report updated successfully!";


        /*
        |--------------------------------------------------------------------------
        | REFRESH REPORT
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare(
            "SELECT
                r.*,
                s.name AS student_name,
                s.student_code,
                s.email AS student_email

             FROM reports r

             INNER JOIN students s
             ON r.student_id = s.student_id

             WHERE r.report_id = ?
             AND r.category = ?"
        );

        $stmt->bind_param(
            "is",
            $report_id,
            $admin_role
        );

        $stmt->execute();

        $report = $stmt
            ->get_result()
            ->fetch_assoc();

        $stmt->close();

    }

}


/*
|--------------------------------------------------------------------------
| UPDATE HISTORY
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "SELECT
        ru.*,
        a.name AS admin_name

     FROM report_updates ru

     INNER JOIN admins a
     ON ru.admin_id = a.admin_id

     WHERE ru.report_id = ?

     ORDER BY ru.created_at DESC"
);

$stmt->bind_param(
    "i",
    $report_id
);

$stmt->execute();

$history = $stmt->get_result();

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
<?= e($portal_title) ?> Report | UniFlow
</title>


<link
    rel="preconnect"
    href="https://fonts.googleapis.com"
>

<link
    rel="preconnect"
    href="https://fonts.googleapis.com"
    crossorigin
>

<link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap"
    rel="stylesheet"
>


<style>

/* =========================================================
   ROOT
========================================================= */

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

    --danger: #b42318;

    --danger-bg: #fff1f0;

    --shadow:
        0 18px 50px
        rgba(77,39,15,0.08);

    --shadow-hover:
        0 25px 65px
        rgba(77,39,15,0.12);

}


/* =========================================================
   RESET
========================================================= */

* {

    margin: 0;

    padding: 0;

    box-sizing: border-box;

}


html {

    scroll-behavior: smooth;

}


body {

    min-height: 100vh;

    font-family:
        "Inter",
        Arial,
        sans-serif;

    color: var(--text);

    background:
        linear-gradient(
            180deg,
            #fffaf5 0%,
            #fff7f0 100%
        );

}


a {

    text-decoration: none;

    color: inherit;

}


/* =========================================================
   NAVBAR
========================================================= */

.navbar {

    position: sticky;

    top: 0;

    z-index: 1000;

    width: 100%;

    min-height: 74px;

    padding:
        13px 5%;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

    background:
        rgba(255,255,255,0.94);

    backdrop-filter:
        blur(18px);

    border-bottom:
        1px solid var(--border);

    box-shadow:
        0 8px 30px
        rgba(50,25,10,0.06);

}


/* =========================================================
   LOGO
========================================================= */

.logo {

    display: flex;

    align-items: center;

    gap: 11px;

    font-family:
        "Plus Jakarta Sans",
        sans-serif;

    font-size: 22px;

    font-weight: 800;

    white-space: nowrap;

}


.logo-icon {

    width: 44px;

    height: 44px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 14px;

    color: var(--white);

    background:
        linear-gradient(
            145deg,
            var(--orange),
            var(--orange-dark)
        );

    box-shadow:
        0 5px 0 #c2410c,
        0 12px 25px
        rgba(249,115,22,0.22);

    transform:
        rotate(-3deg);

    font-weight: 800;

}


/* =========================================================
   NAV RIGHT
========================================================= */

.nav-right {

    display: flex;

    align-items: center;

    gap: 9px;

    flex-wrap: wrap;

}


.nav-info {

    display: inline-flex;

    align-items: center;

    gap: 8px;

    padding:
        9px 13px;

    border-radius: 12px;

    background:
        var(--orange-light);

    border:
        1px solid #fed7aa;

    color:
        var(--muted);

    font-size: 12px;

}


.nav-info strong {

    color: var(--text);

}


.nav-button {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 7px;

    padding:
        10px 14px;

    border-radius: 11px;

    border:
        1px solid var(--border);

    background:
        var(--white);

    color:
        var(--text);

    font-size: 12px;

    font-weight: 800;

    transition: 0.2s ease;

}


.nav-button:hover {

    color:
        var(--orange-dark);

    border-color:
        var(--orange);

    transform:
        translateY(-2px);

    box-shadow:
        0 8px 18px
        rgba(249,115,22,0.10);

}


.nav-button.primary {

    color:
        var(--white);

    background:
        var(--orange);

    border-color:
        var(--orange);

    box-shadow:
        0 4px 0
        var(--orange-dark);

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
   PAGE
========================================================= */

.page-container {

    width: 92%;

    max-width: 1250px;

    margin: 0 auto;

    padding:
        35px 0 70px;

}


/* =========================================================
   HERO
========================================================= */

.report-hero {

    position: relative;

    overflow: hidden;

    min-height: 230px;

    display: flex;

    align-items: center;

    margin-bottom: 24px;

    padding:
        38px 42px;

    border:
        1px solid var(--border);

    border-radius: 28px;

    background:
        linear-gradient(
            135deg,
            #ffffff 0%,
            #fff9f3 100%
        );

    box-shadow:
        var(--shadow);

}


.report-hero::before {

    content: "";

    position: absolute;

    width: 340px;

    height: 340px;

    right: -120px;

    top: -150px;

    border-radius: 50%;

    background:
        rgba(249,115,22,0.08);

}


.hero-content {

    position: relative;

    z-index: 5;

    max-width: 720px;

}


.hero-badge {

    display: inline-flex;

    align-items: center;

    gap: 8px;

    padding:
        8px 13px;

    margin-bottom: 15px;

    border-radius: 30px;

    color:
        var(--orange-dark);

    background:
        var(--orange-light);

    border:
        1px solid #fed7aa;

    font-size: 11px;

    font-weight: 800;

    letter-spacing: 0.5px;

    text-transform: uppercase;

}


.hero-badge::before {

    content: "●";

    color:
        var(--orange);

}


.hero-title {

    font-family:
        "Plus Jakarta Sans",
        sans-serif;

    font-size:
        clamp(30px, 4vw, 48px);

    line-height: 1.08;

    letter-spacing: -1.4px;

    margin-bottom: 12px;

}


.hero-title span {

    color:
        var(--orange);

}


.hero-description {

    color:
        var(--muted);

    line-height: 1.7;

    font-size: 14px;

    max-width: 650px;

}


.hero-ticket {

    display: inline-flex;

    align-items: center;

    gap: 8px;

    margin-top: 18px;

    padding:
        10px 14px;

    border-radius: 11px;

    background:
        var(--white);

    border:
        1px solid var(--border);

    font-size: 12px;

    font-weight: 800;

}


.hero-ticket strong {

    color:
        var(--orange-dark);

}


/* =========================================================
   3D OBJECT
========================================================= */

.hero-object {

    position: absolute;

    z-index: 2;

    pointer-events: none;

}


.object-one {

    width: 145px;

    height: 145px;

    right: 110px;

    top: 45px;

    border-radius: 27px;

    background:
        linear-gradient(
            145deg,
            #fb923c,
            #ea580c
        );

    transform:
        rotate(25deg);

    box-shadow:
        17px 17px 0 #c2410c,
        25px 30px 40px
        rgba(234,88,12,0.22);

}


.object-two {

    width: 55px;

    height: 55px;

    right: 285px;

    bottom: 35px;

    border-radius: 15px;

    background:
        var(--white);

    border:
        7px solid var(--orange);

    transform:
        rotate(18deg);

}


.object-three {

    width: 28px;

    height: 28px;

    right: 55px;

    bottom: 40px;

    border-radius: 50%;

    background:
        var(--orange);

    box-shadow:
        8px 8px 0
        rgba(234,88,12,0.22);

}


/* =========================================================
   ALERTS
========================================================= */

.notice {

    display: flex;

    align-items: center;

    gap: 12px;

    padding:
        15px 17px;

    margin-bottom: 20px;

    border-radius: 14px;

    font-size: 13px;

    font-weight: 700;

}


.notice-icon {

    width: 35px;

    height: 35px;

    flex-shrink: 0;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 10px;

    font-weight: 900;

}


.notice.error {

    color:
        var(--danger);

    background:
        var(--danger-bg);

    border:
        1px solid #fecaca;

}


.notice.error .notice-icon {

    background:
        #fee2e2;

}


.notice.success {

    color:
        var(--orange-dark);

    background:
        var(--orange-light);

    border:
        1px solid #fed7aa;

}


.notice.success .notice-icon {

    background:
        #ffedd5;

}



/* =========================================================
   GRID / LAYOUT CHANGE
========================================================= */

.content-grid {
    display: flex;
    flex-direction: column;
    gap: 22px;
    width: 100%;
}

.content-grid > .card {
    width: 100%;
}


/* =========================================================
   CARD
========================================================= */

.card {

    background:
        rgba(255,255,255,0.97);

    border:
        1px solid var(--border);

    border-radius: 23px;

    box-shadow:
        var(--shadow);

    overflow: hidden;

}


.card-header {

    padding:
        23px 25px;

    border-bottom:
        1px solid var(--border);

    background:
        linear-gradient(
            135deg,
            #ffffff,
            #fffaf5
        );

}


.card-header-row {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

}


.card-icon {

    width: 43px;

    height: 43px;

    display: flex;

    align-items: center;

    justify-content: center;

    flex-shrink: 0;

    border-radius: 13px;

    color:
        var(--orange-dark);

    background:
        var(--orange-light);

    border:
        1px solid #fed7aa;

    font-weight: 900;

}


.card-header-content {

    display: flex;

    align-items: center;

    gap: 12px;

}


.card-header h2 {

    font-family:
        "Plus Jakarta Sans",
        sans-serif;

    font-size: 19px;

    margin-bottom: 4px;

}


.card-header p {

    color:
        var(--muted);

    font-size: 12px;

}


.card-body {

    padding:
        25px;

}


/* =========================================================
   STATUS BADGE
========================================================= */

.status-badge {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding:
        7px 11px;

    border-radius: 20px;

    font-size: 10px;

    font-weight: 800;

    white-space: nowrap;

}


.status-badge::before {

    content: "●";

    font-size: 8px;

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


.status-rejected {

    color:
        #b42318;

    background:
        #fff1f0;

    border:
        1px solid #fecaca;

}


/* =========================================================
   REPORT INFO
========================================================= */

.report-title {

    font-family:
        "Plus Jakarta Sans",
        sans-serif;

    font-size: 24px;

    line-height: 1.3;

    margin-bottom: 20px;

}


.info-grid {

    display: grid;

    grid-template-columns:
        repeat(2, minmax(0, 1fr));

    gap: 12px;

    margin-bottom: 22px;

}


.info-item {

    padding:
        15px;

    border:
        1px solid var(--border);

    border-radius: 14px;

    background:
        #fffaf6;

}


.info-label {

    display: block;

    margin-bottom: 6px;

    color:
        var(--muted);

    font-size: 10px;

    font-weight: 800;

    letter-spacing: 0.5px;

    text-transform: uppercase;

}


.info-value {

    color:
        var(--text);

    font-size: 13px;

    font-weight: 700;

    line-height: 1.5;

    word-break: break-word;

}


.info-value.orange {

    color:
        var(--orange-dark);

}


/* =========================================================
   DESCRIPTION
========================================================= */

.sub-heading {

    display: flex;

    align-items: center;

    gap: 9px;

    margin-bottom: 10px;

    font-family:
        "Plus Jakarta Sans",
        sans-serif;

    font-size: 15px;

}


.sub-heading::before {

    content: "";

    width: 4px;

    height: 18px;

    border-radius: 5px;

    background:
        var(--orange);

}


.description-box {

    padding:
        17px;

    border:
        1px solid var(--border);

    border-left:
        4px solid var(--orange);

    border-radius: 13px;

    background:
        #fffaf6;

    color:
        #4f443d;

    font-size: 13px;

    line-height: 1.8;

}


/* =========================================================
   IMAGE
========================================================= */

.image-preview {

    margin-top: 20px;

    padding:
        14px;

    border:
        1px solid var(--border);

    border-radius: 16px;

    background:
        #fffaf6;

}


.image-preview img {

    display: block;

    width: 100%;

    max-width: 480px;

    max-height: 430px;

    object-fit: contain;

    margin: 0 auto;

    border-radius: 12px;

}


/* =========================================================
   UPDATE FORM
========================================================= */

.form-group {

    margin-bottom: 20px;

}


.form-group label {

    display: block;

    margin-bottom: 8px;

    font-size: 12px;

    font-weight: 800;

}


.form-hint {

    display: block;

    margin-top: 6px;

    color:
        var(--muted);

    font-size: 11px;

}


.form-control {

    width: 100%;

    padding:
        13px 14px;

    border:
        1px solid var(--border);

    border-radius: 12px;

    outline: none;

    background:
        var(--white);

    color:
        var(--text);

    font-family:
        inherit;

    font-size: 13px;

    transition:
        0.2s ease;

}


.form-control:hover {

    border-color:
        #fdba74;

}


.form-control:focus {

    border-color:
        var(--orange);

    box-shadow:
        0 0 0 4px
        rgba(249,115,22,0.10);

}


textarea.form-control {

    min-height: 145px;

    resize: vertical;

    line-height: 1.7;

}


.status-select {

    cursor: pointer;

    font-weight: 700;

}


/* =========================================================
   UPDATE BUTTON
========================================================= */

.update-button {

    width: 100%;

    display: flex;

    align-items: center;

    justify-content: center;

    gap: 9px;

    padding:
        14px 18px;

    border: none;

    border-radius: 12px;

    color:
        var(--white);

    background:
        linear-gradient(
            145deg,
            var(--orange),
            var(--orange-dark)
        );

    box-shadow:
        0 5px 0 #c2410c,
        0 12px 25px
        rgba(249,115,22,0.18);

    font-family:
        inherit;

    font-size: 13px;

    font-weight: 800;

    cursor: pointer;

    transition:
        0.2s ease;

}


.update-button:hover {

    transform:
        translateY(-2px);

    box-shadow:
        0 7px 0 #c2410c,
        0 16px 30px
        rgba(249,115,22,0.22);

}


.update-button:active {

    transform:
        translateY(2px);

    box-shadow:
        0 3px 0 #c2410c;

}


/* =========================================================
   HISTORY
========================================================= */

.history-list {

    position: relative;

}


.history-list::before {

    content: "";

    position: absolute;

    left: 17px;

    top: 5px;

    bottom: 5px;

    width: 2px;

    background:
        #fed7aa;

}


.history-item {

    position: relative;

    padding-left: 48px;

    margin-bottom: 22px;

}


.history-item:last-child {

    margin-bottom: 0;

}


.history-dot {

    position: absolute;

    left: 8px;

    top: 5px;

    width: 20px;

    height: 20px;

    border-radius: 50%;

    background:
        var(--orange);

    border:
        5px solid #fff3e8;

    box-shadow:
        0 0 0 1px #fed7aa;

}


.history-card {

    padding:
        15px;

    border:
        1px solid var(--border);

    border-radius: 14px;

    background:
        #fffaf6;

}


.history-top {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 10px;

    margin-bottom: 9px;

}


.history-status {

    display: inline-flex;

    padding:
        6px 9px;

    border-radius: 20px;

    color:
        var(--orange-dark);

    background:
        var(--orange-light);

    border:
        1px solid #fed7aa;

    font-size: 10px;

    font-weight: 800;

}


.history-message {

    color:
        #4f443d;

    font-size: 12px;

    line-height: 1.7;

    margin-bottom: 9px;

    word-break: break-word;

}


.history-meta {

    color:
        var(--muted);

    font-size: 10px;

    line-height: 1.6;

}


.history-meta strong {

    color:
        var(--text);

}


/* =========================================================
   EMPTY HISTORY
========================================================= */

.empty-history {

    padding:
        35px 20px;

    text-align: center;

    border:
        1px dashed var(--border);

    border-radius: 15px;

    background:
        #fffaf6;

}


.empty-history-icon {

    width: 55px;

    height: 55px;

    display: flex;

    align-items: center;

    justify-content: center;

    margin: 0 auto 12px;

    border-radius: 17px;

    background:
        var(--orange-light);

    font-size: 23px;

}


.empty-history h3 {

    font-family:
        "Plus Jakarta Sans",
        sans-serif;

    font-size: 15px;

    margin-bottom: 5px;

}


.empty-history p {

    color:
        var(--muted);

    font-size: 11px;

}


/* =========================================================
   QUICK INFO
========================================================= */

.quick-info {

    margin-top: 20px;

    padding:
        16px;

    border-radius: 14px;

    background:
        var(--orange-light);

    border:
        1px solid #fed7aa;

}


.quick-info-title {

    color:
        var(--orange-dark);

    font-size: 11px;

    font-weight: 800;

    margin-bottom: 7px;

}


.quick-info p {

    color:
        #7c7068;

    font-size: 11px;

    line-height: 1.6;

}


/* =========================================================
   FOOTER
========================================================= */

.footer {

    padding:
        15px 0 0;

    text-align: center;

    color:
        var(--muted);

    font-size: 11px;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 950px) {

    .content-grid {

        grid-template-columns:
            1fr;

    }

}


@media (max-width: 700px) {

    .navbar {

        align-items:
            flex-start;

        flex-direction:
            column;

        padding:
            13px 4%;

    }


    .nav-right {

        width: 100%;

        justify-content:
            flex-start;

    }


    .nav-info {

        display: none;

    }


    .nav-button {

        flex: 1;

    }


    .page-container {

        width: 94%;

        padding-top:
            22px;

    }


    .report-hero {

        min-height:
            300px;

        padding:
            30px 24px;

    }


    .hero-title {

        font-size:
            33px;

    }


    .object-one {

        width:
            105px;

        height:
            105px;

        right:
            25px;

        top:
            55px;

        opacity:
            0.35;

    }


    .object-two {

        right:
            135px;

    }


    .object-three {

        right:
            20px;

    }


    .card-header,
    .card-body {

        padding:
            20px;

    }


    .info-grid {

        grid-template-columns:
            1fr;

    }


    .card-header-row {

        align-items:
            flex-start;

        flex-direction:
            column;

    }

}


@media (max-width: 480px) {

    .logo {

        font-size:
            20px;

    }


    .logo-icon {

        width:
            40px;

        height:
            40px;

    }


    .nav-right {

        display:
            grid;

        grid-template-columns:
            1fr 1fr;

    }


    .nav-button {

        width:
            100%;

        flex: unset;

    }


    .report-hero {

        padding:
            27px 20px;

    }


    .hero-title {

        font-size:
            29px;

    }


    .card-body {

        padding:
            17px;

    }


    .report-title {

        font-size:
            21px;

    }

}


/* =========================================================
   UNIFLOW ADMIN UPDATE — PREMIUM 3D ORANGE/WHITE OVERRIDE
   UI ONLY — existing PHP/database/auth/update logic preserved
========================================================= */

:root{
  --uf-orange:#f97316;
  --uf-orange2:#ff9a55;
  --uf-dark:#e85d0f;
  --uf-pale:#fff7ef;
  --uf-soft:#ffe7d2;
  --uf-ink:#2b211c;
  --uf-muted:#88766b;
  --uf-line:#efdfd3;
}

html{scroll-behavior:smooth}

body{
  overflow-x:hidden;
  background:
    radial-gradient(circle at 6% 10%,rgba(249,115,22,.14),transparent 23%),
    radial-gradient(circle at 94% 12%,rgba(255,154,85,.11),transparent 25%),
    radial-gradient(circle at 50% 100%,rgba(249,115,22,.07),transparent 31%),
    linear-gradient(135deg,#fffaf5,#fff,#fff7ef);
}

body::before{
  content:"";
  position:fixed;
  width:340px;height:340px;
  right:-170px;top:150px;
  border-radius:50%;
  border:1px solid rgba(249,115,22,.10);
  box-shadow:
    0 0 0 35px rgba(249,115,22,.025),
    0 0 0 70px rgba(249,115,22,.016);
  pointer-events:none;
  z-index:0;
}

.navbar{
  min-height:80px;
  padding:12px clamp(20px,5vw,70px);
  background:rgba(255,255,255,.84);
  border-bottom:1px solid rgba(249,115,22,.13);
  box-shadow:0 8px 30px rgba(55,29,12,.06);
}

.logo-icon{
  background:linear-gradient(145deg,#ff9a55,#e85d0f);
  box-shadow:
    0 5px 0 #c2410c,
    0 13px 28px rgba(249,115,22,.22),
    inset 0 1px 0 rgba(255,255,255,.25);
  transition:.25s ease;
}
.logo:hover .logo-icon{
  transform:rotate(3deg) translateY(-3px);
}

.nav-button{transition:.22s ease}
.nav-button:hover{transform:translateY(-3px)}
.nav-button.primary{
  background:linear-gradient(145deg,#ff8d45,#e85d0f);
  border-color:#e85d0f;
  box-shadow:
    0 5px 0 #c2410c,
    0 12px 25px rgba(249,115,22,.20);
}
.nav-button.primary:hover{
  background:linear-gradient(145deg,#ff7d2e,#d95108);
}

.page-container{
  position:relative;
  z-index:1;
  padding-top:42px;
}

.report-hero{
  border-radius:29px;
  border-color:#efddce;
  background:linear-gradient(135deg,rgba(255,255,255,.98),rgba(255,248,241,.94));
  box-shadow:
    0 25px 65px rgba(77,39,15,.10),
    inset 0 1px 0 rgba(255,255,255,.8);
  transform-style:preserve-3d;
}
.report-hero::after{
  content:"";
  position:absolute;
  width:180px;height:180px;
  left:-80px;bottom:-95px;
  border-radius:50%;
  background:rgba(249,115,22,.045);
}
.hero-badge{
  background:linear-gradient(135deg,#fff0e2,#fffaf6);
  border-color:#ffd5b6;
  box-shadow:0 8px 20px rgba(249,115,22,.07);
}
.hero-title{
  font-size:clamp(34px,4.5vw,54px);
  letter-spacing:-2px;
}
.hero-title span{text-shadow:0 8px 25px rgba(249,115,22,.15)}
.hero-ticket{box-shadow:0 7px 18px rgba(55,29,12,.05)}

.object-one{
  background:linear-gradient(145deg,#ffad76,#e85d0f);
  box-shadow:
    18px 18px 0 #c2410c,
    28px 32px 42px rgba(234,88,12,.20),
    inset 8px 8px 0 rgba(255,255,255,.08);
  animation:ufAdminFloat 6s ease-in-out infinite;
}
.object-two{
  box-shadow:10px 12px 22px rgba(249,115,22,.12);
  animation:ufAdminFloat2 5s ease-in-out infinite;
}
.object-three{
  box-shadow:8px 8px 0 rgba(234,88,12,.20);
  animation:ufAdminFloat2 4s ease-in-out infinite reverse;
}

.content-grid{gap:24px}

.card{
  border-radius:23px;
  border-color:#efdfd3;
  background:rgba(255,255,255,.96);
  box-shadow:
    0 20px 52px rgba(77,39,15,.08),
    inset 0 1px 0 rgba(255,255,255,.8);
  transition:.25s ease;
}
.card:hover{
  box-shadow:
    0 25px 62px rgba(77,39,15,.11),
    inset 0 1px 0 rgba(255,255,255,.8);
}
.card-header{
  background:linear-gradient(135deg,#fff,#fff9f4);
}
.card-icon{
  background:linear-gradient(145deg,#fff0e2,#fff9f4);
  border-color:#ffd6b9;
  box-shadow:0 8px 18px rgba(249,115,22,.07);
}

.info-item{
  background:linear-gradient(145deg,#fffdfb,#fff8f3);
  transition:.2s ease;
  box-shadow:0 5px 15px rgba(55,29,12,.025);
}
.info-item:hover{
  transform:translateY(-2px);
  border-color:#ffcba8;
  box-shadow:0 9px 20px rgba(249,115,22,.07);
}
.description-box{
  background:linear-gradient(145deg,#fffaf6,#fff);
  box-shadow:0 7px 18px rgba(55,29,12,.035);
}
.image-preview{
  background:linear-gradient(145deg,#fffaf6,#fff);
}
.image-preview img{
  box-shadow:0 12px 28px rgba(55,29,12,.09);
}

.form-control{
  border-color:#ecdcd0;
  box-shadow:0 4px 12px rgba(55,29,12,.025);
}
.form-control:hover{border-color:#fdba74}
.form-control:focus{
  border-color:var(--uf-orange);
  box-shadow:
    0 0 0 4px rgba(249,115,22,.10),
    0 8px 20px rgba(249,115,22,.07);
}

.update-button{
  min-height:50px;
  border-radius:13px;
  background:linear-gradient(145deg,#ff9149,#e85d0f);
  box-shadow:
    0 5px 0 #c2410c,
    0 13px 27px rgba(249,115,22,.20),
    inset 0 1px 0 rgba(255,255,255,.22);
}
.update-button:hover{
  background:linear-gradient(145deg,#ff8131,#d95108);
  transform:translateY(-3px);
  box-shadow:
    0 8px 0 #c2410c,
    0 18px 32px rgba(249,115,22,.25);
}
.update-button:active{
  transform:translateY(1px);
}

.notice{
  border-radius:16px;
  box-shadow:0 10px 25px rgba(55,29,12,.05);
}
.quick-info{
  box-shadow:0 8px 20px rgba(249,115,22,.05);
}

.history-list::before{
  background:linear-gradient(#ffd0ad,#fed7aa,#ffe9d8);
}
.history-dot{
  background:linear-gradient(145deg,#ff994f,#e85d0f);
  box-shadow:
    0 0 0 1px #ffcba8,
    0 6px 14px rgba(249,115,22,.15);
}
.history-card{
  background:linear-gradient(145deg,#fffdfb,#fff8f3);
  border-color:#efdfd3;
  box-shadow:0 6px 17px rgba(55,29,12,.035);
  transition:.2s ease;
}
.history-card:hover{
  transform:translateX(3px);
  border-color:#ffcba8;
  box-shadow:0 10px 22px rgba(249,115,22,.07);
}

.footer{padding-top:22px;opacity:.8}

@keyframes ufAdminFloat{
  0%,100%{transform:rotate(25deg) translate3d(0,0,0)}
  50%{transform:rotate(29deg) translate3d(0,-11px,0)}
}
@keyframes ufAdminFloat2{
  0%,100%{transform:translateY(0)}
  50%{transform:translateY(-7px)}
}

@media(max-width:700px){
  .page-container{padding-top:24px}
  .report-hero{border-radius:22px}
  .object-one{opacity:.32}
  .card{border-radius:19px}
}

@media(prefers-reduced-motion:reduce){
  *,*::before,*::after{
    animation-duration:.01ms!important;
    transition-duration:.01ms!important;
  }
}

</style>



<link rel="stylesheet" href="../css/buttons.css">
</head>


<body>


<!-- =====================================================
     NAVBAR
===================================================== -->

<nav class="navbar">


<a
    href="../index.php"
    class="logo"
>

<div class="logo-icon">
U
</div>

UniFlow

</a>


<div class="nav-right">


<div class="nav-info">

Admin:

<strong>
<?= e($_SESSION["admin_name"] ?? "Admin") ?>
</strong>

</div>


<a
    class="nav-button"
    href="../<?= e($admin_role) ?>/dashboard.php"
>

← Dashboard

</a>


<a
    class="nav-button primary"
    href="../<?= e($admin_role) ?>/logout.php"
>

↪ Logout

</a>


</div>

</nav>



<!-- =====================================================
     MAIN
===================================================== -->

<main class="page-container">


<!-- =====================================================
     HERO
===================================================== -->

<section class="report-hero">


<div class="hero-content">


<div class="hero-badge">

<?= e($portal_title) ?> Report Management

</div>


<h1 class="hero-title">

Review &
<span>
Update
</span>

</h1>


<p class="hero-description">

<?= e($portal_description) ?>

You can review the student's information,
check the submitted issue, update the current
status and leave a message for the student.

</p>


<div class="hero-ticket">

Ticket:

<strong>
<?= e($report["ticket_id"]) ?>
</strong>

</div>


</div>


<div class="hero-object object-one"></div>

<div class="hero-object object-two"></div>

<div class="hero-object object-three"></div>


</section>



<!-- =====================================================
     NOTICES
===================================================== -->

<?php if ($error): ?>

<div class="notice error">

<div class="notice-icon">
!
</div>

<div>

<?= e($error) ?>

</div>

</div>

<?php endif; ?>


<?php if ($success): ?>

<div class="notice success">

<div class="notice-icon">
✓
</div>

<div>

<?= e($success) ?>

</div>

</div>

<?php endif; ?>



<!-- =====================================================
     CONTENT GRID
===================================================== -->

<div class="content-grid">



<!-- =====================================================
     LEFT COLUMN
===================================================== -->

<div>



<section class="card">


<div class="card-header">


<div class="card-header-row">


<div class="card-header-content">


<div class="card-icon">
#
</div>


<div>

<h2>
Report Details
</h2>

<p>
Complete information submitted by the student.
</p>

</div>


</div>


<?php

$current_status =
    $report["status"]
    ?? "Pending";

$status_class =
    "status-pending";


if (
    $current_status === "In Progress"
) {

    $status_class =
        "status-progress";

} elseif (
    $current_status === "Resolved"
) {

    $status_class =
        "status-resolved";

} elseif (
    $current_status === "Rejected"
) {

    $status_class =
        "status-rejected";

}

?>


<span
    class="status-badge <?= $status_class ?>"
>

<?= e($current_status) ?>

</span>


</div>


</div>



<div class="card-body">


<h2 class="report-title">

<?= e(
    $report["title"]
    ?? "Untitled Report"
) ?>

</h2>



<div class="info-grid">


<div class="info-item">

<span class="info-label">
Student
</span>

<div class="info-value">

<?= e(
    $report["student_name"]
) ?>

</div>

</div>



<div class="info-item">

<span class="info-label">
Student ID
</span>

<div class="info-value">

<?= e(
    $report["student_code"]
) ?>

</div>

</div>



<div class="info-item">

<span class="info-label">
Email
</span>

<div class="info-value">

<?= e(
    $report["student_email"]
) ?>

</div>

</div>



<div class="info-item">

<span class="info-label">
Issue Type
</span>

<div class="info-value orange">

<?= e(
    $report["issue_type"]
) ?>

</div>

</div>



<div class="info-item">

<span class="info-label">
Location
</span>

<div class="info-value">

<?= e(
    $report["location_text"]
    ?: "Not provided"
) ?>

</div>

</div>



<div class="info-item">

<span class="info-label">
Priority
</span>

<div class="info-value orange">

<?= e(
    $report["priority"]
    ?: "Medium"
) ?>

</div>

</div>



<?php if (isset($report["created_at"])): ?>

<div class="info-item">

<span class="info-label">
Submitted
</span>

<div class="info-value">

<?= e(
    date(
        "d M Y, h:i A",
        strtotime(
            $report["created_at"]
        )
    )
) ?>

</div>

</div>

<?php endif; ?>



<?php if (isset($report["is_confidential"])): ?>

<div class="info-item">

<span class="info-label">
Confidential
</span>

<div class="info-value">

<?= $report["is_confidential"]
    ? "Yes"
    : "No"
?>

</div>

</div>

<?php endif; ?>


</div>



<h3 class="sub-heading">
Description
</h3>


<div class="description-box">

<?= nl2br(
    e(
        $report["description"]
        ?? "No description provided."
    )
) ?>

</div>



<?php if (!empty($report["image_path"])): ?>


<div class="image-preview">


<h3 class="sub-heading">
Attached Photo
</h3>


<img
    src="../<?= e($report["image_path"]) ?>"
    alt="Attached report photo"
>


</div>


<?php endif; ?>


</div>

</section>



<!-- =====================================================
     QUICK INFO
===================================================== -->

<div class="quick-info">

<div class="quick-info-title">

ⓘ Update Guidelines

</div>

<p>

Choose the correct status based on the current
condition of the report. You can also leave a
message to record what action was taken or
what information the student should know.

</p>

</div>


</div>



<!-- =====================================================
     RIGHT COLUMN
===================================================== -->

<div>



<!-- =====================================================
     UPDATE FORM
===================================================== -->

<section class="card">


<div class="card-header">


<div class="card-header-content">


<div class="card-icon">
↻
</div>


<div>

<h2>
Update Report
</h2>

<p>
Change status and add a message.
</p>

</div>


</div>


</div>



<div class="card-body">


<form
    method="POST"
>


<div class="form-group">


<label for="status">

Report Status

</label>


<select
    id="status"
    name="status"
    class="form-control status-select"
    required
>


<option
    value="Pending"
    <?= $report["status"] === "Pending"
        ? "selected"
        : "" ?>
>

Pending

</option>


<option
    value="In Progress"
    <?= $report["status"] === "In Progress"
        ? "selected"
        : "" ?>
>

In Progress

</option>


<option
    value="Resolved"
    <?= $report["status"] === "Resolved"
        ? "selected"
        : "" ?>
>

Resolved

</option>


<option
    value="Rejected"
    <?= $report["status"] === "Rejected"
        ? "selected"
        : "" ?>
>

Rejected

</option>


</select>


<span class="form-hint">

Select the current stage of this report.

</span>


</div>



<div class="form-group">


<label for="message">

Message to Student

</label>


<textarea
    id="message"
    name="message"
    class="form-control"
    rows="6"
    placeholder="Write an update message for the student..."
></textarea>


<span class="form-hint">

This message will be stored in the report
update history.

</span>


</div>



<button
    class="update-button"
    type="submit"
>

✓

Save Report Update

</button>


</form>


</div>

</section>



<!-- =====================================================
     HISTORY
===================================================== -->

<section
    class="card"
    style="margin-top: 22px;"
>


<div class="card-header">


<div class="card-header-content">


<div class="card-icon">
↻
</div>


<div>

<h2>
Update History
</h2>

<p>
Previous actions performed on this report.
</p>

</div>


</div>


</div>



<div class="card-body">


<?php if ($history->num_rows > 0): ?>


<div class="history-list">


<?php while (
    $update =
    $history->fetch_assoc()
): ?>


<div class="history-item">


<div class="history-dot"></div>


<div class="history-card">


<div class="history-top">


<span class="history-status">

<?= e(
    $update["status"]
) ?>

</span>


</div>


<div class="history-message">

<?php if (
    !empty(
        $update["message"]
    )
): ?>

<?= nl2br(
    e(
        $update["message"]
    )
) ?>

<?php else: ?>

No message was added.

<?php endif; ?>

</div>


<div class="history-meta">

Updated by

<strong>
<?= e(
    $update["admin_name"]
) ?>
</strong>


<br>


<?= e(
    date(
        "d M Y, h:i A",
        strtotime(
            $update["created_at"]
        )
    )
) ?>

</div>


</div>


</div>


<?php endwhile; ?>


</div>


<?php else: ?>


<div class="empty-history">


<div class="empty-history-icon">
↻
</div>


<h3>
No Update History
</h3>


<p>
No status update has been recorded for
this report yet.
</p>


</div>


<?php endif; ?>


</div>

</section>


</div>


</div>



<!-- =====================================================
     FOOTER
===================================================== -->

<footer class="footer">

UniFlow Admin Portal
<br>

Orange &amp; White Report Management Interface

</footer>


</main>


</body>

</html>