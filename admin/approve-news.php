<?php
require_once __DIR__ . '/../includes/functions.php';
requireAnyRole(['ADMIN', 'PUBLISHER']);

$pageTitle = 'Approve News Submissions';
include __DIR__ . '/../includes/header.php';

$db = getDB();
$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newsId = (int)($_POST['news_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    
    if ($newsId > 0 && in_array($action, ['approve', 'reject'])) {
        try {
            if ($action === 'approve') {
                // Check if new columns exist
                $columnsExist = false;
                try {
                    $testStmt = $db->query("SELECT pending_approval FROM " . TABLE_PREFIX . "news LIMIT 1");
                    $columnsExist = true;
                } catch (Exception $e) {
                    $columnsExist = false;
                }
                
                if ($columnsExist) {
                    // Approve and publish the news item
                    $stmt = $db->prepare("
                        UPDATE " . TABLE_PREFIX . "news 
                        SET published = 1, pending_approval = 0, published_at = NOW()
                        WHERE news_id = ?
                    ");
                } else {
                    // Fallback: just publish
                    $stmt = $db->prepare("
                        UPDATE " . TABLE_PREFIX . "news 
                        SET published = 1, published_at = NOW()
                        WHERE news_id = ?
                    ");
                }
                $stmt->execute([$newsId]);
                $message = 'News item approved and published successfully.';
            } else {
                // Reject the news item (delete it)
                $stmt = $db->prepare("DELETE FROM " . TABLE_PREFIX . "news WHERE news_id = ?");
                $stmt->execute([$newsId]);
                $message = 'News item rejected and deleted.';
            }
            
            // Redirect to prevent resubmission
            header('Location: ' . baseUrl('/admin/approve-news.php?message=' . urlencode($message)));
            exit;
        } catch (Exception $e) {
            $error = 'Error processing news item: ' . $e->getMessage();
            error_log('News approval error: ' . $e->getMessage());
        }
    }
}

// Get all pending news items
// Check if pending_approval column exists
$columnsExist = false;
try {
    $testStmt = $db->query("SELECT pending_approval FROM " . TABLE_PREFIX . "news LIMIT 1");
    $columnsExist = true;
} catch (Exception $e) {
    $columnsExist = false;
}

if ($columnsExist) {
    $stmt = $db->prepare("
        SELECT n.*, u.username, u.name, u.surname, u.email
        FROM " . TABLE_PREFIX . "news n
        JOIN " . TABLE_PREFIX . "users u ON n.author_id = u.user_id
        WHERE n.pending_approval = 1
        ORDER BY n.created_at DESC
    ");
} else {
    // Fallback: show unpublished news items
    $stmt = $db->prepare("
        SELECT n.*, u.username, u.name, u.surname, u.email
        FROM " . TABLE_PREFIX . "news n
        JOIN " . TABLE_PREFIX . "users u ON n.author_id = u.user_id
        WHERE n.published = 0
        ORDER BY n.created_at DESC
    ");
}
$stmt->execute();
$pendingNews = $stmt->fetchAll();
?>

<div class="container">
    <div class="content-area">
        <h1>Approve News Submissions</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= h($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        
        <?php if (empty($pendingNews)): ?>
            <div class="alert alert-info">
                <p>No news items pending approval.</p>
            </div>
        <?php else: ?>
            <?php foreach ($pendingNews as $news): ?>
                <div class="news-item" style="border: 1px solid #ddd; padding: 1.5rem; margin-bottom: 2rem; border-radius: 8px;">
                    <h2><?= h($news['title']) ?></h2>
                    
                    <div class="news-meta" style="margin-bottom: 1rem; color: #666; font-size: 0.9rem;">
                        <p><strong>Submitted by:</strong> <?= h($news['name'] . ' ' . $news['surname']) ?> (<?= h($news['username']) ?>)</p>
                        <p><strong>Email:</strong> <?= h($news['email']) ?></p>
                        <p><strong>Submitted:</strong> <?= date('Y-m-d H:i:s', strtotime($news['created_at'])) ?></p>
                        <?php if (isset($news['show_publish_date'])): ?>
                            <p><strong>Show publish date:</strong> <?= $news['show_publish_date'] ? 'Yes' : 'No' ?></p>
                        <?php endif; ?>
                        <?php if (isset($news['show_author'])): ?>
                            <p><strong>Show author:</strong> <?= $news['show_author'] ? 'Yes' : 'No' ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($news['excerpt']): ?>
                        <div class="news-excerpt" style="margin-bottom: 1rem; font-style: italic; color: #555;">
                            <?= h($news['excerpt']) ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="news-content" style="margin-bottom: 1.5rem;">
                        <?= $news['content'] ?>
                    </div>
                    
                    <div class="news-actions" style="display: flex; gap: 1rem;">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="news_id" value="<?= $news['news_id'] ?>">
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" class="btn btn-primary" onclick="return confirm('Are you sure you want to approve and publish this news item?');">Approve & Publish</button>
                        </form>
                        
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="news_id" value="<?= $news['news_id'] ?>">
                            <input type="hidden" name="action" value="reject">
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to reject and delete this news item? This action cannot be undone.');">Reject & Delete</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
