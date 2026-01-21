-- Add fields to track when adverts are sent to followers
ALTER TABLE `bk_business_adverts` 
ADD COLUMN `notified_followers_at` DATETIME NULL AFTER `rejection_reason`,
ADD COLUMN `notified_followers_version` INT(11) NOT NULL DEFAULT 0 AFTER `notified_followers_at` COMMENT 'Tracks version to detect updates',
ADD INDEX `idx_notified` (`notified_followers_at`);
