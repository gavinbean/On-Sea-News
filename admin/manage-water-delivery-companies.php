<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/geocoding.php';
requireAnyRole(['ADMIN', 'ANALYTICS']);

$db = getDB();
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $companyName = trim($_POST['company_name'] ?? '');
            $contactName = trim($_POST['contact_name'] ?? '');
            $telephone = trim($_POST['telephone'] ?? '');
            $streetNumber = trim($_POST['street_number'] ?? '');
            $streetName = trim($_POST['street_name'] ?? '');
            $suburb = trim($_POST['suburb'] ?? '');
            $town = trim($_POST['town'] ?? '');
            
            if (empty($companyName)) {
                $error = 'Please enter a company name.';
            } else {
                try {
                    // Geocode address if provided
                    $latitude = null;
                    $longitude = null;
                    
                    if (!empty($streetName) && !empty($town)) {
                        $geocodeResult = validateAndGeocodeAddress([
                            'street_number' => $streetNumber,
                            'street_name' => $streetName,
                            'suburb' => $suburb,
                            'town' => $town
                        ]);
                        
                        if ($geocodeResult['success']) {
                            $latitude = $geocodeResult['latitude'];
                            $longitude = $geocodeResult['longitude'];
                        }
                    }
                    
                    $stmt = $db->prepare("
                        INSERT INTO " . TABLE_PREFIX . "water_delivery_companies 
                        (company_name, contact_name, telephone, street_number, street_name, suburb, town, latitude, longitude) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $companyName,
                        $contactName ?: null,
                        $telephone ?: null,
                        $streetNumber ?: null,
                        $streetName ?: null,
                        $suburb ?: null,
                        $town ?: null,
                        $latitude,
                        $longitude
                    ]);
                    $success = 'Company added successfully!';
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) { // Duplicate entry
                        $error = 'This company name already exists.';
                    } else {
                        $error = 'Error adding company: ' . $e->getMessage();
                        error_log("Add water delivery company error: " . $e->getMessage());
                    }
                }
            }
        } elseif ($_POST['action'] === 'update') {
            $companyId = (int)($_POST['company_id'] ?? 0);
            $companyName = trim($_POST['company_name'] ?? '');
            $contactName = trim($_POST['contact_name'] ?? '');
            $telephone = trim($_POST['telephone'] ?? '');
            $streetNumber = trim($_POST['street_number'] ?? '');
            $streetName = trim($_POST['street_name'] ?? '');
            $suburb = trim($_POST['suburb'] ?? '');
            $town = trim($_POST['town'] ?? '');
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($companyName)) {
                $error = 'Please enter a company name.';
            } elseif ($companyId <= 0) {
                $error = 'Invalid company ID.';
            } else {
                try {
                    // Geocode address if provided
                    $latitude = null;
                    $longitude = null;
                    
                    if (!empty($streetName) && !empty($town)) {
                        $geocodeResult = validateAndGeocodeAddress([
                            'street_number' => $streetNumber,
                            'street_name' => $streetName,
                            'suburb' => $suburb,
                            'town' => $town
                        ]);
                        
                        if ($geocodeResult['success']) {
                            $latitude = $geocodeResult['latitude'];
                            $longitude = $geocodeResult['longitude'];
                        }
                    }
                    
                    $stmt = $db->prepare("
                        UPDATE " . TABLE_PREFIX . "water_delivery_companies 
                        SET company_name = ?, contact_name = ?, telephone = ?, street_number = ?, street_name = ?, suburb = ?, town = ?, latitude = ?, longitude = ?, is_active = ? 
                        WHERE company_id = ?
                    ");
                    $stmt->execute([
                        $companyName,
                        $contactName ?: null,
                        $telephone ?: null,
                        $streetNumber ?: null,
                        $streetName ?: null,
                        $suburb ?: null,
                        $town ?: null,
                        $latitude,
                        $longitude,
                        $isActive,
                        $companyId
                    ]);
                    $success = 'Company updated successfully!';
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) { // Duplicate entry
                        $error = 'This company name already exists.';
                    } else {
                        $error = 'Error updating company: ' . $e->getMessage();
                        error_log("Update water delivery company error: " . $e->getMessage());
                    }
                }
            }
        } elseif ($_POST['action'] === 'delete') {
            $companyId = (int)($_POST['company_id'] ?? 0);
            
            if ($companyId <= 0) {
                $error = 'Invalid company ID.';
            } else {
                // Check if company is used in any deliveries
                $stmt = $db->prepare("
                    SELECT COUNT(*) as count 
                    FROM " . TABLE_PREFIX . "water_deliveries 
                    WHERE company_id = ?
                ");
                $stmt->execute([$companyId]);
                $result = $stmt->fetch();
                
                if ($result['count'] > 0) {
                    $error = 'Cannot delete company. It is used in ' . $result['count'] . ' delivery record(s).';
                } else {
                    try {
                        $stmt = $db->prepare("
                            DELETE FROM " . TABLE_PREFIX . "water_delivery_companies 
                            WHERE company_id = ?
                        ");
                        $stmt->execute([$companyId]);
                        $success = 'Company deleted successfully!';
                    } catch (Exception $e) {
                        $error = 'Error deleting company: ' . $e->getMessage();
                        error_log("Delete water delivery company error: " . $e->getMessage());
                    }
                }
            }
        }
    }
}

