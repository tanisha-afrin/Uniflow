<?php
require_once __DIR__ . '/config.php';

/* =========================================================
   LOST & FOUND USES THE SAME UNIFLOW SESSION.
   No separate Lost & Found login exists.
========================================================= */
function isLoggedIn(): bool {
    return !empty($_SESSION['student_id']) || !empty($_SESSION['admin_id']);
}

function isStudentLoggedIn(): bool {
    return !empty($_SESSION['student_id']);
}

function isAdminLoggedIn(): bool {
    return !empty($_SESSION['admin_id']);
}

function isLostFoundModerator(): bool {
    static $result = null;

    if ($result !== null) {
        return $result;
    }

    if (!isAdminLoggedIn()) {
        return $result = false;
    }

    global $pdo;
    try {
        $adminStmt = $pdo->prepare('SELECT role, admin_type, is_active, must_change_password FROM admins WHERE admin_id = ? LIMIT 1');
        $adminStmt->execute([currentAdminId()]);
        $admin = $adminStmt->fetch();
        if (!$admin || (int)$admin['is_active'] !== 1 || (int)$admin['must_change_password'] === 1) {
            return $result = false;
        }

        $stmt = $pdo->prepare('SELECT 1 FROM admin_access WHERE admin_id = ? AND access_area = ? LIMIT 1');
        $stmt->execute([currentAdminId(), 'lost_found']);
        // New assignments use the permission table. Keep legacy Lost & Found
        // staff access during migration, but never grant it to System Admin.
        return $result = (bool)$stmt->fetchColumn()
            || ($admin['admin_type'] !== 'main_admin' && $admin['role'] === 'lost_found');
    } catch (PDOException $e) {
        return $result = false;
    }
}

function canManagePost(array $post): bool {
    return isLostFoundModerator() || (
        isStudentLoggedIn() &&
        (int)$post['student_id'] === currentStudentId()
    ) || (
        isAdminLoggedIn() &&
        (int)$post['admin_id'] === currentAdminId()
    );
}

function canCreatePost(): bool {
    return isStudentLoggedIn() || (
        isAdminLoggedIn() &&
        in_array(currentAdminRole(), ['technical', 'administrative', 'proctorial', 'lost_found'], true)
    );
}

function currentUserName(): string {
    if (isAdminLoggedIn()) {
        return (string)($_SESSION['admin_name'] ?? 'Admin');
    }

    return (string)($_SESSION['student_name'] ?? 'Student');
}

function currentAdminRole(): string {
    return (string)($_SESSION['admin_role'] ?? '');
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ../login.php?return_to=lost_found');
        exit;
    }
}

function requireStudent(): void {
    if (!isStudentLoggedIn()) {
        if (isAdminLoggedIn()) {
            header('Location: index.php');
        } else {
            header('Location: ../login.php?return_to=lost_found');
        }
        exit;
    }
}

function requirePostPermission(): void {
    requireLogin();
    if (!canCreatePost()) {
        http_response_code(403);
        exit('You do not have permission to create Lost & Found posts.');
    }
}

function currentStudentId(): int {
    return (int)($_SESSION['student_id'] ?? 0);
}

function currentAdminId(): int {
    return (int)($_SESSION['admin_id'] ?? 0);
}

function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
?>
