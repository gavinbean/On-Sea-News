-- Add excerpt field to FAQ table to match news structure
ALTER TABLE `bk_faq`
ADD COLUMN `excerpt` TEXT NULL AFTER `question`;

-- Add index for excerpt if needed for searching
-- ALTER TABLE `bk_faq`
-- ADD FULLTEXT INDEX `idx_excerpt` (`excerpt`);
