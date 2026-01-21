<?php
require_once __DIR__ . '/includes/functions.php';
requireLogin();

header('Content-Type: application/json');

$db = getDB();
$userId = getCurrentUserId();
$isElectricityAdmin = hasRole('ELECTRICITY_ADMIN') || hasRole('ADMIN');

$action = $_POST['action'] ?? '';

if ($action === 'create') {
    // Get user details
    $user = getCurrentUser();
    
    if (empty($user['latitude']) || empty($user['longitude'])) {
        echo json_encode(['success' => false, 'message' => 'Please update your profile with a valid address that includes location information.']);
        exit;
    }
    
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if (empty($description)) {
        echo json_encode(['success' => false, 'message' => 'Please provide a description of the problem.']);
        exit;
    }
    
    // Insert issue
    $stmt = $db->prepare("
        INSERT INTO " . TABLE_PREFIX . "electricity_issues 
        (user_id, name, address, latitude, longitude, description, status)
        VALUES (?, ?, ?, ?, ?, ?, 'New Issue')
    ");
    $stmt->execute([
        $userId,
        $name,
        $address,
        $user['latitude'],
        $user['longitude'],
        $description
    ]);
    
    $issueId = $db->lastInsertId();
    
    // Send email to all electricity admins
    require_once __DIR__ . '/includes/email_electricity.php';
    sendNewElectricityIssueEmail($issueId, $name, $address, $description);
    
    echo json_encode(['success' => true, 'message' => 'Issue submitted successfully!', 'issue_id' => $issueId]);
    
} elseif ($action === 'update_status' && $isElectricityAdmin) {
    $issueId = (int)($_POST['issue_id'] ?? 0);
    $status = trim($_POST['status'] ?? '');
    $comment = trim($_POST['comment'] ?? '');
    $referenceNumber = trim($_POST['reference_number'] ?? '');
    
    if ($issueId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid issue ID']);
        exit;
    }
    
    $validStatuses = ['New Issue', 'Issue Received', 'Issue Updated', 'Issue Resolved', 'Closed'];
    if (!in_array($status, $validStatuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }
    
    // Get current issue
    $stmt = $db->prepare("SELECT * FROM " . TABLE_PREFIX . "electricity_issues WHERE issue_id = ?");
    $stmt->execute([$issueId]);
    $issue = $stmt->fetch();
    
    if (!$issue) {
        echo json_encode(['success' => false, 'message' => 'Issue not found']);
        exit;
    }
    
    // Check if reference_number column exists (backward compatibility)
    $referenceNumberColumnExists = false;
    try {
        $testStmt = $db->query("SELECT reference_number FROM " . TABLE_PREFIX . "electricity_issues LIMIT 1");
        $referenceNumberColumnExists = true;
    } catch (Exception $e) {
        $referenceNumberColumnExists = false;
    }
    
    // Update status and reference number
    $closedAt = ($status === 'Closed' && $issue['status'] !== 'Closed') ? date('Y-m-d H:i:s') : $issue['closed_at'];
    
    if ($referenceNumberColumnExists) {
        $stmt = $db->prepare("
            UPDATE " . TABLE_PREFIX . "electricity_issues 
            SET status = ?, reference_number = ?, updated_at = NOW(), closed_at = ?
            WHERE issue_id = ?
        ");
        $stmt->execute([$status, $referenceNumber ?: null, $closedAt, $issueId]);
    } else {
        $stmt = $db->prepare("
            UPDATE " . TABLE_PREFIX . "electricity_issues 
            SET status = ?, updated_at = NOW(), closed_at = ?
            WHERE issue_id = ?
        ");
        $stmt->execute([$status, $closedAt, $issueId]);
    }
    
    // Add comment if provided
    if (!empty($comment)) {
        $stmt = $db->prepare("
            INSERT INTO " . TABLE_PREFIX . "electricity_issue_comments 
            (issue_id, user_id, comment_text)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$issueId, $userId, $comment]);
    }
    
    // Send email notifications
    require_once __DIR__ . '/includes/email_electricity.php';
    sendElectricityIssueUpdateEmail($issueId, $status, $comment);
    
    echo json_encode(['success' => true, 'message' => 'Issue updated successfully!']);
    
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action or insufficient permissions']);
}
