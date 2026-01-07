<?php
/**
 * Email Functions
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Send email (basic PHP mail function)
 * For production, consider using PHPMailer or similar library
 */
function sendEmail($to, $subject, $message, $fromEmail = null, $fromName = null, $replyTo = null) {
    $fromEmail = $fromEmail ?: EMAIL_FROM_ADDRESS;
    $fromName = $fromName ?: EMAIL_FROM_NAME;
    
    // Clean email addresses to prevent header injection
    $fromEmail = trim($fromEmail);
    $fromName = trim($fromName);
    $to = trim($to);
    
    // Remove any newlines from headers to prevent header injection
    $fromName = str_replace(["\r", "\n"], '', $fromName);
    $fromEmail = str_replace(["\r", "\n"], '', $fromEmail);
    $subject = str_replace(["\r", "\n"], '', $subject);
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    // Use base64 encoding for the whole email body to avoid line-length issues while keeping data URIs intact
    $headers .= "Content-Transfer-Encoding: base64\r\n";
    $headers .= "From: $fromName <$fromEmail>\r\n";
    $headers .= "Sender: $fromEmail\r\n";
    if ($replyTo) {
        $replyTo = str_replace(["\r", "\n"], '', trim($replyTo));
        $headers .= "Reply-To: $replyTo\r\n";
    } else {
        $headers .= "Reply-To: $fromEmail\r\n";
    }
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    
    // Use mail() without @ suppression to allow error logging
    // The verification email works, so this should work too
    error_log("Attempting to send email to: $to, Subject: " . substr($subject, 0, 50));
    
    // Check if message is too large (some servers have limits)
    $messageSize = strlen($message);
    if ($messageSize > 10485760) { // 10MB limit
        error_log("Email message too large: $messageSize bytes (max 10MB)");
        return false;
    }
    
    // Log first 200 chars of message to check for formatting issues
    $messagePreview = substr($message, 0, 200);
    error_log("Email message preview (first 200 chars): " . str_replace(["\r", "\n"], ['[CR]', '[LF]'], $messagePreview));
    
    // Check for potential issues in message
    if (strpos($message, "\0") !== false) {
        error_log("WARNING: Email message contains null bytes, which may cause issues");
    }
    
    // RFC 5322 requires email lines to be max 998 characters
    // Encode the entire message as base64 and wrap at 76 characters (RFC 2045)
    // Remove leading whitespace/newlines to avoid blank preface lines
    $message = ltrim($message);
    $message = chunk_split(base64_encode($message), 76, "\r\n");
    
    // Use envelope sender to match domain and improve deliverability
    $additionalParams = '-f' . $fromEmail;
    $result = mail($to, $subject, $message, $headers, $additionalParams);
    
    // Log email attempts for debugging
    if ($result) {
        error_log("Email sent successfully to: $to");
    } else {
        $lastError = error_get_last();
        error_log("Email send failed to $to: " . ($lastError['message'] ?? 'Unknown error'));
        // Also log PHP error if available
        if (function_exists('error_get_last')) {
            $phpError = error_get_last();
            if ($phpError) {
                error_log("PHP error: " . print_r($phpError, true));
            }
        }
    }
    
    return $result;
}

/**
 * Send email verification email
 */
