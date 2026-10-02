<?php

require_once "../config/database.php";
require_once "../config/auth.php";

student_required();

$message = "";
$type = "";


/* =========================================================
   DEPARTMENT CONTACT INFORMATION
   IMPORTANT:
   নিচের phone number-গুলো তোমাদের actual number দিয়ে replace করো.
========================================================= */

$emergency_contacts = [

    "technical" => [

        "department" =>
            "Technical / IT Support",

        "short_name" =>
            "Technical Support",

        "phone" =>
            "01925408197",

        "description" =>
            "IT support, network / Wi-Fi, lab and system / software issues."

    ],

    "administrative" => [

        "department" =>
            "Administrative Support Department",

        "short_name" =>
            "Administrative Support",

        "phone" =>
            " 01838027665",

        "description" =>
            "Registrar, HR, finance, admissions, student affairs and facilities / maintenance requests."

    ]

];

$issue_types = [
    "technical" => [
        "IT Support",
        "Network / Wi-Fi",
        "Lab Support",
        "System / Software Support"
    ],
    "administrative" => [
        "Registrar / Academic Office",
        "HR",
        "Finance / Accounts",
        "Admissions",
        "Student Affairs",
        "Facilities / Maintenance",
        "Electrical",
        "Plumbing",
        "Cleaning",
        "Classroom / Building Maintenance"
    ],
    "proctorial" => [
        "Student Safety",
        "Discipline / Misconduct",
        "Ragging",
        "Theft / Security",
        "Harassment"
    ]
];


/* =========================================================
   POST
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $category =
        $_POST["category"] ?? "";

    if ($category === "lost_found") {

        header(
            "Location: ../lost_found/index.php"
        );

        exit();
    }

    $issue_type =
        trim(
            $_POST["issue_type"] ?? ""
        );

    if (
        !isset($issue_types[$category])
        || !in_array($issue_type, $issue_types[$category], true)
    ) {
        $message = "Choose a valid category and issue type.";
        $type = "error";
    } else {


    $title =
        trim(
            $_POST["title"] ?? ""
        );

    $description =
        trim(
            $_POST["description"] ?? ""
        );

    $location_text =
        trim(
            $_POST["location_text"] ?? ""
        );

    $priority =
        $_POST["priority"] ?? "Medium";


    $is_confidential =
        isset(
            $_POST["is_confidential"]
        )
        ? 1
        : 0;


    $item_state =
        $_POST["item_state"]
        ?? null;


    $image_path = null;


    /* =====================================================
       IMAGE UPLOAD
    ===================================================== */

    if (
        isset($_FILES["image"])
        &&
        $_FILES["image"]["error"] === 0
    ) {

        $extension =
            pathinfo(
                $_FILES["image"]["name"],
                PATHINFO_EXTENSION
            );


        $extension =
            strtolower(
                $extension
            );


        $allowed_extensions = [
            "jpg",
            "jpeg",
            "png",
            "gif",
            "webp"
        ];


        if (
            in_array(
                $extension,
                $allowed_extensions,
                true
            )
        ) {

            $file_name =
                time()
                . "_"
                . uniqid()
                . "."
                . $extension;


            $upload_path =
                "../uploads/"
                . $file_name;


            if (
                move_uploaded_file(
                    $_FILES["image"]["tmp_name"],
                    $upload_path
                )
            ) {

                $image_path =
                    "uploads/"
                    . $file_name;
            }
        }
    }


    /* =====================================================
       TICKET ID
    ===================================================== */

    $ticket_id =
        "UNI-"
        . strtoupper(
            substr(
                $category,
                0,
                3
            )
        )
        . "-"
        . date("Ymd")
        . "-"
        . rand(
            1000,
            9999
        );


    /* =====================================================
       STUDENT ID
    ===================================================== */

    $student_id =
        $_SESSION["student_id"];


    /* =====================================================
       INSERT REPORT
    ===================================================== */

    $stmt =
        $conn->prepare(

            "INSERT INTO reports
            (
                ticket_id,
                student_id,
                category,
                issue_type,
                title,
                description,
                location_text,
                priority,
                is_confidential,
                item_state,
                image_path
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );


    if ($stmt) {

        $stmt->bind_param(

            "sissssssiss",

            $ticket_id,
            $student_id,
            $category,
            $issue_type,
            $title,
            $description,
            $location_text,
            $priority,
            $is_confidential,
            $item_state,
            $image_path
        );


        if ($stmt->execute()) {

            $message =
                "Report submitted successfully! Ticket: "
                . $ticket_id;

            $type =
                "success";

        } else {

            $message =
                "Something went wrong while submitting the report.";

            $type =
                "error";
        }


        $stmt->close();

    } else {

        $message =
            "Unable to prepare the report.";

        $type =
            "error";
    }
    }
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
    Report an Issue | UniFlow
