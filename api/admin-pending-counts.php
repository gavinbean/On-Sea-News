<?php
/**
 * API endpoint for admin pending counts
 * Returns counts of pending business reviews, contact messages, and advert reviews
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

// Only allow ADMIN users
if (!isLoggedIn() || !hasRole('ADMIN')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Access denied'
    ]);
    exit;
}

$db = getDB();

try {
    // Count pending businesses (is_approved = 0)
    $stmt = $db->query("
        SELECT COUNT(*) as count
        FROM " . TABLE_PREFIX . "businesses
        WHERE is_approved = 0
    ");
    $pendingBusinesses = $stmt->fetch()['count'];
    
    // Count new contact messages (status = 'new')
    $stmt = $db->query("
        SELECT COUNT(*) as count
        FROM " . TABLE_PREFIX . "contact_queries
        WHERE status = 'new'
    ");
    $newContactMessages = $stmt->fetch()['count'];
    
    // Count pending adverts (approval_status = 'pending')
    $stmt = $db->query("
        SELECT COUNT(*) as count
        FROM " . TABLE_PREFIX . "business_adverts
        WHERE approval_status = 'pending'
    ");
    $pendingAdverts = $stmt->fetch()['count'];
    
    echo json_encode([
        'success' => true,
        'pendingBusinesses' => (int)$pendingBusinesses,
        'newContactMessages' => (int)$newContactMessages,
        'pendingAdverts' => (int)$pendingAdverts
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error loading pending counts: ' . $e->getMessage()
    ]);
}
