<?php
require_once __DIR__ . '/../includes/Parser.php';

header('Content-Type: application/json');

$parser = new Parser('data/cache'); // Relativo à pasta /api

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

// Log para debug
error_log("Requested file: " . $requestedFile);

// Verificar se o arquivo existe em possíveis locais
$locations = [
    __DIR__ . '/../data/questions/' . basename($requestedFile),  // api/data/questions/
    __DIR__ . '/data/questions/' . basename($requestedFile),     // api/data/questions/ (alternativo)
];

$questionsFile = null;
foreach ($locations as $location) {
    error_log("Checking location: " . $location);
    if (file_exists($location)) {
        $questionsFile = $location;
        error_log("File found at: " . $questionsFile);
        break;
    }
}

try {
    if (!$questionsFile) {
        error_log("File not found in any location");
        error_log("Searched in:");
        foreach ($locations as $location) {
            error_log("- " . $location);
        }
        throw new Exception("Questions file not found: " . basename($requestedFile));
    }

    error_log("Attempting to parse file: " . $questionsFile);

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
