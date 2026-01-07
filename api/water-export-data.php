<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('ANALYTICS');

header('Content-Type: application/json');

$fromDate = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$toDate = $_GET['to'] ?? date('Y-m-d');

$db = getDB();

// Get water availability data for the date range with addresses and coordinates
// Use stored coordinates from water_availability table, not current user profile coordinates
$stmt = $db->prepare("
    SELECT 
        w.report_date,
        w.has_water,
        w.latitude,
        w.longitude,
        u.street_number,
        u.street_name,
        u.suburb,
        u.town
    FROM " . TABLE_PREFIX . "water_availability w
    JOIN " . TABLE_PREFIX . "users u ON w.user_id = u.user_id
    WHERE w.report_date BETWEEN ? AND ?
    AND w.latitude IS NOT NULL
    AND w.longitude IS NOT NULL
    ORDER BY w.report_date DESC, u.town, u.street_name, u.street_number
");
$stmt->execute([$fromDate, $toDate]);
$reports = $stmt->fetchAll();

// Build address strings from components for each report
foreach ($reports as &$report) {
    $addressParts = [];
    if (!empty($report['street_number'])) $addressParts[] = $report['street_number'];
    if (!empty($report['street_name'])) $addressParts[] = $report['street_name'];
    if (!empty($report['suburb'])) $addressParts[] = $report['suburb'];
    if (!empty($report['town'])) $addressParts[] = $report['town'];
    $report['address'] = implode(', ', $addressParts) ?: 'Address not provided';
}
unset($report);

echo json_encode([
    'success' => true,
    'data' => $reports,
    'from_date' => $fromDate,
    'to_date' => $toDate
]);


