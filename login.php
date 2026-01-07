<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'login') {
            $rememberMe = isset($_POST['remember_me']) && $_POST['remember_me'] == '1';
            $result = loginUser($_POST['username'] ?? '', $_POST['password'] ?? '', $rememberMe);
            if ($result['success']) {
                redirect('/index.php');
            } else {
                $error = $result['message'];
            }
        }
    }
}

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('/index.php');
}

$pageTitle = 'Login';
include 'includes/header.php';
?>

<div class="container">
    <div class="auth-container">
        <h1>Login</h1>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= h($success) ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="login">
            
            <div class="form-group">
                <label for="username">Username or Email:</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" id="remember_me" name="remember_me" value="1">
                    Remember Me
                </label>
                <small>Stay logged in on this device</small>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Login</button>
            </div>
        </form>
        
        <div class="auth-links">
            <a href="<?= baseUrl('/register.php') ?>">Register new account</a> |
            <a href="<?= baseUrl('/resend-verification.php') ?>">Resend Verification Email</a> |
            <a href="<?= baseUrl('/forgot-password.php') ?>">Forgot Password?</a> |
            <a href="<?= baseUrl('/forgot-username.php') ?>">Forgot Username?</a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

