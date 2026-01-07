<?php
require_once 'includes/functions.php';

$pageTitle = 'Home';
$db = getDB();

// Get published news items (pinned first, then latest first)
$stmt = $db->prepare("
    SELECT n.*, u.name, u.surname, u.username
    FROM " . TABLE_PREFIX . "news n
    JOIN " . TABLE_PREFIX . "users u ON n.author_id = u.user_id
    WHERE n.published = 1
    ORDER BY n.is_pinned DESC, n.published_at DESC
    LIMIT 10
");
$stmt->execute();
$newsItems = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="container">
    <div class="content-area">
        <div class="news-list">
            <?php if (empty($newsItems)): ?>
                <div class="news-item">
                    <div class="news-item-content">
                        <p>No news items available at this time.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($newsItems as $news): ?>
                    <article class="news-item">
                        <div class="news-item-header">
                            <h2><a href="<?= baseUrl('/news-view.php?id=' . $news['news_id']) ?>"><?= h($news['title']) ?></a></h2>
                        </div>
                        <div class="news-item-content">
                            <?php if ($news['excerpt']): ?>
                                <div class="news-content"><?= $news['excerpt'] ?></div>
                            <?php else: ?>
                                <p><?= h(substr(strip_tags($news['content']), 0, 200)) ?>...</p>
                            <?php endif; ?>
                            <?php if ($news['featured_image']): ?>
                                <img src="<?= baseUrl('/' . $news['featured_image']) ?>" alt="<?= h($news['title']) ?>">
                            <?php endif; ?>
                        </div>
                        <div class="news-item-footer">
                            <a href="<?= baseUrl('/news-view.php?id=' . $news['news_id']) ?>" class="btn btn-primary">Read More</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php 
$hideAdverts = false;
include 'includes/footer.php'; 
?>


