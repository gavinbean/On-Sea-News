<?php
require_once __DIR__ . '/../includes/functions.php';
requireAnyRole(['ADMIN', 'ANALYTICS']);

header('Content-Type: application/json');

$db = getDB();

// Get filter parameters
$fromDate = $_GET['from_date'] ?? date('Y-m-d', strtotime('-30 days'));
$toDate = $_GET['to_date'] ?? date('Y-m-d');
$reportId = $_GET['report_id'] ?? null;

try {
    if ($reportId) {
        // Get single report
        $stmt = $db->prepare("
            SELECT tr.*, 
                   u.name as reported_by_name,
                   u.surname as reported_by_surname
            FROM " . TABLE_PREFIX . "tanker_reports tr
            JOIN " . TABLE_PREFIX . "users u ON tr.reported_by_user_id = u.user_id
            WHERE tr.report_id = ?
        ");
        $stmt->execute([$reportId]);
        $report = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($report) {
            $report['reported_by_name'] = $report['reported_by_name'] . ' ' . $report['reported_by_surname'];
            echo json_encode([
                'success' => true,
                'report' => $report
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Report not found'
            ]);
        }
    } else {
        // Get filtered reports
        $stmt = $db->prepare("
            SELECT tr.*, 
                   u.name as reported_by_name,
                   u.surname as reported_by_surname
            FROM " . TABLE_PREFIX . "tanker_reports tr
            JOIN " . TABLE_PREFIX . "users u ON tr.reported_by_user_id = u.user_id
            WHERE DATE(tr.reported_at) >= ? AND DATE(tr.reported_at) <= ?
            ORDER BY tr.reported_at DESC
        ");
        $stmt->execute([$fromDate, $toDate]);
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format data
        foreach ($reports as &$report) {
            $report['reported_by_name'] = $report['reported_by_name'] . ' ' . $report['reported_by_surname'];
            $report['latitude'] = (float)$report['latitude'];
            $report['longitude'] = (float)$report['longitude'];
        }
        
        echo json_encode([
            'success' => true,
            'reports' => $reports
        ]);
    }
} catch (PDOException $e) {
    error_log("Tanker reports API error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching reports'
    ]);
}
