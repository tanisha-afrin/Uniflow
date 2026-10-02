<?php

require_once "../config/database.php";
require_once "../config/auth.php";

student_required();

$student_id = $_SESSION["student_id"];

$counts = [
    "Total" => 0,
    "Pending" => 0,
    "In Progress" => 0,
    "Resolved" => 0
];


/* =========================
   REPORT COUNTS
========================= */

$result = $conn->query(
    "SELECT status, COUNT(*) AS total
     FROM reports
     WHERE student_id = $student_id
     GROUP BY status"
);

while ($row = $result->fetch_assoc()) {

    if (isset($counts[$row["status"]])) {

        $counts[$row["status"]] =
            (int)$row["total"];

    }

}


/* =========================
   RECENT REPORTS
========================= */

$reports = $conn->query(
    "SELECT *
     FROM reports
     WHERE student_id = $student_id
     ORDER BY created_at DESC
     LIMIT 8"
);

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
Student Dashboard | UniFlow
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
    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap"
    rel="stylesheet"
>


<style>

/* =====================================================
   ROOT
===================================================== */

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
    --shadow: 0 15px 40px rgba(77,39,15,0.07);
    --shadow-hover: 0 22px 50px rgba(77,39,15,0.12);
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
    min-height: 100vh;
    font-family: "Inter", Arial, sans-serif;
    color: var(--text);
    background: linear-gradient(180deg, #fffaf5 0%, #ffffff 45%, #fff8f2 100%);
}


a {

    text-decoration:
        none;

    color:
        inherit;

}


/* =====================================================
   NAVBAR
===================================================== */

.nav {

    position:
        sticky;

    top:
        0;

    z-index:
        1000;

    width:
        100%;

    min-height:
        72px;

    padding:
        13px 4%;

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    gap:
        20px;

    background:
        rgba(255,255,255,0.94);

    backdrop-filter:
        blur(18px);

    border-bottom:
        1px solid var(--border);

    box-shadow:
        0 8px 28px rgba(80,40,15,0.06);

}


/* =====================================================
   BRAND
===================================================== */

.brand {

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
        23px;

    font-weight:
        800;

    white-space:
        nowrap;

}


