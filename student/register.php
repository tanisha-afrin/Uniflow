<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/email.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit();
}

$currentAdminId = (int) $_SESSION['admin_id'];

$stmt = $conn->prepare(
    'SELECT admin_id, name, email, role, admin_type, is_active, must_change_password
     FROM admins
     WHERE admin_id = ?
     LIMIT 1'
);

$stmt->bind_param('i', $currentAdminId);
$stmt->execute();

$currentAdmin = $stmt->get_result()->fetch_assoc();

$stmt->close();

if ($currentAdmin && (int)$currentAdmin['must_change_password'] === 1) {
    header('Location: ../change_password.php');
    exit();
}

if (!$currentAdmin || ($currentAdmin['admin_type'] ?? 'admin') !== 'main_admin' || (int)$currentAdmin['is_active'] !== 1) {
    header('Location: ../index.php');
    exit();
}


/* =========================================================
   STUDENT TABLE SUPPORT
========================================================= */

function ensureStudentSchema(mysqli $conn): void
{
    $columns = [
        'personal_email' => 'VARCHAR(150) NULL',
        'password_change_token_hash' => 'VARCHAR(64) NULL',
        'password_change_expires_at' => 'DATETIME NULL'
    ];

    foreach ($columns as $name => $definition) {
        $safeName = $conn->real_escape_string($name);
        $check = $conn->query("SHOW COLUMNS FROM students LIKE '{$safeName}'");
        if ($check && $check->num_rows === 0) {
            $conn->query("ALTER TABLE students ADD COLUMN {$name} {$definition}");
        }
    }
}

ensureStudentSchema($conn);


/* =========================================================
   VARIABLES
========================================================= */

$error = '';
$success = '';
$editStudent = null;
$formPasswordMode = 'automated';
$studentAccountCreationEnabled = true;
$settingStmt = $conn->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1');
$settingKey = 'student_account_creation_enabled';
if ($settingStmt) {
    $settingStmt->bind_param('s', $settingKey);
    $settingStmt->execute();
    $setting = $settingStmt->get_result()->fetch_assoc();
    $settingStmt->close();
    $studentAccountCreationEnabled = ($setting['setting_value'] ?? '1') === '1';
}


/* =========================================================
   REMOVE STUDENT
========================================================= */

if (isset($_GET['delete'])) {

    $deleteId = (int) $_GET['delete'];

    if ($deleteId > 0) {

        $stmt = $conn->prepare(
            'DELETE FROM students
             WHERE student_id = ?
             LIMIT 1'
        );

        $stmt->bind_param('i', $deleteId);

        if ($stmt->execute()) {

            $stmt->close();

            header('Location: register.php?removed=1');

            exit();
        }

        $stmt->close();
    }

    $error = 'Could not remove the student account.';
}


if (isset($_GET['removed'])) {

    $success =
        'Student account removed successfully.';
}


/* =========================================================
   EDIT STUDENT
========================================================= */

if (isset($_GET['edit'])) {

    $editId = (int) $_GET['edit'];

    if ($editId > 0) {

        $stmt = $conn->prepare(
            'SELECT
                student_id,
                student_code,
                name,
                email,
                personal_email
             FROM students
             WHERE student_id = ?
             LIMIT 1'
        );

        $stmt->bind_param('i', $editId);

        $stmt->execute();

        $editStudent =
            $stmt->get_result()->fetch_assoc();

        $stmt->close();
    }
}


