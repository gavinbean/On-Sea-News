<?php
require_once __DIR__ . '/../includes/functions.php';
if (!hasRole('ADMIN') && !hasRole('ELECTRICITY_ADMIN')) {
    header('Location: ' . baseUrl('/index.php'));
    exit;
}

$db = getDB();
$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';

// Get filter
$filter = $_GET['filter'] ?? 'all'; // all, open, closed

// Build query based on filter
$whereClause = "1=1";
if ($filter === 'open') {
    $whereClause = "e.status IN ('New Issue', 'Issue Received', 'Issue Updated', 'Issue Resolved')";
} elseif ($filter === 'closed') {
    $whereClause = "e.status = 'Closed'";
}

// Get all issues
$stmt = $db->prepare("
    SELECT 
        e.*,
        u.name as reporter_name,
        u.surname as reporter_surname,
        u.email as reporter_email,
        (SELECT COUNT(*) FROM " . TABLE_PREFIX . "electricity_issue_comments WHERE issue_id = e.issue_id) as comment_count
    FROM " . TABLE_PREFIX . "electricity_issues e
    JOIN " . TABLE_PREFIX . "users u ON e.user_id = u.user_id
    WHERE $whereClause
    ORDER BY e.created_at DESC
");
$stmt->execute();
$issues = $stmt->fetchAll();

// Get selected issue for details view
$selectedIssueId = (int)($_GET['issue_id'] ?? 0);
$selectedIssue = null;
$selectedIssueComments = [];

if ($selectedIssueId > 0) {
    $stmt = $db->prepare("
        SELECT e.*, u.name as reporter_name, u.surname as reporter_surname, u.email as reporter_email
        FROM " . TABLE_PREFIX . "electricity_issues e
        JOIN " . TABLE_PREFIX . "users u ON e.user_id = u.user_id
        WHERE e.issue_id = ?
    ");
    $stmt->execute([$selectedIssueId]);
    $selectedIssue = $stmt->fetch();
    
    if ($selectedIssue) {
        $stmt = $db->prepare("
            SELECT c.*, u.name, u.surname
            FROM " . TABLE_PREFIX . "electricity_issue_comments c
            JOIN " . TABLE_PREFIX . "users u ON c.user_id = u.user_id
            WHERE c.issue_id = ?
            ORDER BY c.created_at ASC
        ");
        $stmt->execute([$selectedIssueId]);
        $selectedIssueComments = $stmt->fetchAll();
    }
}

$pageTitle = 'Manage Electricity Issues';
include __DIR__ . '/../includes/header.php';
?>

<style>
.status-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 4px;
    font-size: 0.9rem;
    font-weight: 600;
}

.status-new { background-color: #e74c3c; color: white; }
.status-received { background-color: #f39c12; color: white; }
.status-updated { background-color: #f39c12; color: white; }
.status-resolved { background-color: #f39c12; color: white; }
.status-closed { background-color: #27ae60; color: white; }

.filter-tabs {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
    border-bottom: 2px solid var(--border-color);
}

.filter-tab {
    padding: 0.75rem 1.5rem;
    background: none;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    font-size: 1rem;
    color: #666;
    transition: all 0.3s;
}

.filter-tab:hover {
    color: var(--primary-color);
}

.filter-tab.active {
    color: var(--primary-color);
    border-bottom-color: var(--primary-color);
    font-weight: 600;
}

.issue-details-panel {
    background: white;
    border-radius: 8px;
    padding: 2rem;
    margin-top: 2rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.issue-detail-row {
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e0e0e0;
}

.issue-detail-row:last-child {
    border-bottom: none;
}

.issue-detail-label {
    font-weight: 600;
    color: #666;
    margin-bottom: 0.25rem;
    display: block;
}

.issue-detail-value {
    color: #333;
}

.comment-item {
    background-color: #f9f9f9;
    padding: 1rem;
    margin-bottom: 1rem;
    border-radius: 4px;
    border-left: 4px solid var(--primary-color);
}

.comment-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
    color: #666;
}

.comment-text {
    color: #333;
    white-space: pre-wrap;
}
</style>

<div class="container">
    <div class="content-area">
        <h1>Manage Electricity Issues</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= h($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        
        <div class="filter-tabs">
            <a href="?filter=all" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">All Issues</a>
            <a href="?filter=open" class="filter-tab <?= $filter === 'open' ? 'active' : '' ?>">Open Issues</a>
            <a href="?filter=closed" class="filter-tab <?= $filter === 'closed' ? 'active' : '' ?>">Closed Issues</a>
        </div>
        
        <?php if (empty($issues)): ?>
            <div class="alert alert-info">
                <p>No electricity issues found.</p>
            </div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Reporter</th>
                        <th>Address</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Comments</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($issues as $issue): ?>
                        <tr>
                            <td>#<?= $issue['issue_id'] ?></td>
                            <td><?= !empty($issue['reference_number']) ? h($issue['reference_number']) : '<em style="color: #999;">-</em>' ?></td>
                            <td><?= h($issue['reporter_name'] . ' ' . $issue['reporter_surname']) ?></td>
                            <td><?= h($issue['address']) ?></td>
                            <td>
                                <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $issue['status'])) ?>">
                                    <?= h($issue['status']) ?>
                                </span>
                            </td>
                            <td><?= date('Y-m-d H:i', strtotime($issue['created_at'])) ?></td>
                            <td><?= (int)$issue['comment_count'] ?></td>
                            <td>
                                <a href="?filter=<?= $filter ?>&issue_id=<?= $issue['issue_id'] ?>" class="btn btn-sm btn-primary">View Details</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <?php if ($selectedIssue): ?>
            <div class="issue-details-panel">
                <h2>Issue Details #<?= $selectedIssue['issue_id'] ?></h2>
                
                <div class="issue-details">
                    <div class="issue-detail-row">
                        <span class="issue-detail-label">Reporter:</span>
                        <span class="issue-detail-value"><?= h($selectedIssue['reporter_name'] . ' ' . $selectedIssue['reporter_surname']) ?></span>
                    </div>
                    
                    <div class="issue-detail-row">
                        <span class="issue-detail-label">Email:</span>
                        <span class="issue-detail-value"><?= h($selectedIssue['reporter_email']) ?></span>
                    </div>
                    
                    <div class="issue-detail-row">
                        <span class="issue-detail-label">Address:</span>
                        <span class="issue-detail-value"><?= h($selectedIssue['address']) ?></span>
                    </div>
                    
                    <div class="issue-detail-row">
                        <span class="issue-detail-label">Reference Number:</span>
                        <span class="issue-detail-value"><?= !empty($selectedIssue['reference_number']) ? h($selectedIssue['reference_number']) : '<em style="color: #999;">Not set</em>' ?></span>
                    </div>
                    
                    <div class="issue-detail-row">
                        <span class="issue-detail-label">Status:</span>
                        <span class="issue-detail-value">
                            <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $selectedIssue['status'])) ?>">
                                <?= h($selectedIssue['status']) ?>
                            </span>
                        </span>
                    </div>
                    
                    <div class="issue-detail-row">
                        <span class="issue-detail-label">Description:</span>
                        <span class="issue-detail-value"><?= nl2br(h($selectedIssue['description'])) ?></span>
                    </div>
                    
                    <div class="issue-detail-row">
                        <span class="issue-detail-label">Created:</span>
                        <span class="issue-detail-value"><?= date('Y-m-d H:i:s', strtotime($selectedIssue['created_at'])) ?></span>
                    </div>
                    
                    <?php if ($selectedIssue['updated_at'] != $selectedIssue['created_at']): ?>
                    <div class="issue-detail-row">
                        <span class="issue-detail-label">Last Updated:</span>
                        <span class="issue-detail-value"><?= date('Y-m-d H:i:s', strtotime($selectedIssue['updated_at'])) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div style="margin-top: 2rem; padding-top: 2rem; border-top: 2px solid var(--border-color);">
                    <h3>Update Issue</h3>
                    <form method="POST" action="../handle-electricity-issue.php">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="issue_id" value="<?= $selectedIssue['issue_id'] ?>">
                        
                        <div class="form-group">
                            <label for="reference_number">Reference Number:</label>
                            <input type="text" 
                                   id="reference_number" 
                                   name="reference_number" 
                                   value="<?= h($selectedIssue['reference_number'] ?? '') ?>" 
                                   placeholder="Enter reference number"
                                   style="width: 100%; max-width: 400px; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 4px;">
                            <small style="display: block; margin-top: 0.5rem; color: #666;">
                                Add or update the reference number for this issue.
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label for="update_status">Status:</label>
                            <select id="update_status" name="status" style="width: 100%; max-width: 400px; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 4px;">
                                <option value="New Issue" <?= $selectedIssue['status'] === 'New Issue' ? 'selected' : '' ?>>New Issue</option>
                                <option value="Issue Received" <?= $selectedIssue['status'] === 'Issue Received' ? 'selected' : '' ?>>Issue Received</option>
                                <option value="Issue Updated" <?= $selectedIssue['status'] === 'Issue Updated' ? 'selected' : '' ?>>Issue Updated</option>
                                <option value="Issue Resolved" <?= $selectedIssue['status'] === 'Issue Resolved' ? 'selected' : '' ?>>Issue Resolved</option>
                                <option value="Closed" <?= $selectedIssue['status'] === 'Closed' ? 'selected' : '' ?>>Closed</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="admin_comment">Add Comment:</label>
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
                
                <div style="margin-top: 2rem; padding-top: 2rem; border-top: 2px solid var(--border-color);">
                    <h3>Progress Notes (<?= count($selectedIssueComments) ?>)</h3>
                    
                    <?php if (empty($selectedIssueComments)): ?>
                        <p style="color: #666; font-style: italic;">No progress notes yet.</p>
                    <?php else: ?>
                        <?php foreach ($selectedIssueComments as $comment): ?>
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
            </div>
        <?php endif; ?>
    </div>
</div>

<?php 
$hideAdverts = true;
include __DIR__ . '/../includes/footer.php'; 
?>
