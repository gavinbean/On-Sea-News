<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('ADMIN');

$db = getDB();
$message = '';
$error = '';

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $advertId = (int)($_POST['advert_id'] ?? 0);
    $userId = getCurrentUserId();
    
    // Get advert and business info before processing
    $stmt = $db->prepare("
        SELECT a.*, b.business_name, b.user_id as business_user_id, 
               u.email as owner_email, u.name as owner_name, u.surname as owner_surname, u.username as owner_username
        FROM " . TABLE_PREFIX . "business_adverts a
        JOIN " . TABLE_PREFIX . "businesses b ON a.business_id = b.business_id
        LEFT JOIN " . TABLE_PREFIX . "users u ON b.user_id = u.user_id
        WHERE a.advert_id = ?
    ");
    $stmt->execute([$advertId]);
    $advert = $stmt->fetch();
    
    if (!$advert) {
        $error = 'Advert not found.';
    } elseif ($_POST['action'] === 'approve') {
        $stmt = $db->prepare("
            UPDATE " . TABLE_PREFIX . "business_adverts 
            SET approval_status = 'approved', approved_at = NOW(), approved_by = ?
            WHERE advert_id = ?
        ");
        $stmt->execute([$userId, $advertId]);
        
        // Send approval email to business owner
        if ($advert['owner_email']) {
            require_once __DIR__ . '/../includes/email.php';
            sendAdvertApprovalEmail($advert);
        }
        
        // Check if we should notify followers (only if approved, active, and in date)
        require_once __DIR__ . '/../includes/email_business_adverts.php';
        
        // Get updated advert with all fields
        $stmt = $db->prepare("
            SELECT a.*, b.business_name, b.description, b.telephone, b.email, b.website, b.address
            FROM " . TABLE_PREFIX . "business_adverts a
            JOIN " . TABLE_PREFIX . "businesses b ON a.business_id = b.business_id
            WHERE a.advert_id = ?
        ");
        $stmt->execute([$advertId]);
        $approvedAdvert = $stmt->fetch();
        
        if ($approvedAdvert) {
            $approvedAdvert['approval_status'] = 'approved'; // Ensure it's set
            $business = [
                'business_id' => $approvedAdvert['business_id'],
                'business_name' => $approvedAdvert['business_name'],
                'description' => $approvedAdvert['description'],
                'telephone' => $approvedAdvert['telephone'],
                'email' => $approvedAdvert['email'],
                'website' => $approvedAdvert['website'],
                'address' => $approvedAdvert['address']
            ];
            
            if (shouldNotifyAdvertUpdate($approvedAdvert)) {
                $emailsSent = sendAdvertUpdateEmail($approvedAdvert, $business);
                if ($emailsSent > 0) {
                    $message = 'Advert approved successfully. Notification emails sent to ' . $emailsSent . ' follower(s).';
                } else {
                    $message = 'Advert approved successfully.';
                }
            } else {
                $message = 'Advert approved successfully.';
            }
        } else {
            $message = 'Advert approved successfully.';
        }
    } elseif ($_POST['action'] === 'reject') {
        $rejectionReason = trim($_POST['rejection_reason'] ?? '');
        
        if (empty($rejectionReason)) {
            $error = 'Please provide a reason for rejection.';
        } else {
            $stmt = $db->prepare("
                UPDATE " . TABLE_PREFIX . "business_adverts 
                SET approval_status = 'rejected', rejected_at = NOW(), rejected_by = ?, rejection_reason = ?
                WHERE advert_id = ?
            ");
            $stmt->execute([$userId, $rejectionReason, $advertId]);
            
            // Send rejection email to business owner
            if ($advert['owner_email']) {
                require_once __DIR__ . '/../includes/email.php';
                sendAdvertRejectionEmail($advert, $rejectionReason);
            }
            
            $message = 'Advert rejected successfully.';
        }
    }
}

// Get pending adverts
$stmt = $db->query("
    SELECT a.*, b.business_name, b.user_id as business_user_id,
           u.username, u.name, u.surname, u.email as user_email
    FROM " . TABLE_PREFIX . "business_adverts a
    JOIN " . TABLE_PREFIX . "businesses b ON a.business_id = b.business_id
    LEFT JOIN " . TABLE_PREFIX . "users u ON b.user_id = u.user_id
    WHERE a.approval_status = 'pending'
    ORDER BY a.created_at DESC
");
$pendingAdverts = $stmt->fetchAll();

// Get approved/rejected adverts (for reference)
$stmt = $db->query("
    SELECT a.*, b.business_name, b.user_id as business_user_id,
           u.username, u.name, u.surname, u.email as user_email,
           approver.username as approver_username, approver.name as approver_name,
           rejector.username as rejector_username, rejector.name as rejector_name
    FROM " . TABLE_PREFIX . "business_adverts a
    JOIN " . TABLE_PREFIX . "businesses b ON a.business_id = b.business_id
    LEFT JOIN " . TABLE_PREFIX . "users u ON b.user_id = u.user_id
    LEFT JOIN " . TABLE_PREFIX . "users approver ON a.approved_by = approver.user_id
    LEFT JOIN " . TABLE_PREFIX . "users rejector ON a.rejected_by = rejector.user_id
    WHERE a.approval_status IN ('approved', 'rejected')
    ORDER BY COALESCE(a.approved_at, a.rejected_at) DESC
    LIMIT 50
");
$processedAdverts = $stmt->fetchAll();

$pageTitle = 'Approve Adverts';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="content-area">
        <h1>Approve Adverts</h1>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= h($message) ?></div>
        <?php endif; ?>
        
        <h2>Pending Adverts (<?= count($pendingAdverts) ?>)</h2>
        
        <?php if (empty($pendingAdverts)): ?>
            <p>No pending adverts to review.</p>
        <?php else: ?>
            <div class="advert-list">
                <?php foreach ($pendingAdverts as $advert): ?>
                    <div class="advert-item" style="border: 1px solid #ddd; padding: 1rem; margin-bottom: 1rem; border-radius: 4px;">
                        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                            <div style="flex: 1; min-width: 200px;">
                                <h3><?= h($advert['business_name']) ?></h3>
                                <p><strong>Type:</strong> <?= h(ucfirst($advert['advert_type'])) ?></p>
                                <?php if ($advert['event_title']): ?>
                                    <p><strong>Event:</strong> <?= h($advert['event_title']) ?></p>
                                    <?php if ($advert['event_date']): ?>
                                        <p><strong>Event Date:</strong> <?= h(date('Y-m-d', strtotime($advert['event_date']))) ?></p>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if ($advert['start_date'] || $advert['end_date']): ?>
                                    <p><strong>Dates:</strong> 
                                        <?= $advert['start_date'] ? h(date('Y-m-d', strtotime($advert['start_date']))) : 'No start' ?> - 
                                        <?= $advert['end_date'] ? h(date('Y-m-d', strtotime($advert['end_date']))) : 'No end' ?>
                                    </p>
                                <?php endif; ?>
                                <p><strong>Created:</strong> <?= h(date('Y-m-d H:i', strtotime($advert['created_at']))) ?></p>
                                <p><strong>Business Owner:</strong> <?= h($advert['username'] ?? 'N/A') ?> (<?= h($advert['user_email'] ?? 'N/A') ?>)</p>
                            </div>
                            <div style="flex: 1; min-width: 200px;">
                                <h4>Banner Image:</h4>
                                <img src="<?= baseUrl('/' . $advert['banner_image']) ?>" alt="Banner" style="max-width: 100%; height: auto; border: 1px solid #ddd; border-radius: 4px;">
                                <h4 style="margin-top: 1rem;">Display Image:</h4>
                                <img src="<?= baseUrl('/' . $advert['display_image']) ?>" alt="Display" style="max-width: 100%; height: auto; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                        </div>
                        <div style="margin-top: 1rem; display: flex; gap: 1rem;">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="advert_id" value="<?= $advert['advert_id'] ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn btn-primary">Approve</button>
                            </form>
                            <form method="POST" id="rejectForm<?= $advert['advert_id'] ?>" style="display: inline;">
                                <input type="hidden" name="advert_id" value="<?= $advert['advert_id'] ?>">
                                <input type="hidden" name="action" value="reject">
                                <button type="button" class="btn btn-danger" onclick="showRejectReason(<?= $advert['advert_id'] ?>)">Reject</button>
                                <div id="rejectReason<?= $advert['advert_id'] ?>" style="display: none; margin-top: 0.5rem;">
                                    <textarea name="rejection_reason" placeholder="Please provide a reason for rejection..." required style="width: 100%; min-height: 80px; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px;"></textarea>
                                    <button type="submit" class="btn btn-danger" style="margin-top: 0.5rem;">Confirm Rejection</button>
                                    <button type="button" class="btn btn-secondary" onclick="hideRejectReason(<?= $advert['advert_id'] ?>)" style="margin-top: 0.5rem; margin-left: 0.5rem;">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <h2 style="margin-top: 2rem;">Recently Processed Adverts</h2>
        
        <?php if (empty($processedAdverts)): ?>
            <p>No processed adverts yet.</p>
        <?php else: ?>
            <div class="advert-list">
                <?php foreach ($processedAdverts as $advert): ?>
                    <div class="advert-item" style="border: 1px solid #ddd; padding: 1rem; margin-bottom: 1rem; border-radius: 4px; opacity: 0.7;">
                        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                            <div style="flex: 1; min-width: 200px;">
                                <h3><?= h($advert['business_name']) ?></h3>
                                <p><strong>Status:</strong> 
                                    <span style="color: <?= $advert['approval_status'] === 'approved' ? 'green' : 'red' ?>;">
                                        <?= h(ucfirst($advert['approval_status'])) ?>
                                    </span>
                                </p>
                                <?php if ($advert['approval_status'] === 'approved' && $advert['approver_username']): ?>
                                    <p><strong>Approved by:</strong> <?= h($advert['approver_username']) ?> on <?= h(date('Y-m-d H:i', strtotime($advert['approved_at']))) ?></p>
                                <?php endif; ?>
                                <?php if ($advert['approval_status'] === 'rejected' && $advert['rejector_username']): ?>
                                    <p><strong>Rejected by:</strong> <?= h($advert['rejector_username']) ?> on <?= h(date('Y-m-d H:i', strtotime($advert['rejected_at']))) ?></p>
                                    <?php if ($advert['rejection_reason']): ?>
                                        <p><strong>Reason:</strong> <?= h($advert['rejection_reason']) ?></p>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function showRejectReason(advertId) {
    document.getElementById('rejectReason' + advertId).style.display = 'block';
}

function hideRejectReason(advertId) {
    document.getElementById('rejectReason' + advertId).style.display = 'none';
    document.getElementById('rejectReason' + advertId).querySelector('textarea').value = '';
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
