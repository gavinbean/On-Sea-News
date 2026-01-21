<?php
require_once 'includes/functions.php';
requireLogin();

$db = getDB();
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'report') {
    require_once 'includes/water-questions.php';
    
    $reportDate = $_POST['report_date'] ?? date('Y-m-d');
    $hasWater = isset($_POST['has_water']) ? (int)$_POST['has_water'] : null;
    // Validate has_water value (0 = No, 1 = Yes, 2 = Intermittent)
    if ($hasWater !== null && !in_array($hasWater, [0, 1, 2])) {
        $hasWater = null;
    }
    $userId = getCurrentUserId();
    
    // Check if user has accepted water data terms
    // Find the terms question (question 7 - "I agree to the terms and conditions")
    $stmt = $db->prepare("
        SELECT q.question_id 
        FROM " . TABLE_PREFIX . "water_questions q
        WHERE q.question_text LIKE '%terms and conditions%' 
        AND q.page_tag = 'water_info'
        AND q.is_active = 1
        LIMIT 1
    ");
    $stmt->execute();
    $termsQuestion = $stmt->fetch();
    
    if ($termsQuestion) {
        $stmt = $db->prepare("
            SELECT response_value 
            FROM " . TABLE_PREFIX . "water_user_responses
            WHERE user_id = ? AND question_id = ?
        ");
        $stmt->execute([$userId, $termsQuestion['question_id']]);
        $termsResponse = $stmt->fetch();
        
        if (!$termsResponse || $termsResponse['response_value'] !== 'agreed') {
            $profileUrl = baseUrl('/profile.php#water-tab');
            $error = 'You must accept the terms and conditions for water data submission before you can report water availability. Please <a href="' . h($profileUrl) . '" style="color: var(--primary-color); text-decoration: underline; font-weight: 600;">complete the Water Information section in your profile</a> and accept the terms.';
        }
    }
    
    // Get user's location from profile
    $user = getCurrentUser();
    if (!$user || empty($user['latitude']) || empty($user['longitude'])) {
        $error = 'Please update your profile with a valid address that includes location information.';
    }
    
    // Validate that has_water is set
    if (empty($error) && $hasWater === null) {
        $error = 'Please select your water availability status.';
    }
    
    if (empty($error)) {
        $userLatitude = (float)$user['latitude'];
        $userLongitude = (float)$user['longitude'];
        
        // Round coordinates to 6 decimal places (about 10cm precision) to handle floating point precision issues
        $userLatitude = round($userLatitude, 6);
        $userLongitude = round($userLongitude, 6);
        
        // Start transaction to prevent race conditions
        $db->beginTransaction();
        
        try {
            // Use SELECT FOR UPDATE to lock the row and prevent race conditions
            // Also use a small tolerance for coordinate comparison (0.000001 degrees ≈ 10cm)
            $stmt = $db->prepare("
                SELECT water_id 
                FROM " . TABLE_PREFIX . "water_availability 
                WHERE report_date = ? 
                AND ABS(latitude - ?) < 0.000001
                AND ABS(longitude - ?) < 0.000001
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->execute([$reportDate, $userLatitude, $userLongitude]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Update existing report (anyone at this location can update it)
                // Update reported_at to track the latest update
                $stmt = $db->prepare("
                    UPDATE " . TABLE_PREFIX . "water_availability 
                    SET has_water = ?, 
                        user_id = ?,
                        reported_at = NOW(),
                        latitude = ?,
                        longitude = ?
                    WHERE water_id = ?
                ");
                $result = $stmt->execute([$hasWater, $userId, $userLatitude, $userLongitude, $existing['water_id']]);
                
                if (!$result) {
                    throw new Exception('Failed to update water availability record');
                }
                
                $message = 'Water availability report updated successfully.';
            } else {
                // Insert new report with coordinates
                $stmt = $db->prepare("
                    INSERT INTO " . TABLE_PREFIX . "water_availability 
                    (user_id, report_date, has_water, latitude, longitude, reported_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $result = $stmt->execute([$userId, $reportDate, $hasWater, $userLatitude, $userLongitude]);
                
                if (!$result) {
                    throw new Exception('Failed to insert water availability record');
                }
                
                $message = 'Water availability report submitted successfully.';
            }
            
            // Commit transaction
            $db->commit();
            
        } catch (Exception $e) {
            // Rollback on error
            $db->rollBack();
            $error = 'Error saving water availability report: ' . $e->getMessage();
            error_log("Water availability update error: " . $e->getMessage());
        }
    }
}

// Get selected date (default to today)
$selectedDate = $_GET['date'] ?? date('Y-m-d');

// Get all water reports for the selected date
// Use stored coordinates from water_availability table, not current user profile coordinates
$stmt = $db->prepare("
    SELECT w.*, u.name, u.surname, u.street_number, u.street_name, u.suburb, u.town
    FROM " . TABLE_PREFIX . "water_availability w
    JOIN " . TABLE_PREFIX . "users u ON w.user_id = u.user_id
    WHERE w.report_date = ?
    AND w.latitude IS NOT NULL
    AND w.longitude IS NOT NULL
    ORDER BY w.reported_at DESC
");
$stmt->execute([$selectedDate]);
$reports = $stmt->fetchAll();

// Get user for form display
$user = getCurrentUser();
$userId = getCurrentUserId();

// Get user's water reports for "My Reports" tab
$userReports = [];
if ($userId) {
    $stmt = $db->prepare("
        SELECT report_date, has_water, reported_at
        FROM " . TABLE_PREFIX . "water_availability
        WHERE user_id = ?
        ORDER BY report_date DESC, reported_at DESC
    ");
    $stmt->execute([$userId]);
    $userReports = $stmt->fetchAll();
}

$pageTitle = 'Water Availability';
include 'includes/header.php';
?>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
     integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
     crossorigin=""/>

<div class="container">
    <div class="content-area">
        <h1>Water Availability Tracking</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= h($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>
        
        <!-- Tabs -->
        <div class="water-tabs">
            <button class="tab-button active" onclick="switchTab('tracking')">Tracking</button>
            <button class="tab-button" onclick="switchTab('my-reports')">My Water Reports</button>
        </div>
        
        <!-- Tracking Tab -->
        <div id="tracking-tab" class="tab-content active">
        <div class="water-report-form">
            <h2>Report Your Water Status</h2>
            <form method="POST" action="" id="waterReportForm">
                <input type="hidden" name="action" value="report">
                
                <div class="form-group" style="display: flex; align-items: center; gap: 0.75rem;">
                    <label for="report_date" style="margin: 0; white-space: nowrap;">Date:</label>
                    <input type="date" id="report_date" name="report_date" value="<?= date('Y-m-d') ?>" required>
                </div>
                
                <div class="form-group">
                    <?php 
                    $addressParts = [];
                    if (!empty($user['street_number'])) $addressParts[] = $user['street_number'];
                    if (!empty($user['street_name'])) $addressParts[] = $user['street_name'];
                    if (!empty($user['suburb'])) $addressParts[] = $user['suburb'];
                    if (!empty($user['town'])) $addressParts[] = $user['town'];
                    $userAddress = implode(', ', $addressParts) ?: 'No address on file';
                    ?>
                    <p class="user-address" style="margin: 0;"><?= h($userAddress) ?></p>
                    <?php if (empty($user['latitude']) || empty($user['longitude'])): ?>
                        <p class="alert alert-error">Please update your profile with a valid address.</p>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="has_water">Do you have water?</label>
                    <div class="radio-group">
                        <label class="radio-label">
                            <input type="radio" id="has_water_yes" name="has_water" value="1">
                            <span class="water-option-dot water-dot-green"></span>
                            Yes, I have water
                        </label>
                        <label class="radio-label">
                            <input type="radio" id="has_water_intermittent" name="has_water" value="2">
                            <span class="water-option-dot water-dot-orange"></span>
                            Intermittent, I have water at irregular intervals
                        </label>
                        <label class="radio-label">
                            <input type="radio" id="has_water_no" name="has_water" value="0">
                            <span class="water-option-dot water-dot-red"></span>
                            No, I do not have water
                        </label>
                    </div>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Submit Report</button>
                </div>
            </form>
            
            <?php 
            // Check if user has accepted terms
            require_once 'includes/water-questions.php';
            $stmt = $db->prepare("
                SELECT q.question_id 
                FROM " . TABLE_PREFIX . "water_questions q
                WHERE q.question_text LIKE '%terms and conditions%' 
                AND q.page_tag = 'water_info'
                AND q.is_active = 1
                LIMIT 1
            ");
            $stmt->execute();
            $termsQuestion = $stmt->fetch();
            
            if ($termsQuestion) {
                $stmt = $db->prepare("
                    SELECT response_value 
                    FROM " . TABLE_PREFIX . "water_user_responses
                    WHERE user_id = ? AND question_id = ?
                ");
                $stmt->execute([$userId, $termsQuestion['question_id']]);
                $termsResponse = $stmt->fetch();
                
                if (!$termsResponse || $termsResponse['response_value'] !== 'agreed'): ?>
                    <div class="alert alert-warning">
                        <strong>Notice:</strong> You must complete the Water Information section in your <a href="<?= baseUrl('/profile.php#water-tab') ?>" style="color: var(--primary-color); text-decoration: underline; font-weight: 600;">profile</a> and accept the terms and conditions before you can submit water availability reports.
                    </div>
                <?php endif;
            }
            ?>
        </div>
        
        <div class="water-controls">
            <label for="date-select">View reports for date:</label>
            <input type="date" id="date-select" value="<?= h($selectedDate) ?>" onchange="loadDate()">
            
            <label for="animate-days">Animate last:</label>
            <select id="animate-days" class="animate-days-select">
                <?php for ($i = 1; $i <= 31; $i++): ?>
                    <option value="<?= $i ?>" <?= $i == 7 ? 'selected' : '' ?>><?= $i ?></option>
                <?php endfor; ?>
            </select>
            <span>days</span>
            <button type="button" class="btn btn-secondary" id="animateBtn" onclick="openAnimationModal()">Go</button>
            <div id="animation-status" style="margin-left: 1rem; display: none;">
                <strong>Showing:</strong> <span id="current-animation-date"></span>
            </div>
        </div>
        
        <div id="map" style="height: 600px; width: 100%; margin-top: 2rem;"></div>
        </div>
        </div>
        
        <!-- My Reports Tab -->
        <div id="my-reports-tab" class="tab-content">
            <h2>My Water Reports</h2>
            <?php if (empty($userReports)): ?>
                <p>You haven't submitted any water availability reports yet.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Availability</th>
                            <th>Reported At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($userReports as $report): ?>
                            <tr>
                                <td><?= formatDate($report['report_date'], 'Y-m-d') ?></td>
                                <td>
                                    <?php
                                    $statusText = '';
                                    $statusColor = '';
                                    if ($report['has_water'] == 1) {
                                        $statusText = 'Yes';
                                        $statusColor = '#27ae60';
                                    } elseif ($report['has_water'] == 0) {
                                        $statusText = 'No';
                                        $statusColor = '#e74c3c';
                                    } elseif ($report['has_water'] == 2) {
                                        $statusText = 'Intermittent';
                                        $statusColor = '#f39c12';
                                    }
                                    ?>
                                    <span style="color: <?= $statusColor ?>; font-weight: 600;"><?= h($statusText) ?></span>
                                </td>
                                <td><?= formatDate($report['reported_at'], 'Y-m-d H:i:s') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
        
<!-- Animation Modal - Must be outside container for proper positioning -->
<div id="animation-modal" class="animation-modal">
    <div class="animation-modal-content">
        <div class="animation-modal-header">
            <h2>Water Availability</h2>
            <button type="button" class="modal-close-btn" onclick="closeAnimationModal()" aria-label="Close">&times;</button>
        </div>
        <div class="animation-modal-body">
            <div class="water-controls-modal">
                <div class="control-group">
                    <label for="date-select-modal">View reports for date:</label>
                    <input type="date" id="date-select-modal" name="date-select-modal" value="<?= h($selectedDate) ?>">
        </div>
        
                <div class="control-group">
                    <label for="animate-days-modal">Animate last:</label>
                    <select id="animate-days-modal" name="animate-days-modal" class="animate-days-select">
                        <?php for ($i = 1; $i <= 31; $i++): ?>
                            <option value="<?= $i ?>" <?= $i == 7 ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                    <span>days</span>
                    <button type="button" class="btn btn-secondary" id="animateBtn-modal" onclick="animateWaterDataModal()">Go</button>
                        </div>
                
                <div id="animation-status-modal" style="margin-left: 1rem; display: none;">
                    <strong>Showing:</strong> <span id="current-animation-date-modal"></span>
                </div>
            </div>
            
            <div id="map-modal" style="flex: 1; min-height: 0; width: 100%; margin-top: 1rem;"></div>
        </div>
    </div>
</div>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
     integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
     crossorigin=""/>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
     integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
     crossorigin=""></script>

<script>
let map;
let mapModal; // Map instance in modal
let markers = [];
let markersModal = []; // Markers in modal
let animationInterval = null;
let currentAnimationDate = null;
let animationDays = 7;
let mapBoundsSet = false; // Track if bounds have been set
let allCoordinates = []; // Store all coordinates for bounds calculation
const isAdmin = <?= hasRole('ADMIN') ? 'true' : 'false' ?>; // Check if current user is admin
const userLocation = <?= !empty($user['latitude']) && !empty($user['longitude']) ? json_encode(['lat' => (float)$user['latitude'], 'lng' => (float)$user['longitude']]) : 'null' ?>;
let userLocationMarker = null;
let userLocationMarkerModal = null;
let gpsLocation = null; // Store GPS location from device

function initMap() {
    // Initialize map - center on user location if available, otherwise use default
    let initialCenter = [-33.7, 26.7];
    let initialZoom = 13;
    
    if (userLocation) {
        initialCenter = [userLocation.lat, userLocation.lng];
        // Calculate zoom level for 2km radius
        // At the equator, 1 degree ≈ 111 km, so 2km ≈ 0.018 degrees
        // We'll use a bounding box approach to ensure 2km radius is visible
        const radiusKm = 2;
        const latRadius = radiusKm / 111; // Approximate degrees for latitude
        const lngRadius = radiusKm / (111 * Math.cos(userLocation.lat * Math.PI / 180)); // Adjust for longitude
        
        // Create a bounding box for 2km radius
        const bounds = L.latLngBounds(
            [userLocation.lat - latRadius, userLocation.lng - lngRadius],
            [userLocation.lat + latRadius, userLocation.lng + lngRadius]
        );
        
        map = L.map('map').fitBounds(bounds);
    } else {
        map = L.map('map').setView(initialCenter, initialZoom);
    }
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);
    
    // Add user location marker if available
    if (userLocation) {
        // Use Leaflet's default pin icon for user location
        const userLocationIcon = L.icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-blue.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34],
            shadowSize: [41, 41]
        });
        
        userLocationMarker = L.marker([userLocation.lat, userLocation.lng], {icon: userLocationIcon}).addTo(map);
        userLocationMarker.bindPopup('Your Location');
    }
    
    // Load initial data and set bounds
    loadWaterData('<?= $selectedDate ?>', true);
}

function loadDate() {
    const dateInput = document.getElementById('date-select');
    if (!dateInput) {
        console.error('Date input not found');
        return;
    }
    const date = dateInput.value;
    console.log('Loading date on main page:', date);
    if (!date) {
        console.error('No date selected');
        return;
    }
    if (!map) {
        console.error('Map not initialized');
        return;
    }
    // Update the URL without reloading the page
    const newUrl = '<?= baseUrl('/water-availability.php') ?>?date=' + date;
    window.history.pushState({date: date}, '', newUrl);
    // Load the data for the selected date
    loadWaterData(date, true);
}

// Create custom colored circle markers for water availability
function createWaterIcon(color) {
    return L.divIcon({
        className: 'water-marker',
        html: `<div style="background-color: ${color}; width: 12px; height: 12px; border-radius: 50%; border: 2px solid white; box-shadow: 0 1px 2px rgba(0,0,0,0.3);"></div>`,
        iconSize: [12, 12],
        iconAnchor: [6, 6]
    });
}

const greenIcon = createWaterIcon('#28a745'); // Green for has water
const orangeIcon = createWaterIcon('#f39c12'); // Orange for intermittent
const redIcon = createWaterIcon('#dc3545');   // Red for no water

function loadWaterData(date, setBounds = false) {
    // Add cache-busting parameter to prevent iOS caching issues
    const cacheBuster = '&_t=' + Date.now();
    fetch('<?= baseUrl('/api/water-data.php') ?>?date=' + date + cacheBuster, {
        cache: 'no-store',
        headers: {
            'Cache-Control': 'no-cache, no-store, must-revalidate',
            'Pragma': 'no-cache'
        }
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                clearMarkers();
                
                // If setting bounds for a single date, clear allCoordinates first
                if (setBounds) {
                    allCoordinates = [];
                }
                
                data.reports.forEach(report => {
                    if (report.latitude && report.longitude) {
                        // Store coordinates for bounds calculation only if setting bounds
                        if (setBounds) {
                            allCoordinates.push([report.latitude, report.longitude]);
                        }
                        
                        // Use appropriate icon based on water status
                        let icon, status;
                        if (report.has_water == 1) {
                            icon = greenIcon;
                            status = 'Has Water';
                        } else if (report.has_water == 2) {
                            icon = orangeIcon;
                            status = 'Intermittent Water';
                        } else {
                            icon = redIcon;
                            status = 'No Water';
                        }
                        
                        // Build popup content - show user name for admins
                        let popupContent = status;
                        if (isAdmin) {
                            if (report.user_id && report.name && report.name !== 'Imported Data') {
                                const fullName = (report.name + ' ' + (report.surname || '')).trim();
                                const editUrl = '<?= baseUrl('/admin/edit-user.php?id=') ?>' + report.user_id;
                                popupContent = `<a href="${editUrl}" target="_blank" style="text-decoration: none; color: #2c5f8d; font-weight: 600;">${fullName}</a><br>${status}`;
                            } else {
                                // For imported data or records without user
                                popupContent = status;
                            }
                        }
                        
                        const marker = L.marker([report.latitude, report.longitude], {icon: icon}).addTo(map);
                        marker.bindPopup(popupContent);
                        markers.push(marker);
                    }
                });
                
                // Only set bounds if requested and we have markers
                // But if user location is available, keep centered on user with 2km radius
                if (setBounds && markers.length > 0 && !userLocation) {
                    const group = new L.featureGroup(markers);
                    map.fitBounds(group.getBounds().pad(0.1));
                    mapBoundsSet = true;
                } else if (userLocation && map) {
                    // Keep map centered on user location with 2km radius
                    const radiusKm = 2;
                    const latRadius = radiusKm / 111;
                    const lngRadius = radiusKm / (111 * Math.cos(userLocation.lat * Math.PI / 180));
                    
                    const bounds = L.latLngBounds(
                        [userLocation.lat - latRadius, userLocation.lng - lngRadius],
                        [userLocation.lat + latRadius, userLocation.lng + lngRadius]
                    );
                    
                    map.fitBounds(bounds);
                }
            }
        })
        .catch(error => {
            console.error('Error loading water data:', error);
        });
}

function clearMarkers() {
    markers.forEach(marker => map.removeLayer(marker));
    markers = [];
}

// Load all data points for a date range to calculate bounds
function loadAllDataPointsForBounds(days) {
    allCoordinates = [];
    const endDate = new Date();
    const startDate = new Date();
    startDate.setDate(startDate.getDate() - days);
    
    const datePromises = [];
    const currentDate = new Date(startDate);
    
    // Load all dates in parallel
    while (currentDate <= endDate) {
        const dateStr = currentDate.toISOString().split('T')[0];
        datePromises.push(
            fetch('<?= baseUrl('/api/water-data.php') ?>?date=' + dateStr + '&_t=' + Date.now(), {
                cache: 'no-store',
                headers: {
                    'Cache-Control': 'no-cache, no-store, must-revalidate',
                    'Pragma': 'no-cache'
                }
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.reports) {
                        data.reports.forEach(report => {
                            if (report.latitude && report.longitude) {
                                allCoordinates.push([report.latitude, report.longitude]);
                            }
                        });
                    }
                })
                .catch(error => {
                    console.error('Error loading data for date ' + dateStr + ':', error);
                })
        );
        currentDate.setDate(currentDate.getDate() + 1);
    }
    
    return Promise.all(datePromises).then(() => {
        // Set map bounds to fit all coordinates
        if (allCoordinates.length > 0) {
            const bounds = L.latLngBounds(allCoordinates);
            map.fitBounds(bounds.pad(0.1));
            mapBoundsSet = true;
        }
    });
}

function openAnimationModal() {
    console.log('openAnimationModal called');
    let modal = document.getElementById('animation-modal');
    
    if (!modal) {
        console.error('Animation modal not found');
            return;
    }
    
    console.log('Modal found, opening...');
    
    // Move modal to body if it's not already there (ensures it's not constrained by parent containers)
    if (modal.parentElement !== document.body) {
        console.log('Moving modal to body');
        document.body.appendChild(modal);
    }
    
    // Copy current settings from main page to modal
    const selectedDate = document.getElementById('date-select').value;
    const animateDays = document.getElementById('animate-days').value;
    
    const dateSelectModal = document.getElementById('date-select-modal');
    const animateDaysModal = document.getElementById('animate-days-modal');
    
    if (dateSelectModal) dateSelectModal.value = selectedDate;
    if (animateDaysModal) animateDaysModal.value = animateDays;
    
    // Remove any existing inline styles and set fresh
    modal.removeAttribute('style');
    
    // Move modal to body to ensure it's not constrained
    if (modal.parentElement !== document.body) {
        console.log('Moving modal to body, current parent:', modal.parentElement);
        document.body.appendChild(modal);
        console.log('Modal moved to body');
    }
    
    // Remove the modal from DOM and re-add it to force browser to recognize it
    const modalClone = modal.cloneNode(true);
    modal.remove();
    modal = modalClone;
    modal.id = 'animation-modal';
    document.body.appendChild(modal);
    console.log('Modal re-added to DOM');
    
    // Show modal with explicit positioning to ensure it appears as overlay
    // Use cssText to set all styles at once with !important
    modal.style.cssText = 'display: block !important; position: fixed !important; z-index: 99999 !important; left: 0px !important; top: 0px !important; width: 100vw !important; height: 100vh !important; background-color: rgba(0, 0, 0, 0.75) !important; margin: 0 !important; padding: 0 !important; overflow: auto !important; visibility: visible !important; opacity: 1 !important;';
    document.body.style.overflow = 'hidden'; // Prevent background scrolling
    
    // Re-attach event listeners after cloning (inline onclick handlers don't work after clone)
    const animateBtnModal = modal.querySelector('#animateBtn-modal');
    if (animateBtnModal) {
        animateBtnModal.removeAttribute('onclick');
        animateBtnModal.addEventListener('click', animateWaterDataModal);
    }
    
    const closeBtn = modal.querySelector('.modal-close-btn');
    if (closeBtn) {
        closeBtn.removeAttribute('onclick');
        closeBtn.addEventListener('click', closeAnimationModal);
    }
    
    // Add event listener to date input to load data when changed
    const dateInputModal = modal.querySelector('#date-select-modal');
    if (dateInputModal) {
        dateInputModal.addEventListener('change', function() {
            console.log('Date changed in modal to:', dateInputModal.value);
            loadDateModal();
        });
    }
    
    console.log('Modal styles applied, checking visibility...');
    setTimeout(() => {
        const rect = modal.getBoundingClientRect();
        console.log('Modal rect after 100ms:', rect);
        console.log('Modal visible?', rect.width > 0 && rect.height > 0);
        if (rect.width === 0 || rect.height === 0) {
            console.error('Modal has zero dimensions!');
        }
    }, 100);
    
    console.log('Modal styles set, display:', modal.style.display, 'computed display:', window.getComputedStyle(modal).display);
    console.log('Modal visibility:', window.getComputedStyle(modal).visibility);
    console.log('Modal z-index:', window.getComputedStyle(modal).zIndex);
    console.log('Modal opacity:', window.getComputedStyle(modal).opacity);
    console.log('Modal position:', window.getComputedStyle(modal).position);
    console.log('Modal left:', window.getComputedStyle(modal).left);
    console.log('Modal top:', window.getComputedStyle(modal).top);
    console.log('Modal width:', window.getComputedStyle(modal).width);
    console.log('Modal height:', window.getComputedStyle(modal).height);
    console.log('Modal background-color:', window.getComputedStyle(modal).backgroundColor);
    
    // Check if modal is actually in viewport
    const rect = modal.getBoundingClientRect();
    console.log('Modal bounding rect:', rect);
    console.log('Window inner dimensions:', window.innerWidth, 'x', window.innerHeight);
    
    // Also ensure modal content is visible
    const modalContent = modal.querySelector('.animation-modal-content');
    if (modalContent) {
        modalContent.style.cssText += 'visibility: visible !important; opacity: 1 !important; display: flex !important;';
        console.log('Modal content visibility set');
        console.log('Modal content computed display:', window.getComputedStyle(modalContent).display);
        console.log('Modal content computed visibility:', window.getComputedStyle(modalContent).visibility);
        console.log('Modal content computed opacity:', window.getComputedStyle(modalContent).opacity);
        const contentRect = modalContent.getBoundingClientRect();
        console.log('Modal content bounding rect:', contentRect);
    } else {
        console.error('Modal content not found!');
    }
    
    // Force a repaint to ensure styles are applied
    void modal.offsetHeight;
    
    // Temporarily set a bright red background to test if modal is actually visible
    console.log('Setting temporary red background for 3 seconds to test visibility...');
    const originalBg = modal.style.backgroundColor;
    modal.style.backgroundColor = 'rgba(255, 0, 0, 0.9)'; // Bright red background for testing
    setTimeout(() => {
        modal.style.backgroundColor = originalBg || 'rgba(0, 0, 0, 0.75)'; // Restore normal background
        console.log('Restored normal background');
    }, 3000);
    
    // Initialize modal map if not already initialized
    if (!mapModal) {
        // Wait a bit for modal to be visible before initializing map
        setTimeout(() => {
            initModalMap();
            // After map is initialized, start animation
            setTimeout(() => {
                animateWaterDataModal();
            }, 200);
        }, 100);
    } else {
        // If map already exists, invalidate size to ensure it resizes correctly
        setTimeout(() => {
            mapModal.invalidateSize();
            // Start animation after map resizes
            setTimeout(() => {
                animateWaterDataModal();
            }, 200);
        }, 100);
    }
}

function closeAnimationModal() {
    const modal = document.getElementById('animation-modal');
    modal.style.display = 'none';
    document.body.style.overflow = ''; // Restore scrolling
    
    // Stop any running animation
    if (animationInterval) {
        clearInterval(animationInterval);
        animationInterval = null;
        const animateBtn = document.getElementById('animateBtn-modal');
        const statusDiv = document.getElementById('animation-status-modal');
        if (animateBtn) animateBtn.textContent = 'Animate';
        if (statusDiv) statusDiv.style.display = 'none';
    }
}

function initModalMap() {
    // Initialize modal map - center on user location if available, otherwise use default
    let initialCenter = [-33.7, 26.7];
    let initialZoom = 13;
    
    if (userLocation) {
        initialCenter = [userLocation.lat, userLocation.lng];
        // Calculate zoom level for 2km radius
        const radiusKm = 2;
        const latRadius = radiusKm / 111; // Approximate degrees for latitude
        const lngRadius = radiusKm / (111 * Math.cos(userLocation.lat * Math.PI / 180)); // Adjust for longitude
        
        // Create a bounding box for 2km radius
        const bounds = L.latLngBounds(
            [userLocation.lat - latRadius, userLocation.lng - lngRadius],
            [userLocation.lat + latRadius, userLocation.lng + lngRadius]
        );
        
        mapModal = L.map('map-modal').fitBounds(bounds);
    } else {
        mapModal = L.map('map-modal').setView(initialCenter, initialZoom);
    }
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(mapModal);
    
    // Add user location marker if available
    if (userLocation) {
        // Use Leaflet's default pin icon for user location
        const userLocationIcon = L.icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-blue.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34],
            shadowSize: [41, 41]
        });
        
        userLocationMarkerModal = L.marker([userLocation.lat, userLocation.lng], {icon: userLocationIcon}).addTo(mapModal);
        userLocationMarkerModal.bindPopup('Your Location');
    }
}

function loadDateModal() {
    const dateInput = document.getElementById('date-select-modal');
    if (!dateInput) {
        console.error('Date input not found in modal');
        return;
    }
    const date = dateInput.value;
    console.log('Loading date in modal:', date);
    if (!date) {
        console.error('No date selected');
        return;
    }
    if (!mapModal) {
        console.error('Modal map not initialized');
        return;
    }
    loadWaterDataModal(date, true);
}

function loadWaterDataModal(date, setBounds = false) {
    // Add cache-busting parameter to prevent iOS caching issues
    const cacheBuster = '&_t=' + Date.now();
    fetch('<?= baseUrl('/api/water-data.php') ?>?date=' + date + cacheBuster, {
        cache: 'no-store',
        headers: {
            'Cache-Control': 'no-cache, no-store, must-revalidate',
            'Pragma': 'no-cache'
        }
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                clearMarkersModal();
                
                // If setting bounds for a single date, clear allCoordinates first
                if (setBounds) {
                    allCoordinates = [];
                }
                
                data.reports.forEach(report => {
                    if (report.latitude && report.longitude) {
                        // Store coordinates for bounds calculation only if setting bounds
                        if (setBounds) {
                            allCoordinates.push([report.latitude, report.longitude]);
                        }
                        
                        // Use green icon for has water, red for no water
                        // Use appropriate icon based on water status
                        let icon, status;
                        if (report.has_water == 1) {
                            icon = greenIcon;
                            status = 'Has Water';
                        } else if (report.has_water == 2) {
                            icon = orangeIcon;
                            status = 'Intermittent Water';
                        } else {
                            icon = redIcon;
                            status = 'No Water';
                        }
                        
                        // Build popup content - show user name for admins
                        let popupContent = status;
                        if (isAdmin) {
                            if (report.user_id && report.name && report.name !== 'Imported Data') {
                                const fullName = (report.name + ' ' + (report.surname || '')).trim();
                                const editUrl = '<?= baseUrl('/admin/edit-user.php?id=') ?>' + report.user_id;
                                popupContent = `<a href="${editUrl}" target="_blank" style="text-decoration: none; color: #2c5f8d; font-weight: 600;">${fullName}</a><br>${status}`;
                            } else {
                                // For imported data or records without user
                                popupContent = status;
                            }
                        }
                        
                        const marker = L.marker([report.latitude, report.longitude], {icon: icon}).addTo(mapModal);
                        marker.bindPopup(popupContent);
                        markersModal.push(marker);
                    }
                });
                
                // Only set bounds if requested and we have markers
                // But if user location is available, keep centered on user with 2km radius
                if (setBounds && markersModal.length > 0 && !userLocation) {
                    const group = new L.featureGroup(markersModal);
                    mapModal.fitBounds(group.getBounds().pad(0.1));
                    mapBoundsSet = true;
                } else if (userLocation && mapModal) {
                    // Keep map centered on user location with 2km radius
                    const radiusKm = 2;
                    const latRadius = radiusKm / 111;
                    const lngRadius = radiusKm / (111 * Math.cos(userLocation.lat * Math.PI / 180));
                    
                    const bounds = L.latLngBounds(
                        [userLocation.lat - latRadius, userLocation.lng - lngRadius],
                        [userLocation.lat + latRadius, userLocation.lng + lngRadius]
                    );
                    
                    mapModal.fitBounds(bounds);
                }
            }
        })
        .catch(error => {
            console.error('Error loading water data:', error);
        });
}

function clearMarkersModal() {
    markersModal.forEach(marker => mapModal.removeLayer(marker));
    markersModal = [];
}

// Load all data points for a date range to calculate bounds (for modal)
function loadAllDataPointsForBoundsModal(days) {
    allCoordinates = [];
    const endDate = new Date();
    const startDate = new Date();
    startDate.setDate(startDate.getDate() - days);
    
    const datePromises = [];
    const currentDate = new Date(startDate);
    
    // Load all dates in parallel
    while (currentDate <= endDate) {
        const dateStr = currentDate.toISOString().split('T')[0];
        datePromises.push(
            fetch('<?= baseUrl('/api/water-data.php') ?>?date=' + dateStr + '&_t=' + Date.now(), {
                cache: 'no-store',
                headers: {
                    'Cache-Control': 'no-cache, no-store, must-revalidate',
                    'Pragma': 'no-cache'
                }
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.reports) {
                        data.reports.forEach(report => {
                            if (report.latitude && report.longitude) {
                                allCoordinates.push([report.latitude, report.longitude]);
                            }
                        });
                    }
                })
                .catch(error => {
                    console.error('Error loading data for date ' + dateStr + ':', error);
                })
        );
        currentDate.setDate(currentDate.getDate() + 1);
    }
    
    return Promise.all(datePromises).then(() => {
        // Set map bounds to fit all coordinates
        if (allCoordinates.length > 0) {
            const bounds = L.latLngBounds(allCoordinates);
            mapModal.fitBounds(bounds.pad(0.1));
            mapBoundsSet = true;
        }
    });
}

