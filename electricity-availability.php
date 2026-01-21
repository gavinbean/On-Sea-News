<?php
require_once 'includes/functions.php';
requireAnyRole(['ADMIN', 'ELECTRICITY_ADMIN']);

$db = getDB();
$message = '';
$error = '';

// Get current user for form
$user = getCurrentUser();
$userId = getCurrentUserId();
$isAdmin = hasRole('ADMIN') || hasRole('ELECTRICITY_ADMIN');

// Get filter parameters
$fromDate = $_GET['from_date'] ?? date('Y-m-d', strtotime('-30 days'));
$toDate = $_GET['to_date'] ?? date('Y-m-d');
$statusFilter = $_GET['status_filter'] ?? 'open'; // open or closed

// Build WHERE clause for filtering
$whereConditions = ["e.latitude IS NOT NULL", "e.longitude IS NOT NULL"];

// Date filtering
if (!empty($fromDate)) {
    $whereConditions[] = "DATE(e.created_at) >= ?";
}
if (!empty($toDate)) {
    $whereConditions[] = "DATE(e.created_at) <= ?";
}

// Status filtering
if ($statusFilter === 'open') {
    $whereConditions[] = "e.status != 'Closed'";
} elseif ($statusFilter === 'closed') {
    $whereConditions[] = "e.status = 'Closed'";
}

$whereClause = implode(' AND ', $whereConditions);

// Get filtered electricity issues for map display
$params = [];
if (!empty($fromDate)) {
    $params[] = $fromDate;
}
if (!empty($toDate)) {
    $params[] = $toDate;
}

$stmt = $db->prepare("
    SELECT 
        e.*,
        u.name as reporter_name,
        u.surname as reporter_surname,
        (SELECT COUNT(*) FROM " . TABLE_PREFIX . "electricity_issue_comments WHERE issue_id = e.issue_id) as comment_count
    FROM " . TABLE_PREFIX . "electricity_issues e
    JOIN " . TABLE_PREFIX . "users u ON e.user_id = u.user_id
    WHERE $whereClause
    ORDER BY e.created_at DESC
");
$stmt->execute($params);
$issues = $stmt->fetchAll();

$pageTitle = 'Electricity Issues';
include 'includes/header.php';
?>

<style>
.electricity-controls {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}

.issue-modal {
    display: none;
    position: fixed;
    z-index: 10000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    overflow-y: auto;
}

.issue-modal-content {
    background-color: #fff;
    margin: 2rem auto;
    padding: 2rem;
    border-radius: 8px;
    max-width: 800px;
    width: 90%;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    position: relative;
}

.issue-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--border-color);
}

.issue-modal-header h2 {
    margin: 0;
    color: var(--primary-color);
}

.modal-close-btn {
    background: none;
    border: none;
    font-size: 2rem;
    cursor: pointer;
    color: #666;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-close-btn:hover {
    color: #000;
}

.issue-details {
    margin-bottom: 1.5rem;
}

.issue-detail-row {
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e0e0e0;
}

.issue-detail-row:last-child {
    border-bottom: none;
}

.issue-detail-label {
    font-weight: 600;
    color: #666;
    margin-bottom: 0.25rem;
    display: block;
}

.issue-detail-value {
    color: #333;
}

.status-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 4px;
    font-size: 0.9rem;
    font-weight: 600;
}

