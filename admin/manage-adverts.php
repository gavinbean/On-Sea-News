<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('ADMIN');

$pageTitle = 'Manage Business Adverts';
include __DIR__ . '/../includes/header.php';

$db = getDB();
$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';

// Get all businesses with adverts
$stmt = $db->prepare("
    SELECT DISTINCT b.business_id, b.business_name, b.pricing_status, 
           COUNT(ba.advert_id) as advert_count
    FROM " . TABLE_PREFIX . "businesses b
    LEFT JOIN " . TABLE_PREFIX . "business_adverts ba ON b.business_id = ba.business_id
    WHERE b.pricing_status IN ('basic', 'timed', 'events')
    GROUP BY b.business_id, b.business_name, b.pricing_status
    ORDER BY b.business_name ASC
");
$stmt->execute();
$businesses = $stmt->fetchAll();
?>

<div class="container">
    <div class="content-area">
        <h1>Manage Business Adverts</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= h($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        
        <div style="margin-bottom: 2rem;">
            <p>Manage advertising graphics for businesses with paid plans (Basic, Timed, Events).</p>
            <p><a href="<?= baseUrl('/admin/my-businesses-admin.php') ?>" class="btn btn-secondary">Back to My Businesses</a></p>
        </div>
        
        <?php if (empty($businesses)): ?>
            <div class="alert alert-info">
                <p>No businesses with paid advertising plans found.</p>
                <p><a href="<?= baseUrl('/admin/my-businesses-admin.php') ?>" class="btn btn-primary">Create Business</a></p>
            </div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Business Name</th>
                        <th>Pricing Plan</th>
                        <th>Advert Count</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($businesses as $business): ?>
                        <tr>
                            <td><?= h($business['business_name']) ?></td>
                            <td>
                                <span class="badge badge-<?= $business['pricing_status'] === 'basic' ? 'primary' : ($business['pricing_status'] === 'timed' ? 'info' : 'success') ?>">
                                    <?= ucfirst($business['pricing_status']) ?>
                                </span>
                            </td>
                            <td><?= (int)$business['advert_count'] ?></td>
                            <td>
                                <a href="<?= baseUrl('/admin/edit-business-admin.php?id=' . $business['business_id'] . '&tab=advert-graphics') ?>" class="btn btn-sm btn-primary">Manage Adverts</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
