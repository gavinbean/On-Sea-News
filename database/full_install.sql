-- Full database install script for On-Sea News / Busken
-- Run this on a NEW, EMPTY database.
-- Assumes you've already created the database and selected it with:
--   CREATE DATABASE your_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--   USE your_db;

/* ======================================================================
   Base schema (from schema.sql)
   ====================================================================== */

-- On-Sea News Community Website Database Schema
-- All tables prefixed with bk_

-- Users table
CREATE TABLE IF NOT EXISTS `bk_users` (
  `user_id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `surname` VARCHAR(100) NOT NULL,
  `telephone` VARCHAR(20) NOT NULL,
  `address` TEXT NOT NULL,
  `terms_accepted` TINYINT(1) NOT NULL DEFAULT 0,
  `terms_accepted_date` DATETIME NULL,
  `email_verified` TINYINT(1) NOT NULL DEFAULT 0,
  `email_verification_token` VARCHAR(255) NULL,
  `email_verification_expires` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `password_reset_token` VARCHAR(255) NULL,
  `password_reset_expires` DATETIME NULL,
  PRIMARY KEY (`user_id`),
  INDEX `idx_username` (`username`),
  INDEX `idx_email` (`email`),
  INDEX `idx_email_verified` (`email_verified`),
  INDEX `idx_email_token` (`email_verification_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User roles table
CREATE TABLE IF NOT EXISTS `bk_roles` (
  `role_id` INT(11) NOT NULL AUTO_INCREMENT,
  `role_name` VARCHAR(50) NOT NULL UNIQUE,
  `role_description` TEXT NULL,
  PRIMARY KEY (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User-role mapping (many-to-many)
CREATE TABLE IF NOT EXISTS `bk_user_roles` (
  `user_role_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `role_id` INT(11) NOT NULL,
  `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_role_id`),
  UNIQUE KEY `unique_user_role` (`user_id`, `role_id`),
  FOREIGN KEY (`user_id`) REFERENCES `bk_users`(`user_id`) ON DELETE CASCADE,
  FOREIGN KEY (`role_id`) REFERENCES `bk_roles`(`role_id`) ON DELETE CASCADE,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_role_id` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- News items table
CREATE TABLE IF NOT EXISTS `bk_news` (
  `news_id` INT(11) NOT NULL AUTO_INCREMENT,
  `author_id` INT(11) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `content` TEXT NOT NULL,
  `excerpt` VARCHAR(500) NULL,
  `featured_image` VARCHAR(255) NULL,
  `published` TINYINT(1) NOT NULL DEFAULT 0,
  `published_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`news_id`),
  FOREIGN KEY (`author_id`) REFERENCES `bk_users`(`user_id`) ON DELETE RESTRICT,
  INDEX `idx_author` (`author_id`),
  INDEX `idx_published` (`published`, `published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Advertiser accounts table
CREATE TABLE IF NOT EXISTS `bk_advertiser_accounts` (
  `account_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`account_id`),
  FOREIGN KEY (`user_id`) REFERENCES `bk_users`(`user_id`) ON DELETE RESTRICT,
  UNIQUE KEY `unique_user_account` (`user_id`),
  INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Business categories table
CREATE TABLE IF NOT EXISTS `bk_business_categories` (
  `category_id` INT(11) NOT NULL AUTO_INCREMENT,
  `category_name` VARCHAR(100) NOT NULL UNIQUE,
  `category_slug` VARCHAR(100) NOT NULL UNIQUE,
  `display_order` INT(11) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`category_id`),
  INDEX `idx_slug` (`category_slug`),
  INDEX `idx_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Businesses table (base)
CREATE TABLE IF NOT EXISTS `bk_businesses` (
  `business_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `category_id` INT(11) NOT NULL,
  `business_name` VARCHAR(255) NOT NULL,
  `contact_name` VARCHAR(100) NULL,
  `telephone` VARCHAR(20) NOT NULL,
  `email` VARCHAR(100) NULL,
  `address` TEXT NULL,
  `website` VARCHAR(255) NULL,
  `description` TEXT NULL,
  `has_paid_subscription` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`business_id`),
  FOREIGN KEY (`user_id`) REFERENCES `bk_users`(`user_id`) ON DELETE RESTRICT,
  FOREIGN KEY (`category_id`) REFERENCES `bk_business_categories`(`category_id`) ON DELETE RESTRICT,
  INDEX `idx_category` (`category_id`),
  INDEX `idx_user` (`user_id`),
  INDEX `idx_paid` (`has_paid_subscription`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Advertisements table
CREATE TABLE IF NOT EXISTS `bk_advertisements` (
  `advert_id` INT(11) NOT NULL AUTO_INCREMENT,
  `business_id` INT(11) NOT NULL,
  `account_id` INT(11) NOT NULL,
  `advert_image` VARCHAR(255) NOT NULL,
  `advert_url` VARCHAR(500) NULL,
  `advert_title` VARCHAR(255) NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `monthly_fee` DECIMAL(10,2) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`advert_id`),
  FOREIGN KEY (`business_id`) REFERENCES `bk_businesses`(`business_id`) ON DELETE CASCADE,
  FOREIGN KEY (`account_id`) REFERENCES `bk_advertiser_accounts`(`account_id`) ON DELETE RESTRICT,
  INDEX `idx_business` (`business_id`),
  INDEX `idx_active` (`is_active`, `start_date`, `end_date`),
  INDEX `idx_dates` (`start_date`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Advertisement click tracking
CREATE TABLE IF NOT EXISTS `bk_advert_clicks` (
  `click_id` INT(11) NOT NULL AUTO_INCREMENT,
  `advert_id` INT(11) NOT NULL,
  `clicked_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  PRIMARY KEY (`click_id`),
  FOREIGN KEY (`advert_id`) REFERENCES `bk_advertisements`(`advert_id`) ON DELETE CASCADE,
  INDEX `idx_advert` (`advert_id`),
  INDEX `idx_date` (`clicked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Advertisement payments/transactions
CREATE TABLE IF NOT EXISTS `bk_advert_transactions` (
  `transaction_id` INT(11) NOT NULL AUTO_INCREMENT,
  `account_id` INT(11) NOT NULL,
  `advert_id` INT(11) NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `transaction_type` ENUM('payment', 'refund', 'fee') NOT NULL,
  `description` TEXT NULL,
  `transaction_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`transaction_id`),
  FOREIGN KEY (`account_id`) REFERENCES `bk_advertiser_accounts`(`account_id`) ON DELETE RESTRICT,
  FOREIGN KEY (`advert_id`) REFERENCES `bk_advertisements`(`advert_id`) ON DELETE SET NULL,
  INDEX `idx_account` (`account_id`),
  INDEX `idx_date` (`transaction_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Water availability tracking (base)
CREATE TABLE IF NOT EXISTS `bk_water_availability` (
  `water_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NULL,
  `report_date` DATE NOT NULL,
  `has_water` TINYINT(1) NOT NULL DEFAULT 1,
  `notes` TEXT NULL,
  `reported_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`water_id`),
  FOREIGN KEY (`user_id`) REFERENCES `bk_users`(`user_id`) ON DELETE RESTRICT,
  INDEX `idx_date` (`report_date`),
  INDEX `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Frequently Asked Questions (FAQ)
CREATE TABLE IF NOT EXISTS `bk_faq` (
  `faq_id` INT(11) NOT NULL AUTO_INCREMENT,
  `question` VARCHAR(255) NOT NULL,
  `answer` TEXT NOT NULL,
  `display_order` INT(11) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`faq_id`),
  INDEX `idx_active_order` (`is_active`, `display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Session table for CAPTCHA and sessions
CREATE TABLE IF NOT EXISTS `bk_sessions` (
  `session_id` VARCHAR(128) NOT NULL,
  `session_data` TEXT NOT NULL,
  `expires` INT(11) NOT NULL,
  PRIMARY KEY (`session_id`),
  INDEX `idx_expires` (`expires`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default roles
INSERT INTO `bk_roles` (`role_name`, `role_description`) VALUES
('USER', 'Regular user'),
('ADMIN', 'Administrator with full access'),
('PUBLISHER', 'Can publish news articles'),
('ADVERTISER', 'Can manage advertisements'),
('DATA_CAPTURER', 'Can capture data and manage content'),
('USER_ADMIN', 'User administrator - can manage users but cannot modify roles or delete users')
ON DUPLICATE KEY UPDATE role_name=role_name;


/* ======================================================================
   User address components (add_address_components.sql)
   ====================================================================== */

-- Add separate address component fields to users table
-- This migration adds street_number, street_name, suburb, and town fields

ALTER TABLE `bk_users`
ADD COLUMN `street_number` VARCHAR(20) NULL AFTER `address`,
ADD COLUMN `street_name` VARCHAR(255) NULL AFTER `street_number`,
ADD COLUMN `suburb` VARCHAR(100) NULL AFTER `street_name`,
ADD COLUMN `town` VARCHAR(100) NULL AFTER `suburb`;

-- Note: The original `address` field is kept for backward compatibility


/* ======================================================================
   Make user address nullable (make_address_nullable.sql)
   ====================================================================== */

-- Make address field nullable since we now use component fields
-- The address field will be auto-generated from components for backward compatibility

ALTER TABLE `bk_users`
MODIFY COLUMN `address` TEXT NULL;


/* ======================================================================
   User geolocation (add_user_location.sql)
   ====================================================================== */

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


/* ======================================================================
   Business address components (add_business_address_components.sql)
   ====================================================================== */

-- Add address component columns to businesses table
ALTER TABLE `bk_businesses`
ADD COLUMN `street_number` VARCHAR(20) NULL AFTER `address`,
ADD COLUMN `street_name` VARCHAR(255) NULL AFTER `street_number`,
ADD COLUMN `suburb` VARCHAR(255) NULL AFTER `street_name`,
ADD COLUMN `town` VARCHAR(255) NULL AFTER `suburb`;

-- Make the old address field nullable (it will be replaced by the components)
ALTER TABLE `bk_businesses`
MODIFY COLUMN `address` TEXT NULL;


/* ======================================================================
   Allow NULL user_id for businesses + telephone optional (allow_null_user_id_businesses.sql)
   ====================================================================== */

-- Allow user_id to be NULL for imported businesses
-- First, drop the foreign key constraint
ALTER TABLE `bk_businesses`
DROP FOREIGN KEY IF EXISTS `bk_businesses_ibfk_1`;

-- Modify the column to allow NULL
ALTER TABLE `bk_businesses` 
MODIFY COLUMN `user_id` INT(11) NULL;

-- Re-add the foreign key constraint (it will allow NULL values)
ALTER TABLE `bk_businesses`
ADD CONSTRAINT `bk_businesses_ibfk_1` 
FOREIGN KEY (`user_id`) REFERENCES `bk_users`(`user_id`) ON DELETE RESTRICT;

-- Make telephone optional for imported businesses
ALTER TABLE `bk_businesses`
MODIFY COLUMN `telephone` VARCHAR(20) NULL;


/* ======================================================================
   Business geolocation (add_business_geolocation.sql)
   ====================================================================== */

-- Add geolocation columns to businesses table
ALTER TABLE `bk_businesses`
ADD COLUMN `latitude` DECIMAL(10, 8) NULL AFTER `town`,
ADD COLUMN `longitude` DECIMAL(11, 8) NULL AFTER `latitude`;

-- Add index for geolocation queries
ALTER TABLE `bk_businesses`
ADD INDEX `idx_location` (`latitude`, `longitude`);


/* ======================================================================
   Business approval fields (add_business_approval.sql)
   ====================================================================== */

-- Add approval status to businesses table
ALTER TABLE `bk_businesses` 
ADD COLUMN `is_approved` TINYINT(1) NOT NULL DEFAULT 0 AFTER `has_paid_subscription`,
ADD COLUMN `approved_at` DATETIME NULL AFTER `is_approved`,
ADD COLUMN `approved_by` INT(11) NULL AFTER `approved_at`,
ADD INDEX `idx_approved` (`is_approved`);

-- Set existing businesses as approved (for backward compatibility)
UPDATE `bk_businesses` SET `is_approved` = 1 WHERE `is_approved` = 0;

-- Add foreign key for approved_by
ALTER TABLE `bk_businesses`
ADD CONSTRAINT `fk_businesses_approved_by` 
FOREIGN KEY (`approved_by`) REFERENCES `bk_users`(`user_id`) ON DELETE SET NULL;


/* ======================================================================
   Business building name (add_business_building_name.sql)
   ====================================================================== */

-- Add building_name column to businesses table
ALTER TABLE `bk_businesses`
ADD COLUMN `building_name` VARCHAR(255) NULL AFTER `address`;


/* ======================================================================
   News pinned field (add_news_pinned_field.sql)
   ====================================================================== */

-- Add is_pinned field to news table
ALTER TABLE `bk_news`
ADD COLUMN `is_pinned` TINYINT(1) NOT NULL DEFAULT 0 AFTER `published_at`;

-- Add index for better query performance
ALTER TABLE `bk_news`
ADD INDEX `idx_pinned_published` (`is_pinned`, `published`, `published_at`);


/* ======================================================================
   Remember-me tokens (add_remember_tokens_table.sql)
   ====================================================================== */

-- Add remember_tokens table for "Remember Me" functionality
CREATE TABLE IF NOT EXISTS `bk_remember_tokens` (
  `token_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `token_hash` VARCHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`token_id`),
  UNIQUE KEY `unique_user_id` (`user_id`),
  UNIQUE KEY `unique_token_hash` (`token_hash`),
  FOREIGN KEY (`user_id`) REFERENCES `bk_users`(`user_id`) ON DELETE CASCADE,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_token_hash` (`token_hash`),
  INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


/* ======================================================================
   Water availability geolocation (add_water_availability_geolocation.sql)
   ====================================================================== */

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


/* ======================================================================
   Analytics role (add_analytics_role.sql)
   ====================================================================== */

-- Add ANALYTICS role
INSERT INTO `bk_roles` (`role_name`, `role_description`) VALUES
('ANALYTICS', 'Can view water analytics and reports')
ON DUPLICATE KEY UPDATE role_name=role_name;


/* ======================================================================
   Remove unique constraint on email (remove_email_unique.sql)
   ====================================================================== */

-- Remove unique constraint from email column
-- This allows the same email to be used on different profiles

ALTER TABLE `bk_users` 
DROP INDEX `email`;


/* ======================================================================
   Water questions system (create_water_questions_system.sql)
   ====================================================================== */

-- Water Questions System
-- Dynamic question/answer system for water information

-- Questions table
CREATE TABLE IF NOT EXISTS `bk_water_questions` (
  `question_id` INT(11) NOT NULL AUTO_INCREMENT,
  `question_text` TEXT NOT NULL,
  `question_type` ENUM('radio', 'checkbox', 'dropdown', 'text', 'textarea') NOT NULL,
  `page_tag` VARCHAR(50) NOT NULL DEFAULT 'water_info',
  `display_order` INT(11) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `is_required` TINYINT(1) NOT NULL DEFAULT 0,
  `depends_on_question_id` INT(11) NULL,
  `depends_on_answer_value` VARCHAR(255) NULL,
  `help_text` TEXT NULL,
  `terms_link` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`question_id`),
  INDEX `idx_page_tag` (`page_tag`),
  INDEX `idx_active` (`is_active`),
  INDEX `idx_display_order` (`display_order`),
  INDEX `idx_depends_on` (`depends_on_question_id`),
  FOREIGN KEY (`depends_on_question_id`) REFERENCES `bk_water_questions`(`question_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Question options (for radio, checkbox, dropdown)
CREATE TABLE IF NOT EXISTS `bk_water_question_options` (
  `option_id` INT(11) NOT NULL AUTO_INCREMENT,
  `question_id` INT(11) NOT NULL,
  `option_value` VARCHAR(255) NOT NULL,
  `option_text` VARCHAR(255) NOT NULL,
  `display_order` INT(11) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`option_id`),
  INDEX `idx_question` (`question_id`),
  INDEX `idx_display_order` (`display_order`),
  FOREIGN KEY (`question_id`) REFERENCES `bk_water_questions`(`question_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User responses to questions
CREATE TABLE IF NOT EXISTS `bk_water_user_responses` (
  `response_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `question_id` INT(11) NOT NULL,
  `response_value` TEXT NULL,
  `response_text` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`response_id`),
  UNIQUE KEY `unique_user_question` (`user_id`, `question_id`),
  INDEX `idx_user` (`user_id`),
  INDEX `idx_question` (`question_id`),
  INDEX `idx_updated` (`updated_at`),
  FOREIGN KEY (`user_id`) REFERENCES `bk_users`(`user_id`) ON DELETE CASCADE,
  FOREIGN KEY (`question_id`) REFERENCES `bk_water_questions`(`question_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


/* ======================================================================
   Populate water questions (populate_water_questions.sql)
   ====================================================================== */

-- Populate initial water questions and options

-- Question 1: Do you have tanks on your property?
INSERT INTO `bk_water_questions` (`question_text`, `question_type`, `page_tag`, `display_order`, `is_active`, `is_required`) 
VALUES ('Do you have tanks on your property?', 'dropdown', 'water_info', 1, 1, 1);

SET @q1_id = LAST_INSERT_ID();

INSERT INTO `bk_water_question_options` (`question_id`, `option_value`, `option_text`, `display_order`) VALUES
(@q1_id, 'yes', 'Yes', 1),
(@q1_id, 'no', 'No', 2);

-- Question 1a: How big are your tanks? (depends on Q1 = Yes)
INSERT INTO `bk_water_questions` (`question_text`, `question_type`, `page_tag`, `display_order`, `is_active`, `is_required`, `depends_on_question_id`, `depends_on_answer_value`) 
VALUES ('How big are your tanks?', 'dropdown', 'water_info', 2, 1, 0, @q1_id, 'yes');

SET @q1a_id = LAST_INSERT_ID();

INSERT INTO `bk_water_question_options` (`question_id`, `option_value`, `option_text`, `display_order`) VALUES
(@q1a_id, '2500', '2500 Litres', 1),
(@q1a_id, '5000', '5000 Litres', 2),
(@q1a_id, '10000', '10 000 Litres', 3),
(@q1a_id, '15000', '15 000 Litres', 4),
(@q1a_id, '20000', '20 000 Litres Plus', 5);

-- Question 1b: Do you get water delivered to top up your tanks? (depends on Q1 = Yes)
INSERT INTO `bk_water_questions` (`question_text`, `question_type`, `page_tag`, `display_order`, `is_active`, `is_required`, `depends_on_question_id`, `depends_on_answer_value`) 
VALUES ('Do you get water delivered to top up your tanks?', 'dropdown', 'water_info', 3, 1, 0, @q1_id, 'yes');

SET @q1b_id = LAST_INSERT_ID();

INSERT INTO `bk_water_question_options` (`question_id`, `option_value`, `option_text`, `display_order`) VALUES
(@q1b_id, 'yes', 'Yes', 1),
(@q1b_id, 'no', 'No', 2);

-- Question 1ca: Municipality or Private Tanker? (depends on Q1b = Yes)
INSERT INTO `bk_water_questions` (`question_text`, `question_type`, `page_tag`, `display_order`, `is_active`, `is_required`, `depends_on_question_id`, `depends_on_answer_value`) 
VALUES ('Do you use the Municipality tankers or Private Tankers?', 'dropdown', 'water_info', 4, 1, 0, @q1b_id, 'yes');

SET @q1ca_id = LAST_INSERT_ID();

INSERT INTO `bk_water_question_options` (`question_id`, `option_value`, `option_text`, `display_order`) VALUES
(@q1ca_id, 'municipality', 'Municipality Tanker', 1),
(@q1ca_id, 'private', 'Private Tanker', 2);

-- Question 1cb: Free municipality delivery? (depends on Q1b = Yes)
INSERT INTO `bk_water_questions` (`question_text`, `question_type`, `page_tag`, `display_order`, `is_active`, `is_required`, `depends_on_question_id`, `depends_on_answer_value`) 
VALUES ('Do you make use of the one FREE municipality tanker delivery each month?', 'dropdown', 'water_info', 5, 1, 0, @q1b_id, 'yes');

SET @q1cb_id = LAST_INSERT_ID();

INSERT INTO `bk_water_question_options` (`question_id`, `option_value`, `option_text`, `display_order`) VALUES
(@q1cb_id, 'yes', 'Yes', 1),
(@q1cb_id, 'no', 'No', 2);

-- Question 1cc: How regularly do you top up? (depends on Q1b = Yes)
INSERT INTO `bk_water_questions` (`question_text`, `question_type`, `page_tag`, `display_order`, `is_active`, `is_required`, `depends_on_question_id`, `depends_on_answer_value`) 
VALUES ('How regularly do you top up your tanks by getting water delivered?', 'dropdown', 'water_info', 6, 1, 0, @q1b_id, 'yes');

SET @q1cc_id = LAST_INSERT_ID();

INSERT INTO `bk_water_question_options` (`question_id`, `option_value`, `option_text`, `display_order`) VALUES
(@q1cc_id, 'weekly', 'Weekly', 1),
(@q1cc_id, 'monthly', 'Monthly', 2),
(@q1cc_id, 'every_other_month', 'Every Other Month', 3);

-- Question 2: Monthly water usage estimate
INSERT INTO `bk_water_questions` (`question_text`, `question_type`, `page_tag`, `display_order`, `is_active`, `is_required`) 
VALUES ('As an estimate how much water does your household use in any given month?', 'dropdown', 'water_info', 7, 1, 1);

SET @q2_id = LAST_INSERT_ID();

INSERT INTO `bk_water_question_options` (`question_id`, `option_value`, `option_text`, `display_order`) VALUES
(@q2_id, '5000', '5000 Litres', 1),
(@q2_id, '10000', '10 000 Litres', 2),
(@q2_id, '15000', '15 000 Litres', 3),
(@q2_id, '20000', '20 000 Litres Plus', 4);

-- Question 3: Number of people in household
INSERT INTO `bk_water_questions` (`question_text`, `question_type`, `page_tag`, `display_order`, `is_active`, `is_required`) 
VALUES ('How many people in your household?', 'dropdown', 'water_info', 8, 1, 1);

SET @q3_id = LAST_INSERT_ID();

INSERT INTO `bk_water_question_options` (`question_id`, `option_value`, `option_text`, `display_order`) VALUES
(@q3_id, '1', '1', 1),
(@q3_id, '2', '2', 2),
(@q3_id, '3', '3', 3),
(@q3_id, '4', '4', 4),
(@q3_id, '5', '5', 5),
(@q3_id, '6', '6', 6),
(@q3_id, '7', '7 Upwards', 7);

-- Question 4: Do you rent out your property?
INSERT INTO `bk_water_questions` (`question_text`, `question_type`, `page_tag`, `display_order`, `is_active`, `is_required`) 
VALUES ('Do you rent out your property?', 'dropdown', 'water_info', 9, 1, 1);

SET @q4_id = LAST_INSERT_ID();

INSERT INTO `bk_water_question_options` (`question_id`, `option_value`, `option_text`, `display_order`) VALUES
(@q4_id, 'yes', 'Yes', 1),
(@q4_id, 'no', 'No', 2);

-- Question 5: Water wise actions (checkbox - multiple)
INSERT INTO `bk_water_questions` (`question_text`, `question_type`, `page_tag`, `display_order`, `is_active`, `is_required`) 
VALUES ('Have you implemented water wise daily actions in your household? Please select all relevant options.', 'checkbox', 'water_info', 10, 1, 0);

SET @q5_id = LAST_INSERT_ID();

INSERT INTO `bk_water_question_options` (`question_id`, `option_value`, `option_text`, `display_order`) VALUES
(@q5_id, 'frontloader', 'Frontloader washing machine', 1),
(@q5_id, 'greywater', 'Greywater system for watering your garden or flushing loos', 2),
(@q5_id, 'showering', 'Showering instead of bathing', 3),
(@q5_id, 'notices', 'Notices in your bathroom for guests about water scarcity', 4),
(@q5_id, 'waterwise_plants', 'Waterwise plants in your garden', 5),
(@q5_id, 'rainwater_tanks', 'Installed tanks to harvest rainwater', 6),
(@q5_id, 'waterwise_appliances', 'Using waterwise appliances to help preserve water', 7);

-- Question 6: Willing to submit affidavit?
INSERT INTO `bk_water_questions` (`question_text`, `question_type`, `page_tag`, `display_order`, `is_active`, `is_required`) 
VALUES ('Would you be willing to submit an affidavit based on your above answers and any water data captured if needed?', 'dropdown', 'water_info', 11, 1, 1);

SET @q6_id = LAST_INSERT_ID();

INSERT INTO `bk_water_question_options` (`question_id`, `option_value`, `option_text`, `display_order`) VALUES
(@q6_id, 'yes', 'Yes', 1),
(@q6_id, 'no', 'No', 2),
(@q6_id, 'maybe', 'Maybe', 3);

-- Question 7: Terms agreement (static checkbox with link)
INSERT INTO `bk_water_questions` (`question_text`, `question_type`, `page_tag`, `display_order`, `is_active`, `is_required`, `terms_link`) 
VALUES ('I agree to the terms and conditions governing my submission of water data', 'checkbox', 'water_info', 12, 1, 1, '/water-data-terms.php');

SET @q7_id = LAST_INSERT_ID();

INSERT INTO `bk_water_question_options` (`question_id`, `option_value`, `option_text`, `display_order`) VALUES
(@q7_id, 'agreed', 'I agree', 1);


/* ======================================================================
   Daily report emails table (create_daily_report_emails.sql)
   ====================================================================== */

-- Table to store email addresses for daily water availability reports
CREATE TABLE IF NOT EXISTS `bk_daily_report_emails` (
  `email_id` INT(11) NOT NULL AUTO_INCREMENT,
  `email_address` VARCHAR(255) NOT NULL,
  `name` VARCHAR(100) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`email_id`),
  UNIQUE KEY `unique_email` (`email_address`),
  INDEX `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


/* ======================================================================
   Intermittent water option (add_intermittent_water_option.sql)
   ====================================================================== */

-- Note: has_water is TINYINT(1) and already supports values 0-255.
-- Convention used in this project:
--   0 = No, I do not have water (red)
--   1 = Yes, I have water (green)
--   2 = Intermittent, I have water at irregular intervals (orange)
-- No ALTER TABLE needed here.


/* ======================================================================
   Allow NULL user_id in water_availability (allow_null_user_id_water_availability.sql)
   ====================================================================== */

-- Allow user_id to be NULL in water_availability table for imported data
ALTER TABLE `bk_water_availability`
MODIFY COLUMN `user_id` INT(11) NULL;

-- Update foreign key constraint to allow NULL
ALTER TABLE `bk_water_availability`
DROP FOREIGN KEY IF EXISTS `bk_water_availability_ibfk_1`;

ALTER TABLE `bk_water_availability`
ADD CONSTRAINT `bk_water_availability_ibfk_1` 
FOREIGN KEY (`user_id`) REFERENCES `bk_users`(`user_id`) ON DELETE RESTRICT;