// Get all companies
$stmt = $db->query("
    SELECT * 
    FROM " . TABLE_PREFIX . "water_delivery_companies 
    ORDER BY company_name ASC
");
$companies = $stmt->fetchAll();

$pageTitle = 'Manage Water Delivery Companies';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="content-area">
        <h1>Manage Water Delivery Companies</h1>
        
        <p><a href="<?= baseUrl('/admin/dashboard.php') ?>" class="btn btn-secondary">← Back to Dashboard</a></p>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= h($success) ?></div>
        <?php endif; ?>
        
        <!-- Add Company Form -->
        <div class="admin-section" style="background: #f5f5f5; padding: 20px; border-radius: 4px; margin-bottom: 30px;">
            <h2>Add New Company</h2>
            <form method="POST" action="" id="addCompanyForm">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label for="company_name">Company Name: <span class="required">*</span></label>
                    <input type="text" id="company_name" name="company_name" required style="width: 100%; max-width: 400px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div class="form-group">
                    <label for="contact_name">Contact Name:</label>
                    <input type="text" id="contact_name" name="contact_name" style="width: 100%; max-width: 400px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div class="form-group">
                    <label for="telephone">Telephone:</label>
                    <input type="tel" id="telephone" name="telephone" style="width: 100%; max-width: 400px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div class="form-group">
                    <label for="address-search">Address Search:</label>
                    <input type="text" id="address-search" placeholder="Start typing the company address..." autocomplete="off" style="width: 100%; max-width: 400px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <div id="address-autocomplete" class="address-autocomplete" style="position: absolute; z-index: 1000; background: white; border: 1px solid #ddd; border-radius: 4px; max-height: 200px; overflow-y: auto; display: none; width: 100%; max-width: 400px;"></div>
                    <small>Start typing the address and select from the suggestions</small>
                </div>
                
                <div class="form-group">
                    <label for="street_number">Street Number:</label>
                    <input type="text" id="street_number" name="street_number" autocomplete="off" style="width: 100%; max-width: 400px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div class="form-group">
                    <label for="street_name">Street Name:</label>
                    <input type="text" id="street_name" name="street_name" autocomplete="off" style="width: 100%; max-width: 400px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div class="form-group">
                    <label for="suburb">Suburb:</label>
                    <input type="text" id="suburb" name="suburb" autocomplete="off" style="width: 100%; max-width: 400px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div class="form-group">
                    <label for="town">Town:</label>
                    <input type="text" id="town" name="town" autocomplete="off" style="width: 100%; max-width: 400px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Add Company</button>
                </div>
            </form>
        </div>
        
        <!-- Companies List -->
        <div class="admin-section">
            <h2>Existing Companies</h2>
            
            <?php if (empty($companies)): ?>
                <p>No companies found.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="data-table" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th style="text-align: left;">Company Name</th>
                                <th style="text-align: left;">Contact</th>
                                <th style="text-align: left;">Telephone</th>
                                <th style="text-align: left;">Address</th>
                                <th style="text-align: left;">Status</th>
                                <th style="text-align: left;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($companies as $company): ?>
                                <tr style="border-bottom: 1px solid #ddd;">
                                    <td style="padding: 10px; vertical-align: top;">
                                        <form method="POST" action="" class="company-edit-form" data-company-id="<?= $company['company_id'] ?>">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="company_id" value="<?= $company['company_id'] ?>">
                                            <div style="margin-bottom: 5px;">
                                                <input type="text" name="company_name" value="<?= h($company['company_name']) ?>" required style="width: 100%; max-width: 250px; padding: 6px; border: 1px solid #ddd; border-radius: 4px;" placeholder="Company Name">
                                            </div>
                                    </td>
                                    <td style="padding: 10px; vertical-align: top;">
                                            <div style="margin-bottom: 5px;">
                                                <input type="text" name="contact_name" value="<?= h($company['contact_name'] ?? '') ?>" style="width: 100%; max-width: 200px; padding: 6px; border: 1px solid #ddd; border-radius: 4px;" placeholder="Contact Name">
                                            </div>
                                    </td>
                                    <td style="padding: 10px; vertical-align: top;">
                                            <div style="margin-bottom: 5px;">
                                                <input type="tel" name="telephone" value="<?= h($company['telephone'] ?? '') ?>" style="width: 100%; max-width: 150px; padding: 6px; border: 1px solid #ddd; border-radius: 4px;" placeholder="Telephone">
                                            </div>
                                    </td>
                                    <td style="padding: 10px; vertical-align: top;">
                                            <div style="margin-bottom: 5px; position: relative;">
                                                <input type="text" class="address-search-edit" data-company-id="<?= $company['company_id'] ?>" placeholder="Search address..." autocomplete="off" style="width: 100%; max-width: 300px; padding: 6px; border: 1px solid #ddd; border-radius: 4px;">
                                                <div class="address-autocomplete-edit" data-company-id="<?= $company['company_id'] ?>" style="position: absolute; z-index: 1000; background: white; border: 1px solid #ddd; border-radius: 4px; max-height: 200px; overflow-y: auto; display: none; width: 100%; max-width: 300px;"></div>
                                            </div>
                                            <div style="margin-bottom: 5px;">
                                                <input type="text" name="street_number" class="street_number_<?= $company['company_id'] ?>" value="<?= h($company['street_number'] ?? '') ?>" style="width: 100%; max-width: 100px; padding: 6px; border: 1px solid #ddd; border-radius: 4px; display: inline-block; margin-right: 5px;" placeholder="No.">
                                                <input type="text" name="street_name" class="street_name_<?= $company['company_id'] ?>" value="<?= h($company['street_name'] ?? '') ?>" style="width: calc(100% - 110px); max-width: 190px; padding: 6px; border: 1px solid #ddd; border-radius: 4px; display: inline-block;" placeholder="Street Name">
                                            </div>
                                            <div style="margin-bottom: 5px;">
                                                <input type="text" name="suburb" class="suburb_<?= $company['company_id'] ?>" value="<?= h($company['suburb'] ?? '') ?>" style="width: 100%; max-width: 150px; padding: 6px; border: 1px solid #ddd; border-radius: 4px; display: inline-block; margin-right: 5px;" placeholder="Suburb">
                                                <input type="text" name="town" class="town_<?= $company['company_id'] ?>" value="<?= h($company['town'] ?? '') ?>" style="width: calc(100% - 160px); max-width: 140px; padding: 6px; border: 1px solid #ddd; border-radius: 4px; display: inline-block;" placeholder="Town">
                                            </div>
                                    </td>
                                    <td style="padding: 10px; vertical-align: top;">
                                            <label style="display: flex; align-items: center; gap: 5px;">
                                                <input type="checkbox" name="is_active" value="1" <?= $company['is_active'] ? 'checked' : '' ?>>
                                                <span>Active</span>
                                            </label>
                                    </td>
                                    <td style="padding: 10px; vertical-align: top;">
                                            <button type="submit" class="btn btn-secondary btn-sm" style="padding: 6px 12px; margin-right: 5px;">Update</button>
                                        </form>
                                        <form method="POST" action="" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to delete this company?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="company_id" value="<?= $company['company_id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" style="padding: 6px 12px;">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.address-autocomplete,
.address-autocomplete-edit {
    position: absolute;
    z-index: 1000;
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    max-height: 200px;
    overflow-y: auto;
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
// Address autocomplete for add form
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
        
        let extractedStreetNumber = '';
        const searchQuery = addressSearch.value.trim();
        if (searchQuery) {
            const numberMatch = searchQuery.match(/^(\d+)\s+/);
            if (numberMatch) {
                extractedStreetNumber = numberMatch[1];
            }
        }
        
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
    }
})();

// Address autocomplete for edit forms (one per company row)
document.querySelectorAll('.address-search-edit').forEach(function(addressSearch) {
    const companyId = addressSearch.getAttribute('data-company-id');
    const autocompleteDiv = document.querySelector('.address-autocomplete-edit[data-company-id="' + companyId + '"]');
    const streetNumberInput = document.querySelector('.street_number_' + companyId);
    const streetNameInput = document.querySelector('.street_name_' + companyId);
    const suburbInput = document.querySelector('.suburb_' + companyId);
    const townInput = document.querySelector('.town_' + companyId);
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
        
        let extractedStreetNumber = '';
        const searchQuery = addressSearch.value.trim();
        if (searchQuery) {
            const numberMatch = searchQuery.match(/^(\d+)\s+/);
            if (numberMatch) {
                extractedStreetNumber = numberMatch[1];
            }
        }
        
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
    }
});
</script>

<?php 
include __DIR__ . '/../includes/footer.php'; 
?>
