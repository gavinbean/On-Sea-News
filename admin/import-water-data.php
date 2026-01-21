<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/geocoding.php';
requireRole('ADMIN');

$db = getDB();
$message = '';
$error = '';
$importedData = [];

// Create temporary table if it doesn't exist
$db->exec("
    CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX . "water_import_temp (
        temp_id INT(11) NOT NULL AUTO_INCREMENT,
        address_name VARCHAR(255) NOT NULL,
        report_date DATE NOT NULL,
        has_water TINYINT(1) NOT NULL DEFAULT 1,
        latitude DECIMAL(10,8) NULL,
        longitude DECIMAL(11,8) NULL,
        geocode_status VARCHAR(50) NULL,
        geocode_error TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (temp_id),
        INDEX idx_address_name (address_name),
        INDEX idx_date (report_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Handle CSV upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_csv') {
    $csvFile = $_FILES['csv_file']['tmp_name'] ?? null;
    
    if (!$csvFile || !is_uploaded_file($csvFile)) {
        $error = 'Please select a CSV file to upload.';
    } else {
        // Clear existing temp data
        $db->exec("TRUNCATE TABLE " . TABLE_PREFIX . "water_import_temp");
        
        // Parse CSV
        $handle = fopen($csvFile, 'r');
        if ($handle === false) {
            $error = 'Failed to open CSV file.';
        } else {
            $rowNum = 0;
            $imported = 0;
            $errors = 0;
            $headerMap = []; // Map column names to indices
            
            try {
                while (($data = fgetcsv($handle, 2000, ',')) !== false) {
                    $rowNum++;
                    
                    // Parse header row
                    if ($rowNum === 1) {
                        // Remove BOM if present (UTF-8 BOM: EF BB BF)
                        $bom = pack('H*', 'EFBBBF');
                        foreach ($data as $index => $header) {
                            // Remove BOM, trim whitespace, normalize
                            $header = trim($header);
                            $header = str_replace($bom, '', $header);
                            $header = trim($header);
                            // Remove any non-printable characters
                            $header = preg_replace('/[\x00-\x1F\x7F]/', '', $header);
                            $headerKey = strtolower($header);
                            $headerMap[$headerKey] = $index;
                        }
                        
                        // Debug: Log found headers
                        error_log("CSV Import: Found headers: " . implode(', ', array_keys($headerMap)));
                        
                        // Validate required headers (try multiple variations)
                        $requiredHeaders = [
                            'timestamp' => ['timestamp', 'time', 'date', 'datetime'],
                            'name' => ['name', 'full name', 'fullname'],
                            'email' => ['email', 'e-mail', 'email address'],
                            'address' => ['address', 'location', 'full address'],
                            'water status' => ['water status', 'waterstatus', 'status', 'water', 'water_status']
                        ];
                        
                        $missingHeaders = [];
                        $foundHeaders = [];
                        
                        foreach ($requiredHeaders as $reqKey => $variations) {
                            $found = false;
                            foreach ($variations as $variation) {
                                if (isset($headerMap[$variation])) {
                                    // Map the variation to the standard key
                                    $headerMap[$reqKey] = $headerMap[$variation];
                                    $found = true;
                                    $foundHeaders[] = $reqKey;
                                    break;
                                }
                            }
                            if (!$found) {
                                $missingHeaders[] = $reqKey;
                            }
                        }
                        
                        if (!empty($missingHeaders)) {
                            $error = 'Missing required headers: ' . implode(', ', $missingHeaders) . 
                                     '. Found headers: ' . implode(', ', array_keys($headerMap));
                            error_log("CSV Import Error: " . $error);
                            break; // Exit the while loop
                        }
                        continue;
                    }
                    
                    // Parse data rows - handle cases where columns might be missing
                $timestamp = isset($headerMap['timestamp']) && isset($data[$headerMap['timestamp']]) ? trim($data[$headerMap['timestamp']]) : '';
                $name = isset($headerMap['name']) && isset($data[$headerMap['name']]) ? trim($data[$headerMap['name']]) : '';
                $email = isset($headerMap['email']) && isset($data[$headerMap['email']]) ? trim($data[$headerMap['email']]) : '';
                $address = isset($headerMap['address']) && isset($data[$headerMap['address']]) ? trim($data[$headerMap['address']]) : '';
                $waterStatus = isset($headerMap['water status']) && isset($data[$headerMap['water status']]) ? trim($data[$headerMap['water status']]) : '';
                
                // Remove quotes from address if present
                $address = trim($address, '"');
                
                if (empty($address)) {
                    $errors++;
                    error_log("CSV Import: Row {$rowNum} skipped - empty address");
                    continue;
                }
                
                // Parse timestamp to get report_date
                // Format: 29/12/2025 08:45:23 or similar
                $reportDate = null;
                if (!empty($timestamp)) {
                    // Try multiple date formats
                    $dateFormats = [
                        'd/m/Y H:i:s',  // 29/12/2025 08:45:23
                        'd/m/Y H:i',    // 29/12/2025 08:45
                        'd/m/Y',        // 29/12/2025
                        'Y-m-d H:i:s',  // 2025-12-29 08:45:23
                        'Y-m-d',        // 2025-12-29
                    ];
                    
                    foreach ($dateFormats as $format) {
                        $dateObj = DateTime::createFromFormat($format, $timestamp);
                        if ($dateObj !== false) {
                            $reportDate = $dateObj->format('Y-m-d');
                            break;
                        }
                    }
                }
                
                // If timestamp parsing failed, use today's date
                if (!$reportDate) {
                    $reportDate = date('Y-m-d');
                }
                
                // Parse water status
                // Format: "Water status: No" or "Water status: Yes" or "Water status: Intermittent"
                $hasWater = 1; // Default to Yes
                if (!empty($waterStatus)) {
                    $waterStatusLower = strtolower($waterStatus);
                    if (strpos($waterStatusLower, 'no') !== false) {
                        $hasWater = 0;
                    } elseif (strpos($waterStatusLower, 'intermittent') !== false) {
                        $hasWater = 2;
                    } elseif (strpos($waterStatusLower, 'yes') !== false) {
                        $hasWater = 1;
                    }
                }
                
                // Use address as address_name (we'll geocode it later)
                $addressName = $address;
                
                // Insert into temp table
                try {
                    $stmt = $db->prepare("
                        INSERT INTO " . TABLE_PREFIX . "water_import_temp 
                        (address_name, report_date, has_water)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$addressName, $reportDate, $hasWater]);
                    $imported++;
                } catch (PDOException $e) {
                    $errors++;
                    error_log("CSV Import: Row {$rowNum} failed to insert - " . $e->getMessage());
                    continue;
                }
                }
            } finally {
                // Always close the file handle if it's valid
                // PHP 7: is_resource(), PHP 8+: is_object() or just check if not false
                if ($handle !== false && (is_resource($handle) || is_object($handle))) {
                    @fclose($handle); // Use @ to suppress errors if already closed
                }
            }
            
            if (empty($error)) {
                if ($imported > 0) {
                    $message = "CSV imported: {$imported} records loaded into temporary table.";
                    if ($errors > 0) {
                        $message .= " {$errors} rows skipped due to errors.";
                    }
                } else {
                    $error = "No valid records found in CSV file. Please check the format and try again.";
                }
            }
        }
    }
}

