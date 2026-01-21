<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Frequently Asked Questions';
$db = getDB();

// Public page: show only active FAQs ordered nicely
// Check if excerpt column exists
$columnsExist = false;
try {
    $testStmt = $db->query("SELECT excerpt FROM " . TABLE_PREFIX . "faq LIMIT 1");
    $columnsExist = true;
} catch (Exception $e) {
    $columnsExist = false;
}

if ($columnsExist) {
    $stmt = $db->query("
        SELECT faq_id, question, answer, excerpt
        FROM " . TABLE_PREFIX . "faq
        WHERE is_active = 1
        ORDER BY display_order ASC, created_at ASC
    ");
} else {
    $stmt = $db->query("
        SELECT faq_id, question, answer
        FROM " . TABLE_PREFIX . "faq
        WHERE is_active = 1
        ORDER BY display_order ASC, created_at ASC
    ");
}
$faqs = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="content-area">
        <div class="terms-content">
            <h1>Frequently Asked Questions</h1>
            <p class="intro">
                Here are some common questions and answers about the On-Sea News community site.
            </p>

            <?php if (empty($faqs)): ?>
                <p>No FAQs have been published yet.</p>
            <?php else: ?>
                <div class="faq-list" style="margin-top: 2rem;">
                    <ul style="list-style: none; padding: 0; margin: 0;">
                        <?php foreach ($faqs as $index => $faq): 
                            // Prepare tooltip content (preview)
                            $excerptText = !empty($faq['excerpt']) ? strip_tags($faq['excerpt']) : strip_tags($faq['answer']);
                            $preview = h(substr($excerptText, 0, 300));
                            if (strlen($excerptText) > 300) {
                                $preview .= '...';
                            }
                            ?>
                            <li style="margin-bottom: 1.5rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-color);">
                                <div style="position: relative; display: inline-block;">
                                    <h2 style="margin: 0; font-size: 1.25rem;">
                                        <a href="<?= baseUrl('/faq-view.php?id=' . $faq['faq_id']) ?>" 
                                           class="faq-question-link" 
                                           style="text-decoration: none; color: var(--primary-color); cursor: pointer; transition: color 0.2s; display: inline-block;"
                                           data-preview="<?= h($preview) ?>"
                                           onmouseover="this.style.color='var(--secondary-color)'; this.style.textDecoration='underline';"
                                           onmouseout="this.style.color='var(--primary-color)'; this.style.textDecoration='none';">
                                            <?= h($faq['question']) ?>
                                        </a>
                                    </h2>
                                    <div class="faq-tooltip" style="display: none; position: absolute; bottom: 100%; left: 0; background-color: #333; color: white; padding: 0.75rem 1rem; border-radius: 4px; min-width: 300px; max-width: 500px; z-index: 1000; margin-bottom: 0.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.2); font-size: 0.875rem; line-height: 1.5; white-space: normal; word-wrap: break-word;">
                                        <div><strong>Preview:</strong><br><?= nl2br($preview) ?></div>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Handle FAQ question hover tooltips
document.addEventListener('DOMContentLoaded', function() {
    const faqLinks = document.querySelectorAll('.faq-question-link');
    faqLinks.forEach(function(linkEl) {
        // Find the tooltip - it's a sibling of the link's parent div
        const parentDiv = linkEl.closest('div[style*="position: relative"]');
        if (parentDiv) {
            const tooltip = parentDiv.querySelector('.faq-tooltip');
            if (tooltip) {
                linkEl.addEventListener('mouseenter', function() {
                    tooltip.style.display = 'block';
                    // Position tooltip to prevent overflow
                    setTimeout(function() {
                        const rect = tooltip.getBoundingClientRect();
                        const windowWidth = window.innerWidth;
                        if (rect.right > windowWidth) {
                            tooltip.style.left = 'auto';
                            tooltip.style.right = '0';
                        }
                    }, 10);
                });
                
                linkEl.addEventListener('mouseleave', function() {
                    tooltip.style.display = 'none';
                });
            }
        }
    });
});
</script>

<?php
$hideAdverts = false;
include __DIR__ . '/includes/footer.php';
?>


