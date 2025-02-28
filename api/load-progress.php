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

// Get file parameter
$file = isset($_GET['file']) ? $_GET['file'] : null;

if (!$file) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'No file specified'
    ]);
    exit;
}

try {
    $examManager = new ExamManager();
    $progress = $examManager->loadProgress($_SESSION['userId'], $file);
    
    if ($progress) {
        echo json_encode([
            'success' => true,
            'data' => $progress
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'data' => null
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}