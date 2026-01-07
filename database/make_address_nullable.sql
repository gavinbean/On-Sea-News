-- Make address field nullable since we now use component fields
-- The address field will be auto-generated from components for backward compatibility

ALTER TABLE `bk_users`
MODIFY COLUMN `address` TEXT NULL;