function sendVerificationEmail($userId, $email, $name, $token) {
    $verificationUrl = SITE_URL . baseUrl('/verify-email.php?token=' . urlencode($token));
    $expiryHours = EMAIL_VERIFICATION_EXPIRY / 3600;
    
    $subject = 'Verify Your Email Address - ' . SITE_NAME;
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #2c5f8d; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f5f5f5; }
            .button { display: inline-block; padding: 12px 24px; background-color: #2c5f8d; color: white; text-decoration: none; border-radius: 4px; margin: 20px 0; }
            .footer { padding: 20px; text-align: center; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>" . h(SITE_NAME) . "</h1>
            </div>
            <div class='content'>
                <h2>Hello " . h($name) . ",</h2>
                <p>Thank you for registering with " . h(SITE_NAME) . "!</p>
                <p>Please verify your email address by clicking the button below:</p>
                <p style='text-align: center;'>
                    <a href='$verificationUrl' class='button'>Verify Email Address</a>
                </p>
                <p>Or copy and paste this link into your browser:</p>
                <p style='word-break: break-all;'>$verificationUrl</p>
                <p><strong>This verification link will expire in $expiryHours hours.</strong></p>
                <p>If you did not create an account, please ignore this email.</p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " " . h(SITE_NAME) . ". All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($email, $subject, $message);
}

/**
 * Send password reset email
 */
function sendPasswordResetEmail($email, $username, $token) {
    $resetUrl = SITE_URL . baseUrl('/reset-password.php?token=' . urlencode($token));
    $expiryHours = PASSWORD_RESET_EXPIRY / 3600;
    
    $subject = 'Password Reset Request - ' . SITE_NAME;
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #2c5f8d; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f5f5f5; }
            .button { display: inline-block; padding: 12px 24px; background-color: #2c5f8d; color: white; text-decoration: none; border-radius: 4px; margin: 20px 0; }
            .footer { padding: 20px; text-align: center; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>" . h(SITE_NAME) . "</h1>
            </div>
            <div class='content'>
                <h2>Password Reset Request</h2>
                <p>Hello $username,</p>
                <p>We received a request to reset your password. Click the button below to reset it:</p>
                <p style='text-align: center;'>
                    <a href='$resetUrl' class='button'>Reset Password</a>
                </p>
                <p>Or copy and paste this link into your browser:</p>
                <p style='word-break: break-all;'>$resetUrl</p>
                <p><strong>This link will expire in $expiryHours hours.</strong></p>
                <p>If you did not request a password reset, please ignore this email.</p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " " . h(SITE_NAME) . ". All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($email, $subject, $message);
}

/**
 * Get all users with a specific role
 */
function getUsersByRole($roleName) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT DISTINCT u.*
        FROM " . TABLE_PREFIX . "users u
        INNER JOIN " . TABLE_PREFIX . "user_roles ur ON u.user_id = ur.user_id
        INNER JOIN " . TABLE_PREFIX . "roles r ON ur.role_id = r.role_id
        WHERE r.role_name = ?
        AND u.is_active = 1
        AND u.email IS NOT NULL
        AND u.email != ''
    ");
    $stmt->execute([$roleName]);
    return $stmt->fetchAll();
}

/**
 * Send pending business notification to all ADMIN users
 */
function notifyAdminsPendingBusinesses() {
    $db = getDB();
    
    // Get all pending businesses
    $stmt = $db->query("
        SELECT b.*, c.category_name, u.username, u.name, u.surname, u.email as user_email
        FROM " . TABLE_PREFIX . "businesses b
        JOIN " . TABLE_PREFIX . "business_categories c ON b.category_id = c.category_id
        LEFT JOIN " . TABLE_PREFIX . "users u ON b.user_id = u.user_id
        WHERE b.is_approved = 0
        ORDER BY b.created_at DESC
    ");
    $pendingBusinesses = $stmt->fetchAll();
    
    if (empty($pendingBusinesses)) {
        return true; // No pending businesses, nothing to notify
    }
    
    // Get all ADMIN users
    $adminUsers = getUsersByRole('ADMIN');
    
    if (empty($adminUsers)) {
        return false; // No admins to notify
    }
    
    $subject = 'Pending Business Approval Requests - ' . SITE_NAME;
    
    // Build business list HTML
    $businessListHtml = '';
    foreach ($pendingBusinesses as $index => $business) {
        $addressParts = [];
        if (!empty($business['street_number'])) $addressParts[] = $business['street_number'];
        if (!empty($business['street_name'])) $addressParts[] = $business['street_name'];
        if (!empty($business['suburb'])) $addressParts[] = $business['suburb'];
        if (!empty($business['town'])) $addressParts[] = $business['town'];
        $fullAddress = !empty($addressParts) ? implode(', ', $addressParts) : ($business['address'] ?: 'No address provided');
        
        $businessListHtml .= "
        <div style='background-color: white; padding: 15px; margin: 15px 0; border-left: 4px solid #2c5f8d; border-radius: 4px;'>
            <h3 style='margin-top: 0; color: #2c5f8d;'>" . ($index + 1) . ". " . h($business['business_name']) . "</h3>
            <p><strong>Category:</strong> " . h($business['category_name']) . "</p>
            <p><strong>Contact:</strong> " . h($business['contact_name'] ?: 'N/A') . "</p>
            <p><strong>Telephone:</strong> " . h($business['telephone']) . "</p>";
        
        if ($business['email']) {
            $businessListHtml .= "<p><strong>Email:</strong> " . h($business['email']) . "</p>";
        }
        
        if ($business['website']) {
            $businessListHtml .= "<p><strong>Website:</strong> <a href='" . h($business['website']) . "'>" . h($business['website']) . "</a></p>";
        }
        
        $businessListHtml .= "<p><strong>Address:</strong> " . h($fullAddress) . "</p>";
        
        if ($business['description']) {
            $businessListHtml .= "<p><strong>Description:</strong> " . nl2br(h($business['description'])) . "</p>";
        }
        
        $businessListHtml .= "
            <p><strong>Submitted by:</strong> " . h($business['username'] ?: 'Imported') . 
            ($business['name'] ? " (" . h($business['name'] . ' ' . $business['surname']) . ")" : '') . "</p>
            <p><strong>Submitted:</strong> " . formatDate($business['created_at']) . "</p>
        </div>";
    }
    
    $approvalUrl = SITE_URL . baseUrl('/admin/approve-businesses.php');
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 800px; margin: 0 auto; padding: 20px; }
            .header { background-color: #2c5f8d; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f5f5f5; }
            .button { display: inline-block; padding: 12px 24px; background-color: #2c5f8d; color: white; text-decoration: none; border-radius: 4px; margin: 20px 0; }
            .footer { padding: 20px; text-align: center; color: #666; font-size: 12px; }
            .count-badge { background-color: #ffc107; color: #000; padding: 5px 10px; border-radius: 4px; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Pending Business Approval Requests</h1>
            </div>
            <div class='content'>
                <p>Hello,</p>
                <p>There <span class='count-badge'>" . count($pendingBusinesses) . "</span> business" . (count($pendingBusinesses) > 1 ? 'es' : '') . " pending approval on " . h(SITE_NAME) . ".</p>
                <p style='text-align: center;'>
                    <a href='$approvalUrl' class='button'>Review Pending Businesses</a>
                </p>
                <h2>Pending Businesses:</h2>
                $businessListHtml
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
    foreach ($adminUsers as $admin) {
        // Use the exact same approach as sendVerificationEmail - just 3 parameters
        $result = sendEmail($admin['email'], $subject, $message);
        
        // Log for debugging
        error_log("Business notification email sent to {$admin['email']}: " . ($result ? 'SUCCESS' : 'FAILED'));
        
        if ($result) {
            $emailsSent++;
        } else {
            error_log("Failed to send business notification email to: {$admin['email']}");
        }
    }
    
    return $emailsSent > 0;
}

/**
 * Send business approval email to business owner
 */
function sendBusinessApprovalEmail($business) {
    $ownerEmail = $business['owner_email'] ?? null;
    if (!$ownerEmail) {
        return false; // No email to send to
    }
    
    $ownerName = trim(($business['owner_name'] ?? '') . ' ' . ($business['owner_surname'] ?? ''));
    if (empty($ownerName)) {
        $ownerName = $business['owner_username'] ?? 'User';
    }
    
    $businessUrl = SITE_URL . baseUrl('/businesses.php');
    
    $subject = 'Your Business Has Been Approved - ' . SITE_NAME;
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #4caf50; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f5f5f5; }
            .button { display: inline-block; padding: 12px 24px; background-color: #4caf50; color: white; text-decoration: none; border-radius: 4px; margin: 20px 0; }
            .footer { padding: 20px; text-align: center; color: #666; font-size: 12px; }
            .business-info { background-color: white; padding: 15px; border-left: 4px solid #4caf50; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Business Approved!</h1>
            </div>
            <div class='content'>
                <p>Hello " . h($ownerName) . ",</p>
                <p>Great news! Your business submission has been approved and is now live on " . h(SITE_NAME) . ".</p>
                <div class='business-info'>
                    <h3 style='margin-top: 0;'>" . h($business['business_name']) . "</h3>
                    <p>Your business is now visible to all visitors on the website.</p>
                </div>
                <p style='text-align: center;'>
                    <a href='$businessUrl' class='button'>View Business Directory</a>
                </p>
                <p>Thank you for your submission!</p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " " . h(SITE_NAME) . ". All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($ownerEmail, $subject, $message);
}

/**
 * Send business rejection email to business owner
 */
function sendBusinessRejectionEmail($business, $rejectionReason) {
    $ownerEmail = $business['owner_email'] ?? null;
    if (!$ownerEmail) {
        return false; // No email to send to
    }
    
    $ownerName = trim(($business['owner_name'] ?? '') . ' ' . ($business['owner_surname'] ?? ''));
    if (empty($ownerName)) {
        $ownerName = $business['owner_username'] ?? 'User';
    }
    
    $subject = 'Business Submission Update - ' . SITE_NAME;
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #d32f2f; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f5f5f5; }
            .button { display: inline-block; padding: 12px 24px; background-color: #2c5f8d; color: white; text-decoration: none; border-radius: 4px; margin: 20px 0; }
            .footer { padding: 20px; text-align: center; color: #666; font-size: 12px; }
            .rejection-info { background-color: white; padding: 15px; border-left: 4px solid #d32f2f; margin: 20px 0; }
            .reason-box { background-color: #ffebee; padding: 15px; border-radius: 4px; margin: 15px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Business Submission Update</h1>
            </div>
            <div class='content'>
                <p>Hello " . h($ownerName) . ",</p>
                <p>We regret to inform you that your business submission has been reviewed and unfortunately cannot be approved at this time.</p>
                <div class='rejection-info'>
                    <h3 style='margin-top: 0;'>" . h($business['business_name']) . "</h3>
                </div>
                <div class='reason-box'>
                    <h4 style='margin-top: 0;'>Reason for Rejection:</h4>
                    <p>" . nl2br(h($rejectionReason)) . "</p>
                </div>
                <p>If you have any questions or would like to resubmit your business with corrections, please feel free to contact us.</p>
                <p>Thank you for your interest in " . h(SITE_NAME) . ".</p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " " . h(SITE_NAME) . ". All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($ownerEmail, $subject, $message);
}

