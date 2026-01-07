-- Add approval status to businesses table
ALTER TABLE `bk_businesses` 
ADD COLUMN `is_approved` TINYINT(1) NOT NULL DEFAULT 0 AFTER `has_paid_subscription`,
ADD COLUMN `approved_at` DATETIME NULL AFTER `is_approved`,
ADD COLUMN `approved_by` INT(11) NULL AFTER `approved_at`,
ADD INDEX `idx_approved` (`is_approved`);

-- Set existing businesses as approved (for backward compatibility)
UPDATE `bk_businesses` SET `is_approved` = 1 WHERE `is_approved` = 0;

-- Add foreign key for approved_by
ALTER TABLE `bk_businesses`
ADD CONSTRAINT `fk_businesses_approved_by` 
FOREIGN KEY (`approved_by`) REFERENCES `bk_users`(`user_id`) ON DELETE SET NULL;

