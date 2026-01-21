-- Add fields for news submission and display options
ALTER TABLE `bk_news`
ADD COLUMN `show_publish_date` TINYINT(1) NOT NULL DEFAULT 1 AFTER `is_pinned`,
ADD COLUMN `show_author` TINYINT(1) NOT NULL DEFAULT 1 AFTER `show_publish_date`,
ADD COLUMN `pending_approval` TINYINT(1) NOT NULL DEFAULT 0 AFTER `show_author`;

-- Add index for pending approval queries
ALTER TABLE `bk_news`
ADD INDEX `idx_pending_approval` (`pending_approval`, `published`);
