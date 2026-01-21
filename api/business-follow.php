<?php
/**
 * API endpoint for following/unfollowing businesses
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    $userId = getCurrentUserId();
    if (!$userId) {
        throw new Exception('User must be logged in');
    }
    
    if ($method === 'POST') {
        $businessId = (int)($_POST['business_id'] ?? 0);
        
        if (!$businessId) {
            throw new Exception('Business ID required');
        }
        
        // Verify business exists
        $stmt = $db->prepare("SELECT business_id FROM " . TABLE_PREFIX . "businesses WHERE business_id = ?");
        $stmt->execute([$businessId]);
        if (!$stmt->fetch()) {
            throw new Exception('Business not found');
        }
        
        if ($action === 'follow') {
            // Check if already following
            $stmt = $db->prepare("SELECT follow_id FROM " . TABLE_PREFIX . "business_follows WHERE user_id = ? AND business_id = ?");
            $stmt->execute([$userId, $businessId]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => true, 'message' => 'Already following this business', 'following' => true]);
                exit;
            }
            
            // Add follow
            $stmt = $db->prepare("INSERT INTO " . TABLE_PREFIX . "business_follows (user_id, business_id) VALUES (?, ?)");
            $stmt->execute([$userId, $businessId]);
            
            echo json_encode(['success' => true, 'message' => 'Now following this business', 'following' => true]);
        } elseif ($action === 'unfollow') {
            // Remove follow
            $stmt = $db->prepare("DELETE FROM " . TABLE_PREFIX . "business_follows WHERE user_id = ? AND business_id = ?");
            $stmt->execute([$userId, $businessId]);
            
            echo json_encode(['success' => true, 'message' => 'Unfollowed this business', 'following' => false]);
        } else {
            throw new Exception('Invalid action');
        }
    } elseif ($method === 'GET') {
        // Check if user is following a business
        $businessId = (int)($_GET['business_id'] ?? 0);
        
        if (!$businessId) {
            throw new Exception('Business ID required');
        }
        
        $stmt = $db->prepare("SELECT follow_id FROM " . TABLE_PREFIX . "business_follows WHERE user_id = ? AND business_id = ?");
        $stmt->execute([$userId, $businessId]);
        $isFollowing = (bool)$stmt->fetch();
        
        echo json_encode(['success' => true, 'following' => $isFollowing]);
    } else {
        throw new Exception('Invalid method');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
