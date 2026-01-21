-- Business Adverts System
-- Supports Basic (single), Timed (multiple with dates), and Events (timed + event info)

-- Business adverts table
CREATE TABLE IF NOT EXISTS `bk_business_adverts` (
  `advert_id` INT(11) NOT NULL AUTO_INCREMENT,
  `business_id` INT(11) NOT NULL,
  `advert_type` ENUM('basic', 'timed', 'events') NOT NULL,
  `banner_image` VARCHAR(255) NOT NULL COMMENT 'Path to banner image',
  `display_image` VARCHAR(255) NOT NULL COMMENT 'Path to display image',
  `start_date` DATE NULL COMMENT 'Start date for timed/events adverts',
  `end_date` DATE NULL COMMENT 'End date for timed/events adverts',
  `event_date` DATE NULL COMMENT 'Event date for events adverts',
  `event_title` VARCHAR(255) NULL COMMENT 'Event title for events adverts',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_changed_at` DATETIME NULL COMMENT 'For Basic adverts - tracks when last changed',
  PRIMARY KEY (`advert_id`),
  FOREIGN KEY (`business_id`) REFERENCES `bk_businesses`(`business_id`) ON DELETE CASCADE,
  INDEX `idx_business` (`business_id`),
  INDEX `idx_type` (`advert_type`),
  INDEX `idx_dates` (`start_date`, `end_date`),
  INDEX `idx_event_date` (`event_date`),
  INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
