-- Migration script to add email verification fields to existing database
-- Run this if you already have the database set up

ALTER TABLE `bk_users` 
ADD COLUMN `email_verified` TINYINT(1) NOT NULL DEFAULT 0 AFTER `terms_accepted_date`,
ADD COLUMN `email_verification_token` VARCHAR(255) NULL AFTER `email_verified`,
ADD COLUMN `email_verification_expires` DATETIME NULL AFTER `email_verification_token`,
ADD INDEX `idx_email_verified` (`email_verified`),
ADD INDEX `idx_email_token` (`email_verification_token`);

-- Set existing users as verified (optional - remove if you want to verify existing users)
-- UPDATE `bk_users` SET `email_verified` = 1;



