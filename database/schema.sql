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

-- Businesses table
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

-- Water availability tracking
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

