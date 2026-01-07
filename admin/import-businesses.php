<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('ADMIN');

$db = getDB();
$message = '';
$error = '';
$imported = 0;
$skipped = 0;

// Handle business import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import') {
    $data = $_POST['data'] ?? '';
    
    if (empty($data)) {
        $error = 'No data provided.';
    } else {
        // Parse the data - expecting format:
        // Category Name
        // Company Name | Contact Name | Contact Number
        // Company Name | Contact Name | Contact Number
        // ...
        // (blank line)
        // Next Category Name
        // ...
        
        $lines = explode("\n", $data);
        $currentCategory = null;
        $categoryId = null;
        $db->beginTransaction();
        
        try {
            foreach ($lines as $line) {
                $line = trim($line);
                
                // Skip empty lines
                if (empty($line)) {
                    $currentCategory = null;
                    $categoryId = null;
                    continue;
                }
                
                // Check if this is a category (heading) - usually all caps, or has no pipe separator
                // If line contains |, it's a business entry
                if (strpos($line, '|') === false) {
                    // This might be a category heading
                    // Check if it looks like a heading (not a phone number, not just numbers)
                    if (!preg_match('/^[\d\s\-\(\)]+$/', $line) && strlen($line) > 2) {
                        $currentCategory = $line;
                        
                        // Create or get category
                        $categorySlug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $currentCategory));
                        $categorySlug = trim($categorySlug, '-');
                        
                        $stmt = $db->prepare("SELECT category_id FROM " . TABLE_PREFIX . "business_categories WHERE category_slug = ?");
                        $stmt->execute([$categorySlug]);
                        $existing = $stmt->fetch();
                        
                        if ($existing) {
                            $categoryId = $existing['category_id'];
                        } else {
                            $stmt = $db->prepare("
                                INSERT INTO " . TABLE_PREFIX . "business_categories 
                                (category_name, category_slug, display_order)
                                VALUES (?, ?, 0)
                            ");
                            $stmt->execute([$currentCategory, $categorySlug]);
                            $categoryId = $db->lastInsertId();
                        }
                    }
                } else {
                    // This is a business entry: Company | Contact Name | Contact Number
                    if (!$categoryId) {
                        // No category set, skip or use default
                        $skipped++;
                        continue;
                    }
                    
                    $parts = array_map('trim', explode('|', $line));
                    $companyName = $parts[0] ?? '';
                    $contactName = $parts[1] ?? '';
                    $contactNumber = $parts[2] ?? '';
                    
                    // Clean up contact number (remove common formatting, but keep the original if it looks valid)
                    $originalContact = $contactNumber;
                    $contactNumber = preg_replace('/[^\d\+\-\(\)\s]/', '', $contactNumber);
                    // If cleaning removed everything, use original
                    if (empty($contactNumber) && !empty($originalContact)) {
                        $contactNumber = $originalContact;
                    }
                    
                    if (empty($companyName)) {
                        $skipped++;
                        continue;
                    }
                    
                    // Check if business already exists (by name and category)
                    $stmt = $db->prepare("
                        SELECT business_id FROM " . TABLE_PREFIX . "businesses 
                        WHERE business_name = ? AND category_id = ?
                    ");
                    $stmt->execute([$companyName, $categoryId]);
                    if ($stmt->fetch()) {
                        $skipped++;
                        continue;
                    }
                    
                    // Insert business (user_id can be NULL for imported businesses)
                    // Imported businesses are auto-approved (is_approved = 1)
                    $stmt = $db->prepare("
                        INSERT INTO " . TABLE_PREFIX . "businesses 
                        (user_id, category_id, business_name, contact_name, telephone, is_approved)
                        VALUES (NULL, ?, ?, ?, ?, 1)
                    ");
                    $stmt->execute([$categoryId, $companyName, $contactName ?: null, $contactNumber ?: null]);
                    $imported++;
                }
            }
            
            $db->commit();
            $message = "Import completed: $imported businesses imported, $skipped skipped.";
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Import failed: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Import Businesses';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="content-area">
        <h1>Import Businesses from PDF</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= h($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        
        <div class="import-instructions">
            <h2>Instructions</h2>
            <ol>
                <li>Extract text from the PDF (copy and paste)</li>
                <li>Format the data as follows:
                    <ul>
                        <li>Category headings should be on their own line (no pipe separator)</li>
                        <li>Business entries should be: <code>Company Name | Contact Name | Contact Number</code></li>
                        <li>Use blank lines to separate categories</li>
                    </ul>
                </li>
                <li>Paste the formatted data in the text area below</li>
                <li>Click "Import Businesses"</li>
            </ol>
            
            <h3>Example Format:</h3>
            <pre>PLUMBING
ABC Plumbing | John Smith | 041 123 4567
XYZ Plumbing | Jane Doe | 042 987 6543

ELECTRICAL
Electric Co | Bob Johnson | 043 555 1234</pre>
        </div>
        
        <div class="import-form">
            <form method="POST" action="">
                <input type="hidden" name="action" value="import">
                
                <div class="form-group">
                    <label for="data">Business Data:</label>
                    <textarea id="data" name="data" rows="20" style="width: 100%; font-family: monospace;" placeholder="Paste extracted text here..."></textarea>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Import Businesses</button>
                </div>
            </form>
        </div>
    </div>
</div>


<?php 
$hideAdverts = true;
include __DIR__ . '/../includes/footer.php'; 
?>