/* =========================================================
   ADD / UPDATE STUDENT
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action =
        $_POST['action'] ?? 'create';

    $studentId =
        (int) ($_POST['student_id'] ?? 0);

    $name =
        trim($_POST['name'] ?? '');

    $studentCode =
        trim($_POST['student_code'] ?? '');

    $universityEmail =
        strtolower(
            trim($_POST['university_email'] ?? '')
        );

    $personalEmail =
        strtolower(
            trim($_POST['personal_email'] ?? '')
        );

    $passwordMode =
        ($_POST['password_mode'] ?? 'automated')
        === 'manual'
        ? 'manual'
        : 'automated';
    $formPasswordMode = $passwordMode;

    $manualPassword =
        $_POST['manual_password'] ?? '';


    /* Validation */

    if ($name === '') {

        $error =
            'Please enter the student full name.';

    } elseif ($studentCode === '') {

        $error =
            'Please enter the Student ID.';

    } elseif (
        !filter_var(
            $universityEmail,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $error =
            'Please enter a valid University Email.';

    } elseif (
        !filter_var(
            $personalEmail,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $error =
            'Please enter a valid Personal Email.';

    } elseif (
        strcasecmp(
            $universityEmail,
            $personalEmail
        ) === 0
    ) {

        $error =
            'University Email and Personal Email should be different.';

    } elseif (
        $action === 'create'
        && $passwordMode === 'manual'
        && strlen($manualPassword) < 8
    ) {

        $error =
            'Manual password must be at least 8 characters.';

    } elseif ($action === 'create' && !$studentAccountCreationEnabled) {

        $error =
            'Student account creation is currently disabled by the System Administrator.';
    }


    /* =====================================================
       UPDATE
    ===================================================== */

    if (
        $error === ''
        && $action === 'update'
    ) {

        if ($studentId <= 0) {

            $error =
                'Invalid student account.';

        } else {

            $stmt = $conn->prepare(
                'SELECT student_id
                 FROM students
                 WHERE
                    (email = ? OR student_code = ?)
                    AND student_id <> ?
                 LIMIT 1'
            );

            $stmt->bind_param(
                'ssi',
                $universityEmail,
                $studentCode,
                $studentId
            );

            $stmt->execute();

            $duplicate =
                $stmt->get_result()->fetch_assoc();

            $stmt->close();


            if ($duplicate) {

                $error =
                    'Another student already uses this University Email or Student ID.';

            } else {

                $stmt = $conn->prepare(
                    'UPDATE students
                     SET
                        name = ?,
                        email = ?,
                        personal_email = ?,
                        student_code = ?
                     WHERE student_id = ?'
                );

                $stmt->bind_param(
                    'ssssi',
                    $name,
                    $universityEmail,
                    $personalEmail,
                    $studentCode,
                    $studentId
                );

                if ($stmt->execute()) {

                    $stmt->close();

                    header(
                        'Location: register.php?updated=1'
                    );

                    exit();
                }

                $stmt->close();

                $error =
                    'Could not update the student account.';
            }
        }
    }


    /* =====================================================
       CREATE
    ===================================================== */

    if (
        $error === ''
        && $action === 'create'
    ) {

        $stmt = $conn->prepare(
            'SELECT student_id
             FROM students
             WHERE email = ?
                OR student_code = ?
             LIMIT 1'
        );

        $stmt->bind_param(
            'ss',
            $universityEmail,
            $studentCode
        );

        $stmt->execute();

        $duplicate =
            $stmt->get_result()->fetch_assoc();

        $stmt->close();


        if ($duplicate) {

            $error =
                'This University Email or Student ID is already registered.';

        } else {

            if ($passwordMode === 'manual') {

                $initialPassword =
                    $manualPassword;

            } else {

                $initialPassword =
                    rtrim(
                        strtr(
                            base64_encode(
                                random_bytes(12)
                            ),
                            '+/',
                            '-_'
                        ),
                        '='
                    );
            }


            $passwordHash =
                password_hash(
                    $initialPassword,
                    PASSWORD_DEFAULT
                );


            $passwordChangeToken = bin2hex(random_bytes(32));
            $passwordChangeTokenHash = hash('sha256', $passwordChangeToken);
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare(
                    'INSERT INTO students
                    (student_code, name, email, personal_email, password,
                     password_change_token_hash, password_change_expires_at)
                    VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))'
                );
                $stmt->bind_param(
                    'ssssss',
                    $studentCode,
                    $name,
                    $universityEmail,
                    $personalEmail,
                    $passwordHash,
                    $passwordChangeTokenHash
                );
                if (!$stmt->execute()) {
                    throw new RuntimeException('Could not create the student account.');
                }
                $stmt->close();

                if (!sendStudentCredentialsEmail(
                    $personalEmail,
                    $name,
                    $studentCode,
                    $universityEmail,
                    $initialPassword,
                    uniflowPasswordChangeUrl($passwordChangeToken)
                )) {
                    throw new RuntimeException('Could not send student login details.');
                }

                $conn->commit();
                header('Location: register.php?created=1');
                exit();
            } catch (Throwable $e) {
                $conn->rollback();
                error_log('UniFlow student account creation/email failed: ' . $e->getMessage());
                $error = 'Could not create the student account or send its email. Check the UniFlow sender email configuration and try again.';
            }
        }
    }
}


