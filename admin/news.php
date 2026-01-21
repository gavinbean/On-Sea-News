<?php
require_once __DIR__ . '/../includes/functions.php';
requireAnyRole(['ADMIN', 'PUBLISHER']);

$db = getDB();
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'create') {
            $title = $_POST['title'] ?? '';
            $content = $_POST['content'] ?? '';
            $excerpt = $_POST['excerpt'] ?? '';
            $published = isset($_POST['published']) ? 1 : 0;
            $isPinned = isset($_POST['is_pinned']) ? 1 : 0;
            $showPublishDate = isset($_POST['show_publish_date']) ? 1 : 0;
            $showAuthor = isset($_POST['show_author']) ? 1 : 0;
            $userId = getCurrentUserId();
            
            if (empty($title) || empty($content)) {
                $error = 'Title and content are required.';
            } else {
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
                        INSERT INTO " . TABLE_PREFIX . "news 
                        (author_id, title, content, excerpt, published, published_at, is_pinned, show_publish_date, show_author)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $publishedAt = $published ? date('Y-m-d H:i:s') : null;
                    $stmt->execute([$userId, $title, $content, $excerpt, $published, $publishedAt, $isPinned, $showPublishDate, $showAuthor]);
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO " . TABLE_PREFIX . "news 
                        (author_id, title, content, excerpt, published, published_at, is_pinned)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $publishedAt = $published ? date('Y-m-d H:i:s') : null;
                    $stmt->execute([$userId, $title, $content, $excerpt, $published, $publishedAt, $isPinned]);
                }
                $message = 'News item created successfully.';
            }
        } elseif ($_POST['action'] === 'update') {
            $newsId = $_POST['news_id'] ?? 0;
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
        } elseif ($_POST['action'] === 'delete') {
            $newsId = $_POST['news_id'] ?? 0;
            if ($newsId > 0) {
                $stmt = $db->prepare("DELETE FROM " . TABLE_PREFIX . "news WHERE news_id = ?");
                $stmt->execute([$newsId]);
                $message = 'News item deleted successfully.';
                
                // Redirect to prevent resubmission
                header('Location: ' . baseUrl('/admin/news.php?message=' . urlencode($message)));
                exit;
            } else {
                $error = 'Invalid news ID.';
            }
        } elseif ($_POST['action'] === 'convert_to_faq') {
            $newsId = $_POST['news_id'] ?? 0;
            
            if ($newsId > 0) {
                // Get the news item
                $stmt = $db->prepare("
                    SELECT title, content, excerpt 
                    FROM " . TABLE_PREFIX . "news 
                    WHERE news_id = ?
                ");
                $stmt->execute([$newsId]);
                $newsItem = $stmt->fetch();
                
                if ($newsItem) {
                    // Map news fields to FAQ fields correctly:
                    // News title → FAQ question
                    // News excerpt → FAQ excerpt (preview)
                    // News content → FAQ answer (full content with HTML preserved)
                    $question = $newsItem['title'];
                    $excerpt = !empty($newsItem['excerpt']) ? $newsItem['excerpt'] : null;
                    $answer = $newsItem['content']; // Preserve HTML from TinyMCE
                    
                    // Check if excerpt column exists in FAQ table
                    $faqExcerptExists = false;
                    try {
                        $testStmt = $db->query("SELECT excerpt FROM " . TABLE_PREFIX . "faq LIMIT 1");
                        $faqExcerptExists = true;
                    } catch (Exception $e) {
                        $faqExcerptExists = false;
                    }
                    
                    // Get the highest display_order to append at the end
                    $stmt = $db->query("SELECT MAX(display_order) as max_order FROM " . TABLE_PREFIX . "faq");
                    $maxOrder = $stmt->fetch();
                    $displayOrder = ($maxOrder['max_order'] ?? 0) + 1;
                    
                    // Create FAQ entry with proper field mapping
                    if ($faqExcerptExists) {
                        $stmt = $db->prepare("
                            INSERT INTO " . TABLE_PREFIX . "faq (question, excerpt, answer, display_order, is_active)
                            VALUES (?, ?, ?, ?, 1)
                        ");
                        $stmt->execute([$question, $excerpt, $answer, $displayOrder]);
                    } else {
                        // Fallback: insert without excerpt column
                        $stmt = $db->prepare("
                            INSERT INTO " . TABLE_PREFIX . "faq (question, answer, display_order, is_active)
                            VALUES (?, ?, ?, 1)
                        ");
                        $stmt->execute([$question, $answer, $displayOrder]);
                    }
                    
                    // Delete the news item
                    $stmt = $db->prepare("DELETE FROM " . TABLE_PREFIX . "news WHERE news_id = ?");
                    $stmt->execute([$newsId]);
                    
                    $message = 'News item successfully converted to FAQ and removed from news.';
                    
                    // Redirect to prevent resubmission
                    header('Location: ' . baseUrl('/admin/news.php?message=' . urlencode($message)));
                    exit;
                } else {
                    $error = 'News item not found.';
                }
            } else {
                $error = 'Invalid news ID.';
            }
        }
    }
}

// Get all news items
$stmt = $db->query("
    SELECT n.*, u.username
    FROM " . TABLE_PREFIX . "news n
    JOIN " . TABLE_PREFIX . "users u ON n.author_id = u.user_id
    ORDER BY n.is_pinned DESC, n.created_at DESC
");
$newsItems = $stmt->fetchAll();

$pageTitle = 'Manage News';
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
                            console.log('Image uploaded successfully, size: ' + Math.round(result.length / 1024) + 'KB');
                            resolve(result);
                        };
                        reader.onerror = function(error) {
                            console.error('Image upload error:', error);
                            reject('Image upload failed. Please try again.');
                        };
                        reader.readAsDataURL(blobInfo.blob());
                    } catch (error) {
                        console.error('Image upload handler error:', error);
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
                    console.log('Admin news paste - current content length:', currentContent.length);
                    // Auto-save after paste operations complete
                    setTimeout(function() {
                        const newContent = editor.getContent();
                        console.log('Admin news paste - new content length:', newContent.length);
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
    
    // Form submission handler for create news form - ensure TinyMCE content is saved before submission
    const createFormInput = document.querySelector('input[name="action"][value="create"]');
    if (createFormInput) {
        const form = createFormInput.closest('form');
        if (form) {
            form.addEventListener('submit', function(e) {
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
                    
                    // Final check - ensure content is set
                    if (!content || content.trim() === '') {
                        content = contentTextarea ? contentTextarea.value : '';
                    }
                    
                    if (!hasContent) {
                        alert('Content is required. Please add text or images to your news item before submitting.');
                        if (contentEditor) {
                            contentEditor.focus();
                        }
                        return false;
                    }
                    
                    // Ensure textareas have the content before submission
                    if (contentTextarea && content) {
                        contentTextarea.value = content;
                    }
                    if (excerptTextarea) {
                        excerptTextarea.value = excerpt;
                    }
                    
                    // All validation passed, submit the form
                    form.submit();
                }, 150);
            });
        }
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

// Handle news title hover tooltips
document.addEventListener('DOMContentLoaded', function() {
    const newsTitles = document.querySelectorAll('.news-title-hover');
    newsTitles.forEach(function(titleEl) {
        const tooltip = titleEl.nextElementSibling;
        if (!tooltip || !tooltip.classList.contains('news-tooltip')) return;
        
        titleEl.addEventListener('mouseenter', function() {
            tooltip.style.display = 'block';
            // Position tooltip to prevent overflow
            const rect = tooltip.getBoundingClientRect();
            const windowWidth = window.innerWidth;
            if (rect.right > windowWidth) {
                tooltip.style.left = 'auto';
                tooltip.style.right = '0';
            }
        });
        
        titleEl.addEventListener('mouseleave', function() {
            tooltip.style.display = 'none';
        });
    });
});
</script>

<div class="container">
    <div class="content-area">
        <h1>Manage News</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= h($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        
        <div class="news-management">
            <h2>Create New News Item</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="create">
                
                <div class="form-group">
                    <label for="title">Title: <span class="required">*</span></label>
                    <input type="text" id="title" name="title" required>
                </div>
                
                <div class="form-group">
                    <label for="excerpt">Excerpt:</label>
                    <textarea id="excerpt" name="excerpt" rows="2"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="content">Content: <span class="required">*</span></label>
                    <textarea id="content" name="content" rows="10"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="published" name="published" value="1">
                        Publish immediately
                    </label>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="is_pinned" name="is_pinned" value="1">
                        Pin on Top
                    </label>
                    <small style="display: block; color: #666; margin-top: 0.25rem;">Pinned items will always appear first in the news listing</small>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="show_publish_date" name="show_publish_date" value="1" checked>
                        Show publish date on news item
                    </label>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="show_author" name="show_author" value="1" checked>
                        Show author name on news item
                    </label>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Create News Item</button>
                    <button type="button" class="btn btn-secondary" onclick="showNewsPreview()">Preview</button>
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
        
        <div class="news-list">
            <h2>Existing News Items</h2>
            <?php if (empty($newsItems)): ?>
                <p>No news items found.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Status</th>
                            <th>Pinned</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($newsItems as $news): 
                            // Prepare tooltip content
                            $author = h($news['username']);
                            $date = formatDate($news['created_at']);
                            $preview = h(substr(strip_tags($news['content']), 0, 300));
                            if (strlen(strip_tags($news['content'])) > 300) {
                                $preview .= '...';
                            }
                            ?>
                            <tr>
                                <td>
                                    <div style="position: relative; display: inline-block;">
                                        <span class="news-title-hover" style="cursor: help; text-decoration: underline; text-decoration-style: dotted; text-decoration-color: #999;">
                                            <?= h($news['title']) ?>
                                        </span>
                                        <div class="news-tooltip" style="display: none; position: absolute; bottom: 100%; left: 0; background-color: #333; color: white; padding: 0.75rem 1rem; border-radius: 4px; min-width: 300px; max-width: 500px; z-index: 1000; margin-bottom: 0.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.2); font-size: 0.875rem; line-height: 1.5; white-space: normal;">
                                            <div style="margin-bottom: 0.5rem;"><strong>Author:</strong> <?= $author ?></div>
                                            <div style="margin-bottom: 0.5rem;"><strong>Date:</strong> <?= $date ?></div>
                                            <div style="border-top: 1px solid rgba(255,255,255,0.2); padding-top: 0.5rem; margin-top: 0.5rem;"><strong>Preview:</strong><br><?= nl2br($preview) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($news['published']): ?>
                                        <span style="color: green;">Published</span>
                                    <?php else: ?>
                                        <span style="color: orange;">Draft</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (isset($news['is_pinned']) && $news['is_pinned']): ?>
                                        <span style="color: #2c5f8d;">📌 Yes</span>
                                    <?php else: ?>
                                        <span style="color: #999;">No</span>
                                    <?php endif; ?>
                                </td>
                                <td style="white-space: nowrap; min-width: 350px;">
                                    <div style="display: flex; gap: 0.25rem; flex-wrap: nowrap; align-items: center;">
                                        <a href="<?= baseUrl('/admin/news-edit.php?id=' . $news['news_id']) ?>" class="btn btn-sm btn-secondary" style="flex-shrink: 0;">Edit</a>
                                        <form method="POST" action="" style="display: inline-flex; margin: 0; flex-shrink: 0;" onsubmit="return confirm('Are you sure you want to convert this news item to an FAQ? The news item will be deleted after conversion.');">
                                            <input type="hidden" name="action" value="convert_to_faq">
                                            <input type="hidden" name="news_id" value="<?= $news['news_id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-info" style="white-space: nowrap;">Move to FAQ</button>
                                        </form>
                                        <form method="POST" action="" style="display: inline-flex; margin: 0; flex-shrink: 0;" onsubmit="return confirm('Are you sure you want to delete this news item? This action cannot be undone.');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="news_id" value="<?= $news['news_id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" style="flex-shrink: 0;">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php 
$hideAdverts = true;
include __DIR__ . '/../includes/footer.php'; 
?>

