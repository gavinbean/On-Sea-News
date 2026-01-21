<?php
require_once 'includes/functions.php';
requireLogin();

$db = getDB();
$error = '';
$success = '';

// Get current user with location
$currentUser = getCurrentUser();
$currentUserId = $currentUser['user_id'];

// Get user's location from profile
$userLatitude = $currentUser['latitude'] ?? null;
$userLongitude = $currentUser['longitude'] ?? null;

// Get all active delivery companies
$stmt = $db->query("
    SELECT company_id, company_name 
    FROM " . TABLE_PREFIX . "water_delivery_companies 
    WHERE is_active = 1 
    ORDER BY company_name ASC
");
$companies = $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Use current logged-in user and their location
    $userId = $currentUserId;
    // Get fresh user data to ensure we have latest coordinates
    $userData = getCurrentUser();
    $userLatitude = $userData['latitude'] ?? null;
    $userLongitude = $userData['longitude'] ?? null;
    
    $dateOrdered = trim($_POST['date_ordered'] ?? '');
    $dateDelivered = trim($_POST['date_delivered'] ?? '');
    $companyId = trim($_POST['company_id'] ?? '');
    $companyNameOther = trim($_POST['company_name_other'] ?? '');
    $vehicleRegistration = trim($_POST['vehicle_registration'] ?? '');
    $litresDelivered = trim($_POST['litres_delivered'] ?? '');
    $price = trim($_POST['price'] ?? '');
    
    // Validation
    if (empty($dateOrdered)) {
        $error = 'Please enter the date ordered.';
    } elseif (empty($dateDelivered)) {
        $error = 'Please enter the date delivered.';
    } elseif (empty($companyId) && empty($companyNameOther)) {
        $error = 'Please select a company or enter a company name.';
    } elseif ($companyId === 'other' && empty($companyNameOther)) {
        $error = 'Please enter a company name when selecting "Other".';
    } elseif (empty($vehicleRegistration)) {
        $error = 'Please enter the vehicle registration number.';
    } elseif (empty($litresDelivered) || !is_numeric($litresDelivered) || $litresDelivered <= 0) {
        $error = 'Please enter a valid number of litres delivered.';
    } elseif (empty($price) || !is_numeric($price) || $price < 0) {
        $error = 'Please enter a valid price.';
    } else {
        // Validate dates
        $dateOrderedObj = DateTime::createFromFormat('Y-m-d', $dateOrdered);
        $dateDeliveredObj = DateTime::createFromFormat('Y-m-d', $dateDelivered);
        
        if (!$dateOrderedObj || $dateOrderedObj->format('Y-m-d') !== $dateOrdered) {
            $error = 'Invalid date ordered format.';
        } elseif (!$dateDeliveredObj || $dateDeliveredObj->format('Y-m-d') !== $dateDelivered) {
            $error = 'Invalid date delivered format.';
        } else {
            try {
                $db->beginTransaction();
                
                $loggedByUserId = getCurrentUser()['user_id'];
                $deliveryUserId = (int)$userId;
                
                // If "Other" was selected, company_id will be NULL and company_name_other will be used
                $finalCompanyId = ($companyId === 'other' || empty($companyId)) ? null : (int)$companyId;
                $finalCompanyNameOther = ($companyId === 'other' || empty($companyId)) ? $companyNameOther : null;
                
                $stmt = $db->prepare("
                    INSERT INTO " . TABLE_PREFIX . "water_deliveries 
                    (user_id, latitude, longitude, date_ordered, date_delivered, company_id, company_name_other, vehicle_registration, litres_delivered, price, logged_by_user_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $deliveryUserId,
                    $userLatitude,
                    $userLongitude,
                    $dateOrdered,
                    $dateDelivered,
                    $finalCompanyId,
                    $finalCompanyNameOther,
                    $vehicleRegistration,
                    (float)$litresDelivered,
                    (float)$price,
                    $loggedByUserId
                ]);
                
                $db->commit();
                
                $success = 'Water delivery logged successfully!';
                
                // Clear form
                $dateOrdered = '';
                $dateDelivered = '';
                $companyId = '';
                $companyNameOther = '';
                $vehicleRegistration = '';
                $litresDelivered = '';
                $price = '';
                
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Error saving delivery: ' . $e->getMessage();
                error_log("Water delivery error: " . $e->getMessage());
            }
        }
    }
}

