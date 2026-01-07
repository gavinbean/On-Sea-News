-- Add separate address component fields to users table
-- This migration adds street_number, street_name, suburb, and town fields

ALTER TABLE `bk_users`
ADD COLUMN `street_number` VARCHAR(20) NULL AFTER `address`,
ADD COLUMN `street_name` VARCHAR(255) NULL AFTER `street_number`,
ADD COLUMN `suburb` VARCHAR(100) NULL AFTER `street_name`,
ADD COLUMN `town` VARCHAR(100) NULL AFTER `suburb`;

-- Note: The original `address` field is kept for backward compatibility
-- You can populate the new fields from existing address data if needed

