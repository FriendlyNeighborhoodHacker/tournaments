-- Migration: Add has_ride to signup_members
-- Applies to MySQL. Run these statements against your existing database.
-- Purpose: Track whether each member on a tournament signup has a ride.

-- 1) Add new column to signup_members
-- Values:
--   NULL = unspecified (default)
--   0    = No
--   1    = Yes
-- Note: If the column already exists, remove this ALTER before running.
ALTER TABLE signup_members
  ADD COLUMN has_ride TINYINT(1) NULL DEFAULT NULL AFTER user_id;

-- 2) (Optional) Backfill / initialization strategy
-- If you want to explicitly set all existing rows to unspecified (NULL), do nothing (NULL is the default).
-- If you want to mark all existing rows to "unspecified" explicitly, you may run:
-- UPDATE signup_members SET has_ride = NULL WHERE has_ride IS NULL;

-- 3) (Optional) Verification query (for sanity check)
-- SELECT tournament_id, COUNT(*) AS members, SUM(has_ride = 1) AS yes_count, SUM(has_ride = 0) AS no_count, SUM(has_ride IS NULL) AS unspecified_count
-- FROM signup_members
-- GROUP BY tournament_id
-- ORDER BY tournament_id;
