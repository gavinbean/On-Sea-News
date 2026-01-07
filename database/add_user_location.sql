-- Migration script to add latitude/longitude to users table and update water_availability table
-- Run this if you already have the database set up

-- Add location fields to users table
ALTER TABLE `bk_users` 
ADD COLUMN `latitude` DECIMAL(10,8) NULL AFTER `address`,
ADD COLUMN `longitude` DECIMAL(11,8) NULL AFTER `latitude`,
ADD INDEX `idx_location` (`latitude`, `longitude`);

-- Remove address, latitude, longitude from water_availability (they'll come from user profile)
ALTER TABLE `bk_water_availability`
DROP COLUMN IF EXISTS `address`,
DROP COLUMN IF EXISTS `latitude`,
DROP COLUMN IF EXISTS `longitude`,
DROP INDEX IF EXISTS `idx_location`;



