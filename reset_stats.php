<?php
require_once __DIR__ . '/includes/ExamManager.php';

$examManager = new ExamManager();
$examManager->reinitializeAllStats();

echo json_encode(['success' => true, 'message' => 'Stats reinitialized']);