# Water Availability Geolocation Migration

This migration adds latitude and longitude fields to the `water_availability` table to prevent duplicate records at the same address and ensure map points remain stable when users change their addresses.

## Changes Made

1. **Database Schema**: Added `latitude` and `longitude` columns to `water_availability` table
2. **Unique Constraint**: Changed from `unique_user_date` to `unique_location_date` (based on geolocation, not user)
3. **Insert/Update Logic**: Now checks for existing records by geolocation instead of user_id
4. **Map Display**: All map queries now use stored coordinates from `water_availability` table instead of current user profile coordinates

## Migration Steps

### Step 1: Run the SQL Migration

Execute the SQL script to add the columns and update constraints:

```bash
mysql -u your_username -p your_database < database/add_water_availability_geolocation.sql
```

Or manually run the SQL commands in `database/add_water_availability_geolocation.sql`

### Step 2: Populate Existing Records

Run the PHP migration script to populate existing records with coordinates from user profiles:

```bash
php database/update_water_availability_coordinates.php
```

This script will:
- Update all existing records with coordinates from user profiles
- Merge duplicate records at the same location for the same date
- Show progress and summary of updates

## How It Works

### Before Migration
- Records were unique per user per date
- Map points used current user profile coordinates
- If a user changed their address, map points would move

### After Migration
- Records are unique per geolocation per date
- Multiple users at the same address can update the same record
- Map points use stored coordinates from the `water_availability` table
- Map points remain stable even if users change their addresses

### Example Scenario

**Before**: 
- User A at "123 Main St" reports water status → Record created
- User B at "123 Main St" reports water status → Separate record created
- User A moves to "456 Oak Ave" → Map point moves to new address

**After**:
- User A at "123 Main St" reports water status → Record created with coordinates
- User B at "123 Main St" reports water status → Updates same record (same coordinates)
- User A moves to "456 Oak Ave" → Map point stays at "123 Main St" (uses stored coordinates)

## Files Modified

1. `database/add_water_availability_geolocation.sql` - SQL migration script
2. `database/update_water_availability_coordinates.php` - PHP migration script
3. `water-availability.php` - Updated insert/update logic and queries
4. `api/water-data.php` - Updated to use stored coordinates
5. `cron/daily-water-report.php` - Updated to use stored coordinates
6. `api/water-export-data.php` - Updated to use stored coordinates
7. `admin/manage-report-emails.php` - Updated to use stored coordinates

## Verification

After migration, verify:
1. Check that all records have latitude/longitude values
2. Test that multiple users at the same address can update the same record
3. Verify map points don't move when users change their addresses
4. Check that email reports use stored coordinates

## Rollback

If you need to rollback:
1. Restore the old unique constraint: `ALTER TABLE bk_water_availability ADD UNIQUE KEY unique_user_date (user_id, report_date);`
2. Remove the new constraint: `ALTER TABLE bk_water_availability DROP INDEX unique_location_date;`
3. Remove columns: `ALTER TABLE bk_water_availability DROP COLUMN latitude, DROP COLUMN longitude;`
4. Revert code changes in the modified files