.status-new { background-color: #e74c3c; color: white; }
.status-received { background-color: #f39c12; color: white; }
.status-updated { background-color: #f39c12; color: white; }
.status-resolved { background-color: #f39c12; color: white; }
.status-closed { background-color: #27ae60; color: white; }

.comments-section {
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 2px solid var(--border-color);
}

.comment-item {
    background-color: #f9f9f9;
    padding: 1rem;
    margin-bottom: 1rem;
    border-radius: 4px;
    border-left: 4px solid var(--primary-color);
}

.comment-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
    color: #666;
}

.comment-text {
    color: #333;
    white-space: pre-wrap;
}

.readonly-field {
    background-color: #f5f5f5;
    border: 1px solid #ddd;
    padding: 0.5rem;
    border-radius: 4px;
    color: #666;
}

#map {
    height: 600px;
    width: 100%;
    margin-top: 2rem;
    border-radius: 8px;
    border: 1px solid var(--border-color);
}
</style>

<div class="container">
    <div class="content-area">
        <h1>Electricity Issues</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= h($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        
        <div class="electricity-controls">
            <button type="button" class="btn btn-primary" onclick="openAddIssueModal()">Add Issue</button>
            
            <div style="display: flex; align-items: center; gap: 1rem; margin-left: auto; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <label for="from_date" style="white-space: nowrap;">From:</label>
                    <input type="date" id="from_date" name="from_date" value="<?= h($fromDate) ?>" onchange="filterMap()" style="padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 4px;">
                </div>
                
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <label for="to_date" style="white-space: nowrap;">To:</label>
                    <input type="date" id="to_date" name="to_date" value="<?= h($toDate) ?>" onchange="filterMap()" style="padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 4px;">
                </div>
                
                <div style="display: flex; align-items: center; gap: 1rem; margin-left: 1rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="radio" name="status_filter" value="open" <?= $statusFilter === 'open' ? 'checked' : '' ?> onchange="filterMap()">
                        <span>Open</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="radio" name="status_filter" value="closed" <?= $statusFilter === 'closed' ? 'checked' : '' ?> onchange="filterMap()">
                        <span>Closed</span>
                    </label>
                </div>
            </div>
        </div>
        
        <div id="map"></div>
    </div>
</div>

<!-- Add Issue Modal -->
<div id="add-issue-modal" class="issue-modal">
    <div class="issue-modal-content">
        <div class="issue-modal-header">
            <h2>Report Electricity Issue</h2>
            <button type="button" class="modal-close-btn" onclick="closeAddIssueModal()" aria-label="Close">&times;</button>
        </div>
        <form id="add-issue-form" onsubmit="submitIssue(event)">
            <input type="hidden" name="action" value="create">
            
            <div class="form-group">
                <label for="issue_name" class="issue-detail-label">Name:</label>
                <input type="text" 
                       id="issue_name" 
                       name="name" 
                       class="readonly-field" 
                       value="<?= h($user['name'] . ' ' . $user['surname']) ?>" 
                       readonly>
            </div>
            
            <div class="form-group">
                <label for="issue_address" class="issue-detail-label">Address:</label>
                <?php 
                $addressParts = [];
                if (!empty($user['street_number'])) $addressParts[] = $user['street_number'];
                if (!empty($user['street_name'])) $addressParts[] = $user['street_name'];
                if (!empty($user['suburb'])) $addressParts[] = $user['suburb'];
                if (!empty($user['town'])) $addressParts[] = $user['town'];
                $userAddress = implode(', ', $addressParts) ?: 'No address on file';
                ?>
                <textarea id="issue_address" 
                          name="address" 
                          class="readonly-field" 
                          rows="2" 
                          readonly><?= h($userAddress) ?></textarea>
                <?php if (empty($user['latitude']) || empty($user['longitude'])): ?>
                    <p class="alert alert-error" style="margin-top: 0.5rem;">Please update your profile with a valid address that includes location information.</p>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="issue_status" class="issue-detail-label">Status:</label>
                <input type="text" 
                       id="issue_status" 
                       name="status" 
                       class="readonly-field" 
                       value="New Issue" 
                       readonly>
            </div>
            
            <div class="form-group">
                <label for="issue_description" class="issue-detail-label">Description of Problem:</label>
                <textarea id="issue_description" 
                          name="description" 
                          rows="5" 
                          required 
                          style="width: 100%; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 4px; font-family: inherit;"></textarea>
                <small style="display: block; margin-top: 0.5rem; color: #666;">
                    Please provide a detailed description of the electricity issue you are experiencing.
                </small>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Submit Issue</button>
                <button type="button" class="btn btn-secondary" onclick="closeAddIssueModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Issue Details Modal -->
<div id="issue-details-modal" class="issue-modal">
    <div class="issue-modal-content">
        <div class="issue-modal-header">
            <h2>Issue Details</h2>
            <button type="button" class="modal-close-btn" onclick="closeIssueDetailsModal()" aria-label="Close">&times;</button>
        </div>
        <div id="issue-details-content">
            <!-- Content loaded via AJAX -->
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

<!-- Custom Lightning Bolt Icon -->
<script>
// Create custom lightning bolt icon
function createLightningIcon(color) {
    return L.divIcon({
        className: 'lightning-icon',
        html: `<svg width="30" height="30" viewBox="0 0 24 24" fill="${color}" stroke="${color}" stroke-width="2">
            <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>
        </svg>`,
        iconSize: [30, 30],
        iconAnchor: [15, 15]
    });
}

let map;
let markers = [];
const userLocation = <?= !empty($user['latitude']) && !empty($user['longitude']) ? json_encode(['lat' => (float)$user['latitude'], 'lng' => (float)$user['longitude']]) : 'null' ?>;
let userLocationMarker = null;

// Initialize map
function initMap() {
    // Initialize map - center on user location if available, otherwise use default
    let initialCenter = [-34.05, 23.05];
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
        
        map = L.map('map').fitBounds(bounds);
    } else {
        map = L.map('map').setView(initialCenter, initialZoom);
    }
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);
    
    // Add user location marker if available
    if (userLocation) {
        // Use Leaflet's blue pin icon for user location
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
    
    // Add markers for each issue
    <?php foreach ($issues as $issue): ?>
        <?php if (!empty($issue['latitude']) && !empty($issue['longitude'])): ?>
            <?php
            // Determine icon color based on status
            $iconColor = '#e74c3c'; // Red for New Issue
            if ($issue['status'] === 'Closed') {
                $iconColor = '#27ae60'; // Green for Closed
            } elseif (in_array($issue['status'], ['Issue Received', 'Issue Updated', 'Issue Resolved'])) {
                $iconColor = '#f39c12'; // Orange for other statuses
            }
            ?>
            var marker = L.marker([<?= $issue['latitude'] ?>, <?= $issue['longitude'] ?>], {
                icon: createLightningIcon('<?= $iconColor ?>')
            }).addTo(map);
            
            <?php if ($isAdmin): ?>
            marker.bindPopup(`
                <div style="min-width: 200px;">
                    <strong><?= h($issue['reporter_name'] . ' ' . $issue['reporter_surname']) ?></strong><br>
                    <small><?= h($issue['address']) ?></small><br>
                    <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $issue['status'])) ?>"><?= h($issue['status']) ?></span><br>
                    <small>Created: <?= date('Y-m-d H:i', strtotime($issue['created_at'])) ?></small><br>
                    <button onclick="viewIssueDetails(<?= $issue['issue_id'] ?>)" style="margin-top: 0.5rem; padding: 0.25rem 0.5rem; background: var(--primary-color); color: white; border: none; border-radius: 4px; cursor: pointer;">View Details</button>
                </div>
            `);
            <?php else: ?>
            marker.bindPopup(`
                <div style="min-width: 200px;">
                    <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $issue['status'])) ?>"><?= h($issue['status']) ?></span><br>
                    <small>Created: <?= date('Y-m-d H:i', strtotime($issue['created_at'])) ?></small><br>
                    <button onclick="viewIssueDetails(<?= $issue['issue_id'] ?>)" style="margin-top: 0.5rem; padding: 0.25rem 0.5rem; background: var(--primary-color); color: white; border: none; border-radius: 4px; cursor: pointer;">View Details</button>
                </div>
            `);
            <?php endif; ?>
            
            markers.push(marker);
        <?php endif; ?>
    <?php endforeach; ?>
    
    // Fit map to show all markers, but keep user location centered if available
    if (markers.length > 0) {
        if (userLocation) {
            // If user location is available, keep centered on user with 2km radius
            const radiusKm = 2;
            const latRadius = radiusKm / 111;
            const lngRadius = radiusKm / (111 * Math.cos(userLocation.lat * Math.PI / 180));
            const bounds = L.latLngBounds(
                [userLocation.lat - latRadius, userLocation.lng - lngRadius],
                [userLocation.lat + latRadius, userLocation.lng + lngRadius]
            );
            map.fitBounds(bounds);
        } else {
            // If no user location, fit to all markers
            var group = new L.featureGroup(markers);
            map.fitBounds(group.getBounds().pad(0.1));
        }
    } else if (userLocation) {
        // If no issue markers but user location available, keep centered on user
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

function openAddIssueModal() {
    document.getElementById('add-issue-modal').style.display = 'block';
}

function closeAddIssueModal() {
    document.getElementById('add-issue-modal').style.display = 'none';
}

function viewIssueDetails(issueId) {
    fetch('api/electricity-issue-details.php?issue_id=' + issueId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('issue-details-content').innerHTML = data.html;
                document.getElementById('issue-details-modal').style.display = 'block';
            } else {
                alert('Error loading issue details: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading issue details');
        });
}

function closeIssueDetailsModal() {
    document.getElementById('issue-details-modal').style.display = 'none';
}

// Close modals when clicking outside
window.onclick = function(event) {
    var addModal = document.getElementById('add-issue-modal');
    var detailsModal = document.getElementById('issue-details-modal');
    if (event.target == addModal) {
        closeAddIssueModal();
    }
    if (event.target == detailsModal) {
        closeIssueDetailsModal();
    }
}

function submitIssue(event) {
    event.preventDefault();
    
    const form = document.getElementById('add-issue-form');
    const formData = new FormData(form);
    
    fetch('handle-electricity-issue.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Issue submitted successfully!');
            closeAddIssueModal();
            // Reload page to refresh map
            window.location.reload();
        } else {
            alert('Error submitting issue: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error submitting issue');
    });
}

// Initialize map when page loads
document.addEventListener('DOMContentLoaded', function() {
    initMap();
});
</script>

<?php 
$hideAdverts = true;
include 'includes/footer.php'; 
?>
