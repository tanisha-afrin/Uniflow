-- Bring an existing UniFlow database up to the admin schema used by login
-- and the admin invitation/permissions pages. Safe to run more than once.
ALTER TABLE admins
    ADD COLUMN IF NOT EXISTS personal_email VARCHAR(150) NULL,
    ADD COLUMN IF NOT EXISTS admin_type ENUM('main_admin','admin') NOT NULL DEFAULT 'admin',
    ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS can_manage_admins TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS password_expires_at DATETIME NULL,
    ADD COLUMN IF NOT EXISTS invited_at DATETIME NULL,
    ADD COLUMN IF NOT EXISTS activation_token_hash VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS activation_expires_at DATETIME NULL,
    ADD COLUMN IF NOT EXISTS activation_used TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS password_change_token_hash VARCHAR(64) NULL,
    ADD COLUMN IF NOT EXISTS password_change_expires_at DATETIME NULL;

CREATE TABLE IF NOT EXISTS admin_access (
    access_id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    access_area ENUM('technical','administrative','proctorial','lost_found') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_admin_access (admin_id, access_area),
    CONSTRAINT fk_admin_access_admin
        FOREIGN KEY (admin_id) REFERENCES admins(admin_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admin_account_audit (
    audit_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_admin_id INT NULL,
    target_admin_id INT NULL,
    action VARCHAR(32) NOT NULL,
    details_json JSON NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_audit_target (target_admin_id, created_at),
    INDEX idx_admin_audit_actor (actor_admin_id, created_at),
    CONSTRAINT fk_admin_audit_actor FOREIGN KEY (actor_admin_id)
        REFERENCES admins(admin_id) ON DELETE SET NULL,
    CONSTRAINT fk_admin_audit_target FOREIGN KEY (target_admin_id)
        REFERENCES admins(admin_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
