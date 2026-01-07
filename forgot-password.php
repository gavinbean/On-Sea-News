<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    if (empty($email)) {
        $error = 'Email is required.';
    } else {
        $result = requestPasswordReset($email);
        if ($result['success']) {
            $success = $result['message'];
        }
    }
}

$pageTitle = 'Forgot Password';
include 'includes/header.php';
?>

<div class="container">
    <div class="auth-container">
        <h1>Forgot Password</h1>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= h($success) ?></div>
            <p><a href="<?= baseUrl('/login.php') ?>">Return to login</a></p>
        <?php else: ?>
            <p>Enter your email address and we'll send you a password reset link.</p>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required autofocus>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Send Reset Link</button>
                </div>
            </form>
            
            <div class="auth-links">
                <a href="<?= baseUrl('/login.php') ?>">Back to Login</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

