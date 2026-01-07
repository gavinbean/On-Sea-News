# Google Maps Geocoding API Setup Guide

This guide will help you set up Google Maps Geocoding API for more accurate address geocoding, especially for house-level precision.

## Benefits of Google Maps Geocoding

- **Better Accuracy**: Google Maps typically has house-level data for most addresses
- **Free Tier**: $200/month credit (~40,000 geocoding requests/month)
- **Reliability**: More consistent results than Nominatim for South African addresses

## Step 1: Create a Google Cloud Project

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Sign in with your Google account
3. Click "Select a project" → "New Project"
4. Enter a project name (e.g., "On-Sea News")
5. Click "Create"

## Step 2: Enable Geocoding API

1. In your project, go to "APIs & Services" → "Library"
2. Search for "Geocoding API"
3. Click on "Geocoding API"
4. Click "Enable"

## Step 3: Create an API Key

1. Go to "APIs & Services" → "Credentials"
2. Click "Create Credentials" → "API Key"
3. Copy your API key (you'll need this in Step 5)

## Step 4: Set Up Billing (Required for Free Tier)

**Important**: Even though you get $200/month free, Google requires a billing account to be set up.

1. Go to "Billing" in the Google Cloud Console
2. Click "Link a billing account"
3. Add your payment method (credit/debit card)
4. **Don't worry**: You won't be charged unless you exceed $200/month in usage

### Understanding the Free Tier

- **$200/month credit** = approximately **40,000 geocoding requests**
- Geocoding costs: **$5 per 1,000 requests**
- For most community sites, this is more than enough
- You can set up billing alerts to notify you if you approach the limit

## Step 5: Configure Your Site

1. Log in to your admin panel
2. Go to "Geocoding Settings" (in the admin dashboard)
3. Select "Google Maps" as the provider
4. Paste your API key
5. Click "Save Settings"

## Step 6: Test the Configuration

1. In the "Geocoding Settings" page, use the "Test Geocoding" form
2. Enter a test address (e.g., "47 Main Street, Bushman's River Mouth")
3. Click "Test Geocoding"
4. Verify that:
   - Coordinates are returned
   - House number is detected in the result
   - Formatted address is accurate

## Hybrid Mode (Recommended)

You can use a hybrid approach:
- **Google Maps** for addresses with street numbers (more accurate)
- **Nominatim** for addresses without street numbers (free, unlimited)

To enable hybrid mode:
1. Set provider to "Nominatim"
2. Check "Use Google Maps for addresses with street numbers"
3. Enter your Google Maps API key
4. Save settings

This gives you the best of both worlds:
- Accurate house-level geocoding when needed
- Free unlimited geocoding for addresses without numbers

## Monitoring Usage

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Navigate to "APIs & Services" → "Dashboard"
3. Select "Geocoding API"
4. View your usage statistics

## Troubleshooting

### "Google Maps API key not configured"
- Make sure you've entered the API key in the admin settings
- Verify the API key is correct (no extra spaces)

### "Geocoding failed: REQUEST_DENIED"
- Check that "Geocoding API" is enabled in your Google Cloud project
- Verify your API key is correct
- Check if there are any restrictions on your API key (IP restrictions, referrer restrictions)

### "Geocoding failed: OVER_QUERY_LIMIT"
- You've exceeded your quota
- Check your usage in Google Cloud Console
- Consider switching to hybrid mode to reduce Google Maps usage

### Still getting same coordinates for different house numbers?
- This is normal if Google Maps doesn't have house-level data for that street
- The system will mark these as "approximate" locations
- You can use the re-geocoding tool to update addresses

## Cost Management

To avoid unexpected charges:

1. **Set up billing alerts**:
   - Go to "Billing" → "Budgets & alerts"
   - Create a budget alert for $200/month

2. **Monitor usage regularly**:
   - Check your API usage weekly
   - Most community sites use < 1,000 requests/month

3. **Use hybrid mode**:
   - Only use Google Maps when you have a street number
   - Use free Nominatim for everything else

## Support

If you need help:
- Check the [Google Maps Platform documentation](https://developers.google.com/maps/documentation/geocoding)
- Review your API usage in Google Cloud Console
- Contact your developer if issues persist

