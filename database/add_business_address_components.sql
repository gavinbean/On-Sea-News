-- Add address component columns to businesses table
ALTER TABLE `bk_businesses`
ADD COLUMN `street_number` VARCHAR(20) NULL AFTER `address`,
ADD COLUMN `street_name` VARCHAR(255) NULL AFTER `street_number`,
ADD COLUMN `suburb` VARCHAR(255) NULL AFTER `street_name`,
ADD COLUMN `town` VARCHAR(255) NULL AFTER `suburb`;

-- Make the old address field nullable (it will be replaced by the components)
ALTER TABLE `bk_businesses`
MODIFY COLUMN `address` TEXT NULL;


