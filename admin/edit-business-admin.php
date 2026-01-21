<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('ADMIN');

$db = getDB();
$businessId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$error = '';

// Check for message/error from GET parameters (from redirects)
if (isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
}
if (isset($_GET['error'])) {
    $error = urldecode($_GET['error']);
}

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
    redirect('/admin/my-businesses-admin.php');
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

// Get pricing options
$stmt = $db->query("
    SELECT * FROM " . TABLE_PREFIX . "business_pricing_options 
    WHERE is_active = 1 
    ORDER BY display_order
");
$pricingOptions = $stmt->fetchAll();

// Handle pricing form submission (separate form)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_pricing' && isset($_POST['pricing_status']) && isset($_POST['business_id'])) {
    $businessId = (int)($_POST['business_id'] ?? 0);
    $pricingStatus = trim($_POST['pricing_status'] ?? 'free');
    
    if ($businessId > 0) {
        // Get current pricing status before update
        $stmt = $db->prepare("SELECT pricing_status FROM " . TABLE_PREFIX . "businesses WHERE business_id = ?");
        $stmt->execute([$businessId]);
        $currentBusiness = $stmt->fetch();
        $oldPricingStatus = $currentBusiness['pricing_status'] ?? 'free';
        
        // Validate pricing status
        $validStatuses = array_column($pricingOptions, 'option_slug');
        if (!in_array($pricingStatus, $validStatuses)) {
            $pricingStatus = 'free';
        }
        
        // Check if pricing status changed
        $pricingChanged = ($oldPricingStatus !== $pricingStatus);
        
        // Update pricing status
        $stmt = $db->prepare("UPDATE " . TABLE_PREFIX . "businesses SET pricing_status = ? WHERE business_id = ?");
        $stmt->execute([$pricingStatus, $businessId]);
        
        // If pricing changed, deactivate all adverts
        if ($pricingChanged) {
            $stmt = $db->prepare("UPDATE " . TABLE_PREFIX . "business_adverts SET is_active = 0 WHERE business_id = ?");
            $stmt->execute([$businessId]);
            
            // Get count of deactivated adverts
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM " . TABLE_PREFIX . "business_adverts WHERE business_id = ?");
            $stmt->execute([$businessId]);
            $advertCount = $stmt->fetch()['count'];
            
            $message = 'Pricing option updated successfully. ';
            if ($advertCount > 0) {
                $message .= "Warning: All {$advertCount} advert(s) have been set to inactive. You can reactivate them individually if needed.";
            } else {
                $message .= "All adverts have been set to inactive.";
            }
        } else {
            $message = 'Pricing option updated successfully.';
        }
        
        // Reload business
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

// Handle business form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $businessName = trim($_POST['business_name'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $contactName = trim($_POST['contact_name'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $isApproved = isset($_POST['is_approved']) ? 1 : 0;
    $pricingStatus = trim($_POST['pricing_status'] ?? 'free');
    
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
        // Get current pricing status before update
        $oldPricingStatus = $business['pricing_status'] ?? 'free';
        
        // Validate pricing status
        $validStatuses = array_column($pricingOptions, 'option_slug');
        if (!in_array($pricingStatus, $validStatuses)) {
            $pricingStatus = 'free';
        }
        
        // Check if pricing status changed
        $pricingChanged = ($oldPricingStatus !== $pricingStatus);
        
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
        
        $stmt = $db->prepare("
            UPDATE " . TABLE_PREFIX . "businesses 
            SET business_name = ?, category_id = ?, contact_name = ?, telephone = ?, 
                email = ?, website = ?, description = ?, is_approved = ?,
                building_name = ?, street_number = ?, street_name = ?, suburb = ?, town = ?,
                address = ?, latitude = ?, longitude = ?, pricing_status = ?
            WHERE business_id = ?
        ");
        $stmt->execute([
            $businessName, $categoryId, $contactName, $telephone, $email, $website, 
            $description, $isApproved, $buildingName, $streetNumber, $streetName, $suburb, $town,
            $address, $latitude, $longitude, $pricingStatus, $businessId
        ]);
        
        // If pricing changed, deactivate all adverts
        if ($pricingChanged) {
            $stmt = $db->prepare("UPDATE " . TABLE_PREFIX . "business_adverts SET is_active = 0 WHERE business_id = ?");
            $stmt->execute([$businessId]);
            
            // Get count of deactivated adverts
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM " . TABLE_PREFIX . "business_adverts WHERE business_id = ?");
            $stmt->execute([$businessId]);
            $advertCount = $stmt->fetch()['count'];
            
            $message = 'Business updated successfully. ';
            if ($advertCount > 0) {
                $message .= "Warning: All {$advertCount} advert(s) have been set to inactive. You can reactivate them individually if needed.";
            } else {
                $message .= "All adverts have been set to inactive.";
            }
        } else {
            $message = 'Business updated successfully.';
        }
        
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
        
        <!-- Pricing Option Section -->
        <div class="pricing-option-section" style="background: white; border-radius: 8px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border-left: 4px solid var(--primary-color);">
            <h2 style="margin-top: 0; color: var(--primary-color);">Pricing Option</h2>
            
            <form method="POST" action="" id="pricingForm" style="display: inline;">
                <input type="hidden" name="action" value="update_pricing">
                <input type="hidden" name="business_id" value="<?= $businessId ?>">
                
                <div class="form-group">
                    <label for="pricing_status" style="font-weight: 600; margin-bottom: 0.5rem; display: block;">Current Pricing Model:</label>
                    <select id="pricing_status" name="pricing_status" style="width: 100%; max-width: 400px; padding: 0.75rem; font-size: 1rem; border: 1px solid var(--border-color); border-radius: 4px;">
                        <?php foreach ($pricingOptions as $option): ?>
                            <option value="<?= h($option['option_slug']) ?>" 
                                    <?= ($business['pricing_status'] ?? 'free') === $option['option_slug'] ? 'selected' : '' ?>>
                                <?= h($option['option_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="display: block; margin-top: 0.5rem; color: #666;">
                        Select a different pricing model for this business. Changes will be saved when you update the business.
                    </small>
                </div>
                <input type="hidden" name="business_id" value="<?= $businessId ?>">
                <button type="submit" class="btn btn-primary" style="margin-top: 1rem;">Update Pricing Option</button>
                
                <div id="option-details-container" style="margin-top: 1rem; padding: 1rem; background-color: #f5f5f5; border-radius: 4px;">
                    <strong>Option Details:</strong>
                    <div id="option-details-content" style="margin-top: 0.5rem; color: #666;">
                        <?php 
                        // Show description of current pricing option
                        $currentOption = null;
                        foreach ($pricingOptions as $option) {
                            if (($business['pricing_status'] ?? 'free') === $option['option_slug']) {
                                $currentOption = $option;
                                break;
                            }
                        }
                        if ($currentOption && !empty($currentOption['description'])) {
                            // Display HTML content from TinyMCE (not escaped)
                            echo $currentOption['description'];
                        } else {
                            echo '<em>No description available</em>';
                        }
                        ?>
                    </div>
                </div>
                
                <!-- Store pricing options data for JavaScript -->
                <script>
                const pricingOptionsData = <?= json_encode(array_map(function($opt) {
                    return [
                        'slug' => $opt['option_slug'],
                        'name' => $opt['option_name'],
                        'description' => $opt['description'] ?? ''
                    ];
                }, $pricingOptions)) ?>;
                </script>
            </form>
        </div>
        
        <!-- Tabs -->
        <div class="tab-navigation" style="margin-bottom: 1rem; border-bottom: 2px solid var(--border-color); display: flex; gap: 1rem; justify-content: flex-start;">
            <button type="button" class="tab-button active" data-tab="edit-business-details">Business Details</button>
            <button type="button" class="tab-button" data-tab="edit-advert-graphics" id="edit-advert-graphics-tab-btn" style="display: none;">Advert Graphics</button>
        </div>
        
        <!-- Business Details Tab -->
        <div id="edit-business-details-tab" class="tab-content active">
            <form method="POST" action="" id="businessForm">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="pricing_status" id="pricing_status_hidden" value="<?= h($business['pricing_status'] ?? 'free') ?>">
                
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
            
            <h3>Address</h3>
            
            <div class="form-group" style="position: relative;">
                <label for="address-search">Search Address:</label>
                <input type="text" id="address-search" name="address-search" placeholder="Start typing an address..." value="">
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
                    <a href="<?= baseUrl('/admin/my-businesses-admin.php') ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
        
        <!-- Advert Graphics Tab -->
        <div id="edit-advert-graphics-tab" class="tab-content" style="display: none;">
            <h2>Advert Graphics</h2>
            <div id="edit-advert-graphics-content">
                <p>Loading...</p>
            </div>
        </div>
    </div>
</div>

<script>
// Sync pricing status from the pricing section to the main form and update description
document.getElementById('pricing_status').addEventListener('change', function() {
    const selectedSlug = this.value;
    document.getElementById('pricing_status_hidden').value = selectedSlug;
    
    // Find the selected option in the pricing options data
    const selectedOption = pricingOptionsData.find(opt => opt.slug === selectedSlug);
    
    // Update the description display
    const detailsContainer = document.getElementById('option-details-content');
    if (selectedOption && selectedOption.description) {
        // Display HTML content (not escaped)
        detailsContainer.innerHTML = selectedOption.description;
    } else {
        detailsContainer.innerHTML = '<em>No description available</em>';
    }
    
    // Update Advert Graphics tab visibility based on pricing option
    updateEditAdvertGraphicsTab(selectedSlug);
});

// Address autocomplete functionality
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

// Tab functionality for edit form
document.addEventListener('DOMContentLoaded', function() {
    // Handle tab clicks
    document.querySelectorAll('.tab-button').forEach(button => {
        button.addEventListener('click', function() {
            const tabName = this.getAttribute('data-tab');
            switchEditTab(tabName);
        });
    });
    
    // Show/hide advert graphics tab based on pricing option (only on page load and form submission)
    const pricingSelect = document.getElementById('pricing_status');
    if (pricingSelect) {
        // Initialize on page load
        const initialPricing = pricingSelect.value;
        // Initialize tab (hide for free, show for paid options)
        updateEditAdvertGraphicsTab(initialPricing);
        
        // Update Advert Graphics tab when pricing form is submitted (Update Pricing Option button)
        const pricingForm = document.getElementById('pricingForm');
        if (pricingForm) {
            pricingForm.addEventListener('submit', function(e) {
                // Update tab immediately before form submission for better UX
                // The page will reload after submission, so this is just for immediate feedback
                updateEditAdvertGraphicsTab(pricingSelect.value);
            });
        }
    }
    
    // Reload adverts after form submission (if redirected back)
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('message') || urlParams.get('error')) {
        const pricingStatus = pricingSelect ? pricingSelect.value : 'free';
        const businessId = <?= $businessId ?>;
        setTimeout(() => {
            loadExistingAdverts(businessId, pricingStatus);
        }, 500);
    }
    
    // Check for tab parameter in URL and switch to that tab
    const tabParam = urlParams.get('tab');
    if (tabParam === 'advert-graphics') {
        // Switch to advert graphics tab
        switchEditTab('edit-advert-graphics');
    }
});

function switchEditTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
        tab.style.display = 'none';
    });
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('active');
    });
    
    const selectedTab = document.getElementById(tabName + '-tab');
    const selectedButton = document.querySelector('[data-tab="' + tabName + '"]');
    
    if (selectedTab) {
        selectedTab.classList.add('active');
        selectedTab.style.display = 'block';
    }
    if (selectedButton) {
        selectedButton.classList.add('active');
    }
}

