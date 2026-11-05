CREATE DATABASE IF NOT EXISTS smartrecruit CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE smartrecruit;
CREATE TABLE users(id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(120) NOT NULL,email VARCHAR(190) NOT NULL UNIQUE,password VARCHAR(255) NOT NULL,role ENUM('candidate','recruiter','admin') NOT NULL DEFAULT 'candidate',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE jobs(id INT AUTO_INCREMENT PRIMARY KEY,recruiter_id INT NOT NULL,title VARCHAR(200) NOT NULL,company VARCHAR(160) NOT NULL,location VARCHAR(160) NOT NULL DEFAULT '',salary VARCHAR(100) NOT NULL DEFAULT '',description TEXT NOT NULL,skills TEXT NOT NULL,status ENUM('pending','approved','rejected') DEFAULT 'pending',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(recruiter_id) REFERENCES users(id));
CREATE TABLE resumes(id INT AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL UNIQUE,filename VARCHAR(255) NOT NULL,original_name VARCHAR(255) NOT NULL,raw_text MEDIUMTEXT,skills TEXT,parsed_json LONGTEXT,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,FOREIGN KEY(user_id) REFERENCES users(id));
CREATE TABLE applications(id INT AUTO_INCREMENT PRIMARY KEY,job_id INT NOT NULL,candidate_id INT NOT NULL,score DECIMAL(5,2),semantic_score DECIMAL(5,2),skill_score DECIMAL(5,2),matched_skills TEXT,missing_skills TEXT,status ENUM('submitted','reviewing','shortlisted','rejected') DEFAULT 'submitted',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_apply(job_id,candidate_id),FOREIGN KEY(job_id) REFERENCES jobs(id),FOREIGN KEY(candidate_id) REFERENCES users(id));
-- SmartRecruit v2: CHẠY MỘT LẦN SAU KHI ĐÃ SAO LƯU DATABASE.
-- Không xóa hoặc thay đổi dữ liệu users, jobs, resumes, applications hiện có.
CREATE TABLE IF NOT EXISTS candidate_profiles (
 user_id INT PRIMARY KEY,
 headline VARCHAR(200) NOT NULL DEFAULT '', phone VARCHAR(30) NOT NULL DEFAULT '',
 location VARCHAR(150) NOT NULL DEFAULT '', bio TEXT,
 education TEXT, experience TEXT, portfolio VARCHAR(255) NOT NULL DEFAULT '',
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS recruiter_profiles (
 user_id INT PRIMARY KEY,
 company_name VARCHAR(160) NOT NULL DEFAULT '', website VARCHAR(255) NOT NULL DEFAULT '',
 company_size VARCHAR(50) NOT NULL DEFAULT '', about TEXT,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS job_details (
 job_id INT PRIMARY KEY,
 job_type ENUM('full_time','part_time','internship','contract') NOT NULL DEFAULT 'full_time',
 work_mode ENUM('onsite','hybrid','remote') NOT NULL DEFAULT 'onsite',
 level ENUM('intern','junior','mid','senior') NOT NULL DEFAULT 'junior',
 deadline DATE NULL, benefits TEXT,
 FOREIGN KEY(job_id) REFERENCES jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS saved_jobs (
 user_id INT NOT NULL, job_id INT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(user_id,job_id),
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY(job_id) REFERENCES jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS notifications (
 id INT AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL,content VARCHAR(255) NOT NULL,
 url VARCHAR(255) NOT NULL DEFAULT 'index.php?page=dashboard',is_read TINYINT(1) NOT NULL DEFAULT 0,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_notif_user(user_id,is_read),
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS user_flags (
 user_id INT PRIMARY KEY,is_active TINYINT(1) NOT NULL DEFAULT 1,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS application_notes (
 application_id INT PRIMARY KEY, note TEXT,
 FOREIGN KEY(application_id) REFERENCES applications(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS interviews (
 id INT AUTO_INCREMENT PRIMARY KEY,
 application_id INT NOT NULL UNIQUE,
 scheduled_at DATETIME NOT NULL,
 location VARCHAR(255) NOT NULL DEFAULT '',
 meeting_url VARCHAR(255) NOT NULL DEFAULT '',
 note TEXT,
 status ENUM('scheduled','completed','cancelled') NOT NULL DEFAULT 'scheduled',
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(application_id) REFERENCES applications(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- CHẠY TRÊN database smartrecruit hiện có, SAU KHI ĐÃ BACKUP SQL.
-- Không chạy database.sql khi nâng cấp. Không xóa tài khoản/tin/CV cũ.

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