</title>


<link
    rel="stylesheet"
    href="../css/style.css"
>


<style>

.report-brand{
    display:inline-flex;
    align-items:center;
    gap:11px;
    color:var(--text);
    font-family:"Plus Jakarta Sans",Arial,sans-serif;
    font-size:22px;
    font-weight:800;
    white-space:nowrap;
}
.report-brand-icon{
    width:45px;
    height:45px;
    flex:0 0 45px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    border-radius:14px;
    color:#fff;
    background:linear-gradient(145deg,#f97316,#ea580c);
    box-shadow:0 6px 0 #c2410c,0 12px 25px rgba(249,115,22,.22);
    transform:rotate(-3deg);
}
body{
    min-height:100vh;
    overflow-x:hidden;
    font-family:Inter,Arial,sans-serif;
    color:#2f211a;
    background:
        radial-gradient(circle at 8% 12%,rgba(255,138,61,.15),transparent 24%),
        radial-gradient(circle at 92% 18%,rgba(255,106,0,.11),transparent 26%),
        linear-gradient(135deg,#fff8f2 0%,#fff 48%,#fff6ed 100%);
}
body::before{
    content:"";
    position:fixed;
    inset:0;
    pointer-events:none;
    background-image:radial-gradient(rgba(255,106,0,.10) 1px,transparent 1px);
    background-size:24px 24px;
    mask-image:linear-gradient(to bottom,rgba(0,0,0,.35),transparent 72%);
}
.report-orb,.report-cube,.report-ring{position:fixed;pointer-events:none;z-index:0}
.report-orb{border-radius:50%;background:radial-gradient(circle at 30% 25%,#fff 0 8%,#ffb36e 18%,#ff7a1a 54%,#e65300 100%);box-shadow:inset -18px -22px 35px rgba(143,48,0,.16),0 28px 55px rgba(255,106,0,.12)}
.report-orb.one{width:140px;height:140px;right:-48px;top:125px;opacity:.46;animation:reportFloat 7s ease-in-out infinite}
.report-orb.two{width:74px;height:74px;left:3%;bottom:12%;opacity:.3;animation:reportFloat 8s ease-in-out infinite reverse}
.report-cube{width:68px;height:68px;left:8%;top:150px;border-radius:17px;background:linear-gradient(145deg,#ff9b52,#f45c00);transform:rotate(22deg);opacity:.14;box-shadow:12px 15px 0 rgba(197,69,0,.10);animation:reportFloat 8s ease-in-out infinite}
.report-ring{width:160px;height:160px;right:8%;bottom:8%;border:18px solid rgba(255,106,0,.09);border-radius:50%;transform:rotate(-20deg) perspective(400px) rotateY(45deg)}
@keyframes reportFloat{50%{translate:0 -12px;rotate:5deg}}
.nav{
    position:sticky;
    top:0;
    z-index:20;
    height:auto;
    min-height:72px;
    padding:10px clamp(18px,5vw,76px);
    background:rgba(255,255,255,.90);
    border-bottom:1px solid rgba(241,223,209,.9);
    box-shadow:0 8px 26px rgba(89,43,14,.06);
    backdrop-filter:blur(16px);
}
.nav-links{gap:8px;flex-wrap:wrap}
.nav-links a{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:40px;
    padding:9px 14px;
    border:1px solid #f1e4da;
    border-radius:11px;
    background:#fff;
    color:#2f211a;
    font-size:12px;
    font-weight:800;
    transition:.2s ease;
}
.nav-links a:hover{transform:translateY(-2px);border-color:#ffd4b5;background:#fff7f0;color:#c2410c}
.nav-links a:last-child{border-color:transparent;background:linear-gradient(135deg,#ff8a3d,#e85d0f);color:#fff;box-shadow:0 5px 12px rgba(249,115,22,.18)}
.nav-links a:last-child:hover{background:#c2410c;color:#fff}
.page{
    position:relative;
    z-index:1;
    display:block;
    min-height:calc(100vh - 72px);
    padding:42px clamp(18px,4vw,64px) 64px;
    background:transparent;
}
.report-container{width:min(100%,1180px);margin:0 auto}
.report-hero{margin-bottom:22px}
.report-kicker{
    display:inline-flex;
    padding:7px 12px;
    border:1px solid #ffe4cf;
    border-radius:999px;
    background:#fff3e8;
    color:#c2410c;
    font-size:10px;
    font-weight:800;
    letter-spacing:.8px;
    text-transform:uppercase;
}
.report-hero h1{margin-top:13px;font:800 clamp(35px,4.5vw,56px)/1.04 "Plus Jakarta Sans",Inter,Arial,sans-serif;letter-spacing:-1.5px;color:#2f211a}
.report-hero h1 span{color:#f97316}
.report-hero p{max-width:720px;margin-top:10px;color:#8a7467;font-size:14px;line-height:1.7}
.report-panel{
    position:relative;
    z-index:1;
    width:min(100%,1180px);
    margin:0 auto;
    padding:25px;
    border:1px solid #f1e4da;
    border-radius:18px;
    background:rgba(255,255,255,.96);
    box-shadow:0 16px 42px rgba(80,40,15,.08);
}
.report-form-heading{margin-bottom:18px}
.report-form-heading h2{font:800 20px "Plus Jakarta Sans",Inter,Arial,sans-serif;color:#2f211a}
.report-form-heading .small{margin-top:5px;color:#8a7467;font-size:12px;line-height:1.6}
.report-panel label{margin:0 0 7px;color:#49372c;font-size:12px;font-weight:800}
.report-panel input:not([type="checkbox"]):not([type="file"]),
.report-panel select,
.report-panel textarea{
    min-height:45px;
    padding:10px 13px;
    border:1px solid #ead7c8;
    border-radius:11px;
    background:#fff;
    color:#2f211a;
    font:500 13px Inter,Arial,sans-serif;
    transition:.18s ease;
}
.report-panel input:focus,.report-panel select:focus,.report-panel textarea:focus{border-color:#ff984d;box-shadow:0 0 0 3px rgba(255,106,0,.10)}
.report-panel textarea{min-height:145px;resize:vertical}
.report-panel .form-grid{gap:17px}
.report-panel .full{grid-column:1/-1}
.report-panel .notice{margin:0 0 18px;border:1px solid #f1e4da;border-radius:11px;padding:12px 14px;font-size:12px;font-weight:700}
.report-panel .notice.success{border-color:#b9e5c8;background:#f1fbf4;color:#256b3b}
.report-panel .notice.error{border-color:#ffc9b9;background:#fff4f0;color:#a63c1f}
.report-panel .btn{min-height:45px;padding:11px 19px;border:0;border-radius:11px;background:linear-gradient(135deg,#ff8a3d,#e85d0f);box-shadow:0 7px 15px rgba(249,115,22,.18);font:800 13px Inter,Arial,sans-serif;transition:.2s ease}
.report-panel .btn:hover{transform:translateY(-2px);background:#c2410c;box-shadow:0 10px 21px rgba(249,115,22,.22)}

/* =========================================================
   EMERGENCY CONTACT SECTION
========================================================= */

.contact-section {

    margin:
        8px 0 25px;

    padding:
        20px;

    border-radius:
        18px;

    background:

        linear-gradient(
            145deg,
            #fffaf3,
            #fff3e5
        );

    border:
        1px solid
        rgba(234,88,12,.14);

    box-shadow:
        0 12px 30px
        rgba(120,53,15,.06);
}

.contact-section{margin:0 0 21px;padding:18px;border-radius:15px;background:linear-gradient(145deg,#fffaf6,#fff3e9);box-shadow:0 7px 18px rgba(120,53,15,.045)}
.contact-heading h3{font:800 16px "Plus Jakarta Sans",Inter,Arial,sans-serif}
.contact-card{border-radius:12px;background:#fff}
.contact-card.active{background:#fff7f0}
.contact-phone{border-radius:9px}


.contact-heading {

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    gap:
        15px;

    margin-bottom:
        15px;
}


.contact-heading-left {

    display:
        flex;

    align-items:
        center;

    gap:
        11px;
}


.contact-icon {

    width:
        42px;

    height:
        42px;

    display:
        grid;

    place-items:
        center;

    border-radius:
        13px;

    background:
        linear-gradient(
            145deg,
            #fb923c,
            #ea580c
        );

    color:
        white;

    font-size:
        18px;

    box-shadow:
        0 9px 20px
        rgba(234,88,12,.20);
}


.contact-heading h3 {

    margin: 0;

    color:
        #3c2419;

    font-size:
        17px;

    font-weight:
        800;
}


.contact-heading p {

    margin:
        3px 0 0;

    color:
        #8a6d60;

    font-size:
        11px;
}


/* =========================================================
   CONTACT CARDS
========================================================= */

.contact-grid {

    display:
        grid;

    grid-template-columns:
        repeat(auto-fit, minmax(220px, 1fr));

    gap:
        12px;
}


.contact-card {

    position:
        relative;

    padding:
        15px;

    border-radius:
        15px;

    background:
        rgba(255,255,255,.88);

    border:
        1px solid
        rgba(154,52,18,.10);

    transition:
        border-color .25s ease,
        background .25s ease,
        transform .25s ease,
        box-shadow .25s ease;
}


.contact-card:hover {

    transform:
        translateY(-3px);

    border-color:
        rgba(234,88,12,.24);

    box-shadow:
        0 12px 25px
        rgba(120,53,15,.08);
}


.contact-card.active {

    border-color:
        #ea580c;

    background:
        linear-gradient(
            145deg,
            #fffaf3,
            #ffead5
        );

    box-shadow:
        0 12px 28px
        rgba(234,88,12,.12);
}


.contact-card-top {

    display:
        flex;

    align-items:
        flex-start;

    justify-content:
        space-between;

    gap:
        10px;
}


.contact-card-icon {

    width:
        34px;

    height:
        34px;

    display:
        grid;

    place-items:
        center;

    border-radius:
        10px;

    background:
        #fff7ed;

    color:
        #c2410c;

    font-size:
        15px;
}


.contact-status {

    display:
        none;

    padding:
        4px 7px;

    border-radius:
        999px;

    background:
        #fff;

    color:
        #c2410c;

    font-size:
        8px;

    font-weight:
        850;

    text-transform:
        uppercase;

    letter-spacing:
        .08em;
}


.contact-card.active
.contact-status {

    display:
        inline-flex;
}


.contact-card h4 {

    margin:
        11px 0 4px;

    color:
        #352118;

    font-size:
        13px;

    line-height:
        1.3;
}


.contact-card .contact-description {

    color:
        #7e6559;

    font-size:
        10px;

    line-height:
        1.5;

    min-height:
        30px;
}


/* =========================================================
   PHONE BUTTON
========================================================= */

.contact-phone {

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    gap:
        8px;

    margin-top:
        12px;

    padding:
        9px 10px;

    border-radius:
        10px;

    background:
        #fff7ed;

    border:
        1px solid
        #fed7aa;

    color:
        #c2410c;

    text-decoration:
        none;

    font-size:
        11px;

    font-weight:
        850;

    transition:
        background .2s ease,
        color .2s ease;
}


.contact-phone:hover {

    background:
        #ea580c;

    color:
        white;
}


.contact-note {

    margin-top:
        11px;

    color:
        #9a3412;

    font-size:
        9px;

    line-height:
        1.45;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 700px) {

    .contact-grid {

        grid-template-columns:
            1fr;
    }

    .contact-heading {

        align-items:
            flex-start;
    }
}

@media(max-width:700px){
    .nav{align-items:flex-start;flex-direction:column;gap:10px;padding:12px 18px}
    .nav-links{width:100%;gap:6px}
    .nav-links a{flex:1;padding:8px 9px;font-size:11px}
    .page{padding:30px 14px 48px}
    .report-hero h1{font-size:36px;letter-spacing:-1px}
    .report-panel{padding:17px;border-radius:15px}
    .report-panel .form-grid,.contact-grid{grid-template-columns:1fr}
    .report-panel .full{grid-column:auto}
    .report-panel .btn{width:100%}
    .report-ring{right:-85px}
    .report-cube{left:-20px}
}

</style>

</head>


<body>

<div class="report-orb one" aria-hidden="true"></div>
<div class="report-orb two" aria-hidden="true"></div>
<div class="report-cube" aria-hidden="true"></div>
<div class="report-ring" aria-hidden="true"></div>


<!-- =====================================================
     NAVBAR
===================================================== -->

<nav class="nav">

    <a
        class="brand report-brand"
        href="dashboard.php"
    >
        <span class="report-brand-icon" aria-hidden="true">U</span>
        <span>UniFlow</span>
    </a>


    <div class="nav-links">

        <a class="nav-link" href="dashboard.php">
            Dashboard
        </a>

        <a class="nav-link" href="my_reports.php">
            My Reports
        </a>

        <a class="nav-link" href="logout.php">
            Logout
        </a>

    </div>

</nav>


<!-- =====================================================
     PAGE
===================================================== -->

<div class="page report-page">

<div class="report-container">

    <section class="report-hero">
        <div class="report-kicker">Student Services</div>
        <h1>Report <span>an Issue</span></h1>
        <p>Send an issue to the right campus department with the details they need to help.</p>
    </section>

    <form
        class="panel report-panel"
        method="post"
        enctype="multipart/form-data"
    >


        <div class="report-form-heading">
            <h2>Issue details</h2>
            <p class="small">Choose a category and describe what happened.</p>
        </div>


        <!-- =================================================
             NOTICE
        ================================================== -->

        <?php if ($message): ?>

            <div class="notice <?= e($type) ?>">

                <?= e($message) ?>

            </div>

        <?php endif; ?>


        <!-- =================================================
             DEPARTMENT CONTACT
        ================================================== -->

        <div class="contact-section">

            <div class="contact-heading">

                <div class="contact-heading-left">

                    <div class="contact-icon">
                        ☎
                    </div>

                    <div>

                        <h3>
                            Emergency / Quick Contact
                        </h3>

                        <p>
                            Need direct help? Contact the relevant department.
                        </p>

                    </div>

                </div>

            </div>


            <div class="contact-grid">


                <!-- =========================================
                     TECHNICAL
                ========================================== -->

                <div
                    class="contact-card"
                    id="technicalContact"
                >

                    <div class="contact-card-top">

                        <div class="contact-card-icon">
                            ⚙
                        </div>

                        <span class="contact-status">
                            Selected
                        </span>

                    </div>


                    <h4>
                        <?= e(
                            $emergency_contacts["technical"]["department"]
                        ) ?>
                    </h4>


                    <p class="contact-description">

                        <?= e(
                            $emergency_contacts["technical"]["description"]
                        ) ?>

                    </p>


                    <a
                        class="contact-phone"
                        href="tel:<?= e(
                            $emergency_contacts["technical"]["phone"]
                        ) ?>"
                    >

                        <span>
                            📞
                            <?= e(
                                $emergency_contacts["technical"]["phone"]
                            ) ?>
                        </span>

                        <span>
                            Call
                        </span>

                    </a>

                </div>


                <!-- =========================================
                     ADMINISTRATIVE
                ========================================== -->

                <div
                    class="contact-card"
                    id="administrativeContact"
                >

                    <div class="contact-card-top">

                        <div class="contact-card-icon">
                            🏢
                        </div>

                        <span class="contact-status">
                            Selected
                        </span>

                    </div>


                    <h4>
                        <?= e(
                            $emergency_contacts["administrative"]["department"]
                        ) ?>
                    </h4>


                    <p class="contact-description">

                        <?= e(
                            $emergency_contacts["administrative"]["description"]
                        ) ?>

                    </p>


                    <a
                        class="contact-phone"
                        href="tel:<?= e(
                            $emergency_contacts["administrative"]["phone"]
                        ) ?>"
                    >

                        <span>
                            📞
                            <?= e(
                                $emergency_contacts["administrative"]["phone"]
                            ) ?>
                        </span>

                        <span>
                            Call
                        </span>

                    </a>

                </div>


            </div>


            <div class="contact-note">

                Please contact the relevant department directly
                only when you need immediate assistance.

            </div>

        </div>


        <!-- =================================================
             FORM GRID
        ================================================== -->

        <div class="form-grid">


            <!-- =============================================
                 CATEGORY
            ============================================== -->

            <div>

                <label>
                    Category
                </label>

                <select
                    name="category"
                    id="category"
                    required
                >

                    <option value="">
                        Select Category
                    </option>

                    <option value="technical">
                        Technical
                    </option>

                    <option value="administrative">
                        Administrative
                    </option>

                    <option value="proctorial">
                        Proctorial
                    </option>

                    <option value="lost_found">
                        Lost &amp; Found
                    </option>

                </select>

            </div>


            <!-- =============================================
                 ISSUE TYPE
            ============================================== -->

            <div>

                <label>
                    Issue Type
                </label>

                <select
                    name="issue_type"
                    id="issue_type"
                    required
                >

                    <option value="">
                        Select category first
                    </option>

                </select>

            </div>


            <!-- =============================================
                 TITLE
            ============================================== -->

            <div class="full">

                <label>
                    Title
                </label>

                <input
                    type="text"
                    name="title"
                    placeholder="Give your issue a clear title"
                    required
                >

            </div>


            <!-- =============================================
                 DESCRIPTION
            ============================================== -->

            <div class="full">

                <label>
                    Description
                </label>

                <textarea
                    name="description"
                    placeholder="Describe the issue clearly..."
                    required
                ></textarea>

            </div>


            <!-- =============================================
                 LOCATION
            ============================================== -->

            <div>

                <label>
                    Location
                </label>

                <input
                    type="text"
                    name="location_text"
                    placeholder="Example: Lab 203"
                >

            </div>


            <!-- =============================================
                 PRIORITY
            ============================================== -->

            <div>

                <label>
                    Priority
                </label>

                <select name="priority">

                    <option>
                        Low
                    </option>

                    <option selected>
                        Medium
                    </option>

                    <option>
                        High
                    </option>

                </select>

            </div>


            <!-- =============================================
                 LOST ITEM STATUS
            ============================================== -->

            <div
                id="lostOptions"
                class="full"
                style="display:none;"
            >

                <label>
                    Item Status
                </label>

                <select name="item_state">

                    <option value="">
                        Select
                    </option>

                    <option value="Lost">
                        Lost
                    </option>

                    <option value="Found">
                        Found
                    </option>

                </select>

            </div>


            <!-- =============================================
                 CONFIDENTIAL
            ============================================== -->

            <div
                id="confidentialOption"
                class="full"
                style="display:none;"
            >

                <label>

                    <input
                        type="checkbox"
                        name="is_confidential"
                        value="1"
                    >

                    Keep this report confidential

                </label>

            </div>


            <!-- =============================================
                 PHOTO
            ============================================== -->

            <div
                id="photoOption"
                class="full"
                style="display:none;"
            >

                <label>
                    Upload Photo
                </label>

                <input
                    type="file"
                    name="image"
                    accept="image/*"
                >

            </div>


            <!-- =============================================
                 SUBMIT
            ============================================== -->

            <div class="full">

                <button
                    class="btn"
                    type="submit"
                >

                    Submit Report

                </button>

            </div>

        </div>

    </form>

</div>

</div>


<script>

/* =========================================================
   CATEGORY / ISSUE TYPES
========================================================= */

const category =
    document.getElementById("category");


const issueType =
    document.getElementById("issue_type");


const lostOptions =
    document.getElementById("lostOptions");


const confidentialOption =
    document.getElementById("confidentialOption");


const photoOption =
    document.getElementById("photoOption");


/* =========================================================
   CONTACT CARDS
========================================================= */

const technicalContact =
    document.getElementById(
        "technicalContact"
    );


const administrativeContact =
    document.getElementById(
        "administrativeContact"
    );


/* =========================================================
   ISSUE TYPES
========================================================= */

const options = {

    technical: [
        "IT Support",
        "Network / Wi-Fi",
        "Lab Support",
        "System / Software Support"
    ],


    administrative: [
        "Registrar / Academic Office",
        "HR",
        "Finance / Accounts",
        "Admissions",
        "Student Affairs",
        "Facilities / Maintenance",
        "Electrical",
        "Plumbing",
        "Cleaning",
        "Classroom / Building Maintenance"
    ],


    proctorial: [
        "Student Safety",
        "Discipline / Misconduct",
        "Ragging",
        "Theft / Security",
        "Harassment"
    ],


    lost_found: [

        "Lost Item",

        "Found Item"

    ]

};


/* =========================================================
   CATEGORY CHANGE
========================================================= */

category.addEventListener(
    "change",
    function() {

        const selected =
            this.value;


        /* -----------------------------------------------
           Lost & Found
        ----------------------------------------------- */

        if (
            selected === "lost_found"
        ) {

            window.location.href =
                "../lost_found/index.php";

            return;
        }


        /* -----------------------------------------------
           Reset issue types
        ----------------------------------------------- */

        issueType.innerHTML =
            '<option value="">Select Issue Type</option>';


        if (
            options[selected]
        ) {

            options[selected].forEach(
                function(item) {

                    const option =
                        document.createElement(
                            "option"
                        );


                    option.value =
                        item;


                    option.textContent =
                        item;


                    issueType.appendChild(
                        option
                    );

                }
            );
        }


        /* -----------------------------------------------
           Lost options
        ----------------------------------------------- */

        if (
            selected === "lost_found"
        ) {

            lostOptions.style.display =
                "block";

            photoOption.style.display =
                "block";

        } else {

            lostOptions.style.display =
                "none";

            photoOption.style.display =
                "none";
        }


        /* -----------------------------------------------
           Confidential
        ----------------------------------------------- */

        if (
            selected === "proctorial"
        ) {

            confidentialOption.style.display =
                "block";

        } else {

            confidentialOption.style.display =
                "none";
        }


        /* -----------------------------------------------
           Contact highlighting
        ----------------------------------------------- */

        technicalContact.classList.remove(
            "active"
        );


        administrativeContact.classList.remove(
            "active"
        );


        if (
            selected === "technical"
        ) {

            technicalContact.classList.add(
                "active"
            );

        }


        if (
            selected === "administrative"
        ) {

            administrativeContact.classList.add(
                "active"
            );
        }


    }
);

</script>


</body>

</html>