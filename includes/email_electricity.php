<?php
/**
 * Electricity Issue Email Functions
 * These functions handle email notifications for electricity issues
 */

require_once __DIR__ . '/email.php';

/**
 * Send new electricity issue notification to all electricity admins
 */
function sendNewElectricityIssueEmail($issueId, $name, $address, $description) {
    $electricityAdmins = getUsersByRole('ELECTRICITY_ADMIN');
    $adminUsers = getUsersByRole('ADMIN');
    
    // Combine both admin types
    $allAdmins = array_merge($electricityAdmins, $adminUsers);
    
    if (empty($allAdmins)) {
        return false; // No admins to notify
    }
    
    $issueUrl = SITE_URL . baseUrl('/admin/electricity-issues.php?issue_id=' . $issueId);
    $subject = 'New Electricity Issue Reported - ' . SITE_NAME;
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 800px; margin: 0 auto; padding: 20px; }
            .header { background-color: #e74c3c; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f5f5f5; }
            .button { display: inline-block; padding: 12px 24px; background-color: #e74c3c; color: white; text-decoration: none; border-radius: 4px; margin-top: 20px; }
            .issue-details { background-color: white; padding: 15px; margin: 15px 0; border-left: 4px solid #e74c3c; border-radius: 4px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>New Electricity Issue Reported</h1>
            </div>
            <div class='content'>
                <p>A new electricity issue has been reported and requires your attention.</p>
                
                <div class='issue-details'>
                    <h3 style='margin-top: 0; color: #e74c3c;'>Issue Details</h3>
                    <p><strong>Reporter:</strong> " . h($name) . "</p>
                    <p><strong>Address:</strong> " . h($address) . "</p>
                    <p><strong>Description:</strong></p>
                    <p>" . nl2br(h($description)) . "</p>
                </div>
                
                <p>
                    <a href='$issueUrl' class='button'>View Issue</a>
                </p>
                
                <p style='margin-top: 30px; font-size: 12px; color: #666;'>
                    This is an automated notification from " . SITE_NAME . ".
                </p>
            </div>
        </div>
    </body>
    </html>";
    
    $emailsSent = 0;
    foreach ($allAdmins as $admin) {
        $result = sendEmail($admin['email'], $subject, $message);
        if ($result) {
            $emailsSent++;
        }
    }
    
    return $emailsSent > 0;
}

/**
 * Send electricity issue update notification
 */
function sendElectricityIssueUpdateEmail($issueId, $status, $comment) {
    $db = getDB();
    
    // Get issue details
    $stmt = $db->prepare("
        SELECT e.*, u.name as reporter_name, u.surname as reporter_surname, u.email as reporter_email
        FROM " . TABLE_PREFIX . "electricity_issues e
        JOIN " . TABLE_PREFIX . "users u ON e.user_id = u.user_id
        WHERE e.issue_id = ?
    ");
    $stmt->execute([$issueId]);
    $issue = $stmt->fetch();
    
    if (!$issue) {
        return false;
    }
    
    $issueUrl = SITE_URL . baseUrl('/electricity-availability.php');
    $adminIssueUrl = SITE_URL . baseUrl('/admin/electricity-issues.php?issue_id=' . $issueId);
    
    // Email to reporter
    if (!empty($issue['reporter_email'])) {
        $subject = 'Electricity Issue Update - ' . SITE_NAME;
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
                .button { display: inline-block; padding: 12px 24px; background-color: #2c5f8d; color: white; text-decoration: none; border-radius: 4px; margin-top: 20px; }
                .update-details { background-color: white; padding: 15px; margin: 15px 0; border-left: 4px solid #2c5f8d; border-radius: 4px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Electricity Issue Update</h1>
                </div>
                <div class='content'>
                    <p>Your electricity issue has been updated.</p>
                    
                    <div class='update-details'>
                        <h3 style='margin-top: 0; color: #2c5f8d;'>Update Details</h3>
                        <p><strong>Status:</strong> " . h($status) . "</p>";
        
        if (!empty($comment)) {
            $message .= "<p><strong>Progress Note:</strong></p><p>" . nl2br(h($comment)) . "</p>";
        }
        
        $message .= "
                        <p><strong>Original Issue:</strong></p>
                        <p>" . nl2br(h($issue['description'])) . "</p>
                    </div>
                    
                    <p>
                        <a href='$issueUrl' class='button'>View Issue</a>
                    </p>
                    
                    <p style='margin-top: 30px; font-size: 12px; color: #666;'>
                        This is an automated notification from " . SITE_NAME . ".
                    </p>
                </div>
            </div>
        </body>
        </html>";
        
        sendEmail($issue['reporter_email'], $subject, $message);
    }
    
    // Email to all electricity admins
    $electricityAdmins = getUsersByRole('ELECTRICITY_ADMIN');
    $adminUsers = getUsersByRole('ADMIN');
    $allAdmins = array_merge($electricityAdmins, $adminUsers);
    
    if (!empty($allAdmins)) {
        $subject = 'Electricity Issue Updated - ' . SITE_NAME;
        $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 800px; margin: 0 auto; padding: 20px; }
                .header { background-color: #f39c12; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background-color: #f5f5f5; }
                .button { display: inline-block; padding: 12px 24px; background-color: #f39c12; color: white; text-decoration: none; border-radius: 4px; margin-top: 20px; }
                .update-details { background-color: white; padding: 15px; margin: 15px 0; border-left: 4px solid #f39c12; border-radius: 4px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Electricity Issue Updated</h1>
                </div>
                <div class='content'>
                    <p>An electricity issue has been updated.</p>
                    
                    <div class='update-details'>
                        <h3 style='margin-top: 0; color: #f39c12;'>Update Details</h3>
                        <p><strong>Reporter:</strong> " . h($issue['reporter_name'] . ' ' . $issue['reporter_surname']) . "</p>
                        <p><strong>Address:</strong> " . h($issue['address']) . "</p>
                        <p><strong>Status:</strong> " . h($status) . "</p>";
        
        if (!empty($comment)) {
            $message .= "<p><strong>Progress Note:</strong></p><p>" . nl2br(h($comment)) . "</p>";
        }
        
        $message .= "
                    </div>
                    
                    <p>
                        <a href='$adminIssueUrl' class='button'>View Issue</a>
                    </p>
                    
                    <p style='margin-top: 30px; font-size: 12px; color: #666;'>
                        This is an automated notification from " . SITE_NAME . ".
                    </p>
                </div>
            </div>
        </body>
        </html>";
        
        foreach ($allAdmins as $admin) {
            sendEmail($admin['email'], $subject, $message);
        }
    }
    
    return true;
}
