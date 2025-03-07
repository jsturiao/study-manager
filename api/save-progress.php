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
$rawData = $_POST['answers'];
error_log("Raw POST data received: " . $rawData);

$data = json_decode($rawData, true);
error_log("Decoded data: " . print_r($data, true));

if (
	!$data ||
	!isset($data['questionId']) ||
	!isset($data['answer']) ||
	!isset($data['file']) ||
	!isset($data['correct']) ||
	!isset($data['correctAnswer'])
) {
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

	// Ensure answers array exists
	if (!isset($progress['answers']) || !is_array($progress['answers'])) {
		$progress['answers'] = [];
	}

	// Convert questionId to integer and ensure it's valid
	$questionNumber = intval($data['questionId']);
	if ($questionNumber <= 0) {
		throw new Exception("Invalid question number: " . $data['questionId']);
	}

	error_log("Processing question number: " . $questionNumber);

	// Update the specific question's answer with validated data
	$progress['answers'][$questionNumber] = [
		'questionNumber' => $questionNumber,
		'examFile' => $data['file'],
		'answer' => $data['answer'],
		'correctAnswer' => $data['correctAnswer'],
		'correct' => (bool)$data['correct'],
		'answered' => true,
		'timestamp' => time()
	];

	error_log("Progress structure before save: " . print_r($progress, true));

	$stats = $examManager->saveProgress(
		$_SESSION['userId'],
		$data['file'],
		$progress['answers']
	);

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
