<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'config/database.php';

$token = $_GET['token'] ?? '';
$message = '';
$error = '';
$success = false;

if (empty($token)) {
    $error = 'No verification token provided.';
} else {
    $db = getDB();
    
    // Find user by token
    $stmt = $db->prepare("
        SELECT user_id, email, email_verification_expires 
        FROM " . TABLE_PREFIX . "users 
        WHERE email_verification_token = ? 
        AND email_verified = 0
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $error = 'Invalid or expired verification token.';
    } elseif (strtotime($user['email_verification_expires']) < time()) {
        $error = 'Verification token has expired. Please register again or contact support.';
    } else {
        // Verify the email
        $stmt = $db->prepare("
            UPDATE " . TABLE_PREFIX . "users 
            SET email_verified = 1, 
                email_verification_token = NULL, 
                email_verification_expires = NULL
            WHERE user_id = ?
        ");
        $stmt->execute([$user['user_id']]);
        
        $success = true;
        $message = 'Email verified successfully! You can now log in.';
    }
}

$pageTitle = 'Verify Email';
include 'includes/header.php';
?>

<div class="container">
    <div class="auth-container">
        <h1>Email Verification</h1>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
            <p><a href="<?= baseUrl('/login.php') ?>" class="btn btn-primary">Go to Login</a></p>
        <?php elseif ($success): ?>
            <div class="alert alert-success"><?= h($message) ?></div>
            <p><a href="<?= baseUrl('/login.php') ?>" class="btn btn-primary">Go to Login</a></p>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>



