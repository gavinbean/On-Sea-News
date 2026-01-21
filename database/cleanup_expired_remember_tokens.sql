-- Optional: Cleanup script to remove expired remember tokens
-- Run this periodically (e.g., via cron job) to keep the table clean

-- Delete expired tokens
DELETE FROM `bk_remember_tokens` 
WHERE `expires_at` < NOW();

-- Optional: Also delete tokens older than 90 days (even if not expired)
-- Uncomment if you want to limit token lifetime regardless of expiration date
/*
DELETE FROM `bk_remember_tokens` 
WHERE `created_at` < DATE_SUB(NOW(), INTERVAL 90 DAY);
*/
