<?php

function uniFlowCsrfToken(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['uniflow_csrf_token'])) {
        $_SESSION['uniflow_csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string)$_SESSION['uniflow_csrf_token'];
}

function uniFlowCsrfIsValid(mixed $token): bool
{
    return is_string($token)
        && $token !== ''
        && hash_equals(uniFlowCsrfToken(), $token);
}

function uniFlowRateLimit(
    mysqli $conn,
    string $action,
    string $identity,
    int $limit,
    int $windowSeconds
): bool {
    $rateKey = hash('sha256', $action . "\0" . $identity);
    try {
        $conn->query(
            'CREATE TABLE IF NOT EXISTS uniflow_rate_limits (
                rate_key CHAR(64) NOT NULL PRIMARY KEY,
                attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
                window_started_at DATETIME NOT NULL,
                INDEX idx_uniflow_rate_window (window_started_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $conn->begin_transaction();
        $insert = $conn->prepare(
            "INSERT IGNORE INTO uniflow_rate_limits (rate_key, attempt_count, window_started_at)
             VALUES (?, 0, NOW())"
        );
        $insert->bind_param('s', $rateKey);
        $insert->execute();
        $insert->close();

        $select = $conn->prepare(
            'SELECT attempt_count, window_started_at
             FROM uniflow_rate_limits WHERE rate_key = ? FOR UPDATE'
        );
        $select->bind_param('s', $rateKey);
        $select->execute();
        $row = $select->get_result()->fetch_assoc();
        $select->close();

        if (!$row) {
            throw new RuntimeException('Could not read signup rate limit.');
        }

        $windowStart = strtotime((string)$row['window_started_at']) ?: 0;
        if (time() - $windowStart >= $windowSeconds) {
            $update = $conn->prepare(
                'UPDATE uniflow_rate_limits
                 SET attempt_count = 1, window_started_at = NOW()
                 WHERE rate_key = ?'
            );
            $update->bind_param('s', $rateKey);
            $update->execute();
            $update->close();
            $allowed = true;
        } elseif ((int)$row['attempt_count'] >= $limit) {
            $allowed = false;
        } else {
            $update = $conn->prepare(
                'UPDATE uniflow_rate_limits SET attempt_count = attempt_count + 1 WHERE rate_key = ?'
            );
            $update->bind_param('s', $rateKey);
            $update->execute();
            $update->close();
            $allowed = true;
        }

        $conn->commit();
        return $allowed;
    } catch (Throwable $exception) {
        try {
            $conn->rollback();
        } catch (Throwable $ignored) {
        }
        error_log('UniFlow signup rate limit failed: ' . $exception->getMessage());
        return false;
    }
}
