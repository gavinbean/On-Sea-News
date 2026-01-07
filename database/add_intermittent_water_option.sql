-- Migration script to support intermittent water availability option
-- TINYINT(1) already supports values 0-255, so no schema change needed
-- This script is for documentation purposes

-- Values:
-- 0 = No, I do not have water (red)
-- 1 = Yes, I have water (green)
-- 2 = Intermittent, I have water at irregular intervals (orange)

-- No ALTER TABLE needed as TINYINT(1) already supports these values

