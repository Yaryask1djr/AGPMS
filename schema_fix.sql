CREATE DATABASE IF NOT EXISTS agpms;
USE agpms;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS visit_logs, gate_passes, blacklist, residents, users;

CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    house_no VARCHAR(20),
    phone VARCHAR(20) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('resident', 'security', 'admin') NOT NULL,
    status ENUM('pending', 'active', 'revoked') DEFAULT 'pending',
    approved TINYINT(1) DEFAULT 0,
    profile_pic VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE residents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    house_no VARCHAR(20),
    phone VARCHAR(20),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE gate_passes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    visitor_name VARCHAR(100) NOT NULL,
    contact VARCHAR(20) NOT NULL,
    purpose TEXT NOT NULL,
    pass_type ENUM('one_time', 'recurring', 'delivery') DEFAULT 'one_time',
    vehicle_details VARCHAR(100),
    entry_time DATETIME NOT NULL,
    exit_time DATETIME NOT NULL,
    status ENUM('pending', 'active', 'checked_in', 'checked_out', 'expired', 'revoked') DEFAULT 'pending',
    token VARCHAR(36) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE visit_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    pass_id INT NOT NULL,
    check_in_time DATETIME NOT NULL,
    check_out_time DATETIME NULL,
    security_id INT NULL,
    FOREIGN KEY (pass_id) REFERENCES gate_passes(id) ON DELETE CASCADE,
    FOREIGN KEY (security_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE blacklist (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100),
    phone VARCHAR(20) NOT NULL UNIQUE,
    reason TEXT,
    added_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL
);

SET FOREIGN_KEY_CHECKS = 1;
