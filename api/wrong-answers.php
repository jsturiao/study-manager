<?php
require_once __DIR__ . '/../includes/ExamManager.php';

header('Content-Type: application/json');

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
	$fileId = isset($_GET['file']) ? $_GET['file'] : null;

	// Get wrong answers for specific file or all files
	$wrongAnswers = $examManager->getWrongAnswers($_SESSION['userId'], $fileId);

	echo json_encode([
		'success' => true,
		'data' => $wrongAnswers
	]);
} catch (Exception $e) {
	error_log('Error in wrong-answers.php: ' . $e->getMessage());
	http_response_code(500);
	echo json_encode([
		'success' => false,
		'error' => $e->getMessage()
	]);
}
