<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('ADMIN');

$db = getDB();
$error = '';
$success = '';

$queryId = $_GET['id'] ?? 0;

if (!$queryId) {
    header('Location: ' . baseUrl('/admin/contact-queries.php'));
    exit;
}

// Get query details
$stmt = $db->prepare("
    SELECT cq.*, 
           u.username, 
           u.name as user_name, 
           u.surname as user_surname
    FROM " . TABLE_PREFIX . "contact_queries cq
    LEFT JOIN " . TABLE_PREFIX . "users u ON cq.user_id = u.user_id
    WHERE cq.query_id = ?
");
$stmt->execute([$queryId]);
$query = $stmt->fetch();

if (!$query) {
    header('Location: ' . baseUrl('/admin/contact-queries.php'));
    exit;
}

// Get replies
$stmt = $db->prepare("
    SELECT cr.*, 
           u.username, 
           u.name, 
           u.surname
    FROM " . TABLE_PREFIX . "contact_replies cr
    JOIN " . TABLE_PREFIX . "users u ON cr.admin_user_id = u.user_id
    WHERE cr.query_id = ?
    ORDER BY cr.created_at ASC
");
$stmt->execute([$queryId]);
$replies = $stmt->fetchAll();

// Handle reply submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reply') {
    $replyMessage = trim($_POST['reply_message'] ?? '');
    
    if (empty($replyMessage)) {
        $error = 'Please enter a reply message.';
    } else {
        // Save reply to database
        $adminUserId = getCurrentUser()['user_id'];
        
        try {
            $db->beginTransaction();
            
            // Insert reply
            $stmt = $db->prepare("
                INSERT INTO " . TABLE_PREFIX . "contact_replies 
                (query_id, admin_user_id, reply_message) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$queryId, $adminUserId, $replyMessage]);
            
            // Update query status
            $newStatus = $query['status'] === 'new' ? 'replied' : $query['status'];
            $stmt = $db->prepare("
                UPDATE " . TABLE_PREFIX . "contact_queries 
                SET status = ? 
                WHERE query_id = ?
            ");
            $stmt->execute([$newStatus, $queryId]);
            
            $db->commit();
            
            // Send email reply
            require_once __DIR__ . '/../includes/email.php';
            $result = sendContactReplyEmail($queryId, $query['email'], $query['name'], $query['subject'], $query['message'], $replyMessage);
            
            if ($result) {
                $success = 'Reply sent successfully!';
                // Reload query to get updated status
                $stmt = $db->prepare("
                    SELECT cq.*, 
                           u.username, 
                           u.name as user_name, 
                           u.surname as user_surname
                    FROM " . TABLE_PREFIX . "contact_queries cq
                    LEFT JOIN " . TABLE_PREFIX . "users u ON cq.user_id = u.user_id
                    WHERE cq.query_id = ?
                ");
                $stmt->execute([$queryId]);
                $query = $stmt->fetch();
                
                // Reload replies
                $stmt = $db->prepare("
                    SELECT cr.*, 
                           u.username, 
                           u.name, 
                           u.surname
                    FROM " . TABLE_PREFIX . "contact_replies cr
                    JOIN " . TABLE_PREFIX . "users u ON cr.admin_user_id = u.user_id
                    WHERE cr.query_id = ?
                    ORDER BY cr.created_at ASC
                ");
                $stmt->execute([$queryId]);
                $replies = $stmt->fetchAll();
            } else {
                $error = 'Reply saved but email failed to send. Please try again.';
            }
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Error saving reply: ' . $e->getMessage();
            error_log("Contact reply error: " . $e->getMessage());
        }
    }
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $newStatus = $_POST['status'] ?? '';
    $validStatuses = ['new', 'replied', 'closed'];
    
    if (in_array($newStatus, $validStatuses)) {
        $stmt = $db->prepare("
            UPDATE " . TABLE_PREFIX . "contact_queries 
            SET status = ? 
            WHERE query_id = ?
        ");
        $stmt->execute([$newStatus, $queryId]);
        $success = 'Status updated successfully!';
        
        // Reload query
        $stmt = $db->prepare("
            SELECT cq.*, 
                   u.username, 
                   u.name as user_name, 
                   u.surname as user_surname
            FROM " . TABLE_PREFIX . "contact_queries cq
            LEFT JOIN " . TABLE_PREFIX . "users u ON cq.user_id = u.user_id
            WHERE cq.query_id = ?
        ");
        $stmt->execute([$queryId]);
        $query = $stmt->fetch();
    } else {
        $error = 'Invalid status.';
    }
}

$pageTitle = 'Contact Query Details';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="content-area">
        <h1>Contact Query Details</h1>
        
        <p><a href="<?= baseUrl('/admin/contact-queries.php') ?>" class="btn btn-secondary">← Back to Queries</a></p>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= h($success) ?></div>
        <?php endif; ?>
        
        <!-- Query Details -->
        <div class="query-details" style="background: #f5f5f5; padding: 20px; border-radius: 4px; margin-bottom: 20px;">
            <h2 style="margin-top: 0;"><?= h($query['subject']) ?></h2>
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
            
            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
                <h3>Original Message:</h3>
                <div style="background: white; padding: 15px; border-left: 4px solid #2c5f8d; border-radius: 4px;">
                    <?= nl2br(h($query['message'])) ?>
                </div>
            </div>
        </div>
        
        <!-- Replies -->
        <?php if (!empty($replies)): ?>
            <div class="replies-section" style="margin-bottom: 20px;">
                <h2>Replies</h2>
                <?php foreach ($replies as $reply): ?>
                    <div style="background: #e8f4f8; padding: 15px; border-left: 4px solid #17a2b8; border-radius: 4px; margin-bottom: 15px;">
                        <p><strong>From:</strong> <?= h($reply['name'] . ' ' . $reply['surname']) ?> (<?= h($reply['username']) ?>)</p>
                        <p><strong>Date:</strong> <?= formatDate($reply['created_at']) ?></p>
                        <div style="background: white; padding: 15px; border-radius: 4px; margin-top: 10px;">
                            <?= nl2br(h($reply['reply_message'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <!-- Reply Form -->
        <div class="reply-form" style="background: #f5f5f5; padding: 20px; border-radius: 4px; margin-bottom: 20px; border: 2px solid #2c5f8d;">
            <h2 style="color: #2c5f8d; margin-top: 0;">Send Reply</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="reply">
                
                <div class="form-group">
                    <label for="reply_message">Reply Message: <span class="required">*</span></label>
                    <textarea id="reply_message" name="reply_message" rows="8" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: inherit;"></textarea>
                    <small>The original query will be included below your reply in the email.</small>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary" style="padding: 10px 20px; font-size: 1rem; font-weight: 600;">Send Reply</button>
                </div>
            </form>
        </div>
        
        <!-- Status Update -->
        <div class="status-update" style="background: #f5f5f5; padding: 20px; border-radius: 4px;">
            <h2>Update Status</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_status">
                
                <div class="form-group">
                    <label for="status">Status:</label>
                    <select id="status" name="status" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="new" <?= $query['status'] === 'new' ? 'selected' : '' ?>>New</option>
                        <option value="replied" <?= $query['status'] === 'replied' ? 'selected' : '' ?>>Replied</option>
                        <option value="closed" <?= $query['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-secondary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
include __DIR__ . '/../includes/footer.php'; 
?>