function updateEditAdvertGraphicsTab(pricingStatus) {
    const tabButton = document.getElementById('edit-advert-graphics-tab-btn');
    const tabContent = document.getElementById('edit-advert-graphics-tab');
    
    // Hide tab for free pricing option
    if (pricingStatus === 'free') {
        if (tabButton) tabButton.style.display = 'none';
        if (tabContent) {
            tabContent.style.display = 'none';
            tabContent.classList.remove('active');
        }
        // If advert graphics tab is active and we're switching to free, switch to business details
        if (tabContent && tabContent.classList.contains('active')) {
            switchEditTab('edit-business-details');
        }
    } else {
        // Show tab for paid pricing options
        if (tabButton) tabButton.style.display = 'inline-block';
        
        // Reset loaded flag so content reloads when pricing changes
        const contentDiv = document.getElementById('edit-advert-graphics-content');
        if (contentDiv) {
            contentDiv.dataset.loaded = 'false';
        }
        
        // Load content for paid pricing options
        loadEditAdvertGraphicsContent(pricingStatus);
    }
}

function loadEditAdvertGraphicsContent(pricingStatus) {
    const contentDiv = document.getElementById('edit-advert-graphics-content');
    if (!contentDiv || contentDiv.dataset.loaded === 'true') return;
    
    let formHTML = '';
    const businessId = <?= $businessId ?>;
    
    if (pricingStatus === 'free' || pricingStatus === 'basic') {
        formHTML = '<button type="button" id="edit-add-basic-advert-btn" class="btn btn-primary" onclick="showAddBasicAdvertForm(\'edit\')">Add New Advert</button>' +
        '<div id="edit-basic-advert-list"></div>' +
        '<div id="edit-basic-advert-form-container" style="display: none; margin-top: 1rem; padding: 1rem; background: #f5f5f5; border-radius: 8px;">' +
            '<h4>Add Basic Advert</h4>' +
            '<form id="edit-basic-advert-form" enctype="multipart/form-data" action="/handle-business-adverts.php" method="POST">' +
                '<input type="hidden" name="action" value="create">' +
                '<input type="hidden" name="business_id" value="' + businessId + '">' +
                '<input type="hidden" name="redirect_url" value="/admin/edit-business-admin.php?id=' + businessId + '">' +
                '<div class="form-group"><label>Banner Image: <span class="required">*</span></label>' +
                '<input type="file" name="banner_image" accept="image/*" required></div>' +
                '<div class="form-group"><label>Display Image: <span class="required">*</span></label>' +
                '<input type="file" name="display_image" accept="image/*" required></div>' +
                '<div class="form-group"><button type="submit" class="btn btn-primary">Save Advert</button> ' +
                '<button type="button" class="btn btn-secondary" onclick="hideAddBasicAdvertForm(\'edit\')">Cancel</button></div>' +
            '</form></div>';
    } else if (pricingStatus === 'timed') {
        formHTML = '<button type="button" class="btn btn-primary" onclick="showAddTimedAdvertForm(\'edit\')">Add New Advert</button>' +
        '<div id="edit-timed-advert-list"></div>' +
        '<div id="edit-timed-advert-form-container" style="display: none; margin-top: 1rem; padding: 1rem; background: #f5f5f5; border-radius: 8px;">' +
            '<h4>Add Timed Advert</h4>' +
            '<form id="edit-timed-advert-form" enctype="multipart/form-data" action="/handle-business-adverts.php" method="POST">' +
                '<input type="hidden" name="action" value="create">' +
                '<input type="hidden" name="business_id" value="' + businessId + '">' +
                '<input type="hidden" name="redirect_url" value="/admin/edit-business-admin.php?id=' + businessId + '">' +
                '<div class="form-group"><label>Banner Image: <span class="required">*</span></label>' +
                '<input type="file" name="banner_image" accept="image/*" required></div>' +
                '<div class="form-group"><label>Display Image: <span class="required">*</span></label>' +
                '<input type="file" name="display_image" accept="image/*" required></div>' +
                '<div class="form-group"><label>Start Date: <span class="required">*</span></label>' +
                '<input type="date" name="start_date" required></div>' +
                '<div class="form-group"><label>End Date: <span class="required">*</span></label>' +
                '<input type="date" name="end_date" required></div>' +
                '<div class="form-group"><button type="submit" class="btn btn-primary">Save Advert</button> ' +
                '<button type="button" class="btn btn-secondary" onclick="hideAddTimedAdvertForm(\'edit\')">Cancel</button></div>' +
            '</form></div>';
    } else if (pricingStatus === 'events') {
        formHTML = '<button type="button" class="btn btn-primary" onclick="showAddEventsAdvertForm(\'edit\')">Add New Advert</button>' +
        '<div id="edit-events-advert-list"></div>' +
        '<div id="edit-events-advert-form-container" style="display: none; margin-top: 1rem; padding: 1rem; background: #f5f5f5; border-radius: 8px;">' +
            '<h4>Add Event Advert</h4>' +
            '<form id="edit-events-advert-form" enctype="multipart/form-data" action="/handle-business-adverts.php" method="POST">' +
                '<input type="hidden" name="action" value="create">' +
                '<input type="hidden" name="business_id" value="' + businessId + '">' +
                '<input type="hidden" name="redirect_url" value="/admin/edit-business-admin.php?id=' + businessId + '">' +
                '<div class="form-group"><label>Banner Image: <span class="required">*</span></label>' +
                '<input type="file" name="banner_image" accept="image/*" required></div>' +
                '<div class="form-group"><label>Display Image: <span class="required">*</span></label>' +
                '<input type="file" name="display_image" accept="image/*" required></div>' +
                '<div class="form-group"><label>Start Date:</label>' +
                '<input type="date" name="start_date"></div>' +
                '<small style="display: block; margin-top: -0.5rem; margin-bottom: 1rem; color: #666;">Optional - Leave blank for always active</small>' +
                '<div class="form-group"><label>End Date:</label>' +
                '<input type="date" name="end_date"></div>' +
                '<small style="display: block; margin-top: -0.5rem; margin-bottom: 1rem; color: #666;">Optional - Leave blank for always active</small>' +
                '<div class="form-group"><label>Event Date:</label>' +
                '<input type="date" name="event_date"></div>' +
                '<small style="display: block; margin-top: -0.5rem; margin-bottom: 1rem; color: #666;">Optional - Only adverts with an event date will appear in the calendar</small>' +
                '<div class="form-group"><label>Event Title: <span class="required">*</span></label>' +
                '<input type="text" name="event_title" required></div>' +
                '<div class="form-group"><button type="submit" class="btn btn-primary">Save Advert</button> ' +
                '<button type="button" class="btn btn-secondary" onclick="hideAddEventsAdvertForm(\'edit\')">Cancel</button></div>' +
            '</form></div>';
    }
    
    contentDiv.innerHTML = formHTML;
    contentDiv.dataset.loaded = 'true';
    
    // Load existing adverts after form is created
    loadExistingAdverts(businessId, pricingStatus);
}

