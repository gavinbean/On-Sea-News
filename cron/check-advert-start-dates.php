<?php
/**
 * Cron Job: Check for adverts that have reached their start date
 * and send notifications to followers
 * 
 * This script should be run daily via cron:
 * 0 0 * * * /usr/bin/php /path/to/cron/check-advert-start-dates.php
 * 
 * Or run it manually for testing:
 * php cron/check-advert-start-dates.php
 */

// Set the base directory
$baseDir = dirname(__DIR__);

// Include required files
require_once $baseDir . '/includes/functions.php';
require_once $baseDir . '/includes/email_business_adverts.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Log script start
error_log("[" . date('Y-m-d H:i:s') . "] Starting advert start date check cron job");

try {
    $db = getDB();
    $today = date('Y-m-d');
    
    // Find adverts that:
    // 1. Are approved
    // 2. Are active
    // 3. Either:
    //    - Have a start_date that is today (just became active), OR
    //    - Have no start_date and were approved today or earlier (always active adverts)
    // 4. Either haven't been sent to followers yet, OR were sent before approval
    $stmt = $db->prepare("
        SELECT a.*, b.business_name, b.description, b.telephone, b.email, b.website, b.address
        FROM " . TABLE_PREFIX . "business_adverts a
        JOIN " . TABLE_PREFIX . "businesses b ON a.business_id = b.business_id
        WHERE a.approval_status = 'approved'
        AND a.is_active = 1
        AND (
            (a.start_date = ? AND a.start_date IS NOT NULL)
            OR
            (a.start_date IS NULL AND DATE(a.approved_at) <= ?)
        )
        AND (
            a.notified_followers_at IS NULL
            OR a.notified_followers_at < a.approved_at
        )
        ORDER BY a.advert_id ASC
    ");
    $stmt->execute([$today, $today]);
    $adverts = $stmt->fetchAll();
    
    $totalProcessed = 0;
    $totalEmailsSent = 0;
    
    foreach ($adverts as $advert) {
        // Check if advert is in date (start_date is today, end_date is either null or >= today)
        $isInDate = true;
        if (!empty($advert['end_date']) && $advert['end_date'] < $today) {
            $isInDate = false;
        }
        
        if (!$isInDate) {
            error_log("[" . date('Y-m-d H:i:s') . "] Advert ID {$advert['advert_id']} is not in date (end_date: {$advert['end_date']}), skipping");
            continue;
        }
        
        // Prepare business data
        $business = [
            'business_id' => $advert['business_id'],
            'business_name' => $advert['business_name'],
            'description' => $advert['description'],
            'telephone' => $advert['telephone'],
            'email' => $advert['email'],
            'website' => $advert['website'],
            'address' => $advert['address']
        ];
        
        // Check if we should notify (using the same logic as manual updates)
        if (shouldNotifyAdvertUpdate($advert)) {
            error_log("[" . date('Y-m-d H:i:s') . "] Sending notifications for advert ID {$advert['advert_id']} (Business: {$advert['business_name']})");
            
            $emailsSent = sendAdvertUpdateEmail($advert, $business);
            
            if ($emailsSent > 0) {
                $totalEmailsSent += $emailsSent;
                error_log("[" . date('Y-m-d H:i:s') . "] Sent {$emailsSent} notification email(s) for advert ID {$advert['advert_id']}");
            } else {
                error_log("[" . date('Y-m-d H:i:s') . "] No followers to notify for advert ID {$advert['advert_id']}");
            }
        } else {
            error_log("[" . date('Y-m-d H:i:s') . "] Advert ID {$advert['advert_id']} should not notify (may not be in date or already sent)");
        }
        
        $totalProcessed++;
    }
    
    // Log summary
    error_log("[" . date('Y-m-d H:i:s') . "] Cron job completed. Processed {$totalProcessed} advert(s), sent {$totalEmailsSent} email(s)");
    
    // If running from command line, output summary
    if (php_sapi_name() === 'cli') {
        echo "[" . date('Y-m-d H:i:s') . "] Advert start date check completed.\n";
        echo "Processed: {$totalProcessed} advert(s)\n";
        echo "Emails sent: {$totalEmailsSent}\n";
    }
    
} catch (Exception $e) {
    $errorMsg = "Error in advert start date check cron job: " . $e->getMessage();
    error_log("[" . date('Y-m-d H:i:s') . "] " . $errorMsg);
    error_log("[" . date('Y-m-d H:i:s') . "] Stack trace: " . $e->getTraceAsString());
    
    if (php_sapi_name() === 'cli') {
        echo "ERROR: " . $errorMsg . "\n";
        exit(1);
    }
    
    // Don't exit in web context, just log
}
