-- Change news and FAQ content/excerpt columns to LONGTEXT to support embedded images
-- TEXT has a limit of 65,535 bytes, which is too small for base64-embedded images
-- LONGTEXT can hold up to 4GB of data

-- ============================================
-- NEWS TABLE CHANGES
-- ============================================

-- Change content column from TEXT to LONGTEXT
ALTER TABLE `bk_news`
MODIFY COLUMN `content` LONGTEXT NOT NULL;

-- Change excerpt column from VARCHAR(500) to LONGTEXT (since it can also contain images)
ALTER TABLE `bk_news`
MODIFY COLUMN `excerpt` LONGTEXT NULL;

-- ============================================
-- FAQ TABLE CHANGES
-- ============================================

-- Change answer column from TEXT to LONGTEXT
ALTER TABLE `bk_faq`
MODIFY COLUMN `answer` LONGTEXT NOT NULL;

-- Change excerpt column from TEXT to LONGTEXT (if it exists)
-- Note: This will fail gracefully if the excerpt column doesn't exist yet
ALTER TABLE `bk_faq`
MODIFY COLUMN `excerpt` LONGTEXT NULL;
