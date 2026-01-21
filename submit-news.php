<?php
require_once __DIR__ . '/includes/functions.php';
requireLogin(); // All registered users can submit news

$pageTitle = 'Submit News';
include __DIR__ . '/includes/header.php';

$db = getDB();
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';
    $excerpt = $_POST['excerpt'] ?? '';
    $showPublishDate = isset($_POST['show_publish_date']) ? 1 : 0;
    $showAuthor = isset($_POST['show_author']) ? 1 : 0;
    $userId = getCurrentUserId();
    
    // Debug: Log content size for troubleshooting
    error_log('News submission - Title length: ' . strlen($title));
    error_log('News submission - Content length: ' . strlen($content));
    error_log('News submission - Excerpt length: ' . strlen($excerpt));
    
    // Check if content was truncated (POST size limit hit)
    if (empty($content) && !empty($title)) {
        $error = 'Content appears to be empty or too large. Please reduce image sizes or split the content.';
        error_log('News submission error: Content is empty but title exists - possible POST size limit exceeded');
    } elseif (empty($title) || empty($content)) {
        $error = 'Title and content are required.';
        error_log('News submission error: Title or content missing - Title: ' . (!empty($title) ? 'exists' : 'empty') . ', Content: ' . (!empty($content) ? 'exists (length: ' . strlen($content) . ')' : 'empty'));
    } else {
        try {
            // Check if new columns exist
            $columnsExist = false;
            try {
                $testStmt = $db->query("SELECT pending_approval, show_publish_date, show_author FROM " . TABLE_PREFIX . "news LIMIT 1");
                $columnsExist = true;
            } catch (Exception $e) {
                // Columns don't exist yet - use old schema
                $columnsExist = false;
            }
            
            if ($columnsExist) {
                // Insert news item with pending_approval = 1
                $stmt = $db->prepare("
                    INSERT INTO " . TABLE_PREFIX . "news 
                    (author_id, title, content, excerpt, published, pending_approval, show_publish_date, show_author)
                    VALUES (?, ?, ?, ?, 0, 1, ?, ?)
                ");
                $stmt->execute([$userId, $title, $content, $excerpt, $showPublishDate, $showAuthor]);
            } else {
                // Fallback: insert without new columns (will need manual approval)
                $stmt = $db->prepare("
                    INSERT INTO " . TABLE_PREFIX . "news 
                    (author_id, title, content, excerpt, published)
                    VALUES (?, ?, ?, ?, 0)
                ");
                $stmt->execute([$userId, $title, $content, $excerpt]);
            }
            
            // Send email notification to all admins
            require_once __DIR__ . '/includes/email.php';
            
            // Use the existing helper function to get all admin users
            $adminUsers = getUsersByRole('ADMIN');
            
            if (empty($adminUsers)) {
                error_log('No ADMIN users found to notify about news submission.');
            } else {
                error_log('Found ' . count($adminUsers) . ' ADMIN user(s) to notify about news submission.');
                
                $submitterName = getCurrentUser()['name'] . ' ' . getCurrentUser()['surname'];
                $siteName = SITE_NAME;
                $siteUrl = SITE_URL;
                $subject = "New News Submission Pending Approval - " . $siteName;
                $approvalUrl = $siteUrl . baseUrl('/admin/approve-news.php');
                
                $body = "
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <meta charset='UTF-8'>
                        <style>
                            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                            .header { background-color: #2c5f8d; color: white; padding: 20px; text-align: center; }
                            .content { padding: 20px; background-color: #f5f5f5; }
                            .button { display: inline-block; padding: 12px 24px; background-color: #2c5f8d; color: white; text-decoration: none; border-radius: 4px; margin: 20px 0; }
                            .footer { padding: 20px; text-align: center; color: #666; font-size: 12px; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h1>New News Submission</h1>
                            </div>
                            <div class='content'>
                                <p>Hello Admin,</p>
                                <p>A new news item titled <strong>\"" . h($title) . "\"</strong> has been submitted by <strong>" . h($submitterName) . "</strong> and is awaiting your approval.</p>
                                <p>Please review the submission and take appropriate action.</p>
                                <p style='text-align: center;'>
                                    <a href='" . $approvalUrl . "' class='button'>Review News Submissions</a>
                                </p>
                                <p>Thank you.</p>
                            </div>
                            <div class='footer'>
                                <p>&copy; " . date('Y') . " " . h($siteName) . ". All rights reserved.</p>
                            </div>
                        </div>
                    </body>
                    </html>
                ";
                
                $emailsSent = 0;
                $emailsFailed = 0;
                
                foreach ($adminUsers as $admin) {
                    $result = sendEmail($admin['email'], $subject, $body);
                    
                    if ($result) {
                        $emailsSent++;
                        error_log("News submission notification email sent successfully to: " . $admin['email']);
                    } else {
                        $emailsFailed++;
                        error_log("Failed to send news submission notification email to: " . $admin['email']);
                    }
                }
                
                error_log("News submission email summary: $emailsSent sent, $emailsFailed failed out of " . count($adminUsers) . " admin(s)");
            }
            
            $message = 'Your news item has been submitted successfully and is pending admin approval.';
            error_log('News submission successful - Title: ' . substr($title, 0, 50) . ', Content length: ' . strlen($content) . ', Excerpt length: ' . strlen($excerpt));
        } catch (Exception $e) {
            $error = 'Error submitting news: ' . $e->getMessage();
            error_log('News submission error: ' . $e->getMessage());
            error_log('News submission error - Stack trace: ' . $e->getTraceAsString());
        }
    }
}
?>

<div class="container">
    <div class="content-area">
        <h1>Submit News</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= h($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        
        <form method="POST" id="submitNewsForm">
            <div class="form-group">
                <label for="title">Title <span class="required">*</span></label>
                <input type="text" id="title" name="title" class="form-control" required value="<?= h($_POST['title'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label for="excerpt">Excerpt (optional)</label>
                <textarea id="excerpt" name="excerpt" class="form-control" rows="3"><?= $_POST['excerpt'] ?? '' ?></textarea>
                <small class="form-text">A brief summary of the news item that will be shown in the news listing</small>
            </div>
            
            <div class="form-group">
                <label for="content">Content <span class="required">*</span></label>
                <textarea id="content" name="content" rows="10" required><?= h($_POST['content'] ?? '') ?></textarea>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="show_publish_date" value="1" <?= isset($_POST['show_publish_date']) ? 'checked' : 'checked' ?>>
                    Show publish date on news item
                </label>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="show_author" value="1" <?= isset($_POST['show_author']) ? 'checked' : 'checked' ?>>
                    Show author name on news item
                </label>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Submit for Approval</button>
                <a href="<?= baseUrl('/index.php') ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<!-- TinyMCE for rich text editing -->
<script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.3/tinymce.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize TinyMCE for content field (full editor with image support)
    tinymce.init({
        selector: '#content',
        height: 420,
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
                        console.log('Content image uploaded successfully, size: ' + Math.round(result.length / 1024) + 'KB');
                        resolve(result);
                    };
                    reader.onerror = function(error) {
                        console.error('Content image upload error:', error);
                        reject('Image upload failed. Please try again.');
                    };
                    reader.readAsDataURL(blobInfo.blob());
                } catch (error) {
                    console.error('Content image upload handler error:', error);
                    reject('Image upload failed: ' + error.message);
                }
            });
        },
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
                const textarea = document.getElementById('content');
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
            
            // Handle paste events to ensure content is preserved (CONTENT EDITOR)
            editor.on('paste', function(e) {
                // Store current content before paste to ensure it's preserved
                const currentContent = editor.getContent();
                console.log('Content editor paste - current content length:', currentContent.length);
                // Auto-save after paste operations complete
                setTimeout(function() {
                    const newContent = editor.getContent();
                    console.log('Content editor paste - new content length:', newContent.length);
                    // If content was lost during paste (safety check)
                    if ((!newContent || newContent.trim() === '' || newContent === '<p></p>') && currentContent && currentContent.trim() !== '' && currentContent !== '<p></p>') {
                        console.warn('Content appears to have been lost during paste, restoring...');
                        // Restore previous content and try to insert pasted content
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
    
    // Initialize TinyMCE for excerpt field (full editor with image support)
    tinymce.init({
        selector: '#excerpt',
        height: 300,
        branding: false,
        menubar: 'file edit view insert format tools table help',
        plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table code help wordcount',
        toolbar: 'undo redo | blocks | bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media table | removeformat | code fullscreen',
        paste_data_images: true,
        paste_as_text: false,
        paste_merge_formats: true,
        paste_auto_cleanup_on_paste: true,
        paste_remove_styles_if_webkit: false,
        paste_strip_class_attributes: 'none',
        image_title: true,
        automatic_uploads: true,
        images_upload_handler: (blobInfo) => new Promise((resolve, reject) => {
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
                    console.log('Excerpt image uploaded successfully, size: ' + Math.round(result.length / 1024) + 'KB');
                    resolve(result);
                };
                reader.onerror = function(error) {
                    console.error('Excerpt image upload error:', error);
                    reject('Image upload failed. Please try again.');
                };
                reader.readAsDataURL(blobInfo.blob());
            } catch (error) {
                console.error('Excerpt image upload handler error:', error);
                reject('Image upload failed: ' + error.message);
            }
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
            // Auto-save on change to ensure excerpt is always in sync
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
            
            // Handle paste events to ensure content is preserved (EXCERPT EDITOR)
            editor.on('paste', function(e) {
                // Store current content before paste to ensure it's preserved
                const currentContent = editor.getContent();
                // Auto-save after paste operations complete
                setTimeout(function() {
                    const newContent = editor.getContent();
                    // If content was lost during paste (shouldn't happen, but safety check)
                    if (!newContent || (newContent.trim() === '' || newContent === '<p></p>') && currentContent && currentContent.trim() !== '') {
                        console.warn('Excerpt content appears to have been lost during paste, attempting to restore');
                        // Try to restore by inserting pasted content into existing content
                        editor.setContent(currentContent);
                    }
                    editor.save();
                }, 200);
            });
            
            // Save content before any resize operations
            editor.on('ObjectResizeStart', function(e) {
                editor.save();
            });
        }
    });
    
    // Form submission handler - ensure TinyMCE content is saved before submission
    const submitForm = document.getElementById('submitNewsForm');
    if (submitForm) {
        submitForm.addEventListener('submit', function(e) {
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
            
            // Wait for save to complete (TinyMCE save is synchronous)
            setTimeout(function() {
                // Get content from textarea (after save)
                const contentTextarea = document.getElementById('content');
                const excerptTextarea = document.getElementById('excerpt');
                
                const content = contentTextarea ? contentTextarea.value : '';
                const excerpt = excerptTextarea ? excerptTextarea.value : '';
                
                // Check if content is actually there (not just empty HTML)
                // Check for meaningful content - images, text, or other elements
                let hasContent = false;
                if (content && content.trim() !== '') {
                    // Remove empty paragraphs and whitespace to check if there's real content
                    let contentStripped = content.replace(/<p>&nbsp;<\/p>/g, '').replace(/<p><\/p>/g, '').replace(/<br\s*\/?>/gi, '').trim();
                    contentStripped = contentStripped.replace(/<p>\s*<\/p>/g, '').trim();
                    
                    // Check if there's actual content (images, text, or other elements)
                    const hasText = contentStripped.replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim().length > 0;
                    const hasImages = content.includes('<img');
                    const hasMedia = content.includes('<iframe') || content.includes('<video') || content.includes('<embed');
                    
                    hasContent = (hasText || hasImages || hasMedia) && contentStripped !== '' && contentStripped !== '<p></p>' && contentStripped !== '<p>&nbsp;</p>';
                }
                
                // If content from textarea appears empty, try to get it from editor
                if (!hasContent && contentEditor) {
                    const editorContent = contentEditor.getContent();
                    console.log('Content from textarea length:', content.length);
                    console.log('Content from editor length:', editorContent.length);
                    
                    if (editorContent && editorContent.trim() !== '' && editorContent !== '<p></p>') {
                        // Editor has content but textarea doesn't - sync them
                        contentTextarea.value = editorContent;
                        content = editorContent; // Update content variable for further checks
                        
                        // Re-validate with editor content
                        const editorContentStripped = editorContent.replace(/<p>&nbsp;<\/p>/g, '').replace(/<p><\/p>/g, '').trim();
                        const editorHasText = editorContent.replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim().length > 0;
                        const editorHasImages = editorContent.includes('<img');
                        const editorHasMedia = editorContent.includes('<iframe') || editorContent.includes('<video') || editorContent.includes('<embed');
                        
                        if (editorContentStripped !== '' && editorContentStripped !== '<p></p>' && (editorHasText || editorHasImages || editorHasMedia)) {
                            // Editor has valid content, continue with submission
                            console.log('Using content from editor (length: ' + editorContent.length + ')');
                            hasContent = true;
                        }
                    }
                }
                
                // Final validation
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
                
                // Validate content length (check for potential issues with large base64 images)
                // Base64 images can be very large, but warn if extremely large (>5MB)
                if (content.length > 5000000) {
                    if (!confirm('The content appears to be very large (' + Math.round(content.length / 1024 / 1024) + 'MB). This may be due to embedded images. Large content may cause submission issues. Do you want to continue?')) {
                        return false;
                    }
                }
                
                // Ensure textareas have the content before submission
                if (contentTextarea) {
                    contentTextarea.value = content;
                }
                if (excerptTextarea) {
                    excerptTextarea.value = excerpt;
                }
                
                // All validation passed, submit the form (remove preventDefault by submitting manually)
                submitForm.submit();
            }, 150); // Small delay to ensure TinyMCE has finished saving
        });
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
