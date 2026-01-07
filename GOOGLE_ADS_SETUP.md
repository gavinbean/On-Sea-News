# Google Ads Setup Instructions

## Overview
A fixed bottom banner has been added to display Google AdSense ads. The banner stays static at the bottom of the screen while all content scrolls normally above it.

## Setup Steps

### 1. Get Your Google AdSense Account
- Sign up for Google AdSense at https://www.google.com/adsense/
- Get your Publisher ID (format: `ca-pub-XXXXXXXXXXXXXX`)
- Create an ad unit and get your Ad Slot ID

### 2. Update Configuration Files

#### Update `includes/header.php`
Replace `ca-pub-XXXXXXXXXXXXXX` with your actual Google AdSense Publisher ID:
```php
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-XXXXXXXXXXXXXX"
        crossorigin="anonymous"></script>
```

#### Update `includes/footer.php`
Replace the placeholder values:
- `ca-pub-XXXXXXXXXXXXXX` with your Publisher ID
- `XXXXXXXXXX` with your Ad Slot ID

```php
<ins class="adsbygoogle"
     style="display:block"
     data-ad-client="ca-pub-XXXXXXXXXXXXXX"
     data-ad-slot="XXXXXXXXXX"
     data-ad-format="auto"
     data-full-width-responsive="true"></ins>
```

### 3. Ad Sizes
- **Desktop**: Leaderboard (728x90 pixels)
- **Mobile**: Banner (320x50 pixels)

The banner automatically adjusts based on screen size using responsive ad units.

### 4. Testing
- After setup, test on both desktop and mobile devices
- Ensure content scrolls properly and isn't hidden behind the ad
- Verify ads display correctly

### 5. Notes
- The banner is fixed at the bottom and doesn't scroll with content
- Content has padding-bottom to prevent being hidden behind the ad
- The banner is unobtrusive and doesn't interfere with site navigation
- Ads are responsive and will automatically adjust to screen size

## Troubleshooting

If ads don't appear:
1. Verify your AdSense account is approved
2. Check that Publisher ID and Ad Slot ID are correct
3. Ensure your site is added to your AdSense account
4. Check browser console for any JavaScript errors
5. Wait a few minutes for ads to initialize (can take up to 10 minutes)

