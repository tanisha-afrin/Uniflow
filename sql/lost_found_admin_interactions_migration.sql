-- Allow Lost & Found likes and comments from both students and admins.
-- Select uniflow_db before running. The guards make repeated imports safe.

ALTER TABLE lost_found_likes
    MODIFY student_id INT DEFAULT NULL;

SET @lf_migration_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lost_found_likes'
       AND COLUMN_NAME = 'admin_id') = 0,
    'ALTER TABLE lost_found_likes ADD COLUMN admin_id INT DEFAULT NULL AFTER student_id',
    'SELECT 1'
);
PREPARE lf_migration_stmt FROM @lf_migration_sql;
EXECUTE lf_migration_stmt;
DEALLOCATE PREPARE lf_migration_stmt;

SET @lf_migration_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lost_found_likes'
       AND INDEX_NAME = 'unique_post_admin_like') = 0,
    'ALTER TABLE lost_found_likes ADD UNIQUE KEY unique_post_admin_like (post_id, admin_id)',
    'SELECT 1'
);
PREPARE lf_migration_stmt FROM @lf_migration_sql;
EXECUTE lf_migration_stmt;
DEALLOCATE PREPARE lf_migration_stmt;

SET @lf_migration_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lost_found_likes'
       AND INDEX_NAME = 'idx_lf_likes_admin') = 0,
    'ALTER TABLE lost_found_likes ADD INDEX idx_lf_likes_admin (admin_id)',
    'SELECT 1'
);
PREPARE lf_migration_stmt FROM @lf_migration_sql;
EXECUTE lf_migration_stmt;
DEALLOCATE PREPARE lf_migration_stmt;

SET @lf_migration_sql = IF(
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'lost_found_likes'
       AND CONSTRAINT_NAME = 'fk_lf_like_admin') = 0,
    'ALTER TABLE lost_found_likes ADD CONSTRAINT fk_lf_like_admin FOREIGN KEY (admin_id) REFERENCES admins(admin_id) ON DELETE CASCADE',
    'SELECT 1'
);
PREPARE lf_migration_stmt FROM @lf_migration_sql;
EXECUTE lf_migration_stmt;
DEALLOCATE PREPARE lf_migration_stmt;

ALTER TABLE lost_found_comments
    MODIFY student_id INT DEFAULT NULL;

SET @lf_migration_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lost_found_comments'
       AND COLUMN_NAME = 'admin_id') = 0,
    'ALTER TABLE lost_found_comments ADD COLUMN admin_id INT DEFAULT NULL AFTER student_id',
    'SELECT 1'
);
PREPARE lf_migration_stmt FROM @lf_migration_sql;
EXECUTE lf_migration_stmt;
DEALLOCATE PREPARE lf_migration_stmt;

SET @lf_migration_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lost_found_comments'
       AND INDEX_NAME = 'idx_lf_comments_admin') = 0,
    'ALTER TABLE lost_found_comments ADD INDEX idx_lf_comments_admin (admin_id)',
    'SELECT 1'
);
PREPARE lf_migration_stmt FROM @lf_migration_sql;
EXECUTE lf_migration_stmt;
DEALLOCATE PREPARE lf_migration_stmt;

SET @lf_migration_sql = IF(
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'lost_found_comments'
       AND CONSTRAINT_NAME = 'fk_lf_comment_admin') = 0,
    'ALTER TABLE lost_found_comments ADD CONSTRAINT fk_lf_comment_admin FOREIGN KEY (admin_id) REFERENCES admins(admin_id) ON DELETE CASCADE',
    'SELECT 1'
);
PREPARE lf_migration_stmt FROM @lf_migration_sql;
EXECUTE lf_migration_stmt;
DEALLOCATE PREPARE lf_migration_stmt;