.brand-icon {

    width:45px;
    height:45px;
    flex:0 0 45px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    color:
        var(--white);

    background:linear-gradient(145deg,#f97316,#ea580c);

    border-radius:
        14px;

    box-shadow:0 6px 0 #c2410c,0 12px 25px rgba(249,115,22,.22);

    transform:rotate(-3deg);

}


/* =====================================================
   NAV LINKS
===================================================== */

.nav-links {

    display:
        flex;

    align-items:
        center;

    gap:
        8px;

    flex-wrap:
        wrap;

    justify-content:
        flex-end;

}


.nav-links a {

    padding:
        9px 13px;

    border-radius:
        10px;

    color:
        var(--text);

    font-size:
        13px;

    font-weight:
        700;

    transition:
        0.2s ease;

}


.nav-links a:hover {

    color:
        var(--orange-dark);

    background:
        var(--orange-light);

    transform:
        translateY(-2px);

}


.nav-links .nav-primary {

    color:
        var(--white);

    background:
        var(--orange);

    box-shadow:
        0 3px 0 var(--orange-dark);

}


.nav-links .nav-primary:hover {

    color:
        var(--white);

    background:
        var(--orange-dark);

}


/* =====================================================
   MAIN
===================================================== */

.container {

    width:
        100%;

    max-width:
        1500px;

    margin:
        0 auto;

    padding:
        34px 4% 65px;

}


/* =====================================================
   HERO
===================================================== */

.dashboard-head {

    position:
        relative;

    overflow:
        hidden;

    min-height:
        285px;

    margin-bottom:
        27px;

    padding:
        45px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    gap:
        30px;

    border:
        1px solid var(--border);

    border-radius:
        29px;

    background:
        linear-gradient(
            125deg,
            #ffffff 0%,
            #fffaf6 60%,
            #fff0e1 100%
        );

    box-shadow:
        var(--shadow);

}


.dashboard-head::before {

    content:
        "";

    position:
        absolute;

    width:
        340px;

    height:
        340px;

    right:
        -100px;

    top:
        -145px;

    border-radius:
        50%;

    background:
        rgba(249,115,22,0.08);

}


.dashboard-head::after {

    content:
        "";

    position:
        absolute;

    width:
        125px;

    height:
        125px;

    right:
        160px;

    bottom:
        28px;

    border-radius:
        28px;

    background:
        linear-gradient(
            145deg,
            #ff8a1c,
            #f97316,
            #ea580c
        );

    transform:
        rotate(18deg);

    box-shadow:
        14px 15px 0 rgba(194,65,12,0.15),
        22px 25px 35px rgba(234,88,12,0.16);

    animation:
        floatingShape 4.5s ease-in-out infinite;

}


@keyframes floatingShape {

    0%,
    100% {

        transform:
            translateY(0)
            rotate(18deg);

    }

    50% {

        transform:
            translateY(-9px)
            rotate(22deg);

    }

}


.hero-content {

    position:
        relative;

    z-index:
        5;

    max-width:
        720px;

}


.hero-badge {

    display:
        inline-flex;

    align-items:
        center;

    gap:
        7px;

    padding:
        8px 13px;

    margin-bottom:
        17px;

    border-radius:
        30px;

    background:
        var(--orange-light);

    border:
        1px solid #fed7aa;

    color:
        var(--orange-dark);

    font-size:
        11px;

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


.dashboard-head h2 {

    margin:
        0 0 10px;

    font-family:
        "Plus Jakarta Sans",
        sans-serif;

    font-size:
        clamp(32px, 4vw, 52px);

    line-height:
        1.08;

    letter-spacing:
        -1.5px;

}


.dashboard-head h2 span {

    color:
        var(--orange);

}


.dashboard-head p {

    color:
        var(--muted);

    font-size:
        15px;

    line-height:
        1.7;

}


/* =====================================================
   HERO BUTTON
===================================================== */

.btn {

    position:
        relative;

    z-index:
        10;

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    padding:
        13px 21px;

    border-radius:
        11px;

    color:
        var(--white);

    background:
        linear-gradient(
            145deg,
            #ff7a18,
            #ea580c
        );

    box-shadow:
        0 5px 0 #c2410c,
        0 11px 22px rgba(249,115,22,0.20);

    font-size:
        13px;

    font-weight:
        800;

    transition:
        0.2s ease;

    white-space:
        nowrap;

}


.btn:hover {

    color:
        var(--white);

    transform:
        translateY(-3px);

    box-shadow:
        0 8px 0 #c2410c,
        0 15px 27px rgba(249,115,22,0.25);

}


.btn:active {

    transform:
        translateY(2px);

    box-shadow:
        0 2px 0 #c2410c;

}


/* =====================================================
   STATISTICS
===================================================== */

.stats {

    display:
        grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap:
        18px;

    margin-bottom:
        28px;

}


.stat {

    position:
        relative;

    overflow:
        hidden;

    min-height:
        145px;

    padding:
        23px;

    display:
        flex;

    flex-direction:
        column;

    justify-content:
        space-between;

    border:
        1px solid var(--border);

    border-radius:
        20px;

    background:
        linear-gradient(
            145deg,
            #ffffff,
            #fffaf6
        );

    box-shadow:
        var(--shadow);

    transition:
        0.25s ease;

}


.stat::before {

    content:
        "";

    position:
        absolute;

    left:
        0;

    top:
        0;

    width:
        5px;

    height:
        100%;

    background:
        linear-gradient(
            #ff8a1c,
            #ea580c
        );

}


.stat::after {

    content:
        "";

    position:
        absolute;

    width:
        85px;

    height:
        85px;

    right:
        -28px;

    bottom:
        -30px;

    border-radius:
        50%;

    background:
        var(--orange-light);

}


.stat:hover {

    transform:
        translateY(-6px);

    border-color:
        #f7c79e;

    box-shadow:
        var(--shadow-hover);

}


.stat-top {

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

}


.stat-icon {

    width:
        43px;

    height:
        43px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    border-radius:
        13px;

    color:
        var(--orange-dark);

    background:
        var(--orange-light);

    border:
        1px solid #fed7aa;

    font-weight:
        800;

}


.stat-label {

    color:
        var(--muted);

    font-size:
        11px;

    font-weight:
        700;

}


.stat b {

    position:
        relative;

    z-index:
        2;

    font-family:
        "Plus Jakarta Sans",
        sans-serif;

    color:
        var(--orange);

    font-size:
        35px;

    line-height:
        1;

}


.stat-title {

    position:
        relative;

    z-index:
        2;

    margin-top:
        7px;

    color:
        var(--muted);

    font-size:
        13px;

}


/* =====================================================
   REPORT SECTION
===================================================== */

.reports-section {

    padding:
        28px;

    border:
        1px solid var(--border);

    border-radius:
        24px;

    background:
        rgba(255,255,255,0.96);

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
        22px;

}


.section-header h3 {

    font-family:
        "Plus Jakarta Sans",
        sans-serif;

    font-size:
        22px;

    margin-bottom:
        5px;

}


.section-header p {

    color:
        var(--muted);

    font-size:
        13px;

}


.report-count {

    padding:
        8px 13px;

    border-radius:
        30px;

    color:
        var(--orange-dark);

    background:
        var(--orange-light);

    border:
        1px solid #fed7aa;

    font-size:
        11px;

    font-weight:
        800;

    white-space:
        nowrap;

}


/* =====================================================
   TABLE
===================================================== */

.table-wrap {

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
        760px;

    border-collapse:
        collapse;

}


thead {

    background:
        linear-gradient(
            180deg,
            #fff8f0,
            #fff3e7
        );

}


th {

    padding:
        15px 17px;

    text-align:
        left;

    color:
        var(--orange-dark);

    border-bottom:
        1px solid var(--border);

    font-size:
        11px;

    font-weight:
        800;

    text-transform:
        uppercase;

    letter-spacing:
        0.4px;

}


td {

    padding:
        16px 17px;

    border-bottom:
        1px solid #f4e8de;

    font-size:
        13px;

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


/* =====================================================
   TICKET
===================================================== */

.ticket {

    font-weight:
        800;

    color:
        var(--text);

}


.ticket-sub {

    margin-top:
        4px;

    color:
        var(--muted);

    font-size:
        10px;

}


/* =====================================================
   CATEGORY
===================================================== */

.category {

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


/* =====================================================
   STATUS
===================================================== */

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
        10px;

    font-weight:
        800;

}


.status::before {

    content:
        "●";

    font-size:
        7px;

}


.status-pending {

    color:
        #a16207;

    background:
        #fffaf0;

    border:
        1px solid #f3d58e;

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


/* =====================================================
   EMPTY STATE
===================================================== */

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

    margin:
        0 auto 15px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    border-radius:
        20px;

    background:
        var(--orange-light);

    font-size:
        28px;

}


.empty h4 {

    margin-bottom:
        7px;

    font-family:
        "Plus Jakarta Sans",
        sans-serif;

}


.empty p {

    color:
        var(--muted);

    font-size:
        13px;

}


/* =====================================================
   SCROLLBAR
===================================================== */

.table-wrap::-webkit-scrollbar {

    height:
        7px;

}


.table-wrap::-webkit-scrollbar-track {

    background:
        #fff5eb;

}


.table-wrap::-webkit-scrollbar-thumb {

    background:
        #fdba74;

    border-radius:
        10px;

}


/* =====================================================
   FOOTER
===================================================== */

.footer {

    padding:
        24px 0 0;

    text-align:
        center;

    color:
        var(--muted);

    font-size:
        11px;

}


/* =====================================================
   TABLET
===================================================== */

@media (max-width: 1000px) {

    .stats {

        grid-template-columns:
            repeat(2, 1fr);

    }

}


/* =====================================================
   MOBILE
===================================================== */

@media (max-width: 700px) {

    .nav {

        flex-direction:
            column;

        align-items:
            flex-start;

        padding:
            12px 18px;

    }


    .nav-links {

        width:
            100%;

        justify-content:
            flex-start;

    }


    .nav-links a {

        padding:
            8px 10px;

        font-size:
            12px;

    }


    .container {

        padding:
            22px 14px 45px;

    }


    .dashboard-head {

        min-height:
            350px;

        padding:
            30px 23px;

        flex-direction:
            column;

        align-items:
            flex-start;

        justify-content:
            center;

    }


    .dashboard-head h2 {

        font-size:
            32px;

    }


    .dashboard-head p {

        font-size:
            13px;

    }


    .dashboard-head::after {

        width:
            90px;

        height:
            90px;

        right:
            45px;

        bottom:
            35px;

        opacity:
            0.45;

    }


    .btn {

        width:
            100%;

    }


    .stats {

        grid-template-columns:
            1fr;

        gap:
            14px;

    }


    .stat {

        min-height:
            125px;

    }


    .reports-section {

        padding:
            19px;

    }


    .section-header {

        align-items:
            flex-start;

        flex-direction:
            column;

    }


    .report-count {

        align-self:
            flex-start;

    }

}


/* =====================================================
   SMALL MOBILE
===================================================== */

@media (max-width: 430px) {

    .brand {

        font-size:
            21px;

    }


    .brand-icon {

        width:45px;
        height:45px;
        flex-basis:45px;

    }


    .dashboard-head h2 {

        font-size:
            28px;

    }


    .stat b {

        font-size:
            31px;

    }

}

/* Dashboard-focused visual system */
body{
    background:
        radial-gradient(circle at 8% 8%,rgba(255,138,61,.13),transparent 24%),
        radial-gradient(circle at 94% 15%,rgba(255,106,0,.09),transparent 23%),
        linear-gradient(145deg,#fff8f2 0%,#fff 50%,#fff6ed 100%);
    overflow-x:hidden;
}
body::before{
    content:"";
    position:fixed;
    inset:0;
    pointer-events:none;
    background-image:radial-gradient(rgba(255,106,0,.08) 1px,transparent 1px);
    background-size:26px 26px;
    mask-image:linear-gradient(to bottom,rgba(0,0,0,.32),transparent 68%);
}
.nav{
    min-height:72px;
    padding:10px clamp(18px,4vw,64px);
    background:rgba(255,255,255,.88);
    border-bottom:1px solid rgba(241,223,209,.9);
    box-shadow:0 8px 26px rgba(80,40,15,.06);
}
.nav-links a{
    min-height:39px;
    padding:9px 13px;
    border:1px solid transparent;
    border-radius:10px;
    font-size:12px;
}
.nav-links a:hover{border-color:#ffe0c7}
.nav-links .nav-primary{
    border:0;
    background:linear-gradient(135deg,#ff8a3d,#e85d0f);
    box-shadow:0 5px 0 #c2410c,0 9px 16px rgba(249,115,22,.16);
}
.container{
    position:relative;
    z-index:1;
    max-width:1480px;
    padding:34px clamp(18px,4vw,64px) 58px;
}
.dashboard-head{
    display:grid;
    grid-template-columns:minmax(0,1fr) 250px;
    align-items:center;
    gap:28px;
    min-height:238px;
    margin-bottom:19px;
    padding:32px 36px;
    border:1px solid #f1e4da;
    border-radius:23px;
    background:linear-gradient(120deg,#fff 0%,#fffaf6 62%,#fff0e2 100%);
    box-shadow:0 15px 38px rgba(77,39,15,.07);
}
.dashboard-head::before{
    width:330px;
    height:330px;
    right:-160px;
    top:-225px;
    background:rgba(249,115,22,.07);
}
.dashboard-head::after{display:none}
.hero-main{
    position:relative;
    z-index:2;
    display:flex;
    flex-direction:column;
    align-items:flex-start;
    gap:19px;
}
.hero-content{max-width:760px}
.hero-badge{padding:7px 11px;margin-bottom:13px;font-size:10px;letter-spacing:.6px}
.dashboard-head h2{margin-bottom:8px;font-size:46px;line-height:1.12;letter-spacing:0}
.dashboard-head p{max-width:670px;font-size:14px;line-height:1.7}
.dashboard-head .btn{
    padding:12px 18px;
    border-radius:10px;
    box-shadow:0 4px 0 #c2410c,0 9px 18px rgba(249,115,22,.18);
    font-size:12px;
}
.resolution-card{
    position:relative;
    z-index:2;
    min-height:166px;
    padding:18px;
    display:flex;
    align-items:center;
    gap:15px;
    border:1px solid rgba(241,223,209,.95);
    border-radius:17px;
    background:rgba(255,255,255,.82);
    box-shadow:0 8px 20px rgba(77,39,15,.05);
}
.resolution-ring{
    position:relative;
    flex:0 0 92px;
    width:92px;
    height:92px;
    display:grid;
    place-items:center;
    border-radius:50%;
    background:conic-gradient(from -90deg,#f97316 var(--resolved-angle),#f1e4da var(--resolved-angle));
}
.resolution-ring::before{
    content:"";
    position:absolute;
    inset:8px;
    border-radius:50%;
    background:#fff;
}
.resolution-ring span{position:relative;z-index:1;font:800 21px "Plus Jakarta Sans",sans-serif;color:#2f211a}
.resolution-copy{display:flex;flex-direction:column;gap:5px}
.resolution-copy small{color:#8a7467;font-size:10px;font-weight:800;text-transform:uppercase}
.resolution-copy strong{font:800 15px "Plus Jakarta Sans",sans-serif;color:#2f211a}
.resolution-copy span{color:#8a7467;font-size:10px;line-height:1.5}
.stats{
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:13px;
    margin-bottom:19px;
}
.stat{
    min-height:112px;
    padding:17px 18px;
    border-radius:16px;
    background:rgba(255,255,255,.96);
    box-shadow:0 8px 20px rgba(77,39,15,.055);
}
.stat::before{width:auto;height:3px;right:0;bottom:auto;background:#f97316}
.stat::after{width:66px;height:66px;right:-24px;bottom:-30px;opacity:.5}
.stat:hover{transform:translateY(-3px);box-shadow:0 12px 24px rgba(77,39,15,.09)}
.stat-icon{width:35px;height:35px;border-radius:10px;font-size:14px}
.stat-label{font-size:10px;text-transform:uppercase;letter-spacing:.4px}
.stat b{font-size:29px}
.stat-title{margin-top:5px;font-size:11px}
.stat:nth-child(2)::before{background:#d59a27}
.stat:nth-child(2) .stat-icon{color:#a66f08;background:#fff7df;border-color:#f3dfaa}
.stat:nth-child(3)::before{background:#4a91b5}
.stat:nth-child(3) .stat-icon{color:#35799d;background:#edf8fc;border-color:#c9e5f0}
.stat:nth-child(4)::before{background:#3f986c}
.stat:nth-child(4) .stat-icon{color:#2e8058;background:#eef9f2;border-color:#cce8d6}
.stat:nth-child(2) b{color:#bd8519}
.stat:nth-child(3) b{color:#3f83a4}
.stat:nth-child(4) b{color:#33855c}
.reports-section{
    padding:0;
    overflow:hidden;
    border:1px solid #f1e4da;
    border-radius:18px;
    background:rgba(255,255,255,.97);
    box-shadow:0 12px 30px rgba(77,39,15,.06);
}
.section-header{
    margin:0;
    padding:19px 22px;
    border-bottom:1px solid #f1e4da;
}
.section-header h3{margin-bottom:4px;font-size:19px}
.section-header p{font-size:11px}
.report-count{padding:7px 10px;font-size:10px}
.table-wrap{border:0;border-radius:0}
table{min-width:700px}
thead{background:#fff7f1}
th{padding:13px 16px;font-size:10px;letter-spacing:.5px}
td{padding:14px 16px;font-size:12px}
tbody tr:hover{background:#fffaf6}
.ticket{font-size:12px}
.category,.status{padding:5px 8px;font-size:10px}
.status-pending{color:#996a0b;background:#fff8e5;border-color:#f1dfaa}
.status-progress{color:#327493;background:#eff8fc;border-color:#cde7f0}
.status-resolved{color:#2e8058;background:#eff9f2;border-color:#cce8d6}
.status::before{content:none}
.empty{margin:18px;padding:38px 18px;background:#fffaf6}
.empty .btn{width:auto;margin-top:10px}
.footer{padding-top:18px}
@media(max-width:1000px){
    .dashboard-head{padding:34px}
    .dashboard-head::after{right:9%;width:96px;height:96px}
    .stats{grid-template-columns:repeat(2,minmax(0,1fr))}
}
@media(max-width:700px){
    .nav{gap:10px;padding:12px 18px}
    .nav-links{gap:5px}
    .nav-links a{padding:8px 10px}
    .container{padding:24px 14px 44px}
    .dashboard-head{align-items:flex-start;flex-direction:column;gap:20px;min-height:300px;padding:28px 22px}
    .dashboard-head h2{font-size:34px}
    .dashboard-head .btn{width:100%}
    .dashboard-head::after{right:8%;top:auto;bottom:22px;width:72px;height:72px;opacity:.4}
    .stats{grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}
    .stat{min-height:104px;padding:14px}
    .reports-section{border-radius:15px}
    .section-header{padding:16px;flex-wrap:wrap}
    .empty{margin:12px}
}
@media(max-width:430px){
    .nav{align-items:flex-start}
    .nav-links{width:100%;justify-content:space-between}
    .nav-links a{padding:8px;font-size:11px}
    .stats{grid-template-columns:1fr 1fr}
    .stat b{font-size:25px}
}
@media(prefers-reduced-motion:reduce){
    *,*::before,*::after{scroll-behavior:auto!important;animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important}
}

body{
    background:
        radial-gradient(circle at 8% 8%,rgba(255,106,0,.13),transparent 24%),
        radial-gradient(circle at 92% 22%,rgba(255,154,82,.14),transparent 25%),
        linear-gradient(180deg,#fffdfb 0%,#fff7f0 48%,#fffaf6 100%);
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
.nav{padding:14px 5.5%;background:rgba(255,255,255,.84);border-bottom:1px solid rgba(242,220,203,.9);box-shadow:0 10px 35px rgba(69,34,12,.08)}
.brand-icon{width:48px;height:48px;flex-basis:48px;border-radius:15px;background:linear-gradient(145deg,#ff8a3d,#e65100);box-shadow:0 7px 0 #c84400,0 16px 28px rgba(255,106,0,.28);transform:rotate(-5deg) translateY(-1px)}
.brand:hover .brand-icon{transform:rotate(5deg) translateY(-3px) scale(1.04)}
.nav-links a{min-height:42px;padding:10px 15px;border:1px solid transparent;border-radius:12px;background:rgba(255,255,255,.96);box-shadow:0 5px 0 #f0dfd2;font-size:12px}
.nav-links a:hover{transform:translateY(-3px);box-shadow:0 8px 16px rgba(255,106,0,.12),0 3px 0 #f0dfd2}
.nav-links .nav-primary{background:linear-gradient(145deg,#ff7a1a,#e65100);box-shadow:0 5px 0 #c84400,0 12px 25px rgba(255,106,0,.18)}
.container{max-width:1540px;padding:42px 5.5% 58px}
.dashboard-head{
    min-height:335px;
    padding:42px;
    border:1px solid #f1e4da;
    border-radius:23px;
    background:radial-gradient(circle at 90% 15%,rgba(255,106,0,.10),transparent 25%),linear-gradient(135deg,#fff 0%,#fffaf6 62%,#fff2e7 100%);
    box-shadow:0 18px 42px rgba(80,40,15,.09);
    transform-style:preserve-3d;
    isolation:isolate;
}
.dashboard-head::before{width:310px;height:310px;right:-95px;top:-185px;background:rgba(255,106,0,.08)}
.dashboard-head::after{
    display:block;
    content:"";
    position:absolute;
    right:13%;
    top:50%;
    width:144px;
    height:144px;
    border-radius:50%;
    background:radial-gradient(circle at 30% 25%,#fff 0 8%,#ffb36e 18%,#ff7a1a 54%,#e65300 100%);
    box-shadow:inset -16px -18px 28px rgba(143,48,0,.12),0 18px 38px rgba(255,106,0,.12);
    transform:translateY(-50%);
    animation:studentDashboardFloat 5s ease-in-out infinite;
    opacity:.22;
    z-index:0;
}
@keyframes studentDashboardFloat{0%,100%{translate:0 0;rotate:0deg}50%{translate:0 -10px;rotate:2deg}}
.hero-content{max-width:720px;transform:translateZ(20px)}
.hero-badge{box-shadow:0 5px 0 #f7dcc8,0 10px 25px rgba(255,106,0,.08)}
.hero-badge::before{content:none}
.dashboard-head h2{font:800 54px/1.06 "Plus Jakarta Sans",Inter,Arial,sans-serif;letter-spacing:0;text-shadow:none;color:#2f211a}
.dashboard-head h2 span{display:inline-block;background:linear-gradient(90deg,#ff6a00,#e65100);-webkit-background-clip:text;background-clip:text;color:transparent}
.dashboard-head .btn{min-width:228px;min-height:56px;padding:16px 24px;border-radius:11px;background:linear-gradient(135deg,#ff8a3d,#e85d0f);box-shadow:0 7px 15px rgba(249,115,22,.20);font-size:14px}
.dashboard-head .btn:hover{background:#c2410c;box-shadow:0 10px 21px rgba(249,115,22,.24)}
.dashboard-head > .btn{align-self:end;justify-self:end;margin:0 0 16px;z-index:2}
.stats{grid-template-columns:repeat(4,minmax(0,1fr));gap:15px;margin-bottom:24px}
.stat{min-height:142px;padding:22px 24px;border:1px solid #f1e4da;border-radius:16px;background:rgba(255,255,255,.96);box-shadow:0 12px 30px rgba(80,40,15,.07);transform-style:preserve-3d}
.stat::before{left:0;top:0;right:0;bottom:auto;width:100%;height:4px;background:linear-gradient(90deg,#ff6a00,#e65100)}
.stat::after{width:74px;height:74px;right:-25px;bottom:-27px;background:rgba(255,106,0,.06)}
.stat:hover{transform:translateY(-4px);box-shadow:0 22px 42px rgba(89,43,13,.13),0 5px 0 rgba(230,81,0,.08)}
.stat-icon{display:none}
.stat-top{justify-content:flex-start}
.stat-label{font-size:11px;color:#7d6e64;letter-spacing:.5px;text-transform:uppercase}
.stat b{display:block;margin-top:14px;font-size:42px;line-height:1;color:#24150d}
.stat-title{display:none}
.stat:nth-child(2)::before{background:linear-gradient(90deg,#ffb13b,#d59a27)}
.stat:nth-child(3)::before{background:linear-gradient(90deg,#64a9c7,#3d809f)}
.stat:nth-child(4)::before{background:linear-gradient(90deg,#54a879,#34875d)}
.stat:nth-child(2) b{color:#bd8519}
.stat:nth-child(3) b{color:#3f83a4}
.stat:nth-child(4) b{color:#33855c}
.reports-section{border-color:#f1d9c5;box-shadow:0 18px 45px rgba(89,43,13,.10);backdrop-filter:blur(10px)}
.reports-section::before{content:"";position:absolute;width:190px;height:190px;right:-95px;top:-95px;border-radius:50%;background:rgba(255,106,0,.045);pointer-events:none}
.section-header,.table-wrap{position:relative;z-index:1}
.section-header h3{font-size:21px}
.table-wrap{border-radius:0}
thead{background:linear-gradient(90deg,#fff4e9,#fffdfa,#fff4e9)}
th{padding:15px 17px}
td{padding:16px 17px}
.status::before{content:none}
.empty-icon{display:none}
@media(max-width:1000px){.dashboard-head{padding:34px}.dashboard-head::after{right:9%;width:96px;height:96px}}
@media(max-width:700px){
    .container{padding:26px 18px 46px}
    .dashboard-head{min-height:300px;padding:28px 23px;align-items:flex-start;justify-content:center}
    .dashboard-head h2{font-size:36px}
    .dashboard-head::after{right:8%;top:auto;bottom:22px;width:72px;height:72px;opacity:.4}
    .dashboard-head .btn{width:100%;min-width:0;align-self:stretch;justify-self:stretch;margin:0}
    .stat{min-height:124px;padding:18px}
}
@media(max-width:430px){.nav{padding:12px 18px}.brand-icon{width:45px;height:45px;flex-basis:45px}.nav-links a{padding:8px 9px;font-size:11px}.stat{padding:15px}.stat b{font-size:34px}}


/* 3D student dashboard visual */
.student-3d-wrap{
    position:absolute;right:2%;top:8px;width:360px;height:260px;z-index:1;
    pointer-events:none;filter:drop-shadow(0 18px 28px rgba(234,88,12,.10));
}
#student-3d-scene{width:100%;height:100%;display:block}
.dashboard-head .hero-content,.dashboard-head .btn{position:relative;z-index:6}
@media(max-width:900px){.student-3d-wrap{right:-55px;opacity:.38;width:300px}.dashboard-head{padding-right:28px}}

/* Home-page ambient background, extended across the full dashboard. */
body{
    position:relative;
    background:
        radial-gradient(circle at 10% 15%,rgba(249,115,22,.18),transparent 24%),
        radial-gradient(circle at 90% 18%,rgba(234,88,12,.14),transparent 24%),
        linear-gradient(135deg,#fffefb 0%,#fffaf3 32%,#fff4e7 66%,#ffe9d1 100%);
    overflow-x:hidden;
}
body::before{
    content:"";position:fixed;inset:-20%;z-index:-2;pointer-events:none;
    background:repeating-radial-gradient(circle at 50% 50%,rgba(234,88,12,.025) 0 2px,transparent 2px 18px);
    opacity:.9;
}
body::after{
    content:"";position:fixed;inset:0;z-index:-1;pointer-events:none;
    background:linear-gradient(90deg,transparent 0%,rgba(255,255,255,.35) 48%,transparent 100%);
    mix-blend-mode:soft-light;
}
.page-background{
    position:fixed;inset:0;z-index:-1;pointer-events:none;overflow:hidden;
}
.page-background::before,.page-background::after{
    content:"";position:absolute;border-radius:50%;filter:blur(10px);
}
.page-background::before{
    width:780px;height:780px;left:-360px;top:-220px;
    background:radial-gradient(circle,rgba(249,115,22,.22) 0%,rgba(249,115,22,.12) 28%,rgba(249,115,22,.04) 52%,transparent 72%);
    animation:ambientFloat 12s ease-in-out infinite alternate;
}
.page-background::after{
    width:900px;height:900px;right:-430px;top:30px;
    background:radial-gradient(circle,rgba(234,88,12,.18) 0%,rgba(249,115,22,.08) 32%,transparent 72%);
    animation:ambientFloat2 14s ease-in-out infinite alternate;
}
.page-grid{
    position:fixed;inset:0;z-index:-1;pointer-events:none;
    background-image:linear-gradient(rgba(154,52,18,.055) 1px,transparent 1px),linear-gradient(90deg,rgba(154,52,18,.055) 1px,transparent 1px);
    background-size:42px 42px;
    mask-image:linear-gradient(to bottom,rgba(0,0,0,.8),transparent 90%);
    opacity:.45;
}
.page-dots{
    position:fixed;inset:0;z-index:-1;pointer-events:none;
    background-image:radial-gradient(rgba(154,52,18,.25) 1px,transparent 1px);
    background-size:20px 20px;
    mask-image:radial-gradient(ellipse at center,black 0%,transparent 78%);
    opacity:.16;
}
@keyframes ambientFloat{from{transform:translate3d(0,0,0) scale(1)}to{transform:translate3d(70px,45px,0) scale(1.08)}}
@keyframes ambientFloat2{from{transform:translate3d(0,0,0) scale(1)}to{transform:translate3d(-65px,35px,0) scale(1.1)}}
.container{z-index:1;isolation:isolate}
.dashboard-head{
    min-height:365px;padding:46px;
    border-color:rgba(154,52,18,.12);border-radius:32px;
    background:radial-gradient(circle at 78% 38%,rgba(249,115,22,.17),transparent 31%),linear-gradient(135deg,rgba(255,255,255,.94),rgba(255,250,243,.88) 48%,rgba(255,233,209,.94));
    box-shadow:0 25px 58px rgba(120,53,15,.15),0 9px 0 rgba(194,65,12,.08),inset 0 1px rgba(255,255,255,.95);
    transform-style:preserve-3d;backdrop-filter:blur(16px);
}
.dashboard-head::before{border:34px solid rgba(234,88,12,.045);background:transparent;box-shadow:0 0 0 25px rgba(234,88,12,.025),0 0 0 66px rgba(234,88,12,.018)}
.dashboard-head::after{opacity:.28;filter:drop-shadow(0 20px 24px rgba(120,53,15,.16))}
.hero-content{transform:translateZ(28px)}
.student-3d-wrap{right:1%;top:4px;transform:translateZ(42px) rotateY(-7deg);filter:drop-shadow(0 28px 30px rgba(174,69,11,.22))}
.dashboard-head .hero-badge{background:rgba(255,255,255,.74);border-color:rgba(154,52,18,.14);box-shadow:0 6px 0 rgba(194,65,12,.10),0 12px 26px rgba(120,53,15,.08)}
.dashboard-head h2{color:#2b1710;text-shadow:0 5px 22px rgba(120,53,15,.08)}
.dashboard-head h2 span{background:linear-gradient(90deg,#f97316,#c2410c);-webkit-background-clip:text;background-clip:text;color:transparent}
.dashboard-head p{color:#8a6d60}
.dashboard-head .btn{transform:translateZ(35px);background:linear-gradient(135deg,#f97316,#c2410c);box-shadow:0 8px 0 #9a3412,0 17px 30px rgba(120,53,15,.22)}
.dashboard-head .btn:hover{transform:translateY(-3px) translateZ(35px)}
.resolution-card{
    border-color:rgba(241,223,209,.95);border-radius:20px;
    background:linear-gradient(145deg,rgba(255,255,255,.95),rgba(255,247,239,.92));
    box-shadow:0 13px 0 rgba(230,185,148,.13),0 20px 34px rgba(77,39,15,.08),inset 0 1px #fff;
    transform:translateZ(12px);transform-style:preserve-3d;
}
.resolution-ring{box-shadow:0 8px 16px rgba(157,82,26,.13),inset 0 2px 5px rgba(255,255,255,.8)}
.resolution-ring::before{box-shadow:inset 0 2px 8px rgba(99,53,20,.08)}
.stats{perspective:1100px}
.stat{
    position:relative;overflow:hidden;min-height:142px;padding:22px 23px;
    border:1px solid rgba(241,223,209,.96);border-radius:19px;
    background:linear-gradient(145deg,rgba(255,255,255,.98),rgba(255,250,245,.94));
    box-shadow:0 9px 0 rgba(230,185,148,.14),0 18px 30px rgba(77,39,15,.07),inset 0 1px #fff;
    transform-style:preserve-3d;transition:transform .25s ease,box-shadow .25s ease;
}
.stat:hover{
    transform:translateY(-7px) rotateX(3deg) rotateY(-1.2deg);
    box-shadow:0 13px 0 rgba(230,185,148,.12),0 27px 42px rgba(77,39,15,.13),inset 0 1px #fff;
}
.stat::before{height:4px;background:linear-gradient(90deg,#ff6a00,#ffb16b)}
.stat::after{width:100px;height:100px;right:-40px;bottom:-45px;border:1px solid rgba(255,106,0,.10);border-radius:50%;box-shadow:0 0 0 13px rgba(255,106,0,.025);opacity:1}
.reports-section{
    border-color:rgba(241,217,197,.97);border-radius:22px;
    background:rgba(255,255,255,.92);
    box-shadow:0 12px 0 rgba(230,185,148,.13),0 24px 42px rgba(89,43,13,.09),inset 0 1px #fff;
    transform-style:preserve-3d;backdrop-filter:blur(12px);
}
.section-header{background:linear-gradient(100deg,rgba(255,255,255,.96),rgba(255,247,239,.92))}
.table-wrap{background:rgba(255,255,255,.92)}
tbody tr{transition:background .18s ease,transform .18s ease}
tbody tr:hover{transform:translateZ(5px);background:#fff5eb}
.report-count{box-shadow:0 4px 0 rgba(244,182,136,.18),0 8px 16px rgba(255,106,0,.06)}
@media(max-width:900px){
    .page-grid{background-size:34px 34px}
    .dashboard-head{min-height:345px}
}
@media(max-width:700px){
    .page-grid{background-size:30px 30px}
    .dashboard-head{min-height:330px;padding:28px 22px;border-radius:24px}
    .student-3d-wrap{transform:translateZ(20px) scale(.88);right:-46px;top:32px}
    .stat{box-shadow:0 7px 0 rgba(230,185,148,.14),0 13px 22px rgba(77,39,15,.07),inset 0 1px #fff}
}
</style>

</head>


<body>

<div class="page-background" aria-hidden="true"></div>
<div class="page-grid" aria-hidden="true"></div>
<div class="page-dots" aria-hidden="true"></div>


<!-- =====================================================
     NAVBAR
===================================================== -->

<nav class="nav">


<a
    class="brand"
    href="../index.php"
>

<span class="brand-icon">
U
</span>

UniFlow

</a>


<div class="nav-links">


<a href="report_issue.php">
New Report
</a>


<a href="my_reports.php">
My Reports
</a>


<a
    class="nav-primary"
    href="logout.php"
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

<section class="dashboard-head">

<div class="student-3d-wrap" aria-hidden="true">
    <canvas id="student-3d-scene"></canvas>
</div>

<div class="hero-content">


<div class="hero-badge">
Student Services
</div>


<h2>

Welcome,

<span>
<?= e($_SESSION["student_name"] ?? "Student") ?>
</span>

</h2>


<p>

Send an issue to the right campus department with the details they need to help.

</p>


</div>


<a
    class="btn"
    href="report_issue.php"
>

Create New Report

</a>


</section>



<!-- =====================================================
     STATISTICS
===================================================== -->

<section class="stats">


<?php foreach (
    $counts as $name => $number
): ?>


<div class="stat">


<div class="stat-label">

<?= e($name) ?>

</div>

<b>
<?= e($number) ?>
</b>


</div>


<?php endforeach; ?>


</section>



<!-- =====================================================
     REPORTS
===================================================== -->

<section class="reports-section">


<div class="section-header">


<div>

<h3>
Recent Reports
</h3>


<p>
Your latest 8 submitted reports.
</p>

</div>


<div class="report-count">

<?= $reports->num_rows ?>

Recent Reports

</div>


</div>



<?php if ($reports->num_rows > 0): ?>


<div class="table-wrap">


<table>


<thead>

<tr>

<th>
Ticket
</th>

<th>
Category
</th>

<th>
Title
</th>

<th>
Status
</th>

</tr>

</thead>


<tbody>


<?php while (
    $report = $reports->fetch_assoc()
): ?>


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


<tr>


<!-- TICKET -->

<td>

<div class="ticket">

<?= e(
    $report["ticket_id"]
) ?>

</div>


<?php if (
    !empty(
        $report["created_at"]
    )
): ?>

<div class="ticket-sub">

<?= e(
    date(
        "d M Y",
        strtotime(
            $report["created_at"]
        )
    )
) ?>

</div>

<?php endif; ?>

</td>



<!-- CATEGORY -->

<td>

<span class="category">

<?= e(ucfirst($report["category"])) ?>

</span>

</td>



<!-- TITLE -->

<td>

<?= e(
    $report["title"]
) ?>

</td>



<!-- STATUS -->

<td>

<span
    class="status <?= $status_class ?>"
>

<?= e($status) ?>

</span>

</td>


</tr>


<?php endwhile; ?>


</tbody>


</table>


</div>


<?php else: ?>


<div class="empty">


<h4>
No Reports Yet
</h4>


<p>

You haven't submitted any reports yet.
Create your first report to get started.

</p>


<br>


<a
    class="btn"
    href="report_issue.php"
>

Create Your First Report

</a>


</div>


<?php endif; ?>


</section>



<!-- =====================================================
     FOOTER
===================================================== -->

<footer class="footer">

UniFlow Student Portal

</footer>


</main>



<script type="module">
import * as THREE from 'https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js';
const canvas=document.getElementById('student-3d-scene');
if(canvas){
 const scene=new THREE.Scene();
 const camera=new THREE.PerspectiveCamera(34,1,.1,30); camera.position.set(0,0,6);
 const renderer=new THREE.WebGLRenderer({canvas,alpha:true,antialias:true});
 renderer.setPixelRatio(Math.min(devicePixelRatio||1,2)); renderer.setClearColor(0,0);
 scene.add(new THREE.AmbientLight(0xffffff,1.45));
 const light=new THREE.PointLight(0xff7a18,15,12); light.position.set(3,2,4); scene.add(light);
 const group=new THREE.Group(); scene.add(group);
 const core=new THREE.Mesh(new THREE.IcosahedronGeometry(1.05,1),new THREE.MeshStandardMaterial({color:0xf97316,roughness:.28,metalness:.18,flatShading:true})); group.add(core);
 group.add(new THREE.LineSegments(new THREE.EdgesGeometry(new THREE.IcosahedronGeometry(1.08,1)),new THREE.LineBasicMaterial({color:0xffdfc5,transparent:true,opacity:.8})));
 const ring=new THREE.Mesh(new THREE.TorusGeometry(1.55,.018,8,120),new THREE.MeshBasicMaterial({color:0xff9a52,transparent:true,opacity:.72})); ring.rotation.x=.9; ring.rotation.z=.25; group.add(ring);
 const ring2=new THREE.Mesh(new THREE.TorusGeometry(1.82,.012,8,120),new THREE.MeshBasicMaterial({color:0xffc49d,transparent:true,opacity:.48})); ring2.rotation.y=.7; group.add(ring2);
 const nodes=[]; const nodeMat=new THREE.MeshStandardMaterial({color:0xffead8,emissive:0x8a3300,emissiveIntensity:.5});
 [[1.45,.45,.2],[-1.35,-.55,.1],[.25,1.48,-.1],[-.45,-1.45,.15]].forEach(p=>{const n=new THREE.Mesh(new THREE.SphereGeometry(.09,18,14),nodeMat);n.position.set(...p);group.add(n);nodes.push(n)});
 function resize(){const r=canvas.parentElement.getBoundingClientRect();if(!r.width||!r.height)return;renderer.setSize(r.width,r.height,false);camera.aspect=r.width/r.height;camera.updateProjectionMatrix()}
 new ResizeObserver(resize).observe(canvas.parentElement); resize();
 const reduce=matchMedia('(prefers-reduced-motion: reduce)').matches;
 function animate(t){if(!reduce){group.rotation.y+=.004;group.rotation.x=Math.sin(t*.0005)*.08;ring.rotation.z+=.0018;ring2.rotation.y-=.0012;nodes.forEach((n,i)=>n.position.y+=Math.sin(t*.001+i)*.00025)}renderer.render(scene,camera);requestAnimationFrame(animate)} requestAnimationFrame(animate);
}
</script>
</body>

</html>
