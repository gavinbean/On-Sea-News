-- Add user_id field to track which user the delivery is for
ALTER TABLE `bk_water_deliveries`
ADD COLUMN `user_id` INT(11) NOT NULL AFTER `delivery_id`,
ADD FOREIGN KEY (`user_id`) REFERENCES `bk_users`(`user_id`) ON DELETE RESTRICT,
ADD INDEX `idx_user` (`user_id`);

-- Update existing records to use logged_by_user_id as user_id (if any exist)
UPDATE `bk_water_deliveries` 
SET `user_id` = `logged_by_user_id` 
WHERE `user_id` IS NULL OR `user_id` = 0;
