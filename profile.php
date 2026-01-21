<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/water-questions.php';
requireLogin();

$user = getCurrentUser();
$userId = getCurrentUserId();
$message = '';
$error = '';
$editMode = isset($_GET['edit']) || isset($_POST['action']);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_profile') {
        $result = updateUserProfile($userId, $_POST);
        if ($result['success']) {
            $message = $result['message'];
            $editMode = false;
            // Reload user data
            $user = getCurrentUser();
        } else {
            $error = $result['message'];
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'create_business') {
        // Handle business creation
        $db = getDB();
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
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_business') {
        // Handle business deletion
        $db = getDB();
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
    } elseif (isset($_POST['action']) && $_POST['action'] === 'save_water_info') {
        // Save water information responses
        $waterResponses = [];
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'water_q') === 0) {
                $questionId = str_replace('water_q', '', $key);
                // Handle checkbox arrays
                if (is_array($value)) {
                    $waterResponses[$questionId] = $value;
                } else {
                    $waterResponses[$questionId] = $value;
                }
            }
        }
        
        $result = saveWaterResponses($userId, $waterResponses);
        if ($result['success']) {
            $message = 'Water information saved successfully.';
        } else {
            $error = $result['message'] ?? 'Failed to save water information.';
        }
    }
}

// Get water questions and user's existing responses
$waterQuestions = getWaterQuestions('water_info', $userId);
$userWaterResponses = getUserWaterResponses($userId, 'water_info');

$pageTitle = 'My Profile';
include 'includes/header.php';
?>

