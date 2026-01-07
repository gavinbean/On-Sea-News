-- Add building_name column to businesses table
ALTER TABLE `bk_businesses`
ADD COLUMN `building_name` VARCHAR(255) NULL AFTER `address`;