// Handle geocoding
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'geocode') {
    // Set longer execution time for geocoding
    set_time_limit(600); // 10 minutes
    
    // Only get records that are still pending (NULL or 'failed' status)
    // Exclude records that are already 'success', 'duplicate', or 'manual'
    $stmt = $db->query("
        SELECT temp_id, address_name, report_date, has_water, latitude, longitude, geocode_status
        FROM " . TABLE_PREFIX . "water_import_temp
        WHERE (geocode_status IS NULL OR geocode_status = 'failed')
        ORDER BY address_name, report_date
    ");
    $records = $stmt->fetchAll();
    
    $totalRecords = count($records);
    $geocoded = 0;
    $failed = 0;
    $skipped = 0;
    $addressMap = []; // Track addresses by name for duplicate handling
    $processed = 0;
    
    // Start transaction for better performance
    $db->beginTransaction();
    
    try {
        foreach ($records as $record) {
            $processed++;
            
            // Check if we're running out of time (leave 5 seconds buffer)
            if ($processed % 10 == 0) { // Check every 10 records
                $remainingTime = ini_get('max_execution_time') - (time() - $_SERVER['REQUEST_TIME']);
                if ($remainingTime < 5) {
                    // Commit what we've done so far
                    $db->commit();
                    // Update message to indicate partial completion
                    $message = "Geocoding partially completed: {$geocoded} addresses geocoded, {$failed} failed, {$skipped} skipped out of {$processed} processed (of {$totalRecords} total). Process timed out. You can run geocoding again to continue with remaining records.";
                    break;
                }
            }
            
            $tempId = $record['temp_id'];
            $addressName = $record['address_name'];
        
            // Check if we already geocoded this address name (in this session)
            if (isset($addressMap[$addressName]) && $addressMap[$addressName]['success']) {
                // Use existing coordinates from this session
                $stmt = $db->prepare("
                    UPDATE " . TABLE_PREFIX . "water_import_temp
                    SET latitude = ?, longitude = ?, geocode_status = 'duplicate', geocode_error = NULL
                    WHERE temp_id = ?
                ");
                $stmt->execute([
                    $addressMap[$addressName]['latitude'],
                    $addressMap[$addressName]['longitude'],
                    $tempId
                ]);
                $geocoded++;
                continue;
            }
            
            // Also check if this address was already geocoded in a previous run
            $checkStmt = $db->prepare("
                SELECT latitude, longitude 
                FROM " . TABLE_PREFIX . "water_import_temp
                WHERE address_name = ? 
                AND geocode_status IN ('success', 'duplicate', 'manual')
                AND latitude IS NOT NULL 
                AND longitude IS NOT NULL
                LIMIT 1
            ");
            $checkStmt->execute([$addressName]);
            $existingGeocode = $checkStmt->fetch();
            
            if ($existingGeocode) {
                // Use coordinates from previous successful geocode
                $stmt = $db->prepare("
                    UPDATE " . TABLE_PREFIX . "water_import_temp
                    SET latitude = ?, longitude = ?, geocode_status = 'duplicate', geocode_error = NULL
                    WHERE temp_id = ?
                ");
                $stmt->execute([
                    $existingGeocode['latitude'],
                    $existingGeocode['longitude'],
                    $tempId
                ]);
                $geocoded++;
                $skipped++; // Count as skipped since we didn't actually geocode it
                continue;
            }
        
            // Try to geocode - parse the full address
            // Address format: "16 Northwood Road, Kenton on Sea, Eastern Cape, South Africa"
            // Try to extract street number, street name, and town
            $addressParts = array_map('trim', explode(',', $addressName));
            $streetNumber = '';
            $streetName = '';
            $suburb = '';
            $town = '';
            
            // First part might be street number + street name
            if (!empty($addressParts[0])) {
                $firstPart = $addressParts[0];
                // Check if it starts with a number
                if (preg_match('/^(\d+)\s+(.+)$/', $firstPart, $matches)) {
                    $streetNumber = $matches[1];
                    $streetName = $matches[2];
                } else {
                    $streetName = $firstPart;
                }
            }
            
            // Remaining parts: suburb, town, province, country
            if (count($addressParts) > 1) {
                $town = $addressParts[1] ?? '';
            }
            if (count($addressParts) > 2) {
                $suburb = $addressParts[1] ?? '';
                $town = $addressParts[2] ?? '';
            }
            
            // Clean up town name (remove "Eastern Cape", "South Africa", etc.)
            $town = preg_replace('/\b(Eastern Cape|Western Cape|Northern Cape|Free State|KwaZulu-Natal|Limpopo|Mpumalanga|North West|Gauteng|South Africa)\b/i', '', $town);
            $town = trim($town, ', ');
            
            // If no town extracted, try to find it in the address
            if (empty($town)) {
                foreach ($addressParts as $part) {
                    if (stripos($part, 'Kenton') !== false || stripos($part, 'Bushmans') !== false) {
                        $town = trim($part);
                        break;
                    }
                }
            }
            
            // Default to Kenton-on-Sea if no town found
            if (empty($town)) {
                $town = 'Kenton-on-Sea';
            }
            
            // Geocode with parsed components
            $geocodeResult = validateAndGeocodeAddress([
                'street_number' => $streetNumber,
                'street_name' => $streetName,
                'suburb' => $suburb,
                'town' => $town
            ]);
            
            // If that fails, try geocoding the full address string
            if (!$geocodeResult['success'] || empty($geocodeResult['latitude'])) {
                $geocodeResult = validateAndGeocodeAddress($addressName);
            }
            
            if ($geocodeResult['success'] && !empty($geocodeResult['latitude']) && !empty($geocodeResult['longitude'])) {
                $latitude = round((float)$geocodeResult['latitude'], 6);
                $longitude = round((float)$geocodeResult['longitude'], 6);
                
                // Store in address map for duplicate handling (this session)
                $addressMap[$addressName] = [
                    'success' => true,
                    'latitude' => $latitude,
                    'longitude' => $longitude
                ];
                
                // Update this record
                $stmt = $db->prepare("
                    UPDATE " . TABLE_PREFIX . "water_import_temp
                    SET latitude = ?, longitude = ?, geocode_status = 'success', geocode_error = NULL
                    WHERE temp_id = ?
                ");
                $stmt->execute([$latitude, $longitude, $tempId]);
                $geocoded++;
                
                // Update all other PENDING records with the same address name
                $stmt = $db->prepare("
                    UPDATE " . TABLE_PREFIX . "water_import_temp
                    SET latitude = ?, longitude = ?, geocode_status = 'duplicate', geocode_error = NULL
                    WHERE address_name = ? 
                    AND temp_id != ?
                    AND (geocode_status IS NULL OR geocode_status = 'failed')
                ");
                $stmt->execute([$latitude, $longitude, $addressName, $tempId]);
                
            } else {
                $errorMsg = $geocodeResult['message'] ?? 'Geocoding failed';
                $stmt = $db->prepare("
                    UPDATE " . TABLE_PREFIX . "water_import_temp
                    SET geocode_status = 'failed', geocode_error = ?
                    WHERE temp_id = ?
                ");
                $stmt->execute([$errorMsg, $tempId]);
                $failed++;
            }
            
            // Rate limit: 1 request per second
            usleep(1100000);
        }
        
        // Commit transaction if we completed all records
        if ($processed >= $totalRecords) {
            $db->commit();
        }
        
    } catch (Exception $e) {
        // Rollback on error
        $db->rollBack();
        $error = 'Error during geocoding: ' . $e->getMessage();
        error_log("Geocoding error: " . $e->getMessage());
        
        // Still show progress
        if ($processed > 0) {
            $message = "Geocoding interrupted: {$geocoded} addresses geocoded, {$failed} failed, {$skipped} skipped out of {$processed} processed. Error: " . $e->getMessage() . ". You can run geocoding again to continue with remaining records.";
        }
    }
    
    // Set final message if not already set
    if (empty($message) && empty($error)) {
        if ($processed >= $totalRecords) {
            $message = "Geocoding completed: {$geocoded} addresses geocoded, {$failed} failed, {$skipped} skipped out of {$totalRecords} total records.";
        } else {
            $remaining = $totalRecords - $processed;
            $message = "Geocoding partially completed: {$geocoded} addresses geocoded, {$failed} failed, {$skipped} skipped out of {$processed} processed (of {$totalRecords} total). {$remaining} records remaining. You can run geocoding again to continue.";
        }
    }
}

// Handle manual coordinate update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_coords') {
    $tempId = (int)($_POST['temp_id'] ?? 0);
    $latitude = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
    $longitude = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;
    $addressName = $_POST['address_name'] ?? '';
    
    if ($tempId > 0 && $latitude !== null && $longitude !== null) {
        // Update this record
        $stmt = $db->prepare("
            UPDATE " . TABLE_PREFIX . "water_import_temp
            SET latitude = ?, longitude = ?, geocode_status = 'manual', geocode_error = NULL
            WHERE temp_id = ?
        ");
        $stmt->execute([$latitude, $longitude, $tempId]);
        
        // Update all records with the same address name
        $stmt = $db->prepare("
            UPDATE " . TABLE_PREFIX . "water_import_temp
            SET latitude = ?, longitude = ?, geocode_status = CASE 
                WHEN temp_id = ? THEN 'manual'
                ELSE 'duplicate'
            END, geocode_error = NULL
            WHERE address_name = ?
        ");
        $stmt->execute([$latitude, $longitude, $tempId, $addressName]);
        
        $message = 'Coordinates updated successfully.';
    } else {
        $error = 'Invalid coordinates provided.';
    }
}

// Handle import to water_availability
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_to_water') {
    $db->beginTransaction();
    
    try {
        $stmt = $db->query("
            SELECT temp_id, address_name, report_date, has_water, latitude, longitude
            FROM " . TABLE_PREFIX . "water_import_temp
            WHERE latitude IS NOT NULL AND longitude IS NOT NULL
            ORDER BY report_date, address_name
        ");
        $records = $stmt->fetchAll();
        
        $imported = 0;
        $skipped = 0;
        $errors = 0;
        
        foreach ($records as $record) {
            $reportDate = $record['report_date'];
            $hasWater = (int)$record['has_water'];
            $latitude = round((float)$record['latitude'], 6);
            $longitude = round((float)$record['longitude'], 6);
            
            // Check if record already exists (same date and location)
            $stmt = $db->prepare("
                SELECT water_id 
                FROM " . TABLE_PREFIX . "water_availability 
                WHERE report_date = ? 
                AND ABS(latitude - ?) < 0.000001
                AND ABS(longitude - ?) < 0.000001
                LIMIT 1
            ");
            $stmt->execute([$reportDate, $latitude, $longitude]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Update existing record (but don't overwrite user_id if it exists)
                $stmt = $db->prepare("
                    UPDATE " . TABLE_PREFIX . "water_availability 
                    SET has_water = ?, reported_at = NOW()
                    WHERE water_id = ? AND user_id IS NULL
                ");
                $stmt->execute([$hasWater, $existing['water_id']]);
                $skipped++;
            } else {
                // Insert new record without user_id
                $stmt = $db->prepare("
                    INSERT INTO " . TABLE_PREFIX . "water_availability 
                    (user_id, report_date, has_water, latitude, longitude, reported_at)
                    VALUES (NULL, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$reportDate, $hasWater, $latitude, $longitude]);
                $imported++;
            }
        }
        
        $db->commit();
        $message = "Import completed: {$imported} new records added, {$skipped} existing records updated, {$errors} errors.";
        
        // Optionally clear temp table after successful import
        if (isset($_POST['clear_temp']) && $_POST['clear_temp'] === '1') {
            $db->exec("TRUNCATE TABLE " . TABLE_PREFIX . "water_import_temp");
            $message .= " Temporary table cleared.";
        }
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = 'Error importing data: ' . $e->getMessage();
        error_log("Water data import error: " . $e->getMessage());
    }
}

// Get all temp records
$stmt = $db->query("
    SELECT temp_id, address_name, report_date, has_water, latitude, longitude, geocode_status, geocode_error
    FROM " . TABLE_PREFIX . "water_import_temp
    ORDER BY address_name, report_date
");
$importedData = $stmt->fetchAll();

$pageTitle = 'Import Water Data';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="content-area">
        <h1>Import Water Data from CSV</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= h($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        
        <div class="import-section">
            <h2>1. Upload CSV File</h2>
            <p>CSV format with headers: <code>Timestamp, Name, Email, Address, Water status</code></p>
            <p><strong>Example:</strong></p>
            <pre>Timestamp,Name,Email,Address,Water status
29/12/2025 08:45:23,Tiaan,tiaan@wildlifemarketing.co.za,"16 Northwood Road, Kenton on Sea, Eastern Cape, South Africa",Water status: No
30/12/2025 09:15:10,John,john@example.com,"Main Street, Kenton on Sea",Water status: Yes</pre>
            <p><strong>Notes:</strong></p>
            <ul>
                <li>Address must be enclosed in quotes if it contains commas</li>
                <li>Timestamp format: DD/MM/YYYY HH:MM:SS or DD/MM/YYYY</li>
                <li>Water status: "Water status: No", "Water status: Yes", or "Water status: Intermittent"</li>
            </ul>
            
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_csv">
                <div class="form-group">
                    <label for="csv_file">CSV File:</label>
                    <input type="file" id="csv_file" name="csv_file" accept=".csv" required>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Upload CSV</button>
                </div>
            </form>
        </div>
        
        <?php if (!empty($importedData)): ?>
            <div class="import-section">
                <h2>2. Geocode Addresses</h2>
                <p>Click the button below to geocode all addresses. This may take several minutes.</p>
                <form method="POST" action="" onsubmit="return confirm('This will geocode all addresses. This may take several minutes. Continue?');">
                    <input type="hidden" name="action" value="geocode">
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Geocode All Addresses</button>
                    </div>
                </form>
            </div>
            
            <div class="import-section">
                <h2>3. Review and Edit</h2>
                <p>Review the geocoded addresses. You can manually edit coordinates for addresses that failed geocoding.</p>
                
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Address Name</th>
                            <th>Report Date</th>
                            <th>Status</th>
                            <th>Coordinates</th>
                            <th>Geocode Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($importedData as $row): ?>
                            <tr>
                                <td><?= h($row['address_name']) ?></td>
                                <td><?= h($row['report_date']) ?></td>
                                <td>
                                    <?php
                                    $statusText = '';
                                    $statusColor = '';
                                    if ($row['has_water'] == 1) {
                                        $statusText = 'Yes';
                                        $statusColor = '#27ae60';
                                    } elseif ($row['has_water'] == 0) {
                                        $statusText = 'No';
                                        $statusColor = '#e74c3c';
                                    } elseif ($row['has_water'] == 2) {
                                        $statusText = 'Intermittent';
                                        $statusColor = '#f39c12';
                                    }
                                    ?>
                                    <span style="color: <?= $statusColor ?>; font-weight: 600;"><?= h($statusText) ?></span>
                                </td>
                                <td>
                                    <?php if ($row['latitude'] && $row['longitude']): ?>
                                        <?= number_format($row['latitude'], 6) ?>, <?= number_format($row['longitude'], 6) ?>
                                    <?php else: ?>
                                        <em>Not geocoded</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $status = $row['geocode_status'] ?? 'pending';
                                    $statusClass = '';
                                    if ($status === 'success' || $status === 'duplicate' || $status === 'manual') {
                                        $statusClass = 'status-success';
                                    } elseif ($status === 'failed') {
                                        $statusClass = 'status-error';
                                    } else {
                                        $statusClass = 'status-pending';
                                    }
                                    ?>
                                    <span class="status-badge <?= $statusClass ?>"><?= ucfirst($status) ?></span>
                                    <?php if ($row['geocode_error']): ?>
                                        <br><small style="color: #dc3545;"><?= h($row['geocode_error']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($status === 'failed' || $status === 'pending'): ?>
                                        <button type="button" class="btn btn-sm btn-secondary" onclick="editCoords(<?= $row['temp_id'] ?>, '<?= h($row['address_name']) ?>', <?= $row['latitude'] ?: 'null' ?>, <?= $row['longitude'] ?: 'null' ?>)">Edit</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="import-section">
                <h2>4. Import to Water Availability</h2>
                <p>Once all addresses are geocoded, click the button below to import them into the water availability table.</p>
                <form method="POST" action="" onsubmit="return confirm('This will import all geocoded records to the water availability table. Records without coordinates will be skipped. Continue?');">
                    <input type="hidden" name="action" value="import_to_water">
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="clear_temp" value="1">
                            Clear temporary table after import
                        </label>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Import to Water Availability</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Coordinates Modal -->
<div id="editCoordsModal" style="display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
    <div style="background-color: var(--white); margin: 5% auto; padding: 2rem; border-radius: 8px; max-width: 500px;">
        <h2>Edit Coordinates</h2>
        <form method="POST" action="" id="editCoordsForm">
            <input type="hidden" name="action" value="update_coords">
            <input type="hidden" name="temp_id" id="edit_temp_id">
            <input type="hidden" name="address_name" id="edit_address_name">
            
            <div class="form-group">
                <label for="edit_latitude">Latitude:</label>
                <input type="number" step="any" id="edit_latitude" name="latitude" required>
            </div>
            
            <div class="form-group">
                <label for="edit_longitude">Longitude:</label>
                <input type="number" step="any" id="edit_longitude" name="longitude" required>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Update</button>
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function editCoords(tempId, addressName, lat, lon) {
    document.getElementById('edit_temp_id').value = tempId;
    document.getElementById('edit_address_name').value = addressName;
    document.getElementById('edit_latitude').value = lat || '';
    document.getElementById('edit_longitude').value = lon || '';
    document.getElementById('editCoordsModal').style.display = 'block';
}

function closeEditModal() {
    document.getElementById('editCoordsModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('editCoordsModal');
    if (event.target === modal) {
        closeEditModal();
    }
}
</script>

<style>
.import-section {
    background-color: var(--white);
    padding: 2rem;
    border-radius: 8px;
    box-shadow: var(--shadow);
    margin-bottom: 2rem;
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 4px;
    font-size: 0.875rem;
    font-weight: 600;
}

.status-success {
    background-color: #28a745;
    color: white;
}

.status-error {
    background-color: #dc3545;
    color: white;
}

.status-pending {
    background-color: #ffc107;
    color: #212529;
}
</style>

<?php 
$hideAdverts = true;
include __DIR__ . '/../includes/footer.php'; 
?>