/* =========================================================
   SUCCESS MESSAGES
========================================================= */

if (isset($_GET['created'])) {

    $success =
        'Student account created and login details were sent to the Personal Email.';
}

if (isset($_GET['updated'])) {

    $success =
        'Student account updated successfully.';
}


/* =========================================================
   GET ALL STUDENTS
========================================================= */

$students = [];

$result = $conn->query(
    'SELECT
        student_id,
        student_code,
        name,
        email,
        personal_email,
        created_at
     FROM students
     ORDER BY student_id DESC'
);

if ($result) {

    while ($row = $result->fetch_assoc()) {

        $students[] = $row;
    }
}


/* =========================================================
   INITIALS
========================================================= */

function studentInitials(string $name): string
{
    $parts =
        preg_split(
            '/\s+/',
            trim($name)
        );

    $out = '';

    foreach (
        array_slice($parts, 0, 2)
        as $part
    ) {

        $out .= strtoupper(
            substr($part, 0, 1)
        );
    }

    return $out ?: 'S';
}


$isEditing =
    is_array($editStudent);

$showStudentForm =
    $isEditing || $error !== '';

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
Student Management | UniFlow
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

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}


:root {

    --orange: #ff6a00;
    --orange-dark: #e95700;

    --orange-light: #fff3e8;
    --orange-soft: #ffe4cf;

    --white: #ffffff;

    --ink: #2f211a;
    --muted: #8a7467;

    --line: #f1e4da;

    --shadow:
        0 20px 55px rgba(255,106,0,.10);

    --shadow-sm:
        0 8px 25px rgba(255,106,0,.08);
}


html {
    scroll-behavior: smooth;
}


