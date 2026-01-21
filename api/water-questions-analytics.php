<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('ANALYTICS');

header('Content-Type: application/json');

$fromDate = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$toDate = $_GET['to'] ?? date('Y-m-d');

$db = getDB();

// Get all active water questions
$stmt = $db->query("
    SELECT q.question_id, q.question_text, q.question_type
    FROM " . TABLE_PREFIX . "water_questions q
    WHERE q.is_active = 1
    ORDER BY q.display_order, q.question_id
");
$questions = $stmt->fetchAll();

$results = [];

foreach ($questions as $question) {
    // Get all responses for this question within date range
    $stmt = $db->prepare("
        SELECT response_value, COUNT(*) as count
        FROM " . TABLE_PREFIX . "water_user_responses
        WHERE question_id = ?
        AND DATE(created_at) BETWEEN ? AND ?
        GROUP BY response_value
    ");
    $stmt->execute([$question['question_id'], $fromDate, $toDate]);
    $responses = $stmt->fetchAll();
    
    // Calculate total responses
    $totalResponses = 0;
    $answerCounts = [];
    foreach ($responses as $response) {
        $value = $response['response_value'];
        $count = (int)$response['count'];
        $answerCounts[$value] = $count;
        $totalResponses += $count;
    }
    
    // For checkbox questions, responses might be comma-separated
    if ($question['question_type'] === 'checkbox') {
        // Recalculate for checkbox (each response can have multiple values)
        $stmt = $db->prepare("
            SELECT response_value
            FROM " . TABLE_PREFIX . "water_user_responses
            WHERE question_id = ?
            AND DATE(created_at) BETWEEN ? AND ?
        ");
        $stmt->execute([$question['question_id'], $fromDate, $toDate]);
        $allResponses = $stmt->fetchAll();
        
        $answerCounts = [];
        $totalResponses = 0;
        foreach ($allResponses as $resp) {
            $values = explode(',', $resp['response_value']);
            foreach ($values as $val) {
                $val = trim($val);
                if (!empty($val)) {
                    $answerCounts[$val] = ($answerCounts[$val] ?? 0) + 1;
                    $totalResponses++;
                }
            }
        }
    }
    
    // Get question options to ensure all options are included
    $stmt = $db->prepare("
        SELECT option_value, option_text
        FROM " . TABLE_PREFIX . "water_question_options
        WHERE question_id = ?
        ORDER BY display_order
    ");
    $stmt->execute([$question['question_id']]);
    $options = $stmt->fetchAll();
    
    // Build answer counts with all options
    $completeAnswerCounts = [];
    if (!empty($options)) {
        foreach ($options as $option) {
            $value = $option['option_value'];
            $completeAnswerCounts[$option['option_text']] = $answerCounts[$value] ?? 0;
        }
    } else {
        // If no options (text/textarea), use the actual response values
        foreach ($answerCounts as $value => $count) {
            $completeAnswerCounts[$value] = $count;
        }
    }
    
    $results[] = [
        'question_id' => $question['question_id'],
        'question_text' => $question['question_text'],
        'question_type' => $question['question_type'],
        'total_responses' => $totalResponses,
        'answers' => $completeAnswerCounts
    ];
}

echo json_encode([
    'success' => true,
    'data' => $results
]);

