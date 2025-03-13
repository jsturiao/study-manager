<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_log("Starting save-progress.php");

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
error_log("POST data received: " . print_r($_POST, true));

if (!isset($_POST['answers'])) {
	error_log("No 'answers' found in POST data");
	http_response_code(400);
	echo json_encode([
		'success' => false,
		'error' => 'No answer data provided'
	]);
	exit;
}

$rawData = $_POST['answers'];
error_log("Raw answer data: " . $rawData);

try {
    $data = json_decode($_POST['answers'], true);
    error_log("Decoded answer data: " . print_r($data, true));

    if (!isset($data['file'])) {
        throw new Exception("File not specified in answer data");
    }

    $examManager = new ExamManager();
    $stats = $examManager->saveProgress(
        $_SESSION['userId'],
        $data['file'],  // Usando file do JSON decodificado
        $data
    );

    error_log("Progress saved successfully, stats: " . print_r($stats, true));

	$response = [
		'success' => true,
		'message' => 'Progress saved successfully',
		'stats' => $stats
	];

	error_log("Sending response: " . print_r($response, true));
	echo json_encode($response);
} catch (Exception $e) {
	error_log('Error in save-progress.php: ' . $e->getMessage());
	http_response_code(500);
	echo json_encode([
		'success' => false,
		'error' => $e->getMessage()
	]);
}
