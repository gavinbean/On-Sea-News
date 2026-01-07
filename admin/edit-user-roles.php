<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('ADMIN');

$db = getDB();
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$error = '';

if ($userId <= 0) {
    redirect('/admin/users.php');
    exit;
}

// Get user
$stmt = $db->prepare("SELECT * FROM " . TABLE_PREFIX . "users WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    redirect('/admin/users.php');
    exit;
}

// Get user's current roles
$stmt = $db->prepare("
    SELECT r.role_id, r.role_name 
    FROM " . TABLE_PREFIX . "user_roles ur
    JOIN " . TABLE_PREFIX . "roles r ON ur.role_id = r.role_id
    WHERE ur.user_id = ?
");
$stmt->execute([$userId]);
$currentRoles = $stmt->fetchAll();
$userRoleIds = !empty($currentRoles) ? array_column($currentRoles, 'role_id') : [];

// Get all available roles
$stmt = $db->query("SELECT * FROM " . TABLE_PREFIX . "roles ORDER BY role_name");
$allRoles = $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_roles') {
    $selectedRoleIds = isset($_POST['roles']) ? array_map('intval', $_POST['roles']) : [];
    
    try {
        $db->beginTransaction();
        
        // Remove all current roles
        $stmt = $db->prepare("DELETE FROM " . TABLE_PREFIX . "user_roles WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        // Add selected roles
        if (!empty($selectedRoleIds)) {
            $stmt = $db->prepare("INSERT INTO " . TABLE_PREFIX . "user_roles (user_id, role_id) VALUES (?, ?)");
            foreach ($selectedRoleIds as $roleId) {
                // Verify role exists
                $checkStmt = $db->prepare("SELECT role_id FROM " . TABLE_PREFIX . "roles WHERE role_id = ?");
                $checkStmt->execute([$roleId]);
                if ($checkStmt->fetch()) {
                    $stmt->execute([$userId, $roleId]);
                }
            }
        }
        
        $db->commit();
        $message = 'User roles updated successfully.';
        
        // Reload user roles
        $stmt = $db->prepare("
            SELECT r.role_id, r.role_name 
            FROM " . TABLE_PREFIX . "user_roles ur
            JOIN " . TABLE_PREFIX . "roles r ON ur.role_id = r.role_id
            WHERE ur.user_id = ?
        ");
        $stmt->execute([$userId]);
        $currentRoles = $stmt->fetchAll();
        $userRoleIds = array_column($currentRoles, 'role_id');
    } catch (Exception $e) {
        $db->rollBack();
        $error = 'Error updating roles: ' . $e->getMessage();
    }
}

$pageTitle = 'Edit User Roles';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="content-area">
        <h1>Edit User Roles</h1>
        
        <p><strong>User:</strong> <?= h($user['name'] . ' ' . $user['surname']) ?> (<?= h($user['username']) ?>)</p>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= h($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        
        <div class="auth-container">
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_roles">
                
                <div class="form-group">
                    <label>Select Roles:</label>
                    <?php foreach ($allRoles as $role): ?>
                        <div style="margin: 10px 0;">
                            <label>
                                <input type="checkbox" name="roles[]" value="<?= $role['role_id'] ?>" 
                                       <?= in_array($role['role_id'], $userRoleIds) ? 'checked' : '' ?>>
                                <strong><?= h($role['role_name']) ?></strong>
                                <?php if ($role['role_description']): ?>
                                    - <?= h($role['role_description']) ?>
                                <?php endif; ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Update Roles</button>
                    <a href="<?= baseUrl('/admin/users.php') ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
$hideAdverts = true;
include __DIR__ . '/../includes/footer.php'; 
?>

