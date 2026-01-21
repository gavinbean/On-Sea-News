# On-Sea News Community Website

Community website for Bushmans River Mouth and Kenton on Sea.

## Features

1. **News System** - Publish and display community news articles
2. **User Authentication** - Registration, login, password recovery with CAPTCHA
3. **User Roles** - USER, ADMIN, PUBLISHER, ADVERTISER, DATA_CAPTURER
4. **Advertisement System** - Paid advertisements with account management
5. **Business Directory** - Categorized business listings with paid subscription features
6. **Water Availability Tracking** - Daily water status reports with map visualization

## Installation

### Requirements
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- GD library for CAPTCHA images

### Setup Instructions

1. **Upload files** to your xneelo hosting directory (public_html or www)

2. **Create database** in your xneelo control panel

3. **Configure database** by editing `config/database.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'your_database_name');
   define('DB_USER', 'your_database_user');
   define('DB_PASS', 'your_database_password');
   ```

4. **Import database schema**:
   - Access your database via phpMyAdmin or command line
   - Import the SQL file: `database/schema.sql`

5. **Create upload directories**:
   ```bash
   mkdir -p uploads/news
   mkdir -p uploads/graphics
   chmod 755 uploads uploads/news uploads/graphics
   ```

6. **Set file permissions**:
   - Ensure PHP can write to upload directories
   - Recommended: 755 for directories, 644 for files

7. **Configure site URL** in `config/database.php`:
   ```php
   define('SITE_URL', 'https://www.busken.co.za');
   ```

## Configuration

### Advertisement Settings
Edit `config/database.php` to configure:
- `ADVERT_MONTHLY_FEE` - Monthly fee for advertisements (default: 500.00 ZAR)
- `ADVERT_ROTATION_INTERVAL` - Time between ad rotations in milliseconds (default: 5000)

### Session Configuration
Sessions are stored in the database. The `bk_sessions` table will be created automatically.

## User Roles

- **USER** - Basic registered user
- **ADMIN** - Full administrative access
- **PUBLISHER** - Can create and publish news articles
- **ADVERTISER** - Can manage advertisements and businesses
- **DATA_CAPTURER** - Can capture and manage data

Users can have multiple roles.

## Security Notes

1. **CAPTCHA**: The current implementation uses a simple CAPTCHA. For production, consider integrating Google reCAPTCHA.

2. **Password Reset**: The password reset token is currently shown in the response. In production:
   - Implement email sending functionality
   - Remove token from response
   - Use secure email templates

3. **File Uploads**: Ensure upload directories are properly secured and validate file types and sizes.

4. **Payment Integration**: The payment system is simplified. For production, integrate with:
   - PayFast (South Africa)
   - PayPal
   - Other payment gateways

5. **HTTPS**: Ensure SSL certificate is installed and all connections use HTTPS.

6. **Geocoding API**: 
   - Get a Google Geocoding API key from Google Cloud Console
   - Add the API key to `config/database.php` (GOOGLE_GEOCODING_API_KEY)
   - This is used to convert user addresses to coordinates for mapping
   - Free tier allows 40,000 requests per month

## File Structure

```
/
├── admin/              # Admin pages
├── advertiser/         # Advertiser dashboard and management
├── api/                # API endpoints (JSON)
├── config/             # Configuration files
├── css/                # Stylesheets
├── database/           # Database schema
├── includes/           # Shared PHP includes
├── js/                 # JavaScript files
├── uploads/            # Uploaded files (news images, adverts)
├── index.php           # Landing page
├── login.php           # Login page
├── register.php        # Registration page
└── README.md           # This file
```

## Database Tables (all prefixed with `bk_`)

- `users` - User accounts
- `roles` - User roles
- `user_roles` - User-role mapping
- `news` - News articles
- `advertiser_accounts` - Advertiser payment accounts
- `business_categories` - Business categories
- `businesses` - Business listings
- `advertisements` - Advertisement records
- `advert_clicks` - Advertisement click tracking
- `advert_transactions` - Payment transactions
- `water_availability` - Water status reports
- `sessions` - Session storage

## Development Notes

- All database tables are prefixed with `bk_`
- Uses PDO for database access
- Sessions stored in database for scalability
- Responsive design with mobile-friendly navigation
- Uses Leaflet.js for map functionality in water tracking

## Support

For issues or questions, contact the website administrator.

## License

Proprietary - All rights reserved

