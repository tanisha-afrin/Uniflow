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

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

);


-- =========================================
-- ADMINS
-- =========================================

CREATE TABLE admins (

    admin_id INT AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(100) NOT NULL,

    email VARCHAR(150) UNIQUE NOT NULL,

    password VARCHAR(255) NOT NULL,

    role ENUM(
        'technical',
        'administrative',
        'proctorial',
        'lost_found'
    ) NOT NULL,

    is_active TINYINT(1) NOT NULL DEFAULT 1,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

);


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