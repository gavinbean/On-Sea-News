# Google Maps Static API Setup Guide

The daily water report emails require the **Google Maps Static API** to be enabled in addition to the Geocoding API.

## Why You're Getting a 403 Error

A 403 error means "Forbidden" - this typically happens when:
1. The Static Maps API is not enabled in your Google Cloud project
2. The API key doesn't have permission to use the Static Maps API
3. The API key has restrictions that are blocking the request

## How to Fix It

### Step 1: Enable Static Maps API

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Select your project (the same one you used for Geocoding API)
3. Go to **"APIs & Services"** → **"Library"**
4. Search for **"Maps Static API"**
5. Click on **"Maps Static API"**
6. Click **"Enable"**

### Step 2: Verify API Key Permissions

1. Go to **"APIs & Services"** → **"Credentials"**
2. Click on your API key
3. Under **"API restrictions"**, make sure:
   - Either **"Don't restrict key"** is selected, OR
   - **"Restrict key"** is selected AND **"Maps Static API"** is in the allowed APIs list

### Step 3: Check API Key Restrictions

If your API key has restrictions:

1. Under **"Application restrictions"**:
   - If set to **"HTTP referrers"**, make sure your domain is added
   - If set to **"IP addresses"**, make sure your server's IP is added
   - For server-side use, **"None"** is usually the best option

2. Under **"API restrictions"**:
   - Make sure **"Maps Static API"** is enabled
   - Also ensure **"Geocoding API"** is enabled (if you're using it)

### Step 4: Test the API Key

You can test if your API key works by visiting this URL in your browser (replace YOUR_API_KEY):

```
https://maps.googleapis.com/maps/api/staticmap?center=-33.7,26.7&zoom=12&size=400x300&key=YOUR_API_KEY
```

If you see a map image, your API key is working correctly.

## Cost Information

- **Static Maps API** is included in the same $200/month free credit
- Each static map image costs **$0.002** (2/10 of a cent)
- With $200 credit, you can generate **100,000 static map images per month**
- For daily reports, this is more than enough

## Troubleshooting

### Still Getting 403 After Enabling?

1. **Wait a few minutes** - API changes can take a few minutes to propagate
2. **Check billing** - Make sure billing is enabled (even for free tier)
3. **Check the error log** - The full error message will be in your PHP error log
4. **Verify the API key** - Make sure you're using the correct API key in `config/geocoding.php`

### Getting "REQUEST_DENIED"?

- This usually means the API key is invalid or the API is not enabled
- Double-check that "Maps Static API" is enabled in your project

### Getting "OVER_QUERY_LIMIT"?

- You've exceeded your quota
- Check your usage in Google Cloud Console
- Consider reducing the frequency of reports or optimizing the code

## What Happens If the Map Fails?

The email will still be sent, but:
- Instead of a map image, a message will appear: "Map image unavailable. Please check Google Maps Static API configuration."
- The data table with all addresses will still be included
- You'll still receive all the water availability information

## Support

If you continue to have issues:
1. Check the PHP error log for detailed error messages
2. Verify your API key in Google Cloud Console
3. Test the API key using the test URL above
4. Contact your developer if issues persist