function animateWaterDataModal() {
    const animateBtn = document.getElementById('animateBtn-modal');
    const statusDiv = document.getElementById('animation-status-modal');
    const currentDateSpan = document.getElementById('current-animation-date-modal');
    
    // If animation is running, stop it
    if (animationInterval) {
        clearInterval(animationInterval);
        animationInterval = null;
        animateBtn.textContent = 'Go';
        statusDiv.style.display = 'none';
        return;
    }
    
    animationDays = parseInt(document.getElementById('animate-days-modal').value);
    const endDate = new Date();
    const startDate = new Date();
    startDate.setDate(startDate.getDate() - animationDays);
    
    currentAnimationDate = new Date(startDate);
    
    // Show status and update button
        animateBtn.textContent = 'Stop';
    statusDiv.style.display = 'inline-block';
    
    // Reset bounds flag and load all data points to set bounds
    mapBoundsSet = false;
    allCoordinates = [];
    
    // First, set map to center on user location with 2km radius if available
    if (userLocation && mapModal) {
        const radiusKm = 2;
        const latRadius = radiusKm / 111; // Approximate degrees for latitude
        const lngRadius = radiusKm / (111 * Math.cos(userLocation.lat * Math.PI / 180)); // Adjust for longitude
        
        // Create a bounding box for 2km radius
        const bounds = L.latLngBounds(
            [userLocation.lat - latRadius, userLocation.lng - lngRadius],
            [userLocation.lat + latRadius, userLocation.lng + lngRadius]
        );
        
        mapModal.fitBounds(bounds);
    }
    
    // First, load all data points to calculate bounds
    loadAllDataPointsForBoundsModal(animationDays).then(() => {
        // Reset to user location with 2km radius after loading bounds
        if (userLocation && mapModal) {
            const radiusKm = 2;
            const latRadius = radiusKm / 111;
            const lngRadius = radiusKm / (111 * Math.cos(userLocation.lat * Math.PI / 180));
            
            const bounds = L.latLngBounds(
                [userLocation.lat - latRadius, userLocation.lng - lngRadius],
                [userLocation.lat + latRadius, userLocation.lng + lngRadius]
            );
            
            mapModal.fitBounds(bounds);
        }
        
        // Now start the animation
        function formatDate(date) {
            const d = new Date(date);
            return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        }
        
        function animate() {
            if (currentAnimationDate > endDate) {
                clearInterval(animationInterval);
                animationInterval = null;
                animateBtn.textContent = 'Go';
                statusDiv.style.display = 'none';
                return;
            }
            
            const dateStr = currentAnimationDate.toISOString().split('T')[0];
            // Don't set bounds during animation - just update markers
            loadWaterDataModal(dateStr, false);
            document.getElementById('date-select-modal').value = dateStr;
            currentDateSpan.textContent = formatDate(currentAnimationDate);
            
            currentAnimationDate.setDate(currentAnimationDate.getDate() + 1);
        }
        
        // Show initial date
        currentDateSpan.textContent = formatDate(currentAnimationDate);
        
        animate();
        animationInterval = setInterval(animate, 1000);
    });
}

