<?php
/*
 * Backward-compatible CLI name for the one-time Administrative account setup.
 * Configure UNIFLOW_BOOTSTRAP_ADMIN_* in a private .env or process environment.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/setup_system_admin.php';
