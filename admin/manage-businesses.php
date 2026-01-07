<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('ADMIN');

$db = getDB();
$message = '';
$error = '';

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $businessId = (int)($_POST['business_id'] ?? 0);
    
    if ($businessId > 0) {
        $stmt = $db->prepare("DELETE FROM " . TABLE_PREFIX . "businesses WHERE business_id = ?");
        $stmt->execute([$businessId]);
        $message = 'Business deleted successfully.';
    } else {
        $error = 'Invalid business ID.';
    }
}

// Get filter parameters
$filterApproved = $_GET['filter'] ?? 'all'; // 'all', 'approved', 'pending'
$searchQuery = trim($_GET['search'] ?? '');

// Build query
$whereConditions = [];
$params = [];

if ($filterApproved === 'approved') {
    $whereConditions[] = "b.is_approved = 1";
} elseif ($filterApproved === 'pending') {
    $whereConditions[] = "b.is_approved = 0";
}

if (!empty($searchQuery)) {
    $whereConditions[] = "(b.business_name LIKE ? OR b.contact_name LIKE ? OR b.email LIKE ? OR c.category_name LIKE ?)";
    $searchParam = '%' . $searchQuery . '%';
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get all businesses
$sql = "
    SELECT b.*, c.category_name, u.username, u.name as owner_name, u.surname as owner_surname
    FROM " . TABLE_PREFIX . "businesses b
    JOIN " . TABLE_PREFIX . "business_categories c ON b.category_id = c.category_id
    LEFT JOIN " . TABLE_PREFIX . "users u ON b.user_id = u.user_id
    $whereClause
    ORDER BY c.category_name ASC, b.business_name ASC
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$businesses = $stmt->fetchAll();

$pageTitle = 'Manage Businesses';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="content-area">
        <h1>Manage Businesses</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= h($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        
        <div class="business-filters">
            <form method="GET" action="" style="display: flex; gap: 1rem; align-items: flex-end; margin-bottom: 2rem;">
                <div class="form-group" style="margin: 0;">
                    <label for="filter">Filter:</label>
                    <select id="filter" name="filter" onchange="this.form.submit()">
                        <option value="all" <?= $filterApproved === 'all' ? 'selected' : '' ?>>All Businesses</option>
                        <option value="approved" <?= $filterApproved === 'approved' ? 'selected' : '' ?>>Approved Only</option>
                        <option value="pending" <?= $filterApproved === 'pending' ? 'selected' : '' ?>>Pending Only</option>
                    </select>
                </div>
                
                <div class="form-group" style="margin: 0; flex: 1;">
                    <label for="search">Search:</label>
                    <input type="text" id="search" name="search" value="<?= h($searchQuery) ?>" placeholder="Search by name, contact, email, or category">
                </div>
                
                <div class="form-group" style="margin: 0;">
                    <button type="submit" class="btn btn-secondary">Search</button>
                    <?php if (!empty($searchQuery) || $filterApproved !== 'all'): ?>
                        <a href="<?= baseUrl('/admin/manage-businesses.php') ?>" class="btn btn-secondary">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <div class="businesses-list">
            <h2>All Businesses (<?= count($businesses) ?>)</h2>
            <?php if (empty($businesses)): ?>
                <p>No businesses found.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Business Name</th>
                            <th>Category</th>
                            <th>Contact</th>
                            <th>Email</th>
                            <th>Owner</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($businesses as $business): ?>
                            <tr>
                                <td><strong><?= h($business['business_name']) ?></strong></td>
                                <td><?= h($business['category_name']) ?></td>
                                <td><?= h($business['contact_name'] ?: 'N/A') ?></td>
                                <td><?= h($business['email'] ?: 'N/A') ?></td>
                                <td>
                                    <?php if ($business['username']): ?>
                                        <?= h($business['owner_name'] . ' ' . $business['owner_surname']) ?><br>
                                        <small>(<?= h($business['username']) ?>)</small>
                                    <?php else: ?>
                                        <em>Imported</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($business['is_approved']): ?>
                                        <span style="color: green; font-weight: 600;">✓ Approved</span>
                                    <?php else: ?>
                                        <span style="color: orange; font-weight: 600;">⏳ Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= formatDate($business['created_at'], 'Y-m-d') ?></td>
                                <td>
                                    <a href="<?= baseUrl('/admin/edit-business.php?id=' . $business['business_id']) ?>" class="btn btn-sm btn-primary">Edit</a>
                                    <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this business? This action cannot be undone.');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="business_id" value="<?= $business['business_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php 
$hideAdverts = true;
include __DIR__ . '/../includes/footer.php'; 
?>


