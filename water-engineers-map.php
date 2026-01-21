<?php
require_once 'includes/functions.php';
requireLogin();

// Allow ADMIN and ANALYTICS roles
if (!hasRole('ADMIN') && !hasRole('ANALYTICS')) {
    header('Location: ' . baseUrl('/index.php'));
    exit;
}

$db = getDB();
$message = '';
$error = '';

// Handle KML file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_kml') {
    if (isset($_FILES['kml_file']) && $_FILES['kml_file']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['application/vnd.google-earth.kml+xml', 'application/xml', 'text/xml', 'text/plain'];
        $fileType = $_FILES['kml_file']['type'];
        $fileName = $_FILES['kml_file']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Check if it's a KML file
        if ($fileExtension === 'kml' || in_array($fileType, $allowedTypes)) {
            $uploadDir = __DIR__ . '/uploads/kml/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $newFileName = 'kml_' . time() . '_' . uniqid() . '.kml';
            $targetPath = $uploadDir . $newFileName;
            
            if (move_uploaded_file($_FILES['kml_file']['tmp_name'], $targetPath)) {
                // Redirect to auto-load the uploaded file
                header('Location: ' . baseUrl('/water-engineers-map.php?kml=' . urlencode($newFileName)));
                exit;
            } else {
                $error = 'Failed to upload KML file.';
            }
        } else {
            $error = 'Invalid file type. Please upload a KML file.';
        }
    } else {
        $error = 'No file uploaded or upload error occurred.';
    }
}

// Get list of uploaded KML files from both locations
$kmlFiles = [];
$kmlDirs = [
    __DIR__ . '/uploads/kml/',
    __DIR__ . '/uploads/'
];

foreach ($kmlDirs as $kmlDir) {
    if (is_dir($kmlDir)) {
        $files = scandir($kmlDir);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'kml') {
                $relativePath = str_replace(__DIR__ . '/', '', $kmlDir) . $file;
                $kmlFiles[] = [
                    'name' => $file,
                    'path' => $relativePath,
                    'size' => filesize($kmlDir . $file),
                    'modified' => filemtime($kmlDir . $file)
                ];
            }
        }
    }
}

// Remove duplicates (in case file exists in both locations)
$uniqueFiles = [];
$seenNames = [];
foreach ($kmlFiles as $file) {
    if (!in_array($file['name'], $seenNames)) {
        $uniqueFiles[] = $file;
        $seenNames[] = $file['name'];
    }
}
$kmlFiles = $uniqueFiles;

// Sort by modification time (newest first)
usort($kmlFiles, function($a, $b) {
    return $b['modified'] - $a['modified'];
});

// Get selected KML file from query parameter
$selectedKml = $_GET['kml'] ?? '';
$selectedKmlPath = '';

if (!empty($selectedKml)) {
    // Try to find the file in both locations
    $possiblePaths = [
        __DIR__ . '/uploads/kml/' . basename($selectedKml),
        __DIR__ . '/uploads/' . basename($selectedKml)
    ];
    
    foreach ($possiblePaths as $fullPath) {
        if (file_exists($fullPath)) {
            $selectedKmlPath = str_replace(__DIR__ . '/', '', $fullPath);
            break;
        }
    }
}

// If no file selected or found, prioritize places.kml, then use the first available file
if (empty($selectedKmlPath) && !empty($kmlFiles)) {
    // Look for places.kml first
    $placesKml = null;
    foreach ($kmlFiles as $file) {
        if (strtolower($file['name']) === 'places.kml') {
            $placesKml = $file;
            break;
        }
    }
    $selectedKmlPath = $placesKml ? $placesKml['path'] : $kmlFiles[0]['path'];
}

$pageTitle = 'Water Engineers Map';
include 'includes/header.php';
?>

<style>
.kml-controls {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
    padding: 1rem;
    background: #f5f5f5;
    border-radius: 8px;
}

