# How to Import Businesses from PDF

## Step 1: Run the Database Migration

Before importing, you need to update the database to allow businesses without user accounts:

1. Open phpMyAdmin or your database management tool
2. Select your database
3. Run the SQL file: `database/allow_null_user_id_businesses.sql`

This will:
- Allow businesses to exist without a user account (for imported businesses)
- Make the telephone field optional

## Step 2: Extract Text from PDF

You have several options to extract text from the PDF:

### Option A: Copy from PDF Viewer
1. Open the PDF file: `Business Services Area Information Sheet.pdf.final.pdf`
2. Select all text (Ctrl+A or Cmd+A)
3. Copy the text (Ctrl+C or Cmd+C)

### Option B: Use Adobe Acrobat
1. Open the PDF in Adobe Acrobat
2. Go to File → Export To → Text
3. Save the text file and open it in a text editor

### Option C: Use Online PDF to Text Converter
1. Upload the PDF to an online converter (like pdftotext.com)
2. Download the converted text file

## Step 3: Format the Data

The import system expects data in this format:

```
CATEGORY NAME
Company Name | Contact Name | Contact Number
Company Name | Contact Name | Contact Number

NEXT CATEGORY NAME
Company Name | Contact Name | Contact Number
```

**Important formatting rules:**
- Category headings should be on their own line (no pipe `|` separator)
- Business entries use pipe `|` to separate: Company | Contact | Phone
- Use blank lines to separate different categories
- If a business is missing contact name or phone, you can leave it empty: `Company Name | | Phone` or `Company Name | Contact |`

### Example:
```
PLUMBING
ABC Plumbing Services | John Smith | 041 123 4567
XYZ Plumbing | Jane Doe | 042 987 6543
Quick Fix Plumbing | | 043 555 1234

ELECTRICAL
Electric Co | Bob Johnson | 044 111 2222
Sparky Electrical | | 045 333 4444

BUILDING
Builders Inc | Mike Builder | 046 555 6666
```

## Step 4: Import the Data

1. **Log in as Admin** to your website
2. Go to **Admin Dashboard** (click "Admin" in the navigation menu)
3. Click **"Import Businesses"** card
4. Or go directly to: `/admin/import-businesses.php`
5. Paste the formatted text into the text area
6. Click **"Import Businesses"** button

## Step 5: Verify the Import

1. Go to the **Businesses** page (click "Businesses" in the navigation menu)
2. Or visit: `/businesses.php`
3. You should see all imported businesses
4. Use the search box to find specific businesses
5. Filter by category using the dropdown

## Troubleshooting

### Import shows "0 businesses imported"
- Check that your data is formatted correctly
- Make sure category headings don't have pipe separators
- Make sure business entries have pipe separators
- Check for extra spaces or formatting issues

### Some businesses are skipped
- The system skips duplicates (same company name in same category)
- This is normal if you run the import multiple times

### Categories not created
- Make sure category headings are on their own line
- Category headings should not contain pipe `|` characters
- Category headings should be at least 3 characters long

### Need to re-import?
- The system prevents duplicates, so you can safely re-run the import
- It will only add new businesses that don't already exist

## Tips

- You can import in batches - just paste a section at a time
- The system automatically creates categories from headings
- Missing contact information is okay - those fields are optional
- After importing, businesses appear immediately on the public directory page

