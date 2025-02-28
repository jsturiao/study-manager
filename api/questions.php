<?php
require_once __DIR__ . '/../includes/Parser.php';

header('Content-Type: application/json');

$parser = new Parser();

// Get the requested file
$requestedFile = isset($_GET['file']) ? $_GET['file'] : null;

if (!$requestedFile) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'No file specified'
    ]);
    exit;
}

$questionsFile = __DIR__ . '/../data/questions/' . basename($requestedFile);

try {
    if (!file_exists($questionsFile)) {
        throw new Exception('Questions file not found: ' . basename($requestedFile));
    }
    
    $questions = $parser->parseFile($questionsFile);
    echo json_encode([
        'success' => true,
        'data' => $questions,
        'file' => basename($requestedFile)
    ]);
} catch (Exception $e) {
    error_log('Error in questions.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}