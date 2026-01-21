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
    $isPinned = isset($_POST['is_pinned']) ? 1 : 0;
    $showPublishDate = isset($_POST['show_publish_date']) ? 1 : 0;
    $showAuthor = isset($_POST['show_author']) ? 1 : 0;
    
    $stmt = $db->prepare("
        SELECT published, published_at FROM " . TABLE_PREFIX . "news WHERE news_id = ?
    ");
    $stmt->execute([$newsId]);
    $existing = $stmt->fetch();
    
    $publishedAt = $published && !$existing['published_at'] ? date('Y-m-d H:i:s') : $existing['published_at'];
    
    // Check if new columns exist
    $columnsExist = false;
    try {
        $testStmt = $db->query("SELECT show_publish_date, show_author FROM " . TABLE_PREFIX . "news LIMIT 1");
        $columnsExist = true;
    } catch (Exception $e) {
        $columnsExist = false;
    }
    
    if ($columnsExist) {
        $stmt = $db->prepare("
            UPDATE " . TABLE_PREFIX . "news 
            SET title = ?, content = ?, excerpt = ?, published = ?, published_at = ?, is_pinned = ?, show_publish_date = ?, show_author = ?
            WHERE news_id = ?
        ");
        $stmt->execute([$title, $content, $excerpt, $published, $publishedAt, $isPinned, $showPublishDate, $showAuthor, $newsId]);
    } else {
        $stmt = $db->prepare("
            UPDATE " . TABLE_PREFIX . "news 
            SET title = ?, content = ?, excerpt = ?, published = ?, published_at = ?, is_pinned = ?
            WHERE news_id = ?
        ");
        $stmt->execute([$title, $content, $excerpt, $published, $publishedAt, $isPinned, $newsId]);
    }
    $message = 'News item updated successfully.';
    
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
            branding: false,
            menubar: 'file edit view insert format tools table help',
            plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table code help wordcount',
            toolbar: 'undo redo | blocks | bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media table | removeformat | code fullscreen',
            paste_data_images: true,
            paste_as_text: false,
            paste_merge_formats: true,
            paste_auto_cleanup_on_paste: false,
            paste_remove_styles_if_webkit: false,
            paste_strip_class_attributes: 'none',
            paste_retain_style_properties: 'all',
            paste_webkit_styles: 'all',
            image_title: true,
            automatic_uploads: true,
            images_upload_handler: (blobInfo) => {
                return new Promise((resolve, reject) => {
                    try {
                        // Limit image size to prevent extremely large base64 strings (2MB max)
                        const maxSize = 2 * 1024 * 1024;
                        if (blobInfo.blob().size > maxSize) {
                            reject('Image is too large. Maximum size is 2MB. Please reduce the image size and try again.');
                            return;
                        }
                        
                        const reader = new FileReader();
                        reader.onload = function() {
                            const result = reader.result;
                            console.log('News edit image uploaded successfully, size: ' + Math.round(result.length / 1024) + 'KB');
                            resolve(result);
                        };
                        reader.onerror = function(error) {
                            console.error('News edit image upload error:', error);
                            reject('Image upload failed. Please try again.');
                        };
                        reader.readAsDataURL(blobInfo.blob());
                    } catch (error) {
                        console.error('News edit image upload handler error:', error);
                        reject('Image upload failed: ' + error.message);
                    }
                });
            },
            // Image alignment and wrapping options
            image_advtab: true,
            image_class_list: [
                {title: 'None', value: ''},
                {title: 'Float Left', value: 'float-left'},
                {title: 'Float Right', value: 'float-right'},
                {title: 'Center', value: 'center-image'},
                {title: 'Full Width', value: 'full-width'}
            ],
            image_caption: true,
            image_dimensions: true,
            image_description: true,
            // Allow all image styles for wrapping
            valid_styles: {
                '*': 'line-height, float, margin, margin-left, margin-right, margin-top, margin-bottom, padding, padding-left, padding-right, padding-top, padding-bottom, width, height, max-width, max-height, display, vertical-align, text-align'
            },
            extended_valid_elements: 'img[class|src|border=0|alt|title|hspace|vspace|width|height|align|onmouseover|onmouseout|name|style|usemap]',
            content_style: 'body { font-family: Arial, sans-serif; font-size: 14px; } ul, ol { padding-left: 40px !important; } img.float-left { float: left; margin: 0 1rem 1rem 0; max-width: 50%; } img.float-right { float: right; margin: 0 0 1rem 1rem; max-width: 50%; } img.center-image { display: block; margin: 1rem auto; max-width: 100%; } img.full-width { width: 100%; height: auto; display: block; margin: 1rem 0; }',
            invalid_styles: '',
            // Enable image resizing
            image_resize: true,
            image_resize_constrain: true,
            setup: function(editor) {
                editor.on('init', function() {
                    // Remove required attribute from textarea since TinyMCE handles it
                    const textarea = document.getElementById(editor.id);
                    if (textarea) {
                        textarea.removeAttribute('required');
                    }
                });
                
                // Auto-save on change to ensure content is always in sync
                editor.on('change', function() {
                    editor.save();
                });
                
                // Save on blur to ensure content is saved when user moves away
                editor.on('blur', function() {
                    editor.save();
                });
                
                // Handle image insertion to ensure content is preserved
                editor.on('ObjectResized', function(e) {
                    editor.save(); // Save after image resize
                });
                
                // Handle paste events to ensure content is preserved
                editor.on('paste', function(e) {
                    // Store current content before paste to ensure it's preserved
                    const currentContent = editor.getContent();
                    console.log('News edit paste - current content length:', currentContent.length);
                    // Auto-save after paste operations complete
                    setTimeout(function() {
                        const newContent = editor.getContent();
                        console.log('News edit paste - new content length:', newContent.length);
                        // If content was lost during paste (safety check)
                        if ((!newContent || newContent.trim() === '' || newContent === '<p></p>') && currentContent && currentContent.trim() !== '' && currentContent !== '<p></p>') {
                            console.warn('Content appears to have been lost during paste, restoring...');
                            // Restore previous content
                            editor.setContent(currentContent);
                            // Wait a bit then check again
                            setTimeout(function() {
                                const restoredContent = editor.getContent();
                                if (!restoredContent || restoredContent.trim() === '') {
                                    console.error('Failed to restore content after paste');
                                }
                                editor.save();
                            }, 100);
                        } else {
                            editor.save();
                        }
                    }, 300);
                });
                
                // Save content before any resize operations
                editor.on('ObjectResizeStart', function(e) {
                    editor.save();
                });
            }
        });
    };
    initTiny('#content');
    initTiny('#excerpt');
    
    // Form submission handler for edit news form - ensure TinyMCE content is saved before submission
    const editForm = document.querySelector('form[method="POST"]');
    if (editForm && !editForm.querySelector('input[name="action"]')) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault(); // Always prevent default first to handle TinyMCE content
            
            // Get TinyMCE editors
            const contentEditor = tinymce.get('content');
            const excerptEditor = tinymce.get('excerpt');
            
            // Save all TinyMCE editors first to ensure content is in textareas
            if (contentEditor) {
                contentEditor.save(); // Save content to textarea
            }
            if (excerptEditor) {
                excerptEditor.save(); // Save excerpt to textarea
            }
            
            // Wait for save to complete
            setTimeout(function() {
                // Get content from textarea (after save)
                const contentTextarea = document.getElementById('content');
                const excerptTextarea = document.getElementById('excerpt');
                
                let content = contentTextarea ? contentTextarea.value : '';
                const excerpt = excerptTextarea ? excerptTextarea.value : '';
                
                // Check if content is actually there
                let hasContent = false;
                if (content && content.trim() !== '') {
                    let contentStripped = content.replace(/<p>&nbsp;<\/p>/g, '').replace(/<p><\/p>/g, '').replace(/<br\s*\/?>/gi, '').trim();
                    contentStripped = contentStripped.replace(/<p>\s*<\/p>/g, '').trim();
                    
                    const hasText = contentStripped.replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim().length > 0;
                    const hasImages = content.includes('<img');
                    const hasMedia = content.includes('<iframe') || content.includes('<video') || content.includes('<embed');
                    
                    hasContent = (hasText || hasImages || hasMedia) && contentStripped !== '' && contentStripped !== '<p></p>' && contentStripped !== '<p>&nbsp;</p>';
                }
                
                // If content from textarea appears empty, try to get it from editor
                if (!hasContent && contentEditor) {
                    const editorContent = contentEditor.getContent();
                    if (editorContent && editorContent.trim() !== '' && editorContent !== '<p></p>') {
                        contentTextarea.value = editorContent;
                        content = editorContent;
                        const editorContentStripped = editorContent.replace(/<p>&nbsp;<\/p>/g, '').replace(/<p><\/p>/g, '').trim();
                        const editorHasText = editorContent.replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim().length > 0;
                        const editorHasImages = editorContent.includes('<img');
                        const editorHasMedia = editorContent.includes('<iframe') || editorContent.includes('<video') || editorContent.includes('<embed');
                        
                        if (editorContentStripped !== '' && editorContentStripped !== '<p></p>' && (editorHasText || editorHasImages || editorHasMedia)) {
                            hasContent = true;
                        }
                    }
                }
                
                if (!hasContent) {
                    alert('Content is required. Please add text or images to your news item before submitting.');
                    if (contentEditor) {
                        contentEditor.focus();
                    }
                    return false;
                }
                
                // Final check - ensure content variable is up to date
                if (!content || content.trim() === '') {
                    const finalContent = contentTextarea ? contentTextarea.value : '';
                    if (!finalContent || finalContent.trim() === '') {
                        alert('Content appears to be empty. Please ensure your content is saved before submitting.');
                        if (contentEditor) {
                            contentEditor.focus();
                        }
                        return false;
                    }
                    content = finalContent;
                }
                
                // Ensure textareas have the content before submission
                if (contentTextarea) {
                    contentTextarea.value = content;
                }
                if (excerptTextarea) {
                    excerptTextarea.value = excerpt;
                }
                
                // All validation passed, submit the form
                editForm.submit();
            }, 150);
        });
    }
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
                <textarea id="content" name="content" rows="15"><?= h($news['content']) ?></textarea>
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
                <label class="checkbox-label">
                    <input type="checkbox" id="show_publish_date" name="show_publish_date" value="1" <?= (!isset($news['show_publish_date']) || $news['show_publish_date']) ? 'checked' : '' ?>>
                    Show publish date on news item
                </label>
            </div>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" id="show_author" name="show_author" value="1" <?= (!isset($news['show_author']) || $news['show_author']) ? 'checked' : '' ?>>
                    Show author name on news item
                </label>
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


