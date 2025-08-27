-- Migration: Add password reset columns to users
-- Applies to MySQL. Run these statements against your existing database.
-- Note: If columns already exist, you may need to adjust this migration accordingly.

ALTER TABLE users
  ADD COLUMN password_reset_token_hash CHAR(64) NULL DEFAULT NULL,
  ADD COLUMN password_reset_expires_at DATETIME NULL DEFAULT NULL;

-- Optional index to help clean up expired tokens or lookups (not strictly required)
CREATE INDEX idx_users_pwreset_expires ON users(password_reset_expires_at);
