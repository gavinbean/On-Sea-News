-- Add latitude and longitude to water deliveries table for map tracking
ALTER TABLE `bk_water_deliveries`
ADD COLUMN `latitude` DECIMAL(10, 8) NULL AFTER `user_id`,
ADD COLUMN `longitude` DECIMAL(11, 8) NULL AFTER `latitude`,
ADD INDEX `idx_location` (`latitude`, `longitude`);
