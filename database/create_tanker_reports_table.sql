-- Create tanker reports table
CREATE TABLE IF NOT EXISTS `bk_tanker_reports` (
  `report_id` INT(11) NOT NULL AUTO_INCREMENT,
  `reported_by_user_id` INT(11) NOT NULL COMMENT 'Admin user who reported the tanker',
  `registration_number` VARCHAR(50) NOT NULL,
  `photo_path` VARCHAR(500) NULL COMMENT 'Path to uploaded photo',
  `latitude` DECIMAL(10, 8) NOT NULL COMMENT 'GPS latitude',
  `longitude` DECIMAL(11, 8) NOT NULL COMMENT 'GPS longitude',
  `address` TEXT NULL COMMENT 'Address if entered on desktop',
  `device_type` ENUM('mobile', 'desktop') NOT NULL DEFAULT 'desktop',
  `reported_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`report_id`),
  FOREIGN KEY (`reported_by_user_id`) REFERENCES `bk_users`(`user_id`) ON DELETE RESTRICT,
  INDEX `idx_reported_by` (`reported_by_user_id`),
  INDEX `idx_reported_at` (`reported_at`),
  INDEX `idx_location` (`latitude`, `longitude`),
  INDEX `idx_registration` (`registration_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
