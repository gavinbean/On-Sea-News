<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('ADMIN');

$db = getDB();
$message = '';
$error = '';

// Include the cron functions to use the same map generation and email functions
// The cron file checks if it's being executed directly, so including it here will only load the functions
try {
    require_once __DIR__ . '/../cron/daily-water-report.php';
} catch (Throwable $e) {
    error_log("Failed to include cron/daily-water-report.php: " . $e->getMessage());
    die("Error loading required files. Please check error logs.");
}

// Remove duplicate functions - use the ones from cron/daily-water-report.php instead
// The following functions are now defined in cron/daily-water-report.php:
// - generateMapImageWithGD()
// - generateMapImageBase64()
// - calculateZoom()
// - generateDailyReportEmail()
// - sendDailyReportEmail()

// All functions are now in cron/daily-water-report.php - removed duplicates

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $email = trim($_POST['email'] ?? '');
            $name = trim($_POST['name'] ?? '');
            
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } else {
                try {
                    $stmt = $db->prepare("
                        INSERT INTO " . TABLE_PREFIX . "daily_report_emails (email_address, name)
                        VALUES (?, ?)
                    ");
                    $stmt->execute([$email, $name ?: null]);
                    $message = 'Email address added successfully.';
                } catch (PDOException $e) {
                    $error = 'Email address already exists.';
                }
            }
        } elseif ($_POST['action'] === 'toggle') {
            $emailId = (int)($_POST['email_id'] ?? 0);
            $isActive = (int)($_POST['is_active'] ?? 0);
            
            $stmt = $db->prepare("
                UPDATE " . TABLE_PREFIX . "daily_report_emails 
                SET is_active = ?
                WHERE email_id = ?
            ");
            $stmt->execute([$isActive, $emailId]);
            $message = 'Email status updated successfully.';
        } elseif ($_POST['action'] === 'delete') {
            $emailId = (int)($_POST['email_id'] ?? 0);
            
            $stmt = $db->prepare("DELETE FROM " . TABLE_PREFIX . "daily_report_emails WHERE email_id = ?");
            $stmt->execute([$emailId]);
            $message = 'Email address deleted successfully.';
        } elseif ($_POST['action'] === 'send_manual') {
            // Send daily report manually
            try {
                require_once __DIR__ . '/../includes/email.php';
                
                $reportDate = $_POST['report_date'] ?? date('Y-m-d');
                
                // Get all active email recipients
                $stmt = $db->query("
                    SELECT email_address, name
                    FROM " . TABLE_PREFIX . "daily_report_emails
                    WHERE is_active = 1
                ");
                $recipients = $stmt->fetchAll();
                
                if (empty($recipients)) {
                    $error = 'No active email recipients found.';
                } else {
                // Get all water availability data for the specified date
                // Use stored coordinates from water_availability table, not current user profile coordinates
                $stmt = $db->prepare("
                    SELECT 
                        w.report_date,
                        w.has_water,
                        w.latitude,
                        w.longitude,
                        w.user_id,
                        u.street_number,
                        u.street_name,
                        u.suburb,
                        u.town,
                        u.name,
                        u.surname
                    FROM " . TABLE_PREFIX . "water_availability w
                    LEFT JOIN " . TABLE_PREFIX . "users u ON w.user_id = u.user_id
                    WHERE w.report_date = ?
                    AND w.latitude IS NOT NULL
                    AND w.longitude IS NOT NULL
                    ORDER BY COALESCE(u.town, ''), COALESCE(u.street_name, ''), COALESCE(u.street_number, '')
                ");
                $stmt->execute([$reportDate]);
                $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                error_log("Manual send: Found " . count($reports) . " reports for date $reportDate");
                
                if (empty($reports)) {
                    error_log("WARNING: No reports found for date $reportDate");
                }
                
                // Build address strings and prepare data
                $dataPoints = [];
                $tableRows = [];
                
                foreach ($reports as $report) {
                    error_log("Processing report: user_id=" . ($report['user_id'] ?? 'NULL') . ", lat=" . ($report['latitude'] ?? 'NULL') . ", lng=" . ($report['longitude'] ?? 'NULL') . ", has_water=" . ($report['has_water'] ?? 'NULL'));
                    $addressParts = [];
                    if (!empty($report['street_number'])) $addressParts[] = $report['street_number'];
                    if (!empty($report['street_name'])) $addressParts[] = $report['street_name'];
                    if (!empty($report['suburb'])) $addressParts[] = $report['suburb'];
                    if (!empty($report['town'])) $addressParts[] = $report['town'];
                    $address = implode(', ', $addressParts);
                    
                    // For imported data (no user_id), show coordinates if no address components
                    if (empty($address) && empty($report['user_id'])) {
                        $address = sprintf('Location (%.6f, %.6f)', $report['latitude'], $report['longitude']);
                    } elseif (empty($address)) {
                        $address = 'Address not provided';
                    }
                    
                    $dataPoints[] = [
                        'lat' => (float)$report['latitude'],
                        'lng' => (float)$report['longitude'],
                        'has_water' => (int)$report['has_water'],
                        'address' => $address
                    ];
                    
                    $status = 'No';
                    if ($report['has_water'] == 1) {
                        $status = 'Yes';
                    } elseif ($report['has_water'] == 2) {
                        $status = 'Intermittent';
                    }
                    
                    // Handle name for imported data (user_id is NULL)
                    $name = 'Imported Data';
                    if (!empty($report['user_id'])) {
                        $name = trim(($report['name'] ?? '') . ' ' . ($report['surname'] ?? '')) ?: 'N/A';
                    }
                    
                    $tableRows[] = [
                        'address' => $address,
                        'name' => $name,
                        'has_water' => $status
                    ];
                }
                
                error_log("Manual send: Built " . count($dataPoints) . " data points and " . count($tableRows) . " table rows");
                
                // Functions are already included at the top of the file
                error_log("Manual send: Preparing to generate map with " . count($dataPoints) . " data points");
                error_log("Manual send: Preparing to generate email with " . count($tableRows) . " table rows");
                
                // Generate map image as base64 embedded image (using function from cron file)
                $mapImageBase64 = generateMapImageBase64($dataPoints);
                error_log("Manual send: Map image generated: " . (empty($mapImageBase64) ? "EMPTY" : strlen($mapImageBase64) . " chars"));
                
                // Generate email content (using function from cron file)
                $emailSubject = 'Daily Water Availability Report - ' . date('F j, Y', strtotime($reportDate));
                $emailBody = generateDailyReportEmail($reportDate, $mapImageBase64, $tableRows, count($reports));
                error_log("Manual send: Email body generated: " . strlen($emailBody) . " bytes");
                
                // Validate email body
                $emailBodyLength = strlen($emailBody);
                $base64Length = strlen($mapImageBase64);
                error_log("Email body length: $emailBodyLength bytes");
                error_log("Base64 image length: $base64Length chars");
                error_log("Table rows count: " . count($tableRows));
                
                // Check for potential issues
                if ($emailBodyLength > 1000000) { // 1MB
                    error_log("WARNING: Email body is very large ($emailBodyLength bytes), may be rejected by mail server");
                }
                
                // Send email to all recipients using the same function as cron job
                $emailsSent = 0;
                $emailsFailed = 0;
                
                error_log("Attempting to send daily report email to " . count($recipients) . " recipients");
                error_log("Email subject: $emailSubject");
                
                foreach ($recipients as $recipient) {
                    $recipientEmail = $recipient['email_address'];
                    $recipientName = $recipient['name'] ?: 'Recipient';
                    
                    error_log("Sending email to: $recipientEmail");
                    
                    // Validate email address
                    if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
                        error_log("Invalid email address: $recipientEmail");
                        $emailsFailed++;
                        continue;
                    }
                    
                    // Use the same sendDailyReportEmail function from cron file
                    if (sendDailyReportEmail($recipientEmail, $recipientName, $emailSubject, $emailBody)) {
                        $emailsSent++;
                        error_log("Email sent successfully to: $recipientEmail");
                    } else {
                        $emailsFailed++;
                        $lastError = error_get_last();
                        error_log("Email failed to send to: $recipientEmail. Error: " . ($lastError['message'] ?? 'Unknown error'));
                    }
                }
                
                if ($emailsSent > 0) {
                    $message = "Daily report sent successfully! $emailsSent email(s) sent" . ($emailsFailed > 0 ? ", $emailsFailed failed" : "") . ". Report date: " . date('F j, Y', strtotime($reportDate)) . " (" . count($reports) . " reports).";
                    error_log("Daily report email summary: $emailsSent sent, $emailsFailed failed");
                } else {
                    $error = "Failed to send emails. $emailsFailed email(s) failed.";
                    error_log("Daily report email failed: All $emailsFailed emails failed to send");
                }
                } // End of else block for empty($recipients)
                
                // Clear any output buffer
                ob_end_clean();
            } catch (Exception $e) {
                ob_end_clean();
                $error = 'Error sending report: ' . $e->getMessage();
                error_log("Daily report manual send error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
            } catch (Error $e) {
                ob_end_clean();
                $error = 'Fatal error sending report: ' . $e->getMessage();
                error_log("Daily report manual send fatal error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
            }
        }
    }
}


// Get all email addresses
$stmt = $db->query("
    SELECT * FROM " . TABLE_PREFIX . "daily_report_emails
    ORDER BY created_at DESC
");
$emails = $stmt->fetchAll();

$pageTitle = 'Manage Daily Report Emails';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="content-area">
        <h1>Manage Daily Report Emails</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= h($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        
        <div class="email-form-section">
            <h2>Add Email Address</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label for="email">Email Address: <span class="required">*</span></label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="name">Name (optional):</label>
                    <input type="text" id="name" name="name" placeholder="Recipient name">
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Add Email</button>
                </div>
            </form>
        </div>
        
        <div class="emails-list-section">
            <h2>Email Recipients (<?= count($emails) ?>)</h2>
            <?php if (empty($emails)): ?>
                <p>No email addresses configured yet.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Email Address</th>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Added</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($emails as $email): ?>
                            <tr>
                                <td><?= h($email['email_address']) ?></td>
                                <td><?= h($email['name'] ?: 'N/A') ?></td>
                                <td>
                                    <?php if ($email['is_active']): ?>
                                        <span style="color: green; font-weight: 600;">Active</span>
                                    <?php else: ?>
                                        <span style="color: red; font-weight: 600;">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= formatDate($email['created_at'], 'Y-m-d') ?></td>
                                <td>
                                    <form method="POST" action="" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="email_id" value="<?= $email['email_id'] ?>">
                                        <input type="hidden" name="is_active" value="<?= $email['is_active'] ? 0 : 1 ?>">
                                        <button type="submit" class="btn btn-sm <?= $email['is_active'] ? 'btn-warning' : 'btn-success' ?>">
                                            <?= $email['is_active'] ? 'Deactivate' : 'Activate' ?>
                                        </button>
                                    </form>
                                    <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this email address?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="email_id" value="<?= $email['email_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <div class="manual-send-section" style="margin-top: 2rem; padding: 1.5rem; background-color: #e8f4f8; border-radius: 8px; border: 2px solid #2c5f8d;">
            <h3>Send Report Manually</h3>
            <p>You can manually send the daily water availability report to all active recipients.</p>
            <form method="POST" action="" onsubmit="return confirm('Are you sure you want to send the daily report to all active recipients?');">
                <input type="hidden" name="action" value="send_manual">
                <div class="form-group" style="display: flex; gap: 1rem; align-items: flex-end;">
                    <div style="flex: 1;">
                        <label for="report_date">Report Date:</label>
                        <input type="date" id="report_date" name="report_date" value="<?= date('Y-m-d') ?>" required>
                        <small style="display: block; color: #666; margin-top: 0.25rem;">Select the date for the report (defaults to today)</small>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2rem;">
                            <i class="fas fa-paper-plane"></i> Send Manually
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="info-section" style="margin-top: 2rem; padding: 1.5rem; background-color: #f5f5f5; border-radius: 8px;">
            <h3>About Daily Reports</h3>
            <p>Daily water availability reports are automatically sent at the end of each day to all active email addresses listed above.</p>
            <p><strong>Note:</strong> To enable automatic sending, you need to set up a cron job or scheduled task to run <code>cron/daily-water-report.php</code> daily.</p>
        </div>
    </div>
</div>

<?php 
$hideAdverts = true;
include __DIR__ . '/../includes/footer.php'; 
?>

