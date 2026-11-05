-- SmartRecruit v2: CHẠY MỘT LẦN SAU KHI ĐÃ SAO LƯU DATABASE.
-- Không xóa hoặc thay đổi dữ liệu users, jobs, resumes, applications hiện có.
USE smartrecruit;
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
