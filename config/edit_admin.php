<?php

require_once "database.php";
require_once "auth.php";
require_once "admin_management_access.php";

/*
=====================================================
ADMIN LOGIN CHECK
=====================================================
*/

if (!isset($_SESSION["admin_id"])) {

    header("Location: admin_login.php");
    exit();

}


/*
=====================================================
CURRENT ADMIN
=====================================================
*/

$current_admin_id = (int) $_SESSION["admin_id"];

$current_admin = getAdminManagementActor($conn, $current_admin_id);


if (!$current_admin) {

    session_destroy();

    header("Location: admin_login.php");
    exit();

}


/*
=====================================================
ONLY MAIN ADMIN
=====================================================
*/

if ((int)$current_admin["must_change_password"] === 1) {
    header("Location: ../change_password.php");
    exit();
}
if (!adminCanManageAccounts($current_admin)) {
    http_response_code(403);
    exit(adminAccountManagementDenialMessage($current_admin));
}


/*
=====================================================
GET ADMIN ID
=====================================================
*/

if (
    !isset($_GET["id"]) ||
    !is_numeric($_GET["id"])
) {

    header("Location: admin_management.php");
    exit();

}

$target_admin_id = (int) $_GET["id"];


/*
=====================================================
GET TARGET ADMIN
=====================================================
*/

$stmt = $conn->prepare(
    "SELECT
        admin_id,
        name,
        email,
        role,
        admin_type,
        can_manage_admins
     FROM admins
     WHERE admin_id = ?"
);

$stmt->bind_param("i", $target_admin_id);
$stmt->execute();

$admin = $stmt->get_result()->fetch_assoc();

$stmt->close();


if (!$admin) {

    header("Location: admin_management.php");
    exit();

}


/*
=====================================================
PREVENT EDITING MAIN ADMIN
=====================================================
*/

if (!adminCanManageTarget($current_admin, $admin)) {

    header("Location: admin_management.php");
    exit();

}

if (empty($_SESSION['admin_management_csrf'])) {
    $_SESSION['admin_management_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!is_string($csrfToken)
        || !hash_equals($_SESSION['admin_management_csrf'], $csrfToken)) {
        http_response_code(403);
        exit('Invalid security token. Reload Admin Management and try again.');
    }
}


/*
=====================================================
GET CURRENT ACCESS
=====================================================
*/

$current_access = [];

$stmt = $conn->prepare(
    "SELECT access_area
     FROM admin_access
     WHERE admin_id = ?"
);

$stmt->bind_param("i", $target_admin_id);
$stmt->execute();

$access_result = $stmt->get_result();

while ($row = $access_result->fetch_assoc()) {

    $current_access[] = $row["access_area"];

}

$stmt->close();


/*
=====================================================
FORM VALUES
=====================================================
*/

$name = $admin["name"];
$email = $admin["email"];
$role = $admin["role"];

$error = "";