body {

    min-height: 100vh;

    font-family:
        Inter,
        Arial,
        sans-serif;

    color: var(--ink);

    background:
        radial-gradient(
            circle at 8% 12%,
            rgba(255,138,61,.15),
            transparent 24%
        ),
        radial-gradient(
            circle at 92% 18%,
            rgba(255,106,0,.11),
            transparent 26%
        ),
        linear-gradient(
            135deg,
            #fff8f2 0%,
            #fff 48%,
            #fff6ed 100%
        );

    overflow-x: hidden;
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

.orb,
.cube,
.ring {
    position: fixed;
    pointer-events: none;
    z-index: 0;
}

.orb {
    border-radius: 50%;
    background: radial-gradient(circle at 30% 25%, #fff 0 8%, #ffb36e 18%, #ff7a1a 54%, #e65300 100%);
    box-shadow: inset -18px -22px 35px rgba(143,48,0,.20), 0 35px 70px rgba(255,106,0,.15);
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
    box-shadow: 15px 18px 0 rgba(197,69,0,.12), 0 25px 55px rgba(255,106,0,.18);
    animation: float2 8s ease-in-out infinite;
}

@keyframes float1 { 50% { transform: translateY(-18px) rotate(8deg); } }
@keyframes float2 { 50% { transform: translateY(14px) rotate(7deg); } }


a {
    text-decoration: none;
    color: inherit;
}


/* =====================================================
   NAVBAR
===================================================== */

.navbar {

    position:
        relative;

    z-index:
        2;

    min-height: 76px;

    padding:
        0 4%;

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    background:
        rgba(255,255,255,.94);

    backdrop-filter:
        blur(18px);

    border-bottom:
        1px solid rgba(255,106,0,.13);
}


.logo {

    display:flex;
    align-items:center;
    gap:11px;
    font:800 22px "Plus Jakarta Sans",sans-serif;
    color:var(--ink);
    white-space:nowrap;
}

.logo-icon {
    width:45px;
    height:45px;
    flex:0 0 45px;
    display:flex;
    align-items:center;
    justify-content:center;
    border-radius:14px;
    color:#fff;
    background:linear-gradient(145deg,#f97316,#ea580c);
    box-shadow:0 6px 0 #c2410c,0 12px 25px rgba(249,115,22,.22);
    font-weight:800;
    transform:rotate(-3deg);
}

.logo-word{color:var(--ink)}


.nav-right {

    display:
        flex;

    align-items:
        center;

    gap:
        9px;
}


.nav-button {

    min-height:
        41px;

    padding:
        9px 15px;

    border-radius:
        11px;

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    font-size:
        12px;

    font-weight:
        800;

    border:
        1px solid var(--line);

    background:
        #fff;
}


.nav-button.primary {

    color:
        #fff;

    background:
        linear-gradient(
            135deg,
            var(--orange),
            var(--orange-dark)
        );

    border-color:
        transparent;
}


/* =====================================================
   PAGE
===================================================== */

.page {

    position:
        relative;

    z-index:
        1;

    padding:
        48px 4% 70px;
}


.container {

    position:
        relative;

    max-width:
        1480px;

    margin:
        auto;
}


/* =====================================================
   HERO
===================================================== */

.hero {

    display:
        flex;

    align-items:
        flex-end;

    justify-content:
        space-between;

    gap:
        30px;

    margin-bottom:
        25px;
}


.badge {

    display:
        inline-flex;

    padding:
        7px 12px;

    border-radius:
        999px;

    color:
        var(--orange-dark);

    background:
        var(--orange-light);

    border:
        1px solid var(--orange-soft);

    font-size:
        10px;

    font-weight:
        800;

    letter-spacing:
        .8px;

    text-transform:
        uppercase;
}


.hero h1 {

    font:
        800 clamp(35px,4.5vw,56px)
        /1.04
        "Plus Jakarta Sans",
        sans-serif;

    letter-spacing:
        -2px;

    margin-top:
        13px;
}


.hero h1 span {
    color: var(--orange);
}


.hero p {

    margin-top:
        13px;

    max-width:
        690px;

    color:
        var(--muted);

    font-size:
        14px;

    line-height:
        1.75;
}


.add-button {

    display:
        inline-flex;

    align-items:
        center;

    padding:
        14px 20px;

    border-radius:
        13px;

    color:
        #fff;

    background:
        linear-gradient(
            135deg,
            var(--orange),
            var(--orange-dark)
        );

    font-size:
        13px;

    font-weight:
        800;

    box-shadow:
        0 10px 25px
        rgba(255,106,0,.22);
}


/* =====================================================
   NOTICE
===================================================== */

.notice {

    padding:
        17px 19px;

    margin-bottom:
        23px;

    background:
        rgba(255,255,255,.94);

    border:
        1px solid var(--line);

    border-left:
        4px solid var(--orange);

    border-radius:
        16px;

    box-shadow:
        var(--shadow-sm);
}


.notice strong {

    display:
        block;

    font-size:
        13px;

    color:
        var(--orange-dark);

    font-weight:
        800;
}


.notice p {

    margin-top:
        4px;

    color:
        var(--muted);

    font-size:
        12px;

    line-height:
        1.6;
}


/* =====================================================
   ALERT
===================================================== */

.alert {

    padding:
        14px 16px;

    border-radius:
        13px;

    margin-bottom:
        18px;

    font-size:
        12px;

    font-weight:
        700;
}


.alert.error {

    background:
        #fff1f1;

    border:
        1px solid #ffcaca;

    color:
        #c62828;
}


.alert.success {

    background:
        #effaf2;

    border:
        1px solid #c9ecd1;

    color:
        #19723b;
}


/* =====================================================
   STATS
===================================================== */

.stats {

    display:
        grid;

    grid-template-columns:
        repeat(2, minmax(0, 1fr));

    gap:
        15px;

    margin-bottom:
        24px;
}


.stat {

    padding:
        20px;

    border:
        1px solid var(--line);

    border-radius:
        18px;

    background:
        rgba(255,255,255,.96);

    box-shadow:
        var(--shadow-sm);
}


.stat-number {

    font:
        800 27px
        "Plus Jakarta Sans",
        sans-serif;
}


.stat-label {

    margin-top:
        3px;

    color:
        var(--muted);

    font-size:
        11px;

    font-weight:
        700;
}


/* =====================================================
   CARDS
===================================================== */

.form-card,
.table-card {

    overflow:
        hidden;

    background:
        rgba(255,255,255,.97);

    border:
        1px solid var(--line);

    border-radius:
        21px;

    box-shadow:
        var(--shadow);

    margin-bottom:
        24px;
}

.form-card[hidden] {
    display: none;
}


.card-head {

    padding:
        21px 23px;

    border-bottom:
        1px solid var(--line);

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;
}


.card-head h2 {

    font:
        800 19px
        "Plus Jakarta Sans",
        sans-serif;
}


.card-head p {

    margin-top:
        3px;

    color:
        var(--muted);

    font-size:
        11px;
}


.form-body {
    padding: 23px;
}


/* =====================================================
   FORM
===================================================== */

.grid {

    display:
        grid;

    grid-template-columns:
        1fr 1fr;

    gap:
        18px;
}


.field {

    display:
        flex;

    flex-direction:
        column;

    gap:
        7px;
}

.field[hidden] {
    display: none;
}


.field.full {
    grid-column: 1 / -1;
}


.field label {

    font-size:
        11px;

    font-weight:
        800;
}


.field input {

    width:
        100%;

    height:
        49px;

    padding:
        0 14px;

    border:
        1px solid #eadbd0;

    border-radius:
        12px;

    background:
        #fffdfb;

    color:
        var(--ink);

    outline:
        none;

    font:
        500 13px
        Inter;
}


.field input:focus {

    border-color:
        #ff9a55;

    box-shadow:
        0 0 0 4px
        rgba(249,115,22,.08);
}


.help {

    font-size:
        10px;

    color:
        #9a887d;
}


/* =====================================================
   PASSWORD OPTIONS
===================================================== */

.modes {

    display: inline-flex;
    flex-wrap: wrap;
    max-width: 100%;
    gap: 3px;
    padding: 4px;
    border: 1px solid var(--line);
    border-radius: 12px;
    background: #fff8f2;

}


.mode {

    min-height: 36px;
    padding: 7px 11px;
    border: 1px solid transparent;
    border-radius: 8px;

    display:
        flex;

    gap: 7px;

    align-items:
        center;

    cursor:
        pointer;
}


.mode:has(input:checked) {

    border-color: #f2c6a8;
    background: #fff;
    box-shadow: 0 2px 7px rgba(89,43,14,.07);
}


.mode input {

    width: 14px;
    height: 14px;
    margin: 0;
    accent-color:
        var(--orange);
}


.mode strong {

    font-size:
        12px;
}


.mode small {

    display: none;
}


/* =====================================================
   BUTTONS
===================================================== */

.form-actions {

    display:
        flex;

    justify-content:
        flex-end;

    gap:
        9px;

    margin-top:
        20px;

    padding-top:
        19px;

    border-top:
        1px solid var(--line);
}


.button {

    padding:
        11px 16px;

    border-radius:
        11px;

    font-size:
        11px;

    font-weight:
        800;

    border:
        1px solid var(--line);

    background:
        #fff;
}


.button.save {

    color:
        #fff;

    background:
        linear-gradient(
            135deg,
            var(--orange),
            var(--orange-dark)
        );

    border-color:
        transparent;
}


/* =====================================================
   TABLE
===================================================== */

.table-wrap {
    overflow-x: auto;
}


table {

    width:
        100%;

    min-width:
        980px;

    border-collapse:
        collapse;
}


th {

    padding:
        14px 17px;

    text-align:
        left;

    color:
        var(--orange-dark);

    background:
        linear-gradient(
            90deg,
            #fff5ea,
            #fff
        );

    font-size:
        10px;

    font-weight:
        800;

    text-transform:
        uppercase;

    letter-spacing:
        .7px;

    border-bottom:
        1px solid var(--line);
}


td {

    padding:
        16px 17px;

    color:
        #5e5049;

    font-size:
        12px;

    border-bottom:
        1px solid #f7eee7;

    vertical-align:
        middle;
}


tbody tr:hover td {
    background:
        #fffaf6;
}


.profile {

    display:
        flex;

    align-items:
        center;

    gap:
        11px;
}


.avatar {

    width:
        40px;

    height:
        40px;

    flex:
        0 0 40px;

    border-radius:
        12px;

    display:
        grid;

    place-items:
        center;

    color:
        #fff;

    font-size:
        11px;

    font-weight:
        800;

    background:
        linear-gradient(
            145deg,
            #ff9a55,
            var(--orange-dark)
        );
}


.name {

    font-weight:
        800;

    color:
        var(--ink);
}


.sub {

    margin-top:
        3px;

    color:
        #96867d;

    font-size:
        10px;
}


.actions {

    display:
        flex;

    gap:
        6px;
}


.action {

    padding:
        7px 10px;

    border-radius:
        9px;

    font-size:
        10px;

    font-weight:
        800;
}


.edit {

    color:
        #fff;

    background:
        var(--orange);
}


.remove {

    color:
        var(--orange-dark);

    border:
        1px solid var(--orange-soft);

    background:
        #fff;
}


.empty {

    text-align:
        center;

    padding:
        55px;

    color:
        var(--muted);
}


/* =====================================================
   MOBILE
===================================================== */

@media (max-width: 800px) {

    .hero {
        display: block;
    }

    .add-button {
        margin-top: 18px;
    }

    .grid,
    .stats {
        grid-template-columns: 1fr;
    }

    .field.full {
        grid-column: auto;
    }

    .navbar {
        padding: 13px 20px;
        gap: 10px;
        align-items: flex-start;
        flex-direction: column;
    }

    .nav-right {
        flex-wrap: wrap;
    }
}

</style>

<link rel="stylesheet" href="../css/buttons.css">
</head>


<body>

<div class="orb one" aria-hidden="true"></div>
<div class="orb two" aria-hidden="true"></div>
<div class="cube" aria-hidden="true"></div>
<div class="ring" aria-hidden="true"></div>

<nav class="navbar">

    <a
        class="logo"
        href="../system_admin/dashboard.php"
    >
        <span class="logo-icon" aria-hidden="true">U</span>
        <span class="logo-word">UniFlow</span>
    </a>


    <div class="nav-right">

        <a
            class="nav-button"
            href="../system_admin/dashboard.php"
        >
            Dashboard
        </a>


        <a
            class="nav-button primary"
            href="../system_admin/logout.php"
        >
            Logout
        </a>

    </div>

</nav>


<main class="page">

<div class="container">


<section class="hero">

    <div>

        <div class="badge">
            System Account Management
        </div>

        <h1>
            Student <span>Management</span>
        </h1>

        <p>
            Create and manage UniFlow student login accounts from
            university-provided student details. This is not student admission or enrollment.
        </p>

    </div>


    <button
        class="add-button"
        id="student-form-toggle"
        type="button"
        aria-controls="student-form"
        aria-expanded="<?= $showStudentForm ? 'true' : 'false' ?>"
    >
        <?= $showStudentForm ? 'Hide Student Form' : 'Create Student Account' ?>
    </button>

</section>


<div class="notice">

    <strong>
        System Account Management · Not Admissions
    </strong>

    <p>
        System Admin manages UniFlow login accounts using student identity details supplied by authorized university staff. Creating an account does not admit or enroll a student; university admission and academic records remain with the university. Login details are sent to the Personal Email entered on the form.
    </p>

</div>


<?php if ($error !== ''): ?>

<div class="alert error">
    <?= e($error) ?>
</div>

<?php endif; ?>


<?php if ($success !== ''): ?>

<div class="alert success">
    <?= e($success) ?>
</div>

<?php endif; ?>


<section class="stats">


    <div class="stat">

        <div class="stat-number">
            <?= count($students) ?>
        </div>

        <div class="stat-label">
            Total Students
        </div>

    </div>


    <div class="stat">

        <div class="stat-number">

            <?= count(
                array_filter(
                    $students,
                    fn($s) =>
                        !empty($s['personal_email'])
                )
            ) ?>

        </div>

        <div class="stat-label">
            Students With Delivery Email
        </div>

    </div>


</section>


<section
    class="form-card"
    id="student-form"
    <?= $showStudentForm ? '' : 'hidden' ?>
>

<script>
const studentForm = document.getElementById('student-form');
const studentFormToggle = document.getElementById('student-form-toggle');

studentFormToggle.addEventListener('click', () => {
    const shouldShowForm = studentForm.hidden;
    studentForm.hidden = !shouldShowForm;
    studentFormToggle.setAttribute('aria-expanded', String(shouldShowForm));
    studentFormToggle.textContent = shouldShowForm
        ? 'Hide Student Form'
        : 'Create Student Account';

    if (shouldShowForm) {
        studentForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
});
</script>


<div class="card-head">

    <div>

        <h2>

            <?= $isEditing
                ? 'Edit Student'
                : 'Create Student Account'
            ?>

        </h2>

        <p>

            <?= $isEditing
                ? 'Update the student account information below.'
                : 'Create a UniFlow login account from university-provided student details and send its login details to the Personal Email.'
            ?>

        </p>

    </div>

</div>


<div class="form-body">


<form method="post">


<input
    type="hidden"
    name="action"
    value="<?= $isEditing ? 'update' : 'create' ?>"
>


<?php if ($isEditing): ?>

<input
    type="hidden"
    name="student_id"
    value="<?= (int)$editStudent['student_id'] ?>"
>

<?php endif; ?>


<div class="grid">


<div class="field">

        <label>
Student Full Name
</label>

<input
    type="text"
    name="name"
    required
    value="<?= e($editStudent['name'] ?? '') ?>"
    placeholder="Enter student full name"
>

<span class="help">
Use the student's official name.
</span>

</div>


<div class="field">

<label>
Student ID
</label>

<input
    type="text"
    name="student_code"
    required
    value="<?= e($editStudent['student_code'] ?? '') ?>"
    placeholder="Enter student ID"
>

<span class="help">
This identifies the student account.
</span>

</div>


<div class="field">

<label>
University Email (Login Email)
</label>

<input
    type="email"
    name="university_email"
    required
    value="<?= e($editStudent['email'] ?? '') ?>"
    placeholder="student@university.edu"
>

<span class="help">
This exact email will be used to log in to UniFlow.
</span>

</div>


<div class="field">

<label>
Personal Email (Delivery Email)
</label>

<input
    type="email"
    name="personal_email"
    required
    value="<?= e($editStudent['personal_email'] ?? '') ?>"
    placeholder="student@gmail.com"
>

<span class="help">
The login details will be sent to this email.
</span>

</div>


<?php if (!$isEditing): ?>


<div class="field full">

<label>
Initial Password
</label>


<div class="modes">


<label class="mode">

<input
    type="radio"
    name="password_mode"
    value="automated"
    <?= $formPasswordMode === 'automated' ? 'checked' : '' ?>
>

<span>

<strong>
Automated
</strong>

<small>
UniFlow creates a strong password
and includes it in the email.
</small>

</span>

</label>


<label class="mode">

<input
    type="radio"
    name="password_mode"
    value="manual"
    <?= $formPasswordMode === 'manual' ? 'checked' : '' ?>
>

<span>

<strong>
Manual: Create your own
</strong>

<small>
Set the password that will be
sent to the student.
</small>

</span>

</label>


</div>

</div>


<div
    class="field full"
    id="student-manual-password-field"
    <?= $formPasswordMode === 'manual' ? '' : 'hidden' ?>
>

<label>
Manual Password
</label>

<input
    id="student-manual-password"
    type="password"
    name="manual_password"
    minlength="8"
    placeholder="Only required when Manual is selected"
    autocomplete="new-password"
    <?= $formPasswordMode === 'manual' ? 'required' : '' ?>
>

<span class="help">
Use at least 8 characters when Manual is selected.
</span>

</div>


<div class="field full">
    <span class="help">The login email includes the initial password and an optional, one-time password-change link. The student can use the link or keep the university-provided password.</span>
</div>


<?php endif; ?>


</div>


<div class="form-actions">


<a
    class="button"
    href="register.php"
>
    Cancel
</a>


<button
    class="button save"
    type="submit"
>

<?= $isEditing
    ? 'Save Student Account Changes'
    : 'Create Account & Send Login Details'
?>

</button>


</div>


</form>

</div>

</section>


<section class="table-card">


<div class="card-head">

    <div>

        <h2>
            Student Accounts
        </h2>

        <p>
            UniFlow login accounts for students.
        </p>

    </div>


    <div class="badge">

        <?= count($students) ?>
        Students

    </div>

</div>


<div class="table-wrap">


<?php if (!$students): ?>


<div class="empty">

    No student accounts have been created yet.

</div>


<?php else: ?>


<table>


<thead>

<tr>

<th>#</th>

<th>Student</th>

<th>Student ID</th>

<th>University Email</th>

<th>Personal Email</th>

<th>Created</th>

<th>Actions</th>

</tr>

</thead>


<tbody>


<?php foreach (
    $students
    as $i => $student
): ?>


<tr>


<td>
    <?= $i + 1 ?>
</td>


<td>

<div class="profile">

<div class="avatar">

<?= e(
    studentInitials(
        $student['name']
    )
) ?>

</div>


<div>

<div class="name">

<?= e(
    $student['name']
) ?>

</div>


<div class="sub">
Student Account
</div>

</div>

</div>

</td>


<td>

<?= e(
    $student['student_code']
) ?>

</td>


<td>

<?= e(
    $student['email']
) ?>

</td>


<td>

<?= e(
    $student['personal_email']
    ?: 'Not set'
) ?>

</td>


<td>

<?= e(
    date(
        'd M Y',
        strtotime(
            $student['created_at']
        )
    )
) ?>

</td>


<td>

<div class="actions">


<a
    class="action edit"
    href="register.php?edit=<?= (int)$student['student_id'] ?>#student-form"
>
    Edit
</a>


<a
    class="action remove"
    href="register.php?delete=<?= (int)$student['student_id'] ?>"
    onclick="return confirm('Remove this student account? Related reports will also be removed.');"
>
    Remove
</a>


</div>

</td>


</tr>


<?php endforeach; ?>


</tbody>

</table>


<?php endif; ?>


</div>

</section>


</div>

</main>

<script>
const studentPasswordModes = document.querySelectorAll('input[name="password_mode"]');
const studentManualPasswordField = document.getElementById('student-manual-password-field');
const studentManualPasswordInput = document.getElementById('student-manual-password');

function updateStudentPasswordMode() {
    if (!studentManualPasswordField || !studentManualPasswordInput) return;
    const manualSelected = document.querySelector('input[name="password_mode"]:checked')?.value === 'manual';
    studentManualPasswordField.hidden = !manualSelected;
    studentManualPasswordInput.required = manualSelected;
    if (!manualSelected) studentManualPasswordInput.value = '';
}

studentPasswordModes.forEach((mode) => mode.addEventListener('change', updateStudentPasswordMode));
updateStudentPasswordMode();
</script>


</body>

</html>
