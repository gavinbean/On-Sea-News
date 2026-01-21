<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('ANALYTICS');

header('Content-Type: application/json');
// Prevent caching (helps ensure filters always refresh correctly on mobile/edge cases)
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$db = getDB();
$fromDate = $_GET['from'] ?? null;
$toDate = $_GET['to'] ?? null;

if (!$fromDate || !$toDate) {
    echo json_encode(['error' => 'From and to dates are required']);
    exit;
}

try {
    // Get totals
    $stmt = $db->prepare("
        SELECT 
            SUM(litres_delivered) as total_litres,
            SUM(price) as total_price
        FROM " . TABLE_PREFIX . "water_deliveries
        WHERE DATE(date_delivered) BETWEEN ? AND ?
    ");
    $stmt->execute([$fromDate, $toDate]);
    $totals = $stmt->fetch();
    
    // Get data by company
    $stmt = $db->prepare("
        SELECT 
            COALESCE(wdc.company_name, wd.company_name_other, 'Unknown') as company_name,
            SUM(wd.litres_delivered) as total_litres,
            SUM(wd.price) as total_price
        FROM " . TABLE_PREFIX . "water_deliveries wd
        LEFT JOIN " . TABLE_PREFIX . "water_delivery_companies wdc ON wd.company_id = wdc.company_id
        WHERE DATE(wd.date_delivered) BETWEEN ? AND ?
        GROUP BY COALESCE(wdc.company_name, wd.company_name_other, 'Unknown')
        ORDER BY total_litres DESC
    ");
    $stmt->execute([$fromDate, $toDate]);
    $companies = $stmt->fetchAll();
    
    // Get individual delivery records with user information
    $stmt = $db->prepare("
        SELECT 
            wd.date_delivered,
            wd.litres_delivered,
            wd.price,
            u.name,
            u.surname,
            u.telephone,
            u.street_number,
            u.street_name,
            u.suburb,
            u.town
        FROM " . TABLE_PREFIX . "water_deliveries wd
        LEFT JOIN " . TABLE_PREFIX . "users u ON wd.user_id = u.user_id
        WHERE DATE(wd.date_delivered) BETWEEN ? AND ?
        ORDER BY wd.date_delivered DESC, wd.created_at DESC
    ");
    $stmt->execute([$fromDate, $toDate]);
    $records = $stmt->fetchAll();
    
    // Build address strings for each record
    foreach ($records as &$record) {
        $addressParts = [];
        if (!empty($record['street_number'])) $addressParts[] = $record['street_number'];
        if (!empty($record['street_name'])) $addressParts[] = $record['street_name'];
        if (!empty($record['suburb'])) $addressParts[] = $record['suburb'];
        if (!empty($record['town'])) $addressParts[] = $record['town'];
        $record['address'] = implode(', ', $addressParts) ?: 'Address not provided';
    }
    unset($record);
    
    echo json_encode([
        'total_litres' => $totals['total_litres'] ?? 0,
        'total_price' => $totals['total_price'] ?? 0,
        'companies' => $companies,
        'records' => $records
    ]);
} catch (Exception $e) {
    error_log("Water deliveries analytics error: " . $e->getMessage());
    echo json_encode(['error' => 'Error loading data: ' . $e->getMessage()]);
}
