<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('ADMIN');

$db = getDB();
$message = '';
$error = '';

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $businessId = (int)($_POST['business_id'] ?? 0);
    $userId = getCurrentUserId();
    
    // Get business and owner info before processing
    $stmt = $db->prepare("
        SELECT b.*, u.email as owner_email, u.name as owner_name, u.surname as owner_surname, u.username as owner_username
        FROM " . TABLE_PREFIX . "businesses b
        LEFT JOIN " . TABLE_PREFIX . "users u ON b.user_id = u.user_id
        WHERE b.business_id = ?
    ");
    $stmt->execute([$businessId]);
    $business = $stmt->fetch();
    
    if (!$business) {
        $error = 'Business not found.';
    } elseif ($_POST['action'] === 'approve') {
        $stmt = $db->prepare("
            UPDATE " . TABLE_PREFIX . "businesses 
            SET is_approved = 1, approved_at = NOW(), approved_by = ?
            WHERE business_id = ?
        ");
        $stmt->execute([$userId, $businessId]);
        
        // Send approval email to business owner
        if ($business['owner_email']) {
            require_once __DIR__ . '/../includes/email.php';
            sendBusinessApprovalEmail($business);
        }
        
        $message = 'Business approved successfully.';
    } elseif ($_POST['action'] === 'reject') {
        $rejectionReason = trim($_POST['rejection_reason'] ?? '');
        
        if (empty($rejectionReason)) {
            $error = 'Please provide a reason for rejection.';
        } else {
            // Send rejection email before deleting
            if ($business['owner_email']) {
                require_once __DIR__ . '/../includes/email.php';
                sendBusinessRejectionEmail($business, $rejectionReason);
            }
            
            $stmt = $db->prepare("DELETE FROM " . TABLE_PREFIX . "businesses WHERE business_id = ?");
            $stmt->execute([$businessId]);
            $message = 'Business rejected and deleted.';
        }
    }
}

// Get pending businesses
$stmt = $db->query("
    SELECT b.*, c.category_name, u.username, u.name, u.surname, u.email as user_email
    FROM " . TABLE_PREFIX . "businesses b
    JOIN " . TABLE_PREFIX . "business_categories c ON b.category_id = c.category_id
    LEFT JOIN " . TABLE_PREFIX . "users u ON b.user_id = u.user_id
    WHERE b.is_approved = 0
    ORDER BY b.created_at DESC
");
$pendingBusinesses = $stmt->fetchAll();

// Get approved businesses (for reference)
$stmt = $db->query("
    SELECT b.*, c.category_name, u.username
    FROM " . TABLE_PREFIX . "businesses b
    JOIN " . TABLE_PREFIX . "business_categories c ON b.category_id = c.category_id
    LEFT JOIN " . TABLE_PREFIX . "users u ON b.user_id = u.user_id
    WHERE b.is_approved = 1
    ORDER BY b.approved_at DESC
    LIMIT 20
");
$approvedBusinesses = $stmt->fetchAll();

$pageTitle = 'Approve Businesses';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="content-area">
        <h1>Approve Businesses</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= h($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        
        <div class="pending-businesses">
            <h2>Pending Approval (<?= count($pendingBusinesses) ?>)</h2>
            <?php if (empty($pendingBusinesses)): ?>
                <p>No businesses pending approval.</p>
            <?php else: ?>
                <div class="business-approval-list">
                    <?php foreach ($pendingBusinesses as $business): ?>
                        <div class="business-approval-item">
                            <div class="business-approval-header">
                                <h3><?= h($business['business_name']) ?></h3>
                                <span class="status-badge pending">Pending</span>
                            </div>
                            <div class="business-approval-details">
                                <p><strong>Category:</strong> <?= h($business['category_name']) ?></p>
                                <p><strong>Contact:</strong> <?= h($business['contact_name'] ?: 'N/A') ?></p>
                                <p><strong>Telephone:</strong> <?= h($business['telephone']) ?></p>
                                <?php if ($business['email']): ?>
                                    <p><strong>Email:</strong> <?= h($business['email']) ?></p>
                                <?php endif; ?>
                                <?php if ($business['website']): ?>
                                    <p><strong>Website:</strong> <a href="<?= h($business['website']) ?>" target="_blank"><?= h($business['website']) ?></a></p>
                                <?php endif; ?>
                                <?php if ($business['address']): ?>
                                    <p><strong>Address:</strong> <?= h($business['address']) ?></p>
                                <?php endif; ?>
                                <?php if ($business['description']): ?>
                                    <p><strong>Description:</strong> <?= nl2br(h($business['description'])) ?></p>
                                <?php endif; ?>
                                <p><strong>Submitted by:</strong> <?= h($business['username'] ?: 'Imported') ?> 
                                    (<?= h($business['name'] . ' ' . $business['surname']) ?>)</p>
                                <p><strong>Submitted:</strong> <?= formatDate($business['created_at']) ?></p>
                            </div>
                            <div class="business-approval-actions">
                                <form method="POST" action="" style="display: inline;" id="approve-form-<?= $business['business_id'] ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="business_id" value="<?= $business['business_id'] ?>">
                                    <button type="submit" class="btn btn-primary">Approve</button>
                                </form>
                                <button type="button" class="btn btn-danger" onclick="showRejectDialog(<?= $business['business_id'] ?>, '<?= h(addslashes($business['business_name'])) ?>')">Reject</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="approved-businesses">
            <h2>Recently Approved</h2>
            <?php if (empty($approvedBusinesses)): ?>
                <p>No approved businesses yet.</p>
            <?php else: ?>
                <div class="business-list-simple">
                    <?php foreach ($approvedBusinesses as $business): ?>
                        <div class="business-item-simple">
                            <strong><?= h($business['business_name']) ?></strong> - 
                            <?= h($business['category_name']) ?>
                            <?php if ($business['approved_at']): ?>
                                <span class="approved-date">(Approved: <?= formatDate($business['approved_at'], 'Y-m-d') ?>)</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.pending-businesses, .approved-businesses {
    background-color: var(--white);
    padding: 2rem;
    border-radius: 8px;
    box-shadow: var(--shadow);
    margin-bottom: 2rem;
}

.business-approval-list {
    display: grid;
    gap: 1.5rem;
    margin-top: 1rem;
}

.business-approval-item {
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 1.5rem;
    background-color: var(--bg-color);
}

.business-approval-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--border-color);
}

.business-approval-header h3 {
    margin: 0;
    color: var(--primary-color);
}

.status-badge {
    padding: 0.5rem 1rem;
    border-radius: 4px;
    font-size: 0.875rem;
    font-weight: 600;
}

.status-badge.pending {
    background-color: #fff3cd;
    color: #856404;
}

.business-approval-details {
    margin-bottom: 1rem;
}

.business-approval-details p {
    margin: 0.5rem 0;
    font-size: 0.9rem;
}

.business-approval-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border-color);
}

