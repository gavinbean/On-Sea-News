<?php
require_once __DIR__ . '/../includes/functions.php';
requireAnyRole(['ADMIN', 'ADVERTISER']);

$db = getDB();
$userId = getCurrentUserId();
$message = '';
$error = '';

// Get advertiser account
$stmt = $db->prepare("SELECT * FROM " . TABLE_PREFIX . "advertiser_accounts WHERE user_id = ?");
$stmt->execute([$userId]);
$account = $stmt->fetch();

if (!$account) {
    header('Location: /advertiser/dashboard.php');
    exit;
}

// Get user's businesses
$stmt = $db->prepare("
    SELECT * FROM " . TABLE_PREFIX . "businesses
    WHERE user_id = ?
    ORDER BY business_name
");
$stmt->execute([$userId]);
$businesses = $stmt->fetchAll();

if (empty($businesses)) {
    $error = 'You must create a business first. <a href="/advertiser/businesses.php">Create Business</a>';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $businessId = (int)($_POST['business_id'] ?? 0);
    $advertUrl = $_POST['advert_url'] ?? '';
    $advertTitle = $_POST['advert_title'] ?? '';
    $startDate = $_POST['start_date'] ?? date('Y-m-d');
    $monthlyFee = ADVERT_MONTHLY_FEE;
    
    // Calculate end date (end of month)
    $start = new DateTime($startDate);
    $endDate = $start->modify('last day of this month')->format('Y-m-d');
    
    // Handle file upload
    $uploadDir = ADVERT_UPLOAD_PATH;
    $uploadedFile = '';
    
    if (isset($_FILES['advert_image']) && $_FILES['advert_image']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['advert_image']['tmp_name'];
        $fileName = $_FILES['advert_image']['name'];
        $fileSize = $_FILES['advert_image']['size'];
        $fileType = $_FILES['advert_image']['type'];
        
        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (in_array($fileType, $allowedTypes)) {
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $newFileName = uniqid() . '.' . $fileExtension;
            $destPath = $uploadDir . $newFileName;
            
            if (move_uploaded_file($fileTmpPath, $destPath)) {
                $uploadedFile = 'uploads/adverts/' . $newFileName;
            } else {
                $error = 'File upload failed.';
            }
        } else {
            $error = 'Invalid file type. Only JPEG, PNG, and GIF are allowed.';
        }
    } else {
        $error = 'Please upload an advertisement image.';
    }
    
    if (empty($error) && $businessId > 0) {
        try {
            $db->beginTransaction();
            
            // Create advertisement
            $stmt = $db->prepare("
                INSERT INTO " . TABLE_PREFIX . "advertisements 
                (business_id, account_id, advert_image, advert_url, advert_title, start_date, end_date, monthly_fee)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$businessId, $account['account_id'], $uploadedFile, $advertUrl, $advertTitle, $startDate, $endDate, $monthlyFee]);
            
            // Deduct fee from account
            $stmt = $db->prepare("UPDATE " . TABLE_PREFIX . "advertiser_accounts SET balance = balance - ? WHERE account_id = ?");
            $stmt->execute([$monthlyFee, $account['account_id']]);
            
            // Record transaction
            $advertId = $db->lastInsertId();
            $stmt = $db->prepare("
                INSERT INTO " . TABLE_PREFIX . "advert_transactions 
                (account_id, advert_id, amount, transaction_type, description)
                VALUES (?, ?, ?, 'fee', ?)
            ");
            $stmt->execute([$account['account_id'], $advertId, $monthlyFee, "Advertisement fee for " . $startDate]);
            
            $db->commit();
            $message = 'Advertisement created successfully.';
            
            // Reload account
            $stmt = $db->prepare("SELECT * FROM " . TABLE_PREFIX . "advertiser_accounts WHERE account_id = ?");
            $stmt->execute([$account['account_id']]);
            $account = $stmt->fetch();
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Failed to create advertisement. Please try again.';
        }
    }
}

$pageTitle = 'Create Advertisement';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="content-area">
        <h1>Create Advertisement</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= h($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        
        <div class="advert-info">
            <p><strong>Monthly Fee:</strong> R <?= number_format(ADVERT_MONTHLY_FEE, 2) ?></p>
            <p><strong>Account Balance:</strong> R <?= number_format($account['balance'], 2) ?></p>
            <?php if ($account['balance'] < ADVERT_MONTHLY_FEE): ?>
                <p class="alert alert-error">Insufficient balance. Please <a href="/advertiser/dashboard.php">add payment</a> first.</p>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($businesses)): ?>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create">
                
                <div class="form-group">
                    <label for="business_id">Business: <span class="required">*</span></label>
                    <select id="business_id" name="business_id" required>
                        <option value="">Select a business</option>
                        <?php foreach ($businesses as $business): ?>
                            <option value="<?= $business['business_id'] ?>"><?= h($business['business_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="advert_image">Advertisement Image: <span class="required">*</span></label>
                    <input type="file" id="advert_image" name="advert_image" accept="image/jpeg,image/jpg,image/png,image/gif" required>
                    <small>Recommended size: 300x250px or similar. Max file size: 2MB</small>
                </div>
                
                <div class="form-group">
                    <label for="advert_title">Advertisement Title:</label>
                    <input type="text" id="advert_title" name="advert_title">
                </div>
                
                <div class="form-group">
                    <label for="advert_url">Click URL:</label>
                    <input type="url" id="advert_url" name="advert_url" placeholder="https://example.com">
                    <small>URL to redirect to when advertisement is clicked</small>
                </div>
                
                <div class="form-group">
                    <label for="start_date">Start Date: <span class="required">*</span></label>
                    <input type="date" id="start_date" name="start_date" value="<?= date('Y-m-d') ?>" required>
                    <small>Advertisement will be valid until the end of this month</small>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary" <?= $account['balance'] < ADVERT_MONTHLY_FEE ? 'disabled' : '' ?>>Create Advertisement</button>
                    <a href="/advertiser/dashboard.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<style>
.advert-info {
    background-color: var(--white);
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: var(--shadow);
    margin-bottom: 2rem;
}

.advert-info p {
    margin-bottom: 0.5rem;
}

form {
    background-color: var(--white);
    padding: 2rem;
    border-radius: 8px;
    box-shadow: var(--shadow);
}
</style>

<?php 
$hideAdverts = true;
include __DIR__ . '/../includes/footer.php'; 
?>

