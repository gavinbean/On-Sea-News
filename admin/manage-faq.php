<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('ADMIN');

$db = getDB();
$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';

// Handle create/update/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'create') {
            $question = trim($_POST['question'] ?? '');
            $answer = trim($_POST['answer'] ?? '');
            $excerpt = trim($_POST['excerpt'] ?? '');
            $displayOrder = (int)($_POST['display_order'] ?? 0);
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($question) || empty($answer)) {
                $error = 'Question and answer are required.';
            } else {
                // Check if excerpt column exists
                $columnsExist = false;
                try {
                    $testStmt = $db->query("SELECT excerpt FROM " . TABLE_PREFIX . "faq LIMIT 1");
                    $columnsExist = true;
                } catch (Exception $e) {
                    $columnsExist = false;
                }
                
                if ($columnsExist) {
                    $stmt = $db->prepare("
                        INSERT INTO " . TABLE_PREFIX . "faq (question, answer, excerpt, display_order, is_active)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$question, $answer, $excerpt, $displayOrder, $isActive]);
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO " . TABLE_PREFIX . "faq (question, answer, display_order, is_active)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$question, $answer, $displayOrder, $isActive]);
                }
                $message = 'FAQ item created successfully.';
                
                // Redirect to prevent resubmission
                header('Location: ' . baseUrl('/admin/manage-faq.php?message=' . urlencode($message)));
                exit;
            }
        } elseif ($_POST['action'] === 'update') {
            $faqId = (int)($_POST['faq_id'] ?? 0);
            $question = trim($_POST['question'] ?? '');
            $answer = trim($_POST['answer'] ?? '');
            $excerpt = trim($_POST['excerpt'] ?? '');
            $displayOrder = (int)($_POST['display_order'] ?? 0);
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($question) || empty($answer)) {
                $error = 'Question and answer are required.';
            } else {
                // Check if excerpt column exists
                $columnsExist = false;
                try {
                    $testStmt = $db->query("SELECT excerpt FROM " . TABLE_PREFIX . "faq LIMIT 1");
                    $columnsExist = true;
                } catch (Exception $e) {
                    $columnsExist = false;
                }
                
                if ($columnsExist) {
                    $stmt = $db->prepare("
                        UPDATE " . TABLE_PREFIX . "faq
                        SET question = ?, answer = ?, excerpt = ?, display_order = ?, is_active = ?
                        WHERE faq_id = ?
                    ");
                    $stmt->execute([$question, $answer, $excerpt, $displayOrder, $isActive, $faqId]);
                } else {
                    $stmt = $db->prepare("
                        UPDATE " . TABLE_PREFIX . "faq
                        SET question = ?, answer = ?, display_order = ?, is_active = ?
                        WHERE faq_id = ?
                    ");
                    $stmt->execute([$question, $answer, $displayOrder, $isActive, $faqId]);
                }
                $message = 'FAQ item updated successfully.';
                
                // Redirect to prevent resubmission
                header('Location: ' . baseUrl('/admin/manage-faq.php?message=' . urlencode($message)));
                exit;
            }
        } elseif ($_POST['action'] === 'delete') {
            $faqId = (int)($_POST['faq_id'] ?? 0);
            if ($faqId > 0) {
                $stmt = $db->prepare("DELETE FROM " . TABLE_PREFIX . "faq WHERE faq_id = ?");
                $stmt->execute([$faqId]);
                $message = 'FAQ item deleted successfully.';
                
                // Redirect to prevent resubmission
                header('Location: ' . baseUrl('/admin/manage-faq.php?message=' . urlencode($message)));
                exit;
            } else {
                $error = 'Invalid FAQ ID.';
            }
        }
    }
}

