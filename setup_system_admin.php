<?php
/*
 * Local bootstrap utility. Run from the project directory with PHP CLI only:
 *   UNIFLOW_BOOTSTRAP_EMAIL=... php setup_system_admin.php
 * System Admin login password is fixed to 12345678.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$email = trim((string)getenv('UNIFLOW_BOOTSTRAP_EMAIL'));
$plainPassword = '12345678';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Set a valid UNIFLOW_BOOTSTRAP_EMAIL. The System Admin password is 12345678.\n");
    exit(2);
}

require_once __DIR__ . '/config/database.php';

$check = $conn->prepare('SELECT admin_id FROM admins WHERE email = ? LIMIT 1');
$check->bind_param('s', $email);
$check->execute();
$existing = $check->get_result()->fetch_assoc();
$check->close();

if ($existing) {
    $passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);
    $stmt = $conn->prepare(
        "UPDATE admins
         SET password = ?, admin_type = 'main_admin', role = 'administrative',
             is_active = 1, can_manage_admins = 1, must_change_password = 0
         WHERE admin_id = ?"
    );
    $stmt->bind_param('si', $passwordHash, $existing['admin_id']);

    if (!$stmt->execute()) {
        fwrite(STDERR, "Could not update the System Admin account: {$stmt->error}\n");
        exit(1);
    }

    echo "System Admin password reset to 12345678 for {$email}.\n";
    exit(0);
}

$name = 'UniFlow System Admin';
$passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);
$stmt = $conn->prepare(
    "INSERT INTO admins (name, email, password, role, admin_type, is_active, can_manage_admins, must_change_password)
     VALUES (?, ?, ?, 'administrative', 'main_admin', 1, 1, 0)"
);
$stmt->bind_param('sss', $name, $email, $passwordHash);

if (!$stmt->execute()) {
    fwrite(STDERR, "Could not create the System Admin account: {$stmt->error}\n");
    exit(1);
}

echo "System Admin account created for {$email} with password 12345678.\n";
