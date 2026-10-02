<?php
/*
 * CLI-only first Administrative account bootstrap.
 * Configure UNIFLOW_BOOTSTRAP_ADMIN_NAME, UNIFLOW_BOOTSTRAP_ADMIN_EMAIL, and
 * UNIFLOW_BOOTSTRAP_ADMIN_PASSWORD in the process environment or private .env.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/database_settings.php';

$name = trim(uniFlowEnvironmentValue('UNIFLOW_BOOTSTRAP_ADMIN_NAME'));
$email = trim(uniFlowEnvironmentValue('UNIFLOW_BOOTSTRAP_ADMIN_EMAIL'));
$password = uniFlowEnvironmentValue('UNIFLOW_BOOTSTRAP_ADMIN_PASSWORD');

if ($name === '' || strlen($name) > 100
    || !filter_var($email, FILTER_VALIDATE_EMAIL)
    || strlen($password) < 12) {
    fwrite(STDERR, "Set a valid bootstrap admin name/email and a password of at least 12 characters.\n");
    exit(2);
}

$existing = $conn->query(
    "SELECT admin_id FROM admins WHERE admin_type = 'admin' AND role = 'administrative' AND is_active = 1 LIMIT 1"
);
if ($existing->num_rows > 0) {
    fwrite(STDERR, "An active Administrative account already exists; no account was changed.\n");
    exit(3);
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
try {
    $conn->begin_transaction();
    $stmt = $conn->prepare(
        "INSERT INTO admins
            (name, email, password, role, admin_type, is_active, can_manage_admins, must_change_password)
         VALUES (?, ?, ?, 'administrative', 'admin', 1, 1, 0)"
    );
    $stmt->bind_param('sss', $name, $email, $passwordHash);
    $stmt->execute();
    $adminId = (int)$conn->insert_id;
    $stmt->close();

    $access = $conn->prepare('INSERT INTO admin_access (admin_id, access_area) VALUES (?, \'administrative\')');
    $access->bind_param('i', $adminId);
    $access->execute();
    $access->close();
    $conn->commit();

    fwrite(STDOUT, "Initial Administrative account created for {$email}.\n");
} catch (Throwable $exception) {
    $conn->rollback();
    error_log('[UniFlow] Initial Administrative account setup failed: ' . $exception->getMessage());
    fwrite(STDERR, "Could not create the initial Administrative account.\n");
    exit(1);
}
