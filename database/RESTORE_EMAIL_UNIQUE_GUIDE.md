# Guide to Restore Email UNIQUE Constraint

This guide will help you safely restore the UNIQUE constraint on the email column after handling existing duplicates.

## Step 1: Find Duplicate Emails

First, run the query to see what duplicates exist:

```sql
-- Run: database/find_duplicate_emails.sql
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
```

Review the results to understand which accounts are duplicates.

## Step 2: Choose Your Strategy

You have two options:

### Option A: Mark Duplicates as Inactive (SAFE - Recommended)
- Keeps all data
- Marks duplicate accounts (except the oldest) as inactive
- Renames their email to `duplicate_{user_id}_{original_email}`
- Use: `database/restore_email_unique_safe.sql`

### Option B: Delete Duplicates (DESTRUCTIVE)
- Permanently deletes duplicate accounts (keeps only the oldest)
- Use with caution!
- Use: `database/restore_email_unique_constraint.sql` (uncomment the DELETE section)

## Step 3: Run the Migration

### If using Option A (Safe):

1. **Backup first** (recommended):
   ```sql
   CREATE TABLE bk_users_backup_before_email_fix AS 
   SELECT * FROM bk_users WHERE email IN (
       SELECT email FROM bk_users GROUP BY email HAVING COUNT(*) > 1
   );
   ```

2. **Run the safe migration**:
   ```sql
   -- Run: database/restore_email_unique_safe.sql
   -- This will:
   -- - Keep the oldest account for each email
   -- - Mark other duplicates as inactive
   -- - Rename their emails to make them unique
   -- - Add the UNIQUE constraint back
   ```

3. **Verify**:
   ```sql
   -- Should return 0 rows
   SELECT email, COUNT(*) as count
   FROM bk_users
   WHERE email NOT LIKE 'duplicate_%'
   GROUP BY email
   HAVING COUNT(*) > 1;
   ```

## Step 4: Manual Review (Optional)

After running the migration, you may want to manually review and potentially:
- Merge data from duplicate accounts into the kept account
- Delete truly duplicate accounts if they're not needed
- Contact users if needed

## Notes

- The safe migration keeps the **oldest account** (lowest `user_id` or earliest `created_at`) for each email
- Duplicate accounts are marked as `is_active = 0` and their email is renamed
- All data is preserved - nothing is deleted
- After migration, only one account per email can be active

## Rollback

If you need to rollback:
```sql
-- Restore from backup
-- Then remove the constraint:
ALTER TABLE `bk_users` DROP INDEX `unique_email`;
```
