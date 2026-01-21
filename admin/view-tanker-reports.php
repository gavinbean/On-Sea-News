<?php
require_once __DIR__ . '/../includes/functions.php';
requireAnyRole(['ADMIN', 'ANALYTICS']);

$db = getDB();

// Get filter parameters
$fromDate = $_GET['from_date'] ?? date('Y-m-d', strtotime('-30 days'));
$toDate = $_GET['to_date'] ?? date('Y-m-d');

$pageTitle = 'View Tanker Reports';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="content-area">
        <h1>View Tanker Reports</h1>
        
        <p><a href="<?= baseUrl('/admin/dashboard.php') ?>" class="btn btn-secondary">← Back to Dashboard</a></p>
        
        <!-- Date Filters -->
        <div class="tanker-filters" style="background: #f5f5f5; padding: 20px; border-radius: 4px; margin-bottom: 20px;">
            <form method="GET" action="" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
                <div class="form-group" style="margin: 0;">
                    <label for="from_date">From Date:</label>
                    <input type="date" id="from_date" name="from_date" value="<?= h($fromDate) ?>" required>
                </div>
                
                <div class="form-group" style="margin: 0;">
                    <label for="to_date">To Date:</label>
                    <input type="date" id="to_date" name="to_date" value="<?= h($toDate) ?>" required>
                </div>
                
                <div class="form-group" style="margin: 0;">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="?" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
        
        <!-- Map -->
        <div id="map" style="width: 100%; height: 500px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 20px;"></div>
        
        <!-- Reports List -->
        <div class="tanker-reports-list">
            <h2>Tanker Reports</h2>
            <div id="reports-container">
                <p style="color: #666;">Loading reports...</p>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
let map;
let markers = [];
const fromDate = '<?= h($fromDate) ?>';
const toDate = '<?= h($toDate) ?>';

// Initialize map
function initMap() {
    // Default center (South Africa)
    map = L.map('map').setView([-33.7, 26.7], 6);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);
    
    // Load reports
    loadReports();
}

// Load reports data
function loadReports() {
    const apiUrl = '<?= baseUrl('/api/tanker-reports.php') ?>?from_date=' + encodeURIComponent(fromDate) + '&to_date=' + encodeURIComponent(toDate);
    
    fetch(apiUrl)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.reports) {
                displayReports(data.reports);
                displayMapMarkers(data.reports);
            } else {
                document.getElementById('reports-container').innerHTML = '<p>No reports found for the selected date range.</p>';
            }
        })
        .catch(error => {
            console.error('Error loading reports:', error);
            document.getElementById('reports-container').innerHTML = '<p style="color: red;">Error loading reports. Please try again.</p>';
        });
}

