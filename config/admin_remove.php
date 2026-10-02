<?php

require_once "database.php";
require_once "auth.php";

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

$stmt = $conn->prepare(
    "SELECT
        admin_id,
        name,
        email,
        role,
        admin_type
     FROM admins
     WHERE admin_id = ?"
);

$stmt->bind_param("i", $current_admin_id);
$stmt->execute();

$current_admin = $stmt->get_result()->fetch_assoc();

$stmt->close();


if (!$current_admin) {

    session_destroy();

    header("Location: admin_login.php");
    exit();

}


/*
=====================================================
ONLY MAIN ADMIN CAN REMOVE
=====================================================
*/

if ($current_admin["admin_type"] !== "main_admin") {

    header("Location: admin_management.php");
    exit();

}


/*
=====================================================
GET TARGET ADMIN ID
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
CANNOT REMOVE YOURSELF
=====================================================
*/

if ($target_admin_id === $current_admin_id) {

    header("Location: admin_management.php");
    exit();

}


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
        admin_type
     FROM admins
     WHERE admin_id = ?"
);

$stmt->bind_param("i", $target_admin_id);
$stmt->execute();

$target_admin = $stmt->get_result()->fetch_assoc();

$stmt->close();


if (!$target_admin) {

    header("Location: admin_management.php");
    exit();

}


/*
=====================================================
MAIN ADMIN PROTECTION
=====================================================
*/

if ($target_admin["admin_type"] === "main_admin") {

    header("Location: admin_management.php");
    exit();

}


/*
=====================================================
REMOVE ADMIN
=====================================================
*/

$stmt = $conn->prepare(
    "DELETE FROM admins
     WHERE admin_id = ?
     AND admin_type != 'main_admin'"
);

$stmt->bind_param(
    "i",
    $target_admin_id
);

$stmt->execute();

$stmt->close();


/*
=====================================================
ADMIN ACCESS IS DELETED AUTOMATICALLY
=====================================================

admin_access has:

ON DELETE CASCADE

So related portal access will also be removed.
*/


header("Location: admin_management.php");
exit();

?>