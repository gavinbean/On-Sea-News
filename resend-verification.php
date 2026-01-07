<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/email.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    
    if (empty($email)) {
        $error = 'Email is required.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT user_id, username, name, surname, email_verified FROM " . TABLE_PREFIX . "users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            // Don't reveal if email exists for security
            $success = 'If the email exists and is not verified, a new verification email has been sent.';
        } elseif ($user['email_verified']) {
            $error = 'This email is already verified. You can log in.';
        } else {
            // Generate new verification token
            $verificationToken = generateToken();
            $verificationExpires = date('Y-m-d H:i:s', time() + EMAIL_VERIFICATION_EXPIRY);
            
            $stmt = $db->prepare("
                UPDATE " . TABLE_PREFIX . "users 
                SET email_verification_token = ?, email_verification_expires = ?
                WHERE user_id = ?
            ");
            $stmt->execute([$verificationToken, $verificationExpires, $user['user_id']]);
            
            // Send verification email
            $fullName = $user['name'] . ' ' . $user['surname'];
            sendVerificationEmail($user['user_id'], $email, $fullName, $verificationToken);
            
            $success = 'A new verification email has been sent. Please check your inbox.';
        }
    }
}

$pageTitle = 'Resend Verification Email';
include 'includes/header.php';
?>

<div class="container">
    <div class="auth-container">
        <h1>Resend Verification Email</h1>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= h($success) ?></div>
            <p><a href="<?= baseUrl('/login.php') ?>" class="btn btn-primary">Go to Login</a></p>
        <?php else: ?>
            <p>Enter your email address to receive a new verification email.</p>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required autofocus>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Resend Verification Email</button>
                </div>
            </form>
            
            <div class="auth-links">
                <a href="<?= baseUrl('/login.php') ?>">Back to Login</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

