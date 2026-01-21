-- Add contact information and address fields to water delivery companies
ALTER TABLE `bk_water_delivery_companies`
ADD COLUMN `contact_name` VARCHAR(200) NULL AFTER `company_name`,
ADD COLUMN `telephone` VARCHAR(20) NULL AFTER `contact_name`,
ADD COLUMN `street_number` VARCHAR(50) NULL AFTER `telephone`,
ADD COLUMN `street_name` VARCHAR(200) NULL AFTER `street_number`,
ADD COLUMN `suburb` VARCHAR(100) NULL AFTER `street_name`,
ADD COLUMN `town` VARCHAR(100) NULL AFTER `suburb`,
ADD COLUMN `latitude` DECIMAL(10, 8) NULL AFTER `town`,
ADD COLUMN `longitude` DECIMAL(11, 8) NULL AFTER `latitude`,
ADD INDEX `idx_location` (`latitude`, `longitude`);
