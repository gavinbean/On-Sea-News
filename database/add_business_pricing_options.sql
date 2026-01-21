-- Add pricing options table
CREATE TABLE IF NOT EXISTS `bk_business_pricing_options` (
  `option_id` INT(11) NOT NULL AUTO_INCREMENT,
  `option_name` VARCHAR(50) NOT NULL UNIQUE,
  `option_slug` VARCHAR(50) NOT NULL UNIQUE,
  `description` TEXT NULL,
  `display_order` INT(11) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`option_id`),
  INDEX `idx_slug` (`option_slug`),
  INDEX `idx_order` (`display_order`),
  INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default pricing options
INSERT INTO `bk_business_pricing_options` (`option_name`, `option_slug`, `description`, `display_order`) VALUES
('Free', 'free', 'Free listing - appears in business directory only', 1),
('Basic', 'basic', 'Basic advertising package', 2),
('Timed', 'timed', 'Time-based advertising package', 3),
('Events', 'events', 'Event-based advertising package', 4)
ON DUPLICATE KEY UPDATE option_name = VALUES(option_name);

-- Add status field to businesses table
ALTER TABLE `bk_businesses`
ADD COLUMN `pricing_status` VARCHAR(50) NULL AFTER `is_approved`,
ADD INDEX `idx_pricing_status` (`pricing_status`);

-- Set default status for existing businesses
UPDATE `bk_businesses` SET `pricing_status` = 'free' WHERE `pricing_status` IS NULL;
