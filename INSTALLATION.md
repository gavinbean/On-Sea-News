# Installation Guide for On-Sea News Community Website

## Step-by-Step Installation Instructions

### 1. Upload Files
Upload all files to your xneelo hosting directory (typically `public_html` or `www`).

### 2. Database Setup
1. Log into your xneelo control panel
2. Create a new MySQL database
3. Note down:
   - Database name
   - Database username
   - Database password
   - Database host (usually `localhost`)

### 3. Configure Database Connection
Edit the file `config/database.php` and update these lines:
```php
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_username');
define('DB_PASS', 'your_database_password');
```

### 4. Import Database Schema
1. Access phpMyAdmin from your xneelo control panel
2. Select your database
3. Go to the "Import" tab
4. Choose the file `database/schema.sql`
5. Click "Go" to import

### 5. Create Upload Directories
Using FTP or File Manager, create these directories and set permissions:

```
uploads/
uploads/news/
uploads/adverts/
```

Set permissions to 755 for directories.

### 6. Configure Site URL
In `config/database.php`, update:
```php
define('SITE_URL', 'https://www.busken.co.za');
```

### 7. Test Installation
1. Visit your website: `https://www.busken.co.za`
2. Try registering a new account
3. Log in with your new account

### 8. Create Admin User (Optional)
You may need to manually assign ADMIN role to your first user via phpMyAdmin:

```sql
-- Find your user_id first
SELECT user_id FROM bk_users WHERE username = 'your_username';

-- Then assign ADMIN role (replace USER_ID with actual ID)
INSERT INTO bk_user_roles (user_id, role_id)
SELECT USER_ID, role_id FROM bk_roles WHERE role_name = 'ADMIN';
```

### 9. Configure Geocoding API
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing one
3. Enable the Geocoding API
4. Create credentials (API Key)
5. Restrict the API key to Geocoding API only (for security)
6. Add the API key to `config/database.php`:
   ```php
   define('GOOGLE_GEOCODING_API_KEY', 'your_api_key_here');
   ```

### 10. Security Recommendations
1. **Change file permissions**: Ensure sensitive files have correct permissions
2. **Enable HTTPS**: Install SSL certificate in xneelo control panel
3. **Update .htaccess**: Uncomment HTTPS redirect in `.htaccess` once SSL is active
4. **API Key Security**: Restrict your Google Geocoding API key to specific APIs and domains
4. **Email Configuration**: Set up email for password reset (currently shows token - needs email integration)
5. **CAPTCHA**: Consider upgrading to Google reCAPTCHA for production

### 10. Payment Gateway Integration
The payment system currently uses a simplified approach. For production:
- Integrate PayFast (recommended for South Africa)
- Or integrate PayPal
- Update `advertiser/dashboard.php` payment processing

## Troubleshooting

### Database Connection Errors
- Verify database credentials in `config/database.php`
- Check database exists in phpMyAdmin
- Verify database user has proper permissions

### Permission Errors
- Ensure upload directories are writable (755 or 777)
- Check file ownership matches web server user

### CAPTCHA Not Displaying
- Verify GD library is installed: `php -m | grep gd`
- Check `captcha-image.php` is accessible
- Check session storage is working

### Maps Not Displaying
- Verify Leaflet.js is loading (check browser console)
- Check internet connection (Leaflet loads from CDN)
- Verify coordinates are being saved correctly

## Next Steps

1. Create business categories via Admin → Business Categories
2. Add some test news articles
3. Configure advertisement pricing
4. Set up email functionality for password resets
5. Integrate payment gateway

