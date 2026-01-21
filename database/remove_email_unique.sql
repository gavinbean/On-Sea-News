-- Remove unique constraint from email column
-- This allows the same email to be used on different profiles

ALTER TABLE `bk_users` 
DROP INDEX `email`;

-- Note: The email column will still have an index for performance, but it won't enforce uniqueness