<div class="container">
    <div class="content-area">
        <h1>My Profile</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= h($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        
        <!-- Profile Tabs -->
        <div class="profile-tabs">
            <button type="button" class="tab-button active" data-tab="personal">Personal Information</button>
            <button type="button" class="tab-button" data-tab="water">Water Information</button>
            <button type="button" class="tab-button" data-tab="availability">Water Availability Records</button>
            <button type="button" class="tab-button" data-tab="deliveries">Water Deliveries</button>
            <button type="button" class="tab-button" data-tab="businesses">My Businesses</button>
        </div>
        
        <!-- Personal Information Tab -->
        <div class="tab-content active" id="tab-personal">
            <?php if ($editMode): ?>
            <div class="profile-edit-form">
                <h2>Edit Profile Information</h2>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="form-group">
                        <label for="username">Username:</label>
                        <input type="text" id="username" name="username" value="<?= h($user['username']) ?>" disabled>
                        <small>Username cannot be changed</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" value="<?= h($user['email']) ?>" disabled>
                        <small>Email cannot be changed</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="name">Name: <span class="required">*</span></label>
                        <input type="text" id="name" name="name" value="<?= h($user['name']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="surname">Surname: <span class="required">*</span></label>
                        <input type="text" id="surname" name="surname" value="<?= h($user['surname']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="telephone">Telephone: <span class="required">*</span></label>
                        <input type="tel" id="telephone" name="telephone" value="<?= h($user['telephone']) ?>" required>
                    </div>
                    
                    <div class="form-group" style="position: relative;">
                        <label>Address Search:</label>
                        <input type="text" id="address-search" placeholder="Start typing your address..." autocomplete="off">
                        <div id="address-autocomplete" class="address-autocomplete"></div>
                        <small>Start typing your address and select from the suggestions</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="street_number">Street Number:</label>
                        <input type="text" id="street_number" name="street_number" value="<?= h($user['street_number'] ?? '') ?>" autocomplete="off">
                    </div>
                    
                    <div class="form-group">
                        <label for="street_name">Street Name: <span class="required">*</span></label>
                        <input type="text" id="street_name" name="street_name" value="<?= h($user['street_name'] ?? '') ?>" required autocomplete="off">
                    </div>
                    
                    <div class="form-group">
                        <label for="suburb">Suburb:</label>
                        <input type="text" id="suburb" name="suburb" value="<?= h($user['suburb'] ?? '') ?>" autocomplete="off">
                    </div>
                    
                    <div class="form-group">
                        <label for="town">Town: <span class="required">*</span></label>
                        <input type="text" id="town" name="town" value="<?= h($user['town'] ?? '') ?>" required autocomplete="off">
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <a href="<?= baseUrl('/profile.php') ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="profile-info">
                <div style="margin-bottom: 1rem;">
                    <a href="?edit=1" class="btn btn-primary">Edit Profile</a>
                </div>
                
                <h2>Account Information</h2>
                <p><strong>Username:</strong> <?= h($user['username']) ?></p>
                <p><strong>Email:</strong> <?= h($user['email']) ?></p>
                <p><strong>Name:</strong> <?= h($user['name'] . ' ' . $user['surname']) ?></p>
                <p><strong>Telephone:</strong> <?= h($user['telephone']) ?></p>
                <p><strong>Address:</strong> 
                    <?php 
                    $addressParts = [];
                    if (!empty($user['street_number'])) $addressParts[] = $user['street_number'];
                    if (!empty($user['street_name'])) $addressParts[] = $user['street_name'];
                    if (!empty($user['suburb'])) $addressParts[] = $user['suburb'];
                    if (!empty($user['town'])) $addressParts[] = $user['town'];
                    echo h(implode(', ', $addressParts) ?: 'Not provided');
                    ?>
                </p>
                <?php if (!empty($user['latitude']) && !empty($user['longitude'])): ?>
                    <p><strong>Location:</strong> <?= number_format($user['latitude'], 6) ?>, <?= number_format($user['longitude'], 6) ?></p>
                <?php endif; ?>
                <p><strong>Member since:</strong> <?= formatDate($user['created_at'], 'F j, Y') ?></p>
            </div>
        <?php endif; ?>
        </div>
        
        <!-- Water Information Tab -->
        <div class="tab-content" id="tab-water">
            <div class="water-information-section">
            <h2>Water Information</h2>
            <p class="info-text">Complete this section if you want to report your water availability. You must accept the terms and conditions to submit water availability reports.</p>
            
            <form method="POST" action="" id="waterInfoForm">
                <input type="hidden" name="action" value="save_water_info">
                
                <div id="water-questions-container">
                    <?php foreach ($waterQuestions as $question): 
                        $userResponse = $userWaterResponses[$question['question_id']] ?? null;
                        $existingValue = $userResponse['response_value'] ?? '';
                    ?>
                        <div class="form-group water-question" 
                             data-question-id="<?= $question['question_id'] ?>"
                             <?php if ($question['depends_on_question_id']): ?>
                                 data-depends-on="<?= $question['depends_on_question_id'] ?>"
                                 data-depends-on-value="<?= h($question['depends_on_answer_value']) ?>"
                                 style="display: none;"
                             <?php endif; ?>>
                            <label for="water_q<?= $question['question_id'] ?>">
                                <?= h($question['question_text']) ?>
                                <?php if ($question['is_required']): ?>
                                    <span class="required">*</span>
                                <?php endif; ?>
                            </label>
                            
                            <?php if ($question['question_type'] === 'dropdown'): ?>
                                <select id="water_q<?= $question['question_id'] ?>" 
                                        name="water_q<?= $question['question_id'] ?>" 
                                        class="water-response"
                                        data-question-id="<?= $question['question_id'] ?>"
                                        <?= $question['is_required'] ? 'required' : '' ?>>
                                    <option value="">Select an option</option>
                                    <?php foreach ($question['options'] as $option): ?>
                                        <option value="<?= h($option['option_value']) ?>" <?= ($existingValue == $option['option_value']) ? 'selected' : '' ?>>
                                            <?= h($option['option_text']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            
                            <?php elseif ($question['question_type'] === 'checkbox'): ?>
                                <div class="checkbox-group">
                                    <?php 
                                    $existingValues = $existingValue ? explode(',', $existingValue) : [];
                                    foreach ($question['options'] as $option): 
                                    ?>
                                        <label class="checkbox-label">
                                            <input type="checkbox" 
                                                   name="water_q<?= $question['question_id'] ?>[]" 
                                                   value="<?= h($option['option_value']) ?>"
                                                   class="water-response"
                                                   data-question-id="<?= $question['question_id'] ?>"
                                                   <?= in_array($option['option_value'], $existingValues) ? 'checked' : '' ?>
                                                   <?= $question['is_required'] && $option['display_order'] == 1 ? 'required' : '' ?>>
                                            <?= h($option['option_text']) ?>
                                            <?php if ($question['terms_link']): ?>
                                                <a href="<?= baseUrl($question['terms_link']) ?>" target="_blank">(View Terms)</a>
                                            <?php endif; ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            
                            <?php elseif ($question['question_type'] === 'radio'): ?>
                                <div class="radio-group">
                                    <?php foreach ($question['options'] as $option): ?>
                                        <label class="radio-label">
                                            <input type="radio" 
                                                   name="water_q<?= $question['question_id'] ?>" 
                                                   value="<?= h($option['option_value']) ?>"
                                                   class="water-response"
                                                   data-question-id="<?= $question['question_id'] ?>"
                                                   <?= ($existingValue == $option['option_value']) ? 'checked' : '' ?>
                                                   <?= $question['is_required'] ? 'required' : '' ?>>
                                            <?= h($option['option_text']) ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($question['help_text']): ?>
                                <small><?= h($question['help_text']) ?></small>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Save Water Information</button>
                </div>
            </form>
            </div>
        </div>
        
        <!-- Water Availability Records Tab -->
        <div class="tab-content" id="tab-availability">
            <?php
            // Get user's water availability records
            $db = getDB();
            $stmt = $db->prepare("
                SELECT water_id, report_date, has_water, notes, latitude, longitude, reported_at
                FROM " . TABLE_PREFIX . "water_availability
                WHERE user_id = ?
                ORDER BY report_date DESC, reported_at DESC
            ");
            $stmt->execute([$userId]);
            $waterRecords = $stmt->fetchAll();
            ?>
            
            <div class="water-records-section">
                <h2>Water Availability Records</h2>
                
                <?php if (empty($waterRecords)): ?>
                    <p>No water availability records found.</p>
                <?php else: ?>
                    <div style="margin-bottom: 1rem; padding: 1rem; background-color: #f5f5f5; border-radius: 4px;">
                        <p style="margin: 0; font-size: 0.9rem; color: #666;">
                            <strong>Total Records:</strong> <?= count($waterRecords) ?>
                        </p>
                    </div>
                    
                    <div style="overflow-x: auto;">
                        <table class="data-table" style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: #2c5f8d; color: white;">
                                    <th style="padding: 10px; text-align: left;">Report Date</th>
                                    <th style="padding: 10px; text-align: left;">Status</th>
                                    <th style="padding: 10px; text-align: left;">Location</th>
                                    <th style="padding: 10px; text-align: left;">Notes</th>
                                    <th style="padding: 10px; text-align: left;">Reported At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($waterRecords as $record): ?>
                                    <tr style="border-bottom: 1px solid #ddd;">
                                        <td style="padding: 10px;"><?= formatDate($record['report_date'], 'Y-m-d') ?></td>
                                        <td style="padding: 10px;">
                                            <?php
                                            $statusText = '';
                                            $statusColor = '';
                                            if ($record['has_water'] == 1) {
                                                $statusText = 'Yes';
                                                $statusColor = 'green';
                                            } elseif ($record['has_water'] == 0) {
                                                $statusText = 'No';
                                                $statusColor = 'red';
                                            } else {
                                                $statusText = 'Intermittent';
                                                $statusColor = 'orange';
                                            }
                                            ?>
                                            <span style="color: <?= $statusColor ?>; font-weight: 600;"><?= h($statusText) ?></span>
                                        </td>
                                        <td style="padding: 10px;">
                                            <?php if (!empty($record['latitude']) && !empty($record['longitude'])): ?>
                                                <?= number_format($record['latitude'], 6) ?>, <?= number_format($record['longitude'], 6) ?>
                                            <?php else: ?>
                                                <span style="color: #999;">No coordinates</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 10px;"><?= !empty($record['notes']) ? h(substr($record['notes'], 0, 50)) . (strlen($record['notes']) > 50 ? '...' : '') : '-' ?></td>
                                        <td style="padding: 10px;"><?= formatDate($record['reported_at'], 'Y-m-d H:i:s') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Water Deliveries Tab -->
        <div class="tab-content" id="tab-deliveries">
            <?php
            // Get user's water deliveries
            $db = getDB();
            $stmt = $db->prepare("
                SELECT wd.*, 
                       COALESCE(wdc.company_name, wd.company_name_other) as company_name,
                       u.username as logged_by_username,
                       u.name as logged_by_name,
                       u.surname as logged_by_surname
                FROM " . TABLE_PREFIX . "water_deliveries wd
                LEFT JOIN " . TABLE_PREFIX . "water_delivery_companies wdc ON wd.company_id = wdc.company_id
                LEFT JOIN " . TABLE_PREFIX . "users u ON wd.logged_by_user_id = u.user_id
                WHERE wd.user_id = ?
                ORDER BY wd.date_delivered DESC, wd.created_at DESC
            ");
            $stmt->execute([$userId]);
            $deliveries = $stmt->fetchAll();
            
            // Calculate totals
            $totalLitres = 0;
            $totalPrice = 0;
            foreach ($deliveries as $delivery) {
                $totalLitres += (float)$delivery['litres_delivered'];
                $totalPrice += (float)$delivery['price'];
            }
            ?>
            
            <div class="deliveries-summary" style="background: #f5f5f5; padding: 20px; border-radius: 4px; margin-bottom: 20px;">
                <h2>Summary</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div>
                        <p style="margin: 0; color: #666; font-size: 0.9rem;">Total Deliveries</p>
                        <p style="margin: 0; font-size: 2rem; font-weight: bold; color: #2c5f8d;"><?= count($deliveries) ?></p>
                    </div>
                    <div>
                        <p style="margin: 0; color: #666; font-size: 0.9rem;">Total Litres</p>
                        <p style="margin: 0; font-size: 2rem; font-weight: bold; color: #2c5f8d;"><?= number_format($totalLitres, 2) ?> L</p>
                    </div>
                    <div>
                        <p style="margin: 0; color: #666; font-size: 0.9rem;">Total Price</p>
                        <p style="margin: 0; font-size: 2rem; font-weight: bold; color: #2c5f8d;">R <?= number_format($totalPrice, 2) ?></p>
                    </div>
                </div>
            </div>
            
            <?php if (empty($deliveries)): ?>
                <p>No water deliveries recorded yet.</p>
            <?php else: ?>
                <div class="deliveries-list">
                    <table class="data-table" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th style="text-align: left;">Date Ordered</th>
                                <th style="text-align: left;">Date Delivered</th>
                                <th style="text-align: left;">Company</th>
                                <th style="text-align: left;">Vehicle Registration</th>
                                <th style="text-align: right;">Litres</th>
                                <th style="text-align: right;">Price</th>
                                <th style="text-align: left;">Logged By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($deliveries as $delivery): ?>
                                <tr style="border-bottom: 1px solid #ddd;">
                                    <td style="padding: 10px;"><?= formatDate($delivery['date_ordered']) ?></td>
                                    <td style="padding: 10px;"><?= formatDate($delivery['date_delivered']) ?></td>
                                    <td style="padding: 10px;"><?= h($delivery['company_name']) ?></td>
                                    <td style="padding: 10px;"><?= h($delivery['vehicle_registration']) ?></td>
                                    <td style="padding: 10px; text-align: right;"><?= number_format($delivery['litres_delivered'], 2) ?> L</td>
                                    <td style="padding: 10px; text-align: right;">R <?= number_format($delivery['price'], 2) ?></td>
                                    <td style="padding: 10px;"><?= h($delivery['logged_by_name'] . ' ' . $delivery['logged_by_surname']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- My Businesses Tab -->
        <div class="tab-content" id="tab-businesses">
            <?php
            // Get all categories
            $db = getDB();
            $stmt = $db->query("SELECT * FROM " . TABLE_PREFIX . "business_categories ORDER BY category_name");
            $categories = $stmt->fetchAll();
            
            // Get user's businesses
            $stmt = $db->prepare("
                SELECT b.*, c.category_name
                FROM " . TABLE_PREFIX . "businesses b
                JOIN " . TABLE_PREFIX . "business_categories c ON b.category_id = c.category_id
                WHERE b.user_id = ?
                ORDER BY b.created_at DESC, b.business_name
            ");
            $stmt->execute([$userId]);
            $businesses = $stmt->fetchAll();
            ?>
            
            <div class="business-form" style="background-color: var(--white); padding: 2rem; border-radius: 8px; box-shadow: var(--shadow); margin-bottom: 2rem;">
                <h2>Add New Business</h2>
                <p class="info-text" style="color: #666; font-style: italic; margin-bottom: 1rem;">Your business will be reviewed by an administrator before appearing on the website.</p>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="create_business">
                    
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
                        <input type="text" id="business-address-search" placeholder="Start typing your address..." autocomplete="off">
                        <div id="business-address-autocomplete" class="address-autocomplete"></div>
                        <small>Start typing your address and select from the suggestions</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="building_name">Building Name:</label>
                        <input type="text" id="building_name" name="building_name" autocomplete="off">
                    </div>
                    
                    <div class="form-group">
                        <label for="business_street_number">Street Number:</label>
                        <input type="text" id="business_street_number" name="street_number" autocomplete="off">
                    </div>
                    
                    <div class="form-group">
                        <label for="business_street_name">Street Name:</label>
                        <input type="text" id="business_street_name" name="street_name" autocomplete="off">
                    </div>
                    
                    <div class="form-group">
                        <label for="business_suburb">Suburb:</label>
                        <input type="text" id="business_suburb" name="suburb" autocomplete="off">
                    </div>
                    
                    <div class="form-group">
                        <label for="business_town">Town:</label>
                        <input type="text" id="business_town" name="town" autocomplete="off">
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
            
            <div class="businesses-list" style="background-color: var(--white); padding: 2rem; border-radius: 8px; box-shadow: var(--shadow);">
                <h2>My Businesses</h2>
                <?php if (empty($businesses)): ?>
                    <p>No businesses yet. Create your first business above.</p>
                <?php else: ?>
                    <div class="business-list" style="display: grid; gap: 1rem;">
                        <?php foreach ($businesses as $business): ?>
                            <div class="business-item" style="padding: 1.5rem; border: 1px solid var(--border-color); border-radius: 8px; background-color: var(--bg-color);">
                                <div class="business-item-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                    <h3 style="margin: 0; color: var(--primary-color);"><?= h($business['business_name']) ?></h3>
                                    <span class="business-status <?= $business['is_approved'] ? 'approved' : 'pending' ?>" style="padding: 0.25rem 0.75rem; border-radius: 4px; font-size: 0.875rem; font-weight: 600; <?= $business['is_approved'] ? 'background-color: #d4edda; color: #155724;' : 'background-color: #fff3cd; color: #856404;' ?>">
                                        <?= $business['is_approved'] ? '✓ Approved' : '⏳ Pending Approval' ?>
                                    </span>
                                </div>
                                <p class="business-category" style="color: #666; font-size: 0.9rem; margin-bottom: 0.5rem;">Category: <?= h($business['category_name']) ?></p>
                                <?php if ($business['description']): ?>
                                    <p class="business-description" style="color: #666; margin-bottom: 1rem;"><?= h(substr($business['description'], 0, 100)) ?><?= strlen($business['description']) > 100 ? '...' : '' ?></p>
                                <?php endif; ?>
                                <div class="business-actions" style="display: flex; gap: 0.5rem;">
                                    <a href="<?= baseUrl('/edit-business.php?id=' . $business['business_id']) ?>" class="btn btn-secondary btn-sm" style="padding: 0.5rem 1rem; font-size: 0.875rem;">Edit</a>
                                    <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this business?');">
                                        <input type="hidden" name="action" value="delete_business">
                                        <input type="hidden" name="business_id" value="<?= $business['business_id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" style="padding: 0.5rem 1rem; font-size: 0.875rem;">Delete</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Tab functionality
(function() {
    const tabs = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');
    let currentTab = 0;
    
    function showTab(index) {
        tabs.forEach((tab, i) => {
            if (i === index) {
                tab.classList.add('active');
            } else {
                tab.classList.remove('active');
            }
        });
        
        tabContents.forEach((content, i) => {
            if (i === index) {
                content.classList.add('active');
            } else {
                content.classList.remove('active');
            }
        });
        
        currentTab = index;
    }
    
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', function() {
            showTab(index);
        });
    });
    
    // Check if URL hash indicates which tab to show
    function checkHashAndShowTab() {
        if (window.location.hash === '#water-tab') {
            showTab(1); // Show water information tab (index 1)
        } else if (window.location.hash === '#availability-tab') {
            showTab(2); // Show water availability records tab (index 2)
        } else if (window.location.hash === '#deliveries-tab') {
            showTab(3); // Show water deliveries tab (index 3)
        } else if (window.location.hash === '#businesses-tab') {
            showTab(4); // Show my businesses tab (index 4)
        }
    }
    
    // Check on page load (with a small delay to ensure DOM is ready)
    setTimeout(checkHashAndShowTab, 100);
    
    // Also check if hash changes (in case user navigates with back button)
    window.addEventListener('hashchange', checkHashAndShowTab);
})();

// Handle water question dependencies
(function() {
    function updateQuestionVisibility() {
        const answers = {};
        
        // Collect all current answers
        document.querySelectorAll('.water-response').forEach(function(input) {
            const questionId = input.dataset.questionId;
            if (input.type === 'checkbox') {
                if (!answers[questionId]) answers[questionId] = [];
                if (input.checked) {
                    answers[questionId].push(input.value);
                }
            } else if (input.checked || input.value) {
                answers[questionId] = input.value;
            }
        });
        
        // Show/hide dependent questions
        document.querySelectorAll('.water-question').forEach(function(questionDiv) {
            const dependsOn = questionDiv.dataset.dependsOn;
            const dependsOnValue = questionDiv.dataset.dependsOnValue;
            
            if (dependsOn && dependsOnValue) {
                const answer = answers[dependsOn];
                if (Array.isArray(answer)) {
                    questionDiv.style.display = answer.includes(dependsOnValue) ? 'block' : 'none';
                } else {
                    questionDiv.style.display = (answer == dependsOnValue) ? 'block' : 'none';
                }
            } else {
                questionDiv.style.display = 'block';
            }
        });
    }
    
    // Update visibility when answers change
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('water-response')) {
            updateQuestionVisibility();
        }
    });
    
    // Initial visibility update
    updateQuestionVisibility();
})();
</script>

