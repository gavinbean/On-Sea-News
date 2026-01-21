<?php
header('Content-Type: application/json');
// Prevent caching on iOS and other browsers
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../includes/functions.php';

$date = $_GET['date'] ?? date('Y-m-d');
$db = getDB();

$stmt = $db->prepare("
    SELECT 
        w.water_id,
        w.user_id,
        w.report_date,
        w.has_water,
        w.notes,
        w.reported_at,
        w.latitude,
        w.longitude,
        u.name, 
        u.surname, 
        u.street_number, 
        u.street_name, 
        u.suburb, 
        u.town
    FROM " . TABLE_PREFIX . "water_availability w
    LEFT JOIN " . TABLE_PREFIX . "users u ON w.user_id = u.user_id
    WHERE w.report_date = ?
    AND w.latitude IS NOT NULL
    AND w.longitude IS NOT NULL
    ORDER BY w.reported_at DESC
");
$stmt->execute([$date]);
$reports = $stmt->fetchAll();

// Build address strings from components for each report
foreach ($reports as &$report) {
    $addressParts = [];
    if (!empty($report['street_number'])) $addressParts[] = $report['street_number'];
    if (!empty($report['street_name'])) $addressParts[] = $report['street_name'];
    if (!empty($report['suburb'])) $addressParts[] = $report['suburb'];
    if (!empty($report['town'])) $addressParts[] = $report['town'];
    $report['address'] = implode(', ', $addressParts) ?: 'Address not provided';
    // Set name to 'Imported Data' or 'N/A' if user_id is NULL
    if (empty($report['user_id'])) {
        $report['name'] = 'Imported Data';
        $report['surname'] = '';
    }
}
unset($report);

echo json_encode([
    'success' => true,
    'reports' => $reports,
    'date' => $date
]);

