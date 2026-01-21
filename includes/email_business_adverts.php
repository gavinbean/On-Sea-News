<?php
/**
 * Business Advert Email Functions
 * Handles email notifications for business advert updates
 */

require_once __DIR__ . '/email.php';

/**
 * Send advert update notification to all users following a business
 */
function sendAdvertUpdateEmail($advert, $business) {
    $db = getDB();
    
    // Get all users following this business
    $stmt = $db->prepare("
        SELECT u.user_id, u.email, u.name, u.surname
        FROM " . TABLE_PREFIX . "business_follows bf
        JOIN " . TABLE_PREFIX . "users u ON bf.user_id = u.user_id
        WHERE bf.business_id = ?
        AND u.is_active = 1
        AND u.email_verified = 1
    ");
    $stmt->execute([$business['business_id']]);
    $followers = $stmt->fetchAll();
    
    if (empty($followers)) {
        return 0; // No followers to notify
    }
    
    $emailsSent = 0;
    $today = date('Y-m-d');
    
    // Build advert details
    $advertType = '';
    if ($advert['advert_type'] === 'events' && !empty($advert['event_title'])) {
        $advertType = 'Event: ' . $advert['event_title'];
        if ($advert['event_date']) {
            $eventDate = new DateTime($advert['event_date']);
            $advertType .= ' - ' . $eventDate->format('l, F j, Y');
        }
    } elseif ($advert['advert_type'] === 'timed') {
        $advertType = 'Timed Advertisement';
    } else {
        $advertType = 'Advertisement';
    }
    
    $dateInfo = '';
    if ($advert['start_date'] && $advert['end_date']) {
        $startDate = new DateTime($advert['start_date']);
        $endDate = new DateTime($advert['end_date']);
        $dateInfo = 'Active from ' . $startDate->format('F j, Y') . ' to ' . $endDate->format('F j, Y');
    } elseif ($advert['start_date']) {
        $startDate = new DateTime($advert['start_date']);
        $dateInfo = 'Active from ' . $startDate->format('F j, Y');
    } elseif ($advert['end_date']) {
        $endDate = new DateTime($advert['end_date']);
        $dateInfo = 'Active until ' . $endDate->format('F j, Y');
    } else {
        $dateInfo = 'Always active';
    }
    
    $bannerUrl = '';
    if (!empty($advert['banner_image'])) {
        $bannerPath = str_replace('uploads/adverts/', 'uploads/graphics/', $advert['banner_image']);
        $bannerUrl = SITE_URL . baseUrl('/' . $bannerPath);
    }
    
    $displayUrl = '';
    if (!empty($advert['display_image'])) {
        $displayPath = str_replace('uploads/adverts/', 'uploads/graphics/', $advert['display_image']);
        $displayUrl = SITE_URL . baseUrl('/' . $displayPath);
    }
    
    $businessUrl = SITE_URL . baseUrl('/business-view.php?id=' . $business['business_id']);
    
    foreach ($followers as $follower) {
        // Generate unfollow token (using user_id + business_id for simplicity)
        $unfollowToken = md5($follower['user_id'] . $business['business_id']);
        $unfollowUrl = SITE_URL . baseUrl('/unfollow-business.php?token=' . urlencode($unfollowToken) . '&business_id=' . $business['business_id']);
        
        $subject = 'New Advert Update: ' . $business['business_name'] . ' - ' . SITE_NAME;
        
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
                .advert-image { max-width: 100%; height: auto; border-radius: 4px; margin: 15px 0; }
                .button { display: inline-block; padding: 12px 24px; background-color: #2c5f8d; color: #ffffff !important; text-decoration: none; border-radius: 4px; margin: 10px 5px; }
                .unfollow-link { display: block; margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; font-size: 0.9rem; }
                .unfollow-link a { color: #666; text-decoration: underline; }
                .footer { padding: 20px; text-align: center; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1 style='margin: 0;'>New Advert Update</h1>
                </div>
                <div class='content'>
                    <p>Hello " . h($follower['name']) . ",</p>
                    <p><strong>" . h($business['business_name']) . "</strong> has updated their advertisement.</p>
                    
                    <h2 style='color: #2c5f8d; margin-top: 20px;'>" . h($advertType) . "</h2>
                    <p style='color: #666;'>" . h($dateInfo) . "</p>";
        
        if ($displayUrl) {
            $message .= "<img src='" . h($displayUrl) . "' alt='Advertisement' class='advert-image'>";
        }
        
        if (!empty($business['description'])) {
            $message .= "<p>" . nl2br(h($business['description'])) . "</p>";
        }
        
        $message .= "
                    <div style='margin: 20px 0; padding: 15px; background: white; border-radius: 4px;'>
                        <h3 style='margin-top: 0;'>Contact Information</h3>";
        
        if (!empty($business['telephone'])) {
            $message .= "<p><strong>Telephone:</strong> " . h($business['telephone']) . "</p>";
        }
        if (!empty($business['email'])) {
            $message .= "<p><strong>Email:</strong> <a href='mailto:" . h($business['email']) . "'>" . h($business['email']) . "</a></p>";
        }
        if (!empty($business['website'])) {
            $message .= "<p><strong>Website:</strong> <a href='" . h($business['website']) . "' target='_blank'>" . h($business['website']) . "</a></p>";
        }
        if (!empty($business['address'])) {
            $message .= "<p><strong>Address:</strong> " . h($business['address']) . "</p>";
        }
        
        $message .= "
                    </div>
                    
                    <div style='text-align: center; margin: 20px 0;'>
                        <a href='" . h($businessUrl) . "' class='button'>View Business</a>
                    </div>
                    
                    <div class='unfollow-link'>
                        <p>You are receiving this email because you are following this business.</p>
                        <p><a href='" . h($unfollowUrl) . "'>Unfollow this business</a> to stop receiving these notifications.</p>
                    </div>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " " . SITE_NAME . ". All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>";
        
        $result = sendEmail($follower['email'], $subject, $message);
        if ($result) {
            $emailsSent++;
        } else {
            error_log("Failed to send advert update email to: {$follower['email']}");
        }
    }
    
    // Update database to mark that followers have been notified
    if ($emailsSent > 0) {
        $db = getDB();
        $stmt = $db->prepare("
            UPDATE " . TABLE_PREFIX . "business_adverts 
            SET notified_followers_at = NOW(), 
                notified_followers_version = notified_followers_version + 1
            WHERE advert_id = ?
        ");
        $stmt->execute([$advert['advert_id']]);
    }
    
    return $emailsSent;
}

/**
 * Check if an advert should trigger notifications
 * Returns true if:
 * - Advert is approved
 * - Advert is active and in date
 * - Hasn't been sent before OR there are actual updates
 */
function shouldNotifyAdvertUpdate($advert, $oldAdvert = null) {
    // Only send if advert is approved
    if (empty($advert['approval_status']) || $advert['approval_status'] !== 'approved') {
        return false;
    }
    
    // Only send if advert is active
    if (empty($advert['is_active']) || $advert['is_active'] != 1) {
        return false;
    }
    
    $today = date('Y-m-d');
    
    // Check if advert is in date
    $isInDate = false;
    if (empty($advert['start_date']) && empty($advert['end_date'])) {
        // No dates = always active
        $isInDate = true;
    } elseif (empty($advert['start_date']) && !empty($advert['end_date'])) {
        // Only end date - check if today <= end_date
        $isInDate = ($advert['end_date'] >= $today);
    } elseif (!empty($advert['start_date']) && empty($advert['end_date'])) {
        // Only start date - check if today >= start_date
        $isInDate = ($advert['start_date'] <= $today);
    } else {
        // Both dates - check if today is within range
        $isInDate = ($advert['start_date'] <= $today && $advert['end_date'] >= $today);
    }
    
    if (!$isInDate) {
        return false;
    }
    
    // If never sent before, send it
    if (empty($advert['notified_followers_at'])) {
        return true;
    }
    
    // If there's an old advert, check for actual updates
    if ($oldAdvert) {
        // Check if images changed
        $bannerChanged = ($advert['banner_image'] !== ($oldAdvert['banner_image'] ?? ''));
        $displayChanged = ($advert['display_image'] !== ($oldAdvert['display_image'] ?? ''));
        
        // Check if dates changed
        $startDateChanged = ($advert['start_date'] !== ($oldAdvert['start_date'] ?? null));
        $endDateChanged = ($advert['end_date'] !== ($oldAdvert['end_date'] ?? null));
        $eventDateChanged = ($advert['event_date'] !== ($oldAdvert['event_date'] ?? null));
        
        // Check if event title changed
        $eventTitleChanged = ($advert['event_title'] !== ($oldAdvert['event_title'] ?? null));
        
        // Check if approval status changed from pending/rejected to approved
        $justApproved = (($oldAdvert['approval_status'] ?? 'pending') !== 'approved' && $advert['approval_status'] === 'approved');
        
        // If any meaningful change, send notification
        if ($bannerChanged || $displayChanged || $startDateChanged || $endDateChanged || $eventDateChanged || $eventTitleChanged || $justApproved) {
            return true;
        }
    }
    
    // No updates, don't send
    return false;
}
