<?php

function getAdminManagementActor(mysqli $conn, int $adminId): ?array
{
    $stmt = $conn->prepare(
        'SELECT admin_id, name, email, role, admin_type, is_active,
                must_change_password, can_manage_admins
         FROM admins WHERE admin_id = ? LIMIT 1'
    );
    if (!$stmt) return null;
    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $actor = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $actor;
}

function adminCanManageAccounts(?array $admin): bool
{
    if (!$admin || (int)$admin['is_active'] !== 1 || (int)$admin['must_change_password'] === 1) {
        return false;
    }

    return ($admin['admin_type'] ?? 'admin') === 'main_admin'
        || (int)($admin['can_manage_admins'] ?? 0) === 1;
}

function adminCanManageTarget(array $actor, array $target): bool
{
    if ((int)$actor['admin_id'] === (int)$target['admin_id']
        || ($target['admin_type'] ?? 'admin') !== 'admin') {
        return false;
    }

    if (($actor['admin_type'] ?? 'admin') === 'main_admin') {
        return true;
    }

    return (int)($actor['can_manage_admins'] ?? 0) === 1
        && (int)($target['can_manage_admins'] ?? 0) !== 1;
}

function writeAdminAccountAudit(
    mysqli $conn,
    int $actorAdminId,
    ?int $targetAdminId,
    string $action,
    array $details = []
): bool {
    $detailsJson = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($detailsJson === false) $detailsJson = '{}';

    $stmt = $conn->prepare(
        'INSERT INTO admin_account_audit (actor_admin_id, target_admin_id, action, details_json)
         VALUES (?, ?, ?, ?)'
    );
    if (!$stmt) return false;
    $stmt->bind_param('iiss', $actorAdminId, $targetAdminId, $action, $detailsJson);
    $saved = $stmt->execute();
    $stmt->close();
    return $saved;
}

