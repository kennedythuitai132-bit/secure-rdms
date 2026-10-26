<?php
// MySQL database bootstrap for the Secure Research Data Management System.
// Requires PHP 8.0+ with the PDO_MySQL extension enabled.

$host = getenv("RDMS_DB_HOST") ?: "127.0.0.1";
$port = getenv("RDMS_DB_PORT") ?: "3306";
$name = getenv("RDMS_DB_NAME") ?: "secure_rdms";
$user = getenv("RDMS_DB_USER") ?: "root";
$password = getenv("RDMS_DB_PASS") ?: "";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

// Connect to the server first so the application can create its own database.
$pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $password, $options);
$pdo->exec(
    "CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
);
$pdo->exec("USE `{$name}`");

$schema = [
    "CREATE TABLE IF NOT EXISTS users (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(150) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        full_name VARCHAR(120) NOT NULL,
        role ENUM('admin','researcher','viewer') NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_login DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS research_data (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS audit_logs (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS mfa_codes (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        code_hash VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        used TINYINT(1) NOT NULL DEFAULT 0,
        CONSTRAINT fk_mfa_user FOREIGN KEY (user_id) REFERENCES users(id),
        INDEX idx_mfa_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS security_alerts (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NULL,
        alert_type VARCHAR(60) NOT NULL,
        message VARCHAR(255) NOT NULL,
        severity ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
        resolved TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_alert_user FOREIGN KEY (user_id) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS login_attempts (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NULL,
        attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        success TINYINT(1) NOT NULL DEFAULT 0,
        INDEX idx_attempt_lookup (username, attempted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

foreach ($schema as $sql) {
    $pdo->exec($sql);
}

// Seed the local demonstration accounts once.
$count = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
if (!$count) {
    $seed = $pdo->prepare(
        "INSERT INTO users(username, email, password_hash, full_name, role) VALUES (?, ?, ?, ?, ?)",
    );
    $accounts = [
        ["admin", "admin@example.test", "Admin@123", "System Administrator", "admin"],
        [
            "researcher1",
            "researcher@example.test",
            "Researcher@123",
            "Researcher One",
            "researcher",
        ],
        ["viewer1", "viewer@example.test", "Viewer@123", "Viewer One", "viewer"],
    ];
    foreach ($accounts as [$username, $email, $plain, $fullName, $role]) {
        $seed->execute([
            $username,
            $email,
            password_hash($plain, PASSWORD_DEFAULT),
            $fullName,
            $role,
        ]);
    }
}
