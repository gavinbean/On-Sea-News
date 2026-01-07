<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

$db = getDB();
$today = date('Y-m-d');

// Get active advertisements with valid accounts
$stmt = $db->prepare("
    SELECT a.advert_id, a.advert_image, a.advert_url, a.advert_title, a.business_id
    FROM " . TABLE_PREFIX . "advertisements a
    JOIN " . TABLE_PREFIX . "advertiser_accounts ac ON a.account_id = ac.account_id
    WHERE a.is_active = 1
    AND a.start_date <= ?
    AND a.end_date >= ?
    AND ac.balance >= 0
    ORDER BY RAND()
");
$stmt->execute([$today, $today]);
$advertisements = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'advertisements' => $advertisements
]);



