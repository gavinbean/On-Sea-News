# Cron Jobs

This directory contains cron job scripts for automated tasks.

## Advert Start Date Check

**File:** `check-advert-start-dates.php`

**Purpose:** Checks for adverts that have reached their start date and sends notifications to followers.

**When to run:** Daily (recommended at midnight)

**Cron schedule example:**
```bash
# Run daily at midnight
0 0 * * * /usr/bin/php /path/to/your/project/cron/check-advert-start-dates.php >> /path/to/logs/advert-cron.log 2>&1
```

**Manual execution:**
```bash
php cron/check-advert-start-dates.php
```

**What it does:**
1. Finds adverts that:
   - Are approved (`approval_status = 'approved'`)
   - Are active (`is_active = 1`)
   - Have a `start_date` that is today
   - Haven't been sent to followers yet, OR were sent before approval
2. Checks if each advert is in date (end_date is null or >= today)
3. Sends email notifications to all followers of the business
4. Updates the `notified_followers_at` timestamp

**Logging:**
- All actions are logged to PHP error log
- When run from command line, outputs summary to stdout
- Errors are logged with full stack traces

**Requirements:**
- PHP must have access to the project's includes directory
- Database connection must be configured
- Email sending must be configured
