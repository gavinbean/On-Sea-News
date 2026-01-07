<?php
/**
 * Migration script to update existing water_availability records
 * with latitude and longitude from user profiles
 * 
 * Run this script once after adding the latitude/longitude columns
 * Usage: php database/update_water_availability_coordinates.php
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

$db = getDB();

echo "Starting migration: Updating water_availability records with coordinates from user profiles...\n\n";

// Get all water availability records without coordinates
$stmt = $db->query("
    SELECT w.water_id, w.user_id, w.report_date, u.latitude, u.longitude
    FROM " . TABLE_PREFIX . "water_availability w
    JOIN " . TABLE_PREFIX . "users u ON w.user_id = u.user_id
    WHERE (w.latitude IS NULL OR w.longitude IS NULL)
    AND u.latitude IS NOT NULL AND u.longitude IS NOT NULL
");

$records = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalRecords = count($records);

echo "Found $totalRecords records to update.\n\n";

if ($totalRecords == 0) {
    echo "No records need updating. Migration complete.\n";
    exit(0);
}

$updated = 0;
$errors = 0;
$duplicates = 0;

// Start transaction
$db->beginTransaction();

try {
    foreach ($records as $record) {
        $waterId = $record['water_id'];
        $latitude = $record['latitude'];
        $longitude = $record['longitude'];
        $reportDate = $record['report_date'];
        
        // Check if a record with the same date and location already exists (and has coordinates)
        $stmt = $db->prepare("
            SELECT water_id 
            FROM " . TABLE_PREFIX . "water_availability 
            WHERE water_id != ? 
            AND report_date = ?
            AND latitude = ? 
            AND longitude = ?
            AND latitude IS NOT NULL
            AND longitude IS NOT NULL
            LIMIT 1
        ");
        $stmt->execute([$waterId, $reportDate, $latitude, $longitude]);
        $duplicate = $stmt->fetch();
        
        if ($duplicate) {
            // Merge with existing record - update the existing one and delete this duplicate
            echo "Found duplicate at location ($latitude, $longitude) for date $reportDate. Merging records...\n";
            
            // Get the has_water value from the current record
            $stmt = $db->prepare("SELECT has_water, reported_at FROM " . TABLE_PREFIX . "water_availability WHERE water_id = ?");
            $stmt->execute([$waterId]);
            $currentData = $stmt->fetch();
            
            if ($currentData) {
                // Get the existing record's reported_at
                $stmt = $db->prepare("SELECT reported_at FROM " . TABLE_PREFIX . "water_availability WHERE water_id = ?");
                $stmt->execute([$duplicate['water_id']]);
                $existingData = $stmt->fetch();
                
                // Use the more recent reported_at
                $newReportedAt = $currentData['reported_at'] > $existingData['reported_at'] 
                    ? $currentData['reported_at'] 
                    : $existingData['reported_at'];
                
                // Update the existing record (keep the most recent has_water value)
                $stmt = $db->prepare("
                    UPDATE " . TABLE_PREFIX . "water_availability 
                    SET has_water = ?,
                        reported_at = ?
                    WHERE water_id = ?
                ");
                $stmt->execute([$currentData['has_water'], $newReportedAt, $duplicate['water_id']]);
                
                // Delete the duplicate
                $stmt = $db->prepare("DELETE FROM " . TABLE_PREFIX . "water_availability WHERE water_id = ?");
                $stmt->execute([$waterId]);
            }
            
            $duplicates++;
        } else {
            // Update the record with coordinates
            $stmt = $db->prepare("
                UPDATE " . TABLE_PREFIX . "water_availability 
                SET latitude = ?, longitude = ?
                WHERE water_id = ?
            ");
            
            if ($stmt->execute([$latitude, $longitude, $waterId])) {
                $updated++;
                if ($updated % 100 == 0) {
                    echo "Updated $updated records...\n";
                }
            } else {
                $errors++;
                echo "Error updating record $waterId\n";
            }
        }
    }
    
    // Commit transaction
    $db->commit();
    
    echo "\nMigration complete!\n";
    echo "Updated: $updated records\n";
    echo "Merged duplicates: $duplicates records\n";
    echo "Errors: $errors records\n";
    
} catch (Exception $e) {
    // Rollback on error
    $db->rollBack();
    echo "\nError during migration: " . $e->getMessage() . "\n";
    echo "Transaction rolled back.\n";
    exit(1);
}

