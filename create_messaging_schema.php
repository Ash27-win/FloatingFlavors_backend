<?php
require_once "config.php";

$sql = "
CREATE TABLE IF NOT EXISTS fcm_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    role ENUM('User', 'Admin', 'Delivery', 'Guest') DEFAULT 'User',
    token TEXT NOT NULL,
    device_info VARCHAR(255) DEFAULT NULL,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (user_id),
    INDEX (role)
);

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_id INT NOT NULL,
    recipient_role ENUM('User', 'Admin', 'Delivery') NOT NULL,
    title VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    type VARCHAR(100) NOT NULL,
    reference_id INT DEFAULT 0,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (recipient_id),
    INDEX (recipient_role),
    INDEX (is_read)
);
";

try {
    $pdo->exec($sql);
    echo "Tables fcm_tokens and notifications created successfully.\n";
} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}
