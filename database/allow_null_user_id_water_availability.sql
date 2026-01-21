-- Allow user_id to be NULL in water_availability table for imported data
ALTER TABLE `bk_water_availability`
MODIFY COLUMN `user_id` INT(11) NULL;

-- Update foreign key constraint to allow NULL
ALTER TABLE `bk_water_availability`
DROP FOREIGN KEY IF EXISTS `bk_water_availability_ibfk_1`;

ALTER TABLE `bk_water_availability`
ADD CONSTRAINT `bk_water_availability_ibfk_1` 
FOREIGN KEY (`user_id`) REFERENCES `bk_users`(`user_id`) ON DELETE RESTRICT;

