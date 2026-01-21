<?php
// ABSOLUTE FIRST LINE - Write file to confirm PHP is executing this file at all
@file_put_contents(__DIR__ . '/uploads/script-execution-test-root.txt', 
    date('Y-m-d H:i:s') . ' - handle-business-adverts.php file executed' . "\n" .
    'Request method: ' . ($_SERVER['REQUEST_METHOD'] ?? 'unknown') . "\n" .
    'Request URI: ' . ($_SERVER['REQUEST_URI'] ?? 'unknown')
);

// TEST ENDPOINT - MUST BE FIRST, BEFORE ANY INCLUDES OR OUTPUT
if (isset($_GET['test']) || isset($_POST['test'])) {
    @file_put_contents(__DIR__ . '/uploads/test-handle-adverts-root.txt', date('Y-m-d H:i:s') . ' - Test endpoint reached');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'message' => 'Script is reachable', 'timestamp' => date('Y-m-d H:i:s')]);
    exit;
}

/**
 * Handle business advert form submissions
 * Processes Basic, Timed, and Events advert creation/updates
 * RESTRICTED TO ADMIN ROLE ONLY
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start output buffering FIRST to prevent any output before redirect
while (ob_get_level() > 0) {
    ob_end_clean();
}
ob_start();

// Log that script started (before includes)
@file_put_contents(__DIR__ . '/uploads/debug-handle-adverts-root-start.txt', 
    date('Y-m-d H:i:s') . ' - Script started, before includes' . "\n" .
    'Request method: ' . ($_SERVER['REQUEST_METHOD'] ?? 'unknown') . "\n" .
    'POST action: ' . ($_POST['action'] ?? 'none')
);

require_once __DIR__ . '/includes/functions.php';

// Log after includes
@file_put_contents(__DIR__ . '/uploads/debug-handle-adverts-root-after-includes.txt', 
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
    $returnUrl = urlencode($_SERVER['REQUEST_URI']);
    header('Location: ' . baseUrl('/login.php?return=' . $returnUrl));
    exit;
}

// Check if user has ADMIN role (restricted to admin only for testing)
if (!hasRole('ADMIN')) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Location: ' . baseUrl('/?error=' . urlencode('You do not have permission to access this page.')));
    exit;
}

// Define upload path if not already defined
if (!defined('ADVERT_UPLOAD_PATH')) {
    define('ADVERT_UPLOAD_PATH', __DIR__ . '/uploads/graphics/');
}

$db = getDB();
$message = '';
$error = '';
$deletedBusinessId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Write debug file to confirm POST received
    @file_put_contents(__DIR__ . '/uploads/test-handle-adverts-root-post.txt', 
        date('Y-m-d H:i:s') . "\n" . 
        'POST: ' . print_r($_POST, true) . "\n" . 
        'FILES: ' . print_r($_FILES, true) . "\n" . 
        'Action: ' . ($_POST['action'] ?? 'none')
    );
    
    error_log('=== HANDLE-ADVERTS-ROOT POST REQUEST ===');
    error_log('POST data: ' . print_r($_POST, true));
    error_log('FILES data: ' . print_r($_FILES, true));
    
    $action = $_POST['action'] ?? '';
    error_log('Action: ' . $action);
    
    if (empty($action)) {
        error_log('ERROR: No action specified in POST request');
        $error = 'No action specified';
    } else if ($action === 'delete') {
        // Handle delete action
        $advertId = (int)($_POST['advert_id'] ?? 0);
        
        if ($advertId <= 0) {
            $error = 'Invalid advert ID.';
        } else {
            try {
                // Get advert to delete files and get business_id
                $stmt = $db->prepare("SELECT banner_image, display_image, business_id FROM " . TABLE_PREFIX . "business_adverts WHERE advert_id = ?");
                $stmt->execute([$advertId]);
                $advert = $stmt->fetch();
                
                if (!$advert) {
                    $error = 'Advert not found.';
                } else {
                    $deletedBusinessId = (int)$advert['business_id'];
                    
                    // Delete files
                    if (!empty($advert['banner_image']) && file_exists(__DIR__ . '/' . $advert['banner_image'])) {
                        @unlink(__DIR__ . '/' . $advert['banner_image']);
                    }
                    if (!empty($advert['display_image']) && file_exists(__DIR__ . '/' . $advert['display_image'])) {
                        @unlink(__DIR__ . '/' . $advert['display_image']);
                    }
                    
                    // Delete record
                    $stmt = $db->prepare("DELETE FROM " . TABLE_PREFIX . "business_adverts WHERE advert_id = ?");
                    $stmt->execute([$advertId]);
                    $message = 'Advert deleted successfully.';
                }
            } catch (Exception $e) {
                $error = 'Error deleting advert: ' . $e->getMessage();
                error_log('Delete error: ' . $e->getMessage());
            }
        }
        
        // Redirect after delete
        $redirectUrl = $_POST['redirect_url'] ?? '';
        if (empty($redirectUrl) && !empty($deletedBusinessId)) {
            $redirectUrl = '/admin/edit-business-admin.php?id=' . (int)$deletedBusinessId;
        } else if (empty($redirectUrl)) {
            $redirectUrl = '/admin/my-businesses-admin.php';
        } else {
            $parsedUrl = parse_url($redirectUrl);
            if ($parsedUrl && isset($parsedUrl['path'])) {
                $redirectUrl = $parsedUrl['path'];
                if (isset($parsedUrl['query'])) {
                    $redirectUrl .= '?' . $parsedUrl['query'];
                }
            }
        }
        
        if (substr($redirectUrl, 0, 1) !== '/') {
            $redirectUrl = '/' . $redirectUrl;
        }
        
        $separator = (strpos($redirectUrl, '?') !== false ? '&' : '?');
        if ($message) {
            $redirectUrl .= $separator . 'message=' . urlencode($message);
            $separator = '&';
        } else if ($error) {
            $redirectUrl .= $separator . 'error=' . urlencode($error);
            $separator = '&';
        }
        $redirectUrl .= $separator . 'tab=advert-graphics';
        
        $fullRedirectUrl = baseUrl($redirectUrl);
        
        @file_put_contents(__DIR__ . '/uploads/debug-handle-adverts-root-redirect.txt', 
            date('Y-m-d H:i:s') . "\n" .
            'Redirect URL: ' . $fullRedirectUrl . "\n" .
            'Action: delete'
        );
        
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        if (headers_sent($file, $line)) {
            error_log("ERROR: Headers already sent in $file on line $line");
            echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Redirecting...</title></head><body>';
            echo '<script>window.location.href = ' . json_encode($fullRedirectUrl) . ';</script>';
            echo '<p>If you are not redirected, <a href="' . h($fullRedirectUrl) . '">click here</a>.</p>';
            echo '</body></html>';
            exit;
        }
        
        header('Location: ' . $fullRedirectUrl, true, 302);
        exit;
    } else if (!empty($action)) {
        // Handle create/update actions - simplified for testing
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
                
                if (empty($error)) {
                    if ($action === 'create') {
                        // Simplified create - just log for now
                        @file_put_contents(__DIR__ . '/uploads/debug-create-action.txt', 
                            date('Y-m-d H:i:s') . "\n" .
                            'Business ID: ' . $businessId . "\n" .
                            'Pricing Status: ' . $pricingStatus . "\n" .
                            'FILES: ' . print_r($_FILES, true)
                        );
                        
                        // Handle file uploads
                        $bannerImage = '';
                        $displayImage = '';
                        
                        // Upload banner image
                        if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
                            $bannerImage = uploadAdvertImage($_FILES['banner_image'], 'banner');
                            if (!$bannerImage) {
                                $error = 'Failed to upload banner image.';
                            }
                        } else {
                            $uploadError = $_FILES['banner_image']['error'] ?? 'No file uploaded';
                            $error = 'Banner image is required (Error: ' . $uploadError . ')';
                        }
                        
                        // Upload display image
                        if (empty($error) && isset($_FILES['display_image']) && $_FILES['display_image']['error'] === UPLOAD_ERR_OK) {
                            $displayImage = uploadAdvertImage($_FILES['display_image'], 'display');
                            if (!$displayImage) {
                                $error = 'Failed to upload display image.';
                            }
                        } else if (empty($error)) {
                            $uploadError = $_FILES['display_image']['error'] ?? 'No file uploaded';
                            $error = 'Display image is required (Error: ' . $uploadError . ')';
                        }
                        
                        if (empty($error)) {
                            // For Basic pricing, check if one already exists
                            if ($pricingStatus === 'basic') {
                                $stmt = $db->prepare("SELECT advert_id FROM " . TABLE_PREFIX . "business_adverts WHERE business_id = ?");
                                $stmt->execute([$businessId]);
                                if ($stmt->fetch()) {
                                    $error = 'Basic plan allows only one advert. Please edit the existing one.';
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
                                        $stmt = $db->prepare("
                                            INSERT INTO " . TABLE_PREFIX . "business_adverts 
                                            (business_id, banner_image, display_image, start_date, end_date, event_date, event_title, is_active, approval_status, advert_type)
                                            VALUES (?, ?, ?, ?, ?, ?, ?, 1, 'pending', ?)
                                        ");
                                        $advertType = $pricingStatus === 'events' ? 'events' : ($pricingStatus === 'timed' ? 'timed' : 'basic');
                                        $stmt->execute([$businessId, $bannerImage, $displayImage, $startDate, $endDate, $eventDate, $eventTitle, $advertType]);
                                        $advertId = $db->lastInsertId();
                                        $message = 'Advert created successfully.';
                                        
                                        // Send notifications to followers if advert should be active
                                        require_once __DIR__ . '/includes/email_business_adverts.php';
                                        
                                        // Get created advert and business info (include approval status and notification fields)
                                        $stmt = $db->prepare("
                                            SELECT a.*, b.business_name, b.description, b.telephone, b.email, b.website, b.address
                                            FROM " . TABLE_PREFIX . "business_adverts a
                                            JOIN " . TABLE_PREFIX . "businesses b ON a.business_id = b.business_id
                                            WHERE a.advert_id = ?
                                        ");
                                        $stmt->execute([$advertId]);
                                        $newAdvert = $stmt->fetch();
                                        
                                        if ($newAdvert) {
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
                                    } catch (Exception $e) {
                                        $error = 'Error creating advert: ' . $e->getMessage();
                                        error_log('Create error: ' . $e->getMessage());
                                    }
                                }
                            }
                        }
                    } else if ($action === 'update') {
                        $advertId = (int)($_POST['advert_id'] ?? 0);
                        
                        if ($advertId <= 0) {
                            $error = 'Invalid advert ID.';
                        } else {
                            try {
                                // Get existing advert
                                $stmt = $db->prepare("SELECT * FROM " . TABLE_PREFIX . "business_adverts WHERE advert_id = ?");
                                $stmt->execute([$advertId]);
                                $existing = $stmt->fetch();
                                
                                if (!$existing) {
                                    $error = 'Advert not found.';
                                } else {
                                    $bannerImage = $existing['banner_image'];
                                    $displayImage = $existing['display_image'];
                                    
                                    $imageChanged = false;
                                    
                                    // Handle file uploads if provided
                                    if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
                                        $newBanner = uploadAdvertImage($_FILES['banner_image'], 'banner');
                                        if ($newBanner) {
                                            // Delete old banner
                                            if (!empty($bannerImage) && file_exists(__DIR__ . '/' . $bannerImage)) {
                                                @unlink(__DIR__ . '/' . $bannerImage);
                                            }
                                            $bannerImage = $newBanner;
                                            $imageChanged = true;
                                        }
                                    }
                                    
                                    if (isset($_FILES['display_image']) && $_FILES['display_image']['error'] === UPLOAD_ERR_OK) {
                                        $newDisplay = uploadAdvertImage($_FILES['display_image'], 'display');
                                        if ($newDisplay) {
                                            // Delete old display
                                            if (!empty($displayImage) && file_exists(__DIR__ . '/' . $displayImage)) {
                                                @unlink(__DIR__ . '/' . $displayImage);
                                            }
                                            $displayImage = $newDisplay;
                                            $imageChanged = true;
                                        }
                                    }
                                    
                                    // Update dates if provided
                                    $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : $existing['start_date'];
                                    $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : $existing['end_date'];
                                    $eventDate = !empty($_POST['event_date']) ? $_POST['event_date'] : $existing['event_date'];
                                    $eventTitle = !empty($_POST['event_title']) ? trim($_POST['event_title']) : $existing['event_title'];
                                    
                                    // Update is_active status
                                    $isActive = isset($_POST['is_active']) && $_POST['is_active'] === '1' ? 1 : 0;
                                    
                                    // If images changed, set approval_status back to pending
                                    $approvalStatus = $imageChanged ? 'pending' : $existing['approval_status'];
                                    
                                    $stmt = $db->prepare("
                                        UPDATE " . TABLE_PREFIX . "business_adverts 
                                        SET banner_image = ?, display_image = ?, start_date = ?, end_date = ?, event_date = ?, event_title = ?, is_active = ?, approval_status = ?
                                        WHERE advert_id = ?
                                    ");
                                    $stmt->execute([$bannerImage, $displayImage, $startDate, $endDate, $eventDate, $eventTitle, $isActive, $approvalStatus, $advertId]);
                                    $message = 'Advert updated successfully.';
                                    
                                    // Send notifications to followers if advert should be active
                                    if ($isActive == 1) {
                                        require_once __DIR__ . '/includes/email_business_adverts.php';
                                        
                                        // Get updated advert and business info (include approval status and notification fields)
                                        $stmt = $db->prepare("
                                            SELECT a.*, b.business_name, b.description, b.telephone, b.email, b.website, b.address
                                            FROM " . TABLE_PREFIX . "business_adverts a
                                            JOIN " . TABLE_PREFIX . "businesses b ON a.business_id = b.business_id
                                            WHERE a.advert_id = ?
                                        ");
                                        $stmt->execute([$advertId]);
                                        $updatedAdvert = $stmt->fetch();
                                        
                                        if ($updatedAdvert) {
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
                                    }
                                }
                            } catch (Exception $e) {
                                $error = 'Error updating advert: ' . $e->getMessage();
                                error_log('Update error: ' . $e->getMessage());
                            }
                        }
                    }
                }
            }
        }
    }
}

// Upload function
function uploadAdvertImage($file, $prefix) {
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowedTypes)) {
        error_log('Invalid file type: ' . $file['type']);
        return false;
    }
    
    if ($file['size'] > $maxSize) {
        error_log('File too large: ' . $file['size']);
        return false;
    }
    
    if (!is_dir(ADVERT_UPLOAD_PATH)) {
        error_log('Upload directory does not exist: ' . ADVERT_UPLOAD_PATH);
        if (!mkdir(ADVERT_UPLOAD_PATH, 0755, true)) {
            error_log('Failed to create upload directory: ' . ADVERT_UPLOAD_PATH);
            return false;
        }
    }
    
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
        return false;
    }
}

// Redirect back
$redirectUrl = $_POST['redirect_url'] ?? '';

if (empty($redirectUrl)) {
    $businessId = (int)($_POST['business_id'] ?? 0);
    if ($businessId > 0) {
        $redirectUrl = '/admin/edit-business-admin.php?id=' . $businessId;
    } else {
        $redirectUrl = '/admin/my-businesses-admin.php';
    }
} else {
    $parsedUrl = parse_url($redirectUrl);
    if ($parsedUrl && isset($parsedUrl['path'])) {
        $redirectUrl = $parsedUrl['path'];
        if (isset($parsedUrl['query'])) {
            $redirectUrl .= '?' . $parsedUrl['query'];
        }
    }
}

if (substr($redirectUrl, 0, 1) !== '/') {
    $redirectUrl = '/' . $redirectUrl;
}

$separator = (strpos($redirectUrl, '?') !== false ? '&' : '?');
if ($message) {
    $redirectUrl .= $separator . 'message=' . urlencode($message);
    $separator = '&';
} else if ($error) {
    $redirectUrl .= $separator . 'error=' . urlencode($error);
    $separator = '&';
}

if (isset($action) && ($action === 'create' || $action === 'update')) {
    $redirectUrl .= $separator . 'tab=advert-graphics';
}

$fullRedirectUrl = baseUrl($redirectUrl);

@file_put_contents(__DIR__ . '/uploads/debug-handle-adverts-root-redirect.txt', 
    date('Y-m-d H:i:s') . "\n" .
    'Redirect URL: ' . $fullRedirectUrl . "\n" .
    'Action: ' . ($action ?? 'none') . "\n" .
    'Message: ' . ($message ?: 'none') . "\n" .
    'Error: ' . ($error ?: 'none')
);

while (ob_get_level() > 0) {
    ob_end_clean();
}

if (headers_sent($file, $line)) {
    error_log("ERROR: Headers already sent in $file on line $line");
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Redirecting...</title></head><body>';
    echo '<script>window.location.href = ' . json_encode($fullRedirectUrl) . ';</script>';
    echo '<p>If you are not redirected, <a href="' . h($fullRedirectUrl) . '">click here</a>.</p>';
    echo '</body></html>';
    exit;
}

header('Location: ' . $fullRedirectUrl, true, 302);
exit;
