-- Create contact queries table
CREATE TABLE IF NOT EXISTS `bk_contact_queries` (
  `query_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NULL COMMENT 'User ID if logged in, NULL if anonymous',
  `name` VARCHAR(200) NOT NULL,
  `email` VARCHAR(200) NOT NULL,
  `subject` VARCHAR(500) NOT NULL,
  `message` TEXT NOT NULL,
  `status` ENUM('new', 'replied', 'closed') NOT NULL DEFAULT 'new',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`query_id`),
  FOREIGN KEY (`user_id`) REFERENCES `bk_users`(`user_id`) ON DELETE SET NULL,
  INDEX `idx_user` (`user_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create contact replies table
CREATE TABLE IF NOT EXISTS `bk_contact_replies` (
  `reply_id` INT(11) NOT NULL AUTO_INCREMENT,
  `query_id` INT(11) NOT NULL,
  `admin_user_id` INT(11) NOT NULL COMMENT 'Admin who sent the reply',
  `reply_message` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`reply_id`),
  FOREIGN KEY (`query_id`) REFERENCES `bk_contact_queries`(`query_id`) ON DELETE CASCADE,
  FOREIGN KEY (`admin_user_id`) REFERENCES `bk_users`(`user_id`) ON DELETE RESTRICT,
  INDEX `idx_query` (`query_id`),
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
