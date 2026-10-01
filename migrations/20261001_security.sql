-- MariaDB 10.4: additive migration. Back up first; existing sessions/tokens expire.
ALTER TABLE users ADD COLUMN IF NOT EXISTS auth_version INT UNSIGNED NOT NULL DEFAULT 1;
ALTER TABLE users ADD COLUMN IF NOT EXISTS api_token VARCHAR(64) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS api_token_hash CHAR(64) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS api_token_expires_at DATETIME NULL;
CREATE TABLE IF NOT EXISTS agpms_password_resets (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL, token_hash CHAR(64) NOT NULL UNIQUE,
 expires_at DATETIME NOT NULL, consumed_at DATETIME NULL,
 INDEX(user_id, consumed_at), FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS agpms_reset_requests (
 request_key CHAR(64) NOT NULL PRIMARY KEY,
 window_start DATETIME NOT NULL, attempts INT UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS agpms_scan_events (
 event_id CHAR(36) PRIMARY KEY, actor_id INT NOT NULL,
 payload_hash CHAR(64) NOT NULL, result_json TEXT NOT NULL,
 received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(actor_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
UPDATE users SET api_token = NULL, api_token_hash = NULL, api_token_expires_at = NULL;
CREATE TABLE IF NOT EXISTS incident_reports (
 id INT AUTO_INCREMENT PRIMARY KEY, security_id INT NOT NULL,
 title VARCHAR(255) NOT NULL,
 category ENUM('trespassing','gate_damage','suspicious_activity','argument','other') NOT NULL DEFAULT 'other',
 description TEXT NOT NULL, severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
 status ENUM('reported','investigating','resolved') NOT NULL DEFAULT 'reported',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 resolved_by INT NULL, resolved_at DATETIME NULL,
 INDEX(status,created_at,id),
 FOREIGN KEY(security_id) REFERENCES users(id) ON DELETE RESTRICT,
 FOREIGN KEY(resolved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
