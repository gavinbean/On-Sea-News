<?php
require_once 'includes/functions.php';
require_once 'includes/email.php';
require_once 'includes/captcha.php';

$error = '';
$success = '';
$showCaptcha = !isLoggedIn(); // Show CAPTCHA if user is not logged in

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $captcha = trim($_POST['captcha'] ?? '');
    
    // Validation
    if (empty($name)) {
        $error = 'Please enter your name.';
    } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (empty($subject)) {
        $error = 'Please enter a subject.';
    } elseif (empty($message)) {
        $error = 'Please enter your message.';
    } elseif ($showCaptcha && empty($captcha)) {
        $error = 'Please enter the CAPTCHA code.';
    } elseif ($showCaptcha && !verifyCaptcha($captcha)) {
        $error = 'Invalid CAPTCHA code. Please try again.';
    } else {
        // Get all admin users - use the same function as business notifications
        $adminUsers = getUsersByRole('ADMIN');
        
        // Debug logging
        error_log("Contact form: Found " . count($adminUsers) . " admin users");
        if (!empty($adminUsers)) {
            foreach ($adminUsers as $admin) {
                error_log("Contact form: Admin user - " . ($admin['email'] ?? 'NO EMAIL'));
            }
        }
        
        if (empty($adminUsers)) {
            $error = 'No administrators found to receive your message.';
            error_log("Contact form: No admin users found!");
        } else {
            // Prepare email content
            $userName = isLoggedIn() ? getCurrentUser()['name'] . ' ' . getCurrentUser()['surname'] : $name;
            $userEmail = isLoggedIn() ? getCurrentUser()['email'] : $email;
            
            $emailSubject = 'Contact Form: ' . $subject;
            
            $emailMessage = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background-color: #2c5f8d; color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; background-color: #f5f5f5; }
                    .message-box { background-color: white; padding: 15px; border-left: 4px solid #2c5f8d; margin: 20px 0; }
                    .info { background-color: #e8f4f8; padding: 10px; border-radius: 4px; margin: 10px 0; }
                    .footer { padding: 20px; text-align: center; color: #666; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Contact Form Message</h1>
                    </div>
                    <div class='content'>
                        <div class='info'>
                            <p><strong>From:</strong> " . h($userName) . "</p>
                            <p><strong>Email:</strong> " . h($userEmail) . "</p>
                            <p><strong>Subject:</strong> " . h($subject) . "</p>
                        </div>
                        <div class='message-box'>
                            <h3>Message:</h3>
                            <p>" . nl2br(h($message)) . "</p>
                        </div>
                        <p style='margin-top: 20px; font-size: 12px; color: #666;'>
                            This message was sent through the contact form on " . h(SITE_NAME) . ".
                            " . (isLoggedIn() ? 'The user is registered on the site.' : 'The user is not registered.') . "
                        </p>
                    </div>
                    <div class='footer'>
                        <p>&copy; " . date('Y') . " " . h(SITE_NAME) . ". All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            // Send email to all admin users - use exact same mechanism as verification email
            $emailsSent = 0;
            $emailsFailed = 0;
            
            foreach ($adminUsers as $admin) {
                $adminEmail = $admin['email'];
                
                // Use the exact same approach as sendVerificationEmail - just 3 parameters
                $result = sendEmail($adminEmail, $emailSubject, $emailMessage);
                
                // Log for debugging
                error_log("Contact form email sent to $adminEmail: " . ($result ? 'SUCCESS' : 'FAILED'));
                
                if ($result) {
                    $emailsSent++;
                } else {
                    $emailsFailed++;
                    error_log("Failed to send contact form email to: $adminEmail");
                }
            }
            
            if ($emailsSent > 0) {
                // Save to database
                $db = getDB();
                $userId = isLoggedIn() ? getCurrentUser()['user_id'] : null;
                
                try {
                    $stmt = $db->prepare("
                        INSERT INTO " . TABLE_PREFIX . "contact_queries 
                        (user_id, name, email, subject, message, status) 
                        VALUES (?, ?, ?, ?, ?, 'new')
                    ");
                    $stmt->execute([$userId, $userName, $userEmail, $subject, $message]);
                    
                    $success = 'Thank you for your message! We have received it and will get back to you soon.';
                    // Clear form
                    $name = '';
                    $email = '';
                    $subject = '';
                    $message = '';
                    // Regenerate CAPTCHA if needed
                    if ($showCaptcha) {
                        $captchaCode = generateCaptcha();
                    }
                } catch (Exception $e) {
                    error_log("Failed to save contact query to database: " . $e->getMessage());
                    // Still show success since email was sent
                    $success = 'Thank you for your message! We have received it and will get back to you soon.';
                    // Clear form
                    $name = '';
                    $email = '';
                    $subject = '';
                    $message = '';
                    // Regenerate CAPTCHA if needed
                    if ($showCaptcha) {
                        $captchaCode = generateCaptcha();
                    }
                }
            } else {
                $error = 'Sorry, there was an error sending your message. Please try again later.';
                error_log("Contact form: Failed to send to all " . count($adminUsers) . " admin users");
            }
        }
    }
}

// Generate CAPTCHA if needed
if ($showCaptcha && empty($captchaCode)) {
    $captchaCode = generateCaptcha();
}

$pageTitle = 'Contact Us';
include 'includes/header.php';
?>

<div class="container">
    <div class="content-area">
        <h1>Contact Us</h1>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= h($success) ?></div>
        <?php endif; ?>
        
        <div class="auth-container">
            <p style="margin-bottom: 1.5rem;">Have a question or need assistance? Please fill out the form below and we'll get back to you as soon as possible.</p>
            
            <form method="POST" action="" id="contactForm">
                <div class="form-group">
                    <label for="name">Your Name: <span class="required">*</span></label>
                    <input type="text" id="name" name="name" value="<?= h($name ?? (isLoggedIn() ? (getCurrentUser()['name'] . ' ' . getCurrentUser()['surname']) : '')) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Your Email: <span class="required">*</span></label>
                    <input type="email" id="email" name="email" value="<?= h($email ?? (isLoggedIn() ? getCurrentUser()['email'] : '')) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="subject">Subject: <span class="required">*</span></label>
                    <input type="text" id="subject" name="subject" value="<?= h($subject ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="message">Message: <span class="required">*</span></label>
                    <textarea id="message" name="message" rows="6" required><?= h($message ?? '') ?></textarea>
                </div>
                
                <?php if ($showCaptcha): ?>
                    <div class="form-group">
                        <label for="captcha">CAPTCHA Code: <span class="required">*</span></label>
                        <div class="captcha-container">
                            <img src="<?= baseUrl('/captcha-image.php') ?>" alt="CAPTCHA" id="captchaImage">
                            <button type="button" onclick="refreshCaptcha()" class="btn-captcha-refresh" title="Refresh CAPTCHA">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>
                        <input type="text" id="captcha" name="captcha" required maxlength="4" pattern="[0-9]{4}" placeholder="Enter the 4-digit code">
                        <small>Please enter the 4-digit code shown in the image above.</small>
                    </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Send Message</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function refreshCaptcha() {
    document.getElementById('captchaImage').src = '<?= baseUrl('/captcha-image.php') ?>?' + new Date().getTime();
    document.getElementById('captcha').value = '';
}
</script>

<?php 
$hideAdverts = false;
include 'includes/footer.php'; 
?>

