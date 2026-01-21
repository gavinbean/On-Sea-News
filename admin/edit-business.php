<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('ADMIN');

$db = getDB();
$businessId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$error = '';

// Get business (admin can edit any business) with owner information
$stmt = $db->prepare("
    SELECT b.*, c.category_name, u.name as owner_name, u.surname as owner_surname, u.username as owner_username
    FROM " . TABLE_PREFIX . "businesses b
    JOIN " . TABLE_PREFIX . "business_categories c ON b.category_id = c.category_id
    LEFT JOIN " . TABLE_PREFIX . "users u ON b.user_id = u.user_id
    WHERE b.business_id = ?
");
$stmt->execute([$businessId]);
$business = $stmt->fetch();

if (!$business) {
    redirect('/admin/manage-businesses.php');
    exit;
}

// Build address from components if address field is empty but components exist
if (empty($business['address']) || trim($business['address']) === '') {
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $businessName = trim($_POST['business_name'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $contactName = trim($_POST['contact_name'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $description = trim($_POST['description'] ?? '');
        $isApproved = isset($_POST['is_approved']) ? 1 : 0;
        
        // Owner selection (only if business doesn't have an owner)
        $selectedUserId = null;
        if (empty($business['user_id']) && !empty($_POST['user_id'])) {
            $selectedUserId = (int)$_POST['user_id'];
            // Verify user exists
            $userCheck = $db->prepare("SELECT user_id FROM " . TABLE_PREFIX . "users WHERE user_id = ?");
            $userCheck->execute([$selectedUserId]);
            if (!$userCheck->fetch()) {
                $selectedUserId = null;
            }
        }
        
        // Address components
    $buildingName = trim($_POST['building_name'] ?? '');
    $streetNumber = trim($_POST['street_number'] ?? '');
    $streetName = trim($_POST['street_name'] ?? '');
    $suburb = trim($_POST['suburb'] ?? '');
    $town = trim($_POST['town'] ?? '');
    
    if (empty($businessName)) {
        $error = 'Business name is required.';
    } elseif ($categoryId <= 0) {
        $error = 'Please select a category.';
    } else {
        // Geocode address if components provided
        $latitude = $business['latitude'];
        $longitude = $business['longitude'];
        
        if (!empty($streetName) && !empty($town)) {
            require_once __DIR__ . '/../includes/geocoding.php';
            $geocodeResult = validateAndGeocodeAddress([
                'street_number' => $streetNumber,
                'street_name' => $streetName,
                'suburb' => $suburb,
                'town' => $town
            ]);
            if ($geocodeResult && isset($geocodeResult['success']) && $geocodeResult['success']) {
                $latitude = $geocodeResult['latitude'] ?? $latitude;
                $longitude = $geocodeResult['longitude'] ?? $longitude;
            }
        }
        
        // Build full address string
        $addressParts = [];
        if (!empty($buildingName)) $addressParts[] = $buildingName;
        if (!empty($streetNumber)) $addressParts[] = $streetNumber;
        if (!empty($streetName)) $addressParts[] = $streetName;
        if (!empty($suburb)) $addressParts[] = $suburb;
        if (!empty($town)) $addressParts[] = $town;
        $address = implode(', ', $addressParts);
        
        // Build update query - include user_id if setting an owner
        if ($selectedUserId !== null) {
            $stmt = $db->prepare("
                UPDATE " . TABLE_PREFIX . "businesses 
                SET business_name = ?, category_id = ?, contact_name = ?, telephone = ?, 
                    email = ?, website = ?, description = ?, is_approved = ?,
                    building_name = ?, street_number = ?, street_name = ?, suburb = ?, town = ?,
                    address = ?, latitude = ?, longitude = ?, user_id = ?
                WHERE business_id = ?
            ");
            $stmt->execute([
                $businessName, $categoryId, $contactName, $telephone, $email, $website, 
                $description, $isApproved, $buildingName, $streetNumber, $streetName, $suburb, $town,
                $address, $latitude, $longitude, $selectedUserId, $businessId
            ]);
        } else {
            $stmt = $db->prepare("
                UPDATE " . TABLE_PREFIX . "businesses 
                SET business_name = ?, category_id = ?, contact_name = ?, telephone = ?, 
                    email = ?, website = ?, description = ?, is_approved = ?,
                    building_name = ?, street_number = ?, street_name = ?, suburb = ?, town = ?,
                    address = ?, latitude = ?, longitude = ?
                WHERE business_id = ?
            ");
            $stmt->execute([
                $businessName, $categoryId, $contactName, $telephone, $email, $website, 
                $description, $isApproved, $buildingName, $streetNumber, $streetName, $suburb, $town,
                $address, $latitude, $longitude, $businessId
            ]);
        }
        
        $message = 'Business updated successfully.';
        
        // Reload business with owner information
        $stmt = $db->prepare("
            SELECT b.*, c.category_name, u.name as owner_name, u.surname as owner_surname, u.username as owner_username
            FROM " . TABLE_PREFIX . "businesses b
            JOIN " . TABLE_PREFIX . "business_categories c ON b.category_id = c.category_id
            LEFT JOIN " . TABLE_PREFIX . "users u ON b.user_id = u.user_id
            WHERE b.business_id = ?
        ");
        $stmt->execute([$businessId]);
        $business = $stmt->fetch();
        
        // Rebuild address
        if (empty($business['address']) || trim($business['address']) === '') {
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

// Get all users for owner selection (only if business doesn't have an owner)
$users = [];
if (empty($business['user_id'])) {
    $stmt = $db->query("
        SELECT user_id, username, name, surname, email 
        FROM " . TABLE_PREFIX . "users 
        WHERE is_active = 1 
        ORDER BY name, surname, username
    ");
    $users = $stmt->fetchAll();
}

$pageTitle = 'Edit Business';
include __DIR__ . '/../includes/header.php';
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
        
        <form method="POST" action="" id="businessForm">
            <input type="hidden" name="action" value="update">
            
            <div class="form-group">
                <label for="business_name">Business Name: <span class="required">*</span></label>
                <input type="text" id="business_name" name="business_name" value="<?= h($business['business_name']) ?>" required>
            </div>
            
            <div class="form-group">
                <label for="category_id">Category: <span class="required">*</span></label>
                <select id="category_id" name="category_id" required>
                    <option value="">Select a category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['category_id'] ?>" <?= $business['category_id'] == $category['category_id'] ? 'selected' : '' ?>>
                            <?= h($category['category_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="contact_name">Contact Name:</label>
                <input type="text" id="contact_name" name="contact_name" value="<?= h($business['contact_name'] ?: '') ?>">
            </div>
            
            <div class="form-group">
                <label for="telephone">Telephone:</label>
                <input type="text" id="telephone" name="telephone" value="<?= h($business['telephone'] ?: '') ?>">
            </div>
            
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?= h($business['email'] ?: '') ?>">
            </div>
            
            <div class="form-group">
                <label for="website">Website:</label>
                <input type="url" id="website" name="website" value="<?= h($business['website'] ?: '') ?>" placeholder="https://">
            </div>
            
            <div class="form-group">
                <label for="description">Description:</label>
                <textarea id="description" name="description" rows="5"><?= h($business['description'] ?: '') ?></textarea>
            </div>
            
            <h3>Owner</h3>
            
            <?php if (!empty($business['user_id']) && !empty($business['owner_name'])): ?>
                <!-- Show owner as link if business has an owner -->
                <div class="form-group">
                    <label>Current Owner:</label>
                    <div style="padding: 0.75rem; background-color: #f5f5f5; border-radius: 4px; border-left: 3px solid var(--primary-color);">
                        <a href="<?= baseUrl('/admin/edit-user.php?id=' . $business['user_id']) ?>" 
                           style="text-decoration: none; color: var(--primary-color); font-weight: 600; font-size: 1.1rem;">
                            <?= h($business['owner_name'] . ' ' . ($business['owner_surname'] ?? '')) ?>
                        </a>
                        <?php if (!empty($business['owner_username'])): ?>
                            <span style="color: #666; margin-left: 0.5rem;">(<?= h($business['owner_username']) ?>)</span>
                        <?php endif; ?>
                    </div>
                    <small style="display: block; margin-top: 0.25rem; color: #666;">
                        Click the name to edit the owner's profile
                    </small>
                </div>
            <?php else: ?>
                <!-- Show owner selection dropdown if business doesn't have an owner -->
                <div class="form-group">
                    <label for="user_id">Select Owner:</label>
                    <select id="user_id" name="user_id" style="width: 100%;">
                        <option value="">-- No Owner (Imported Business) --</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= $user['user_id'] ?>">
                                <?= h($user['name'] . ' ' . ($user['surname'] ?? '') . ' (' . $user['username'] . ') - ' . $user['email']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="display: block; margin-top: 0.25rem; color: #666;">
                        Select a user to assign as the owner of this business. Leave as "No Owner" for imported businesses.
                    </small>
                </div>
            <?php endif; ?>
            
            <h3>Address</h3>
            
            <div class="form-group">
                <label for="address-search">Search Address:</label>
                <input type="text" id="address-search" name="address-search" placeholder="Start typing an address...">
                <div id="address-suggestions" class="address-suggestions"></div>
            </div>
            
            <div class="form-group">
                <label for="building_name">Building Name:</label>
                <input type="text" id="building_name" name="building_name" value="<?= h($business['building_name'] ?: '') ?>">
            </div>
            
            <div class="form-group">
                <label for="street_number">Street Number:</label>
                <input type="text" id="street_number" name="street_number" value="<?= h($business['street_number'] ?: '') ?>">
            </div>
            
            <div class="form-group">
                <label for="street_name">Street Name:</label>
                <input type="text" id="street_name" name="street_name" value="<?= h($business['street_name'] ?: '') ?>">
            </div>
            
            <div class="form-group">
                <label for="suburb">Suburb:</label>
                <input type="text" id="suburb" name="suburb" value="<?= h($business['suburb'] ?: '') ?>">
            </div>
            
            <div class="form-group">
                <label for="town">Town:</label>
                <input type="text" id="town" name="town" value="<?= h($business['town'] ?: '') ?>">
            </div>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" id="is_approved" name="is_approved" value="1" <?= $business['is_approved'] ? 'checked' : '' ?>>
                    Approved (visible on website)
                </label>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Update Business</button>
                <a href="<?= baseUrl('/admin/manage-businesses.php') ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
// Address autocomplete functionality (same as register.php)
let addressTimeout;
let isInputFocused = false;

document.getElementById('address-search').addEventListener('input', function(e) {
    const query = e.target.value.trim();
    clearTimeout(addressTimeout);
    
    if (query.length < 3) {
        document.getElementById('address-suggestions').style.display = 'none';
        return;
    }
    
    addressTimeout = setTimeout(() => {
        fetchAddressSuggestions(query);
    }, 300);
});

document.getElementById('address-search').addEventListener('focus', function() {
    isInputFocused = true;
});

document.getElementById('address-search').addEventListener('blur', function() {
    isInputFocused = false;
    setTimeout(() => {
        if (!isInputFocused) {
            document.getElementById('address-suggestions').style.display = 'none';
        }
    }, 200);
});

function fetchAddressSuggestions(query) {
    fetch('<?= baseUrl('/api/address-autocomplete.php') ?>?q=' + encodeURIComponent(query))
        .then(response => response.json())
        .then(data => {
            if (data.success && data.suggestions && data.suggestions.length > 0) {
                displaySuggestions(data.suggestions);
            } else {
                document.getElementById('address-suggestions').style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Error fetching address suggestions:', error);
            document.getElementById('address-suggestions').style.display = 'none';
        });
}

function displaySuggestions(suggestions) {
    const container = document.getElementById('address-suggestions');
    container.innerHTML = '';
    
    suggestions.forEach(suggestion => {
        const div = document.createElement('div');
        div.className = 'address-suggestion';
        div.textContent = suggestion.display_name;
        div.addEventListener('mousedown', (e) => {
            e.preventDefault();
            selectSuggestion(suggestion);
        });
        container.appendChild(div);
    });
    
    container.style.display = 'block';
}

function selectSuggestion(suggestion) {
    // Extract street number from query if present
    const searchQuery = document.getElementById('address-search').value.trim();
    const streetNumberMatch = searchQuery.match(/^(\d+)\s+/);
    const extractedStreetNumber = streetNumberMatch ? streetNumberMatch[1] : '';
    
    document.getElementById('street_number').value = suggestion.street_number || extractedStreetNumber || '';
    document.getElementById('street_name').value = suggestion.street_name || '';
    document.getElementById('suburb').value = suggestion.suburb || '';
    document.getElementById('town').value = suggestion.town || '';
    
    document.getElementById('address-search').value = '';
    document.getElementById('address-suggestions').style.display = 'none';
}
</script>

<?php 
$hideAdverts = true;
include __DIR__ . '/../includes/footer.php'; 
?>

