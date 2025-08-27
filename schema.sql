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
  email_verify_token VARCHAR(64) DEFAULT NULL,
  email_verified_at DATETIME DEFAULT NULL,
  password_reset_token_hash CHAR(64) DEFAULT NULL,
  password_reset_expires_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Indexes for users
CREATE INDEX idx_users_email_verify_token ON users(email_verify_token);
CREATE INDEX idx_users_pwreset_expires ON users(password_reset_expires_at);

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
  has_ride TINYINT(1) NULL DEFAULT NULL,
  CONSTRAINT fk_sm_signup     FOREIGN KEY (signup_id)     REFERENCES signups(id)     ON DELETE CASCADE,
  CONSTRAINT fk_sm_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
  CONSTRAINT fk_sm_user       FOREIGN KEY (user_id)       REFERENCES users(id)       ON DELETE RESTRICT,
  UNIQUE KEY uniq_one_per_tournament (tournament_id, user_id)
) ENGINE=InnoDB;

-- Judges (people who can judge), sponsored by a user
CREATE TABLE judges (
  id INT AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(100) NOT NULL,
  last_name  VARCHAR(100) NOT NULL,
  email      VARCHAR(255) DEFAULT NULL,
  phone      VARCHAR(30)  DEFAULT NULL,
  sponsor_id INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_judges_sponsor FOREIGN KEY (sponsor_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Indexes for judges
CREATE INDEX idx_judges_sponsor_id ON judges(sponsor_id);
CREATE INDEX idx_judges_name ON judges(last_name, first_name);

-- Judges attached directly to a tournament (not tied to a signup)
CREATE TABLE tournament_judges (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tournament_id INT NOT NULL,
  judge_id  INT NOT NULL,
  CONSTRAINT fk_tj_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
  CONSTRAINT fk_tj_judge     FOREIGN KEY (judge_id)      REFERENCES judges(id)      ON DELETE CASCADE,
  UNIQUE KEY uniq_tj (tournament_id, judge_id)
) ENGINE=InnoDB;

-- Helpful indexes for tournament_judges
CREATE INDEX idx_tj_tournament_id ON tournament_judges(tournament_id);
CREATE INDEX idx_tj_judge_id ON tournament_judges(judge_id);

-- Judges attached to a team signup
CREATE TABLE signup_judges (
  id INT AUTO_INCREMENT PRIMARY KEY,
  signup_id INT NOT NULL,
  judge_id  INT NOT NULL,
  CONSTRAINT fk_sj_signup FOREIGN KEY (signup_id) REFERENCES signups(id) ON DELETE CASCADE,
  CONSTRAINT fk_sj_judge  FOREIGN KEY (judge_id)  REFERENCES judges(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_signup_judge (signup_id, judge_id)
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
  ('announcement', '')
ON DUPLICATE KEY UPDATE value=VALUES(value);
