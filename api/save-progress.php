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

// Get POST data
$data = json_decode($_POST['answers'], true);

if (!$data || !isset($data['questionId']) || !isset($data['answer']) || !isset($data['file'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid data provided'
    ]);
    exit;
}

try {
    $examManager = new ExamManager();
    
    // Load current progress
    $progress = $examManager->loadProgress($_SESSION['userId'], $data['file']) ?? [
        'userId' => $_SESSION['userId'],
        'fileId' => $data['file'],
        'answers' => [],
        'timestamp' => time()
    ];
    
    // Update the specific question's answer
    $progress['answers'][$data['questionId']] = [
        'answer' => $data['answer'],
        'correct' => $data['correct'],
        'answered' => true,
        'timestamp' => time()
    ];
    
    // Save progress and get updated stats
    $stats = $examManager->saveProgress(
        $_SESSION['userId'],
        $data['file'],
        $progress['answers']
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Progress saved successfully',
        'stats' => $stats
    ]);
} catch (Exception $e) {
    error_log('Error in save-progress.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}