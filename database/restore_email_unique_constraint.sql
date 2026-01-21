-- Migration script to restore UNIQUE constraint on email
-- This script handles existing duplicates before adding the constraint

-- STEP 1: Find and display duplicate emails (for review)
-- Run this query first to see what will be affected:
/*
SELECT 
    email,
    COUNT(*) as duplicate_count,
    GROUP_CONCAT(user_id ORDER BY created_at ASC) as user_ids,
    GROUP_CONCAT(username ORDER BY created_at ASC) as usernames,
    GROUP_CONCAT(created_at ORDER BY created_at ASC) as created_dates,
    GROUP_CONCAT(is_active ORDER BY created_at ASC) as active_status
FROM bk_users
GROUP BY email
HAVING COUNT(*) > 1
ORDER BY duplicate_count DESC, email;
*/

-- STEP 2: Handle duplicates
-- Strategy: Keep the oldest account (first created), mark others as inactive
-- You can modify this strategy as needed

-- Create a temporary table to store emails that need to be kept
CREATE TEMPORARY TABLE IF NOT EXISTS emails_to_keep AS
SELECT 
    email,
    MIN(user_id) as keep_user_id
FROM bk_users
GROUP BY email;

-- Mark duplicate accounts (except the one we're keeping) as inactive
-- This preserves the data but prevents them from being used
UPDATE bk_users u
INNER JOIN (
    SELECT 
        email,
        MIN(user_id) as keep_user_id
    FROM bk_users
    GROUP BY email
    HAVING COUNT(*) > 1
) duplicates ON u.email = duplicates.email
LEFT JOIN emails_to_keep etk ON u.user_id = etk.keep_user_id
SET 
    u.is_active = 0,
    u.email = CONCAT('duplicate_', u.user_id, '_', u.email)
WHERE etk.keep_user_id IS NULL;

-- Alternative: If you want to DELETE duplicates instead of marking inactive, use this:
-- (COMMENTED OUT - uncomment only if you want to delete duplicates)
/*
DELETE u FROM bk_users u
INNER JOIN (
    SELECT 
        email,
        MIN(user_id) as keep_user_id
    FROM bk_users
    GROUP BY email
    HAVING COUNT(*) > 1
) duplicates ON u.email = duplicates.email
WHERE u.user_id != duplicates.keep_user_id;
*/

-- STEP 3: Verify no duplicates remain
-- Run this to confirm:
/*
SELECT 
    email,
    COUNT(*) as count
FROM bk_users
GROUP BY email
HAVING COUNT(*) > 1;
*/

-- STEP 4: Add back the UNIQUE constraint
-- Only run this after confirming no duplicates remain
ALTER TABLE `bk_users` 
ADD UNIQUE KEY `unique_email` (`email`);

-- Clean up temporary table
DROP TEMPORARY TABLE IF EXISTS emails_to_keep;
