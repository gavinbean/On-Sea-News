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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $username = trim($_POST['username'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $surname = trim($_POST['surname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $emailVerified = isset($_POST['email_verified']) ? 1 : 0;
    
    // Validation
    if (empty($username)) {
        $error = 'Username is required.';
    } elseif (empty($name)) {
        $error = 'Name is required.';
    } elseif (empty($email)) {
        $error = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } else {
        // Check if username is already taken by another user
        $stmt = $db->prepare("SELECT user_id FROM " . TABLE_PREFIX . "users WHERE username = ? AND user_id != ?");
        $stmt->execute([$username, $userId]);
        if ($stmt->fetch()) {
            $error = 'Username is already taken.';
        } else {
            // Update user
            $stmt = $db->prepare("
                UPDATE " . TABLE_PREFIX . "users 
                SET username = ?, name = ?, surname = ?, email = ?, is_active = ?, email_verified = ?
                WHERE user_id = ?
            ");
            $stmt->execute([$username, $name, $surname, $email, $isActive, $emailVerified, $userId]);
            $message = 'User updated successfully.';
            
            // Reload user
            $stmt = $db->prepare("SELECT * FROM " . TABLE_PREFIX . "users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
        }
    }
}

$pageTitle = 'Edit User';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="content-area">
        <h1>Edit User</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= h($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        
        <div class="auth-container">
            <form method="POST" action="">
                <input type="hidden" name="action" value="update">
                
                <div class="form-group">
                    <label for="username">Username: <span class="required">*</span></label>
                    <input type="text" id="username" name="username" value="<?= h($user['username']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="name">Name: <span class="required">*</span></label>
                    <input type="text" id="name" name="name" value="<?= h($user['name']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="surname">Surname:</label>
                    <input type="text" id="surname" name="surname" value="<?= h($user['surname']) ?>">
                </div>
                
                <div class="form-group">
                    <label for="email">Email: <span class="required">*</span></label>
                    <input type="email" id="email" name="email" value="<?= h($user['email']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" value="1" <?= $user['is_active'] ? 'checked' : '' ?>>
                        Active
                    </label>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="email_verified" value="1" <?= $user['email_verified'] ? 'checked' : '' ?>>
                        Email Verified
                    </label>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Update User</button>
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