$pageTitle = 'Log Water Deliveries';
include 'includes/header.php';
?>

<div class="container">
    <div class="content-area">
        <h1>Log Water Deliveries</h1>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= h($success) ?></div>
        <?php endif; ?>
        
        <div class="auth-container">
            <form method="POST" action="" id="waterDeliveryForm">
                <div class="form-group">
                    <p><strong>Logging delivery for:</strong> <?= h($currentUser['name'] . ' ' . $currentUser['surname']) ?></p>
                </div>
                
                <div class="form-group">
                    <label for="date_ordered">Date Ordered: <span class="required">*</span></label>
                    <input type="date" id="date_ordered" name="date_ordered" value="<?= h($dateOrdered ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="date_delivered">Date Delivered: <span class="required">*</span></label>
                    <input type="date" id="date_delivered" name="date_delivered" value="<?= h($dateDelivered ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="company_id">Company Name: <span class="required">*</span></label>
                    <select id="company_id" name="company_id" required onchange="toggleOtherCompany()">
                        <option value="">-- Select Company --</option>
                        <?php foreach ($companies as $company): ?>
                            <option value="<?= $company['company_id'] ?>" <?= (isset($companyId) && $companyId == $company['company_id']) ? 'selected' : '' ?>>
                                <?= h($company['company_name']) ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="other" <?= (isset($companyId) && $companyId === 'other') ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                
                <div class="form-group" id="other_company_group" style="display: <?= (isset($companyId) && $companyId === 'other') ? 'block' : 'none' ?>;">
                    <label for="company_name_other">Other Company Name: <span class="required">*</span></label>
                    <input type="text" id="company_name_other" name="company_name_other" value="<?= h($companyNameOther ?? '') ?>" placeholder="Enter company name">
                </div>
                
                <div class="form-group">
                    <label for="vehicle_registration">Delivery Vehicle Registration Number: <span class="required">*</span></label>
                    <input type="text" id="vehicle_registration" name="vehicle_registration" value="<?= h($vehicleRegistration ?? '') ?>" required placeholder="e.g., ABC 123 GP">
                </div>
                
                <div class="form-group">
                    <label for="litres_delivered">Litres Delivered: <span class="required">*</span></label>
                    <input type="number" id="litres_delivered" name="litres_delivered" value="<?= h($litresDelivered ?? '') ?>" required min="0.01" step="0.01" placeholder="e.g., 5000.00">
                </div>
                
                <div class="form-group">
                    <label for="price">Price: <span class="required">*</span></label>
                    <input type="number" id="price" name="price" value="<?= h($price ?? '') ?>" required min="0" step="0.01" placeholder="e.g., 500.00">
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Log Delivery</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleOtherCompany() {
    const companySelect = document.getElementById('company_id');
    const otherCompanyGroup = document.getElementById('other_company_group');
    const otherCompanyInput = document.getElementById('company_name_other');
    
    if (companySelect.value === 'other') {
        otherCompanyGroup.style.display = 'block';
        otherCompanyInput.required = true;
    } else {
        otherCompanyGroup.style.display = 'none';
        otherCompanyInput.required = false;
        otherCompanyInput.value = '';
    }
}

// Set default dates to today
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    const dateOrderedInput = document.getElementById('date_ordered');
    const dateDeliveredInput = document.getElementById('date_delivered');
    
    if (!dateOrderedInput.value) {
        dateOrderedInput.value = today;
    }
    if (!dateDeliveredInput.value) {
        dateDeliveredInput.value = today;
    }
});
</script>

<?php 
$hideAdverts = false;
include 'includes/footer.php'; 
?>
