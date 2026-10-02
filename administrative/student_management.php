<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| Administrative access only
|--------------------------------------------------------------------------
*/

if (!function_exists('is_admin_logged_in') || !is_admin_logged_in()) {
    header('Location: ../login.php');
    exit;
}

$adminRole = $_SESSION['admin_role'] ?? '';
$adminType = $_SESSION['admin_type'] ?? '';

$currentAdminId = (int)$_SESSION['admin_id'];
$activeStmt = $conn->prepare('SELECT is_active, must_change_password FROM admins WHERE admin_id = ? LIMIT 1');
$activeStmt->bind_param('i', $currentAdminId);
$activeStmt->execute();
$activeAdmin = $activeStmt->get_result()->fetch_assoc();
$activeStmt->close();

if ($activeAdmin && (int)$activeAdmin['must_change_password'] === 1) {
    header('Location: ../change_password.php');
    exit;
}

if ($adminType !== 'main_admin' || !$activeAdmin || (int)$activeAdmin['is_active'] !== 1) {
    header('Location: dashboard.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Database connection
|--------------------------------------------------------------------------
*/

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection failed.');
}


/*
|--------------------------------------------------------------------------
| Get students
|--------------------------------------------------------------------------
*/

$students = [];

$sql = "
    SELECT
        student_id,
        student_code,
        name,
        email,
        personal_email,
        created_at
    FROM students
    ORDER BY student_id DESC
";

$result = $conn->query($sql);

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }

}


/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$totalStudents = count($students);

$studentsWithPersonalEmail = 0;

foreach ($students as $student) {

    if (!empty($student['personal_email'])) {
        $studentsWithPersonalEmail++;
    }

}


/*
|--------------------------------------------------------------------------
| Escape helper
|--------------------------------------------------------------------------
*/

