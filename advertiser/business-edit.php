<?php
require_once __DIR__ . '/../includes/functions.php';
requireAnyRole(['ADMIN', 'ADVERTISER']);

$businessId = $_GET['id'] ?? 0;
$db = getDB();
$userId = getCurrentUserId();

// Get business
$stmt = $db->prepare("SELECT * FROM " . TABLE_PREFIX . "businesses WHERE business_id = ? AND user_id = ?");
$stmt->execute([$businessId, $userId]);
$business = $stmt->fetch();

if (!$business) {
    header('Location: /advertiser/businesses.php');
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $businessName = $_POST['business_name'] ?? '';
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $contactName = $_POST['contact_name'] ?? '';
    $telephone = $_POST['telephone'] ?? '';
    $email = $_POST['email'] ?? '';
    $address = $_POST['address'] ?? '';
    $website = $_POST['website'] ?? '';
    $description = $_POST['description'] ?? '';
    $stmt = $db->prepare("
        UPDATE " . TABLE_PREFIX . "businesses 
        SET business_name = ?, category_id = ?, contact_name = ?, telephone = ?, 
            email = ?, address = ?, website = ?, description = ?
        WHERE business_id = ? AND user_id = ?
    ");
    $stmt->execute([$businessName, $categoryId, $contactName, $telephone, $email, $address, $website, $description, $businessId, $userId]);
    $message = 'Business updated successfully.';
    
    // Reload business
    $stmt = $db->prepare("SELECT * FROM " . TABLE_PREFIX . "businesses WHERE business_id = ?");
    $stmt->execute([$businessId]);
    $business = $stmt->fetch();
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
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="business_name">Business Name: <span class="required">*</span></label>
                <input type="text" id="business_name" name="business_name" value="<?= h($business['business_name']) ?>" required>
            </div>
            
            <div class="form-group">
                <label for="category_id">Category: <span class="required">*</span></label>
                <select id="category_id" name="category_id" required>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['category_id'] ?>" <?= $cat['category_id'] == $business['category_id'] ? 'selected' : '' ?>>
                            <?= h($cat['category_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="contact_name">Contact Name:</label>
                <input type="text" id="contact_name" name="contact_name" value="<?= h($business['contact_name']) ?>">
            </div>
            
            <div class="form-group">
                <label for="telephone">Telephone: <span class="required">*</span></label>
                <input type="tel" id="telephone" name="telephone" value="<?= h($business['telephone']) ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?= h($business['email']) ?>">
            </div>
            
            <div class="form-group">
                <label for="address">Address:</label>
                <textarea id="address" name="address" rows="2"><?= h($business['address']) ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="website">Website:</label>
                <input type="url" id="website" name="website" value="<?= h($business['website']) ?>">
            </div>
            
            <div class="form-group">
                <label for="description">Description:</label>
                <textarea id="description" name="description" rows="4"><?= h($business['description']) ?></textarea>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Update Business</button>
                <a href="/advertiser/businesses.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php 
$hideAdverts = true;
include __DIR__ . '/../includes/footer.php'; 
?>