function loadExistingAdverts(businessId, pricingStatus) {
    fetch('<?= baseUrl('/api/business-adverts.php') ?>?action=list&business_id=' + businessId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.adverts) {
                // Show all adverts, don't filter by type
                displayAdvertsList(data.adverts, pricingStatus, businessId);
            }
        })
        .catch(error => {
            console.error('Error loading adverts:', error);
        });
}

function displayAdvertsList(adverts, pricingStatus, businessId) {
    // Find the correct list container based on pricing status
    let listContainer;
    if (pricingStatus === 'free' || pricingStatus === 'basic') {
        listContainer = document.getElementById('edit-basic-advert-list');
    } else if (pricingStatus === 'timed') {
        listContainer = document.getElementById('edit-timed-advert-list');
    } else if (pricingStatus === 'events') {
        listContainer = document.getElementById('edit-events-advert-list');
    }
    
    if (!listContainer) return;
    
    // For free option, only show 1 active record and mark others as inactive
    if (pricingStatus === 'free') {
        const activeAdverts = adverts.filter(ad => ad.is_active == 1 || ad.is_active === '1');
        if (activeAdverts.length > 1) {
            // Mark all but the first as inactive
            const advertsToDeactivate = activeAdverts.slice(1);
            advertsToDeactivate.forEach(ad => {
                deactivateAdvert(ad.advert_id);
            });
            // Update the adverts array
            adverts = adverts.map(ad => {
                if (advertsToDeactivate.find(a => a.advert_id === ad.advert_id)) {
                    ad.is_active = 0;
                }
                return ad;
            });
        }
        // Only show the first active advert (or all if none are active)
        const activeCount = adverts.filter(ad => ad.is_active == 1 || ad.is_active === '1').length;
        if (activeCount > 0) {
            adverts = adverts.filter(ad => ad.is_active == 1 || ad.is_active === '1').slice(0, 1);
        }
    }
    
    let html = '<div style="margin-top: 2rem;"><h4>Existing Adverts</h4>';
    
    if (adverts.length === 0) {
        html += '<p style="margin-top: 1rem; color: #666;">No adverts found.</p>';
        html += '</div>';
        listContainer.innerHTML = html;
        return;
    }
    
    html += '<table class="data-table" style="margin-top: 1rem;">';
    html += '<thead><tr>';
    html += '<th>Banner</th>';
    html += '<th>Display</th>';
    // Show date columns based on pricing status
    if (pricingStatus === 'timed' || pricingStatus === 'events') {
        html += '<th>Start Date</th>';
        html += '<th>End Date</th>';
    }
    if (pricingStatus === 'events') {
        html += '<th>Event Date</th>';
        html += '<th>Event Title</th>';
    }
    html += '<th>Status</th>';
    html += '<th>Approval</th>';
    html += '<th>Actions</th>';
    html += '</tr></thead><tbody>';
    
    adverts.forEach(advert => {
        // Normalize paths: replace old 'uploads/adverts/' with 'uploads/graphics/'
        let bannerPath = (advert.banner_image || '').replace(/uploads\/adverts\//g, 'uploads/graphics/');
        let displayPath = (advert.display_image || '').replace(/uploads\/adverts\//g, 'uploads/graphics/');
        
        // Construct full URLs - ensure no double slashes
        const baseUrl = '<?= baseUrl('/') ?>';
        const bannerUrl = bannerPath ? (baseUrl + (bannerPath.startsWith('/') ? bannerPath.substring(1) : bannerPath)).replace(/\/+/g, '/') : '';
        const displayUrl = displayPath ? (baseUrl + (displayPath.startsWith('/') ? displayPath.substring(1) : displayPath)).replace(/\/+/g, '/') : '';
        
        // Debug logging
        console.log('Advert ID:', advert.advert_id);
        console.log('Banner path from DB:', bannerPath);
        console.log('Banner URL:', bannerUrl);
        console.log('Display path from DB:', displayPath);
        console.log('Display URL:', displayUrl);
        
        html += '<tr>';
        html += '<td>';
        if (bannerUrl && bannerPath) {
            const escapedBannerUrl = bannerUrl.replace(/'/g, "\\'");
            // Use data-src for lazy loading and add a fallback
            html += '<div style="position: relative;">';
            html += '<img src="' + bannerUrl + '" data-src="' + bannerUrl + '" style="max-width: 100px; max-height: 50px; object-fit: contain; cursor: pointer; border: 1px solid #ddd; padding: 2px; background: #f5f5f5; display: block;" onclick="showImagePreview(\'' + escapedBannerUrl + '\', \'Banner Image\')" title="Click to preview" onerror="this.onerror=null; this.src=\'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'100\' height=\'50\'%3E%3Ctext x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dy=\'.3em\' fill=\'%23999\'%3EImage blocked%3C/text%3E%3C/svg%3E\'; this.nextElementSibling.style.display=\'inline\';">';
            html += '<span style="color: #999; display: none; font-size: 10px;">Image blocked or not found</span>';
            html += '</div>';
        } else {
            html += '<span style="color: #999;">No image</span>';
        }
        html += '</td>';
        html += '<td>';
        if (displayUrl && displayPath) {
            const escapedDisplayUrl = displayUrl.replace(/'/g, "\\'");
            html += '<div style="position: relative;">';
            html += '<img src="' + displayUrl + '" data-src="' + displayUrl + '" style="max-width: 100px; max-height: 50px; object-fit: contain; cursor: pointer; border: 1px solid #ddd; padding: 2px; background: #f5f5f5; display: block;" onclick="showImagePreview(\'' + escapedDisplayUrl + '\', \'Display Image\')" title="Click to preview" onerror="this.onerror=null; this.src=\'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'100\' height=\'50\'%3E%3Ctext x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dy=\'.3em\' fill=\'%23999\'%3EImage blocked%3C/text%3E%3C/svg%3E\'; this.nextElementSibling.style.display=\'inline\';">';
            html += '<span style="color: #999; display: none; font-size: 10px;">Image blocked or not found</span>';
            html += '</div>';
        } else {
            html += '<span style="color: #999;">No image</span>';
        }
        html += '</td>';
        // Show date columns based on pricing status
        if (pricingStatus === 'timed' || pricingStatus === 'events') {
            html += '<td>' + (advert.start_date || '-') + '</td>';
            html += '<td>' + (advert.end_date || '-') + '</td>';
        }
        if (pricingStatus === 'events') {
            html += '<td>' + (advert.event_date || '-') + '</td>';
            html += '<td>' + (advert.event_title || '-') + '</td>';
        }
        html += '<td>' + (advert.is_active ? '<span style="color: green;">Active</span>' : '<span style="color: red;">Inactive</span>') + '</td>';
        // Approval Status
        html += '<td>';
        const approvalStatus = advert.approval_status || 'pending';
        if (approvalStatus === 'approved') {
            html += '<span style="background-color: #d4edda; color: #155724; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.875rem; font-weight: 600;">✓ Approved</span>';
        } else if (approvalStatus === 'rejected') {
            html += '<span style="background-color: #f8d7da; color: #721c24; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.875rem; font-weight: 600;">✗ Rejected</span>';
            if (advert.rejection_reason) {
                const reason = (advert.rejection_reason || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                html += '<br><small style="color: #666; margin-top: 0.25rem; display: block;">Reason: ' + reason.substring(0, 50) + (reason.length > 50 ? '...' : '') + '</small>';
            }
        } else {
            html += '<span style="background-color: #fff3cd; color: #856404; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.875rem; font-weight: 600;">⏳ Pending</span>';
        }
        html += '</td>';
        html += '<td>';
        html += '<button type="button" class="btn btn-sm btn-primary" onclick="editAdvert(' + advert.advert_id + ', \'' + pricingStatus + '\')">Edit</button> ';
        html += '<button type="button" class="btn btn-sm btn-danger" onclick="deleteAdvert(' + advert.advert_id + ')">Delete</button>';
        html += '</td>';
        html += '</tr>';
    });
    
    html += '</tbody></table></div>';
    listContainer.innerHTML = html;
    
    // For Basic option, disable the "Add New Advert" button if there's already an advert
    if (pricingStatus === 'basic' && adverts.length > 0) {
        const addButton = document.getElementById('edit-add-basic-advert-btn');
        if (addButton) {
            addButton.disabled = true;
            addButton.style.opacity = '0.5';
            addButton.style.cursor = 'not-allowed';
        }
    } else if (pricingStatus === 'basic' && adverts.length === 0) {
        const addButton = document.getElementById('edit-add-basic-advert-btn');
        if (addButton) {
            addButton.disabled = false;
            addButton.style.opacity = '1';
            addButton.style.cursor = 'pointer';
        }
    }
}

function editAdvert(advertId, pricingStatus) {
    // Get current pricing status from the dropdown if not provided
    if (!pricingStatus) {
        const pricingSelect = document.getElementById('pricing_status');
        pricingStatus = pricingSelect ? pricingSelect.value : 'free';
    }
    
    fetch('<?= baseUrl('/api/business-adverts.php') ?>?action=get&advert_id=' + advertId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.advert) {
                const advert = data.advert;
                showEditAdvertForm(advert, pricingStatus);
            }
        })
        .catch(error => {
            console.error('Error loading advert:', error);
            alert('Error loading advert details');
        });
}

function showEditAdvertForm(advert, pricingStatus) {
    const businessId = <?= $businessId ?>;
    let formHTML = '';
    const formId = 'edit-' + pricingStatus + '-advert-edit-form';
    const containerId = 'edit-' + pricingStatus + '-advert-edit-container';
    
    if (pricingStatus === 'basic') {
        const baseUrl = '<?= baseUrl('/') ?>';
        // Normalize paths: replace old 'uploads/adverts/' with 'uploads/graphics/'
        let bannerPath = (advert.banner_image || '').replace(/uploads\/adverts\//g, 'uploads/graphics/');
        let displayPath = (advert.display_image || '').replace(/uploads\/adverts\//g, 'uploads/graphics/');
        const bannerUrl = bannerPath ? (baseUrl + (bannerPath.startsWith('/') ? bannerPath.substring(1) : bannerPath)).replace(/\/+/g, '/') : '';
        const displayUrl = displayPath ? (baseUrl + (displayPath.startsWith('/') ? displayPath.substring(1) : displayPath)).replace(/\/+/g, '/') : '';
        const bannerImg = bannerUrl ? '<img src="' + bannerUrl + '" style="max-width: 200px; max-height: 100px; margin-bottom: 0.5rem; cursor: pointer; border: 1px solid #ddd; padding: 2px;" onclick="showImagePreview(\'' + bannerUrl.replace(/'/g, "\\'") + '\', \'Banner Image\')" title="Click to preview" onerror="console.error(\'Banner image failed to load:\', this.src);">' : '<span style="color: #999;">No image</span>';
        const displayImg = displayUrl ? '<img src="' + displayUrl + '" style="max-width: 200px; max-height: 100px; margin-bottom: 0.5rem; cursor: pointer; border: 1px solid #ddd; padding: 2px;" onclick="showImagePreview(\'' + displayUrl.replace(/'/g, "\\'") + '\', \'Display Image\')" title="Click to preview" onerror="console.error(\'Display image failed to load:\', this.src);">' : '<span style="color: #999;">No image</span>';
        formHTML = '<div id="' + containerId + '" style="margin-top: 1rem; padding: 1rem; background: #f5f5f5; border-radius: 8px;">' +
            '<h4>Edit Basic Advert</h4>' +
            '<form id="' + formId + '" enctype="multipart/form-data" action="/handle-business-adverts.php" method="POST">' +
                '<input type="hidden" name="action" value="update">' +
                '<input type="hidden" name="advert_id" value="' + advert.advert_id + '">' +
                '<input type="hidden" name="business_id" value="' + businessId + '">' +
                '<input type="hidden" name="redirect_url" value="/admin/edit-business-admin.php?id=' + businessId + '">' +
                '<div class="form-group"><label>Current Banner Image:</label><br>' + bannerImg + '<br>' +
                '<label>New Banner Image:</label>' +
                '<input type="file" name="banner_image" accept="image/*"></div>' +
                '<div class="form-group"><label>Current Display Image:</label><br>' + displayImg + '<br>' +
                '<label>New Display Image:</label>' +
                '<input type="file" name="display_image" accept="image/*"></div>' +
                '<div class="form-group"><label><input type="checkbox" name="is_active" value="1" ' + (advert.is_active == 1 ? 'checked' : '') + '> Active (Display this advert)</label></div>' +
                '<div class="form-group"><button type="submit" class="btn btn-primary">Update Advert</button> ' +
                '<button type="button" class="btn btn-secondary" onclick="closeEditAdvertForm(\'' + containerId + '\')">Cancel</button></div>' +
            '</form></div>';
    } else if (pricingStatus === 'timed') {
        const baseUrl = '<?= baseUrl('/') ?>';
        // Normalize paths: replace old 'uploads/adverts/' with 'uploads/graphics/'
        let bannerPath = (advert.banner_image || '').replace(/uploads\/adverts\//g, 'uploads/graphics/');
        let displayPath = (advert.display_image || '').replace(/uploads\/adverts\//g, 'uploads/graphics/');
        const bannerUrl = bannerPath ? (baseUrl + (bannerPath.startsWith('/') ? bannerPath.substring(1) : bannerPath)) : '';
        const displayUrl = displayPath ? (baseUrl + (displayPath.startsWith('/') ? displayPath.substring(1) : displayPath)) : '';
        console.log('Timed advert - Banner URL:', bannerUrl, 'Display URL:', displayUrl);
        const bannerImg = bannerUrl ? '<img src="' + bannerUrl + '" style="max-width: 200px; max-height: 100px; margin-bottom: 0.5rem; cursor: pointer; border: 1px solid #ddd; padding: 2px;" onclick="showImagePreview(\'' + bannerUrl.replace(/'/g, "\\'") + '\', \'Banner Image\')" title="Click to preview" onerror="console.error(\'Banner image failed to load:\', this.src);">' : '<span style="color: #999;">No image</span>';
        const displayImg = displayUrl ? '<img src="' + displayUrl + '" style="max-width: 200px; max-height: 100px; margin-bottom: 0.5rem; cursor: pointer; border: 1px solid #ddd; padding: 2px;" onclick="showImagePreview(\'' + displayUrl.replace(/'/g, "\\'") + '\', \'Display Image\')" title="Click to preview" onerror="console.error(\'Display image failed to load:\', this.src);">' : '<span style="color: #999;">No image</span>';
        formHTML = '<div id="' + containerId + '" style="margin-top: 1rem; padding: 1rem; background: #f5f5f5; border-radius: 8px;">' +
            '<h4>Edit Timed Advert</h4>' +
            '<form id="' + formId + '" enctype="multipart/form-data" action="/handle-business-adverts.php" method="POST">' +
                '<input type="hidden" name="action" value="update">' +
                '<input type="hidden" name="advert_id" value="' + advert.advert_id + '">' +
                '<input type="hidden" name="business_id" value="' + businessId + '">' +
                '<input type="hidden" name="redirect_url" value="/admin/edit-business-admin.php?id=' + businessId + '">' +
                '<div class="form-group"><label>Current Banner Image:</label><br>' + bannerImg + '<br>' +
                '<label>New Banner Image:</label>' +
                '<input type="file" name="banner_image" accept="image/*"></div>' +
                '<div class="form-group"><label>Current Display Image:</label><br>' + displayImg + '<br>' +
                '<label>New Display Image:</label>' +
                '<input type="file" name="display_image" accept="image/*"></div>' +
                '<div class="form-group"><label>Start Date:</label>' +
                '<input type="date" name="start_date" value="' + (advert.start_date || '') + '"></div>' +
                '<small style="display: block; margin-top: -0.5rem; margin-bottom: 1rem; color: #666;">Optional - Leave blank for always active</small>' +
                '<div class="form-group"><label>End Date:</label>' +
                '<input type="date" name="end_date" value="' + (advert.end_date || '') + '"></div>' +
                '<small style="display: block; margin-top: -0.5rem; margin-bottom: 1rem; color: #666;">Optional - Leave blank for always active</small>' +
                '<div class="form-group"><label><input type="checkbox" name="is_active" value="1" ' + (advert.is_active == 1 ? 'checked' : '') + '> Active (Display this advert)</label></div>' +
                '<div class="form-group"><button type="submit" class="btn btn-primary">Update Advert</button> ' +
                '<button type="button" class="btn btn-secondary" onclick="closeEditAdvertForm(\'' + containerId + '\')">Cancel</button></div>' +
            '</form></div>';
    } else if (pricingStatus === 'events') {
        const baseUrl = '<?= baseUrl('/') ?>';
        // Normalize paths: replace old 'uploads/adverts/' with 'uploads/graphics/'
        let bannerPath = (advert.banner_image || '').replace(/uploads\/adverts\//g, 'uploads/graphics/');
        let displayPath = (advert.display_image || '').replace(/uploads\/adverts\//g, 'uploads/graphics/');
        const bannerUrl = bannerPath ? (baseUrl + (bannerPath.startsWith('/') ? bannerPath.substring(1) : bannerPath)).replace(/\/+/g, '/') : '';
        const displayUrl = displayPath ? (baseUrl + (displayPath.startsWith('/') ? displayPath.substring(1) : displayPath)).replace(/\/+/g, '/') : '';
        const bannerImg = bannerUrl ? '<img src="' + bannerUrl + '" style="max-width: 200px; max-height: 100px; margin-bottom: 0.5rem; cursor: pointer; border: 1px solid #ddd; padding: 2px;" onclick="showImagePreview(\'' + bannerUrl.replace(/'/g, "\\'") + '\', \'Banner Image\')" title="Click to preview" onerror="console.error(\'Banner image failed to load:\', this.src);">' : '<span style="color: #999;">No image</span>';
        const displayImg = displayUrl ? '<img src="' + displayUrl + '" style="max-width: 200px; max-height: 100px; margin-bottom: 0.5rem; cursor: pointer; border: 1px solid #ddd; padding: 2px;" onclick="showImagePreview(\'' + displayUrl.replace(/'/g, "\\'") + '\', \'Display Image\')" title="Click to preview" onerror="console.error(\'Display image failed to load:\', this.src);">' : '<span style="color: #999;">No image</span>';
        formHTML = '<div id="' + containerId + '" style="margin-top: 1rem; padding: 1rem; background: #f5f5f5; border-radius: 8px;">' +
            '<h4>Edit Event Advert</h4>' +
            '<form id="' + formId + '" enctype="multipart/form-data" action="/handle-business-adverts.php" method="POST">' +
                '<input type="hidden" name="action" value="update">' +
                '<input type="hidden" name="advert_id" value="' + advert.advert_id + '">' +
                '<input type="hidden" name="business_id" value="' + businessId + '">' +
                '<input type="hidden" name="redirect_url" value="/admin/edit-business-admin.php?id=' + businessId + '">' +
                '<div class="form-group"><label>Current Banner Image:</label><br>' + bannerImg + '<br>' +
                '<label>New Banner Image:</label>' +
                '<input type="file" name="banner_image" accept="image/*"></div>' +
                '<div class="form-group"><label>Current Display Image:</label><br>' + displayImg + '<br>' +
                '<label>New Display Image:</label>' +
                '<input type="file" name="display_image" accept="image/*"></div>' +
                '<div class="form-group"><label>Start Date:</label>' +
                '<input type="date" name="start_date" value="' + (advert.start_date || '') + '"></div>' +
                '<small style="display: block; margin-top: -0.5rem; margin-bottom: 1rem; color: #666;">Optional - Leave blank for always active</small>' +
                '<div class="form-group"><label>End Date:</label>' +
                '<input type="date" name="end_date" value="' + (advert.end_date || '') + '"></div>' +
                '<small style="display: block; margin-top: -0.5rem; margin-bottom: 1rem; color: #666;">Optional - Leave blank for always active</small>' +
                '<div class="form-group"><label>Event Date:</label>' +
                '<input type="date" name="event_date" value="' + (advert.event_date || '') + '"></div>' +
                '<small style="display: block; margin-top: -0.5rem; margin-bottom: 1rem; color: #666;">Optional - Only adverts with an event date will appear in the calendar</small>' +
                '<div class="form-group"><label>Event Title: <span class="required">*</span></label>' +
                '<input type="text" name="event_title" value="' + (advert.event_title || '') + '" required></div>' +
                '<div class="form-group"><label><input type="checkbox" name="is_active" value="1" ' + (advert.is_active == 1 ? 'checked' : '') + '> Active (Display this advert)</label></div>' +
                '<div class="form-group"><button type="submit" class="btn btn-primary">Update Advert</button> ' +
                '<button type="button" class="btn btn-secondary" onclick="closeEditAdvertForm(\'' + containerId + '\')">Cancel</button></div>' +
            '</form></div>';
    }
    
    // Remove any existing edit form
    const existingContainer = document.getElementById(containerId);
    if (existingContainer) {
        existingContainer.remove();
    }
    
    // Add new edit form
    let listContainer;
    if (pricingStatus === 'basic') {
        listContainer = document.getElementById('edit-basic-advert-list');
    } else if (pricingStatus === 'timed') {
        listContainer = document.getElementById('edit-timed-advert-list');
    } else if (pricingStatus === 'events') {
        listContainer = document.getElementById('edit-events-advert-list');
    }
    
    if (listContainer) {
        listContainer.insertAdjacentHTML('afterend', formHTML);
        // Scroll to form
        const newContainer = document.getElementById(containerId);
        if (newContainer) {
            newContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }
}

function closeEditAdvertForm(containerId) {
    const container = document.getElementById(containerId);
    if (container) {
        container.remove();
    }
}

function deleteAdvert(advertId) {
    if (!confirm('Are you sure you want to delete this advert?')) {
        return;
    }
    
    // Create a form and submit it, just like category deletion
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/handle-business-adverts.php';
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'delete';
    form.appendChild(actionInput);
    
    const advertIdInput = document.createElement('input');
    advertIdInput.type = 'hidden';
    advertIdInput.name = 'advert_id';
    advertIdInput.value = advertId;
    form.appendChild(advertIdInput);
    
    const redirectInput = document.createElement('input');
    redirectInput.type = 'hidden';
    redirectInput.name = 'redirect_url';
    redirectInput.value = window.location.href;
    form.appendChild(redirectInput);
    
    document.body.appendChild(form);
    form.submit();
}

function showImagePreview(imageUrl, title) {
    // Create modal overlay
    const modal = document.createElement('div');
    modal.id = 'image-preview-modal';
    modal.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 10000; display: flex; align-items: center; justify-content: center; cursor: pointer;';
    modal.onclick = function() { document.body.removeChild(modal); };
    
    // Create image container
    const container = document.createElement('div');
    container.style.cssText = 'max-width: 90%; max-height: 90%; position: relative; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);';
    container.onclick = function(e) { e.stopPropagation(); };
    
    // Create title
    const titleEl = document.createElement('h3');
    titleEl.textContent = title;
    titleEl.style.cssText = 'margin: 0 0 15px 0; color: #333;';
    container.appendChild(titleEl);
    
    // Create image
    const img = document.createElement('img');
    img.src = imageUrl;
    img.style.cssText = 'max-width: 100%; max-height: 70vh; object-fit: contain; display: block;';
    img.onerror = function() {
        img.alt = 'Image not found';
        img.style.cssText = 'width: 300px; height: 200px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #999;';
    };
    container.appendChild(img);
    
    // Create close button
    const closeBtn = document.createElement('button');
    closeBtn.textContent = '×';
    closeBtn.style.cssText = 'position: absolute; top: 10px; right: 10px; background: #f44336; color: white; border: none; width: 30px; height: 30px; border-radius: 50%; cursor: pointer; font-size: 20px; line-height: 1;';
    closeBtn.onclick = function() { document.body.removeChild(modal); };
    container.appendChild(closeBtn);
    
    modal.appendChild(container);
    document.body.appendChild(modal);
    
    // Close on Escape key
    const escapeHandler = function(e) {
        if (e.key === 'Escape') {
            document.body.removeChild(modal);
            document.removeEventListener('keydown', escapeHandler);
        }
    };
    document.addEventListener('keydown', escapeHandler);
}

function showAddBasicAdvertForm(containerId) {
    if (containerId === 'edit') {
        const form = document.getElementById('edit-basic-advert-form-container');
        if (form) form.style.display = 'block';
    } else {
        const prefix = containerId === 'create-business-container' ? 'create_' : '';
        const form = document.getElementById(prefix + 'basic-advert-form-container');
        if (form) form.style.display = 'block';
    }
}

function hideAddBasicAdvertForm(containerId) {
    if (containerId === 'edit') {
        const form = document.getElementById('edit-basic-advert-form-container');
        if (form) form.style.display = 'none';
    } else {
        const prefix = containerId === 'create-business-container' ? 'create_' : '';
        const form = document.getElementById(prefix + 'basic-advert-form-container');
        if (form) form.style.display = 'none';
    }
}

function showAddTimedAdvertForm(containerId) {
    if (containerId === 'edit') {
        const form = document.getElementById('edit-timed-advert-form-container');
        if (form) form.style.display = 'block';
    } else {
        const prefix = containerId === 'create-business-container' ? 'create_' : '';
        const form = document.getElementById(prefix + 'timed-advert-form-container');
        if (form) form.style.display = 'block';
    }
}

function hideAddTimedAdvertForm(containerId) {
    if (containerId === 'edit') {
        const form = document.getElementById('edit-timed-advert-form-container');
        if (form) form.style.display = 'none';
    } else {
        const prefix = containerId === 'create-business-container' ? 'create_' : '';
        const form = document.getElementById(prefix + 'timed-advert-form-container');
        if (form) form.style.display = 'none';
    }
}

function showAddEventsAdvertForm(containerId) {
    if (containerId === 'edit') {
        const form = document.getElementById('edit-events-advert-form-container');
        if (form) form.style.display = 'block';
    } else {
        const prefix = containerId === 'create-business-container' ? 'create_' : '';
        const form = document.getElementById(prefix + 'events-advert-form-container');
        if (form) form.style.display = 'block';
    }
}

function hideAddEventsAdvertForm(containerId) {
    if (containerId === 'edit') {
        const form = document.getElementById('edit-events-advert-form-container');
        if (form) form.style.display = 'none';
    } else {
        const prefix = containerId === 'create-business-container' ? 'create_' : '';
        const form = document.getElementById(prefix + 'events-advert-form-container');
        if (form) form.style.display = 'none';
    }
}

function deactivateAdvert(advertId) {
    // Deactivate advert by updating is_active to 0
    const formData = new FormData();
    formData.append('action', 'update_status');
    formData.append('advert_id', advertId);
    formData.append('is_active', '0');
    
    fetch('<?= baseUrl('/handle-business-adverts.php') ?>', {
        method: 'POST',
        body: formData
    }).catch(error => {
        console.error('Error deactivating advert:', error);
    });
}
</script>

<?php 
$hideAdverts = true;
include __DIR__ . '/../includes/footer.php'; 
?>
