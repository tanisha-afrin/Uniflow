ALTER TABLE admins
    ADD COLUMN IF NOT EXISTS can_manage_admins TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active;

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
