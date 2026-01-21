<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('ADMIN');

$db = getDB();
$error = '';
$success = '';

// Handle status filter
$statusFilter = $_GET['status'] ?? 'all';
$validStatuses = ['all', 'new', 'replied', 'closed'];
if (!in_array($statusFilter, $validStatuses)) {
    $statusFilter = 'all';
}

// Build query
$query = "
    SELECT cq.*, 
           u.username, 
           u.name as user_name, 
           u.surname as user_surname,
           COUNT(cr.reply_id) as reply_count
    FROM " . TABLE_PREFIX . "contact_queries cq
    LEFT JOIN " . TABLE_PREFIX . "users u ON cq.user_id = u.user_id
    LEFT JOIN " . TABLE_PREFIX . "contact_replies cr ON cq.query_id = cr.query_id
    WHERE 1=1
";

$params = [];

if ($statusFilter !== 'all') {
    $query .= " AND cq.status = ?";
    $params[] = $statusFilter;
}

$query .= " GROUP BY cq.query_id ORDER BY cq.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$queries = $stmt->fetchAll();

$pageTitle = 'Contact Queries';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="content-area">
        <h1>Contact Queries</h1>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= h($success) ?></div>
        <?php endif; ?>
        
        <!-- Status Filter -->
        <div class="filter-tabs" style="margin-bottom: 20px;">
            <a href="?status=all" class="filter-tab <?= $statusFilter === 'all' ? 'active' : '' ?>">All</a>
            <a href="?status=new" class="filter-tab <?= $statusFilter === 'new' ? 'active' : '' ?>">New</a>
            <a href="?status=replied" class="filter-tab <?= $statusFilter === 'replied' ? 'active' : '' ?>">Replied</a>
            <a href="?status=closed" class="filter-tab <?= $statusFilter === 'closed' ? 'active' : '' ?>">Closed</a>
        </div>
        
        <?php if (empty($queries)): ?>
            <p>No contact queries found.</p>
        <?php else: ?>
            <div class="queries-list">
                <?php foreach ($queries as $query): ?>
                    <div class="query-item" style="border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 4px; background: <?= $query['status'] === 'new' ? '#fff3cd' : '#fff' ?>;">
                        <div style="display: flex; justify-content: space-between; align-items: start;">
                            <div style="flex: 1;">
                                <h3 style="margin-top: 0;">
                                    <a href="<?= baseUrl('/admin/contact-query-detail.php?id=' . $query['query_id']) ?>" style="text-decoration: none; color: #2c5f8d;">
                                        <?= h($query['subject']) ?>
                                    </a>
                                </h3>
                                <p><strong>From:</strong> <?= h($query['name']) ?> (<?= h($query['email']) ?>)</p>
                                <?php if ($query['user_id']): ?>
                                    <p><strong>User:</strong> <?= h($query['username'] ?? ($query['user_name'] . ' ' . $query['user_surname'])) ?></p>
                                <?php endif; ?>
                                <p><strong>Date:</strong> <?= formatDate($query['created_at']) ?></p>
                                <p><strong>Status:</strong> 
                                    <span style="padding: 3px 8px; border-radius: 3px; background: <?= $query['status'] === 'new' ? '#ffc107' : ($query['status'] === 'replied' ? '#17a2b8' : '#6c757d') ?>; color: white; font-size: 12px;">
                                        <?= ucfirst($query['status']) ?>
                                    </span>
                                </p>
                                <?php if ($query['reply_count'] > 0): ?>
                                    <p><strong>Replies:</strong> <?= $query['reply_count'] ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #eee;">
                            <p style="margin: 0;"><strong>Message:</strong></p>
                            <p style="margin: 5px 0 0 0; color: #666;"><?= nl2br(h(substr($query['message'], 0, 200))) ?><?= strlen($query['message']) > 200 ? '...' : '' ?></p>
                        </div>
                        <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #eee;">
                            <a href="<?= baseUrl('/admin/contact-query-detail.php?id=' . $query['query_id']) ?>" class="btn btn-primary" style="display: inline-block;">Reply</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.filter-tabs {
    display: flex;
    gap: 10px;
    border-bottom: 2px solid #ddd;
    padding-bottom: 10px;
}

.filter-tab {
    padding: 8px 16px;
    text-decoration: none;
    color: #666;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    transition: all 0.3s;
}

.filter-tab:hover {
    color: #2c5f8d;
}

.filter-tab.active {
    color: #2c5f8d;
    border-bottom-color: #2c5f8d;
    font-weight: bold;
}
</style>

<?php 
include __DIR__ . '/../includes/footer.php'; 
?>
