<?php
require_once __DIR__ . '/includes/functions.php';

$faqId = $_GET['id'] ?? 0;
$db = getDB();

$stmt = $db->prepare("
    SELECT * FROM " . TABLE_PREFIX . "faq
    WHERE faq_id = ? AND is_active = 1
");
$stmt->execute([$faqId]);
$faq = $stmt->fetch();

if (!$faq) {
    header('HTTP/1.0 404 Not Found');
    redirect(baseUrl('/faq.php'));
    exit;
}

$pageTitle = $faq['question'];
include __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="content-area">
        <article class="news-item">
            <div class="news-item-header">
                <h1><?= h($faq['question']) ?></h1>
                <div class="news-meta" style="font-size: 0.9rem; color: #666; margin-top: 0.5rem; margin-bottom: 1rem;">
                    <span>Updated: <?= formatDate($faq['updated_at'] ?? $faq['created_at']) ?></span>
                </div>
            </div>
            <div class="news-item-content">
                <?php if (!empty($faq['excerpt'])): ?>
                    <div class="news-content" style="margin-bottom: 1.5rem; padding: 1rem; background-color: #f5f5f5; border-left: 4px solid var(--primary-color);">
                        <?= $faq['excerpt'] ?>
                    </div>
                <?php endif; ?>
                <div class="news-content">
                    <?php
                    // If content contains HTML tags (from TinyMCE), render as-is.
                    // Otherwise, treat as plain text and preserve line breaks.
                    $content = $faq['answer'];
                    if (strpos($content, '<') !== false) {
                        echo $content;
                    } else {
                        echo nl2br(h($content));
                    }
                    ?>
                </div>
            </div>
            <div class="news-item-footer">
                <a href="<?= baseUrl('/faq.php') ?>" class="btn btn-secondary">Back to FAQ</a>
            </div>
        </article>
    </div>
</div>

<style>
/* Force list indentation - inject directly into page to override everything */
.news-content ul,
.news-content ol,
.news-item-content ul,
.news-item-content ol {
    padding-left: 40px !important;
    margin: 1rem 0 !important;
}
.news-content ul ul,
.news-content ol ol,
.news-content ul ol,
.news-content ol ul,
.news-item-content ul ul,
.news-item-content ol ol,
.news-item-content ul ol,
.news-item-content ol ul {
    padding-left: 40px !important;
}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
