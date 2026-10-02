<?php

/**
 * Read a UniFlow setting from the process environment or project-root .env.
 * Process environment variables take precedence. The .env file is optional.
 */
function uniFlowEnvironmentValue(string $name, string $default = ''): string
{
    static $fileValues = null;

    if ($fileValues === null) {
        $fileValues = [];
        $envFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';

        if (is_readable($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
                // Remove a UTF-8 BOM (Windows Notepad adds one) so the first line still parses.
                $line = trim(preg_replace('/^\xEF\xBB\xBF/', '', $line));
                if ($line === '' || str_starts_with($line, '#')
                    || !preg_match('/^(UNIFLOW_[A-Z0-9_]+)\s*=\s*(.*)$/', $line, $matches)) {
                    continue;
                }

                $value = trim($matches[2]);
                if (strlen($value) >= 2) {
                    $first = $value[0];
                    $last = $value[strlen($value) - 1];
                    if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                        $value = substr($value, 1, -1);
                    }
                }

                $fileValues[$matches[1]] = $value;
            }
        }
    }

    $processValue = getenv($name);
    if ($processValue !== false) {
        return $processValue;
    }

    return $fileValues[$name] ?? $default;
}

/** Shared MySQL settings for the main UniFlow and Lost & Found connections. */
function uniFlowDatabaseSettings(): array
{
    static $settings = null;
    if ($settings !== null) {
        return $settings;
    }

    $portValue = filter_var(
        uniFlowEnvironmentValue('UNIFLOW_DB_PORT', '3306'),
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => 65535]]
    );

    $settings = [
        'host' => uniFlowEnvironmentValue('UNIFLOW_DB_HOST', '127.0.0.1'),
        'port' => $portValue === false ? 3306 : $portValue,
        'name' => uniFlowEnvironmentValue('UNIFLOW_DB_NAME', 'uniflow_db'),
        'user' => uniFlowEnvironmentValue('UNIFLOW_DB_USER', 'root'),
        'password' => uniFlowEnvironmentValue('UNIFLOW_DB_PASSWORD'),
    ];

    return $settings;
}

function uniFlowDatabaseErrorMessage(Throwable $error): string
{
    if ((int)$error->getCode() === 1130) {
        return 'MariaDB does not allow connections from localhost. Configure a database account '
            . 'with local access to uniflow_db, then set that account in the UNIFLOW_DB_* settings.';
    }

    return 'Cannot connect to the database. Confirm MariaDB is running, then check the '
        . 'UNIFLOW_DB_* settings and that the configured account has access to the configured database.';
}
