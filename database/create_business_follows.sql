-- Business Follows Table
-- Tracks which users follow which businesses for advert notifications

CREATE TABLE IF NOT EXISTS `bk_business_follows` (
  `follow_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `business_id` INT(11) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`follow_id`),
  UNIQUE KEY `unique_follow` (`user_id`, `business_id`),
  FOREIGN KEY (`user_id`) REFERENCES `bk_users`(`user_id`) ON DELETE CASCADE,
  FOREIGN KEY (`business_id`) REFERENCES `bk_businesses`(`business_id`) ON DELETE CASCADE,
  INDEX `idx_user` (`user_id`),
  INDEX `idx_business` (`business_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
