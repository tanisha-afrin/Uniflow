-- Preserve existing posts while enabling posts authored by administrators.
ALTER TABLE lost_found_posts
    MODIFY student_id INT DEFAULT NULL,
    ADD COLUMN admin_id INT DEFAULT NULL AFTER student_id,
    ADD INDEX idx_lf_posts_admin (admin_id),
    ADD CONSTRAINT fk_lf_post_admin
        FOREIGN KEY (admin_id) REFERENCES admins(admin_id)
        ON DELETE SET NULL;

SET @lf_moderation_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'lost_found_posts'
       AND COLUMN_NAME = 'moderation_status') = 0,
    'ALTER TABLE lost_found_posts ADD COLUMN moderation_status ENUM(''Pending'',''Verified'',''Resolved'',''Rejected'') NOT NULL DEFAULT ''Pending'' AFTER description',
    'SELECT 1'
);
PREPARE lf_moderation_stmt FROM @lf_moderation_sql;
EXECUTE lf_moderation_stmt;
DEALLOCATE PREPARE lf_moderation_stmt;

SET @lf_moderation_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'lost_found_posts'
       AND COLUMN_NAME = 'admin_note') = 0,
    'ALTER TABLE lost_found_posts ADD COLUMN admin_note TEXT DEFAULT NULL AFTER moderation_status',
    'SELECT 1'
);
PREPARE lf_moderation_stmt FROM @lf_moderation_sql;
EXECUTE lf_moderation_stmt;
DEALLOCATE PREPARE lf_moderation_stmt;

SET @lf_moderation_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'lost_found_posts'
       AND COLUMN_NAME = 'moderated_by') = 0,
    'ALTER TABLE lost_found_posts ADD COLUMN moderated_by INT DEFAULT NULL AFTER admin_note',
    'SELECT 1'
);
PREPARE lf_moderation_stmt FROM @lf_moderation_sql;
EXECUTE lf_moderation_stmt;
DEALLOCATE PREPARE lf_moderation_stmt;

SET @lf_moderation_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'lost_found_posts'
       AND COLUMN_NAME = 'moderated_at') = 0,
    'ALTER TABLE lost_found_posts ADD COLUMN moderated_at TIMESTAMP NULL DEFAULT NULL AFTER moderated_by',
    'SELECT 1'
);
PREPARE lf_moderation_stmt FROM @lf_moderation_sql;
EXECUTE lf_moderation_stmt;
DEALLOCATE PREPARE lf_moderation_stmt;