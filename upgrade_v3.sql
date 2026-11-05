-- CHẠY TRÊN database smartrecruit hiện có, SAU KHI ĐÃ BACKUP SQL.
-- Không chạy database.sql khi nâng cấp. Không xóa tài khoản/tin/CV cũ.
USE smartrecruit;
CREATE TABLE IF NOT EXISTS cv_consents (
 user_id INT PRIMARY KEY,
 consent_version VARCHAR(40) NOT NULL,
 consented_at DATETIME NOT NULL,
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS audit_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 actor_id INT NULL,
 action VARCHAR(70) NOT NULL,
 entity_type VARCHAR(40) NOT NULL,
 entity_id INT NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_audit_time (created_at), KEY idx_audit_actor (actor_id),
 FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
