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
        $username = getUserByEmail($email);
        if ($username) {
            $success = "Your username is: $username";
        } else {
            // Don't reveal if email exists
            $success = "If the email exists, your username has been sent.";
        }
    }
}

$pageTitle = 'Forgot Username';
include 'includes/header.php';
?>

<div class="container">
    <div class="auth-container">
        <h1>Forgot Username</h1>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= h($success) ?></div>
            <p><a href="<?= baseUrl('/login.php') ?>">Return to login</a></p>
        <?php else: ?>
            <p>Enter your email address and we'll send you your username.</p>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required autofocus>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Send Username</button>
                </div>
            </form>
            
            <div class="auth-links">
                <a href="<?= baseUrl('/login.php') ?>">Back to Login</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

