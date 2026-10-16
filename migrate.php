<?php
if (PHP_SAPI !== "cli") { http_response_code(404); exit; }
require_once 'includes/db.php';

$q1 = "CREATE TABLE IF NOT EXISTS blacklist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    phone VARCHAR(20) NOT NULL UNIQUE,
    reason TEXT,
    added_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

try {
    $conn->query($q1);
    echo "Blacklist table created.\n";
} catch (Exception $e) {
    echo "Blacklist: " . $e->getMessage() . "\n";
}

$q2 = "ALTER TABLE gate_passes ADD COLUMN pass_type ENUM('one_time', 'recurring', 'delivery') DEFAULT 'one_time' AFTER purpose";

try {
    $conn->query($q2);
    echo "pass_type column added.\n";
} catch (Exception $e) {
    echo "pass_type already exists, skipping.\n";
}

$q3 = "ALTER TABLE users ADD COLUMN dues_status ENUM('cleared', 'defaulter') DEFAULT 'cleared' AFTER approved";

try {
    $conn->query($q3);
    echo "dues_status column added.\n";
} catch (Exception $e) {
    echo "dues_status already exists, skipping.\n";
}

// Panic Alerts table
$q4 = "CREATE TABLE IF NOT EXISTS panic_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    house_no VARCHAR(10),
    message TEXT,
    status ENUM('active', 'resolved') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_by INT NULL,
    resolved_at DATETIME NULL
)";

try {
    $conn->query($q4);
    echo "panic_alerts table created.\n";
} catch (Exception $e) {
    echo "panic_alerts: " . $e->getMessage() . "\n";
}

// Notifications table
$q5 = "CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255),
    message TEXT,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

try {
    $conn->query($q5);
    echo "notifications table created.\n";
} catch (Exception $e) {
    echo "notifications: " . $e->getMessage() . "\n";
}

// Admin Activity Log table
$q6 = "CREATE TABLE IF NOT EXISTS admin_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

try {
    $conn->query($q6);
    echo "admin_activity_log table created.\n";
} catch (Exception $e) {
    echo "admin_activity_log: " . $e->getMessage() . "\n";
}

echo "Migration complete.\n";
?>
