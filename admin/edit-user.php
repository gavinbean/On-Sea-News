<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/geocoding.php';
// Allow both ADMIN and USER_ADMIN to edit users
requireAnyRole(['ADMIN', 'USER_ADMIN']);

$db = getDB();
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$error = '';

if ($userId <= 0) {
    redirect('/admin/users.php');
    exit;
}

// Get user
$stmt = $db->prepare("SELECT * FROM " . TABLE_PREFIX . "users WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    redirect('/admin/users.php');
    exit;
}

// Get water availability records for this user
$waterStmt = $db->prepare("
    SELECT water_id, report_date, has_water, notes, latitude, longitude, reported_at
    FROM " . TABLE_PREFIX . "water_availability
    WHERE user_id = ?
    ORDER BY report_date DESC, reported_at DESC
");
$waterStmt->execute([$userId]);
$waterRecords = $waterStmt->fetchAll();

// Get water deliveries for this user
$deliveriesStmt = $db->prepare("
    SELECT wd.*, 
           COALESCE(wdc.company_name, wd.company_name_other) as company_name,
           u.username as logged_by_username,
           u.name as logged_by_name,
           u.surname as logged_by_surname
    FROM " . TABLE_PREFIX . "water_deliveries wd
    LEFT JOIN " . TABLE_PREFIX . "water_delivery_companies wdc ON wd.company_id = wdc.company_id
    LEFT JOIN " . TABLE_PREFIX . "users u ON wd.logged_by_user_id = u.user_id
    WHERE wd.user_id = ?
    ORDER BY wd.date_delivered DESC, wd.created_at DESC
");
$deliveriesStmt->execute([$userId]);
$waterDeliveries = $deliveriesStmt->fetchAll();

// Calculate totals for deliveries
$totalLitres = 0;
$totalPrice = 0;
foreach ($waterDeliveries as $delivery) {
    $totalLitres += (float)$delivery['litres_delivered'];
    $totalPrice += (float)$delivery['price'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $username = trim($_POST['username'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $surname = trim($_POST['surname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $emailVerified = isset($_POST['email_verified']) ? 1 : 0;
    
    // Address fields
    $streetNumber = trim($_POST['street_number'] ?? '');
    $streetName = trim($_POST['street_name'] ?? '');
    $suburb = trim($_POST['suburb'] ?? '');
    $town = trim($_POST['town'] ?? '');
    
    // Manual coordinates (if provided, they override geocoding)
    $manualLatitude = trim($_POST['latitude'] ?? '');
    $manualLongitude = trim($_POST['longitude'] ?? '');
    $updateWaterAvailability = isset($_POST['update_water_availability']) && $_POST['update_water_availability'] === '1';
    
    // Validation
    if (empty($username)) {
        $error = 'Username is required.';
    } elseif (empty($name)) {
        $error = 'Name is required.';
    } elseif (empty($email)) {
        $error = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (!empty($manualLatitude) && (!is_numeric($manualLatitude) || $manualLatitude < -90 || $manualLatitude > 90)) {
        $error = 'Invalid latitude. Must be a number between -90 and 90.';
    } elseif (!empty($manualLongitude) && (!is_numeric($manualLongitude) || $manualLongitude < -180 || $manualLongitude > 180)) {
        $error = 'Invalid longitude. Must be a number between -180 and 180.';
    } else {
        // Check if username is already taken by another user
        $stmt = $db->prepare("SELECT user_id FROM " . TABLE_PREFIX . "users WHERE username = ? AND user_id != ?");
        $stmt->execute([$username, $userId]);
        if ($stmt->fetch()) {
            $error = 'Username is already taken.';
        } else {
            // Determine coordinates: manual override first, then geocoding, then keep existing
            $latitude = null;
            $longitude = null;
            $oldLatitude = $user['latitude'];
            $oldLongitude = $user['longitude'];
            
            // Use manual coordinates if provided
            if (!empty($manualLatitude) && !empty($manualLongitude)) {
                $latitude = (float)$manualLatitude;
                $longitude = (float)$manualLongitude;
            } elseif (!empty($streetName) && !empty($town)) {
                // Geocode address if street name and town are provided
                $geocodeResult = validateAndGeocodeAddress([
                    'street_number' => $streetNumber,
                    'street_name' => $streetName,
                    'suburb' => $suburb,
                    'town' => $town
                ]);
                
                if ($geocodeResult['success']) {
                    $latitude = $geocodeResult['latitude'] ?? null;
                    $longitude = $geocodeResult['longitude'] ?? null;
                }
            } else {
                // Keep existing coordinates if no new address or manual coordinates
                $latitude = $oldLatitude;
                $longitude = $oldLongitude;
            }
            
            // Check if coordinates changed
            $coordsChanged = false;
            if (empty($oldLatitude) || empty($oldLongitude)) {
                $coordsChanged = !empty($latitude) && !empty($longitude);
            } else {
                // Check if coordinates are significantly different (more than 0.001 degrees, roughly 100m)
                $latDiff = abs((float)$oldLatitude - (float)$latitude);
                $lonDiff = abs((float)$oldLongitude - (float)$longitude);
                if ($latDiff > 0.001 || $lonDiff > 0.001) {
                    $coordsChanged = true;
                }
            }
            
            // Start transaction
            $db->beginTransaction();
            try {
                // Update user
                $stmt = $db->prepare("
                    UPDATE " . TABLE_PREFIX . "users 
                    SET username = ?, name = ?, surname = ?, email = ?, telephone = ?, is_active = ?, email_verified = ?,
                        street_number = ?, street_name = ?, suburb = ?, town = ?, latitude = ?, longitude = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([
                    $username, $name, $surname, $email, $telephone, $isActive, $emailVerified,
                    $streetNumber ?: null, $streetName ?: null, $suburb ?: null, $town ?: null,
                    $latitude, $longitude, $userId
                ]);
                
                $waterRecordsUpdated = 0;
                // Update water availability records if requested and we have valid coordinates
                if ($updateWaterAvailability && !empty($latitude) && !empty($longitude)) {
                    // Get all water records for this user
                    $waterStmt = $db->prepare("
                        SELECT water_id, report_date, latitude, longitude
                        FROM " . TABLE_PREFIX . "water_availability
                        WHERE user_id = ?
                    ");
                    $waterStmt->execute([$userId]);
                    $waterRecords = $waterStmt->fetchAll();
                    
                    foreach ($waterRecords as $waterRecord) {
                        // When checkbox is checked, update ALL records to current user geolocation
                        // Check if another record exists at this location/date (to avoid duplicates)
                        $duplicateStmt = $db->prepare("
                            SELECT water_id 
                            FROM " . TABLE_PREFIX . "water_availability 
                            WHERE water_id != ? 
                            AND report_date = ?
                            AND ABS(latitude - ?) < 0.000001
                            AND ABS(longitude - ?) < 0.000001
                            AND latitude IS NOT NULL
                            AND longitude IS NOT NULL
                            LIMIT 1
                        ");
                        $duplicateStmt->execute([$waterRecord['water_id'], $waterRecord['report_date'], $latitude, $longitude]);
                        $duplicate = $duplicateStmt->fetch();
                        
                        if ($duplicate) {
                            // Merge with existing record at this location/date
                            $mergeStmt = $db->prepare("
                                UPDATE " . TABLE_PREFIX . "water_availability 
                                SET user_id = ?, reported_at = NOW()
                                WHERE water_id = ?
                            ");
                            $mergeStmt->execute([$userId, $duplicate['water_id']]);
                            
                            // Delete the duplicate record
                            $deleteStmt = $db->prepare("DELETE FROM " . TABLE_PREFIX . "water_availability WHERE water_id = ?");
                            $deleteStmt->execute([$waterRecord['water_id']]);
                            $waterRecordsUpdated++;
                        } else {
                            // Update the record with current user coordinates
                            $updateWaterStmt = $db->prepare("
                                UPDATE " . TABLE_PREFIX . "water_availability 
                                SET latitude = ?, longitude = ?, reported_at = NOW()
                                WHERE water_id = ?
                            ");
                            $updateWaterStmt->execute([$latitude, $longitude, $waterRecord['water_id']]);
                            $waterRecordsUpdated++;
                        }
                    }
                }
                
                $db->commit();
                $message = 'User updated successfully.';
                if ($updateWaterAvailability) {
                    if ($waterRecordsUpdated > 0) {
                        $message .= " Updated {$waterRecordsUpdated} water availability record(s) with current geolocation.";
                    } else {
                        $message .= " No water availability records found to update.";
                    }
                }
                
                // Reload user
                $stmt = $db->prepare("SELECT * FROM " . TABLE_PREFIX . "users WHERE user_id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Error updating user: ' . $e->getMessage();
                error_log("Edit user error: " . $e->getMessage());
            }
        }
    }
}

$pageTitle = 'Edit User';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="content-area">
        <h1>Edit User</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= h($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        
        <!-- Tabs Navigation -->
        <div class="tabs-container" style="margin-bottom: 2rem;">
            <div class="tabs-nav" style="display: flex; gap: 0.5rem; border-bottom: 2px solid var(--border-color);">
                <button class="tab-button active" onclick="switchTab('user-details', this)" style="padding: 0.75rem 1.5rem; background: none; border: none; border-bottom: 3px solid var(--primary-color); cursor: pointer; font-size: 1rem; color: var(--primary-color); font-weight: 600;">
                    User Details
                </button>
                <button class="tab-button" onclick="switchTab('water-records', this)" style="padding: 0.75rem 1.5rem; background: none; border: none; border-bottom: 3px solid transparent; cursor: pointer; font-size: 1rem; color: #666; font-weight: 600;">
                    Water Availability Records (<?= count($waterRecords) ?>)
                </button>
                <button class="tab-button" onclick="switchTab('water-deliveries', this)" style="padding: 0.75rem 1.5rem; background: none; border: none; border-bottom: 3px solid transparent; cursor: pointer; font-size: 1rem; color: #666; font-weight: 600;">
                    Water Deliveries (<?= count($waterDeliveries) ?>)
                </button>
            </div>
        </div>
        
        <!-- User Details Tab -->
        <div id="user-details-tab" class="tab-content active">
        <div class="auth-container">
            <form method="POST" action="">
                <input type="hidden" name="action" value="update">
                
                <div class="form-group">
                    <label for="username">Username: <span class="required">*</span></label>
                    <input type="text" id="username" name="username" value="<?= h($user['username']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="name">Name: <span class="required">*</span></label>
                    <input type="text" id="name" name="name" value="<?= h($user['name']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="surname">Surname:</label>
                    <input type="text" id="surname" name="surname" value="<?= h($user['surname']) ?>">
                </div>
                
                <div class="form-group">
                    <label for="email">Email: <span class="required">*</span></label>
                    <input type="email" id="email" name="email" value="<?= h($user['email']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="telephone">Telephone:</label>
                    <input type="tel" id="telephone" name="telephone" value="<?= h($user['telephone'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" value="1" <?= $user['is_active'] ? 'checked' : '' ?>>
                        Active
                    </label>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="email_verified" value="1" <?= $user['email_verified'] ? 'checked' : '' ?>>
                        Email Verified
                    </label>
                </div>
                
                <hr style="margin: 2rem 0; border: none; border-top: 1px solid #ddd;">
                
                <h2 style="margin-bottom: 1.5rem;">Address Information</h2>
                
                <div class="form-group" style="position: relative;">
                    <label>Address Search:</label>
                    <input type="text" id="address-search" placeholder="Start typing your address..." autocomplete="off">
                    <div id="address-autocomplete" class="address-autocomplete"></div>
                    <small>Start typing your address and select from the suggestions</small>
                </div>
                
                <div class="form-group">
                    <label for="street_number">Street Number:</label>
                    <input type="text" id="street_number" name="street_number" value="<?= h($user['street_number'] ?? '') ?>" placeholder="e.g., 16" autocomplete="off">
                </div>
                
                <div class="form-group">
                    <label for="street_name">Street Name:</label>
                    <input type="text" id="street_name" name="street_name" value="<?= h($user['street_name'] ?? '') ?>" placeholder="e.g., Northwood Road" autocomplete="off">
                </div>
                
                <div class="form-group">
                    <label for="suburb">Suburb:</label>
                    <input type="text" id="suburb" name="suburb" value="<?= h($user['suburb'] ?? '') ?>" placeholder="e.g., Bushman's River Mouth" autocomplete="off">
                </div>
                
                <div class="form-group">
                    <label for="town">Town:</label>
                    <input type="text" id="town" name="town" value="<?= h($user['town'] ?? '') ?>" placeholder="e.g., Kenton-on-Sea" autocomplete="off">
                </div>
                
                <div class="form-group" style="background-color: #e7f3ff; padding: 0.75rem; border-radius: 4px; margin-top: 0.5rem; border-left: 3px solid #2196F3;">
                    <p style="margin: 0; font-size: 0.85rem; color: #1976D2;">
                        <small><strong>Note:</strong> Street Name and Town are required for automatic geocoding. If both are provided, coordinates will be automatically updated when you save.</small>
                    </p>
                </div>
                
                <div class="form-group">
                    <label for="latitude">Latitude:</label>
                    <input type="number" id="latitude" name="latitude" 
                           value="<?= !empty($user['latitude']) ? number_format($user['latitude'], 6) : '' ?>" 
                           step="0.000001" min="-90" max="90" 
                           placeholder="e.g., -33.723456" autocomplete="off">
                    <small>Leave empty to auto-geocode from address, or enter manually (between -90 and 90)</small>
                </div>
                
                <div class="form-group">
                    <label for="longitude">Longitude:</label>
                    <input type="number" id="longitude" name="longitude" 
                           value="<?= !empty($user['longitude']) ? number_format($user['longitude'], 6) : '' ?>" 
                           step="0.000001" min="-180" max="180" 
                           placeholder="e.g., 26.654321" autocomplete="off">
                    <small>Leave empty to auto-geocode from address, or enter manually (between -180 and 180)</small>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="update_water_availability" value="1">
                        Update Water Availability Records
                    </label>
                    <small style="display: block; margin-top: 0.25rem; color: #666;">
                        If checked, all water availability records for this user will be updated with the new coordinates when coordinates change.
                    </small>
                </div>
                
                <div class="form-group" style="background-color: #e7f3ff; padding: 0.75rem; border-radius: 4px; margin-top: 0.5rem; border-left: 3px solid #2196F3;">
                    <p style="margin: 0; font-size: 0.85rem; color: #1976D2;">
                        <small><strong>Note:</strong> If you enter coordinates manually, they will override automatic geocoding. If you leave coordinates empty and provide Street Name and Town, coordinates will be automatically geocoded.</small>
                    </p>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Update User</button>
                    <a href="<?= baseUrl('/admin/users.php') ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
        </div>
        
        <!-- Water Availability Records Tab -->
        <div id="water-records-tab" class="tab-content" style="display: none;">
            <div class="content-area">
                <h2>Water Availability Records</h2>
                
                <?php if (empty($waterRecords)): ?>
                    <p>No water availability records found for this user.</p>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Report Date</th>
                                    <th>Status</th>
                                    <th>Location</th>
                                    <th>Notes</th>
                                    <th>Reported At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($waterRecords as $record): ?>
                                    <tr>
                                        <td><?= $record['water_id'] ?></td>
                                        <td><?= formatDate($record['report_date'], 'Y-m-d') ?></td>
                                        <td>
                                            <?php
                                            $statusText = '';
                                            $statusColor = '';
                                            if ($record['has_water'] == 1) {
                                                $statusText = 'Yes';
                                                $statusColor = 'green';
                                            } elseif ($record['has_water'] == 0) {
                                                $statusText = 'No';
                                                $statusColor = 'red';
                                            } else {
                                                $statusText = 'Intermittent';
                                                $statusColor = 'orange';
                                            }
                                            ?>
                                            <span style="color: <?= $statusColor ?>; font-weight: 600;"><?= h($statusText) ?></span>
                                        </td>
                                        <td>
                                            <?php if (!empty($record['latitude']) && !empty($record['longitude'])): ?>
                                                <?= number_format($record['latitude'], 6) ?>, <?= number_format($record['longitude'], 6) ?>
                                            <?php else: ?>
                                                <span style="color: #999;">No coordinates</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= !empty($record['notes']) ? h(substr($record['notes'], 0, 50)) . (strlen($record['notes']) > 50 ? '...' : '') : '-' ?></td>
                                        <td><?= formatDate($record['reported_at'], 'Y-m-d H:i:s') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div style="margin-top: 1rem; padding: 1rem; background-color: #f5f5f5; border-radius: 4px;">
                        <p style="margin: 0; font-size: 0.9rem; color: #666;">
                            <strong>Total Records:</strong> <?= count($waterRecords) ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Water Deliveries Tab -->
        <div id="water-deliveries-tab" class="tab-content" style="display: none;">
            <div class="content-area">
                <h2>Water Deliveries</h2>
                
                <?php if (empty($waterDeliveries)): ?>
                    <p>No water deliveries found for this user.</p>
                <?php else: ?>
                    <div style="margin-bottom: 1rem; padding: 1rem; background-color: #f5f5f5; border-radius: 4px;">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                            <div>
                                <p style="margin: 0; color: #666; font-size: 0.9rem;">Total Deliveries</p>
                                <p style="margin: 0; font-size: 1.5rem; font-weight: bold; color: #2c5f8d;"><?= count($waterDeliveries) ?></p>
                            </div>
                            <div>
                                <p style="margin: 0; color: #666; font-size: 0.9rem;">Total Litres</p>
                                <p style="margin: 0; font-size: 1.5rem; font-weight: bold; color: #2c5f8d;"><?= number_format($totalLitres, 2) ?> L</p>
                            </div>
                            <div>
                                <p style="margin: 0; color: #666; font-size: 0.9rem;">Total Price</p>
                                <p style="margin: 0; font-size: 1.5rem; font-weight: bold; color: #2c5f8d;">R <?= number_format($totalPrice, 2) ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date Ordered</th>
                                    <th>Date Delivered</th>
                                    <th>Company</th>
                                    <th>Vehicle Registration</th>
                                    <th>Litres</th>
                                    <th>Price</th>
                                    <th>Logged By</th>
                                    <th>Created At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($waterDeliveries as $delivery): ?>
                                    <tr>
                                        <td><?= formatDate($delivery['date_ordered']) ?></td>
                                        <td><?= formatDate($delivery['date_delivered']) ?></td>
                                        <td><?= h($delivery['company_name']) ?></td>
                                        <td><?= h($delivery['vehicle_registration']) ?></td>
                                        <td style="text-align: right;"><?= number_format($delivery['litres_delivered'], 2) ?> L</td>
                                        <td style="text-align: right;">R <?= number_format($delivery['price'], 2) ?></td>
                                        <td><?= h($delivery['logged_by_name'] . ' ' . $delivery['logged_by_surname']) ?></td>
                                        <td><?= formatDate($delivery['created_at'], 'Y-m-d H:i:s') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.tab-button {
    transition: all 0.2s ease;
}

.tab-button:hover {
    color: var(--primary-color) !important;
}

.tab-button.active {
    color: var(--primary-color) !important;
    border-bottom-color: var(--primary-color) !important;
}
</style>

<script>
function switchTab(tabName, buttonElement) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
        tab.style.display = 'none';
    });
    
    // Remove active class from all buttons
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('active');
        btn.style.borderBottomColor = 'transparent';
        btn.style.color = '#666';
    });
    
    // Show selected tab
    const selectedTab = document.getElementById(tabName + '-tab');
    if (selectedTab) {
        selectedTab.classList.add('active');
        selectedTab.style.display = 'block';
    }
    
    // Activate selected button
    if (buttonElement) {
        buttonElement.classList.add('active');
        buttonElement.style.borderBottomColor = 'var(--primary-color)';
        buttonElement.style.color = 'var(--primary-color)';
    }
}
</script>

<script>
// Address autocomplete functionality
(function() {
    const addressSearch = document.getElementById('address-search');
    const autocompleteDiv = document.getElementById('address-autocomplete');
    const streetNumberInput = document.getElementById('street_number');
    const streetNameInput = document.getElementById('street_name');
    const suburbInput = document.getElementById('suburb');
    const townInput = document.getElementById('town');
    const apiUrl = '<?= baseUrl('/api/address-autocomplete.php') ?>';
    let autocompleteTimeout = null;
    let selectedIndex = -1;
    let suggestions = [];

    if (!addressSearch || !autocompleteDiv) {
        return;
    }

    // Hide autocomplete when clicking outside (but allow time for clicks on items)
    let hideTimeout = null;
    let isInputFocused = false;
    
    addressSearch.addEventListener('focus', function() {
        isInputFocused = true;
        clearTimeout(hideTimeout);
        // If there's a query and suggestions, show the dropdown
        const query = this.value.trim();
        if (query.length >= 3 && suggestions.length > 0) {
            autocompleteDiv.style.display = 'block';
        }
    });
    
    addressSearch.addEventListener('blur', function() {
        isInputFocused = false;
        // Delay hiding to allow clicks on suggestions
        hideTimeout = setTimeout(function() {
            if (!autocompleteDiv.matches(':hover')) {
                autocompleteDiv.style.display = 'none';
            }
        }, 200);
    });
    
    document.addEventListener('click', function(e) {
        const isClickInside = addressSearch.contains(e.target) || autocompleteDiv.contains(e.target);
        if (!isClickInside && !isInputFocused) {
            hideTimeout = setTimeout(function() {
                autocompleteDiv.style.display = 'none';
            }, 150);
        } else {
            clearTimeout(hideTimeout);
        }
    });
    
    // Keep autocomplete visible when hovering over it
    autocompleteDiv.addEventListener('mouseenter', function() {
        clearTimeout(hideTimeout);
    });
    
    autocompleteDiv.addEventListener('mouseleave', function() {
        // Only hide if input is not focused
        if (!isInputFocused) {
            hideTimeout = setTimeout(function() {
                autocompleteDiv.style.display = 'none';
            }, 200);
        }
    });

    addressSearch.addEventListener('input', function() {
        const query = this.value.trim();
        
        // Clear any hide timeouts when user is typing
        clearTimeout(hideTimeout);
        clearTimeout(autocompleteTimeout);
        
        if (query.length < 3) {
            autocompleteDiv.style.display = 'none';
            suggestions = []; // Clear suggestions
            return;
        }

        // Debounce API calls
        autocompleteTimeout = setTimeout(function() {
            fetchAddressSuggestions(query);
        }, 300);
    });

    addressSearch.addEventListener('keydown', function(e) {
        if (autocompleteDiv.style.display === 'none' || suggestions.length === 0) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            selectedIndex = Math.min(selectedIndex + 1, suggestions.length - 1);
            updateSelection();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            selectedIndex = Math.max(selectedIndex - 1, -1);
            updateSelection();
        } else if (e.key === 'Enter' && selectedIndex >= 0) {
            e.preventDefault();
            selectSuggestion(suggestions[selectedIndex]);
        } else if (e.key === 'Escape') {
            autocompleteDiv.style.display = 'none';
        }
    });

    function fetchAddressSuggestions(query) {
        const url = apiUrl + '?q=' + encodeURIComponent(query);
        fetch(url)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    return response.text().then(text => {
                        throw new Error('Response is not JSON');
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.suggestions && data.suggestions.length > 0) {
                    suggestions = data.suggestions;
                    displaySuggestions(data.suggestions);
                } else {
                    autocompleteDiv.style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Error fetching address suggestions:', error);
                autocompleteDiv.style.display = 'none';
            });
    }

    function displaySuggestions(suggestions) {
        // Clear any hide timeouts when displaying suggestions
        clearTimeout(hideTimeout);
        
        if (suggestions.length === 0) {
            autocompleteDiv.style.display = 'none';
            return;
        }

        autocompleteDiv.innerHTML = '';
        suggestions.forEach((suggestion, index) => {
            const item = document.createElement('div');
            item.className = 'autocomplete-item';
            item.textContent = suggestion.display_name || 'Unknown address';
            item.addEventListener('mousedown', function(e) {
                e.preventDefault(); // Prevent input from losing focus
                selectSuggestion(suggestion);
            });
            
            item.addEventListener('mouseenter', function() {
                selectedIndex = index;
                updateSelection();
            });
            autocompleteDiv.appendChild(item);
        });
        
        autocompleteDiv.style.display = 'block';
        selectedIndex = -1;
    }

    function updateSelection() {
        const items = autocompleteDiv.querySelectorAll('.autocomplete-item');
        items.forEach((item, index) => {
            if (index === selectedIndex) {
                item.classList.add('selected');
            } else {
                item.classList.remove('selected');
            }
        });
    }

    function selectSuggestion(suggestion) {
        // Clear any hide timeouts
        clearTimeout(hideTimeout);
        
        // Extract street number from search query if present and not returned by API
        let extractedStreetNumber = '';
        const searchQuery = addressSearch.value.trim();
        if (searchQuery) {
            // Try to extract street number from query (e.g., "47 Main Street" -> "47")
            const numberMatch = searchQuery.match(/^(\d+)\s+/);
            if (numberMatch) {
                extractedStreetNumber = numberMatch[1];
            }
        }
        
        // Populate separate address fields
        // Use API street_number if available, otherwise use extracted from query
        if (streetNumberInput) {
            streetNumberInput.value = suggestion.street_number || extractedStreetNumber || '';
        }
        if (streetNameInput) streetNameInput.value = suggestion.street_name || '';
        if (suburbInput) suburbInput.value = suggestion.suburb || '';
        if (townInput) townInput.value = suggestion.town || '';
        
        // Clear the search field
        addressSearch.value = '';
        autocompleteDiv.style.display = 'none';
        selectedIndex = -1;
        suggestions = [];
        
        // Focus on street name if empty, otherwise town
        if (streetNameInput && !streetNameInput.value) {
            streetNameInput.focus();
        } else if (townInput && !townInput.value) {
            townInput.focus();
        }
    }
})();
</script>

<?php 
$hideAdverts = true;
include __DIR__ . '/../includes/footer.php'; 
?>



