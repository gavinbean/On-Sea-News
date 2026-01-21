-- SAFE Migration script to restore UNIQUE constraint on email
-- This version only marks duplicates as inactive and renames their emails
-- It does NOT delete any data

-- STEP 1: Display duplicate emails for review
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

-- STEP 2: Handle duplicates by keeping the oldest account and renaming others
-- This preserves all data but makes emails unique

-- First, create a backup of affected users (optional but recommended)
-- CREATE TABLE bk_users_backup_before_email_fix AS SELECT * FROM bk_users WHERE email IN (
--     SELECT email FROM bk_users GROUP BY email HAVING COUNT(*) > 1
-- );

-- Mark duplicate accounts (except the oldest one) as inactive and rename their email
UPDATE bk_users u
INNER JOIN (
    -- Find all duplicate emails
    SELECT email
    FROM bk_users
    GROUP BY email
    HAVING COUNT(*) > 1
) duplicates ON u.email = duplicates.email
INNER JOIN (
    -- Find the user_id to keep (oldest account for each email)
    SELECT email, MIN(user_id) as keep_user_id
    FROM bk_users
    GROUP BY email
    HAVING COUNT(*) > 1
) keep_users ON u.email = keep_users.email
SET 
    u.is_active = 0,
    u.email = CONCAT('duplicate_', u.user_id, '_', u.email)
WHERE u.user_id != keep_users.keep_user_id;

-- STEP 3: Verify no duplicates remain
SELECT 
    email,
    COUNT(*) as count
FROM bk_users
WHERE email NOT LIKE 'duplicate_%'
GROUP BY email
HAVING COUNT(*) > 1;
-- This should return 0 rows

-- STEP 4: Add back the UNIQUE constraint
-- Only run this after confirming no duplicates remain (STEP 3 returns 0 rows)
ALTER TABLE `bk_users` 
ADD UNIQUE KEY `unique_email` (`email`);
