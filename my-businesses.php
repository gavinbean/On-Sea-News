<?php
require_once 'includes/functions.php';
requireLogin();

$db = getDB();
$userId = getCurrentUserId();
$message = '';
$error = '';

// Handle business creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
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
            
            // New businesses require approval (is_approved = 0)
            $stmt = $db->prepare("
                INSERT INTO " . TABLE_PREFIX . "businesses 
                (user_id, category_id, business_name, contact_name, telephone, email, address, building_name, street_number, street_name, suburb, town, latitude, longitude, website, description, is_approved)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
            ");
            $stmt->execute([$userId, $categoryId, $businessName, $contactName, $telephone, $email, $address, $buildingName, $streetNumber, $streetName, $suburb, $town, $latitude, $longitude, $website, $description]);
            
            // Notify admins about pending business
            require_once __DIR__ . '/includes/email.php';
            notifyAdminsPendingBusinesses();
            
            $message = 'Business created successfully! It will be reviewed by an administrator before appearing on the website.';
        }
    } elseif ($_POST['action'] === 'delete') {
        $businessId = (int)($_POST['business_id'] ?? 0);
        
        // Verify user owns this business
        $stmt = $db->prepare("SELECT user_id FROM " . TABLE_PREFIX . "businesses WHERE business_id = ? AND user_id = ?");
        $stmt->execute([$businessId, $userId]);
        if ($stmt->fetch()) {
            $stmt = $db->prepare("DELETE FROM " . TABLE_PREFIX . "businesses WHERE business_id = ? AND user_id = ?");
            $stmt->execute([$businessId, $userId]);
            $message = 'Business deleted successfully.';
        } else {
            $error = 'You can only delete your own businesses.';
        }
    }
}

// Get all categories
$stmt = $db->query("SELECT * FROM " . TABLE_PREFIX . "business_categories ORDER BY category_name");
$categories = $stmt->fetchAll();

// Get user's businesses (including those with NULL user_id if somehow created)
$stmt = $db->prepare("
    SELECT b.*, c.category_name
    FROM " . TABLE_PREFIX . "businesses b
    JOIN " . TABLE_PREFIX . "business_categories c ON b.category_id = c.category_id
    WHERE b.user_id = ?
    ORDER BY b.created_at DESC, b.business_name
");
$stmt->execute([$userId]);
$businesses = $stmt->fetchAll();

// Debug: Log if no businesses found
if (empty($businesses)) {
    // Check if there are any businesses at all for this user
    $debugStmt = $db->prepare("SELECT COUNT(*) as count FROM " . TABLE_PREFIX . "businesses WHERE user_id = ?");
    $debugStmt->execute([$userId]);
    $count = $debugStmt->fetch()['count'];
    if ($count > 0) {
        error_log("User $userId has $count businesses but query returned empty");
    }
}

$pageTitle = 'My Businesses';
include 'includes/header.php';
?>

<div class="container">
    <div class="content-area">
        <h1>My Businesses</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= h($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        
        <div class="business-form">
            <h2>Add New Business</h2>
            <p class="info-text">Your business will be reviewed by an administrator before appearing on the website.</p>
            <form method="POST" action="">
                <input type="hidden" name="action" value="create">
                
                <div class="form-group">
                    <label for="business_name">Business Name: <span class="required">*</span></label>
                    <input type="text" id="business_name" name="business_name" required>
                </div>
                
                <div class="form-group">
                    <label for="category_id">Category: <span class="required">*</span></label>
                    <select id="category_id" name="category_id" required>
                        <option value="">Select a category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['category_id'] ?>"><?= h($cat['category_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="contact_name">Contact Name:</label>
                    <input type="text" id="contact_name" name="contact_name">
                </div>
                
                <div class="form-group">
                    <label for="telephone">Telephone: <span class="required">*</span></label>
                    <input type="tel" id="telephone" name="telephone" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email">
                </div>
                
                <div class="form-group" style="position: relative;">
                    <label>Address:</label>
                    <input type="text" id="address-search" placeholder="Start typing your address..." autocomplete="off">
                    <div id="address-autocomplete" class="address-autocomplete"></div>
                    <small>Start typing your address and select from the suggestions</small>
                </div>
                
                <div class="form-group">
                    <label for="building_name">Building Name:</label>
                    <input type="text" id="building_name" name="building_name" autocomplete="off">
                </div>
                
                <div class="form-group">
                    <label for="street_number">Street Number:</label>
                    <input type="text" id="street_number" name="street_number" autocomplete="off">
                </div>
                
                <div class="form-group">
                    <label for="street_name">Street Name:</label>
                    <input type="text" id="street_name" name="street_name" autocomplete="off">
                </div>
                
                <div class="form-group">
                    <label for="suburb">Suburb:</label>
                    <input type="text" id="suburb" name="suburb" autocomplete="off">
                </div>
                
                <div class="form-group">
                    <label for="town">Town:</label>
                    <input type="text" id="town" name="town" autocomplete="off">
                </div>
                
                <div class="form-group">
                    <label for="website">Website:</label>
                    <input type="url" id="website" name="website" placeholder="https://">
                </div>
                
                <div class="form-group">
                    <label for="description">Description:</label>
                    <textarea id="description" name="description" rows="4"></textarea>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Create Business</button>
                </div>
            </form>
        </div>
        
        <div class="businesses-list">
            <h2>My Businesses</h2>
            <?php if (empty($businesses)): ?>
                <p>No businesses yet. Create your first business above.</p>
            <?php else: ?>
                <div class="business-list">
                    <?php foreach ($businesses as $business): ?>
                        <div class="business-item">
                            <div class="business-item-header">
                                <h3><?= h($business['business_name']) ?></h3>
                                <span class="business-status <?= $business['is_approved'] ? 'approved' : 'pending' ?>">
                                    <?= $business['is_approved'] ? '✓ Approved' : '⏳ Pending Approval' ?>
                                </span>
                            </div>
                            <p class="business-category">Category: <?= h($business['category_name']) ?></p>
                            <?php if ($business['description']): ?>
                                <p class="business-description"><?= h(substr($business['description'], 0, 100)) ?><?= strlen($business['description']) > 100 ? '...' : '' ?></p>
                            <?php endif; ?>
                            <div class="business-actions">
                                <a href="<?= baseUrl('/edit-business.php?id=' . $business['business_id']) ?>" class="btn btn-secondary btn-sm">Edit</a>
                                <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this business?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="business_id" value="<?= $business['business_id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.business-form, .businesses-list {
    background-color: var(--white);
    padding: 2rem;
    border-radius: 8px;
    box-shadow: var(--shadow);
    margin-bottom: 2rem;
}

.info-text {
    color: #666;
    font-style: italic;
    margin-bottom: 1rem;
}

.business-list {
    display: grid;
    gap: 1rem;
}

.business-item {
    padding: 1.5rem;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    background-color: var(--bg-color);
}

.business-item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.business-item-header h3 {
    margin: 0;
    color: var(--primary-color);
}

.business-status {
    padding: 0.25rem 0.75rem;
    border-radius: 4px;
    font-size: 0.875rem;
    font-weight: 600;
}

.business-status.approved {
    background-color: #d4edda;
    color: #155724;
}

.business-status.pending {
    background-color: #fff3cd;
    color: #856404;
}

.business-category {
    color: #666;
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
}

.business-description {
    color: #666;
    margin-bottom: 1rem;
}

.business-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
}
</style>

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

