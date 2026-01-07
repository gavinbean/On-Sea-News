-- Add geolocation columns to businesses table
ALTER TABLE `bk_businesses`
ADD COLUMN `latitude` DECIMAL(10, 8) NULL AFTER `town`,
ADD COLUMN `longitude` DECIMAL(11, 8) NULL AFTER `latitude`;

-- Add index for geolocation queries
ALTER TABLE `bk_businesses`
ADD INDEX `idx_location` (`latitude`, `longitude`);


