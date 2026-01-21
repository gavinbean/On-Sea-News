<?php
require_once 'includes/functions.php';
requireLogin();

$db = getDB();
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $registrationNumber = trim($_POST['registration_number'] ?? '');
    $latitude = trim($_POST['latitude'] ?? '');
    $longitude = trim($_POST['longitude'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $deviceType = trim($_POST['device_type'] ?? 'desktop');
    
    // Validation
    if (empty($registrationNumber)) {
        $error = 'Please enter the registration number.';
    } elseif (empty($latitude) || empty($longitude)) {
        $error = 'Location coordinates are required.';
    } else {
        $userId = getCurrentUserId();
        
        // Handle photo upload
        $photoPath = null;
        if (isset($_FILES['photo'])) {
            $fileError = $_FILES['photo']['error'];
            
            // Check for upload errors
            if ($fileError === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/uploads/tankers/';
                if (!is_dir($uploadDir)) {
                    if (!mkdir($uploadDir, 0755, true)) {
                        $error = 'Failed to create upload directory.';
                        error_log("Failed to create upload directory: " . $uploadDir);
                    }
                }
                
                if (empty($error)) {
                    // Get file extension from name or MIME type
                    $fileName = $_FILES['photo']['name'] ?? '';
                    // On mobile, filename might be empty, so use temp name or generate one
                    if (empty($fileName)) {
                        $fileName = 'mobile_photo_' . time();
                    }
                    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    
                    // If no extension, try to get from MIME type
                    if (empty($fileExtension) && isset($_FILES['photo']['type'])) {
                        $mimeType = $_FILES['photo']['type'];
                        $mimeToExt = [
                            'image/jpeg' => 'jpg',
                            'image/jpg' => 'jpg',
                            'image/png' => 'png',
                            'image/gif' => 'gif',
                            'image/webp' => 'webp'
                        ];
                        if (isset($mimeToExt[$mimeType])) {
                            $fileExtension = $mimeToExt[$mimeType];
                        }
                    }
                    
                    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    
                    if (empty($fileExtension) || !in_array($fileExtension, $allowedExtensions)) {
                        $error = 'Invalid file type. Please upload a JPG, PNG, GIF, or WEBP image.';
                        error_log("Invalid file type. Extension: " . $fileExtension . ", MIME: " . ($_FILES['photo']['type'] ?? 'unknown'));
                    } else {
                        $fileName = 'tanker_' . time() . '_' . uniqid() . '.' . $fileExtension;
                        $filePath = $uploadDir . $fileName;
                        
                        if (move_uploaded_file($_FILES['photo']['tmp_name'], $filePath)) {
                            $photoPath = 'uploads/tankers/' . $fileName;
                        } else {
                            $error = 'Failed to upload photo. Please check server permissions.';
                            error_log("Failed to move uploaded file. Source: " . $_FILES['photo']['tmp_name'] . ", Destination: " . $filePath);
                        }
                    }
                }
            } else {
                // Handle specific upload errors
                $uploadErrors = [
                    UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive.',
                    UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive.',
                    UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
                    UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
                    UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
                    UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                    UPLOAD_ERR_EXTENSION => 'File upload stopped by extension.'
                ];
                $error = 'Photo upload error: ' . ($uploadErrors[$fileError] ?? 'Unknown error (' . $fileError . ')');
                error_log("Photo upload error: " . $fileError . " - " . ($uploadErrors[$fileError] ?? 'Unknown'));
            }
        } else {
            // Check if file was actually uploaded (not just form submitted)
            if (isset($_FILES['photo'])) {
                $fileError = $_FILES['photo']['error'];
                if ($fileError === UPLOAD_ERR_NO_FILE) {
                    $error = 'No photo file was uploaded. Please take a photo before submitting.';
                } else {
                    $uploadErrors = [
                        UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive.',
                        UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive.',
                        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
                        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
                        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                        UPLOAD_ERR_EXTENSION => 'File upload stopped by extension.'
                    ];
                    $error = 'Photo upload error: ' . ($uploadErrors[$fileError] ?? 'Unknown error (' . $fileError . ')');
                }
            } else {
                $error = 'No photo file was received. Please ensure you have taken a photo before submitting.';
            }
            error_log("No photo file in \$_FILES array. \$_FILES contents: " . print_r($_FILES, true) . " POST contents: " . print_r($_POST, true));
        }
        
        // Log photo upload status for debugging
        if (isset($_FILES['photo'])) {
            error_log("Photo upload debug - Error code: " . $_FILES['photo']['error'] . ", Name: " . ($_FILES['photo']['name'] ?? 'none') . ", Size: " . ($_FILES['photo']['size'] ?? 0) . ", Type: " . ($_FILES['photo']['type'] ?? 'none') . ", Tmp name: " . ($_FILES['photo']['tmp_name'] ?? 'none'));
        } else {
            error_log("Photo upload debug - \$_FILES['photo'] is not set at all. POST data: " . print_r($_POST, true));
        }
        
        // Check if this is a photo-related error that we should allow to pass
        $photoError = null;
        if (!empty($error)) {
            $errorLower = strtolower($error);
            if (strpos($errorLower, 'photo') !== false || strpos($errorLower, 'file') !== false) {
                // Log the photo error but don't block submission
                $photoError = $error;
                error_log("Photo upload failed but continuing with report save. Error: " . $error);
                $error = ''; // Clear error so report can be saved
            }
        }
        
        if (empty($error)) {
            try {
                $stmt = $db->prepare("
                    INSERT INTO " . TABLE_PREFIX . "tanker_reports 
                    (reported_by_user_id, registration_number, photo_path, latitude, longitude, address, device_type)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $userId,
                    $registrationNumber,
                    $photoPath,
                    $latitude,
                    $longitude,
                    $address ?: null,
                    $deviceType
                ]);
                
                if (!empty($photoError)) {
                    $success = 'Tanker report submitted successfully, but photo upload failed: ' . $photoError;
                } else {
                    $success = 'Tanker report submitted successfully!';
                }
                
                // Clear form data
                $registrationNumber = '';
                $latitude = '';
                $longitude = '';
                $address = '';
            } catch (PDOException $e) {
                $error = 'Error saving report: ' . $e->getMessage();
                error_log("Tanker report error: " . $e->getMessage());
            }
        }
    }
}

