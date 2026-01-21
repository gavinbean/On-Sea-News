<?php
/**
 * Unfollow a business - called from email link
 */

require_once 'includes/functions.php';

$token = $_GET['token'] ?? '';
$businessId = (int)($_GET['business_id'] ?? 0);

if (empty($token) || $businessId <= 0) {
    header('HTTP/1.0 400 Bad Request');
    echo 'Invalid request.';
    exit;
}

$db = getDB();

// Verify token and get user_id
$stmt = $db->prepare("
    SELECT user_id 
    FROM " . TABLE_PREFIX . "business_follows 
    WHERE business_id = ? 
    AND MD5(CONCAT(user_id, business_id)) = ?
    LIMIT 1
");
$stmt->execute([$businessId, $token]);
$follow = $stmt->fetch();

if ($follow) {
    // Remove follow
    $stmt = $db->prepare("DELETE FROM " . TABLE_PREFIX . "business_follows WHERE user_id = ? AND business_id = ?");
    $stmt->execute([$follow['user_id'], $businessId]);
    
    $message = 'You have successfully unfollowed this business. You will no longer receive email notifications about their adverts.';
} else {
    $message = 'Invalid or expired link. You may have already unfollowed this business.';
}

$pageTitle = 'Unfollow Business';
include 'includes/header.php';
?>

<div class="container">
    <div class="content-area">
        <div class="alert alert-success">
            <p><?= h($message) ?></p>
            <p><a href="<?= baseUrl('/index.php') ?>" class="btn btn-primary">Return to Home</a></p>
        </div>
    </div>
</div>

<?php 
include 'includes/footer.php'; 
?>
