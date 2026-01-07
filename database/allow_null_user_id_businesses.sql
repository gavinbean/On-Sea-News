-- Allow user_id to be NULL for imported businesses
-- First, drop the foreign key constraint
ALTER TABLE `bk_businesses`
DROP FOREIGN KEY IF EXISTS `bk_businesses_ibfk_1`;

-- Modify the column to allow NULL
ALTER TABLE `bk_businesses` 
MODIFY COLUMN `user_id` INT(11) NULL;

-- Re-add the foreign key constraint (it will allow NULL values)
ALTER TABLE `bk_businesses`
ADD CONSTRAINT `bk_businesses_ibfk_1` 
FOREIGN KEY (`user_id`) REFERENCES `bk_users`(`user_id`) ON DELETE RESTRICT;

-- Make telephone optional for imported businesses
ALTER TABLE `bk_businesses`
MODIFY COLUMN `telephone` VARCHAR(20) NULL;

