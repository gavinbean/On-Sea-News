<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('ANALYTICS');

header('Content-Type: application/json');

$fromDate = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$toDate = $_GET['to'] ?? date('Y-m-d');
$period = $_GET['period'] ?? 'daily';

$db = getDB();

// Get availability data for the date range
$stmt = $db->prepare("
    SELECT 
        SUM(CASE WHEN has_water = 1 THEN 1 ELSE 0 END) as has_water,
        SUM(CASE WHEN has_water = 2 THEN 1 ELSE 0 END) as intermittent_water,
        SUM(CASE WHEN has_water = 0 THEN 1 ELSE 0 END) as no_water,
        COUNT(DISTINCT user_id) as total_respondents
    FROM " . TABLE_PREFIX . "water_availability
    WHERE report_date BETWEEN ? AND ?
");
$stmt->execute([$fromDate, $toDate]);
$availabilityData = $stmt->fetch();

// Get total registered users (users who have accepted water terms)
$stmt = $db->query("
    SELECT COUNT(DISTINCT user_id) as total
    FROM " . TABLE_PREFIX . "water_user_responses wur
    JOIN " . TABLE_PREFIX . "water_questions wq ON wur.question_id = wq.question_id
    WHERE wq.terms_link IS NOT NULL AND wq.terms_link != ''
    AND wur.response_value = '1'
");
$totalRegistered = $stmt->fetch()['total'] ?? 0;

// If no registered users found, get total active users as fallback
if ($totalRegistered == 0) {
    $stmt = $db->query("
        SELECT COUNT(*) as total
        FROM " . TABLE_PREFIX . "users
        WHERE is_active = 1
    ");
    $totalRegistered = $stmt->fetch()['total'] ?? 0;
}

echo json_encode([
    'success' => true,
    'data' => [
        'has_water' => (int)($availabilityData['has_water'] ?? 0),
        'intermittent_water' => (int)($availabilityData['intermittent_water'] ?? 0),
        'no_water' => (int)($availabilityData['no_water'] ?? 0),
        'total_respondents' => (int)($availabilityData['total_respondents'] ?? 0),
        'total_registered' => (int)$totalRegistered,
        'from_date' => $fromDate,
        'to_date' => $toDate,
        'period' => $period
    ]
]);


