-- Create DB then use it
-- CREATE DATABASE debate_app DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hackleydebate; 

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(100) NOT NULL,
  last_name  VARCHAR(100) NOT NULL,
  email      VARCHAR(255) NOT NULL UNIQUE,
  phone      VARCHAR(30)  DEFAULT NULL,
  password_hash VARCHAR(255) NOT NULL,
  is_coach   TINYINT(1) NOT NULL DEFAULT 0,
  is_admin   TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE tournaments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  location VARCHAR(255) NOT NULL,
  start_date DATE NOT NULL,
  end_date   DATE NOT NULL,
  max_teams INT DEFAULT NULL,
  signup_deadline DATE DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (start_date)
) ENGINE=InnoDB;

CREATE TABLE signups (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tournament_id INT NOT NULL,
  created_by_user_id INT NOT NULL,
  go_maverick TINYINT(1) NOT NULL DEFAULT 0,
  comment TEXT,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_signups_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
  CONSTRAINT fk_signups_creator   FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Members of a signup (team). We store tournament_id redundantly to enforce uniqueness.
CREATE TABLE signup_members (
  id INT AUTO_INCREMENT PRIMARY KEY,
  signup_id INT NOT NULL,
  tournament_id INT NOT NULL,
  user_id INT NOT NULL,
  CONSTRAINT fk_sm_signup     FOREIGN KEY (signup_id)     REFERENCES signups(id)     ON DELETE CASCADE,
  CONSTRAINT fk_sm_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
  CONSTRAINT fk_sm_user       FOREIGN KEY (user_id)       REFERENCES users(id)       ON DELETE RESTRICT,
  UNIQUE KEY uniq_one_per_tournament (tournament_id, user_id)
) ENGINE=InnoDB;

-- Helpful seed (change email & pass later). Run once, then delete or disable.
INSERT INTO users (first_name,last_name,email,phone,password_hash,is_coach,is_admin)
VALUES ('Lilly','Rosenthal','lillyjrosenthal123@gmail.com',NULL, '$2y$10$9xH7Jq4v3o6s9k3y8i4rVOyWb0yBYZ5rW.0f9pZ.gG9K6l7lS6b2S', 1,1);
-- The above hash is for password: Admin123!

-- Application settings
CREATE TABLE settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  key_name VARCHAR(191) NOT NULL UNIQUE,
  value LONGTEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Default settings (optional)
INSERT INTO settings (key_name, value) VALUES
  ('email pattern', 'hackleyschool.org'),
  ('announcement', ''),
  ('new_user_message', '')
ON DUPLICATE KEY UPDATE value=VALUES(value);
