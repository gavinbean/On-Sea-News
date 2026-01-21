-- Add approval status to business adverts table
ALTER TABLE `bk_business_adverts` 
ADD COLUMN `approval_status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending' AFTER `is_active`,
ADD COLUMN `approved_at` DATETIME NULL AFTER `approval_status`,
ADD COLUMN `approved_by` INT(11) NULL AFTER `approved_at`,
ADD COLUMN `rejected_at` DATETIME NULL AFTER `approved_by`,
ADD COLUMN `rejected_by` INT(11) NULL AFTER `rejected_at`,
ADD COLUMN `rejection_reason` TEXT NULL AFTER `rejected_by`,
ADD INDEX `idx_approval_status` (`approval_status`);

-- Set existing adverts as approved (for backward compatibility)
UPDATE `bk_business_adverts` SET `approval_status` = 'approved' WHERE `approval_status` = 'pending';

-- Add foreign keys for approved_by and rejected_by
ALTER TABLE `bk_business_adverts`
ADD CONSTRAINT `fk_adverts_approved_by` 
FOREIGN KEY (`approved_by`) REFERENCES `bk_users`(`user_id`) ON DELETE SET NULL;

ALTER TABLE `bk_business_adverts`
ADD CONSTRAINT `fk_adverts_rejected_by` 
FOREIGN KEY (`rejected_by`) REFERENCES `bk_users`(`user_id`) ON DELETE SET NULL;
