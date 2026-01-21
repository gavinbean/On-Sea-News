<?php
// ABSOLUTE FIRST LINE - Write file to confirm PHP is executing this file at all
@file_put_contents(__DIR__ . '/../uploads/script-execution-test.txt', 
    date('Y-m-d H:i:s') . ' - handle-adverts.php file executed' . "\n" .
    'Request method: ' . ($_SERVER['REQUEST_METHOD'] ?? 'unknown') . "\n" .
    'Request URI: ' . ($_SERVER['REQUEST_URI'] ?? 'unknown')
);

// TEST ENDPOINT - MUST BE FIRST, BEFORE ANY INCLUDES OR OUTPUT
// This must match test-simple.php exactly
if (isset($_GET['test']) || isset($_POST['test'])) {
    // Write to a test file to confirm script execution
    @file_put_contents(__DIR__ . '/../uploads/test-handle-adverts.txt', date('Y-m-d H:i:s') . ' - Test endpoint reached');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'message' => 'Script is reachable', 'timestamp' => date('Y-m-d H:i:s')]);
    exit;
}

/**
 * Handle business advert form submissions
 * Processes Basic, Timed, and Events advert creation/updates
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors, log them instead
ini_set('log_errors', 1);

// Start output buffering FIRST to prevent any output before redirect
// Use multiple levels to catch any output from includes
while (ob_get_level() > 0) {
    ob_end_clean();
}
ob_start();

// Log that script started (before includes)
@file_put_contents(__DIR__ . '/../uploads/debug-handle-adverts-start.txt', 
    date('Y-m-d H:i:s') . ' - Script started, before includes' . "\n" .
    'Request method: ' . ($_SERVER['REQUEST_METHOD'] ?? 'unknown') . "\n" .
    'POST action: ' . ($_POST['action'] ?? 'none')
);

require_once __DIR__ . '/../includes/functions.php';

// Log after includes
@file_put_contents(__DIR__ . '/../uploads/debug-handle-adverts-after-includes.txt', 
    date('Y-m-d H:i:s') . ' - After includes loaded' . "\n" .
    'Functions loaded: ' . (function_exists('getDB') ? 'YES' : 'NO') . "\n" .
    'isLoggedIn exists: ' . (function_exists('isLoggedIn') ? 'YES' : 'NO')
);

// Check authentication and authorization WITHOUT using requireAnyRole (which redirects)
// We need to handle redirects ourselves to maintain output buffering
if (!isLoggedIn()) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    // Redirect to login with return URL
    $returnUrl = urlencode($_SERVER['REQUEST_URI']);
    header('Location: ' . baseUrl('/login.php?return=' . $returnUrl));
    exit;
}

// Check if user has required role
if (!hasAnyRole(['ADMIN', 'ADVERTISER'])) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    // Redirect to home with error message
    header('Location: ' . baseUrl('/?error=' . urlencode('You do not have permission to access this page.')));
    exit;
}

// Define upload path if not already defined
if (!defined('ADVERT_UPLOAD_PATH')) {
    define('ADVERT_UPLOAD_PATH', __DIR__ . '/../uploads/graphics/');
}

$db = getDB();
$message = '';
$error = '';
$deletedBusinessId = null; // Store business_id for redirect after delete

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log('=== HANDLE-ADVERTS POST REQUEST ===');
    error_log('POST data: ' . print_r($_POST, true));
    error_log('FILES data: ' . print_r($_FILES, true));
    error_log('Request URI: ' . ($_SERVER['REQUEST_URI'] ?? 'unknown'));
    error_log('Script name: ' . ($_SERVER['SCRIPT_NAME'] ?? 'unknown'));
    
    $action = $_POST['action'] ?? '';
    error_log('Action: ' . $action);
    
    // If no action, set error and skip processing (will redirect at end)
    if (empty($action)) {
        error_log('ERROR: No action specified in POST request');
        $error = 'No action specified';
        // Skip all processing, go straight to redirect
    } else if ($action === 'delete') {
        // Handle delete action - same as category deletion (regular form POST)
        $advertId = (int)($_POST['advert_id'] ?? 0);
        
        if ($advertId <= 0) {
            $error = 'Invalid advert ID.';
        } else {
            try {
                // Get advert to delete files and get business_id for verification
                $stmt = $db->prepare("SELECT banner_image, display_image, business_id FROM " . TABLE_PREFIX . "business_adverts WHERE advert_id = ?");
                $stmt->execute([$advertId]);
                $advert = $stmt->fetch();
                
                if (!$advert) {
                    $error = 'Advert not found.';
                } else {
                    // Store business_id for redirect
                    $deletedBusinessId = (int)$advert['business_id'];
                    
                    // Verify business ownership (unless admin)
                    $advertBusinessId = (int)$advert['business_id'];
                    $userId = getCurrentUserId();
                    if (!hasRole('ADMIN')) {
                        $stmt = $db->prepare("SELECT user_id FROM " . TABLE_PREFIX . "businesses WHERE business_id = ?");
                        $stmt->execute([$advertBusinessId]);
                        $businessCheck = $stmt->fetch();
                        if (!$businessCheck || $businessCheck['user_id'] != $userId) {
                            $error = 'Unauthorized.';
                        }
                    }
                    
                    if (empty($error)) {
                        // Delete files
                        if (!empty($advert['banner_image']) && file_exists(__DIR__ . '/../' . $advert['banner_image'])) {
                            @unlink(__DIR__ . '/../' . $advert['banner_image']);
                        }
                        if (!empty($advert['display_image']) && file_exists(__DIR__ . '/../' . $advert['display_image'])) {
                            @unlink(__DIR__ . '/../' . $advert['display_image']);
                        }
                        
                        // Delete record
                        $stmt = $db->prepare("DELETE FROM " . TABLE_PREFIX . "business_adverts WHERE advert_id = ?");
                        $stmt->execute([$advertId]);
                        $message = 'Advert deleted successfully.';
                    }
                }
            } catch (Exception $e) {
                $error = 'Error deleting advert: ' . $e->getMessage();
            }
        }
        
        // For delete action, redirect immediately after processing (don't continue to other actions)
        $redirectUrl = $_POST['redirect_url'] ?? '';
        
        // If no redirect URL provided, use the business_id we stored
        if (empty($redirectUrl) && !empty($deletedBusinessId)) {
            $redirectUrl = '/admin/edit-business-admin.php?id=' . (int)$deletedBusinessId;
        } else if (empty($redirectUrl)) {
            $redirectUrl = '/admin/my-businesses-admin.php';
        } else {
            // Clean up redirect URL - extract path and preserve query string
            $parsedUrl = parse_url($redirectUrl);
            if ($parsedUrl && isset($parsedUrl['path'])) {
                $redirectUrl = $parsedUrl['path'];
                // Preserve existing query string (like id=...)
                if (isset($parsedUrl['query'])) {
                    $redirectUrl .= '?' . $parsedUrl['query'];
                }
            } else {
                // Fallback: extract path and query manually
                $parts = explode('?', $redirectUrl, 2);
                $redirectUrl = $parts[0];
                if (isset($parts[1])) {
                    // Remove fragment if present
                    $query = explode('#', $parts[1], 2)[0];
                    $redirectUrl .= '?' . $query;
                }
            }
        }
        
        // Ensure redirect URL starts with /
        if (substr($redirectUrl, 0, 1) !== '/') {
            $redirectUrl = '/' . $redirectUrl;
        }
        
        // Clear ALL output buffers before redirect
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Build redirect URL with message or error and tab state
        $separator = (strpos($redirectUrl, '?') !== false ? '&' : '?');
        if ($message) {
            $redirectUrl .= $separator . 'message=' . urlencode($message);
            $separator = '&';
        } else if ($error) {
            $redirectUrl .= $separator . 'error=' . urlencode($error);
            $separator = '&';
        }
        // Add tab parameter to open Advert Graphics tab
        $redirectUrl .= $separator . 'tab=advert-graphics';
        
        // Build full URL for redirect
        $fullRedirectUrl = baseUrl($redirectUrl);
        
        // Log redirect for debugging
        error_log('Delete action - Redirecting to: ' . $fullRedirectUrl);
        
        // Ensure no output has been sent
        if (headers_sent($file, $line)) {
            error_log("ERROR: Headers already sent in $file on line $line");
            // If headers already sent, output JavaScript redirect as fallback
            echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Redirecting...</title></head><body>';
            echo '<script>window.location.href = ' . json_encode($fullRedirectUrl) . ';</script>';
            echo '<p>If you are not redirected, <a href="' . h($fullRedirectUrl) . '">click here</a>.</p>';
            echo '</body></html>';
            exit;
        }
        
        // Send redirect header
        header('Location: ' . $fullRedirectUrl, true, 302);
        exit;
    } else if (!empty($action)) {
        // Only process other actions if action is not empty
        $businessId = (int)($_POST['business_id'] ?? 0);
        
        if (!$businessId) {
            $error = 'Business ID required';
        } else {
        // Get business to determine pricing_status
        $stmt = $db->prepare("SELECT pricing_status FROM " . TABLE_PREFIX . "businesses WHERE business_id = ?");
        $stmt->execute([$businessId]);
        $business = $stmt->fetch();
        
        if (!$business) {
            $error = 'Business not found';
        } else {
            $pricingStatus = $business['pricing_status'] ?? 'free';
            
            // Verify business ownership (unless admin)
            $userId = getCurrentUserId();
            if (!hasRole('ADMIN')) {
                $stmt = $db->prepare("SELECT user_id FROM " . TABLE_PREFIX . "businesses WHERE business_id = ?");
                $stmt->execute([$businessId]);
                $businessCheck = $stmt->fetch();
                if (!$businessCheck || $businessCheck['user_id'] != $userId) {
                    $error = 'Unauthorized';
                }
            }
            
            if (empty($error)) {
            if ($action === 'create') {
                // Handle file uploads
                $bannerImage = '';
                $displayImage = '';
                
                // Upload banner image
                if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
                    $bannerImage = uploadAdvertImage($_FILES['banner_image'], 'banner');
                    if (!$bannerImage) {
                        $error = 'Failed to upload banner image. Please check file type and size.';
                    }
                } else {
                    $uploadError = $_FILES['banner_image']['error'] ?? 'No file uploaded';
                    $errorMessages = [
                        UPLOAD_ERR_INI_SIZE => 'Banner image exceeds upload_max_filesize',
                        UPLOAD_ERR_FORM_SIZE => 'Banner image exceeds MAX_FILE_SIZE',
                        UPLOAD_ERR_PARTIAL => 'Banner image was only partially uploaded',
                        UPLOAD_ERR_NO_FILE => 'Banner image is required',
                        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                        UPLOAD_ERR_CANT_WRITE => 'Failed to write banner image to disk',
                        UPLOAD_ERR_EXTENSION => 'Banner image upload stopped by extension'
                    ];
                    $error = $errorMessages[$uploadError] ?? 'Banner image is required (Error: ' . $uploadError . ')';
                }
                
                // Upload display image
                if (empty($error) && isset($_FILES['display_image']) && $_FILES['display_image']['error'] === UPLOAD_ERR_OK) {
                    $displayImage = uploadAdvertImage($_FILES['display_image'], 'display');
                    if (!$displayImage) {
                        $error = 'Failed to upload display image. Please check file type and size.';
                    }
                } else if (empty($error)) {
                    $uploadError = $_FILES['display_image']['error'] ?? 'No file uploaded';
                    $errorMessages = [
                        UPLOAD_ERR_INI_SIZE => 'Display image exceeds upload_max_filesize',
                        UPLOAD_ERR_FORM_SIZE => 'Display image exceeds MAX_FILE_SIZE',
                        UPLOAD_ERR_PARTIAL => 'Display image was only partially uploaded',
                        UPLOAD_ERR_NO_FILE => 'Display image is required',
                        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                        UPLOAD_ERR_CANT_WRITE => 'Failed to write display image to disk',
                        UPLOAD_ERR_EXTENSION => 'Display image upload stopped by extension'
                    ];
                    $error = $errorMessages[$uploadError] ?? 'Display image is required (Error: ' . $uploadError . ')';
                }
                
                if (empty($error)) {
                    // For Basic pricing, check if one already exists
                    if ($pricingStatus === 'basic') {
                        $stmt = $db->prepare("SELECT advert_id FROM " . TABLE_PREFIX . "business_adverts WHERE business_id = ?");
                        $stmt->execute([$businessId]);
                        $existing = $stmt->fetch();
                        if ($existing) {
                            $error = 'A Basic advert already exists for this business. You can only have one Basic advert.';
                        }
                    }
                    
                    if (empty($error)) {
                        $startDate = null;
                        $endDate = null;
                        $eventDate = null;
                        $eventTitle = null;
                        
                        if ($pricingStatus === 'timed' || $pricingStatus === 'events') {
                            // Start and end dates are optional - if not provided, advert is always active
                            $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
                            $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
                            
                            if ($pricingStatus === 'events') {
                                $eventDate = !empty($_POST['event_date']) ? $_POST['event_date'] : null;
                                $eventTitle = trim($_POST['event_title'] ?? '');
                                
                                // Event date is optional - only adverts with event_date will appear in calendar
                                if (empty($eventTitle)) {
                                    $error = 'Event title is required for events adverts';
                                }
                            }
                        }
                        
                        if (empty($error)) {
                            try {
                                // Use pricing_status as advert_type for backward compatibility with existing schema
                                $stmt = $db->prepare("
                                    INSERT INTO " . TABLE_PREFIX . "business_adverts 
                                    (business_id, advert_type, banner_image, display_image, start_date, end_date, event_date, event_title, last_changed_at, approval_status)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'pending')
                                ");
                                $result = $stmt->execute([$businessId, $pricingStatus, $bannerImage, $displayImage, $startDate, $endDate, $eventDate, $eventTitle]);
                                
                                if ($result) {
                                    $advertId = $db->lastInsertId();
                                    $message = 'Advert created successfully';
                                    error_log('Advert created - Banner: ' . $bannerImage . ', Display: ' . $displayImage);
                                    
                                    // Send notifications to followers if advert should be active
                                    require_once __DIR__ . '/../includes/email_business_adverts.php';
                                    
                                    // Get created advert and business info
                                    $stmt = $db->prepare("
                                        SELECT a.*, b.business_name, b.description, b.telephone, b.email, b.website, b.address
                                        FROM " . TABLE_PREFIX . "business_adverts a
                                        JOIN " . TABLE_PREFIX . "businesses b ON a.business_id = b.business_id
                                        WHERE a.advert_id = ?
                                    ");
                                    $stmt->execute([$advertId]);
                                    $newAdvert = $stmt->fetch();
                                    
                                    if ($newAdvert && $newAdvert['is_active'] == 1) {
                                        $business = [
                                            'business_id' => $newAdvert['business_id'],
                                            'business_name' => $newAdvert['business_name'],
                                            'description' => $newAdvert['description'],
                                            'telephone' => $newAdvert['telephone'],
                                            'email' => $newAdvert['email'],
                                            'website' => $newAdvert['website'],
                                            'address' => $newAdvert['address']
                                        ];
                                        
                                        if (shouldNotifyAdvertUpdate($newAdvert)) {
                                            $emailsSent = sendAdvertUpdateEmail($newAdvert, $business);
                                            if ($emailsSent > 0) {
                                                $message .= " Notification emails sent to {$emailsSent} follower(s).";
                                            }
                                        }
                                    }
                                } else {
                                    $error = 'Failed to save advert. Please try again.';
                                    error_log('Advert creation failed: ' . print_r($stmt->errorInfo(), true));
                                    error_log('Values attempted: Banner=' . $bannerImage . ', Display=' . $displayImage);
                                }
                            } catch (Exception $e) {
                                $error = 'Error saving advert: ' . $e->getMessage();
                                error_log('Advert creation exception: ' . $e->getMessage());
                            }
                        }
                    }
                }
            } elseif ($action === 'update') {
                $advertId = (int)($_POST['advert_id'] ?? 0);
                if (!$advertId) {
                    $error = 'Advert ID required';
                } else {
                    // Get existing advert
                    $stmt = $db->prepare("SELECT * FROM " . TABLE_PREFIX . "business_adverts WHERE advert_id = ?");
                    $stmt->execute([$advertId]);
                    $existing = $stmt->fetch();
                    
                    if (!$existing) {
                        $error = 'Advert not found';
                    } else {
                        // Get business pricing_status for update logic
                        $stmt = $db->prepare("SELECT pricing_status FROM " . TABLE_PREFIX . "businesses WHERE business_id = ?");
                        $stmt->execute([$businessId]);
                        $businessUpdate = $stmt->fetch();
                        $pricingStatusUpdate = $businessUpdate['pricing_status'] ?? 'free';
                        
                        // For Basic pricing, check if a month has passed since last change
                        if ($pricingStatusUpdate === 'basic' && $existing['last_changed_at']) {
                            $lastChanged = new DateTime($existing['last_changed_at']);
                            $now = new DateTime();
                            $diff = $now->diff($lastChanged);
                            if ($diff->days < 30) {
                                $error = 'Basic adverts can only be changed once per month. Last changed: ' . $lastChanged->format('Y-m-d');
                            }
                        }
                        
                        if (empty($error)) {
                            $bannerImage = $existing['banner_image'];
                            $displayImage = $existing['display_image'];
                            
                            // Handle new file uploads
                            if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
                                $newBanner = uploadAdvertImage($_FILES['banner_image'], 'banner');
                                if ($newBanner) {
                                    // Delete old banner
                                    if (file_exists(__DIR__ . '/../' . $bannerImage)) {
                                        unlink(__DIR__ . '/../' . $bannerImage);
                                    }
                                    $bannerImage = $newBanner;
                                }
                            }
                            
                            if (isset($_FILES['display_image']) && $_FILES['display_image']['error'] === UPLOAD_ERR_OK) {
                                $newDisplay = uploadAdvertImage($_FILES['display_image'], 'display');
                                if ($newDisplay) {
                                    // Delete old display image
                                    if (file_exists(__DIR__ . '/../' . $displayImage)) {
                                        unlink(__DIR__ . '/../' . $displayImage);
                                    }
                                    $displayImage = $newDisplay;
                                }
                            }
                            
                            // Initialize with existing values
                            $startDate = $existing['start_date'];
                            $endDate = $existing['end_date'];
                            $eventDate = $existing['event_date'];
                            $eventTitle = $existing['event_title'];
                            
                            // Update with POST values if provided
                            if ($pricingStatusUpdate === 'timed' || $pricingStatusUpdate === 'events') {
                                if (!empty($_POST['start_date'])) {
                                    $startDate = $_POST['start_date'];
                                }
                                if (!empty($_POST['end_date'])) {
                                    $endDate = $_POST['end_date'];
                                }
                                
                                if ($pricingStatusUpdate === 'events') {
                                    if (!empty($_POST['event_date'])) {
                                        $eventDate = $_POST['event_date'];
                                    }
                                    if (!empty($_POST['event_title'])) {
                                        $eventTitle = trim($_POST['event_title']);
                                    }
                                }
                            }
                            
                            try {
                                $stmt = $db->prepare("
                                    UPDATE " . TABLE_PREFIX . "business_adverts 
                                    SET banner_image = ?, display_image = ?, start_date = ?, end_date = ?, 
                                        event_date = ?, event_title = ?, last_changed_at = NOW()
                                    WHERE advert_id = ?
                                ");
                                $result = $stmt->execute([$bannerImage, $displayImage, $startDate, $endDate, $eventDate, $eventTitle, $advertId]);
                                
                                if ($result) {
                                    $message = 'Advert updated successfully';
                                    
                                    // Send notifications to followers if advert should be active
                                    require_once __DIR__ . '/../includes/email_business_adverts.php';
                                    
                                    // Get updated advert and business info
                                    $stmt = $db->prepare("
                                        SELECT a.*, b.business_name, b.description, b.telephone, b.email, b.website, b.address
                                        FROM " . TABLE_PREFIX . "business_adverts a
                                        JOIN " . TABLE_PREFIX . "businesses b ON a.business_id = b.business_id
                                        WHERE a.advert_id = ?
                                    ");
                                    $stmt->execute([$advertId]);
                                    $updatedAdvert = $stmt->fetch();
                                    
                                    if ($updatedAdvert && $updatedAdvert['is_active'] == 1) {
                                        $business = [
                                            'business_id' => $updatedAdvert['business_id'],
                                            'business_name' => $updatedAdvert['business_name'],
                                            'description' => $updatedAdvert['description'],
                                            'telephone' => $updatedAdvert['telephone'],
                                            'email' => $updatedAdvert['email'],
                                            'website' => $updatedAdvert['website'],
                                            'address' => $updatedAdvert['address']
                                        ];
                                        
                                        if (shouldNotifyAdvertUpdate($updatedAdvert, $existing)) {
                                            $emailsSent = sendAdvertUpdateEmail($updatedAdvert, $business);
                                            if ($emailsSent > 0) {
                                                $message .= " Notification emails sent to {$emailsSent} follower(s).";
                                            }
                                        }
                                    }
                                } else {
                                    $error = 'Failed to update advert. Please try again.';
                                    error_log('Advert update failed: ' . print_r($stmt->errorInfo(), true));
                                }
                            } catch (Exception $e) {
                                $error = 'Error updating advert: ' . $e->getMessage();
                                error_log('Advert update exception: ' . $e->getMessage());
                            }
                        }
                    }
                }
            } elseif ($action === 'update_status') {
                $advertId = (int)($_POST['advert_id'] ?? 0);
                $isActive = (int)($_POST['is_active'] ?? 0);
                
                if (!$advertId) {
                    $error = 'Advert ID required';
                } else {
                    try {
                        $stmt = $db->prepare("UPDATE " . TABLE_PREFIX . "business_adverts SET is_active = ? WHERE advert_id = ?");
                        $result = $stmt->execute([$isActive, $advertId]);
                        
                        if ($result) {
                            $message = 'Advert status updated successfully';
                        } else {
                            $error = 'Failed to update advert status';
                        }
                    } catch (Exception $e) {
                        $error = 'Error updating advert status: ' . $e->getMessage();
                        error_log('Advert status update exception: ' . $e->getMessage());
                    }
                }
            }
        }
    }
}
}

// Helper function to upload advert images
function uploadAdvertImage($file, $prefix = '') {
    error_log('uploadAdvertImage called - File: ' . ($file['name'] ?? 'no name') . ', Size: ' . ($file['size'] ?? 0) . ', Error: ' . ($file['error'] ?? 'unknown'));
    
    // Check for upload errors first
    if (isset($file['error']) && $file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        $errorMsg = $errorMessages[$file['error']] ?? 'Unknown upload error: ' . $file['error'];
        error_log('Upload error detected: ' . $errorMsg);
        return false;
    }
    
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $fileType = $file['type'] ?? '';
    
    if (!in_array($fileType, $allowedTypes)) {
        error_log('Invalid file type: ' . $fileType);
        return false;
    }
    
    // Ensure upload directory exists
    if (!is_dir(ADVERT_UPLOAD_PATH)) {
        error_log('Upload directory does not exist: ' . ADVERT_UPLOAD_PATH);
        if (!mkdir(ADVERT_UPLOAD_PATH, 0755, true)) {
            error_log('Failed to create upload directory: ' . ADVERT_UPLOAD_PATH);
            return false;
        }
        error_log('Created upload directory: ' . ADVERT_UPLOAD_PATH);
    }
    
    // Check if directory is writable
    if (!is_writable(ADVERT_UPLOAD_PATH)) {
        error_log('Upload directory is not writable: ' . ADVERT_UPLOAD_PATH);
        return false;
    }
    
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $newFileName = $prefix . '_' . uniqid() . '.' . $fileExtension;
    $destPath = ADVERT_UPLOAD_PATH . $newFileName;
    
    error_log('Attempting to move file from: ' . ($file['tmp_name'] ?? 'no tmp_name') . ' to: ' . $destPath);
    
    if (move_uploaded_file($file['tmp_name'], $destPath)) {
        $returnPath = 'uploads/graphics/' . $newFileName;
        error_log('File uploaded successfully: ' . $returnPath);
        return $returnPath;
    } else {
        error_log('Failed to move uploaded file. Source: ' . ($file['tmp_name'] ?? 'no tmp_name') . ', Dest: ' . $destPath);
        error_log('Upload error code: ' . ($file['error'] ?? 'unknown'));
        error_log('is_uploaded_file check: ' . (is_uploaded_file($file['tmp_name']) ? 'YES' : 'NO'));
        error_log('file_exists check: ' . (file_exists($file['tmp_name']) ? 'YES' : 'NO'));
        return false;
    }
}

// For non-AJAX requests, redirect back
$redirectUrl = $_POST['redirect_url'] ?? '';

// If no redirect URL provided, try to get business_id from POST or default
if (empty($redirectUrl)) {
    // For delete action, use the business_id we stored
    if (isset($action) && $action === 'delete' && isset($deletedBusinessId) && $deletedBusinessId > 0) {
        $redirectUrl = '/admin/edit-business-admin.php?id=' . (int)$deletedBusinessId;
    } else {
        // Try to get business_id from POST
        $businessId = (int)($_POST['business_id'] ?? 0);
        if ($businessId > 0) {
            $redirectUrl = '/admin/edit-business-admin.php?id=' . $businessId;
        } else {
            $redirectUrl = '/admin/my-businesses-admin.php';
        }
    }
} else {
    // Clean up redirect URL - handle both full URLs and relative paths
    // Remove any domain/scheme if present
    $redirectUrl = preg_replace('#^https?://[^/]+#', '', $redirectUrl);
    
    // Extract path and query string
    $parsedUrl = parse_url($redirectUrl);
    if ($parsedUrl && isset($parsedUrl['path'])) {
        $redirectUrl = $parsedUrl['path'];
        // Preserve existing query string (like id=...)
        if (isset($parsedUrl['query'])) {
            $redirectUrl .= '?' . $parsedUrl['query'];
        }
    } else {
        // Fallback: extract path and query manually
        $parts = explode('?', $redirectUrl, 2);
        $redirectUrl = $parts[0];
        if (isset($parts[1])) {
            // Remove fragment if present
            $query = explode('#', $parts[1], 2)[0];
            $redirectUrl .= '?' . $query;
        }
    }
}

// Ensure redirect URL starts with /
if (substr($redirectUrl, 0, 1) !== '/') {
    $redirectUrl = '/' . $redirectUrl;
}

// Build redirect URL with message or error and tab state
$separator = (strpos($redirectUrl, '?') !== false ? '&' : '?');
if ($message) {
    $redirectUrl .= $separator . 'message=' . urlencode($message);
    $separator = '&';
} else if ($error) {
    $redirectUrl .= $separator . 'error=' . urlencode($error);
    $separator = '&';
}

// Add tab parameter for create/update actions
if (isset($action) && ($action === 'create' || $action === 'update')) {
    $redirectUrl .= $separator . 'tab=advert-graphics';
}

// Log redirect for debugging
error_log('=== REDIRECT DEBUG ===');
error_log('Original redirect_url from POST: ' . ($_POST['redirect_url'] ?? 'none'));
error_log('Cleaned redirectUrl: ' . $redirectUrl);
error_log('Message: ' . ($message ?: 'none'));
error_log('Error: ' . ($error ?: 'none'));
error_log('Action: ' . ($action ?? 'none'));

// Clear ALL output buffers before redirect
while (ob_get_level() > 0) {
    ob_end_clean();
}

// Ensure no output has been sent
if (headers_sent($file, $line)) {
    error_log("ERROR: Headers already sent in $file on line $line");
    // If headers already sent, output JavaScript redirect as fallback
    $fullUrl = baseUrl($redirectUrl);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Redirecting...</title></head><body>';
    echo '<script>window.location.href = ' . json_encode($fullUrl) . ';</script>';
    echo '<p>If you are not redirected, <a href="' . h($fullUrl) . '">click here</a>.</p>';
    echo '</body></html>';
    exit;
}

// Build full URL for redirect
$fullRedirectUrl = baseUrl($redirectUrl);

// Log final redirect URL
error_log('Full redirect URL: ' . $fullRedirectUrl);
error_log('====================');

// Write debug file before redirect
@file_put_contents(__DIR__ . '/../uploads/debug-handle-adverts-redirect.txt', 
    date('Y-m-d H:i:s') . "\n" .
    'Redirect URL: ' . $fullRedirectUrl . "\n" .
    'Action: ' . ($action ?? 'none') . "\n" .
    'Message: ' . ($message ?: 'none') . "\n" .
    'Error: ' . ($error ?: 'none')
);

// Send redirect header
header('Location: ' . $fullRedirectUrl, true, 302);
exit;