.business-list-simple {
    margin-top: 1rem;
}

.business-item-simple {
    padding: 0.75rem;
    border-bottom: 1px solid var(--border-color);
    font-size: 0.9rem;
}

.business-item-simple:last-child {
    border-bottom: none;
}

.approved-date {
    color: #666;
    font-size: 0.85rem;
    font-style: italic;
}

/* Rejection Dialog Styles */
.reject-dialog-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.75);
    z-index: 100000;
    justify-content: center;
    align-items: center;
}

.reject-dialog-overlay.active {
    display: flex;
}

.reject-dialog {
    background-color: white;
    padding: 2rem;
    border-radius: 8px;
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.reject-dialog h3 {
    margin-top: 0;
    color: #d32f2f;
}

.reject-dialog textarea {
    width: 100%;
    min-height: 120px;
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-family: inherit;
    font-size: 1rem;
    margin-bottom: 1rem;
}

.reject-dialog-actions {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
}
</style>

<!-- Rejection Dialog -->
<div id="reject-dialog-overlay" class="reject-dialog-overlay">
    <div class="reject-dialog">
        <h3>Reject Business</h3>
        <p>Please provide a reason for rejecting this business:</p>
        <form method="POST" action="" id="reject-form">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="business_id" id="reject-business-id">
            <textarea name="rejection_reason" id="rejection-reason" placeholder="Enter reason for rejection..." required></textarea>
            <div class="reject-dialog-actions">
                <button type="button" class="btn btn-secondary" onclick="closeRejectDialog()">Cancel</button>
                <button type="submit" class="btn btn-danger">Confirm Rejection</button>
            </div>
        </form>
    </div>
</div>

<script>
function showRejectDialog(businessId, businessName) {
    const overlay = document.getElementById('reject-dialog-overlay');
    const form = document.getElementById('reject-form');
    const businessIdInput = document.getElementById('reject-business-id');
    const reasonTextarea = document.getElementById('rejection-reason');
    
    businessIdInput.value = businessId;
    reasonTextarea.value = '';
    overlay.classList.add('active');
    reasonTextarea.focus();
}

function closeRejectDialog() {
    const overlay = document.getElementById('reject-dialog-overlay');
    overlay.classList.remove('active');
    document.getElementById('rejection-reason').value = '';
}

// Close dialog when clicking outside
document.getElementById('reject-dialog-overlay').addEventListener('click', function(e) {
    if (e.target === this) {
        closeRejectDialog();
    }
});
</script>

<?php 
$hideAdverts = true;
include __DIR__ . '/../includes/footer.php'; 
?>

