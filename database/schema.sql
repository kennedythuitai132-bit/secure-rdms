-- Secure Research Data Management System (RDMS)
-- MySQL 8.0+ / MariaDB 10.4+ schema and demonstration data.
-- Import this file in phpMyAdmin or run:
--   mysql -u root -p < database/schema.sql

CREATE DATABASE IF NOT EXISTS secure_rdms
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE secure_rdms;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(120) NOT NULL,
    role ENUM('admin','researcher','viewer') NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS research_data (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size BIGINT UNSIGNED NULL,
    mime_type VARCHAR(120) NULL,
    uploaded_by INT UNSIGNED NOT NULL,
    project_code VARCHAR(50) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_research_user FOREIGN KEY (uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    username VARCHAR(50) NULL,
    action VARCHAR(60) NOT NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    status ENUM('success','failed') NOT NULL DEFAULT 'success',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_action (action),
    INDEX idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mfa_codes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_mfa_user FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_mfa_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS security_alerts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    alert_type VARCHAR(60) NOT NULL,
    message VARCHAR(255) NOT NULL,
    severity ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
    resolved TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_alert_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS login_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    success TINYINT(1) NOT NULL DEFAULT 0,
    INDEX idx_attempt_lookup (username, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Demonstration accounts (password hashes generated with PHP password_hash).
-- admin / Admin@123          researcher1 / Researcher@123       viewer1 / Viewer@123
INSERT INTO users (username, email, password_hash, full_name, role) VALUES
    ('admin', 'admin@example.test', '$2y$10$c2Z6gNSpDdbTBrWWyLOVUeRvQISL5f1AF5jweJ6GNvxyVknDQqEiy', 'System Administrator', 'admin'),
    ('researcher1', 'researcher@example.test', '$2y$10$xEwoSvbafATB2Wiu/ZEk9OQNECDG9w9z3sHAd3Dq6WcNmrBP0EguK', 'Researcher One', 'researcher'),
    ('viewer1', 'viewer@example.test', '$2y$10$hvIgI93/ed6kUvLtgbtl8.pWSgI5IR10UePqSXuIA7d6X0qvgLBtK', 'Viewer One', 'viewer')
ON DUPLICATE KEY UPDATE username = username;
