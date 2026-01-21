<?php
/**
 * API endpoint for managing business adverts
 * Handles CRUD operations for Basic, Timed, and Events adverts
 */

require_once __DIR__ . '/../includes/functions.php';
requireAnyRole(['ADMIN', 'ADVERTISER']);

header('Content-Type: application/json');

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            // List adverts for a business
            $businessId = (int)($_GET['business_id'] ?? 0);
            if (!$businessId) {
                throw new Exception('Business ID required');
            }
            
            // Verify business ownership (unless admin)
            $userId = getCurrentUserId();
            if (!hasRole('ADMIN')) {
                $stmt = $db->prepare("SELECT user_id FROM " . TABLE_PREFIX . "businesses WHERE business_id = ?");
                $stmt->execute([$businessId]);
                $business = $stmt->fetch();
                if (!$business || $business['user_id'] != $userId) {
                    throw new Exception('Unauthorized');
                }
            }
            
            $stmt = $db->prepare("
                SELECT *, approval_status, rejection_reason FROM " . TABLE_PREFIX . "business_adverts 
                WHERE business_id = ? 
                ORDER BY created_at DESC
            ");
            $stmt->execute([$businessId]);
            $adverts = $stmt->fetchAll();
            
            // Normalize paths: replace old 'uploads/adverts/' with 'uploads/graphics/'
            foreach ($adverts as &$advert) {
                if (!empty($advert['banner_image'])) {
                    $advert['banner_image'] = str_replace('uploads/adverts/', 'uploads/graphics/', $advert['banner_image']);
                }
                if (!empty($advert['display_image'])) {
                    $advert['display_image'] = str_replace('uploads/adverts/', 'uploads/graphics/', $advert['display_image']);
                }
            }
            unset($advert);
            
            echo json_encode(['success' => true, 'adverts' => $adverts]);
            break;
            
        case 'get':
            // Get single advert
            $advertId = (int)($_GET['advert_id'] ?? 0);
            if (!$advertId) {
                throw new Exception('Advert ID required');
            }
            
            $stmt = $db->prepare("
                SELECT a.*, b.user_id as business_user_id 
                FROM " . TABLE_PREFIX . "business_adverts a
                JOIN " . TABLE_PREFIX . "businesses b ON a.business_id = b.business_id
                WHERE a.advert_id = ?
            ");
            $stmt->execute([$advertId]);
            $advert = $stmt->fetch();
            
            if (!$advert) {
                throw new Exception('Advert not found');
            }
            
            // Normalize paths: replace old 'uploads/adverts/' with 'uploads/graphics/'
            if (!empty($advert['banner_image'])) {
                $advert['banner_image'] = str_replace('uploads/adverts/', 'uploads/graphics/', $advert['banner_image']);
            }
            if (!empty($advert['display_image'])) {
                $advert['display_image'] = str_replace('uploads/adverts/', 'uploads/graphics/', $advert['display_image']);
            }
            
            // Verify ownership (unless admin)
            $userId = getCurrentUserId();
            if (!hasRole('ADMIN') && $advert['business_user_id'] != $userId) {
                throw new Exception('Unauthorized');
            }
            
            echo json_encode(['success' => true, 'advert' => $advert]);
            break;
            
        case 'events':
            // Get events for calendar (all events, admin only for now)
            if (!hasRole('ADMIN')) {
                throw new Exception('Unauthorized');
            }
            
            $stmt = $db->prepare("
                SELECT a.advert_id, a.event_date, a.event_title, a.banner_image, a.display_image,
                       b.business_name, b.business_id
                FROM " . TABLE_PREFIX . "business_adverts a
                JOIN " . TABLE_PREFIX . "businesses b ON a.business_id = b.business_id
                WHERE b.pricing_status = 'events' 
                  AND a.event_date IS NOT NULL
                  AND a.is_active = 1
                ORDER BY a.event_date ASC
            ");
            $stmt->execute();
            $events = $stmt->fetchAll();
            
            // Normalize paths: replace old 'uploads/adverts/' with 'uploads/graphics/'
            foreach ($events as &$event) {
                if (!empty($event['banner_image'])) {
                    $event['banner_image'] = str_replace('uploads/adverts/', 'uploads/graphics/', $event['banner_image']);
                }
                if (!empty($event['display_image'])) {
                    $event['display_image'] = str_replace('uploads/adverts/', 'uploads/graphics/', $event['display_image']);
                }
            }
            unset($event);
            
            echo json_encode(['success' => true, 'events' => $events]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
