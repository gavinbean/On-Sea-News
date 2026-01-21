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
        <?php if (empty($newsItems)): ?>
            <div class="news-item">
                <div class="news-item-content">
                    <p>No news items available at this time.</p>
                </div>
            </div>
        <?php else: ?>
            <?php
            // Separate pinned and regular news items
            $pinnedNews = [];
            $regularNews = [];
            foreach ($newsItems as $news) {
                if (isset($news['is_pinned']) && $news['is_pinned']) {
                    $pinnedNews[] = $news;
                } else {
                    $regularNews[] = $news;
                }
            }
            ?>
            
            <!-- Pinned/Featured News Section -->
            <?php if (!empty($pinnedNews)): ?>
                <div class="featured-news-section">
                    <h2 class="section-title">Featured News</h2>
                    <div class="featured-news-grid">
                        <?php foreach ($pinnedNews as $news): ?>
                            <article class="news-item featured-news-item">
                                <?php if ($news['featured_image']): ?>
                                    <div class="featured-news-image">
                                        <a href="<?= baseUrl('/news-view.php?id=' . $news['news_id']) ?>">
                                            <img src="<?= baseUrl('/' . $news['featured_image']) ?>" alt="<?= h($news['title']) ?>">
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <div class="news-item-header">
                                    <h2><a href="<?= baseUrl('/news-view.php?id=' . $news['news_id']) ?>"><?= h($news['title']) ?></a></h2>
                                    <div class="news-meta" style="font-size: 0.9rem; color: #666; margin-top: 0.5rem;">
                                        <?php 
                                        $showDate = !isset($news['show_publish_date']) || $news['show_publish_date'];
                                        $showAuthor = !isset($news['show_author']) || $news['show_author'];
                                        ?>
                                        <?php if ($showDate && $news['published_at']): ?>
                                            <span>Published: <?= date('Y-m-d', strtotime($news['published_at'])) ?></span>
                                        <?php endif; ?>
                                        <?php if ($showAuthor): ?>
                                            <?php if ($showDate && $news['published_at']): ?> | <?php endif; ?>
                                            <span>By <?= h($news['name'] . ' ' . $news['surname']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="news-item-content">
                                    <?php if ($news['excerpt']): ?>
                                        <div class="news-content"><?= $news['excerpt'] ?></div>
                                    <?php else: ?>
                                        <p><?= h(substr(strip_tags($news['content']), 0, 300)) ?>...</p>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Regular News Grid -->
            <?php if (!empty($regularNews)): ?>
                <div class="regular-news-section">
                    <?php if (!empty($pinnedNews)): ?>
                        <h2 class="section-title">Latest News</h2>
                    <?php endif; ?>
                    <div class="news-grid">
                        <?php foreach ($regularNews as $news): ?>
                            <article class="news-item regular-news-item">
                                <?php if ($news['featured_image']): ?>
                                    <div class="news-item-image">
                                        <a href="<?= baseUrl('/news-view.php?id=' . $news['news_id']) ?>">
                                            <img src="<?= baseUrl('/' . $news['featured_image']) ?>" alt="<?= h($news['title']) ?>">
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <div class="news-item-header">
                                    <h3><a href="<?= baseUrl('/news-view.php?id=' . $news['news_id']) ?>"><?= h($news['title']) ?></a></h3>
                                    <div class="news-meta" style="font-size: 0.9rem; color: #666; margin-top: 0.5rem;">
                                        <?php 
                                        $showDate = !isset($news['show_publish_date']) || $news['show_publish_date'];
                                        $showAuthor = !isset($news['show_author']) || $news['show_author'];
                                        ?>
                                        <?php if ($showDate && $news['published_at']): ?>
                                            <span>Published: <?= date('Y-m-d', strtotime($news['published_at'])) ?></span>
                                        <?php endif; ?>
                                        <?php if ($showAuthor): ?>
                                            <?php if ($showDate && $news['published_at']): ?> | <?php endif; ?>
                                            <span>By <?= h($news['name'] . ' ' . $news['surname']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="news-item-content">
                                    <?php if ($news['excerpt']): ?>
                                        <div class="news-content"><?= $news['excerpt'] ?></div>
                                    <?php else: ?>
                                        <p><?= h(substr(strip_tags($news['content']), 0, 150)) ?>...</p>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php 
$hideAdverts = false;
include 'includes/footer.php'; 
?>


