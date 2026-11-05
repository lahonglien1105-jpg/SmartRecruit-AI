-- SmartRecruit v5 email + phỏng vấn: sao lưu database trước khi chạy.
-- Chạy sau database.sql, upgrade.sql, upgrade_v3.sql.
USE smartrecruit;
CREATE TABLE IF NOT EXISTS user_emails (
 user_id INT PRIMARY KEY,
 verified_at DATETIME NULL,
 token_hash CHAR(64) NULL,
 token_expires_at DATETIME NULL,
 last_sent_at DATETIME NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_user_email_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS notification_preferences (
 user_id INT PRIMARY KEY,
 email_application TINYINT(1) NOT NULL DEFAULT 1,
 email_status TINYINT(1) NOT NULL DEFAULT 1,
 email_interview TINYINT(1) NOT NULL DEFAULT 1,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS email_outbox (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT NULL,
 recipient_email VARCHAR(190) NOT NULL,
 subject VARCHAR(240) NOT NULL,
 body_html MEDIUMTEXT NOT NULL,
 body_text TEXT,
 event_key VARCHAR(140) NOT NULL UNIQUE,
 status ENUM('pending','sending','sent','failed') NOT NULL DEFAULT 'pending',
 attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
 next_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 last_error VARCHAR(350) NULL,
 provider_message_id VARCHAR(128) NULL,
 sent_at DATETIME NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_outbox_retry (status,next_attempt_at),
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS interview_responses (
 application_id INT PRIMARY KEY,
 response ENUM('accepted','reschedule') NOT NULL,
 note TEXT NULL,
 responded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(application_id) REFERENCES applications(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