$pageTitle = 'Report Tankers';
include __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="content-area">
        <h1>Report Tankers</h1>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= h($success) ?></div>
        <?php endif; ?>
        
        <div class="auth-container">
            <form method="POST" action="" id="tankerReportForm" enctype="multipart/form-data">
                <input type="hidden" name="device_type" id="device_type" value="desktop">
                <input type="hidden" name="latitude" id="latitude" value="">
                <input type="hidden" name="longitude" id="longitude" value="">
                
                <div class="form-group">
                    <label for="registration_number">Registration Number: <span class="required">*</span></label>
                    <input type="text" id="registration_number" name="registration_number" value="<?= h($registrationNumber ?? '') ?>" required placeholder="e.g., ABC 123 GP" style="text-transform: uppercase;">
                </div>
                
                <!-- Mobile Section -->
                <div id="mobile-section" style="display: none;">
                    <div class="form-group">
                        <label>Take Photo: <span class="required">*</span></label>
                        <!-- File input for mobile - must be directly in form, not in hidden div -->
                        <input type="file" id="camera-photo" name="photo" accept="image/*" capture="environment" style="position: absolute; clip: rect(0,0,0,0); width: 1px; height: 1px; margin: -1px; padding: 0; border: 0; overflow: hidden; opacity: 0;">
                        <button type="button" id="take-photo-btn" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-camera"></i> Take Photo
                        </button>
                        <small style="display: block; margin-top: 5px;">Use your device camera to take a photo of the tanker. GPS location will be captured automatically.</small>
                        <div id="gps-status" style="margin-top: 10px; padding: 10px; border-radius: 4px; display: none;"></div>
                        <div id="camera-preview" style="margin-top: 10px; display: none;">
                            <img id="camera-preview-img" src="" alt="Preview" style="max-width: 100%; max-height: 300px; border: 1px solid #ddd; border-radius: 4px;">
                            <button type="button" id="retake-photo-btn" class="btn btn-secondary" style="margin-top: 10px; width: 100%;">
                                <i class="fas fa-redo"></i> Retake Photo
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Desktop Section -->
                <div id="desktop-section">
                    <div class="form-group">
                        <label for="address-search">Search Address: <span class="required">*</span></label>
                        <input type="text" id="address-search" placeholder="Start typing the address..." autocomplete="off">
                        <div id="address-autocomplete" class="address-autocomplete" style="position: absolute; z-index: 1000; background: white; border: 1px solid #ddd; border-radius: 4px; max-height: 200px; overflow-y: auto; display: none; width: 100%; max-width: 500px;"></div>
                        <small>Start typing the address and select from the suggestions</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Address:</label>
                        <textarea id="address" name="address" rows="3" readonly style="background-color: #f5f5f5;"><?= h($address ?? '') ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="photo-upload">Upload Photo: <span class="required">*</span></label>
                        <input type="file" id="photo-upload" name="photo" accept="image/*">
                        <small>Upload a photo of the tanker</small>
                        <div id="photo-preview" style="margin-top: 10px; display: none;">
                            <img id="photo-preview-img" src="" alt="Preview" style="max-width: 100%; max-height: 300px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Submit Report</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.address-autocomplete {
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    margin-top: 2px;
}

