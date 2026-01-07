<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('ADMIN');

$db = getDB();
$message = '';
$error = '';

// Handle category actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_category') {
        $categoryName = trim($_POST['category_name'] ?? '');
        $categorySlug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $categoryName));
        $categorySlug = trim($categorySlug, '-');
        $displayOrder = (int)($_POST['display_order'] ?? 0);
        
        if (empty($categoryName)) {
            $error = 'Category name is required.';
        } else {
            $stmt = $db->prepare("
                INSERT INTO " . TABLE_PREFIX . "business_categories 
                (category_name, category_slug, display_order)
                VALUES (?, ?, ?)
            ");
            try {
                $stmt->execute([$categoryName, $categorySlug, $displayOrder]);
                $message = 'Category created successfully.';
            } catch (PDOException $e) {
                $error = 'Category name or slug already exists.';
            }
        }
    } elseif ($_POST['action'] === 'update_category') {
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $categoryName = trim($_POST['category_name'] ?? '');
        $categorySlug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $categoryName));
        $categorySlug = trim($categorySlug, '-');
        $displayOrder = (int)($_POST['display_order'] ?? 0);
        
        if (empty($categoryName)) {
            $error = 'Category name is required.';
        } elseif ($categoryId <= 0) {
            $error = 'Invalid category ID.';
        } else {
            $stmt = $db->prepare("
                UPDATE " . TABLE_PREFIX . "business_categories 
                SET category_name = ?, category_slug = ?, display_order = ?
                WHERE category_id = ?
            ");
            try {
                $stmt->execute([$categoryName, $categorySlug, $displayOrder, $categoryId]);
                $message = 'Category updated successfully.';
            } catch (PDOException $e) {
                $error = 'Category name or slug already exists.';
            }
        }
    } elseif ($_POST['action'] === 'delete_category') {
        $categoryId = (int)($_POST['category_id'] ?? 0);
        
        if ($categoryId <= 0) {
            $error = 'Invalid category ID.';
        } else {
            // Check if category is in use
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM " . TABLE_PREFIX . "businesses WHERE category_id = ?");
            $stmt->execute([$categoryId]);
            $result = $stmt->fetch();
            
            if ($result['count'] > 0) {
                $error = 'Cannot delete category: ' . $result['count'] . ' business(es) are using this category.';
            } else {
                $stmt = $db->prepare("DELETE FROM " . TABLE_PREFIX . "business_categories WHERE category_id = ?");
                $stmt->execute([$categoryId]);
                $message = 'Category deleted successfully.';
            }
        }
    }
}

// Get category to edit (if editing)
$editCategory = null;
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
if ($editId > 0) {
    $stmt = $db->prepare("SELECT * FROM " . TABLE_PREFIX . "business_categories WHERE category_id = ?");
    $stmt->execute([$editId]);
    $editCategory = $stmt->fetch();
    if (!$editCategory) {
        $editId = 0;
        $editCategory = null;
    }
}

// Get all categories with business counts
$stmt = $db->query("
    SELECT c.*, COUNT(b.business_id) as business_count
    FROM " . TABLE_PREFIX . "business_categories c
    LEFT JOIN " . TABLE_PREFIX . "businesses b ON c.category_id = b.category_id
    GROUP BY c.category_id
    ORDER BY c.display_order, c.category_name
");
$categories = $stmt->fetchAll();

$pageTitle = 'Manage Business Categories';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="content-area">
        <h1>Manage Business Categories</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= h($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        
        <div class="category-form">
            <h2><?= $editCategory ? 'Edit Category' : 'Create New Category' ?></h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="<?= $editCategory ? 'update_category' : 'create_category' ?>">
                <?php if ($editCategory): ?>
                    <input type="hidden" name="category_id" value="<?= $editCategory['category_id'] ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="category_name">Category Name: <span class="required">*</span></label>
                    <input type="text" id="category_name" name="category_name" value="<?= $editCategory ? h($editCategory['category_name']) : '' ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="display_order">Display Order:</label>
                    <input type="number" id="display_order" name="display_order" value="<?= $editCategory ? $editCategory['display_order'] : '0' ?>">
                    <small>Lower numbers appear first</small>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary"><?= $editCategory ? 'Update Category' : 'Create Category' ?></button>
                    <?php if ($editCategory): ?>
                        <a href="<?= baseUrl('/admin/businesses.php') ?>" class="btn btn-secondary">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <div class="categories-list">
            <h2>Existing Categories</h2>
            <?php if (empty($categories)): ?>
                <p>No categories yet.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Category Name</th>
                            <th>Slug</th>
                            <th>Display Order</th>
                            <th>Businesses</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td><?= h($category['category_name']) ?></td>
                                <td><code><?= h($category['category_slug']) ?></code></td>
                                <td><?= h($category['display_order']) ?></td>
                                <td><?= (int)$category['business_count'] ?></td>
                                <td>
                                    <a href="<?= baseUrl('/admin/businesses.php?edit=' . $category['category_id']) ?>" class="btn btn-sm btn-secondary">Edit</a>
                                    <?php if ($category['business_count'] == 0): ?>
                                        <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this category?');">
                                            <input type="hidden" name="action" value="delete_category">
                                            <input type="hidden" name="category_id" value="<?= $category['category_id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color: #666; font-size: 0.9rem;">Cannot delete (in use)</span>
                                    <?php endif; ?>
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


