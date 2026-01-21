<?php
require_once 'includes/functions.php';

$businessId = $_GET['id'] ?? 0;
$db = getDB();

$stmt = $db->prepare("
    SELECT b.*, c.category_name, u.name, u.surname
    FROM " . TABLE_PREFIX . "businesses b
    JOIN " . TABLE_PREFIX . "business_categories c ON b.category_id = c.category_id
    LEFT JOIN " . TABLE_PREFIX . "users u ON b.user_id = u.user_id
    WHERE b.business_id = ? AND b.is_approved = 1
");
$stmt->execute([$businessId]);
$business = $stmt->fetch();

if (!$business) {
    header('HTTP/1.0 404 Not Found');
    echo 'Business not found.';
    exit;
}

// Get advertisements if paid subscription
$advertisements = [];
if ($business['has_paid_subscription']) {
    $today = date('Y-m-d');
    $stmt = $db->prepare("
        SELECT a.*
        FROM " . TABLE_PREFIX . "advertisements a
        JOIN " . TABLE_PREFIX . "advertiser_accounts ac ON a.account_id = ac.account_id
        WHERE a.business_id = ?
        AND a.is_active = 1
        AND a.start_date <= ?
        AND a.end_date >= ?
        AND ac.balance >= 0
        ORDER BY a.created_at DESC
    ");
    $stmt->execute([$businessId, $today, $today]);
    $advertisements = $stmt->fetchAll();
}

$pageTitle = $business['business_name'];
include 'includes/header.php';
?>

<div class="container">
    <div class="content-area">
        <div class="business-detail">
            <h1><?= h($business['business_name']) ?></h1>
            <p class="business-category">Category: <?= h($business['category_name']) ?></p>
            
            <?php if ($business['description']): ?>
                <div class="business-description">
                    <p><?= nl2br(h($business['description'])) ?></p>
                </div>
            <?php endif; ?>
            
            <div class="business-contact">
                <h2>Contact Information</h2>
                <p><strong>Contact:</strong> <?= h($business['contact_name'] ?: $business['name'] . ' ' . $business['surname']) ?></p>
                <p><strong>Telephone:</strong> <?= h($business['telephone']) ?></p>
                <?php if ($business['email']): ?>
                    <p><strong>Email:</strong> <a href="mailto:<?= h($business['email']) ?>"><?= h($business['email']) ?></a></p>
                <?php endif; ?>
                <?php if ($business['address']): ?>
                    <p><strong>Address:</strong> <?= h($business['address']) ?></p>
                <?php endif; ?>
                <?php if ($business['website']): ?>
                    <p><strong>Website:</strong> <a href="<?= h($business['website']) ?>" target="_blank"><?= h($business['website']) ?></a></p>
                <?php endif; ?>
            </div>
            
            <?php if ($business['has_paid_subscription'] && !empty($advertisements)): ?>
                <div class="business-advertisements">
                    <h2>Advertisements</h2>
                    <div class="advert-gallery">
                        <?php foreach ($advertisements as $ad): ?>
                            <div class="advert-item">
                                <a href="<?= baseUrl('/advert-click.php?id=' . $ad['advert_id'] . '&url=' . urlencode($ad['advert_url'] ?: '#')) ?>" target="_blank">
                                    <img src="<?= baseUrl('/' . $ad['advert_image']) ?>" alt="<?= h($ad['advert_title'] ?: 'Advertisement') ?>">
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (isLoggedIn()): ?>
            <div class="business-follow" style="margin-top: 20px; padding: 15px; background: #f5f5f5; border-radius: 4px;">
                <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer;">
                    <input type="checkbox" id="follow-business-<?= $business['business_id'] ?>" style="margin-top: 3px; cursor: pointer;" onchange="toggleBusinessFollow(<?= $business['business_id'] ?>, this.checked)">
                    <div>
                        <strong>Follow</strong>
                        <p style="margin: 5px 0 0 0; font-size: 0.9rem; color: #666;">By ticking Follow I agree to having emails sent to me when adverts are changed</p>
                    </div>
                </label>
            </div>
            <?php endif; ?>
            
            <div class="business-actions" style="margin-top: 20px;">
                <a href="<?= baseUrl('/index.php') ?>" class="btn btn-secondary">Back to Home</a>
            </div>
        </div>
    </div>
</div>

<?php if (isLoggedIn()): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    checkFollowStatus(<?= $business['business_id'] ?>);
});
</script>
<?php endif; ?>

<?php 
$hideAdverts = false;
include 'includes/footer.php'; 
?>