function e($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
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

    <title>Student Management | UniFlow</title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #fffaf5;

            color: #24150f;

        }


        a {

            text-decoration: none;

            color: inherit;

        }


        /* =========================
           TOP BAR
        ========================= */

        .topbar {

            height: 70px;

            background: #ffffff;

            border-bottom: 1px solid #eadfd7;

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding: 0 42px;

        }


        .brand {

            font-size: 27px;

            font-weight: 800;

            color: #1d120d;

        }


        .nav {

            display: flex;

            align-items: center;

            gap: 28px;

            font-size: 14px;

            font-weight: 700;

        }


        .nav a {

            color: #1d120d;

        }


        .nav a:hover {

            color: #ff650f;

        }


        /* =========================
           PAGE
        ========================= */

        .page {

            width: calc(100% - 100px);

            max-width: 1450px;

            margin: 30px auto 60px;

        }


        /* =========================
           INTRO
        ========================= */

        .intro {

            background: #ffffff;

            border: 1px solid #efdcd0;

            border-left: 5px solid #ff650f;

            border-radius: 18px;

            padding: 24px 25px;

            margin-bottom: 22px;

        }


        .intro h1 {

            margin: 0 0 8px;

            font-size: 18px;

            color: #ff5e0b;

        }


        .intro p {

            margin: 0;

            color: #806f66;

            font-size: 13px;

            line-height: 1.6;

        }


        /* =========================
           STATISTICS
        ========================= */

        .stats {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 18px;

            margin-bottom: 24px;

        }


        .stat {

            background: #ffffff;

            border: 1px solid #efdcd0;

            border-radius: 18px;

            padding: 24px;

        }


        .stat-number {

            font-size: 30px;

            font-weight: 800;

            margin-bottom: 6px;

        }


        .stat-label {

            color: #806f66;

            font-size: 13px;

            font-weight: 600;

        }


        /* =========================
           MAIN STUDENT CARD
        ========================= */

        .main-card {

            background: #ffffff;

            border: 1px solid #efdcd0;

            border-radius: 22px;

            overflow: hidden;

        }


        .card-header {

            padding: 25px 28px;

            border-bottom:
                1px solid #eee1d9;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 20px;

        }


        .card-header h2 {

            margin: 0 0 6px;

            font-size: 21px;

        }


        .card-header p {

            margin: 0;

            color: #806f66;

            font-size: 13px;

        }


        /* =========================
           ADD NEW STUDENT BUTTON
        ========================= */

        .add-button {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            background: #ff6812;

            color: #ffffff;

            border-radius: 11px;

            padding: 13px 20px;

            font-size: 13px;

            font-weight: 800;

            border: none;

            cursor: pointer;

            box-shadow:
                0 7px 16px
                rgba(255, 104, 18, 0.20);

        }


        .add-button:hover {

            background: #ed5705;

        }


        /* =========================
           TABLE
        ========================= */

        .table-wrap {

            padding: 25px 28px 30px;

            overflow-x: auto;

        }


        table {

            width: 100%;

            border-collapse: collapse;

            min-width: 850px;

        }


        th {

            text-align: left;

            background: #fff7f1;

            color: #5e4d44;

            font-size: 12px;

            padding: 15px 14px;

            border-bottom:
                1px solid #eadbd2;

        }


        td {

            padding: 16px 14px;

            border-bottom:
                1px solid #f0e5df;

            font-size: 13px;

            vertical-align: middle;

        }


        tr:last-child td {

            border-bottom: none;

        }


        .student-name {

            font-weight: 800;

            color: #24150f;

        }


        .student-code {

            font-weight: 700;

            color: #ff650f;

        }


        .email {

            color: #5e4d44;

        }


        .date {

            color: #806f66;

            white-space: nowrap;

        }


        /* =========================
           EMPTY STATE
        ========================= */

        .empty {

            padding: 65px 20px;

            text-align: center;

            color: #806f66;

        }


        .empty h3 {

            margin: 0 0 8px;

            color: #24150f;

        }


        .empty p {

            margin: 0;

        }


        /* =========================
           MOBILE
        ========================= */

        @media (max-width: 900px) {

            .page {

                width:
                    calc(100% - 30px);

            }


            .topbar {

                padding: 0 18px;

            }


            .nav {

                gap: 14px;

            }


            .stats {

                grid-template-columns: 1fr;

            }


            .card-header {

                align-items: flex-start;

                flex-direction: column;

            }

        }


        .actions { display:flex; gap:8px; white-space:nowrap; }
        .action { display:inline-flex; align-items:center; justify-content:center; padding:8px 12px; border-radius:10px; font-size:11px; font-weight:800; transition:.18s ease; }
        .action.edit { color:#fff; background:#ff6a00; border:1px solid #ff6a00; }
        .action.edit:hover { background:#e95700; transform:translateY(-1px); }
        .action.remove { color:#e95700; background:#fff; border:1px solid #ffd8bd; }
        .action.remove:hover { background:#fff3e8; transform:translateY(-1px); }

    </style>

<link rel="stylesheet" href="../css/buttons.css">
</head>


<body>


<header class="topbar">

    <a
        href="dashboard.php"
        class="brand"
    >
        UniFlow
    </a>


    <nav class="nav">

        <a href="dashboard.php">
            Dashboard
        </a>


        <a href="student_management.php">
            Student Management
        </a>


        <a href="logout.php">
            Logout
        </a>

    </nav>

</header>



<main class="page">


    <!-- INTRO -->

    <section class="intro">

        <h1>
            Student Account Management
        </h1>

        <p>
            View UniFlow student login accounts created from university-provided student details. This list does not record admission or enrollment; those remain university responsibilities.
        </p>

    </section>



    <!-- STATISTICS -->

    <section class="stats">


        <div class="stat">

            <div class="stat-number">
                <?= $totalStudents ?>
            </div>

            <div class="stat-label">
                Student Login Accounts
            </div>

        </div>



        <div class="stat">

            <div class="stat-number">
                <?= $studentsWithPersonalEmail ?>
            </div>

            <div class="stat-label">
                Students With Personal Email
            </div>

        </div>



        <div class="stat">

            <div class="stat-number">
                System
            </div>

            <div class="stat-label">
                Student Management
            </div>

        </div>


    </section>



    <!-- STUDENT LIST -->

    <section class="main-card">


        <div class="card-header">


            <div>

                <h2>
                    Student Accounts
                </h2>

                <p>
                    UniFlow login accounts for students.
                </p>

            </div>



            <!-- ONLY LINK TO ADD STUDENT PAGE -->

            <a
                href="../student/register.php"
                class="add-button"
            >
                Create / Manage Student Accounts
            </a>


        </div>



        <?php if (empty($students)): ?>


            <div class="empty">

                <h3>
                    No student login accounts found
                </h3>

                <p>
                    Create a UniFlow account to provide student access to the platform.
                </p>

            </div>


        <?php else: ?>


            <div class="table-wrap">


                <table>


                    <thead>

                        <tr>

                            <th>
                                Student ID
                            </th>

                            <th>
                                Student Name
                            </th>

                            <th>
                                University Email
                            </th>

                            <th>
                                Personal Email
                            </th>

                            <th>
                                Created
                            </th>

                            <th>
                                Actions
                            </th>

                        </tr>

                    </thead>



                    <tbody>


                    <?php foreach ($students as $student): ?>


                        <tr>


                            <td>

                                <span class="student-code">

                                    <?= e(
                                        $student['student_code']
                                    ) ?>

                                </span>

                            </td>



                            <td>

                                <div class="student-name">

                                    <?= e(
                                        $student['name']
                                    ) ?>

                                </div>

                            </td>



                            <td>

                                <span class="email">

                                    <?= e(
                                        $student['email']
                                    ) ?>

                                </span>

                            </td>



                            <td>

                                <span class="email">

                                    <?php if (
                                        !empty(
                                            $student['personal_email']
                                        )
                                    ): ?>

                                        <?= e(
                                            $student['personal_email']
                                        ) ?>

                                    <?php else: ?>

                                        Not provided

                                    <?php endif; ?>

                                </span>

                            </td>



                            <td>

                                <span class="date">

                                    <?=
                                        !empty(
                                            $student['created_at']
                                        )
                                        ? e(
                                            date(
                                                'd M Y',
                                                strtotime(
                                                    $student['created_at']
                                                )
                                            )
                                        )
                                        : 'Not available'
                                    ?>

                                </span>

                            </td>

                            <td>

                                <div class="actions">

                                    <a
                                        href="student_edit.php?id=<?= (int)$student['student_id'] ?>"
                                        class="action edit"
                                    >
                                        Edit
                                    </a>

                                    <a
                                        href="student_edit.php?id=<?= (int)$student['student_id'] ?>&remove=1"
                                        class="action remove"
                                        onclick="return confirm('Remove this student account?');"
                                    >
                                        Remove
                                    </a>

                                </div>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                    </tbody>


                </table>


            </div>


        <?php endif; ?>


    </section>


</main>


</body>

</html>