// Display reports list
function displayReports(reports) {
    const container = document.getElementById('reports-container');
    
    if (reports.length === 0) {
        container.innerHTML = '<p>No reports found for the selected date range.</p>';
        return;
    }
    
    let html = '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">';
    
    reports.forEach(function(report) {
        const photoUrl = report.photo_path ? '<?= baseUrl('/') ?>' + report.photo_path : '';
        const reportDate = new Date(report.reported_at).toLocaleString();
        
        html += `
            <div class="tanker-report-card" style="background: white; border: 1px solid #ddd; border-radius: 4px; padding: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                ${photoUrl ? `
                    <div style="margin-bottom: 10px;">
                        <img src="${photoUrl}" alt="Tanker Photo" style="width: 100%; height: 200px; object-fit: cover; border-radius: 4px; cursor: pointer;" onclick="showReportDetails(${report.report_id})">
                    </div>
                ` : '<div style="height: 200px; background: #f5f5f5; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #999;">No Photo</div>'}
                <div>
                    <p style="margin: 5px 0; font-weight: bold;">Registration: ${escapeHtml(report.registration_number)}</p>
                    <p style="margin: 5px 0; color: #666; font-size: 0.9rem;">Date: ${reportDate}</p>
                    <p style="margin: 5px 0; color: #666; font-size: 0.9rem;">Device: ${escapeHtml(report.device_type)}</p>
                    ${report.address ? `<p style="margin: 5px 0; color: #666; font-size: 0.9rem;">Address: ${escapeHtml(report.address)}</p>` : ''}
                    <p style="margin: 5px 0; color: #666; font-size: 0.9rem;">Location: ${report.latitude.toFixed(6)}, ${report.longitude.toFixed(6)}</p>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="showReportDetails(${report.report_id})" style="margin-top: 10px; width: 100%;">View Details</button>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;
}

// Display markers on map
function displayMapMarkers(reports) {
    // Clear existing markers
    markers.forEach(marker => map.removeLayer(marker));
    markers = [];
    
    if (reports.length === 0) {
        return;
    }
    
    const bounds = [];
    
    reports.forEach(function(report) {
        if (report.latitude && report.longitude) {
            const lat = parseFloat(report.latitude);
            const lng = parseFloat(report.longitude);
            
            // Create custom icon
            const icon = L.divIcon({
                className: 'tanker-marker',
                html: '<div style="background: #e74c3c; color: white; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; font-weight: bold; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);">T</div>',
                iconSize: [30, 30],
                iconAnchor: [15, 15]
            });
            
            const marker = L.marker([lat, lng], {icon: icon}).addTo(map);
            
            // Create popup content
            const photoUrl = report.photo_path ? '<?= baseUrl('/') ?>' + report.photo_path : '';
            const reportDate = new Date(report.reported_at).toLocaleString();
            
            let popupContent = `
                <div style="min-width: 200px;">
                    <h3 style="margin: 0 0 10px 0;">${escapeHtml(report.registration_number)}</h3>
                    ${photoUrl ? `<img src="${photoUrl}" alt="Tanker Photo" style="width: 100%; max-height: 200px; object-fit: cover; border-radius: 4px; margin-bottom: 10px; cursor: pointer;" onclick="showReportDetails(${report.report_id})">` : ''}
                    <p style="margin: 5px 0; font-size: 0.9rem;"><strong>Date:</strong> ${reportDate}</p>
                    <p style="margin: 5px 0; font-size: 0.9rem;"><strong>Device:</strong> ${escapeHtml(report.device_type)}</p>
                    ${report.address ? `<p style="margin: 5px 0; font-size: 0.9rem;"><strong>Address:</strong> ${escapeHtml(report.address)}</p>` : ''}
                    <p style="margin: 5px 0; font-size: 0.9rem;"><strong>Location:</strong> ${lat.toFixed(6)}, ${lng.toFixed(6)}</p>
                    <button type="button" class="btn btn-primary btn-sm" onclick="showReportDetails(${report.report_id})" style="margin-top: 10px; width: 100%;">View Full Details</button>
                </div>
            `;
            
            marker.bindPopup(popupContent);
            markers.push(marker);
            bounds.push([lat, lng]);
        }
    });
    
    // Fit map to show all markers
    if (bounds.length > 0) {
        map.fitBounds(bounds, {padding: [50, 50]});
    }
}

// Show report details in modal
function showReportDetails(reportId) {
    // Fetch full report details
    fetch('<?= baseUrl('/api/tanker-reports.php') ?>?report_id=' + reportId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.report) {
                const report = data.report;
                const photoUrl = report.photo_path ? '<?= baseUrl('/') ?>' + report.photo_path : '';
                const reportDate = new Date(report.reported_at).toLocaleString();
                
                let modalContent = `
                    <div style="max-width: 600px; margin: 0 auto;">
                        <h2 style="margin-top: 0;">Tanker Report Details</h2>
                        ${photoUrl ? `
                            <div style="margin-bottom: 20px;">
                                <img src="${photoUrl}" alt="Tanker Photo" style="width: 100%; max-height: 400px; object-fit: contain; border-radius: 4px; border: 1px solid #ddd;">
                            </div>
                        ` : ''}
                        <div style="background: #f5f5f5; padding: 15px; border-radius: 4px;">
                            <p style="margin: 5px 0;"><strong>Registration Number:</strong> ${escapeHtml(report.registration_number)}</p>
                            <p style="margin: 5px 0;"><strong>Reported At:</strong> ${reportDate}</p>
                            <p style="margin: 5px 0;"><strong>Reported By:</strong> ${escapeHtml(report.reported_by_name)}</p>
                            <p style="margin: 5px 0;"><strong>Device Type:</strong> ${escapeHtml(report.device_type)}</p>
                            ${report.address ? `<p style="margin: 5px 0;"><strong>Address:</strong> ${escapeHtml(report.address)}</p>` : ''}
                            <p style="margin: 5px 0;"><strong>Latitude:</strong> ${parseFloat(report.latitude).toFixed(8)}</p>
                            <p style="margin: 5px 0;"><strong>Longitude:</strong> ${parseFloat(report.longitude).toFixed(8)}</p>
                        </div>
                    </div>
                `;
                
                // Create and show modal
                showModal('Tanker Report Details', modalContent);
            }
        })
        .catch(error => {
            console.error('Error loading report details:', error);
            alert('Error loading report details. Please try again.');
        });
}

// Show modal
function showModal(title, content) {
    // Remove existing modal if any
    const existingModal = document.getElementById('tanker-modal');
    if (existingModal) {
        existingModal.remove();
    }
    
    const modal = document.createElement('div');
    modal.id = 'tanker-modal';
    modal.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; display: flex; align-items: center; justify-content: center; padding: 20px;';
    
    const modalContent = document.createElement('div');
    modalContent.style.cssText = 'background: white; border-radius: 8px; padding: 20px; max-width: 90%; max-height: 90vh; overflow-y: auto; position: relative;';
    
    const closeBtn = document.createElement('button');
    closeBtn.innerHTML = '&times;';
    closeBtn.style.cssText = 'position: absolute; top: 10px; right: 10px; background: none; border: none; font-size: 2rem; cursor: pointer; color: #666; width: 40px; height: 40px; line-height: 40px;';
    closeBtn.onclick = function() {
        modal.remove();
    };
    
    modalContent.appendChild(closeBtn);
    modalContent.innerHTML += content;
    modal.appendChild(modalContent);
    
    modal.onclick = function(e) {
        if (e.target === modal) {
            modal.remove();
        }
    };
    
    document.body.appendChild(modal);
}

// Escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initMap();
});
</script>

<style>
.tanker-filters .form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.tanker-filters input[type="date"] {
    padding: 0.5rem;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 1rem;
}

.tanker-report-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    transition: box-shadow 0.3s;
}

.tanker-report-card img {
    transition: transform 0.3s;
}

.tanker-report-card img:hover {
    transform: scale(1.05);
}
</style>

<?php 
include __DIR__ . '/../includes/footer.php'; 
?>
