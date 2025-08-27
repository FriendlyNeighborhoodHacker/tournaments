-- Migration: Add email verification support to users table
-- Applies to MySQL. Run these statements against your existing database.

-- 1) Add new columns to users
-- Note: If these columns already exist, remove the corresponding ADD COLUMN
-- lines before running or run them individually as needed.
ALTER TABLE users
  ADD COLUMN email_verify_token VARCHAR(64) NULL AFTER is_admin,
  ADD COLUMN email_verified_at DATETIME NULL AFTER email_verify_token;

-- 2) (Optional but recommended) Create an index for faster lookups by token
-- Remove this if you do not want an additional index.
CREATE INDEX idx_users_email_verify_token ON users(email_verify_token);

-- 3) (Optional) Mark existing users as verified to avoid locking them out.
-- If you prefer to require existing users to verify, skip this step.
UPDATE users
SET email_verified_at = NOW()
WHERE email_verified_at IS NULL;
