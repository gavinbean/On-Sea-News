-- Electricity Issues System
-- Allows users to log electricity issues and admins to manage them

-- Electricity issues table
CREATE TABLE IF NOT EXISTS `bk_electricity_issues` (
  `issue_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL COMMENT 'User who reported the issue',
  `name` VARCHAR(200) NOT NULL COMMENT 'Name from user profile',
  `address` TEXT NOT NULL COMMENT 'Address from user profile',
  `latitude` DECIMAL(10, 8) NULL COMMENT 'Latitude for map display',
  `longitude` DECIMAL(11, 8) NULL COMMENT 'Longitude for map display',
  `description` TEXT NOT NULL COMMENT 'Initial description of the problem',
  `status` ENUM('New Issue', 'Issue Received', 'Issue Updated', 'Issue Resolved', 'Closed') NOT NULL DEFAULT 'New Issue',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `closed_at` DATETIME NULL COMMENT 'When the issue was closed',
  PRIMARY KEY (`issue_id`),
  FOREIGN KEY (`user_id`) REFERENCES `bk_users`(`user_id`) ON DELETE RESTRICT,
  INDEX `idx_user` (`user_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_created` (`created_at`),
  INDEX `idx_location` (`latitude`, `longitude`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Electricity issue comments table
CREATE TABLE IF NOT EXISTS `bk_electricity_issue_comments` (
  `comment_id` INT(11) NOT NULL AUTO_INCREMENT,
  `issue_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL COMMENT 'User who added the comment',
  `comment_text` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`comment_id`),
  FOREIGN KEY (`issue_id`) REFERENCES `bk_electricity_issues`(`issue_id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `bk_users`(`user_id`) ON DELETE RESTRICT,
  INDEX `idx_issue` (`issue_id`),
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
