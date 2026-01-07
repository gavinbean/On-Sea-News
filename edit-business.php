<?php
require_once 'includes/functions.php';
requireLogin();

$db = getDB();
$userId = getCurrentUserId();
$businessId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$error = '';

// Get business and verify ownership
$stmt = $db->prepare("
    SELECT b.*, c.category_name
    FROM " . TABLE_PREFIX . "businesses b
    JOIN " . TABLE_PREFIX . "business_categories c ON b.category_id = c.category_id
    WHERE b.business_id = ? AND b.user_id = ?
");
$stmt->execute([$businessId, $userId]);
$business = $stmt->fetch();

// Build address from components if address field is empty but components exist
if ($business && (empty($business['address']) || trim($business['address']) === '')) {
    $addressParts = [];
    if (!empty($business['building_name'])) $addressParts[] = $business['building_name'];
    if (!empty($business['street_number'])) $addressParts[] = $business['street_number'];
    if (!empty($business['street_name'])) $addressParts[] = $business['street_name'];
    if (!empty($business['suburb'])) $addressParts[] = $business['suburb'];
    if (!empty($business['town'])) $addressParts[] = $business['town'];
    if (!empty($addressParts)) {
        $business['address'] = implode(', ', $addressParts);
    }
}

if (!$business) {
    redirect('/my-businesses.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $businessName = $_POST['business_name'] ?? '';
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $contactName = $_POST['contact_name'] ?? '';
    $telephone = $_POST['telephone'] ?? '';
    $email = $_POST['email'] ?? '';
    $buildingName = $_POST['building_name'] ?? '';
    $streetNumber = $_POST['street_number'] ?? '';
    $streetName = $_POST['street_name'] ?? '';
    $suburb = $_POST['suburb'] ?? '';
    $town = $_POST['town'] ?? '';
    $website = $_POST['website'] ?? '';
    $description = $_POST['description'] ?? '';
    
    // Build comma-separated address for display
    $addressParts = [];
    if (!empty($buildingName)) $addressParts[] = $buildingName;
    if (!empty($streetNumber)) $addressParts[] = $streetNumber;
    if (!empty($streetName)) $addressParts[] = $streetName;
    if (!empty($suburb)) $addressParts[] = $suburb;
    if (!empty($town)) $addressParts[] = $town;
    $address = implode(', ', $addressParts);
    
    if (empty($businessName) || empty($categoryId) || empty($telephone)) {
        $error = 'Business name, category, and telephone are required.';
    } else {
        // Geocode address
        require_once __DIR__ . '/includes/geocoding.php';
        $geocodeResult = validateAndGeocodeAddress([
            'street_number' => $streetNumber,
            'street_name' => $streetName,
            'suburb' => $suburb,
            'town' => $town
        ]);
        
        $latitude = null;
        $longitude = null;
        if ($geocodeResult['success']) {
            $latitude = $geocodeResult['latitude'] ?? null;
            $longitude = $geocodeResult['longitude'] ?? null;
        }
        
        // Edits do not require re-approval - keep existing approval status
        $stmt = $db->prepare("
            UPDATE " . TABLE_PREFIX . "businesses 
            SET category_id = ?, business_name = ?, contact_name = ?, telephone = ?, 
                email = ?, address = ?, building_name = ?, street_number = ?, street_name = ?, suburb = ?, town = ?, latitude = ?, longitude = ?, website = ?, description = ?
            WHERE business_id = ? AND user_id = ?
        ");
        $stmt->execute([$categoryId, $businessName, $contactName, $telephone, $email, $address, $buildingName, $streetNumber, $streetName, $suburb, $town, $latitude, $longitude, $website, $description, $businessId, $userId]);
        $message = 'Business updated successfully!';
        
        // Reload business data
        $stmt = $db->prepare("
            SELECT b.*, c.category_name
            FROM " . TABLE_PREFIX . "businesses b
            JOIN " . TABLE_PREFIX . "business_categories c ON b.category_id = c.category_id
            WHERE b.business_id = ? AND b.user_id = ?
        ");
        $stmt->execute([$businessId, $userId]);
        $business = $stmt->fetch();
        
        // Build address from components if address field is empty but components exist
        if ($business && (empty($business['address']) || trim($business['address']) === '')) {
            $addressParts = [];
            if (!empty($business['building_name'])) $addressParts[] = $business['building_name'];
            if (!empty($business['street_number'])) $addressParts[] = $business['street_number'];
            if (!empty($business['street_name'])) $addressParts[] = $business['street_name'];
            if (!empty($business['suburb'])) $addressParts[] = $business['suburb'];
            if (!empty($business['town'])) $addressParts[] = $business['town'];
            if (!empty($addressParts)) {
                $business['address'] = implode(', ', $addressParts);
            }
        }
    }
}

// Get all categories
$stmt = $db->query("SELECT * FROM " . TABLE_PREFIX . "business_categories ORDER BY category_name");
$categories = $stmt->fetchAll();

$pageTitle = 'Edit Business';
include 'includes/header.php';
?>

<div class="container">
    <div class="content-area">
        <h1>Edit Business</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= h($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        
        
        <div class="business-form">
            <form method="POST" action="">
                <input type="hidden" name="action" value="update">
                
                <div class="form-group">
                    <label for="business_name">Business Name: <span class="required">*</span></label>
                    <input type="text" id="business_name" name="business_name" value="<?= h($business['business_name']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="category_id">Category: <span class="required">*</span></label>
                    <select id="category_id" name="category_id" required>
                        <option value="">Select a category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['category_id'] ?>" <?= $cat['category_id'] == $business['category_id'] ? 'selected' : '' ?>>
                                <?= h($cat['category_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="contact_name">Contact Name:</label>
                    <input type="text" id="contact_name" name="contact_name" value="<?= h($business['contact_name'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="telephone">Telephone: <span class="required">*</span></label>
                    <input type="tel" id="telephone" name="telephone" value="<?= h($business['telephone']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" value="<?= h($business['email'] ?? '') ?>">
                </div>
                
                <div class="form-group" style="position: relative;">
                    <label>Address:</label>
                    <input type="text" id="address-search" placeholder="Start typing your address..." autocomplete="off">
                    <div id="address-autocomplete" class="address-autocomplete"></div>
                    <small>Start typing your address and select from the suggestions</small>
                </div>
                
                <div class="form-group">
                    <label for="building_name">Building Name:</label>
                    <input type="text" id="building_name" name="building_name" value="<?= h($business['building_name'] ?? '') ?>" autocomplete="off">
                </div>
                
                <div class="form-group">
                    <label for="street_number">Street Number:</label>
                    <input type="text" id="street_number" name="street_number" value="<?= h($business['street_number'] ?? '') ?>" autocomplete="off">
                </div>
                
                <div class="form-group">
                    <label for="street_name">Street Name:</label>
                    <input type="text" id="street_name" name="street_name" value="<?= h($business['street_name'] ?? '') ?>" autocomplete="off">
                </div>
                
                <div class="form-group">
                    <label for="suburb">Suburb:</label>
                    <input type="text" id="suburb" name="suburb" value="<?= h($business['suburb'] ?? '') ?>" autocomplete="off">
                </div>
                
                <div class="form-group">
                    <label for="town">Town:</label>
                    <input type="text" id="town" name="town" value="<?= h($business['town'] ?? '') ?>" autocomplete="off">
                </div>
                
                <div class="form-group">
                    <label for="website">Website:</label>
                    <input type="url" id="website" name="website" value="<?= h($business['website'] ?? '') ?>" placeholder="https://">
                </div>
                
                <div class="form-group">
                    <label for="description">Description:</label>
                    <textarea id="description" name="description" rows="4"><?= h($business['description'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Update Business</button>
                    <a href="<?= baseUrl('/my-businesses.php') ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Address autocomplete functionality (same as register.php)
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
        
        addressSearch.value = '';
        autocompleteDiv.style.display = 'none';
        selectedIndex = -1;
        suggestions = [];
        
        if (streetNameInput && !streetNameInput.value) {
            streetNameInput.focus();
        } else if (townInput && !townInput.value) {
            townInput.focus();
        }
    }
})();
</script>

<?php 
$hideAdverts = false;
include 'includes/footer.php'; 
?>