// Fetch FAQs for listing
$stmt = $db->query("
    SELECT * FROM " . TABLE_PREFIX . "faq
    ORDER BY display_order ASC, created_at ASC
");
$faqs = $stmt->fetchAll();

$pageTitle = 'Manage FAQs';
include __DIR__ . '/../includes/header.php';
?>

<!-- TinyMCE for rich text editing -->
<script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.3/tinymce.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize TinyMCE for answer field (with image support)
    if (document.querySelector('#answer')) {
        tinymce.init({
            selector: '#answer',
            height: 420,
            branding: false,
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
            image_advtab: true,
            image_class_list: [
                {title: 'None', value: ''},
                {title: 'Float Left', value: 'float-left'},
                {title: 'Float Right', value: 'float-right'},
                {title: 'Center', value: 'center-image'},
                {title: 'Full Width', value: 'full-width'}
            ],
            content_style: 'body { font-family: Arial, sans-serif; font-size: 14px; } ul, ol { padding-left: 40px !important; } img.float-left { float: left; margin: 0 1rem 1rem 0; max-width: 50%; } img.float-right { float: right; margin: 0 0 1rem 1rem; max-width: 50%; } img.center-image { display: block; margin: 1rem auto; max-width: 100%; } img.full-width { width: 100%; height: auto; display: block; margin: 1rem 0; }',
            invalid_styles: '',
            image_resize: true,
            image_resize_constrain: true,
            setup: function(editor) {
                editor.on('init', function() {
                    // Remove required attribute from textarea since TinyMCE handles it
                    const textarea = document.getElementById('answer');
                    if (textarea) {
                        textarea.removeAttribute('required');
                    }
                });
                editor.on('change', function() {
                    editor.save();
                });
            }
        });
    }
    
    // Initialize TinyMCE for excerpt field (simpler, no images)
    if (document.querySelector('#excerpt')) {
        tinymce.init({
            selector: '#excerpt',
            height: 180,
            branding: false,
            menubar: false,
            toolbar: 'undo redo | bold italic | bullist numlist',
            paste_data_images: false,
            content_style: 'body { font-family: Arial, sans-serif; font-size: 14px; }',
            setup: function(editor) {
                editor.on('change', function() {
                    editor.save();
                });
            }
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

function showFaqPreview() {
    const question = getFieldValue('question') || '(No question)';
    const excerpt = getEditorContent('excerpt');
    const answer = getEditorContent('answer');

    const container = document.getElementById('faq-preview-container');
    const titleEl = document.getElementById('faq-preview-title');
    const excerptEl = document.getElementById('faq-preview-excerpt');
    const contentEl = document.getElementById('faq-preview-content');

    if (!container || !titleEl || !excerptEl || !contentEl) {
        console.error('Preview elements not found');
        return;
    }

    titleEl.textContent = question;
    excerptEl.innerHTML = excerpt || '';
    contentEl.innerHTML = answer || '<p><em>No content</em></p>';

    container.style.display = 'block';
    container.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// Handle FAQ title hover tooltips
document.addEventListener('DOMContentLoaded', function() {
    const faqTitles = document.querySelectorAll('.faq-title-hover');
    faqTitles.forEach(function(titleEl) {
        const tooltip = titleEl.nextElementSibling;
        if (!tooltip || !tooltip.classList.contains('faq-tooltip')) return;
        
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
    
    // Form submission handler
    const createForm = document.getElementById('createFaqForm');
    if (createForm) {
        createForm.addEventListener('submit', function(e) {
            // Get content from TinyMCE
            const answer = tinymce.get('answer');
            if (answer) {
                const content = answer.getContent();
                if (!content || content.trim() === '' || content === '<p></p>') {
                    e.preventDefault();
                    alert('Answer (Content) is required.');
                    return false;
                }
                // Update hidden textarea with TinyMCE content
                document.getElementById('answer').value = content;
            }
            
            const excerpt = tinymce.get('excerpt');
            if (excerpt) {
                document.getElementById('excerpt').value = excerpt.getContent();
            }
        });
    }
});
</script>

<div class="container">
    <div class="content-area">
        <h1>Manage Frequently Asked Questions</h1>

        <?php if ($message): ?>
            <div class="alert alert-success"><?= h($message) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>

        <div class="news-management">
            <h2>Create New FAQ Item</h2>
            <form method="POST" action="" id="createFaqForm">
                <input type="hidden" name="action" value="create">

                <div class="form-group">
                    <label for="question">Question (Title): <span class="required">*</span></label>
                    <input type="text" id="question" name="question" required>
                </div>

                <div class="form-group">
                    <label for="excerpt">Preview/Excerpt:</label>
                    <textarea id="excerpt" name="excerpt" rows="2"></textarea>
                    <small>A brief preview that will be shown in the FAQ list</small>
                </div>

                <div class="form-group">
                    <label for="answer">Answer (Content): <span class="required">*</span></label>
                    <textarea id="answer" name="answer" rows="10" required></textarea>
                </div>

                <div class="form-group">
                    <label for="display_order">Display Order:</label>
                    <input type="number" id="display_order" name="display_order" value="0" min="0">
                    <small>Lower numbers appear first.</small>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_active" value="1" checked>
                        Active
                    </label>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Create FAQ Item</button>
                    <button type="button" class="btn btn-secondary" onclick="showFaqPreview()">Preview</button>
                </div>
            </form>

            <div id="faq-preview-container" style="display:none; margin-top: 2rem; padding: 1.5rem; background-color: #ffffff; border-radius: 8px; border: 1px solid #ddd;">
                <h2 style="margin-top: 0; margin-bottom: 1rem;">Preview</h2>
                <article class="news-preview-article">
                    <h3 id="faq-preview-title" style="margin-top: 0;"></h3>
                    <div id="faq-preview-excerpt" style="color: #555; margin-bottom: 1rem;"></div>
                    <div id="faq-preview-content"></div>
                </article>
            </div>
        </div>
        
        <div class="news-list">
            <h2>Existing FAQ Items</h2>
            <?php if (empty($faqs)): ?>
                <p>No FAQ items found.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Question</th>
                            <th>Display Order</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($faqs as $faq): 
                            // Prepare tooltip content
                            $date = formatDate($faq['created_at']);
                            $excerptText = !empty($faq['excerpt']) ? $faq['excerpt'] : strip_tags($faq['answer']);
                            $fullContent = strip_tags($faq['answer']);
                            $preview = h(substr($excerptText, 0, 300));
                            if (strlen($excerptText) > 300) {
                                $preview .= '...';
                            }
                            ?>
                            <tr>
                                <td>
                                    <div style="position: relative; display: inline-block;">
                                        <span class="faq-title-hover" style="cursor: help; text-decoration: underline; text-decoration-style: dotted; text-decoration-color: #999;">
                                            <?= h($faq['question']) ?>
                                        </span>
                                        <div class="faq-tooltip" style="display: none; position: absolute; bottom: 100%; left: 0; background-color: #333; color: white; padding: 0.75rem 1rem; border-radius: 4px; min-width: 300px; max-width: 500px; z-index: 1000; margin-bottom: 0.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.2); font-size: 0.875rem; line-height: 1.5; white-space: normal; word-wrap: break-word;">
                                            <div style="margin-bottom: 0.5rem;"><strong>Created:</strong> <?= $date ?></div>
                                            <div style="border-top: 1px solid rgba(255,255,255,0.2); padding-top: 0.5rem; margin-top: 0.5rem;"><strong>Preview:</strong><br><?= nl2br($preview) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?= (int)$faq['display_order'] ?></td>
                                <td>
                                    <?php if ($faq['is_active']): ?>
                                        <span style="color: green;">Active</span>
                                    <?php else: ?>
                                        <span style="color: #999;">Hidden</span>
                                    <?php endif; ?>
                                </td>
                                <td style="white-space: nowrap; min-width: 200px;">
                                    <div style="display: flex; gap: 0.25rem; flex-wrap: nowrap; align-items: center;">
                                        <a href="<?= baseUrl('/admin/faq-edit.php?id=' . $faq['faq_id']) ?>" class="btn btn-sm btn-secondary" style="flex-shrink: 0;">Edit</a>
                                        <form method="POST" action="" style="display: inline-flex; margin: 0; flex-shrink: 0;" onsubmit="return confirm('Are you sure you want to delete this FAQ item? This action cannot be undone.');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="faq_id" value="<?= (int)$faq['faq_id'] ?>">
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


