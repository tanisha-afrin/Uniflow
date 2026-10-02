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
$stmt = $conn->prepare('SELECT admin_id, role, admin_type, is_active, must_change_password, can_manage_admins FROM admins WHERE admin_id = ? LIMIT 1');
$stmt->bind_param('i', $currentAdminId);
$stmt->execute();
$currentAdmin = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($currentAdmin && (int)$currentAdmin['must_change_password'] === 1) {
    header('Location: ../change_password.php');
    exit;
}
if (!adminCanManageAccounts($currentAdmin)) {
    http_response_code(403);
    exit(adminAccountManagementDenialMessage($currentAdmin));
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!hash_equals((string)($_SESSION['admin_status_csrf'] ?? ''), $csrfToken)) {
    http_response_code(403);
    exit('Invalid security token. Reload Admin Management and try again.');
}

$targetAdminIdRaw = $_POST['admin_id'] ?? '';
$isActiveRaw = $_POST['is_active'] ?? '';
if (
    !is_string($targetAdminIdRaw)
    || !ctype_digit($targetAdminIdRaw)
    || !in_array($isActiveRaw, ['0', '1'], true)
) {
    http_response_code(400);
    exit('Invalid account status request.');
}

$targetAdminId = (int)$targetAdminIdRaw;
$isActive = (int)$isActiveRaw;
if ($targetAdminId <= 0 || $targetAdminId === $currentAdminId) {
    http_response_code(400);
    exit('Invalid account status request.');
}

$targetStmt = $conn->prepare('SELECT admin_id, admin_type, role, can_manage_admins FROM admins WHERE admin_id = ? LIMIT 1');
$targetStmt->bind_param('i', $targetAdminId);
$targetStmt->execute();
$targetAdmin = $targetStmt->get_result()->fetch_assoc();
$targetStmt->close();
if (!$targetAdmin || !adminCanManageTarget($currentAdmin, $targetAdmin)) {
    http_response_code(403);
    exit('This account is outside your management access.');
}

if ($isActive === 0 && $targetAdmin['admin_type'] === 'admin') {
    if ($targetAdmin['role'] === 'administrative') {
        $activeManagers = $conn->query(
            "SELECT COUNT(*) FROM admins WHERE admin_type = 'admin' AND role = 'administrative' AND is_active = 1"
        )->fetch_row()[0];
        if ((int)$activeManagers <= 1) {
            http_response_code(409);
            exit('The last active Administrative manager cannot be deactivated. Create and activate another Administrative account first.');
        }
    }
}

$stmt = $conn->prepare('UPDATE admins SET is_active = ? WHERE admin_id = ? AND admin_type = \'admin\'');
$stmt->bind_param('ii', $isActive, $targetAdminId);
$stmt->execute();
$updated = $stmt->affected_rows;
$stmt->close();

header('Location: admin_management.php?' . ($updated > 0 ? 'status_updated=1' : 'status_unchanged=1'));
exit;