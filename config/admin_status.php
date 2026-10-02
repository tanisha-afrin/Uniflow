<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/admin_management_access.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('POST required.');
}

$currentAdminId = (int)$_SESSION['admin_id'];
$currentAdmin = getAdminManagementActor($conn, $currentAdminId);
if (!$currentAdmin || (int)$currentAdmin['must_change_password'] === 1) {
    header('Location: ../change_password.php');
    exit;
}
if (!adminCanManageAccounts($currentAdmin)) {
    http_response_code(403);
    exit('Administrator account management access required.');
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!isset($_SESSION['admin_status_csrf'])
    || !is_string($csrfToken)
    || !hash_equals((string)$_SESSION['admin_status_csrf'], $csrfToken)) {
    http_response_code(403);
    exit('Invalid security token. Reload Admin Management and try again.');
}

$targetAdminId = filter_var($_POST['admin_id'] ?? null, FILTER_VALIDATE_INT);
$isActive = filter_var($_POST['is_active'] ?? null, FILTER_VALIDATE_INT);
if (!$targetAdminId || !in_array($isActive, [0, 1], true)) {
    http_response_code(400);
    exit('Invalid account status request.');
}

$stmt = $conn->prepare('SELECT admin_id, name, email, admin_type, is_active, can_manage_admins FROM admins WHERE admin_id = ? LIMIT 1');
$stmt->bind_param('i', $targetAdminId);
$stmt->execute();
$targetAdmin = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$targetAdmin || !adminCanManageTarget($currentAdmin, $targetAdmin)) {
    http_response_code(403);
    exit('This account is outside your management access.');
}

if ((int)$targetAdmin['is_active'] === $isActive) {
    header('Location: admin_management.php?status_unchanged=1');
    exit;
}

$conn->begin_transaction();
try {
    $managerGuard = ($currentAdmin['admin_type'] ?? 'admin') === 'main_admin' ? '' : ' AND can_manage_admins = 0';
    $stmt = $conn->prepare("UPDATE admins SET is_active = ? WHERE admin_id = ? AND admin_type = 'admin'{$managerGuard}");
    $stmt->bind_param('ii', $isActive, $targetAdminId);
    if (!$stmt->execute() || $stmt->affected_rows !== 1) {
        throw new RuntimeException('Account status was not changed.');
    }
    $stmt->close();

    $action = $isActive === 1 ? 'activated' : 'deactivated';
    if (!writeAdminAccountAudit($conn, $currentAdminId, $targetAdminId, $action, [
        'name' => $targetAdmin['name'] ?? null,
        'email' => $targetAdmin['email'] ?? null
    ])) {
        throw new RuntimeException('Could not write the account audit record.');
    }
    $conn->commit();
    header('Location: admin_management.php?status_updated=1');
    exit;
} catch (Throwable $e) {
    $conn->rollback();
    error_log('UniFlow admin status update failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Could not update the administrator account status.');
}
