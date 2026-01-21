<?php
// Ultra-simple test - no includes, no dependencies
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => true, 'message' => 'Simple test works', 'timestamp' => date('Y-m-d H:i:s')]);
exit;
