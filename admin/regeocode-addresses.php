<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/geocoding.php';
requireRole('ADMIN');

$db = getDB();
$message = '';
$error = '';
$results = [];
$waterMigrationResults = [];

// Handle re-geocoding request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'regeocode') {
    $dryRun = isset($_POST['dry_run']) && $_POST['dry_run'] === '1';
    
    // Get all users with addresses
    $stmt = $db->query("
        SELECT user_id, username, name, surname, street_number, street_name, suburb, town, latitude, longitude
        FROM " . TABLE_PREFIX . "users
        WHERE street_name IS NOT NULL 
        AND street_name != ''
        AND town IS NOT NULL
        AND town != ''
        AND is_active = 1
        ORDER BY user_id
    ");
    $users = $stmt->fetchAll();
    
    $processed = 0;
    $updated = 0;
    $failed = 0;
    $skipped = 0;
    $coordinateMap = []; // Track coordinates to detect duplicates
    
    foreach ($users as $user) {
        $processed++;
        
        // Skip if no street number or street name
        if (empty($user['street_name']) || empty($user['town'])) {
            $skipped++;
            $results[] = [
                'user_id' => $user['user_id'],
                'username' => $user['username'],
                'name' => $user['name'] . ' ' . $user['surname'],
                'address' => trim(($user['street_number'] ?? '') . ' ' . ($user['street_name'] ?? '')) . ', ' . ($user['town'] ?? ''),
                'old_lat' => $user['latitude'],
                'old_lon' => $user['longitude'],
                'status' => 'skipped',
                'reason' => 'Missing required address fields'
            ];
            continue;
        }
        
        // Re-geocode the address
        $geocodeResult = validateAndGeocodeAddress([
            'street_number' => $user['street_number'] ?? '',
            'street_name' => $user['street_name'],
            'suburb' => $user['suburb'] ?? '',
            'town' => $user['town']
        ]);
        
        if ($geocodeResult['success'] && !empty($geocodeResult['latitude']) && !empty($geocodeResult['longitude'])) {
            $newLat = $geocodeResult['latitude'];
            $newLon = $geocodeResult['longitude'];
            
            // Check for duplicate coordinates (same coordinates for different addresses)
            $coordKey = round($newLat, 6) . ',' . round($newLon, 6);
            $hasDuplicate = false;
            if (isset($coordinateMap[$coordKey])) {
                $hasDuplicate = true;
                // Add this user to the duplicate list
                if (!isset($coordinateMap[$coordKey]['duplicates'])) {
                    $coordinateMap[$coordKey]['duplicates'] = [];
                }
                $coordinateMap[$coordKey]['duplicates'][] = [
                    'user_id' => $user['user_id'],
                    'username' => $user['username'],
                    'address' => trim(($user['street_number'] ?? '') . ' ' . $user['street_name']) . ', ' . $user['town']
                ];
            } else {
                $coordinateMap[$coordKey] = [
                    'user_id' => $user['user_id'],
                    'username' => $user['username'],
                    'address' => trim(($user['street_number'] ?? '') . ' ' . $user['street_name']) . ', ' . $user['town'],
                    'duplicates' => []
                ];
            }
            
            // Check if coordinates changed
            $coordsChanged = false;
            if (empty($user['latitude']) || empty($user['longitude'])) {
                $coordsChanged = true;
            } else {
                // Check if coordinates are significantly different (more than 0.001 degrees, roughly 100m)
                $latDiff = abs($user['latitude'] - $newLat);
                $lonDiff = abs($user['longitude'] - $newLon);
                if ($latDiff > 0.001 || $lonDiff > 0.001) {
                    $coordsChanged = true;
                }
            }
            
            if ($coordsChanged) {
                if (!$dryRun) {
                    // Update coordinates in database
                    $updateStmt = $db->prepare("
                        UPDATE " . TABLE_PREFIX . "users
                        SET latitude = ?, longitude = ?
                        WHERE user_id = ?
                    ");
                    $updateStmt->execute([$newLat, $newLon, $user['user_id']]);
                }
                
                $updated++;
                $results[] = [
                    'user_id' => $user['user_id'],
                    'username' => $user['username'],
                    'name' => $user['name'] . ' ' . $user['surname'],
                    'address' => trim(($user['street_number'] ?? '') . ' ' . $user['street_name']) . ', ' . $user['town'],
                    'old_lat' => $user['latitude'],
                    'old_lon' => $user['longitude'],
                    'new_lat' => $newLat,
                    'new_lon' => $newLon,
                    'status' => $dryRun ? 'would_update' : 'updated',
                    'approximate' => isset($geocodeResult['approximate']) && $geocodeResult['approximate'],
                    'has_duplicate' => $hasDuplicate,
                    'has_house_number' => isset($geocodeResult['has_house_number']) ? $geocodeResult['has_house_number'] : null
                ];
            } else {
                $skipped++;
                $results[] = [
                    'user_id' => $user['user_id'],
                    'username' => $user['username'],
                    'name' => $user['name'] . ' ' . $user['surname'],
                    'address' => trim(($user['street_number'] ?? '') . ' ' . $user['street_name']) . ', ' . $user['town'],
                    'old_lat' => $user['latitude'],
                    'old_lon' => $user['longitude'],
                    'new_lat' => $newLat,
                    'new_lon' => $newLon,
                    'status' => 'skipped',
                    'reason' => 'Coordinates unchanged',
                    'has_duplicate' => $hasDuplicate,
                    'has_house_number' => isset($geocodeResult['has_house_number']) ? $geocodeResult['has_house_number'] : null
                ];
            }
        } else {
            $failed++;
            $results[] = [
                'user_id' => $user['user_id'],
                'username' => $user['username'],
                'name' => $user['name'] . ' ' . $user['surname'],
                'address' => trim(($user['street_number'] ?? '') . ' ' . $user['street_name']) . ', ' . $user['town'],
                'status' => 'failed',
                'reason' => $geocodeResult['message'] ?? 'Geocoding failed'
            ];
        }
        
        // Add a small delay to respect Nominatim rate limits (1 request per second)
        usleep(1100000); // 1.1 seconds
    }
    
    if ($dryRun) {
        $message = "Dry run completed: Would update {$updated} addresses, {$failed} failed, {$skipped} skipped out of {$processed} total users.";
    } else {
        $message = "Re-geocoding completed: Updated {$updated} addresses, {$failed} failed, {$skipped} skipped out of {$processed} total users.";
    }
}

// Handle water availability coordinates migration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'migrate_water_coordinates') {
    $dryRun = isset($_POST['dry_run']) && $_POST['dry_run'] === '1';
    
    // Get all water availability records without coordinates
    $stmt = $db->query("
        SELECT w.water_id, w.user_id, w.report_date, u.latitude, u.longitude, u.username, u.name, u.surname
        FROM " . TABLE_PREFIX . "water_availability w
        JOIN " . TABLE_PREFIX . "users u ON w.user_id = u.user_id
        WHERE (w.latitude IS NULL OR w.longitude IS NULL)
        AND u.latitude IS NOT NULL AND u.longitude IS NOT NULL
        ORDER BY w.water_id
    ");
    
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalRecords = count($records);
    
    $updated = 0;
    $errors = 0;
    $duplicates = 0;
    $skipped = 0;
    
    if ($totalRecords > 0) {
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
                    // Merge with existing record
                    $stmt = $db->prepare("SELECT has_water, reported_at FROM " . TABLE_PREFIX . "water_availability WHERE water_id = ?");
                    $stmt->execute([$waterId]);
                    $currentData = $stmt->fetch();
                    
                    if ($currentData) {
                        $stmt = $db->prepare("SELECT reported_at FROM " . TABLE_PREFIX . "water_availability WHERE water_id = ?");
                        $stmt->execute([$duplicate['water_id']]);
                        $existingData = $stmt->fetch();
                        
                        $newReportedAt = $currentData['reported_at'] > $existingData['reported_at'] 
                            ? $currentData['reported_at'] 
                            : $existingData['reported_at'];
                        
                        if (!$dryRun) {
                            $stmt = $db->prepare("
                                UPDATE " . TABLE_PREFIX . "water_availability 
                                SET has_water = ?, reported_at = ?
                                WHERE water_id = ?
                            ");
                            $stmt->execute([$currentData['has_water'], $newReportedAt, $duplicate['water_id']]);
                            
                            $stmt = $db->prepare("DELETE FROM " . TABLE_PREFIX . "water_availability WHERE water_id = ?");
                            $stmt->execute([$waterId]);
                        }
                        
                        $waterMigrationResults[] = [
                            'water_id' => $waterId,
                            'user' => trim(($record['name'] ?? '') . ' ' . ($record['surname'] ?? '')) ?: $record['username'],
                            'report_date' => $reportDate,
                            'status' => $dryRun ? 'would_merge' : 'merged',
                            'reason' => 'Duplicate at same location for same date',
                            'merged_with' => $duplicate['water_id']
                        ];
                        $duplicates++;
                    }
                } else {
                    // Update the record with coordinates
                    if (!$dryRun) {
                        $stmt = $db->prepare("
                            UPDATE " . TABLE_PREFIX . "water_availability 
                            SET latitude = ?, longitude = ?
                            WHERE water_id = ?
                        ");
                        
                        if ($stmt->execute([$latitude, $longitude, $waterId])) {
                            $updated++;
                        } else {
                            $errors++;
                            $waterMigrationResults[] = [
                                'water_id' => $waterId,
                                'user' => trim(($record['name'] ?? '') . ' ' . ($record['surname'] ?? '')) ?: $record['username'],
                                'report_date' => $reportDate,
                                'status' => 'error',
                                'reason' => 'Failed to update coordinates'
                            ];
                        }
                    } else {
                        $updated++;
                    }
                    
                    $waterMigrationResults[] = [
                        'water_id' => $waterId,
                        'user' => trim(($record['name'] ?? '') . ' ' . ($record['surname'] ?? '')) ?: $record['username'],
                        'report_date' => $reportDate,
                        'status' => $dryRun ? 'would_update' : 'updated',
                        'latitude' => $latitude,
                        'longitude' => $longitude
                    ];
                }
            }
            
            // Commit transaction
            if (!$dryRun) {
                $db->commit();
            } else {
                $db->rollBack();
            }
            
            if ($dryRun) {
                $message = "Dry run completed: Would update {$updated} records, merge {$duplicates} duplicates, {$errors} errors out of {$totalRecords} total records.";
            } else {
                $message = "Migration completed: Updated {$updated} records, merged {$duplicates} duplicates, {$errors} errors out of {$totalRecords} total records.";
            }
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Error during migration: " . $e->getMessage();
            error_log("Water availability coordinates migration error: " . $e->getMessage());
        }
    } else {
        $message = "No records need updating. All water availability records already have coordinates.";
    }
}

