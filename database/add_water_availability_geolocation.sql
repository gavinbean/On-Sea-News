-- Migration script to add latitude/longitude to water_availability table
-- This allows multiple users at the same address to update the same record
-- and ensures map points don't move when users change their addresses

-- Add latitude and longitude columns to water_availability table
ALTER TABLE `bk_water_availability` 
ADD COLUMN `latitude` DECIMAL(10,8) NULL AFTER `has_water`,
ADD COLUMN `longitude` DECIMAL(11,8) NULL AFTER `latitude`;

-- Remove the old unique constraint on user_id and report_date
ALTER TABLE `bk_water_availability`
DROP INDEX IF EXISTS `unique_user_date`;

-- Add new unique constraint on report_date, latitude, and longitude
-- This prevents duplicate records for the same location on the same date
ALTER TABLE `bk_water_availability`
ADD UNIQUE KEY `unique_location_date` (`report_date`, `latitude`, `longitude`);

-- Add index for geolocation queries
ALTER TABLE `bk_water_availability`
ADD INDEX `idx_location` (`latitude`, `longitude`);

-- Note: After running this script, run the migration script:
-- database/update_water_availability_coordinates.php
-- to populate existing records with coordinates from user profiles

