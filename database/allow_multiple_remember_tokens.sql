-- Migration to allow multiple remember tokens per user (for multiple devices)
-- This removes the unique constraint on user_id so users can stay logged in on multiple devices

-- Step 1: Remove the unique constraint on user_id
ALTER TABLE `bk_remember_tokens` 
DROP INDEX `unique_user_id`;

-- Step 2: Add optional device identifier column (for tracking which device each token belongs to)
-- This is optional but helpful for security and user management
ALTER TABLE `bk_remember_tokens`
ADD COLUMN `device_info` VARCHAR(255) NULL COMMENT 'User agent or device identifier' AFTER `token_hash`,
ADD COLUMN `last_used_at` DATETIME NULL COMMENT 'Last time this token was used' AFTER `expires_at`;

-- Step 3: Add index for cleaning up expired tokens
ALTER TABLE `bk_remember_tokens`
ADD INDEX `idx_user_expires` (`user_id`, `expires_at`);

-- Note: After this migration, users can have multiple remember tokens (one per device)
-- Old tokens will remain valid until they expire
-- You may want to clean up expired tokens periodically
