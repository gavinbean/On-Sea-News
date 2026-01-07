<?php
require_once 'includes/functions.php';

$advertId = $_GET['id'] ?? 0;
$url = $_GET['url'] ?? '#';

if ($advertId) {
    $db = getDB();
    
    // Record click
    $stmt = $db->prepare("
        INSERT INTO " . TABLE_PREFIX . "advert_clicks 
        (advert_id, ip_address, user_agent)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([
        $advertId,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
}

// Redirect to advertisement URL
header('Location: ' . $url);
exit;



