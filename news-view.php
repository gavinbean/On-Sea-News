<?php
require_once 'includes/functions.php';

$newsId = $_GET['id'] ?? 0;
$db = getDB();

$stmt = $db->prepare("
    SELECT n.*, u.name, u.surname, u.username
    FROM " . TABLE_PREFIX . "news n
    JOIN " . TABLE_PREFIX . "users u ON n.author_id = u.user_id
    WHERE n.news_id = ? AND n.published = 1
");
$stmt->execute([$newsId]);
$news = $stmt->fetch();

if (!$news) {
    header('HTTP/1.0 404 Not Found');
    include '404.php';
    exit;
}

$pageTitle = $news['title'];
include 'includes/header.php';
?>

<div class="container">
    <div class="content-area">
        <article class="news-item">
            <div class="news-item-header">
                <h1><?= h($news['title']) ?></h1>
                <div class="news-meta" style="font-size: 0.9rem; color: #666; margin-top: 0.5rem; margin-bottom: 1rem;">
                    <?php 
                    $showDate = !isset($news['show_publish_date']) || $news['show_publish_date'];
                    $showAuthor = !isset($news['show_author']) || $news['show_author'];
                    ?>
                    <?php if ($showDate && $news['published_at']): ?>
                        <span>Published: <?= date('F j, Y', strtotime($news['published_at'])) ?></span>
                    <?php endif; ?>
                    <?php if ($showAuthor): ?>
                        <?php if ($showDate && $news['published_at']): ?> | <?php endif; ?>
                        <span>By <?= h($news['name'] . ' ' . $news['surname']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="news-item-content">
                <?php if ($news['featured_image']): ?>
                    <img src="<?= baseUrl('/' . $news['featured_image']) ?>" alt="<?= h($news['title']) ?>" style="max-width: 100%; margin-bottom: 1rem;">
                <?php endif; ?>
                <div class="news-content">
                    <?php
                    // If content contains HTML tags (from TinyMCE), render as-is.
                    // Otherwise, treat as plain text and preserve line breaks.
                    $content = $news['content'];
                    if (strpos($content, '<') !== false) {
                        echo $content;
                    } else {
                        echo nl2br(h($content));
                    }
                    ?>
                </div>
            </div>
            <div class="news-item-footer">
                <a href="<?= baseUrl('/index.php') ?>" class="btn btn-secondary">Back to News</a>
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
<script>
// Force list indentation - override any inline styles from TinyMCE
(function() {
    function fixListIndentation() {
        const newsContent = document.querySelector('.news-content');
        if (!newsContent) {
            console.warn('List fix: .news-content not found');
            return;
        }
        
        const lists = newsContent.querySelectorAll('ul, ol');
        console.log('List fix: Found', lists.length, 'lists');
        
        if (lists.length === 0) {
            // Try alternative selectors
            const altLists = document.querySelectorAll('.news-item-content ul, .news-item-content ol');
            console.log('List fix: Found', altLists.length, 'lists in .news-item-content');
            altLists.forEach(function(list) {
                fixList(list);
            });
            return;
        }
        
        lists.forEach(function(list) {
            fixList(list);
        });
    }
    
    function fixList(list) {
        // Log current state
        const currentPadding = window.getComputedStyle(list).paddingLeft;
        console.log('List fix: Current padding-left:', currentPadding, 'for', list.tagName);
        
        // Completely remove all inline padding/margin styles
        list.style.removeProperty('padding');
        list.style.removeProperty('padding-left');
        list.style.removeProperty('padding-right');
        list.style.removeProperty('padding-top');
        list.style.removeProperty('padding-bottom');
        list.style.removeProperty('margin');
        list.style.removeProperty('margin-left');
        
        // Force padding-left using setProperty with important flag
        list.style.setProperty('padding-left', '40px', 'important');
        list.style.setProperty('margin', '1rem 0', 'important');
        
        // Verify it worked
        const newPadding = window.getComputedStyle(list).paddingLeft;
        console.log('List fix: New padding-left:', newPadding, 'for', list.tagName);
        
        // Also fix any nested lists
        const nestedLists = list.querySelectorAll('ul, ol');
        nestedLists.forEach(function(nestedList) {
            nestedList.style.removeProperty('padding');
            nestedList.style.removeProperty('padding-left');
            nestedList.style.setProperty('padding-left', '40px', 'important');
        });
    }
    
    // Run multiple times to catch all cases
    function runFix() {
        fixListIndentation();
        setTimeout(fixListIndentation, 50);
        setTimeout(fixListIndentation, 200);
        setTimeout(fixListIndentation, 500);
        setTimeout(fixListIndentation, 1000);
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', runFix);
    } else {
        runFix();
    }
    
    // Also run when page is fully loaded
    window.addEventListener('load', runFix);
    
    // Use MutationObserver to catch dynamically added content
    const observer = new MutationObserver(function(mutations) {
        fixListIndentation();
    });
    
    const newsContent = document.querySelector('.news-content') || document.querySelector('.news-item-content');
    if (newsContent) {
        observer.observe(newsContent, {
            childList: true,
            subtree: true
        });
    }
})();
</script>

<?php include 'includes/footer.php'; ?>

