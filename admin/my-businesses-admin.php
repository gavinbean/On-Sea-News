<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('ADMIN');

$db = getDB();
$message = '';
$error = '';
$userId = getCurrentUserId();

// Handle form submission for new business
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $businessName = trim($_POST['business_name'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $contactName = trim($_POST['contact_name'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $website = trim($_POST['website'] ?? '');
        $description = trim($_POST['description'] ?? '');
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
            // Geocode address if components provided
            $latitude = null;
            $longitude = null;
            
            if (!empty($streetName) && !empty($town)) {
                require_once __DIR__ . '/../includes/geocoding.php';
                $geocodeResult = validateAndGeocodeAddress([
                    'street_number' => $streetNumber,
                    'street_name' => $streetName,
                    'suburb' => $suburb,
                    'town' => $town
                ]);
                if ($geocodeResult && isset($geocodeResult['success']) && $geocodeResult['success']) {
                    $latitude = $geocodeResult['latitude'] ?? null;
                    $longitude = $geocodeResult['longitude'] ?? null;
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
            
            // Insert business with Free status
            $stmt = $db->prepare("
                INSERT INTO " . TABLE_PREFIX . "businesses 
                (user_id, category_id, business_name, contact_name, telephone, email, website, description,
                 building_name, street_number, street_name, suburb, town, address, latitude, longitude,
                 pricing_status, is_approved)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([
                $userId, $categoryId, $businessName, $contactName, $telephone, $email, $website, $description,
                $buildingName, $streetNumber, $streetName, $suburb, $town, $address, $latitude, $longitude,
                $pricingStatus
            ]);
            
            $message = 'Business created successfully!';
        }
    }
}

// Get user's businesses
$stmt = $db->prepare("
    SELECT b.*, c.category_name
    FROM " . TABLE_PREFIX . "businesses b
    JOIN " . TABLE_PREFIX . "business_categories c ON b.category_id = c.category_id
    WHERE b.user_id = ?
    ORDER BY b.created_at DESC
");
$stmt->execute([$userId]);
$businesses = $stmt->fetchAll();

// Get pricing options
$stmt = $db->query("
    SELECT * FROM " . TABLE_PREFIX . "business_pricing_options 
    WHERE is_active = 1 
    ORDER BY display_order
");
$pricingOptions = $stmt->fetchAll();

// Get all categories
$stmt = $db->query("SELECT * FROM " . TABLE_PREFIX . "business_categories ORDER BY category_name");
$categories = $stmt->fetchAll();

$pageTitle = 'My Businesses (Admin)';
include __DIR__ . '/../includes/header.php';
?>

<script>
// Define function immediately in global scope - before any buttons are rendered
window.showCreateBusinessForm = function() {
    console.log('showCreateBusinessForm called');
    const container = document.getElementById('create-business-container');
    if (!container) {
        console.error('Create business container not found');
        return false;
    }
    console.log('Showing create business form');
    container.style.display = 'block';
    
    // Ensure tabs are visible and properly initialized
    const pricingSelect = document.getElementById('create_pricing_status');
    if (pricingSelect) {
        // Update tabs based on current pricing selection
        updateAdvertGraphicsTab('create-business-container', pricingSelect.value);
    }
    
    // Initialize address autocomplete
    setTimeout(function() {
        if (typeof initCreateAddressAutocomplete === 'function') {
            initCreateAddressAutocomplete();
        }
    }, 100);
    const businessNameField = document.getElementById('create_business_name');
    if (businessNameField) {
        businessNameField.focus();
    }
    // Scroll to form
    container.scrollIntoView({ behavior: 'smooth', block: 'start' });
    return false;
};

window.hideCreateBusinessForm = function() {
    const container = document.getElementById('create-business-container');
    if (container) {
        container.style.display = 'none';
    }
    const form = document.getElementById('createBusinessForm');
    if (form) {
        form.reset();
    }
};

// Also attach event listener using event delegation for reliability
document.addEventListener('DOMContentLoaded', function() {
    // Handle button click using event delegation
    document.addEventListener('click', function(e) {
        if (e.target && e.target.getAttribute('data-action') === 'show-create-form') {
            e.preventDefault();
            if (typeof window.showCreateBusinessForm === 'function') {
                window.showCreateBusinessForm();
            } else {
                console.error('showCreateBusinessForm function not available');
            }
            return false;
        }
    });
    
    // Also directly attach to button if it exists
    const createBtn = document.getElementById('create-business-btn');
    if (createBtn) {
        createBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (typeof window.showCreateBusinessForm === 'function') {
                window.showCreateBusinessForm();
            } else {
                console.error('showCreateBusinessForm function not available');
            }
            return false;
        });
    }
    
    // Update pricing option details when selection changes (for create form when businesses exist)
    const createPricingSelect = document.getElementById('create_pricing_status');
    if (createPricingSelect) {
        createPricingSelect.addEventListener('change', function() {
            const selectedSlug = this.value;
            const hiddenField = document.getElementById('create_pricing_status_hidden');
            if (hiddenField) {
                hiddenField.value = selectedSlug;
            }
            
            // Find the selected option in the pricing options data
            if (typeof window.createPricingOptionsData !== 'undefined') {
                const selectedOption = window.createPricingOptionsData.find(function(opt) {
                    return opt.slug === selectedSlug;
                });
                
                // Update the description display
                const detailsContainer = document.getElementById('create-option-details-content');
                if (detailsContainer) {
                    if (selectedOption && selectedOption.description) {
                        // Display HTML content (not escaped)
                        detailsContainer.innerHTML = selectedOption.description;
                    } else {
                        detailsContainer.innerHTML = '<em>No description available</em>';
                    }
                }
            }
        });
    }
    
    // Update pricing option details when selection changes (for initial form when no businesses exist)
    const initialPricingSelect = document.getElementById('pricing_status_initial');
    if (initialPricingSelect) {
        initialPricingSelect.addEventListener('change', function() {
            const selectedSlug = this.value;
            const hiddenField = document.getElementById('pricing_status');
            if (hiddenField) {
                hiddenField.value = selectedSlug;
            }
            
            // Find the selected option in the pricing options data
            if (typeof window.createPricingOptionsData !== 'undefined') {
                const selectedOption = window.createPricingOptionsData.find(function(opt) {
                    return opt.slug === selectedSlug;
                });
                
                // Update the description display
                const detailsContainer = document.getElementById('initial-option-details-content');
                if (detailsContainer) {
                    if (selectedOption && selectedOption.description) {
                        // Display HTML content (not escaped)
                        detailsContainer.innerHTML = selectedOption.description;
                    } else {
                        detailsContainer.innerHTML = '<em>No description available</em>';
                    }
                }
            }
        });
    }
});
</script>

<div class="container">
    <div class="content-area">
        <h1>My Businesses (Admin Test)</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= h($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        
        <?php if (empty($businesses)): ?>
            <!-- Show pricing options when no businesses exist -->
            <div class="pricing-options-container" style="margin-bottom: 3rem;">
                <h2>Choose Your Advertising Package</h2>
                <p style="margin-bottom: 2rem; color: #666;">Select a pricing option to get started:</p>
                
                <div class="pricing-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem; margin-bottom: 2rem;">
                    <?php foreach ($pricingOptions as $option): ?>
                        <div class="pricing-card" style="background: white; border-radius: 8px; padding: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border: 2px solid #e0e0e0; cursor: pointer; transition: all 0.3s ease;" 
                             onclick="selectPricingOption('<?= h($option['option_slug']) ?>')"
                             onmouseover="this.style.borderColor='var(--primary-color)'; this.style.transform='translateY(-4px)'"
                             onmouseout="this.style.borderColor='#e0e0e0'; this.style.transform='translateY(0)'">
                            <h3 style="margin-top: 0; color: var(--primary-color); font-size: 1.5rem;"><?= h($option['option_name']) ?></h3>
                            <div class="pricing-description" style="color: #666; line-height: 1.6;">
                                <?= $option['description'] ? nl2br(h($option['description'])) : 'No description available' ?>
                            </div>
                            <button type="button" class="btn btn-primary" style="margin-top: 1.5rem; width: 100%;" onclick="event.stopPropagation(); selectPricingOption('<?= h($option['option_slug']) ?>')">
                                Select <?= h($option['option_name']) ?>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Business form (hidden by default, shown when Free is selected) -->
            <div id="business-form-container" style="display: none;">
                <!-- Pricing Option Section -->
                <div class="pricing-option-section" style="background: #f5f5f5; border-radius: 8px; padding: 1.5rem; margin-bottom: 2rem; border-left: 4px solid var(--primary-color);">
                    <h3 style="margin-top: 0; color: var(--primary-color);">Pricing Option</h3>
                    
                    <div class="form-group">
                        <label for="pricing_status_initial" style="font-weight: 600; margin-bottom: 0.5rem; display: block;">Select Pricing Model:</label>
                        <select id="pricing_status_initial" name="pricing_status_initial" style="width: 100%; max-width: 400px; padding: 0.75rem; font-size: 1rem; border: 1px solid var(--border-color); border-radius: 4px;">
                            <?php foreach ($pricingOptions as $option): ?>
                                <option value="<?= h($option['option_slug']) ?>" <?= ($option['option_slug'] === 'free') ? 'selected' : '' ?>>
                                    <?= h($option['option_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="display: block; margin-top: 0.5rem; color: #666;">
                            Select a pricing model for this business.
                        </small>
                    </div>
                    
                    <div id="initial-option-details-container" style="margin-top: 1rem; padding: 1rem; background-color: white; border-radius: 4px;">
                        <strong>Option Details:</strong>
                        <div id="initial-option-details-content" style="margin-top: 0.5rem; color: #666;">
                            <?php 
                            // Show description of default (free) pricing option
                            $defaultOption = null;
                            foreach ($pricingOptions as $option) {
                                if ($option['option_slug'] === 'free') {
                                    $defaultOption = $option;
                                    break;
                                }
                            }
                            if ($defaultOption && !empty($defaultOption['description'])) {
                                // Display HTML content from TinyMCE (not escaped)
                                echo $defaultOption['description'];
                            } else {
                                echo '<em>No description available</em>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
                
                <!-- Store pricing options data for JavaScript (for initial form) -->
                <script>
                if (typeof window.createPricingOptionsData === 'undefined') {
                    window.createPricingOptionsData = <?= json_encode(array_map(function($opt) {
                        return [
                            'slug' => $opt['option_slug'],
                            'name' => $opt['option_name'],
                            'description' => $opt['description'] ?? ''
                        ];
                    }, $pricingOptions)) ?>;
                }
                </script>
                
                <!-- Tabs -->
                <div class="tab-navigation" style="margin-bottom: 1rem; border-bottom: 2px solid var(--border-color); display: flex; gap: 1rem; justify-content: flex-start;">
                    <button type="button" class="tab-button active" data-tab="business-details">Business Details</button>
                    <button type="button" class="tab-button" data-tab="advert-graphics" id="advert-graphics-tab-btn" style="display: none;">Advert Graphics</button>
                </div>
                
                <!-- Business Details Tab -->
                <div id="business-details-tab" class="tab-content active">
                    <h2>Business Details</h2>
                    <form method="POST" action="" id="businessForm">
                        <input type="hidden" name="action" value="create">
                        <input type="hidden" name="pricing_status" id="pricing_status" value="free">
                        
                        <div class="form-group">
                        <label for="business_name">Business Name: <span class="required">*</span></label>
                        <input type="text" id="business_name" name="business_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="category_id">Category: <span class="required">*</span></label>
                        <select id="category_id" name="category_id" required>
                            <option value="">Select a category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= $category['category_id'] ?>"><?= h($category['category_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="contact_name">Contact Name:</label>
                        <input type="text" id="contact_name" name="contact_name">
                    </div>
                    
                    <div class="form-group">
                        <label for="telephone">Telephone:</label>
                        <input type="text" id="telephone" name="telephone">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email">
                    </div>
                    
                    <div class="form-group">
                        <label for="website">Website:</label>
                        <input type="url" id="website" name="website" placeholder="https://">
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description:</label>
                        <textarea id="description" name="description" rows="5"></textarea>
                    </div>
                    
                    <h3>Address</h3>
                    
                    <div class="form-group" style="position: relative;">
                        <label for="address-search">Search Address:</label>
                        <input type="text" id="address-search" name="address-search" placeholder="Start typing an address...">
                        <div id="address-suggestions" class="address-suggestions"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="building_name">Building Name:</label>
                        <input type="text" id="building_name" name="building_name">
                    </div>
                    
                    <div class="form-group">
                        <label for="street_number">Street Number:</label>
                        <input type="text" id="street_number" name="street_number">
                    </div>
                    
                    <div class="form-group">
                        <label for="street_name">Street Name:</label>
                        <input type="text" id="street_name" name="street_name">
                    </div>
                    
                    <div class="form-group">
                        <label for="suburb">Suburb:</label>
                        <input type="text" id="suburb" name="suburb">
                    </div>
                    
                    <div class="form-group">
                        <label for="town">Town:</label>
                        <input type="text" id="town" name="town">
                    </div>
                    
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Create Business</button>
                            <button type="button" class="btn btn-secondary" onclick="cancelBusinessForm()">Cancel</button>
                        </div>
                    </form>
                </div>
                
                <!-- Advert Graphics Tab -->
                <div id="advert-graphics-tab" class="tab-content" style="display: none;">
                    <h2>Advert Graphics</h2>
                    <div id="advert-graphics-content">
                        <p>Please select a pricing option other than "Free" to manage advert graphics.</p>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Show existing businesses -->
            <div class="businesses-list">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-right: 1rem;">
                    <h2 style="margin: 0; padding: 0.5rem 0;">Your Businesses</h2>
                    <button type="button" id="create-business-btn" class="btn btn-primary" data-action="show-create-form" style="flex-shrink: 0; margin-left: auto;">Create Business</button>
                </div>
                
                <!-- Create Business Form (hidden by default when businesses exist) -->
                <div id="create-business-container" style="display: none; background: white; border-radius: 8px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <!-- Pricing Option Section -->
                    <div class="pricing-option-section" style="background: #f5f5f5; border-radius: 8px; padding: 1.5rem; margin-bottom: 2rem; border-left: 4px solid var(--primary-color);">
                        <h3 style="margin-top: 0; color: var(--primary-color);">Pricing Option</h3>
                        
                        <div class="form-group">
                            <label for="create_pricing_status" style="font-weight: 600; margin-bottom: 0.5rem; display: block;">Select Pricing Model:</label>
                            <select id="create_pricing_status" name="pricing_status" style="width: 100%; max-width: 400px; padding: 0.75rem; font-size: 1rem; border: 1px solid var(--border-color); border-radius: 4px;">
                                <?php foreach ($pricingOptions as $option): ?>
                                    <option value="<?= h($option['option_slug']) ?>" <?= ($option['option_slug'] === 'free') ? 'selected' : '' ?>>
                                        <?= h($option['option_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small style="display: block; margin-top: 0.5rem; color: #666;">
                                Select a pricing model for this business.
                            </small>
                        </div>
                        
                        <div id="create-option-details-container" style="margin-top: 1rem; padding: 1rem; background-color: white; border-radius: 4px;">
                            <strong>Option Details:</strong>
                            <div id="create-option-details-content" style="margin-top: 0.5rem; color: #666;">
                                <?php 
                                // Show description of default (free) pricing option
                                $defaultOption = null;
                                foreach ($pricingOptions as $option) {
                                    if ($option['option_slug'] === 'free') {
                                        $defaultOption = $option;
                                        break;
                                    }
                                }
                                if ($defaultOption && !empty($defaultOption['description'])) {
                                    // Display HTML content from TinyMCE (not escaped)
                                    echo $defaultOption['description'];
                                } else {
                                    echo '<em>No description available</em>';
                                }
                                ?>
                            </div>
                        </div>
                        
                        <!-- Store pricing options data for JavaScript -->
                        <script>
                        if (typeof window.createPricingOptionsData === 'undefined') {
                            window.createPricingOptionsData = <?= json_encode(array_map(function($opt) {
                                return [
                                    'slug' => $opt['option_slug'],
                                    'name' => $opt['option_name'],
                                    'description' => $opt['description'] ?? ''
                                ];
                            }, $pricingOptions)) ?>;
                        }
                        </script>
                    </div>
                    
                    <!-- Tabs -->
                    <div class="tab-navigation" style="margin-bottom: 1rem; border-bottom: 2px solid var(--border-color); display: flex; gap: 1rem; justify-content: flex-start;">
                        <button type="button" class="tab-button active" data-tab="create-business-details">Business Details</button>
                        <button type="button" class="tab-button" data-tab="create-advert-graphics" id="create-advert-graphics-tab-btn" style="display: none;">Advert Graphics</button>
                    </div>
                    
                    <!-- Business Details Tab -->
                    <div id="create-business-details-tab" class="tab-content active">
                        <h3 style="margin-top: 0;">Business Details</h3>
                        <form method="POST" action="" id="createBusinessForm">
                            <input type="hidden" name="action" value="create">
                            <input type="hidden" name="pricing_status" id="create_pricing_status_hidden" value="free">
                            
                            <div class="form-group">
                            <label for="create_business_name">Business Name: <span class="required">*</span></label>
                            <input type="text" id="create_business_name" name="business_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="create_category_id">Category: <span class="required">*</span></label>
                            <select id="create_category_id" name="category_id" required>
                                <option value="">Select a category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['category_id'] ?>"><?= h($category['category_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="create_contact_name">Contact Name:</label>
                            <input type="text" id="create_contact_name" name="contact_name">
                        </div>
                        
                        <div class="form-group">
                            <label for="create_telephone">Telephone:</label>
                            <input type="text" id="create_telephone" name="telephone">
                        </div>
                        
                        <div class="form-group">
                            <label for="create_email">Email:</label>
                            <input type="email" id="create_email" name="email">
                        </div>
                        
                        <div class="form-group">
                            <label for="create_website">Website:</label>
                            <input type="url" id="create_website" name="website" placeholder="https://">
                        </div>
                        
                        <div class="form-group">
                            <label for="create_description">Description:</label>
                            <textarea id="create_description" name="description" rows="5"></textarea>
                        </div>
                        
                        <h4>Address</h4>
                        
                        <div class="form-group" style="position: relative;">
                            <label for="create_address-search">Search Address:</label>
                            <input type="text" id="create_address-search" name="address-search" placeholder="Start typing an address...">
                            <div id="create_address-suggestions" class="address-suggestions"></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="create_building_name">Building Name:</label>
                            <input type="text" id="create_building_name" name="building_name">
                        </div>
                        
                        <div class="form-group">
                            <label for="create_street_number">Street Number:</label>
                            <input type="text" id="create_street_number" name="street_number">
                        </div>
                        
                        <div class="form-group">
                            <label for="create_street_name">Street Name:</label>
                            <input type="text" id="create_street_name" name="street_name">
                        </div>
                        
                        <div class="form-group">
                            <label for="create_suburb">Suburb:</label>
                            <input type="text" id="create_suburb" name="suburb">
                        </div>
                        
                        <div class="form-group">
                            <label for="create_town">Town:</label>
                            <input type="text" id="create_town" name="town">
                        </div>
                        
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary">Create Business</button>
                                    <button type="button" class="btn btn-secondary" onclick="hideCreateBusinessForm()">Cancel</button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Advert Graphics Tab -->
                        <div id="create-advert-graphics-tab" class="tab-content" style="display: none;">
                            <h3 style="margin-top: 0;">Advert Graphics</h3>
                            <div id="create-advert-graphics-content">
                                <p>Please select a pricing option other than "Free" to manage advert graphics.</p>
                            </div>
                        </div>
                    </div>
                
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Business Name</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($businesses as $business): ?>
                            <tr>
                                <td><?= h($business['business_name']) ?></td>
                                <td><?= h($business['category_name']) ?></td>
                                <td>
                                    <span style="text-transform: capitalize; font-weight: 600;">
                                        <?= h($business['pricing_status'] ?? 'free') ?>
                                    </span>
                                </td>
                                <td><?= formatDate($business['created_at'], 'Y-m-d') ?></td>
                                <td>
                                    <a href="<?= baseUrl('/admin/edit-business-admin.php?id=' . $business['business_id']) ?>" class="btn btn-sm btn-primary">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function cancelBusinessForm() {
    document.getElementById('business-form-container').style.display = 'none';
    document.querySelector('.pricing-options-container').style.display = 'block';
    document.getElementById('businessForm').reset();
}

// hideCreateBusinessForm is already defined in the header script

// Address autocomplete for create form
function initCreateAddressAutocomplete() {
    const addressSearch = document.getElementById('create_address-search');
    if (!addressSearch || addressSearch.dataset.initialized) return;
    addressSearch.dataset.initialized = 'true';
    
    let createAddressTimeout;
    let isCreateInputFocused = false;
    
    addressSearch.addEventListener('input', function(e) {
        const query = e.target.value.trim();
        clearTimeout(createAddressTimeout);
        
        if (query.length < 3) {
            document.getElementById('create_address-suggestions').style.display = 'none';
            return;
        }
        
        createAddressTimeout = setTimeout(() => {
            fetchCreateAddressSuggestions(query);
        }, 300);
    });
    
    addressSearch.addEventListener('focus', function() {
        isCreateInputFocused = true;
    });
    
    addressSearch.addEventListener('blur', function() {
        isCreateInputFocused = false;
        setTimeout(() => {
            if (!isCreateInputFocused) {
                document.getElementById('create_address-suggestions').style.display = 'none';
            }
        }, 200);
    });
}

function fetchCreateAddressSuggestions(query) {
    fetch('<?= baseUrl('/api/address-autocomplete.php') ?>?q=' + encodeURIComponent(query))
        .then(response => response.json())
        .then(data => {
            if (data.success && data.suggestions && data.suggestions.length > 0) {
                displayCreateSuggestions(data.suggestions);
            } else {
                document.getElementById('create_address-suggestions').style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Error fetching address suggestions:', error);
            document.getElementById('create_address-suggestions').style.display = 'none';
        });
}

function displayCreateSuggestions(suggestions) {
    const container = document.getElementById('create_address-suggestions');
    container.innerHTML = '';
    
    suggestions.forEach(suggestion => {
        const div = document.createElement('div');
        div.className = 'address-suggestion';
        div.textContent = suggestion.display_name;
        div.addEventListener('mousedown', (e) => {
            e.preventDefault();
            selectCreateSuggestion(suggestion);
        });
        container.appendChild(div);
    });
    
    container.style.display = 'block';
}

function selectCreateSuggestion(suggestion) {
    const searchQuery = document.getElementById('create_address-search').value.trim();
    const streetNumberMatch = searchQuery.match(/^(\d+)\s+/);
    const extractedStreetNumber = streetNumberMatch ? streetNumberMatch[1] : '';
    
    document.getElementById('create_street_number').value = suggestion.street_number || extractedStreetNumber || '';
    document.getElementById('create_street_name').value = suggestion.street_name || '';
    document.getElementById('create_suburb').value = suggestion.suburb || '';
    document.getElementById('create_town').value = suggestion.town || '';
    
    document.getElementById('create_address-search').value = '';
    document.getElementById('create_address-suggestions').style.display = 'none';
}

<script>
// Address autocomplete functionality
let addressTimeout;
let isInputFocused = false;

// Initialize address autocomplete when form is shown
function initAddressAutocomplete() {
    const addressSearch = document.getElementById('address-search');
    if (!addressSearch || addressSearch.dataset.initialized) return;
    addressSearch.dataset.initialized = 'true';
    
    addressSearch.addEventListener('input', function(e) {
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
    
    addressSearch.addEventListener('focus', function() {
        isInputFocused = true;
    });
    
    addressSearch.addEventListener('blur', function() {
        isInputFocused = false;
        setTimeout(() => {
            if (!isInputFocused) {
                document.getElementById('address-suggestions').style.display = 'none';
            }
        }, 200);
    });
}

// Initialize when form is shown
function selectPricingOption(slug) {
    // Show form for any option
    document.getElementById('pricing_status').value = slug;
    document.getElementById('pricing_status_initial').value = slug;
    if (document.getElementById('selected-package-name')) {
        document.getElementById('selected-package-name').textContent = slug.charAt(0).toUpperCase() + slug.slice(1);
    }
    document.getElementById('business-form-container').style.display = 'block';
    document.querySelector('.pricing-options-container').style.display = 'none';
    
    // Initialize tabs based on pricing option
    if (typeof updateAdvertGraphicsTab === 'function') {
        updateAdvertGraphicsTab('business-form-container', slug);
    }
    
    // Initialize address autocomplete
    setTimeout(() => {
        if (typeof initAddressAutocomplete === 'function') {
            initAddressAutocomplete();
        }
    }, 100);
        document.getElementById('business_name').focus();
    } else {
        alert('The ' + slug + ' option is coming soon! For now, please select the Free option.');
    }
}

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

// Tab functionality for business forms
document.addEventListener('DOMContentLoaded', function() {
    // Handle tab clicks
    document.querySelectorAll('.tab-button').forEach(button => {
        button.addEventListener('click', function() {
            const container = this.closest('#business-form-container, #create-business-container');
            if (!container) return;
            const tabName = this.getAttribute('data-tab');
            switchTab(container.id, tabName);
        });
    });
    
    // Show/hide advert graphics tab based on pricing option (initial form)
    const initialPricingSelect = document.getElementById('pricing_status_initial');
    if (initialPricingSelect) {
        const updateInitialTabs = function() {
            updateAdvertGraphicsTab('business-form-container', initialPricingSelect.value);
        };
        initialPricingSelect.addEventListener('change', updateInitialTabs);
        updateInitialTabs();
    }
    
    // Show/hide advert graphics tab based on pricing option (create form)
    const createPricingSelect = document.getElementById('create_pricing_status');
    if (createPricingSelect) {
        const updateCreateTabs = function() {
            updateAdvertGraphicsTab('create-business-container', createPricingSelect.value);
        };
        createPricingSelect.addEventListener('change', updateCreateTabs);
        updateCreateTabs();
    }
});

function switchTab(containerId, tabName) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    container.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
        tab.style.display = 'none';
    });
    container.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('active');
    });
    
    const selectedTab = container.querySelector('#' + tabName + '-tab');
    const selectedButton = container.querySelector('[data-tab="' + tabName + '"]');
    
    if (selectedTab) {
        selectedTab.classList.add('active');
        selectedTab.style.display = 'block';
    }
    if (selectedButton) {
        selectedButton.classList.add('active');
    }
}

function updateAdvertGraphicsTab(containerId, pricingStatus) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    // Find the correct tab button and content based on container
    let tabButton, tabContent;
    if (containerId === 'business-form-container') {
        tabButton = document.getElementById('advert-graphics-tab-btn');
        tabContent = document.getElementById('advert-graphics-tab');
    } else if (containerId === 'create-business-container') {
        tabButton = document.getElementById('create-advert-graphics-tab-btn');
        tabContent = document.getElementById('create-advert-graphics-tab');
    } else {
        // Fallback to querySelector
        tabButton = container.querySelector('[data-tab*="advert-graphics"]');
        tabContent = container.querySelector('[id*="advert-graphics-tab"]');
    }
    
    // Ensure tab navigation is visible
    const tabNav = container.querySelector('.tab-navigation');
    if (tabNav) {
        tabNav.style.display = 'flex';
    }
    
    if (pricingStatus === 'free') {
        if (tabButton) tabButton.style.display = 'none';
        if (tabContent) tabContent.style.display = 'none';
    } else {
        if (tabButton) {
            tabButton.style.display = 'inline-block';
        }
        if (tabContent) {
            // Reset loaded flag so content reloads when pricing changes
            const contentDiv = tabContent.querySelector('[id*="advert-graphics-content"]');
            if (contentDiv) {
                contentDiv.dataset.loaded = 'false';
            }
            loadAdvertGraphicsContent(containerId, pricingStatus);
        }
    }
}

function loadAdvertGraphicsContent(containerId, pricingStatus) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    const contentDiv = container.querySelector('[id*="advert-graphics-content"]');
    if (!contentDiv || contentDiv.dataset.loaded === 'true') return;
    
    const prefix = containerId === 'create-business-container' ? 'create_' : '';
    let formHTML = '';
    
    if (pricingStatus === 'basic') {
        formHTML = `<form id="${prefix}basic-advert-form" enctype="multipart/form-data" action="<?= baseUrl('/admin/manage-business-adverts.php') ?>" method="POST">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="advert_type" value="basic">
            <input type="hidden" name="business_id" id="${prefix}basic_business_id" value="">
            <div class="form-group"><label>Banner Image: <span class="required">*</span></label>
            <input type="file" name="banner_image" accept="image/*" required></div>
            <div class="form-group"><label>Display Image: <span class="required">*</span></label>
            <input type="file" name="display_image" accept="image/*" required></div>
            <div class="form-group"><button type="submit" class="btn btn-primary">Save Advert</button></div>
        </form><div id="${prefix}basic-advert-list"></div>`;
    } else if (pricingStatus === 'timed') {
        formHTML = '<button type="button" class="btn btn-primary" onclick="showAddTimedAdvertForm(\'' + containerId + '\')">Add New Timed Advert</button>' +
        '<div id="' + prefix + 'timed-advert-list"></div>' +
        '<div id="' + prefix + 'timed-advert-form-container" style="display: none; margin-top: 1rem; padding: 1rem; background: #f5f5f5; border-radius: 8px;">' +
            '<h4>Add Timed Advert</h4>' +
            '<form id="' + prefix + 'timed-advert-form" enctype="multipart/form-data" action="<?= baseUrl('/admin/manage-business-adverts.php') ?>" method="POST">' +
                '<input type="hidden" name="action" value="create">' +
                '<input type="hidden" name="advert_type" value="timed">' +
                '<input type="hidden" name="business_id" id="' + prefix + 'timed_business_id" value="">' +
                '<div class="form-group"><label>Banner Image: <span class="required">*</span></label>' +
                '<input type="file" name="banner_image" accept="image/*" required></div>' +
                '<div class="form-group"><label>Display Image: <span class="required">*</span></label>' +
                '<input type="file" name="display_image" accept="image/*" required></div>' +
                '<div class="form-group"><label>Start Date: <span class="required">*</span></label>' +
                '<input type="date" name="start_date" required></div>' +
                '<div class="form-group"><label>End Date: <span class="required">*</span></label>' +
                '<input type="date" name="end_date" required></div>' +
                '<div class="form-group"><button type="submit" class="btn btn-primary">Save Advert</button> ' +
                '<button type="button" class="btn btn-secondary" onclick="hideAddTimedAdvertForm(\'' + containerId + '\')">Cancel</button></div>' +
            '</form></div>';
    } else if (pricingStatus === 'events') {
        formHTML = '<button type="button" class="btn btn-primary" onclick="showAddEventsAdvertForm(\'' + containerId + '\')">Add New Event Advert</button>' +
        '<div id="' + prefix + 'events-advert-list"></div>' +
        '<div id="' + prefix + 'events-advert-form-container" style="display: none; margin-top: 1rem; padding: 1rem; background: #f5f5f5; border-radius: 8px;">' +
            '<h4>Add Event Advert</h4>' +
            '<form id="' + prefix + 'events-advert-form" enctype="multipart/form-data" action="<?= baseUrl('/admin/manage-business-adverts.php') ?>" method="POST">' +
                '<input type="hidden" name="action" value="create">' +
                '<input type="hidden" name="advert_type" value="events">' +
                '<input type="hidden" name="business_id" id="' + prefix + 'events_business_id" value="">' +
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
                '<button type="button" class="btn btn-secondary" onclick="hideAddEventsAdvertForm(\'' + containerId + '\')">Cancel</button></div>' +
            '</form></div>';
    }
    
    contentDiv.innerHTML = formHTML;
    contentDiv.dataset.loaded = 'true';
    
    // For basic adverts, show the form immediately (since there's only one)
    if (pricingStatus === 'basic') {
        // Form is already visible in the HTML
    }
    // For timed and events, the form is hidden until "Add New" is clicked
}

function showAddTimedAdvertForm(containerId) {
    const prefix = containerId === 'create-business-container' ? 'create_' : '';
    const form = document.getElementById(prefix + 'timed-advert-form-container');
    if (form) form.style.display = 'block';
}

function hideAddTimedAdvertForm(containerId) {
    const prefix = containerId === 'create-business-container' ? 'create_' : '';
    const form = document.getElementById(prefix + 'timed-advert-form-container');
    if (form) form.style.display = 'none';
}

function showAddEventsAdvertForm(containerId) {
    const prefix = containerId === 'create-business-container' ? 'create_' : '';
    const form = document.getElementById(prefix + 'events-advert-form-container');
    if (form) form.style.display = 'block';
}

function hideAddEventsAdvertForm(containerId) {
    const prefix = containerId === 'create-business-container' ? 'create_' : '';
    const form = document.getElementById(prefix + 'events-advert-form-container');
    if (form) form.style.display = 'none';
}
</script>

<?php 
$hideAdverts = true;
include __DIR__ . '/../includes/footer.php'; 
?>
