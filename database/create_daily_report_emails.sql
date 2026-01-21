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