// Get statistics
$statsStmt = $db->query("
    SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN latitude IS NOT NULL AND longitude IS NOT NULL THEN 1 ELSE 0 END) as geocoded_users,
        SUM(CASE WHEN street_number IS NOT NULL AND street_number != '' THEN 1 ELSE 0 END) as users_with_street_number
    FROM " . TABLE_PREFIX . "users
    WHERE is_active = 1
    AND street_name IS NOT NULL
    AND street_name != ''
    AND town IS NOT NULL
    AND town != ''
");
$stats = $statsStmt->fetch();

// Get water availability statistics
$waterStatsStmt = $db->query("
    SELECT 
        COUNT(*) as total_records,
        SUM(CASE WHEN latitude IS NOT NULL AND longitude IS NOT NULL THEN 1 ELSE 0 END) as records_with_coordinates,
        SUM(CASE WHEN latitude IS NULL OR longitude IS NULL THEN 1 ELSE 0 END) as records_without_coordinates
    FROM " . TABLE_PREFIX . "water_availability
");
$waterStats = $waterStatsStmt->fetch();

$pageTitle = 'Re-geocode Addresses';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="content-area">
        <h1>Re-geocode Addresses</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= h($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        
        <div class="regeocode-info">
            <h2>Current Statistics</h2>
            <ul>
                <li><strong>Total users with addresses:</strong> <?= $stats['total_users'] ?></li>
                <li><strong>Users with geocoded coordinates:</strong> <?= $stats['geocoded_users'] ?></li>
                <li><strong>Users with street numbers:</strong> <?= $stats['users_with_street_number'] ?></li>
            </ul>
            
            <div class="alert alert-info">
                <strong>Note:</strong> This script will re-geocode all user addresses using the updated geocoding function that includes street numbers. 
                The process respects Nominatim rate limits (1 request per second), so it may take some time to complete.
                <br><br>
                <strong>Dry Run:</strong> Check the "Dry Run" option to see what would be updated without actually making changes.
            </div>
        </div>
        
        <div class="regeocode-form">
            <h2>Re-geocode All Addresses</h2>
            <form method="POST" action="" onsubmit="return confirm('This will re-geocode all user addresses. It may take several minutes. Continue?');">
                <input type="hidden" name="action" value="regeocode">
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="dry_run" value="1" checked>
                        Dry Run (preview changes without updating database)
                    </label>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Start Re-geocoding</button>
                </div>
            </form>
        </div>
        
        <?php if (!empty($results)): 
            // Find duplicate coordinates
            $duplicates = [];
            $coordGroups = [];
            foreach ($results as $result) {
                if (isset($result['new_lat']) && isset($result['new_lon'])) {
                    $coordKey = round($result['new_lat'], 6) . ',' . round($result['new_lon'], 6);
                    if (!isset($coordGroups[$coordKey])) {
                        $coordGroups[$coordKey] = [];
                    }
                    $coordGroups[$coordKey][] = $result;
                }
            }
            foreach ($coordGroups as $coordKey => $group) {
                if (count($group) > 1) {
                    $duplicates[$coordKey] = $group;
                }
            }
        ?>
            <div class="regeocode-results">
                <h2>Results</h2>
                <div class="results-summary">
                    <p>
                        <strong>Total Processed:</strong> <?= count($results) ?><br>
                        <strong>Updated/Would Update:</strong> <?= count(array_filter($results, function($r) { return in_array($r['status'], ['updated', 'would_update']); })) ?><br>
                        <strong>Failed:</strong> <?= count(array_filter($results, function($r) { return $r['status'] === 'failed'; })) ?><br>
                        <strong>Skipped:</strong> <?= count(array_filter($results, function($r) { return $r['status'] === 'skipped'; })) ?><br>
                        <?php if (!empty($duplicates)): ?>
                            <strong style="color: #dc3545;">⚠️ Duplicate Coordinates Found:</strong> <?= count($duplicates) ?> location(s) with multiple addresses<br>
                            <small style="color: #856404;">This indicates that Nominatim doesn't have specific house number data for these addresses.</small>
                        <?php endif; ?>
                    </p>
                </div>
                
                <?php if (!empty($duplicates)): ?>
                    <div class="duplicates-warning">
                        <h3>⚠️ Duplicate Coordinates Detected</h3>
                        <p>The following addresses have the same coordinates, which indicates that Nominatim doesn't have specific house number data for these locations:</p>
                        <?php foreach ($duplicates as $coordKey => $group): ?>
                            <div class="duplicate-group">
                                <strong>Coordinates:</strong> <?= number_format($group[0]['new_lat'], 6) ?>, <?= number_format($group[0]['new_lon'], 6) ?><br>
                                <strong>Addresses with same coordinates:</strong>
                                <ul>
                                    <?php foreach ($group as $item): ?>
                                        <li><?= h($item['address']) ?> (<?= h($item['username']) ?>)</li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <div class="results-table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>User ID</th>
                                <th>Username</th>
                                <th>Name</th>
                                <th>Address</th>
                                    <th>Old Coordinates</th>
                                    <th>New Coordinates</th>
                                    <th>Status</th>
                                    <th>Notes</th>
                                    <th>House # in Result</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $result): ?>
                                <tr class="result-<?= $result['status'] ?>">
                                    <td><?= $result['user_id'] ?></td>
                                    <td><?= h($result['username']) ?></td>
                                    <td><?= h($result['name'] ?? 'N/A') ?></td>
                                    <td><?= h($result['address'] ?? 'N/A') ?></td>
                                    <td>
                                        <?php if (isset($result['old_lat']) && isset($result['old_lon'])): ?>
                                            <?= number_format($result['old_lat'], 6) ?>, <?= number_format($result['old_lon'], 6) ?>
                                        <?php else: ?>
                                            None
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (isset($result['new_lat']) && isset($result['new_lon'])): ?>
                                            <?= number_format($result['new_lat'], 6) ?>, <?= number_format($result['new_lon'], 6) ?>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= $result['status'] ?>">
                                            <?php
                                            switch($result['status']) {
                                                case 'updated':
                                                    echo 'Updated';
                                                    break;
                                                case 'would_update':
                                                    echo 'Would Update';
                                                    break;
                                                case 'failed':
                                                    echo 'Failed';
                                                    break;
                                                case 'skipped':
                                                    echo 'Skipped';
                                                    break;
                                                default:
                                                    echo ucfirst($result['status']);
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (isset($result['approximate']) && $result['approximate']): ?>
                                            <span class="approximate-warning">Approximate location</span><br>
                                        <?php endif; ?>
                                        <?php if (isset($result['has_duplicate']) && $result['has_duplicate']): ?>
                                            <span class="duplicate-warning">⚠️ Duplicate coordinates</span><br>
                                        <?php endif; ?>
                                        <?php if (isset($result['reason'])): ?>
                                            <?= h($result['reason']) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (isset($result['has_house_number'])): ?>
                                            <?= $result['has_house_number'] ? '<span style="color: green;">✓ Yes</span>' : '<span style="color: orange;">✗ No</span>' ?>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Water Availability Coordinates Migration Section -->
        <div class="regeocode-info">
            <h2>Water Availability Coordinates Migration</h2>
            <h3>Current Statistics</h3>
            <ul>
                <li><strong>Total water availability records:</strong> <?= $waterStats['total_records'] ?></li>
                <li><strong>Records with coordinates:</strong> <?= $waterStats['records_with_coordinates'] ?></li>
                <li><strong>Records without coordinates:</strong> <?= $waterStats['records_without_coordinates'] ?></li>
            </ul>
            
            <div class="alert alert-info">
                <strong>Note:</strong> This migration will populate existing water availability records with coordinates from user profiles. 
                If multiple records exist for the same location and date, they will be merged into a single record.
                <br><br>
                <strong>Dry Run:</strong> Check the "Dry Run" option to see what would be updated without actually making changes.
            </div>
        </div>
        
        <div class="regeocode-form">
            <h2>Migrate Water Availability Coordinates</h2>
            <form method="POST" action="" onsubmit="return confirm('This will update water availability records with coordinates from user profiles. Continue?');">
                <input type="hidden" name="action" value="migrate_water_coordinates">
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="dry_run" value="1" checked>
                        Dry Run (preview changes without updating database)
                    </label>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Start Migration</button>
                </div>
            </form>
        </div>
        
        <?php if (!empty($waterMigrationResults)): ?>
            <div class="regeocode-results">
                <h2>Migration Results</h2>
                <div class="results-summary">
                    <p>
                        <strong>Total Processed:</strong> <?= count($waterMigrationResults) ?><br>
                        <strong>Updated/Would Update:</strong> <?= count(array_filter($waterMigrationResults, function($r) { return in_array($r['status'], ['updated', 'would_update']); })) ?><br>
                        <strong>Merged/Would Merge:</strong> <?= count(array_filter($waterMigrationResults, function($r) { return in_array($r['status'], ['merged', 'would_merge']); })) ?><br>
                        <strong>Errors:</strong> <?= count(array_filter($waterMigrationResults, function($r) { return $r['status'] === 'error'; })) ?><br>
                    </p>
                </div>
                
                <div class="results-table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Water ID</th>
                                <th>User</th>
                                <th>Report Date</th>
                                <th>Coordinates</th>
                                <th>Status</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($waterMigrationResults as $result): ?>
                                <tr class="result-<?= $result['status'] ?>">
                                    <td><?= $result['water_id'] ?></td>
                                    <td><?= h($result['user']) ?></td>
                                    <td><?= h($result['report_date']) ?></td>
                                    <td>
                                        <?php if (isset($result['latitude']) && isset($result['longitude'])): ?>
                                            <?= number_format($result['latitude'], 6) ?>, <?= number_format($result['longitude'], 6) ?>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= $result['status'] ?>">
                                            <?php
                                            switch($result['status']) {
                                                case 'updated':
                                                    echo 'Updated';
                                                    break;
                                                case 'would_update':
                                                    echo 'Would Update';
                                                    break;
                                                case 'merged':
                                                    echo 'Merged';
                                                    break;
                                                case 'would_merge':
                                                    echo 'Would Merge';
                                                    break;
                                                case 'error':
                                                    echo 'Error';
                                                    break;
                                                default:
                                                    echo ucfirst($result['status']);
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (isset($result['reason'])): ?>
                                            <?= h($result['reason']) ?>
                                        <?php endif; ?>
                                        <?php if (isset($result['merged_with'])): ?>
                                            <br><small>Merged with record #<?= $result['merged_with'] ?></small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.regeocode-info,
.regeocode-form,
.regeocode-results {
    background-color: var(--white);
    padding: 2rem;
    border-radius: 8px;
    box-shadow: var(--shadow);
    margin-bottom: 2rem;
}

.regeocode-info ul {
    list-style: none;
    padding: 0;
}

.regeocode-info li {
    padding: 0.5rem 0;
    font-size: 1.1rem;
}

.results-summary {
    background-color: var(--bg-color);
    padding: 1rem;
    border-radius: 4px;
    margin-bottom: 1rem;
}

.results-table-container {
    max-height: 600px;
    overflow-y: auto;
}

.result-updated {
    background-color: #d4edda;
}

.result-would_update {
    background-color: #fff3cd;
}

.result-failed {
    background-color: #f8d7da;
}

.result-skipped {
    background-color: #e2e3e5;
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 4px;
    font-size: 0.875rem;
    font-weight: 600;
}

.status-updated {
    background-color: #28a745;
    color: white;
}

.status-would_update {
    background-color: #ffc107;
    color: #212529;
}

.status-failed {
    background-color: #dc3545;
    color: white;
}

.status-skipped {
    background-color: #6c757d;
    color: white;
}

.status-merged,
.status-would_merge {
    background-color: #17a2b8;
    color: white;
}

.approximate-warning {
    color: #856404;
    font-size: 0.875rem;
    font-style: italic;
}

.duplicates-warning {
    background-color: #fff3cd;
    border: 2px solid #ffc107;
    border-radius: 4px;
    padding: 1rem;
    margin-bottom: 1rem;
}

.duplicate-group {
    background-color: white;
    padding: 0.75rem;
    margin: 0.5rem 0;
    border-radius: 4px;
    border-left: 4px solid #dc3545;
}

.duplicate-group ul {
    margin: 0.5rem 0;
    padding-left: 1.5rem;
}

.duplicate-warning {
    color: #dc3545;
    font-size: 0.875rem;
    font-weight: 600;
}
</style>

<?php 
$hideAdverts = true;
include __DIR__ . '/../includes/footer.php'; 
?>

