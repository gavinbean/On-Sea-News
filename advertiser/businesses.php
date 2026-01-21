<?php
require_once __DIR__ . '/../includes/functions.php';
requireAnyRole(['ADMIN', 'ADVERTISER']);

$db = getDB();
$userId = getCurrentUserId();
$message = '';
$error = '';

// Handle business creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $businessName = $_POST['business_name'] ?? '';
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $contactName = $_POST['contact_name'] ?? '';
    $telephone = $_POST['telephone'] ?? '';
    $email = $_POST['email'] ?? '';
    $address = $_POST['address'] ?? '';
    $website = $_POST['website'] ?? '';
    $description = $_POST['description'] ?? '';
    
    if (empty($businessName) || empty($categoryId) || empty($telephone)) {
        $error = 'Business name, category, and telephone are required.';
    } else {
        $stmt = $db->prepare("
            INSERT INTO " . TABLE_PREFIX . "businesses 
            (user_id, category_id, business_name, contact_name, telephone, email, address, website, description)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $categoryId, $businessName, $contactName, $telephone, $email, $address, $website, $description]);
        $message = 'Business created successfully.';
    }
}

// Get all categories
$stmt = $db->query("SELECT * FROM " . TABLE_PREFIX . "business_categories ORDER BY category_name");
$categories = $stmt->fetchAll();

// Get user's businesses
$stmt = $db->prepare("
    SELECT b.*, c.category_name
    FROM " . TABLE_PREFIX . "businesses b
    JOIN " . TABLE_PREFIX . "business_categories c ON b.category_id = c.category_id
    WHERE b.user_id = ?
    ORDER BY b.business_name
");
$stmt->execute([$userId]);
$businesses = $stmt->fetchAll();

$pageTitle = 'My Businesses';
include __DIR__ . '/../includes/header.php';
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
                
                <div class="form-group">
                    <label for="address">Address:</label>
                    <textarea id="address" name="address" rows="2"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="website">Website:</label>
                    <input type="url" id="website" name="website">
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
                <p>No businesses yet.</p>
            <?php else: ?>
                <ul class="business-list">
                    <?php foreach ($businesses as $business): ?>
                        <li>
                            <strong><?= h($business['business_name']) ?></strong> (<?= h($business['category_name']) ?>)
                            <br>
                            <a href="/advertiser/business-edit.php?id=<?= $business['business_id'] ?>" class="btn btn-secondary btn-small">Edit</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
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

.business-list {
    list-style: none;
    padding: 0;
}

.business-list li {
    padding: 1rem;
    border-bottom: 1px solid var(--border-color);
    margin-bottom: 0.5rem;
}

.btn-small {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    margin-top: 0.5rem;
}
</style>

<?php 
$hideAdverts = true;
include __DIR__ . '/../includes/footer.php'; 
?>




