CREATE DATABASE IF NOT EXISTS uniflow_db;

USE uniflow_db;


-- =========================================
-- STUDENTS
-- =========================================

CREATE TABLE students (

    student_id INT AUTO_INCREMENT PRIMARY KEY,

    student_code VARCHAR(50) UNIQUE NOT NULL,

    name VARCHAR(100) NOT NULL,

    email VARCHAR(150) UNIQUE NOT NULL,

    password VARCHAR(255) NOT NULL,

    password_change_token_hash VARCHAR(64) NULL,

    password_change_expires_at DATETIME NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

);


-- =========================================
-- ADMINS
-- =========================================

CREATE TABLE admins (

    admin_id INT AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(100) NOT NULL,

    email VARCHAR(150) UNIQUE NOT NULL,

    personal_email VARCHAR(150) NULL,

    password VARCHAR(255) NOT NULL,

    role ENUM(
        'technical',
        'administrative',
        'proctorial',
        'lost_found'
    ) NOT NULL,

    admin_type ENUM('main_admin','admin') NOT NULL DEFAULT 'admin',

    is_active TINYINT(1) NOT NULL DEFAULT 1,

    can_manage_admins TINYINT(1) NOT NULL DEFAULT 0,

    must_change_password TINYINT(1) NOT NULL DEFAULT 0,

    password_expires_at DATETIME NULL,

    invited_at DATETIME NULL,

    activation_token_hash VARCHAR(255) NULL,

    activation_expires_at DATETIME NULL,

    activation_used TINYINT(1) NOT NULL DEFAULT 0,

    password_change_token_hash VARCHAR(64) NULL,

    password_change_expires_at DATETIME NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

);

CREATE TABLE admin_access (
    access_id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    access_area ENUM('technical','administrative','proctorial','lost_found') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_admin_access (admin_id, access_area),
    FOREIGN KEY (admin_id) REFERENCES admins(admin_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE admin_account_audit (
    audit_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_admin_id INT NULL,
    target_admin_id INT NULL,
    action VARCHAR(32) NOT NULL,
    details_json JSON NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_audit_target (target_admin_id, created_at),
    INDEX idx_admin_audit_actor (actor_admin_id, created_at),
    FOREIGN KEY (actor_admin_id) REFERENCES admins(admin_id) ON DELETE SET NULL,
    FOREIGN KEY (target_admin_id) REFERENCES admins(admin_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =========================================
-- REPORTS
-- =========================================

CREATE TABLE reports (

    report_id INT AUTO_INCREMENT PRIMARY KEY,

    ticket_id VARCHAR(50) UNIQUE NOT NULL,

    student_id INT NOT NULL,

    category ENUM(
        'technical',
        'administrative',
        'proctorial',
        'lost_found'
    ) NOT NULL,

    issue_type VARCHAR(100) NOT NULL,

    title VARCHAR(200) NOT NULL,

    description TEXT NOT NULL,

    location_text VARCHAR(200),

    priority ENUM(
        'Low',
        'Medium',
        'High'
    ) DEFAULT 'Medium',

    status ENUM(
        'Pending',
        'In Progress',
        'Resolved',
        'Rejected'
    ) DEFAULT 'Pending',

    is_confidential TINYINT(1) DEFAULT 0,

    item_state VARCHAR(50),

    image_path VARCHAR(255),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (student_id)
    REFERENCES students(student_id)
    ON DELETE CASCADE

);


-- =========================================
-- REPORT UPDATES
-- =========================================

CREATE TABLE report_updates (

    update_id INT AUTO_INCREMENT PRIMARY KEY,

    report_id INT NOT NULL,

    admin_id INT NOT NULL,

    status VARCHAR(50) NOT NULL,

    message TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (report_id)
    REFERENCES reports(report_id)
    ON DELETE CASCADE,

    FOREIGN KEY (admin_id)
    REFERENCES admins(admin_id)
    ON DELETE CASCADE

);


CREATE TABLE system_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL,
    updated_by INT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES admins(admin_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO system_settings (setting_key, setting_value)
VALUES ('student_account_creation_enabled', '1');