.autocomplete-item {
    padding: 8px 12px;
    cursor: pointer;
    border-bottom: 1px solid #eee;
}

.autocomplete-item:hover,
.autocomplete-item.selected {
    background-color: #f0f0f0;
}

.autocomplete-item:last-child {
    border-bottom: none;
}

.form-group {
    position: relative;
}
</style>

<script>
// Detect if mobile device
function isMobileDevice() {
    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) || 
           (window.innerWidth <= 768 && window.innerHeight <= 1024);
}

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    const isMobile = isMobileDevice();
    const mobileSection = document.getElementById('mobile-section');
    const desktopSection = document.getElementById('desktop-section');
    const deviceTypeInput = document.getElementById('device_type');
    
    if (isMobile) {
        console.log('Mobile device detected, showing mobile section');
        mobileSection.style.display = 'block';
        mobileSection.style.visibility = 'visible';
        desktopSection.style.display = 'none';
        deviceTypeInput.value = 'mobile';
        
        // Setup camera capture
        const cameraPhoto = document.getElementById('camera-photo');
        const takePhotoBtn = document.getElementById('take-photo-btn');
        const retakePhotoBtn = document.getElementById('retake-photo-btn');
        const cameraPreview = document.getElementById('camera-preview');
        const cameraPreviewImg = document.getElementById('camera-preview-img');
        const gpsStatus = document.getElementById('gps-status');
        const form = document.getElementById('tankerReportForm');
        
        // Ensure file input is in the form and properly configured
        // Move it from mobile-section to directly in form for mobile browsers
        if (form && cameraPhoto) {
            // If it's inside mobile-section, move it to form level
            if (cameraPhoto.parentElement && cameraPhoto.parentElement.id === 'mobile-section') {
                // Remove from mobile-section
                cameraPhoto.parentElement.removeChild(cameraPhoto);
                // Insert directly in form after device_type input
                const deviceTypeInput = document.getElementById('device_type');
                if (deviceTypeInput && deviceTypeInput.nextSibling) {
                    form.insertBefore(cameraPhoto, deviceTypeInput.nextSibling);
                } else {
                    form.appendChild(cameraPhoto);
                }
                console.log('File input moved from mobile-section to form level');
            } else if (!form.contains(cameraPhoto)) {
                form.appendChild(cameraPhoto);
                console.log('File input moved into form');
            }
            // Ensure it's properly configured for mobile
            cameraPhoto.setAttribute('name', 'photo');
            cameraPhoto.setAttribute('accept', 'image/*');
            cameraPhoto.setAttribute('capture', 'environment');
            cameraPhoto.disabled = false;
            cameraPhoto.removeAttribute('disabled');
            cameraPhoto.style.display = '';
            cameraPhoto.style.visibility = 'visible';
            cameraPhoto.style.position = 'absolute';
            cameraPhoto.style.clip = 'rect(0,0,0,0)';
            cameraPhoto.style.width = '1px';
            cameraPhoto.style.height = '1px';
            cameraPhoto.style.margin = '-1px';
            cameraPhoto.style.padding = '0';
            cameraPhoto.style.border = '0';
            cameraPhoto.style.overflow = 'hidden';
            cameraPhoto.style.opacity = '0';
        }
        
        // Function to get GPS location
        function getGPSLocation() {
            if (!navigator.geolocation) {
                gpsStatus.innerHTML = '<span style="color: red;">Geolocation is not supported by your browser.</span>';
                gpsStatus.style.display = 'block';
                return;
            }
            
            gpsStatus.innerHTML = '<span style="color: #666;"><i class="fas fa-spinner fa-spin"></i> Capturing GPS location...</span>';
            gpsStatus.style.display = 'block';
            
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    
                    document.getElementById('latitude').value = lat;
                    document.getElementById('longitude').value = lng;
                    
                    gpsStatus.innerHTML = '<span style="color: green;"><i class="fas fa-check-circle"></i> GPS location captured: ' + lat.toFixed(6) + ', ' + lng.toFixed(6) + '</span>';
                },
                function(error) {
                    let errorMsg = 'Error getting location: ';
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            errorMsg += 'Permission denied. Please enable location access.';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            errorMsg += 'Position unavailable.';
                            break;
                        case error.TIMEOUT:
                            errorMsg += 'Request timeout.';
                            break;
                        default:
                            errorMsg += 'Unknown error.';
                            break;
                    }
                    gpsStatus.innerHTML = '<span style="color: red;">' + errorMsg + '</span>';
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                }
            );
        }
        
        // Button click triggers file input
        takePhotoBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Ensure file input is in the form and properly configured
            if (form && !form.contains(cameraPhoto)) {
                form.appendChild(cameraPhoto);
            }
            
            // Ensure the input is accessible and has proper attributes
            cameraPhoto.setAttribute('name', 'photo');
            cameraPhoto.disabled = false;
            cameraPhoto.removeAttribute('disabled');
            cameraPhoto.style.display = '';
            cameraPhoto.style.visibility = 'visible';
            cameraPhoto.style.position = 'absolute';
            cameraPhoto.style.clip = 'rect(0,0,0,0)';
            cameraPhoto.style.width = '1px';
            cameraPhoto.style.height = '1px';
            cameraPhoto.style.margin = '-1px';
            cameraPhoto.style.padding = '0';
            cameraPhoto.style.border = '0';
            cameraPhoto.style.overflow = 'hidden';
            cameraPhoto.style.opacity = '0';
            
            // Force a reflow to ensure browser recognizes the input
            void cameraPhoto.offsetHeight;
            
            // Small delay to ensure input is ready
            setTimeout(function() {
                cameraPhoto.click();
            }, 50);
        });
        
        // Retake photo button
        retakePhotoBtn.addEventListener('click', function() {
            cameraPhoto.value = '';
            cameraPreview.style.display = 'none';
            takePhotoBtn.innerHTML = '<i class="fas fa-camera"></i> Take Photo';
            takePhotoBtn.classList.remove('btn-success');
            takePhotoBtn.classList.add('btn-primary');
            gpsStatus.style.display = 'none';
            document.getElementById('latitude').value = '';
            document.getElementById('longitude').value = '';
        });
        
        // Handle photo capture - automatically get GPS location
        cameraPhoto.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Verify file is valid
                if (file.size === 0) {
                    alert('The photo file appears to be empty. Please try taking the photo again.');
                    cameraPhoto.value = '';
                    return;
                }
                
                // Log file info for debugging
                console.log('Photo captured - Name:', file.name, 'Size:', file.size, 'Type:', file.type);
                
                // Ensure file input is in the form
                if (!cameraPhoto.form || cameraPhoto.form.id !== 'tankerReportForm') {
                    const form = document.getElementById('tankerReportForm');
                    if (form && !form.contains(cameraPhoto)) {
                        form.appendChild(cameraPhoto);
                    }
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    cameraPreviewImg.src = e.target.result;
                    cameraPreview.style.display = 'block';
                    takePhotoBtn.innerHTML = '<i class="fas fa-check-circle"></i> Photo Captured';
                    takePhotoBtn.classList.remove('btn-primary');
                    takePhotoBtn.classList.add('btn-success');
                };
                reader.onerror = function() {
                    alert('Error reading photo file. Please try taking the photo again.');
                    cameraPhoto.value = '';
                };
                reader.readAsDataURL(file);
                
                // Automatically capture GPS location when photo is taken
                // Wait a moment for photo to be processed
                setTimeout(function() {
                    getGPSLocation();
                }, 500);
            } else {
                console.error('No file selected in camera input');
            }
        });
    } else {
        console.log('Desktop device detected, showing desktop section');
        mobileSection.style.display = 'none';
        desktopSection.style.display = 'block';
        deviceTypeInput.value = 'desktop';
        
        // Hide mobile file input on desktop
        const cameraPhoto = document.getElementById('camera-photo');
        if (cameraPhoto) {
            cameraPhoto.style.display = 'none';
            // Remove name attribute so it's not submitted with desktop form
            cameraPhoto.removeAttribute('name');
        }
        
        // Setup address autocomplete
        const addressSearch = document.getElementById('address-search');
        const autocompleteDiv = document.getElementById('address-autocomplete');
        const addressInput = document.getElementById('address');
        const apiUrl = '<?= baseUrl('/api/address-autocomplete.php') ?>';
        let autocompleteTimeout = null;
        let selectedIndex = -1;
        let suggestions = [];
        
        let hideTimeout = null;
        let isInputFocused = false;
        
        addressSearch.addEventListener('focus', function() {
            isInputFocused = true;
            clearTimeout(hideTimeout);
            const query = this.value.trim();
            if (query.length >= 3 && suggestions.length > 0) {
                autocompleteDiv.style.display = 'block';
            }
        });
        
        addressSearch.addEventListener('blur', function() {
            isInputFocused = false;
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
        
        autocompleteDiv.addEventListener('mouseenter', function() {
            clearTimeout(hideTimeout);
        });
        
        autocompleteDiv.addEventListener('mouseleave', function() {
            if (!isInputFocused) {
                hideTimeout = setTimeout(function() {
                    autocompleteDiv.style.display = 'none';
                }, 200);
            }
        });
        
        addressSearch.addEventListener('input', function() {
            const query = this.value.trim();
            clearTimeout(hideTimeout);
            clearTimeout(autocompleteTimeout);
            
            if (query.length < 3) {
                autocompleteDiv.style.display = 'none';
                suggestions = [];
                return;
            }
            
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
                    e.preventDefault();
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
            clearTimeout(hideTimeout);
            
            // Geocode the selected address
            fetch('<?= baseUrl('/api/geocode-address.php') ?>?address=' + encodeURIComponent(suggestion.display_name))
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.latitude && data.longitude) {
                        document.getElementById('latitude').value = data.latitude;
                        document.getElementById('longitude').value = data.longitude;
                        addressInput.value = suggestion.display_name;
                        addressSearch.value = '';
                        autocompleteDiv.style.display = 'none';
                    } else {
                        alert('Error geocoding address. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Error geocoding:', error);
                    alert('Error geocoding address. Please try again.');
                });
        }
        
        // Setup photo preview
        const photoUpload = document.getElementById('photo-upload');
        const photoPreview = document.getElementById('photo-preview');
        const photoPreviewImg = document.getElementById('photo-preview-img');
        
        photoUpload.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    photoPreviewImg.src = e.target.result;
                    photoPreview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    // Uppercase registration number
    const registrationInput = document.getElementById('registration_number');
    registrationInput.addEventListener('input', function() {
        this.value = this.value.toUpperCase();
    });
    
    // Form validation and submission
    const form = document.getElementById('tankerReportForm');
    form.addEventListener('submit', function(e) {
        let isValid = true;
        let errorMessage = '';
        
        if (isMobile) {
            const cameraPhoto = document.getElementById('camera-photo');
            // Check if file is selected
            if (!cameraPhoto || !cameraPhoto.files || cameraPhoto.files.length === 0) {
                isValid = false;
                errorMessage = 'Please take a photo of the tanker before submitting.';
            } else {
                // Verify file exists and has size
                const file = cameraPhoto.files[0];
                if (!file || file.size === 0) {
                    isValid = false;
                    errorMessage = 'The photo file appears to be empty. Please take the photo again.';
                }
            }
            
            const latitude = document.getElementById('latitude').value;
            const longitude = document.getElementById('longitude').value;
            if (isValid && (!latitude || !longitude)) {
                isValid = false;
                errorMessage = 'GPS location is required. Please wait a moment for GPS to be captured after taking the photo, then try again.';
            }
        } else {
            // Desktop validation
            const photoUpload = document.getElementById('photo-upload');
            if (!photoUpload.files || photoUpload.files.length === 0) {
                isValid = false;
                errorMessage = 'Please upload a photo of the tanker before submitting.';
            }
            
            const latitude = document.getElementById('latitude').value;
            const longitude = document.getElementById('longitude').value;
            if (isValid && (!latitude || !longitude)) {
                isValid = false;
                errorMessage = 'Please search for and select an address to get GPS coordinates.';
            }
        }
        
        // Validate registration number
        const registrationNumber = document.getElementById('registration_number').value.trim();
        if (isValid && !registrationNumber) {
            isValid = false;
            errorMessage = 'Please enter the registration number.';
        }
        
        if (!isValid) {
            e.preventDefault();
            e.stopPropagation();
            alert(errorMessage);
            return false;
        }
        
        // Double-check file is present before submitting (especially for mobile)
        if (isMobile) {
            const cameraPhoto = document.getElementById('camera-photo');
            const form = document.getElementById('tankerReportForm');
            
            if (cameraPhoto && cameraPhoto.files && cameraPhoto.files.length > 0) {
                const file = cameraPhoto.files[0];
                console.log('Submitting form with file - Name:', file.name, 'Size:', file.size, 'Type:', file.type);
                
                // CRITICAL: For mobile, use FormData to manually construct the submission
                // This ensures the file is included even if the browser doesn't recognize the hidden input
                e.preventDefault(); // Prevent default form submission
                
                // Create FormData manually
                const formData = new FormData();
                formData.append('photo', file);
                formData.append('device_type', document.getElementById('device_type').value);
                formData.append('latitude', document.getElementById('latitude').value);
                formData.append('longitude', document.getElementById('longitude').value);
                formData.append('registration_number', document.getElementById('registration_number').value);
                
                // Show loading state
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
                }
                
                // Submit via fetch
                const submitUrl = form.action || window.location.href;
                console.log('Submitting to:', submitUrl);
                
                fetch(submitUrl, {
                    method: 'POST',
                    body: formData,
                    redirect: 'follow' // Follow redirects
                })
                .then(response => {
                    console.log('Response status:', response.status, 'Redirected:', response.redirected);
                    if (response.redirected) {
                        // Server redirected (likely success), reload page
                        window.location.href = response.url;
                        return;
                    }
                    // If no redirect, get the response HTML
                    return response.text();
                })
                .then(html => {
                    if (html) {
                        // Replace page content with response (for error messages, etc.)
                        document.open();
                        document.write(html);
                        document.close();
                    }
                })
                .catch(error => {
                    console.error('Submission error:', error);
                    alert('Error submitting form: ' + error.message + '. Please try again.');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = 'Submit Report';
                    }
                });
                
                return false; // Prevent default submission
            } else {
                console.error('No file found in camera input before submission');
                e.preventDefault();
                alert('Please take a photo before submitting.');
                return false;
            }
        }
        
        // Show loading state
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
            
            // Re-enable after 10 seconds in case of error
            setTimeout(function() {
                if (submitBtn.disabled) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            }, 10000);
        }
        
        // Allow form to submit normally if validation passes
        return true;
    });
});
</script>

<?php 
include __DIR__ . '/includes/footer.php'; 
?>
