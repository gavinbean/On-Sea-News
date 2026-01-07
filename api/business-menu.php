<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

$db = getDB();

// Get all categories with their businesses
$stmt = $db->query("
    SELECT c.category_id, c.category_name, c.category_slug, c.display_order
    FROM " . TABLE_PREFIX . "business_categories c
    ORDER BY c.display_order, c.category_name
");
$categories = $stmt->fetchAll();

// Get businesses for each category (only approved)
foreach ($categories as &$category) {
    $stmt = $db->prepare("
        SELECT business_id, business_name, has_paid_subscription
        FROM " . TABLE_PREFIX . "businesses
        WHERE category_id = ? AND is_approved = 1
        ORDER BY business_name
    ");
    $stmt->execute([$category['category_id']]);
    $category['businesses'] = $stmt->fetchAll();
}

echo json_encode([
    'success' => true,
    'categories' => $categories
]);


