<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$businessId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($businessId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid business ID']);
    exit;
}

$db = getDB();

// Get business details (only approved businesses)
$stmt = $db->prepare("
    SELECT b.*, c.category_name
    FROM " . TABLE_PREFIX . "businesses b
    LEFT JOIN " . TABLE_PREFIX . "business_categories c ON b.category_id = c.category_id
    WHERE b.business_id = ? AND b.is_approved = 1
");
$stmt->execute([$businessId]);
$business = $stmt->fetch();

if (!$business) {
    echo json_encode(['success' => false, 'message' => 'Business not found']);
    exit;
}

// Build address from components if address field is empty but components exist
if (empty($business['address']) || trim($business['address']) === '') {
    $addressParts = [];
    if (!empty($business['street_number'])) $addressParts[] = $business['street_number'];
    if (!empty($business['street_name'])) $addressParts[] = $business['street_name'];
    if (!empty($business['suburb'])) $addressParts[] = $business['suburb'];
    if (!empty($business['town'])) $addressParts[] = $business['town'];
    if (!empty($addressParts)) {
        $business['address'] = implode(', ', $addressParts);
    }
}

echo json_encode(['success' => true, 'business' => $business]);

