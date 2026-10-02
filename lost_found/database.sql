-- UniFlow Lost & Found - FRESH DATABASE SETUP
-- IMPORTANT: This resets ONLY the three Lost & Found tables.
-- It does NOT delete the existing students table.

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS lost_found_comments;
DROP TABLE IF EXISTS lost_found_likes;
DROP TABLE IF EXISTS lost_found_posts;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE lost_found_posts (
    post_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT DEFAULT NULL,
    admin_id INT DEFAULT NULL,
    post_type ENUM('Lost','Found') NOT NULL,
    item_name VARCHAR(150) NOT NULL,
    photo VARCHAR(255) DEFAULT NULL,
    location VARCHAR(255) NOT NULL,
    event_date DATE NOT NULL,
    description TEXT NOT NULL,
    moderation_status ENUM('Pending','Verified','Resolved','Rejected') NOT NULL DEFAULT 'Pending',
    admin_note TEXT DEFAULT NULL,
    moderated_by INT DEFAULT NULL,
    moderated_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_lf_posts_student (student_id),
    INDEX idx_lf_posts_admin (admin_id),
    INDEX idx_lf_moderation_status (moderation_status),
    INDEX idx_lf_moderated_by (moderated_by),
    INDEX idx_lf_posts_date (created_at),
    INDEX idx_lf_posts_type (post_type),
    CONSTRAINT fk_lf_post_student
        FOREIGN KEY (student_id) REFERENCES students(student_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_lf_post_admin
        FOREIGN KEY (admin_id) REFERENCES admins(admin_id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lost_found_likes (
    like_id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    student_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_post_like (post_id, student_id),
    INDEX idx_lf_likes_student (student_id),
    CONSTRAINT fk_lf_like_post
        FOREIGN KEY (post_id) REFERENCES lost_found_posts(post_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_lf_like_student
        FOREIGN KEY (student_id) REFERENCES students(student_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lost_found_comments (
    comment_id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    student_id INT NOT NULL,
    comment_text VARCHAR(500) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_lf_comments_post (post_id),
    INDEX idx_lf_comments_student (student_id),
    CONSTRAINT fk_lf_comment_post
        FOREIGN KEY (post_id) REFERENCES lost_found_posts(post_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_lf_comment_student
        FOREIGN KEY (student_id) REFERENCES students(student_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
