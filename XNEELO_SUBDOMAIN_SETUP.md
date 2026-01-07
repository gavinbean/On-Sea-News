# Xneelo Subdomain Setup Guide

## Issues and Solutions

### Issue 1: "You don't have permission to access this resource" Error

This error typically occurs due to:
1. **File/Directory Permissions** - Files need proper permissions
2. **.htaccess Configuration** - May need adjustment for subdomain
3. **Document Root Configuration** - Ensure files are in the correct location

#### Solution Steps:

1. **Check File Permissions via FTP/cPanel File Manager:**
   - Files should be: `644` or `644` (readable by web server)
   - Directories should be: `755` (executable by web server)
   - Critical files like `index.php` should be: `644`

2. **Verify Document Root:**
   - In xneelo control panel, check the subdomain's document root
   - Ensure all website files are in the correct directory
   - The document root should point to where your `index.php` is located

3. **Check .htaccess File:**
   - Ensure `.htaccess` file exists in the root directory
   - Verify it's not blocking access
   - If needed, temporarily rename it to `.htaccess.bak` to test

4. **Check PHP Version:**
   - In xneelo control panel, ensure PHP version is set (7.4 or higher recommended)
   - Check if PHP is enabled for the subdomain

5. **Check Error Logs:**
   - In xneelo control panel, check error logs for specific permission errors
   - Look for "Permission denied" or "Access forbidden" messages

### Issue 2: SSL Certificate Not Valid for Subdomain

#### Solution Steps:

1. **Enable SSL Certificate in xneelo:**
   - Log into your xneelo control panel
   - Navigate to your subdomain settings
   - Look for "SSL/TLS" or "Security" section
   - Enable "Let's Encrypt" or "Auto SSL" if available
   - This will automatically generate a free SSL certificate for your subdomain

2. **Force HTTPS Redirect:**
   - After SSL is enabled, update your `.htaccess` to force HTTPS
   - See the updated `.htaccess` file below

3. **Update SITE_URL in config:**
   - Update `config/database.php` with your new subdomain URL
   - Change `SITE_URL` to use `https://` instead of `http://`

4. **Wait for Certificate Propagation:**
   - SSL certificates can take a few minutes to hours to propagate
   - Clear your browser cache and try again
   - Test in an incognito/private window

## Updated .htaccess Configuration

Add this to your `.htaccess` file (or update existing one):

```apache
# Enable Rewrite Engine
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    
    # Force HTTPS (uncomment after SSL is enabled)
    # RewriteCond %{HTTPS} off
    # RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
    
    # Prevent directory browsing
    Options -Indexes
    
    # Protect sensitive files
    <FilesMatch "\.(htaccess|htpasswd|ini|log|sh|sql)$">
        Order Allow,Deny
        Deny from all
    </FilesMatch>
    
    # Protect config directory
    <IfModule mod_authz_core.c>
        <DirectoryMatch "^.*/config/">
            Require all denied
        </DirectoryMatch>
    </IfModule>
    
    # Protect includes directory (optional - uncomment if needed)
    # <IfModule mod_authz_core.c>
    #     <DirectoryMatch "^.*/includes/">
    #         Require all denied
    #     </DirectoryMatch>
    # </IfModule>
</IfModule>

# PHP Settings
<IfModule mod_php7.c>
    php_value upload_max_filesize 10M
    php_value post_max_size 10M
    php_value max_execution_time 300
    php_value max_input_time 300
</IfModule>

# Security Headers
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "1; mode=block"
</IfModule>

# Default index file
DirectoryIndex index.php index.html
```

## Quick Checklist

- [ ] Files have correct permissions (644 for files, 755 for directories)
- [ ] Document root is correctly set in xneelo control panel
- [ ] PHP is enabled and correct version is selected
- [ ] `.htaccess` file exists and is properly configured
- [ ] SSL certificate is enabled in xneelo control panel
- [ ] `SITE_URL` in `config/database.php` is updated to new subdomain
- [ ] `SITE_URL` uses `https://` protocol
- [ ] Error logs checked for specific issues
- [ ] Browser cache cleared

## Contact xneelo Support

If issues persist:
1. Check xneelo knowledge base
2. Contact xneelo support with:
   - Your subdomain name
   - Specific error messages
   - Screenshots of error pages
   - Error log excerpts

## Testing After Setup

1. Visit your subdomain: `https://your-subdomain.yourdomain.co.za`
2. Check if `index.php` loads correctly
3. Test a few pages (login, register, etc.)
4. Verify SSL certificate shows as valid in browser
5. Check browser console for any mixed content warnings


