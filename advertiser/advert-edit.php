<?php
require_once __DIR__ . '/../includes/functions.php';
requireAnyRole(['ADMIN', 'ADVERTISER']);

$advertId = $_GET['id'] ?? 0;
$db = getDB();
$userId = getCurrentUserId();

// Get advertisement with business ownership check
$stmt = $db->prepare("
    SELECT a.*, b.user_id as business_user_id
    FROM " . TABLE_PREFIX . "advertisements a
    JOIN " . TABLE_PREFIX . "businesses b ON a.business_id = b.business_id
    WHERE a.advert_id = ? AND b.user_id = ?
");
$stmt->execute([$advertId, $userId]);
$advert = $stmt->fetch();

if (!$advert) {
    header('Location: /advertiser/dashboard.php');
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $advertUrl = $_POST['advert_url'] ?? '';
    $advertTitle = $_POST['advert_title'] ?? '';
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    $uploadedFile = $advert['advert_image'];
    
    // Handle file upload if new file is provided
    if (isset($_FILES['advert_image']) && $_FILES['advert_image']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['advert_image']['tmp_name'];
        $fileName = $_FILES['advert_image']['name'];
        $fileType = $_FILES['advert_image']['type'];
        
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (in_array($fileType, $allowedTypes)) {
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $newFileName = uniqid() . '.' . $fileExtension;
            $destPath = ADVERT_UPLOAD_PATH . $newFileName;
            
            if (move_uploaded_file($fileTmpPath, $destPath)) {
                // Delete old file
                if (file_exists(__DIR__ . '/../' . $advert['advert_image'])) {
                    unlink(__DIR__ . '/../' . $advert['advert_image']);
                }
                $uploadedFile = 'uploads/graphics/' . $newFileName;
            }
        }
    }
    
    $stmt = $db->prepare("
        UPDATE " . TABLE_PREFIX . "advertisements 
        SET advert_image = ?, advert_url = ?, advert_title = ?, is_active = ?
        WHERE advert_id = ?
    ");
    $stmt->execute([$uploadedFile, $advertUrl, $advertTitle, $isActive, $advertId]);
    $message = 'Advertisement updated successfully.';
    
    // Reload advertisement
    $stmt = $db->prepare("SELECT a.* FROM " . TABLE_PREFIX . "advertisements a WHERE a.advert_id = ?");
    $stmt->execute([$advertId]);
    $advert = $stmt->fetch();
}

$pageTitle = 'Edit Advertisement';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="content-area">
        <h1>Edit Advertisement</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= h($message) ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="form-group">
                <label for="advert_image">Advertisement Image:</label>
                <?php if ($advert['advert_image']): ?>
                    <div class="current-image">
                        <img src="/<?= h($advert['advert_image']) ?>" alt="Current advertisement" style="max-width: 300px; margin-bottom: 1rem;">
                    </div>
                <?php endif; ?>
                <input type="file" id="advert_image" name="advert_image" accept="image/jpeg,image/jpg,image/png,image/gif">
                <small>Leave empty to keep current image</small>
            </div>
            
            <div class="form-group">
                <label for="advert_title">Advertisement Title:</label>
                <input type="text" id="advert_title" name="advert_title" value="<?= h($advert['advert_title']) ?>">
            </div>
            
            <div class="form-group">
                <label for="advert_url">Click URL:</label>
                <input type="url" id="advert_url" name="advert_url" value="<?= h($advert['advert_url']) ?>">
            </div>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" id="is_active" name="is_active" value="1" <?= $advert['is_active'] ? 'checked' : '' ?>>
                    Active
                </label>
            </div>
            
            <div class="form-group">
                <p><strong>Period:</strong> <?= formatDate($advert['start_date']) ?> to <?= formatDate($advert['end_date']) ?></p>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Update Advertisement</button>
                <a href="/advertiser/dashboard.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php 
$hideAdverts = true;
include __DIR__ . '/../includes/footer.php'; 
?>

