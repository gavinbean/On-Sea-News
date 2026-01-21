<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('ADMIN');

$db = getDB();
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update') {
        $optionId = (int)($_POST['option_id'] ?? 0);
        $description = $_POST['description'] ?? '';
        $price = !empty($_POST['price']) ? (float)$_POST['price'] : 0.00;
        $allowedAdverts = !empty($_POST['allowed_adverts']) ? (int)$_POST['allowed_adverts'] : 0;
        
        if ($optionId > 0) {
            // Check if price and allowed_adverts columns exist (backward compatibility)
            $priceColumnExists = false;
            $allowedAdvertsColumnExists = false;
            try {
                $testStmt = $db->query("SELECT price, allowed_adverts FROM " . TABLE_PREFIX . "business_pricing_options LIMIT 1");
                $priceColumnExists = true;
                $allowedAdvertsColumnExists = true;
            } catch (Exception $e) {
                try {
                    $testStmt = $db->query("SELECT price FROM " . TABLE_PREFIX . "business_pricing_options LIMIT 1");
                    $priceColumnExists = true;
                } catch (Exception $e2) {
                    $priceColumnExists = false;
                }
            }
            
            if ($priceColumnExists && $allowedAdvertsColumnExists) {
                $stmt = $db->prepare("
                    UPDATE " . TABLE_PREFIX . "business_pricing_options 
                    SET description = ?, price = ?, allowed_adverts = ?
                    WHERE option_id = ?
                ");
                $stmt->execute([$description, $price, $allowedAdverts, $optionId]);
            } elseif ($priceColumnExists) {
                $stmt = $db->prepare("
                    UPDATE " . TABLE_PREFIX . "business_pricing_options 
                    SET description = ?, price = ?
                    WHERE option_id = ?
                ");
                $stmt->execute([$description, $price, $optionId]);
            } else {
                $stmt = $db->prepare("
                    UPDATE " . TABLE_PREFIX . "business_pricing_options 
                    SET description = ?
                    WHERE option_id = ?
                ");
                $stmt->execute([$description, $optionId]);
            }
            $message = 'Pricing option updated successfully.';
        } else {
            $error = 'Invalid option ID.';
        }
    }
}

// Get all pricing options
$stmt = $db->query("
    SELECT * FROM " . TABLE_PREFIX . "business_pricing_options 
    ORDER BY display_order
");
$pricingOptions = $stmt->fetchAll();

$pageTitle = 'Manage Pricing Options';
include __DIR__ . '/../includes/header.php';
?>

<!-- TinyMCE for rich text editing -->
<script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.3/tinymce.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize TinyMCE for all description textareas
    const descriptionTextareas = document.querySelectorAll('.pricing-description-editor');
    descriptionTextareas.forEach(textarea => {
        tinymce.init({
            selector: '#' + textarea.id,
            height: 300,
            branding: false,
            menubar: 'file edit view insert format tools table help',
            plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table code help wordcount',
            toolbar: 'undo redo | blocks | bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media table | removeformat | code fullscreen',
            paste_data_images: true,
            content_style: 'body { font-family: Arial, sans-serif; font-size: 14px; }',
            setup: function(editor) {
                editor.on('change', function() {
                    editor.save();
                });
            }
        });
    });
});

function getEditorContent(id) {
    if (window.tinymce && tinymce.get(id)) {
        return tinymce.get(id).getContent();
    }
    const el = document.getElementById(id);
    return el ? el.value : '';
}

function submitForm(optionId) {
    const form = document.getElementById('form-' + optionId);
    const description = getEditorContent('description-' + optionId);
    
    // Update the hidden textarea with TinyMCE content
    const textarea = document.getElementById('description-' + optionId);
    if (textarea) {
        textarea.value = description;
    }
    
    // Validate price
    const priceInput = document.getElementById('price-' + optionId);
    if (priceInput) {
        const price = parseFloat(priceInput.value);
        if (isNaN(price) || price < 0) {
            alert('Please enter a valid price (0 or greater).');
            priceInput.focus();
            return;
        }
    }
    
    // Validate allowed adverts
    const allowedAdvertsInput = document.getElementById('allowed_adverts-' + optionId);
    if (allowedAdvertsInput) {
        const allowedAdverts = parseInt(allowedAdvertsInput.value);
        if (isNaN(allowedAdverts) || allowedAdverts < 0) {
            alert('Please enter a valid number of allowed adverts (0 or greater).');
            allowedAdvertsInput.focus();
            return;
        }
    }
    
    form.submit();
}
</script>

<div class="container">
    <div class="content-area">
        <h1>Manage Pricing Options</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= h($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        
        <div class="pricing-options-list">
            <?php foreach ($pricingOptions as $option): ?>
                <div class="pricing-option-card" style="background: white; border-radius: 8px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0; color: var(--primary-color);">
                        <?= h($option['option_name']) ?> 
                        <span style="font-size: 0.8rem; color: #666; font-weight: normal;">
                            (<?= h($option['option_slug']) ?>)
                        </span>
                    </h2>
                    
                    <form method="POST" action="" id="form-<?= $option['option_id'] ?>" onsubmit="event.preventDefault(); submitForm(<?= $option['option_id'] ?>);">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="option_id" value="<?= $option['option_id'] ?>">
                        
                        <div class="form-group">
                            <label for="price-<?= $option['option_id'] ?>">Price (ZAR):</label>
                            <input type="number" 
                                   id="price-<?= $option['option_id'] ?>" 
                                   name="price" 
                                   step="0.01" 
                                   min="0" 
                                   value="<?= number_format((float)($option['price'] ?? 0), 2, '.', '') ?>" 
                                   style="width: 200px; padding: 0.5rem; font-size: 1rem; border: 1px solid var(--border-color); border-radius: 4px;">
                            <small style="display: block; margin-top: 0.5rem; color: #666;">
                                Set the price for this pricing option. Used for calculating outstanding costs.
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label for="allowed_adverts-<?= $option['option_id'] ?>">Allowed Adverts:</label>
                            <input type="number" 
                                   id="allowed_adverts-<?= $option['option_id'] ?>" 
                                   name="allowed_adverts" 
                                   step="1" 
                                   min="0" 
                                   value="<?= (int)($option['allowed_adverts'] ?? 0) ?>" 
                                   style="width: 200px; padding: 0.5rem; font-size: 1rem; border: 1px solid var(--border-color); border-radius: 4px;">
                            <small style="display: block; margin-top: 0.5rem; color: #666;">
                                Maximum number of adverts that can be created and displayed for this pricing option.
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label for="description-<?= $option['option_id'] ?>">Description:</label>
                            <textarea id="description-<?= $option['option_id'] ?>" 
                                      name="description" 
                                      class="pricing-description-editor"
                                      rows="10"><?= h($option['description'] ?? '') ?></textarea>
                            <small style="display: block; margin-top: 0.5rem; color: #666;">
                                This description will be shown to users when they select a pricing option.
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Update Pricing Option</button>
                        </div>
                    </form>
                    
                    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e0e0e0; color: #666; font-size: 0.9rem;">
                        <strong>Display Order:</strong> <?= $option['display_order'] ?> | 
                        <strong>Status:</strong> <?= $option['is_active'] ? 'Active' : 'Inactive' ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php 
$hideAdverts = true;
include __DIR__ . '/../includes/footer.php'; 
?>
