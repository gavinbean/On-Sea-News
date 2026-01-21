<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$issueId = (int)($_GET['issue_id'] ?? 0);

if ($issueId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid issue ID']);
    exit;
}

$db = getDB();
$userId = getCurrentUserId();
$isElectricityAdmin = hasRole('ELECTRICITY_ADMIN') || hasRole('ADMIN');

// Get issue details
$stmt = $db->prepare("
    SELECT e.*, u.name as reporter_name, u.surname as reporter_surname, u.email as reporter_email
    FROM " . TABLE_PREFIX . "electricity_issues e
    JOIN " . TABLE_PREFIX . "users u ON e.user_id = u.user_id
    WHERE e.issue_id = ?
");
$stmt->execute([$issueId]);
$issue = $stmt->fetch();

if (!$issue) {
    echo json_encode(['success' => false, 'message' => 'Issue not found']);
    exit;
}

// Get comments
$stmt = $db->prepare("
    SELECT c.*, u.name, u.surname
    FROM " . TABLE_PREFIX . "electricity_issue_comments c
    JOIN " . TABLE_PREFIX . "users u ON c.user_id = u.user_id
    WHERE c.issue_id = ?
    ORDER BY c.created_at ASC
");
$stmt->execute([$issueId]);
$comments = $stmt->fetchAll();

// Check if user is admin
$isAdmin = hasRole('ADMIN') || hasRole('ELECTRICITY_ADMIN');

// Build HTML
ob_start();
?>

<div class="issue-details">
    <?php if ($isAdmin): ?>
    <div class="issue-detail-row">
        <span class="issue-detail-label">Name:</span>
        <span class="issue-detail-value"><?= h($issue['reporter_name'] . ' ' . $issue['reporter_surname']) ?></span>
    </div>
    
    <div class="issue-detail-row">
        <span class="issue-detail-label">Address:</span>
        <span class="issue-detail-value"><?= h($issue['address']) ?></span>
    </div>
    <?php endif; ?>
    
    <?php if ($isAdmin): ?>
    <div class="issue-detail-row">
        <span class="issue-detail-label">Reference Number:</span>
        <span class="issue-detail-value"><?= !empty($issue['reference_number']) ? h($issue['reference_number']) : '<em style="color: #999;">Not set</em>' ?></span>
    </div>
    <?php endif; ?>
    
    <div class="issue-detail-row">
        <span class="issue-detail-label">Status:</span>
        <span class="issue-detail-value">
            <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $issue['status'])) ?>">
                <?= h($issue['status']) ?>
            </span>
        </span>
    </div>
    
    <div class="issue-detail-row">
        <span class="issue-detail-label">Description:</span>
        <span class="issue-detail-value"><?= nl2br(h($issue['description'])) ?></span>
    </div>
    
    <div class="issue-detail-row">
        <span class="issue-detail-label">Created:</span>
        <span class="issue-detail-value"><?= date('Y-m-d H:i:s', strtotime($issue['created_at'])) ?></span>
    </div>
    
    <?php if ($issue['updated_at'] != $issue['created_at']): ?>
    <div class="issue-detail-row">
        <span class="issue-detail-label">Last Updated:</span>
        <span class="issue-detail-value"><?= date('Y-m-d H:i:s', strtotime($issue['updated_at'])) ?></span>
    </div>
    <?php endif; ?>
</div>

<?php if ($isElectricityAdmin): ?>
<div class="admin-actions" style="margin-top: 2rem; padding-top: 2rem; border-top: 2px solid var(--border-color);">
    <h3>Admin Actions</h3>
    <form id="update-issue-form" method="POST" action="handle-electricity-issue.php" style="margin-bottom: 1rem;">
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="issue_id" value="<?= $issueId ?>">
        
        <div class="form-group">
            <label for="reference_number" class="issue-detail-label">Reference Number:</label>
            <input type="text" 
                   id="reference_number" 
                   name="reference_number" 
                   value="<?= h($issue['reference_number'] ?? '') ?>" 
                   placeholder="Enter reference number"
                   style="width: 100%; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 4px;">
            <small style="display: block; margin-top: 0.5rem; color: #666;">
                Add or update the reference number for this issue.
            </small>
        </div>
        
        <div class="form-group">
            <label for="update_status" class="issue-detail-label">Update Status:</label>
            <select id="update_status" name="status" style="width: 100%; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 4px;">
                <option value="New Issue" <?= $issue['status'] === 'New Issue' ? 'selected' : '' ?>>New Issue</option>
                <option value="Issue Received" <?= $issue['status'] === 'Issue Received' ? 'selected' : '' ?>>Issue Received</option>
                <option value="Issue Updated" <?= $issue['status'] === 'Issue Updated' ? 'selected' : '' ?>>Issue Updated</option>
                <option value="Issue Resolved" <?= $issue['status'] === 'Issue Resolved' ? 'selected' : '' ?>>Issue Resolved</option>
                <option value="Closed" <?= $issue['status'] === 'Closed' ? 'selected' : '' ?>>Closed</option>
            </select>
        </div>
        
        <div class="form-group">
            <label for="admin_comment" class="issue-detail-label">Add Comment:</label>
            <textarea id="admin_comment" 
                      name="comment" 
                      rows="4" 
                      style="width: 100%; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 4px; font-family: inherit;"></textarea>
            <small style="display: block; margin-top: 0.5rem; color: #666;">
                Add a progress note or update about this issue.
            </small>
        </div>
        
        <div class="form-group">
            <button type="submit" class="btn btn-primary">Update Issue</button>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="comments-section">
    <h3>Progress Notes (<?= count($comments) ?>)</h3>
    
    <?php if (empty($comments)): ?>
        <p style="color: #666; font-style: italic;">No progress notes yet.</p>
    <?php else: ?>
        <?php foreach ($comments as $comment): ?>
            <div class="comment-item">
                <div class="comment-header">
                    <strong><?= h($comment['name'] . ' ' . $comment['surname']) ?></strong>
                    <span><?= date('Y-m-d H:i:s', strtotime($comment['created_at'])) ?></span>
                </div>
                <div class="comment-text"><?= nl2br(h($comment['comment_text'])) ?></div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
// Handle form submission via AJAX
document.getElementById('update-issue-form')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('handle-electricity-issue.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Issue updated successfully!');
            // Reload issue details
            viewIssueDetails(<?= $issueId ?>);
            // Reload page to refresh map
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            alert('Error updating issue: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating issue');
    });
});
</script>

<?php
$html = ob_get_clean();

echo json_encode([
    'success' => true,
    'html' => $html
]);
?>
