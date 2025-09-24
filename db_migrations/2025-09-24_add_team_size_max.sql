-- Add team_size_max column to tournaments table
-- This allows tournaments to optionally specify a maximum team size
-- NULL means no limit, positive integer means maximum team size
-- Maverick (solo) signups are always allowed regardless of this setting

ALTER TABLE tournaments ADD COLUMN team_size_max INT DEFAULT NULL AFTER max_teams;