// Close modal when clicking outside of it
document.addEventListener('click', function(event) {
    const modal = document.getElementById('animation-modal');
    if (modal && modal.style.display === 'block') {
        // Check if click is on the modal backdrop (not the content)
        if (event.target === modal) {
            closeAnimationModal();
        }
    }
});

// Tab switching function
function switchTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remove active class from all buttons
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab
    if (tabName === 'tracking') {
        document.getElementById('tracking-tab').classList.add('active');
        document.querySelectorAll('.tab-button')[0].classList.add('active');
        // Initialize map if not already initialized
        if (typeof L !== 'undefined' && !map) {
            initMap();
        }
    } else if (tabName === 'my-reports') {
        document.getElementById('my-reports-tab').classList.add('active');
        document.querySelectorAll('.tab-button')[1].classList.add('active');
    }
}

// Initialize map when page loads and Leaflet is ready
document.addEventListener('DOMContentLoaded', function() {
    // Wait a bit to ensure Leaflet is fully loaded
    if (typeof L !== 'undefined') {
        initMap();
    } else {
        // Retry after a short delay
        setTimeout(function() {
            if (typeof L !== 'undefined') {
                initMap();
            } else {
                console.error('Failed to load Leaflet library');
            }
        }, 100);
    }
});
</script>

<style>
.water-tabs {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 2rem;
    border-bottom: 2px solid var(--border-color);
}

.tab-button {
    padding: 0.75rem 1.5rem;
    background: none;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    font-size: 1rem;
    color: #666;
    transition: all 0.3s;
    margin-bottom: -2px;
}

.tab-button:hover {
    color: var(--primary-color);
    background-color: var(--bg-color);
}

.tab-button.active {
    color: var(--primary-color);
    border-bottom-color: var(--primary-color);
    font-weight: 600;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}
</style>

<?php 
$hideAdverts = false;
include 'includes/footer.php'; 
?>
