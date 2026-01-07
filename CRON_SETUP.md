# Daily Water Report Cron Job Setup

This document explains how to set up the automated daily water availability report email.

## Overview

The daily water report script (`cron/daily-water-report.php`) automatically:
- Collects all water availability data for the current day
- Generates a map image showing all data points
- Creates a table listing all addresses with water status
- Sends an email to all active recipients configured in the admin panel

## Setup Instructions

### 1. Database Setup

Run the SQL migration to create the email recipients table:

```sql
-- Run this SQL file
database/create_daily_report_emails.sql
```

### 2. Configure Email Recipients

1. Log in as an admin user
2. Go to Admin Dashboard
3. Click "Manage Emails" under "Daily Report Emails"
4. Add email addresses that should receive daily reports
5. Activate/deactivate recipients as needed

### 3. Set Up Cron Job

#### On Linux/Unix (cPanel, cPanelX, etc.)

Add this line to your crontab (run `crontab -e`):

```
0 23 * * * /usr/bin/php /path/to/your/site/cron/daily-water-report.php
```

This runs the script at 11:00 PM (23:00) every day.

**To find your PHP path:**
```bash
which php
```

**To find your site path:**
Use the full absolute path to the `cron/daily-water-report.php` file.

#### On Windows (Task Scheduler)

1. Open Task Scheduler
2. Create Basic Task
3. Set trigger to "Daily" at 11:00 PM
4. Set action to "Start a program"
5. Program: `C:\path\to\php.exe` (or your PHP executable)
6. Arguments: `C:\path\to\your\site\cron\daily-water-report.php`
7. Start in: `C:\path\to\your\site\cron`

#### On cPanel

1. Go to cPanel → Cron Jobs
2. Add New Cron Job
3. Common Settings: "Once Per Day (0 0 * * *)" or set custom time
4. Command: `/usr/bin/php /home/username/public_html/cron/daily-water-report.php`
   (Replace `username` with your cPanel username)

### 4. Test the Script

You can manually test the script by running:

```bash
php cron/daily-water-report.php
```

Check the error logs to see if it runs successfully:
- Check PHP error logs
- Check email sending logs
- Verify emails are received

## Email Content

The daily report email includes:
- **Map Image**: Embedded static map showing all water availability points
  - Green markers = Has Water
  - Red markers = No Water
- **Data Table**: List of all addresses with:
  - Address
  - Name
  - Water Available status (Yes/No)

## Troubleshooting

### Emails Not Sending

1. Check PHP mail() function is working
2. Verify email addresses are active in admin panel
3. Check server error logs
4. Test email sending manually

### Map Not Showing

1. Verify Google Maps API key is configured in `config/geocoding.php`
2. Check that Google Maps Static API is enabled in Google Cloud Console
3. Verify API key has proper permissions

### No Data in Report

- This is normal if no water availability reports were submitted for the day
- The email will still be sent with a message indicating no data

## Notes

- The script runs for the current date (today)
- Only active email recipients receive the report
- The map image is embedded directly in the email (no download required)
- Map is automatically sized to include all data points


