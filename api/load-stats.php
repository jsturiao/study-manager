<?php
require_once __DIR__ . '/../includes/ExamManager.php';

header('Content-Type: application/json');

// Start session to get userId
session_start();

if (!isset($_SESSION['userId'])) {
  http_response_code(401);
  echo json_encode([
    'success' => false,
    'error' => 'User not authenticated'
  ]);
  exit;
}

try {
  $examManager = new ExamManager();
  $stats = $examManager->getAllFileStats($_SESSION['userId']);

  echo json_encode([
    'success' => true,
    'stats' => $stats
  ]);
} catch (Exception $e) {
  error_log('Error in load-stats.php: ' . $e->getMessage());
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'error' => $e->getMessage()
  ]);
}
