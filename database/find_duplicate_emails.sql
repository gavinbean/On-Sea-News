-- Script to find duplicate emails in the users table
-- Run this first to see what duplicates exist

SELECT 
    email,
    COUNT(*) as duplicate_count,
    GROUP_CONCAT(user_id ORDER BY created_at ASC) as user_ids,
    GROUP_CONCAT(username ORDER BY created_at ASC) as usernames,
    GROUP_CONCAT(created_at ORDER BY created_at ASC) as created_dates
FROM bk_users
GROUP BY email
HAVING COUNT(*) > 1
ORDER BY duplicate_count DESC, email;
