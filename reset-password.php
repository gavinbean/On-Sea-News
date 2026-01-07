<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

$error = '';
$success = '';
$token = $_GET['token'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    $result = resetPassword($token, $password, $password_confirm);
    if ($result['success']) {
        $success = $result['message'];
    } else {
        $error = $result['message'];
    }
}

$pageTitle = 'Reset Password';
include 'includes/header.php';
?>

<div class="container">
    <div class="auth-container">
        <h1>Reset Password</h1>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= h($success) ?></div>
            <p><a href="<?= baseUrl('/login.php') ?>">Login with your new password</a></p>
        <?php else: ?>
            <?php if (empty($token)): ?>
                <div class="alert alert-error">No reset token provided.</div>
                <p><a href="<?= baseUrl('/forgot-password.php') ?>">Request a new reset link</a></p>
            <?php else: ?>
                <form method="POST" action="">
                    <input type="hidden" name="token" value="<?= h($token) ?>">
                    
                    <div class="form-group">
                        <label for="password">New Password:</label>
                        <input type="password" id="password" name="password" required minlength="8">
                        <small>Minimum 8 characters</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="password_confirm">Confirm New Password:</label>
                        <input type="password" id="password_confirm" name="password_confirm" required minlength="8">
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Reset Password</button>
                    </div>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

