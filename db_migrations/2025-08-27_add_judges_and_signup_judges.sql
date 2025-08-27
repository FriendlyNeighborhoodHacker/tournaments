-- Migration: Add judges and signup_judges tables
-- Applies to MySQL. Run these statements against your existing database.

-- 1) Judges (people who can judge), sponsored by a user
-- ON DELETE CASCADE on sponsor_id ensures that when a sponsoring user is deleted,
--   their judges are deleted as well (and, due to cascade in signup_judges below,
--   any of their signup_judges rows are also removed).
CREATE TABLE IF NOT EXISTS judges (
  id INT AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(100) NOT NULL,
  last_name  VARCHAR(100) NOT NULL,
  email      VARCHAR(255) DEFAULT NULL,
  phone      VARCHAR(30)  DEFAULT NULL,
  sponsor_id INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_judges_sponsor FOREIGN KEY (sponsor_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Helpful indexes (optional)
CREATE INDEX idx_judges_sponsor_id ON judges(sponsor_id);
CREATE INDEX idx_judges_name ON judges(last_name, first_name);

-- 2) Judges attached to a team signup
-- ON DELETE CASCADE on judge_id and signup_id keeps associations clean when
--   either a judge or a signup is removed.
CREATE TABLE IF NOT EXISTS signup_judges (
  id INT AUTO_INCREMENT PRIMARY KEY,
  signup_id INT NOT NULL,
  judge_id  INT NOT NULL,
  CONSTRAINT fk_sj_signup FOREIGN KEY (signup_id) REFERENCES signups(id) ON DELETE CASCADE,
  CONSTRAINT fk_sj_judge  FOREIGN KEY (judge_id)  REFERENCES judges(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_signup_judge (signup_id, judge_id)
) ENGINE=InnoDB;

-- 3) Verification queries (optional)
-- List judges:
--   SELECT j.id, j.first_name, j.last_name, u.first_name AS sponsor_first, u.last_name AS sponsor_last
--   FROM judges j JOIN users u ON u.id=j.sponsor_id ORDER BY j.last_name, j.first_name;
--
-- List judges per signup:
--   SELECT sj.signup_id, j.first_name, j.last_name
--   FROM signup_judges sj JOIN judges j ON j.id=sj.judge_id
--   ORDER BY sj.signup_id, j.last_name, j.first_name;