/*
=====================================================
FORM PROCESSING
=====================================================
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $new_password = $_POST["password"] ?? "";

    $access_areas = $_POST["access"] ?? [];


    /*
    -------------------------------------------------
    VALIDATION
    -------------------------------------------------
    */

    if ($name === "") {

        $error = "Please enter administrator name.";

    }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    }
    elseif (
        $new_password !== "" &&
        strlen($new_password) < 6
    ) {

        $error =
            "New password must be at least 6 characters.";

    }


    /*
    -------------------------------------------------
    CHECK DUPLICATE EMAIL
    -------------------------------------------------
    */

    if ($error === "") {

        $stmt = $conn->prepare(
            "SELECT admin_id
             FROM admins
             WHERE email = ?
             AND admin_id != ?"
        );

        $stmt->bind_param(
            "si",
            $email,
            $target_admin_id
        );

        $stmt->execute();

        $duplicate = $stmt->get_result()->fetch_assoc();

        $stmt->close();


        if ($duplicate) {

            $error =
                "Another administrator already uses this email.";

        }

    }


    /*
    -------------------------------------------------
    UPDATE ADMIN
    -------------------------------------------------
    */

    if ($error === "") {

        if ($new_password !== "") {

            $password_hash = password_hash(
                $new_password,
                PASSWORD_DEFAULT
            );


            $stmt = $conn->prepare(
                "UPDATE admins
                 SET
                    name = ?,
                    email = ?,
                    password = ?
                 WHERE admin_id = ?"
            );

            $stmt->bind_param(
                "sssi",
                $name,
                $email,
                $password_hash,
                $target_admin_id
            );

        }
        else {

            $stmt = $conn->prepare(
                "UPDATE admins
                 SET
                    name = ?,
                    email = ?
                 WHERE admin_id = ?"
            );

            $stmt->bind_param(
                "ssi",
                $name,
                $email,
                $target_admin_id
            );

        }


        if ($stmt->execute()) {

            $stmt->close();


            /*
            -----------------------------------------
            DELETE OLD ACCESS
            -----------------------------------------
            */

            $stmt = $conn->prepare(
                "DELETE FROM admin_access
                 WHERE admin_id = ?"
            );

            $stmt->bind_param(
                "i",
                $target_admin_id
            );

            $stmt->execute();

            $stmt->close();


            /*
            -----------------------------------------
            ADD NEW ACCESS
            -----------------------------------------
            */

            if (is_array($access_areas)) {

                $access_stmt = $conn->prepare(
                    "INSERT INTO admin_access
                    (
                        admin_id,
                        access_area
                    )
                    VALUES (?, ?)"
                );


                foreach ($access_areas as $area) {

                    if (
                        in_array(
                            $area,
                            [
                                "technical",
                                "administrative",
                                "proctorial",
                                "lost_found"
                            ]
                        )
                    ) {

                        $access_stmt->bind_param(
                            "is",
                            $target_admin_id,
                            $area
                        );

                        $access_stmt->execute();

                    }

                }

                $access_stmt->close();

            }


            header("Location: admin_management.php");
            exit();

        }
        else {

            $error =
                "Unable to update administrator.";

            $stmt->close();

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
    Edit Admin | UniFlow
</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">


<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--orange:#ff6a00;--orange-dark:#e95700;--orange-light:#fff3e8;--orange-soft:#ffe4cf;--ink:#2f211a;--muted:#8a7467;--line:#f1e4da;--shadow:0 20px 55px rgba(255,106,0,.10);--shadow-sm:0 8px 25px rgba(255,106,0,.08)}
html{scroll-behavior:smooth}
body{min-height:100vh;font-family:Inter,Arial,sans-serif;background:radial-gradient(circle at 8% 12%,rgba(255,138,61,.15),transparent 24%),radial-gradient(circle at 92% 18%,rgba(255,106,0,.11),transparent 26%),linear-gradient(135deg,#fff8f2 0%,#fff 48%,#fff6ed 100%);color:var(--ink);overflow-x:hidden}
body:before{content:"";position:fixed;inset:0;pointer-events:none;background-image:radial-gradient(rgba(255,106,0,.10) 1px,transparent 1px);background-size:24px 24px;mask-image:linear-gradient(to bottom,rgba(0,0,0,.35),transparent 72%)}
a{text-decoration:none;color:inherit}
.orb,.cube,.ring{position:fixed;pointer-events:none;z-index:0}
.orb{border-radius:50%;background:radial-gradient(circle at 30% 25%,#fff 0 8%,#ffb36e 18%,#ff7a1a 54%,#e65300 100%);box-shadow:inset -18px -22px 35px rgba(143,48,0,.20),0 35px 70px rgba(255,106,0,.15)}
.orb.one{width:150px;height:150px;right:-55px;top:125px;opacity:.55;animation:float1 7s ease-in-out infinite}
.orb.two{width:82px;height:82px;left:3%;bottom:12%;opacity:.35;animation:float2 6s ease-in-out infinite}
.ring{width:180px;height:180px;right:9%;bottom:7%;border:20px solid rgba(255,106,0,.12);border-radius:50%;transform:rotate(-20deg) perspective(400px) rotateY(45deg);box-shadow:0 20px 50px rgba(255,106,0,.08)}
.cube{width:74px;height:74px;left:8%;top:135px;border-radius:18px;background:linear-gradient(145deg,#ff9b52,#f45c00);transform:rotate(22deg) skewY(-5deg);box-shadow:15px 18px 0 rgba(197,69,0,.12),0 25px 55px rgba(255,106,0,.18);animation:float2 8s ease-in-out infinite}
@keyframes float1{50%{transform:translateY(-18px) rotate(8deg)}}
@keyframes float2{50%{transform:translateY(14px) rotate(7deg)}}
.navbar{min-height:76px;height:auto;background:rgba(255,255,255,.94);border-bottom:1px solid rgba(255,106,0,.13);display:flex;align-items:center;justify-content:space-between;padding:0 4%;position:relative;z-index:2;box-shadow:0 8px 30px rgba(50,25,10,.06);backdrop-filter:blur(18px)}
.logo{display:flex;align-items:center;gap:11px;font:800 22px 'Plus Jakarta Sans',Arial,sans-serif;color:#2f211a;white-space:nowrap}
.logo-icon{width:45px;height:45px;flex:0 0 45px;display:flex;align-items:center;justify-content:center;border-radius:14px;color:#fff;background:linear-gradient(145deg,#f97316,#ea580c);box-shadow:0 6px 0 #c2410c,0 12px 25px rgba(249,115,22,.22);font-weight:800;transform:rotate(-3deg)}
.logo-word{color:#2f211a}
.back-button{padding:10px 15px;border:1px solid #eadfd7;border-radius:11px;color:#1d120d;font-size:12px;font-weight:800;background:#fff;transition:.18s ease}.back-button:hover{color:var(--orange);border-color:#ffd9bd;background:#fff8f2;transform:translateY(-1px)}
.page{width:calc(100% - 100px);max-width:1180px;margin:0 auto;padding:42px 0 70px;position:relative;z-index:1}
.page-title .label{display:inline-flex;padding:8px 13px;border-radius:999px;background:linear-gradient(135deg,#fff3e7,#fffaf6);border:1px solid #ffd9bd;color:var(--orange-dark);font-size:11px;font-weight:800;letter-spacing:.6px;text-transform:uppercase;box-shadow:0 8px 20px rgba(249,115,22,.08)}
.page-title h1{margin-top:14px;font:800 clamp(34px,5vw,56px)/1.05 'Plus Jakarta Sans',Arial,sans-serif;letter-spacing:-2.1px;color:#2f211a}.page-title h1:after{content:"";display:inline-block;width:72px;height:10px;margin-left:12px;border-radius:999px;background:var(--orange);vertical-align:middle;opacity:.14}.page-title p{margin-top:10px;color:var(--muted);font-size:14px;line-height:1.7;max-width:720px}
.form-card{margin-top:30px;background:rgba(255,255,255,.94);border:1px solid var(--line);border-radius:24px;box-shadow:var(--shadow);padding:28px;backdrop-filter:blur(12px)}
.form-card:before{content:"";display:block;height:4px;width:76px;border-radius:999px;background:linear-gradient(90deg,var(--orange),#ffb079);margin-bottom:22px}
.error{padding:13px 15px;border-radius:13px;margin-bottom:20px;background:#fff1ef;border:1px solid #ffd0ca;color:#b42318;font-size:12px;font-weight:700}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.form-group.full{grid-column:1/-1}.form-group label{display:block;margin-bottom:8px;color:#5e4b41;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.45px}.form-group input{width:100%;height:48px;border:1px solid #eadbd0;border-radius:12px;background:#fffaf7;color:var(--ink);padding:0 14px;font:600 13px Inter,Arial,sans-serif;outline:none;transition:.18s ease}.form-group input:focus{border-color:#ff9a55;box-shadow:0 0 0 4px rgba(249,115,22,.09);background:#fff}
.access-box{padding:20px;border-radius:18px;background:linear-gradient(135deg,#fff8f1,#fff);border:1px solid #f3dfd0;box-shadow:var(--shadow-sm)}.access-title{font:800 14px 'Plus Jakarta Sans',Arial,sans-serif;color:#3a2a22;margin-bottom:5px}.access-title:after{content:"Choose which UniFlow portals this administrator can access.";display:block;margin-top:5px;color:var(--muted);font:500 11px Inter,Arial,sans-serif}.checkbox-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:11px;margin-top:17px}.checkbox-item{border:1px solid #efdfd3;border-radius:12px;background:#fff;padding:12px 13px;transition:.18s ease}.checkbox-item:hover{border-color:#ffbd91;background:#fff9f4}.checkbox-item label{margin:0;display:flex;align-items:center;gap:9px;font-size:12px;font-weight:800;text-transform:none;letter-spacing:0;color:#5a473d}.checkbox-item input{width:17px;height:17px;accent-color:var(--orange);margin:0}
.form-actions{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:25px;padding-top:20px;border-top:1px solid var(--line)}.cancel-button,.save-button{display:inline-flex;align-items:center;justify-content:center;min-height:45px;padding:11px 17px;border-radius:12px;font-size:12px;font-weight:800;border:1px solid transparent;transition:.18s ease}.cancel-button{color:#6f6057;background:#fff;border-color:#eadfd7}.cancel-button:hover{background:#fff7f1;transform:translateY(-1px)}.save-button{font-family:inherit;cursor:pointer;color:#fff;background:linear-gradient(135deg,#ff8a3d,#e85d0f);box-shadow:0 10px 22px rgba(249,115,22,.20)}.save-button:hover{transform:translateY(-2px);box-shadow:0 14px 28px rgba(249,115,22,.27)}
@media(max-width:800px){.page{width:calc(100% - 40px)}.form-grid{grid-template-columns:1fr}.form-group.full{grid-column:auto}.checkbox-grid{grid-template-columns:1fr}}
@media(max-width:560px){.navbar{height:auto;padding:15px 20px;gap:10px;align-items:flex-start;flex-direction:column}.back-button{align-self:flex-end}.form-card{padding:20px}.form-actions{flex-direction:column-reverse;align-items:stretch}.cancel-button,.save-button{width:100%}}
</style>

</head>


<body>


<div class="orb one" aria-hidden="true"></div>
<div class="orb two" aria-hidden="true"></div>
<div class="cube" aria-hidden="true"></div>
<div class="ring" aria-hidden="true"></div>


<nav class="navbar">

    <a class="logo"     href="../administrative/dashboard.php">
        <span class="logo-icon" aria-hidden="true">U</span>
        <span class="logo-word">UniFlow</span>
    </a>


    <a
        href="admin_management.php"
        class="back-button"
    >
        Back to Admin List
    </a>

</nav>


<main class="page">


    <div class="page-title">

        <div class="label">
            ADMINISTRATION
        </div>

        <h1>
            Edit Administrator
        </h1>

        <p>
            Update administrator information
            and portal access.
        </p>

    </div>


    <div class="form-card">


        <?php if ($error !== ""): ?>

            <div class="error">
                <?= e($error) ?>
            </div>

        <?php endif; ?>


        <form
            method="POST"
            action=""
        >

            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['admin_management_csrf']) ?>">

            <div class="form-grid">


                <div class="form-group">

                    <label>
                        Administrator ID
                    </label>

                    <input
                        type="text"
                        value="ADM-<?= (int)$target_admin_id ?>"
                        readonly
                    >

                </div>


                <div class="form-group">

                    <label>
                        Full Name
                    </label>

                    <input
                        type="text"
                        name="name"
                        value="<?= e($name) ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Email Address
                    </label>

                    <input
                        type="email"
                        name="email"
                        value="<?= e($email) ?>"
                        required
                    >

                </div>


                <div class="form-group full">

                    <label>
                        New Password
                    </label>

                    <input
                        type="password"
                        name="password"
                        placeholder="Leave blank to keep current password"
                    >

                </div>


                <div class="form-group full">

                    <div class="access-box">

                        <div class="access-title">
                            Portal Access
                        </div>


                        <div class="checkbox-grid">


                            <div class="checkbox-item">

                                <label>

                                    <input
                                        type="checkbox"
                                        name="access[]"
                                        value="technical"
                                        <?= in_array(
                                            "technical",
                                            $current_access
                                        )
                                            ? "checked"
                                            : "" ?>
                                    >

                                    Technical

                                </label>

                            </div>


                            <div class="checkbox-item">

                                <label>

                                    <input
                                        type="checkbox"
                                        name="access[]"
                                        value="administrative"
                                        <?= in_array(
                                            "administrative",
                                            $current_access
                                        )
                                            ? "checked"
                                            : "" ?>
                                    >

                                    Administrative

                                </label>

                            </div>


                            <div class="checkbox-item">

                                <label>

                                    <input
                                        type="checkbox"
                                        name="access[]"
                                        value="proctorial"
                                        <?= in_array(
                                            "proctorial",
                                            $current_access
                                        )
                                            ? "checked"
                                            : "" ?>
                                    >

                                    Proctorial

                                </label>

                            </div>


                            <div class="checkbox-item">

                                <label>

                                    <input
                                        type="checkbox"
                                        name="access[]"
                                        value="lost_found"
                                        <?= in_array(
                                            "lost_found",
                                            $current_access
                                        )
                                            ? "checked"
                                            : "" ?>
                                    >

                                    Lost & Found

                                </label>

                            </div>


                        </div>

                    </div>

                </div>


            </div>


            <div class="form-actions">

                <a
                    href="admin_management.php"
                    class="cancel-button"
                >
                    Cancel
                </a>


                <button
                    type="submit"
                    class="save-button"
                >
                    Save Changes
                </button>

            </div>


        </form>

    </div>

</main>


</body>

</html>
