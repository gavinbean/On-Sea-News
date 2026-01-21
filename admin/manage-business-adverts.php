<?php
// TEST ENDPOINT - MUST BE FIRST, BEFORE ANY INCLUDES OR OUTPUT
// This must match test-simple.php exactly
if (isset($_GET['test']) || isset($_POST['test'])) {
    error_log('MANAGE-BUSINESS-ADVERTS TEST ENDPOINT REACHED');
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
ob_start();

require_once __DIR__ . '/../includes/functions.php';

// Check for AJAX request before requiring role (to avoid redirects for AJAX)
$isAjaxRequest = isset($_GET['ajax']) || isset($_POST['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

// Debug logging
error_log('=== AJAX Request Check ===');
error_log('GET[ajax]: ' . (isset($_GET['ajax']) ? $_GET['ajax'] : 'not set'));
error_log('POST[ajax]: ' . (isset($_POST['ajax']) ? $_POST['ajax'] : 'not set'));
error_log('HTTP_X_REQUESTED_WITH: ' . (isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? $_SERVER['HTTP_X_REQUESTED_WITH'] : 'not set'));
error_log('isAjaxRequest: ' . ($isAjaxRequest ? 'YES' : 'NO'));

// For AJAX requests, check role but don't redirect - return JSON error instead
if ($isAjaxRequest) {
    if (!isLoggedIn()) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }
    if (!hasAnyRole(['ADMIN', 'ADVERTISER'])) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
} else {
    requireAnyRole(['ADMIN', 'ADVERTISER']);
}

// Define upload path if not already defined
if (!defined('ADVERT_UPLOAD_PATH')) {
    define('ADVERT_UPLOAD_PATH', __DIR__ . '/../uploads/graphics/');
}

$db = getDB();
$message = '';
$error = '';
$deletedBusinessId = null; // Store business_id for redirect after delete

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Handle delete action - same as category deletion (regular form POST)
    if ($action === 'delete') {
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
        
        // Log redirect for debugging
        error_log('Redirecting to: ' . $redirectUrl);
        
        // Ensure no output has been sent
        if (headers_sent($file, $line)) {
            error_log("ERROR: Headers already sent in $file on line $line");
            // If headers already sent, output JavaScript redirect as fallback
            echo '<script>window.location.href = ' . json_encode($redirectUrl) . ';</script>';
            exit;
        }
        
        // Redirect immediately
        redirect($redirectUrl);
    } else if ($isAjaxRequest) {
            error_log('=== SENDING AJAX RESPONSE ===');
            
            // Clear ALL output buffers
            $obLevel = ob_get_level();
            error_log('Output buffer levels to clear: ' . $obLevel);
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            error_log('Output buffers cleared');
            
            // Check if headers already sent
            if (headers_sent($file, $line)) {
                error_log("ERROR: Headers already sent in $file on line $line");
                // Try to send error as text anyway
                die('ERROR: Headers already sent in ' . $file . ' on line ' . $line);
            }
            
            // Set headers
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-cache, must-revalidate');
            error_log('Headers set');
            
            // Build response
            $response = [
                'success' => empty($error),
                'message' => $message ?: ($error ?: 'Unknown error')
            ];
            $json = json_encode($response, JSON_UNESCAPED_UNICODE);
            error_log('JSON to send: ' . $json);
            error_log('JSON length: ' . strlen($json));
            
            // Send response
            echo $json;
            error_log('Response sent via echo');
            
            // Force flush
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            
            error_log('=== EXITING ===');
            exit;
        } else {
            error_log('Not an AJAX request, continuing with normal flow');
        }
    } else {
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
                                    (business_id, advert_type, banner_image, display_image, start_date, end_date, event_date, event_title, last_changed_at)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
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
                            if ($advertType === 'timed' || $advertType === 'events') {
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

// Helper function to upload advert images
function uploadAdvertImage($file, $prefix = '') {
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $fileType = $file['type'];
    
    if (!in_array($fileType, $allowedTypes)) {
        return false;
    }
    
    // Ensure upload directory exists
    if (!is_dir(ADVERT_UPLOAD_PATH)) {
        if (!mkdir(ADVERT_UPLOAD_PATH, 0755, true)) {
            error_log('Failed to create upload directory: ' . ADVERT_UPLOAD_PATH);
            return false;
        }
    }
    
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $newFileName = $prefix . '_' . uniqid() . '.' . $fileExtension;
    $destPath = ADVERT_UPLOAD_PATH . $newFileName;
    
    if (move_uploaded_file($file['tmp_name'], $destPath)) {
        return 'uploads/graphics/' . $newFileName;
    } else {
        error_log('Failed to move uploaded file. Source: ' . $file['tmp_name'] . ', Dest: ' . $destPath);
        error_log('Upload error: ' . $file['error']);
        return false;
    }
}

// Return JSON response for AJAX requests
if (isset($isAjaxRequest) && $isAjaxRequest) {
    error_log('AJAX request detected - preparing JSON response');
    error_log('Message: ' . ($message ?: 'none') . ', Error: ' . ($error ?: 'none'));
    
    // Clear ALL output buffers before JSON response
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Ensure no output has been sent
    if (headers_sent($file, $line)) {
        error_log("Headers already sent in $file on line $line");
        // If headers already sent, we can't send JSON, so output error as text
        die('ERROR: Headers already sent. Cannot send JSON response.');
    }
    
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    
    // Ensure we have a message or error
    if (empty($message) && empty($error)) {
        $error = 'No response generated';
        error_log('WARNING: No message or error set for AJAX response');
    }
    
    $response = [
        'success' => empty($error),
        'message' => $message ?: ($error ?: 'Unknown error')
    ];
    
    $jsonResponse = json_encode($response, JSON_UNESCAPED_UNICODE);
    error_log('Sending AJAX JSON Response: ' . $jsonResponse);
    
    // Flush output to ensure it's sent
    echo $jsonResponse;
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
    exit;
}

// For non-AJAX requests, redirect back
$redirectUrl = $_POST['redirect_url'] ?? '';

// If no redirect URL provided, try to get business_id from POST or default
if (empty($redirectUrl)) {
    // For delete action, use the business_id we stored
    if (isset($action) && $action === 'delete' && isset($deletedBusinessId) && $deletedBusinessId > 0) {
        $redirectUrl = '/admin/edit-business-admin.php?id=' . (int)$deletedBusinessId;
    } else {
        $redirectUrl = '/admin/my-businesses-admin.php';
    }
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

// Build redirect URL with message or error
$separator = (strpos($redirectUrl, '?') !== false ? '&' : '?');
if ($message) {
    $redirectUrl .= $separator . 'message=' . urlencode($message);
    $separator = '&';
} else if ($error) {
    $redirectUrl .= $separator . 'error=' . urlencode($error);
    $separator = '&';
}
// Add tab parameter to open Advert Graphics tab for create/update actions
if (isset($action) && ($action === 'create' || $action === 'update')) {
    $redirectUrl .= $separator . 'tab=advert-graphics';
}

// Log redirect for debugging
error_log('Redirecting to: ' . $redirectUrl);

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

// Use redirect function which handles baseUrl properly
redirect($redirectUrl);
