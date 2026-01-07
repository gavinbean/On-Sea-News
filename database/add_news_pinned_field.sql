-- Add is_pinned field to news table
ALTER TABLE `bk_news`
ADD COLUMN `is_pinned` TINYINT(1) NOT NULL DEFAULT 0 AFTER `published_at`;

-- Add index for better query performance
ALTER TABLE `bk_news`
ADD INDEX `idx_pinned_published` (`is_pinned`, `published`, `published_at`);


