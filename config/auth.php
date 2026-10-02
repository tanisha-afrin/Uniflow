<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e($value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function login_required(): void
{
    if (!isset($_SESSION['student_id']) && !isset($_SESSION['admin_id'])) {
        header('Location: ../login.php');
        exit();
    }
}

function student_required(): void
{
    if (!isset($_SESSION['student_id'])) {
        header('Location: ../login.php');
        exit();
    }
}

function getAdminAccessAreas(mysqli $conn, int $adminId): array
{
    $areas = [];
    $stmt = $conn->prepare('SELECT access_area FROM admin_access WHERE admin_id = ? ORDER BY FIELD(access_area, "technical", "administrative", "proctorial", "lost_found")');
    if (!$stmt) return $areas;
    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $areas[] = $row['access_area'];
    $stmt->close();
    return $areas;
}

function admin_has_access(string $portal): bool
{
    if (!isset($_SESSION['admin_id'])) return false;

    global $conn;
    if (!isset($conn) || !($conn instanceof mysqli)) return false;

    $id = (int)$_SESSION['admin_id'];
    $statusStmt = $conn->prepare('SELECT is_active, must_change_password FROM admins WHERE admin_id = ? LIMIT 1');
    if (!$statusStmt) return false;
    $statusStmt->bind_param('i', $id);
    $statusStmt->execute();
    $account = $statusStmt->get_result()->fetch_assoc();
    $statusStmt->close();
    if (!$account || (int)$account['is_active'] !== 1 || (int)$account['must_change_password'] === 1) return false;

    $adminType = $_SESSION['admin_type'] ?? 'admin';
    $role = $_SESSION['admin_role'] ?? '';
    if ($adminType === 'main_admin') return true;
    if ($role === $portal) return true;

    $stmt = $conn->prepare('SELECT access_id FROM admin_access WHERE admin_id = ? AND access_area = ? LIMIT 1');
    if (!$stmt) return false;
    $stmt->bind_param('is', $id, $portal);
    $stmt->execute();
    $result = $stmt->get_result();
    $allowed = $result && $result->num_rows > 0;
    $stmt->close();
    return $allowed;
}

function admin_required(string $role): void
{
    if (!isset($_SESSION['admin_id'])) {
        header('Location: ../login.php');
        exit();
    }
    if (!admin_has_access($role)) {
        header('Location: ../index.php');
        exit();
    }
}

function is_student_logged_in(): bool { return isset($_SESSION['student_id']); }
function is_admin_logged_in(): bool { return isset($_SESSION['admin_id']); }
function current_user_type(): string
{
    if (is_admin_logged_in()) return 'admin';
    if (is_student_logged_in()) return 'student';
    return 'guest';
}