.kml-upload-form {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.kml-file-list {
    margin-top: 1rem;
    padding: 1rem;
    background: white;
    border-radius: 8px;
    border: 1px solid var(--border-color);
}

.kml-file-item {
    padding: 0.5rem;
    margin-bottom: 0.5rem;
    border-bottom: 1px solid #e0e0e0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.kml-file-item:last-child {
    border-bottom: none;
}

.kml-file-item.active {
    background-color: #e7f3ff;
    border-left: 4px solid var(--primary-color);
    padding-left: calc(0.5rem - 4px);
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
        <h1>Water Engineers Map</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= h($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        
        <div class="kml-controls">
            <form method="POST" enctype="multipart/form-data" class="kml-upload-form">
                <input type="hidden" name="action" value="upload_kml">
                <input type="file" name="kml_file" accept=".kml" required>
                <button type="submit" class="btn btn-primary">Upload KML File</button>
            </form>
        </div>
        
        <?php if (!empty($kmlFiles)): ?>
            <div class="kml-file-list">
                <h3>Available KML Files</h3>
                <?php foreach ($kmlFiles as $kmlFile): ?>
                    <div class="kml-file-item <?= $kmlFile['path'] === $selectedKmlPath ? 'active' : '' ?>">
                        <div>
                            <strong><?= h($kmlFile['name']) ?></strong>
                            <small style="display: block; color: #666;">
                                Size: <?= number_format($kmlFile['size'] / 1024, 2) ?> KB | 
                                Modified: <?= date('Y-m-d H:i', $kmlFile['modified']) ?>
                            </small>
                        </div>
                        <a href="?kml=<?= urlencode($kmlFile['name']) ?>" class="btn btn-sm btn-primary">Load on Map</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <p>No KML files uploaded yet. Upload a KML file to display it on the map.</p>
            </div>
        <?php endif; ?>
        
        <div id="map"></div>
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

<!-- toGeoJSON plugin for KML support -->
<script src="https://unpkg.com/@mapbox/togeojson@0.16.0/togeojson.js"></script>
<!-- Alternative CDN if first fails -->
<script>
if (typeof toGeoJSON === 'undefined') {
    const script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/@mapbox/togeojson@0.16.0/togeojson.js';
    script.onerror = function() {
        console.error('Failed to load toGeoJSON from both CDNs');
    };
    document.head.appendChild(script);
}
</script>

<script>
let map;
let kmlLayer = null;

// Initialize map
function initMap() {
    // Center on South Africa (adjust as needed)
    map = L.map('map').setView([-33.9249, 18.4241], 10);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);
    
    <?php if (!empty($selectedKmlPath)): ?>
    // Load KML file automatically
    console.log('Auto-loading KML:', '<?= baseUrl('/' . $selectedKmlPath) ?>');
    loadKML('<?= baseUrl('/' . $selectedKmlPath) ?>');
    <?php else: ?>
    console.log('No KML file selected for auto-load');
    <?php endif; ?>
}

function loadKML(kmlUrl) {
    // Remove existing KML layer if present
    if (kmlLayer) {
        map.removeLayer(kmlLayer);
        kmlLayer = null;
    }
    
    // Show loading message
    const loadingMsg = L.marker([0, 0]).addTo(map);
    loadingMsg.bindPopup('Loading KML file...').openPopup();
    
    // Fetch and parse KML file
    fetch(kmlUrl + '?t=' + Date.now()) // Add cache buster
        .then(response => {
            if (!response.ok) {
                throw new Error('Failed to load KML file: ' + response.statusText);
            }
            return response.text();
        })
        .then(kmlText => {
            map.removeLayer(loadingMsg);
            
            // Parse KML to GeoJSON
            const parser = new DOMParser();
            const kml = parser.parseFromString(kmlText, 'text/xml');
            
            // Check for parsing errors
            const parseError = kml.querySelector('parsererror');
            if (parseError) {
                throw new Error('Invalid KML file format');
            }
            
            const geojson = toGeoJSON.kml(kml);
            
            if (!geojson || !geojson.features || geojson.features.length === 0) {
                throw new Error('KML file contains no valid geographic features');
            }
            
            // Create Leaflet layer from GeoJSON
            kmlLayer = L.geoJSON(geojson, {
                onEachFeature: function(feature, layer) {
                    // Add popup with feature name/description if available
                    if (feature.properties) {
                        let popupContent = '';
                        if (feature.properties.name) {
                            popupContent += '<strong>' + escapeHtml(feature.properties.name) + '</strong><br>';
                        }
                        if (feature.properties.description) {
                            // Clean HTML from description
                            const desc = feature.properties.description.replace(/<[^>]*>/g, '');
                            popupContent += escapeHtml(desc);
                        }
                        if (popupContent) {
                            layer.bindPopup(popupContent);
                        }
                    }
                },
                style: function(feature) {
                    // Style based on feature properties
                    return {
                        color: feature.properties.stroke || feature.properties['stroke-color'] || '#3388ff',
                        weight: feature.properties['stroke-width'] || 3,
                        opacity: feature.properties['stroke-opacity'] || 0.8,
                        fillColor: feature.properties.fill || feature.properties['fill-color'] || '#3388ff',
                        fillOpacity: feature.properties['fill-opacity'] || 0.2
                    };
                },
                pointToLayer: function(feature, latlng) {
                    // Custom marker for points
                    return L.marker(latlng, {
                        icon: L.icon({
                            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-blue.png',
                            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                            iconSize: [25, 41],
                            iconAnchor: [12, 41],
                            popupAnchor: [1, -34],
                            shadowSize: [41, 41]
                        })
                    });
                }
            }).addTo(map);
            
            // Fit map to KML bounds - wait a moment for layer to be fully added
            setTimeout(function() {
                try {
                    const bounds = kmlLayer.getBounds();
                    if (bounds && bounds.isValid()) {
                        map.fitBounds(bounds.pad(0.1));
                    } else {
                        // If bounds are invalid, collect all coordinates from features
                        let allLatLngs = [];
                        kmlLayer.eachLayer(function(layer) {
                            if (layer.getLatLng) {
                                const latlng = layer.getLatLng();
                                if (latlng && latlng.lat && latlng.lng) {
                                    allLatLngs.push(latlng);
                                }
                            } else if (layer.getBounds) {
                                const layerBounds = layer.getBounds();
                                if (layerBounds && layerBounds.isValid()) {
                                    allLatLngs.push(layerBounds.getNorthEast());
                                    allLatLngs.push(layerBounds.getSouthWest());
                                }
                            } else if (layer.getLatLngs) {
                                const latlngs = layer.getLatLngs();
                                if (Array.isArray(latlngs)) {
                                    latlngs.forEach(function(ll) {
                                        if (ll && ll.lat && ll.lng) {
                                            allLatLngs.push(ll);
                                        }
                                    });
                                }
                            }
                        });
                        
                        if (allLatLngs.length > 0) {
                            const bounds = L.latLngBounds(allLatLngs);
                            if (bounds.isValid()) {
                                map.fitBounds(bounds.pad(0.1));
                            }
                        } else {
                            // Last resort: try to center on first feature
                            if (geojson.features.length > 0 && geojson.features[0].geometry) {
                                const coords = geojson.features[0].geometry.coordinates;
                                if (coords && coords.length >= 2) {
                                    const lat = Array.isArray(coords[0]) ? coords[0][1] : coords[1];
                                    const lng = Array.isArray(coords[0]) ? coords[0][0] : coords[0];
                                    if (lat && lng && !isNaN(lat) && !isNaN(lng)) {
                                        map.setView([lat, lng], 13);
                                    }
                                }
                            }
                        }
                    }
                } catch (e) {
                    console.error('Error fitting bounds:', e);
                }
            }, 100);
        })
        .catch(error => {
            map.removeLayer(loadingMsg);
            console.error('Error loading KML:', error);
            alert('Error loading KML file: ' + error.message);
        });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
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
