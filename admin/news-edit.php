<?php
require_once __DIR__ . '/../includes/functions.php';
requireAnyRole(['ADMIN', 'PUBLISHER']);

$newsId = $_GET['id'] ?? 0;
$db = getDB();

$stmt = $db->prepare("SELECT * FROM " . TABLE_PREFIX . "news WHERE news_id = ?");
$stmt->execute([$newsId]);
$news = $stmt->fetch();

if (!$news) {
    redirect('/admin/news.php');
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $content = $_POST['content'] ?? '';
    $excerpt = $_POST['excerpt'] ?? '';
    $published = isset($_POST['published']) ? 1 : 0;
    
    $stmt = $db->prepare("
        SELECT published, published_at FROM " . TABLE_PREFIX . "news WHERE news_id = ?
    ");
    $stmt->execute([$newsId]);
    $existing = $stmt->fetch();
    
    $publishedAt = $published && !$existing['published_at'] ? date('Y-m-d H:i:s') : $existing['published_at'];
    
    $isPinned = isset($_POST['is_pinned']) ? 1 : 0;
    
    $stmt = $db->prepare("
        UPDATE " . TABLE_PREFIX . "news 
        SET title = ?, content = ?, excerpt = ?, published = ?, published_at = ?, is_pinned = ?
        WHERE news_id = ?
    ");
    $stmt->execute([$title, $content, $excerpt, $published, $publishedAt, $isPinned, $newsId]);
    $message = 'News item updated successfully.';
    $news = $stmt->fetch();
    
    // Reload news
    $stmt = $db->prepare("SELECT * FROM " . TABLE_PREFIX . "news WHERE news_id = ?");
    $stmt->execute([$newsId]);
    $news = $stmt->fetch();
}

$pageTitle = 'Edit News';
include __DIR__ . '/../includes/header.php';
?>

<!-- TinyMCE for rich text editing (self-hosted CDN build, no API key required) -->
<script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.3/tinymce.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const initTiny = (selector) => {
        if (!document.querySelector(selector)) return;
        tinymce.init({
            selector,
            height: selector === '#content' ? 420 : 180,
            menubar: 'file edit view insert format tools table help',
            plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table code help wordcount',
            toolbar: 'undo redo | blocks | bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media table | removeformat | code fullscreen',
            paste_data_images: true,
            image_title: true,
            automatic_uploads: true,
            images_upload_handler: (blobInfo) => new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = () => resolve(reader.result);
                reader.onerror = () => reject('Image upload failed');
                reader.readAsDataURL(blobInfo.blob());
            }),
            content_style: 'body { font-family: Arial, sans-serif; font-size: 14px; } ul, ol { padding-left: 40px !important; }',
            invalid_styles: 'padding padding-left padding-right padding-top padding-bottom',
            valid_styles: {
                '*': 'line-height'
            }
        });
    };
    initTiny('#content');
    initTiny('#excerpt');
});

function getFieldValue(id) {
    const el = document.getElementById(id);
    return el ? el.value : '';
}

function getEditorContent(id) {
    if (window.tinymce && tinymce.get(id)) {
        return tinymce.get(id).getContent();
    }
    const el = document.getElementById(id);
    return el ? el.value : '';
}

function showNewsPreview() {
    const title = getFieldValue('title') || '(No title)';
    const excerpt = getEditorContent('excerpt');
    const content = getEditorContent('content');

    const container = document.getElementById('news-preview-container');
    const titleEl = document.getElementById('news-preview-title');
    const excerptEl = document.getElementById('news-preview-excerpt');
    const contentEl = document.getElementById('news-preview-content');

    if (!container || !titleEl || !excerptEl || !contentEl) {
        console.error('Preview elements not found');
        return;
    }

    titleEl.textContent = title;
    excerptEl.innerHTML = excerpt || '';
    contentEl.innerHTML = content || '<p><em>No content</em></p>';

    container.style.display = 'block';
    container.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>

<div class="container">
    <div class="content-area">
        <h1>Edit News Item</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= h($message) ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="title">Title: <span class="required">*</span></label>
                <input type="text" id="title" name="title" value="<?= h($news['title']) ?>" required>
            </div>
            
            <div class="form-group">
                <label for="excerpt">Excerpt:</label>
                <textarea id="excerpt" name="excerpt" rows="2"><?= h($news['excerpt']) ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="content">Content: <span class="required">*</span></label>
                <textarea id="content" name="content" rows="15" required><?= h($news['content']) ?></textarea>
            </div>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" id="published" name="published" value="1" <?= $news['published'] ? 'checked' : '' ?>>
                    Published
                </label>
            </div>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" id="is_pinned" name="is_pinned" value="1" <?= isset($news['is_pinned']) && $news['is_pinned'] ? 'checked' : '' ?>>
                    Pin on Top
                </label>
                <small style="display: block; color: #666; margin-top: 0.25rem;">Pinned items will always appear first in the news listing</small>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Update News Item</button>
                <button type="button" class="btn btn-secondary" onclick="showNewsPreview()">Preview</button>
                <a href="<?= baseUrl('/admin/news.php') ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>

        <div id="news-preview-container" style="display:none; margin-top: 2rem; padding: 1.5rem; background-color: #ffffff; border-radius: 8px; border: 1px solid #ddd;">
            <h2 style="margin-top: 0; margin-bottom: 1rem;">Preview</h2>
            <article class="news-preview-article">
                <h3 id="news-preview-title" style="margin-top: 0;"></h3>
                <div id="news-preview-excerpt" style="color: #555; margin-bottom: 1rem;"></div>
                <div id="news-preview-content"></div>
            </article>
        </div>
    </div>
</div>

<?php 
$hideAdverts = true;
include __DIR__ . '/../includes/footer.php'; 
?>