<?php if ($editMode): ?>
<script>
// Address autocomplete functionality
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

    console.log('Address autocomplete initialized');
    console.log('API URL:', apiUrl);
    console.log('Address search element:', addressSearch);
    console.log('Autocomplete div element:', autocompleteDiv);

    if (!addressSearch || !autocompleteDiv) {
        console.error('Missing required elements:', {addressSearch, autocompleteDiv});
        return;
    }

    // Hide autocomplete when clicking outside (but allow time for clicks on items)
    let hideTimeout = null;
    let isInputFocused = false;
    
    addressSearch.addEventListener('focus', function() {
        isInputFocused = true;
        clearTimeout(hideTimeout);
        // If there's a query and suggestions, show the dropdown
        const query = this.value.trim();
        if (query.length >= 3 && suggestions.length > 0) {
            autocompleteDiv.style.display = 'block';
        }
    });
    
    addressSearch.addEventListener('blur', function() {
        isInputFocused = false;
        // Delay hiding to allow clicks on suggestions
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
    
    // Keep autocomplete visible when hovering over it
    autocompleteDiv.addEventListener('mouseenter', function() {
        clearTimeout(hideTimeout);
    });
    
    autocompleteDiv.addEventListener('mouseleave', function() {
        // Only hide if input is not focused
        if (!isInputFocused) {
            hideTimeout = setTimeout(function() {
                autocompleteDiv.style.display = 'none';
            }, 200);
        }
    });

    addressSearch.addEventListener('input', function() {
        const query = this.value.trim();
        console.log('Input event, query:', query, 'length:', query.length);
        
        // Clear any hide timeouts when user is typing
        clearTimeout(hideTimeout);
        clearTimeout(autocompleteTimeout);
        
        if (query.length < 3) {
            console.log('Query too short, hiding autocomplete');
            autocompleteDiv.style.display = 'none';
            suggestions = []; // Clear suggestions
            return;
        }

        // Debounce API calls
        autocompleteTimeout = setTimeout(function() {
            console.log('Calling fetchAddressSuggestions with:', query);
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
        console.log('Fetching address suggestions from:', url);
        fetch(url)
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response content-type:', response.headers.get('content-type'));
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    return response.text().then(text => {
                        console.error('Non-JSON response:', text.substring(0, 200));
                        throw new Error('Response is not JSON');
                    });
                }
                return response.json();
            })
            .then(data => {
                console.log('API Response:', data);
                if (data.success && data.suggestions && data.suggestions.length > 0) {
                    suggestions = data.suggestions;
                    console.log('Found', suggestions.length, 'suggestions');
                    displaySuggestions(data.suggestions);
                } else {
                    console.log('No suggestions found or empty response');
                    autocompleteDiv.style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Error fetching address suggestions:', error);
                autocompleteDiv.style.display = 'none';
            });
    }

    function displaySuggestions(suggestions) {
        // Clear any hide timeouts when displaying suggestions
        clearTimeout(hideTimeout);
        
        if (suggestions.length === 0) {
            console.log('No suggestions to display');
            autocompleteDiv.style.display = 'none';
            return;
        }

        console.log('Displaying', suggestions.length, 'suggestions');
        autocompleteDiv.innerHTML = '';
        suggestions.forEach((suggestion, index) => {
            const item = document.createElement('div');
            item.className = 'autocomplete-item';
            item.textContent = suggestion.display_name || 'Unknown address';
            item.addEventListener('mousedown', function(e) {
                e.preventDefault(); // Prevent input from losing focus
                selectSuggestion(suggestion);
            });
            
            item.addEventListener('mouseenter', function() {
                selectedIndex = index;
                updateSelection();
            });
            autocompleteDiv.appendChild(item);
        });
        
        autocompleteDiv.style.display = 'block';
        console.log('Autocomplete div display set to block, z-index:', window.getComputedStyle(autocompleteDiv).zIndex);
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
        // Clear any hide timeouts
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
        
        // Clear the search field
        addressSearch.value = '';
        autocompleteDiv.style.display = 'none';
        selectedIndex = -1;
        suggestions = [];
        
        // Focus on street name if empty, otherwise town
        if (streetNameInput && !streetNameInput.value) {
            streetNameInput.focus();
        } else if (townInput && !townInput.value) {
            townInput.focus();
        }
    }
})();
</script>
<?php endif; ?>

<script>
// Address autocomplete for business form
(function() {
    const addressSearch = document.getElementById('business-address-search');
    const autocompleteDiv = document.getElementById('business-address-autocomplete');
    const streetNumberInput = document.getElementById('business_street_number');
    const streetNameInput = document.getElementById('business_street_name');
    const suburbInput = document.getElementById('business_suburb');
    const townInput = document.getElementById('business_town');
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

