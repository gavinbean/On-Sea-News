-- Add reference_number field to electricity issues table
ALTER TABLE `bk_electricity_issues`
ADD COLUMN `reference_number` VARCHAR(100) NULL AFTER `issue_id`,
ADD INDEX `idx_reference_number` (`reference_number`);
