<?php
// Include required files
require_once __DIR__ . '/includes/Parser.php';
require_once __DIR__ . '/includes/ExamManager.php';

// Initialize session for user tracking
session_start();
if (!isset($_SESSION['userId'])) {
    // Usando um ID fixo para manter histórico e estatísticas
    $_SESSION['userId'] = 'user_67c20b49f1aa3';  // ID fixo que já possui histórico
}

$parser = new Parser('data/cache'); // Relativo à pasta /api
$examManager = new ExamManager();

// Get question sets and their stats
$questionSets = [];
$questionDir = __DIR__ . '/api/data/questions';

// Using existing question directory
if (!is_dir($questionDir)) {
    error_log("Warning: Question directory not found: $questionDir");
}

// Get file stats
$fileStats = $examManager->getAllFileStats($_SESSION['userId']);

// Debug: Log the loaded stats
error_log('Loaded file stats for user ' . $_SESSION['userId'] . ': ' . print_r($fileStats, true));

// Count total files found
$totalFiles = count(glob($questionDir . '/*.md'));
error_log('Total .md files found: ' . $totalFiles);

if (is_dir($questionDir)) {
	$files = glob($questionDir . '/*.md');

	// Sort files based on numeric part
	usort($files, function ($a, $b) {
		$numA = (int) preg_replace('/^.*?(\d+)\.md$/', '$1', $a);
		$numB = (int) preg_replace('/^.*?(\d+)\.md$/', '$1', $b);
		return $numA - $numB;
	});

	foreach ($files as $file) {
		$filename = basename($file);
		// Format title: "Practice Exam 1" instead of "practice exam 1"
		$number = (int) preg_replace('/^.*?(\d+)\.md$/', '$1', $filename);
		$title = "Practice Exam {$number}";

		$stats = isset($fileStats[$filename]) ? $fileStats[$filename] : [
			'total' => 0,
			'answered' => 0,
			'correct' => 0,
			'percentage' => 0
		];

		$questionSets[] = [
			'file' => $filename,
			'title' => $title,
			'path' => $file,
			'stats' => $stats
		];
	}
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>AWS Practice Exam Manager</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
	<style>
		.question-card {
		    margin-bottom: 1.5rem;
		    padding: 1.25rem;
		    border: 1px solid #e0e0e0;
		    border-radius: 8px;
		    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
		    background-color: #fff;
		}
		
		.margem{
			margin-left: 5%;
			margin-right: 5%;
		}

		.option-label {
		    display: flex;
		    align-items: center;
		    padding: 0.75rem 1rem;
		    margin: 0.35rem 0;
		    border: 1px solid #e0e0e0;
		    border-radius: 6px;
		    cursor: pointer;
		    transition: all 0.2s ease;
		    font-size: 0.95rem;
		    line-height: 1.4;
		}
		
		.option-label input[type="radio"],
		.option-label input[type="checkbox"] {
		    margin-right: 0.75rem;
		}
		
		.option-label:hover {
		    background-color: #f8f9fa;
		    border-color: #0d6efd;
		    transform: translateX(4px);
		}
		
		.selected {
		    background-color: #ebf5ff;
		    border-color: #0d6efd;
		    box-shadow: 0 2px 4px rgba(13,110,253,0.15);
		}

		.answer-status {
		    margin: 1rem 0;
		    padding: 1rem;
		    border-radius: 6px;
		    display: none;
		    font-weight: 500;
		    border-left: 4px solid;
		}
		
		.answer-status.correct {
		    background-color: #edfcf3;
		    border-color: #28a745;
		    color: #0d6832;
		}
		
		.answer-status.incorrect {
		    background-color: #fff5f5;
		    border-color: #dc3545;
		    color: #a52834;
		}
		
		.explanation {
		    margin: 1rem 0;
		    padding: 1rem 1.25rem;
		    background-color: #f8f9fa;
		    border-radius: 6px;
		    display: none;
		    border-left: 4px solid #6c757d;
		    font-size: 0.95rem;
		    line-height: 1.5;
		    color: #2c3e50;
		}
		
		.question-text {
		    font-size: 1rem;
		    line-height: 1.6;
		    color: #2c3e50;
		    margin: 1rem 0 1.5rem;
		    padding: 0.75rem 1rem;
		    background-color: #f8f9fa;
		    border-radius: 6px;
		    border-left: 4px solid #0d6efd;
		}

		.option-correct {
		    background-color: rgba(40, 167, 69, 0.1) !important;
		    border-color: #28a745 !important;
		    position: relative;
		}
		
		.option-correct::after {
		    content: '✓';
		    position: absolute;
		    right: 1rem;
		    color: #28a745;
		    font-weight: bold;
		}
		
		.option-incorrect {
		    background-color: rgba(220, 53, 69, 0.1) !important;
		    border-color: #dc3545 !important;
		    position: relative;
		}
		
		.option-incorrect::after {
		    content: '×';
		    position: absolute;
		    right: 1rem;
		    color: #dc3545;
		    font-weight: bold;
		    font-size: 1.2em;
		}

		.question-set-card {
			cursor: pointer;
			transition: all 0.2s;
			position: relative;
			overflow: hidden;
			font-size: 0.9em;
		}

		.question-set-card:hover {
			transform: translateY(-5px);
			box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
		}

		.question-set-card .card-body {
			padding: 0.5rem;
		}

		.question-set-card .card-header {
			padding: 0.4rem 0.6rem;
		}

		.question-set-card h6.card-title {
			font-size: 0.9rem;
			margin: 0;
		}

		.question-set-card .badge {
			font-size: 0.65rem;
			padding: 0.2rem 0.4rem;
		}

		.question-set-card small {
			font-size: 0.75rem;
		}

		.question-set-stats {
			font-size: 0.85em;
			color: #666;
			margin-top: 8px;
			padding-top: 8px;
			border-top: 1px solid #eee;
		}

		/* Status-based card styles */
		.card-status-not-started {
			border-left: 4px solid #6c757d;
			background-color: #f8f9fa;
		}

		.card-status-in-progress {
			border-left: 4px solid #ffc107;
			background-color: #fff8e1;
		}

		.card-status-completed {
			border-left: 4px solid #28a745;
			background-color: #f1f9f1;
		}

		/* Compact progress bars */
		.progress-mini {
			height: 4px;
			margin-top: 4px;
		}

		.question-set-card .progress {
			height: 6px;
			margin: 4px 0;
			background-color: rgba(0, 0, 0, 0.05);
		}

		.question-status-icon {
		    position: absolute;
		    top: 1rem;
		    right: 1rem;
		    width: 28px;
		    height: 28px;
		    display: flex;
		    align-items: center;
		    justify-content: center;
		    border-radius: 50%;
		    font-size: 1rem;
		}
		
		.status-correct {
		    background-color: #28a745;
		    color: #fff;
		    box-shadow: 0 2px 4px rgba(40, 167, 69, 0.2);
		}
		
		.status-incorrect {
		    background-color: #dc3545;
		    color: #fff;
		    box-shadow: 0 2px 4px rgba(220, 53, 69, 0.2);
		}
		
		.status-unanswered {
		    background-color: #6c757d;
		    color: #fff;
		    opacity: 0.5;
		}
		
		.card-title {
		    font-size: 1.25rem;
		    color: #2c3e50;
		    margin-bottom: 1.5rem;
		    padding-bottom: 1rem;
		    border-bottom: 2px solid #f0f0f0;
		}

		#examContainer {
			display: none;
		}

		#questionSetsContainer {
			display: block;
		}

		.multiple-answer-notice {
		    color: #664d03;
		    background-color: #fff3cd;
		    border-left: 4px solid #ffc107;
		    padding: 1rem 1.25rem;
		    margin: 1rem 0 1.5rem;
		    border-radius: 6px;
		    display: none;
		    font-size: 0.95rem;
		    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
		}
		
		.multiple-answer-notice::before {
		    content: '📝';
		    margin-right: 0.5rem;
		}
		
		.submit-answer {
		    background-color: #28a745;
		    border: none;
		    padding: 0.75rem 2rem;
		    font-weight: 500;
		    text-transform: uppercase;
		    letter-spacing: 0.5px;
		    transition: all 0.2s;
		    box-shadow: 0 2px 4px rgba(40, 167, 69, 0.2);
		}
		
		.submit-answer:hover:not(:disabled) {
		    background-color: #218838;
		    transform: translateY(-2px);
		    box-shadow: 0 4px 8px rgba(40, 167, 69, 0.3);
		}
		
		.submit-answer:disabled {
		    background-color: #6c757d;
		    opacity: 0.65;
		    cursor: not-allowed;
		}
		
		.navigation-buttons {
		    margin-top: 2rem;
		    padding-top: 1.5rem;
		    border-top: 1px solid #e0e0e0;
		}
		
		.navigation-buttons .btn {
		    padding: 0.75rem 1.5rem;
		    font-weight: 500;
		    letter-spacing: 0.5px;
		    transition: all 0.2s;
		}
		
		.navigation-buttons .btn:hover:not(:disabled) {
		    transform: translateY(-2px);
		    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
		}

		/* Wrong answers review styles */
		.wrong-answer-card {
			transition: all 0.2s;
			height: 100%;
		}

		.wrong-answer-card:hover {
			transform: translateY(-3px);
			box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
		}

		.wrong-answer-card .card-header {
			background-color: #f8f9fa;
			border-bottom: 1px solid rgba(0, 0, 0, 0.1);
		}

		.wrong-answer-card .card-body {
			padding: 1rem;
		}

		.wrong-answer-card h3 {
			font-size: 2rem;
			font-weight: 600;
			color: #dc3545;
		}

		#wrongAnswersModal .list-group-item {
			border-left: none;
			border-right: none;
			padding: 1.25rem;
		}

		#wrongAnswersModal .list-group-item:first-child {
			border-top: none;
		}

		#wrongAnswersModal .list-group-item:last-child {
			border-bottom: none;
		}

		/* Modal improvements */
		#wrongAnswersModal .modal-dialog {
		    max-width: 1200px;
		}
		
		#wrongAnswersModal .modal-header {
		    background-color: #f8f9fa;
		    border-bottom: 1px solid rgba(0, 0, 0, 0.1);
		    padding: 0.75rem 1rem;
		}
		
		#wrongAnswersModal .modal-body {
		    padding: 0;
		    max-height: 85vh;
		    overflow-y: auto;
		}
		
		#wrongAnswersModal .modal-content {
		    border: none;
		    border-radius: 8px;
		    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
		}
		
		#wrongAnswersModal .list-group-item {
		    padding: 1rem;
		    margin: 0.75rem;
		    border: 1px solid rgba(0,0,0,0.1);
		    border-radius: 8px;
		    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
		    background-color: #fff;
		}
		
		#wrongAnswersModal .question-header {
		    display: flex;
		    align-items: center;
		    margin-bottom: 1rem;
		    padding-bottom: 0.5rem;
		    border-bottom: 2px solid #e9ecef;
		}
		
		#wrongAnswersModal .question-number {
		    font-size: 0.9rem;
		    color: #6c757d;
		    font-weight: 600;
		    text-transform: uppercase;
		    letter-spacing: 0.5px;
		}
		
		#wrongAnswersModal .question-text {
		    font-size: 0.95rem;
		    line-height: 1.5;
		    color: #2c3e50;
		    background-color: #f8f9fa;
		    padding: 0.75rem 1rem;
		    border-radius: 6px;
		    border-left: 3px solid #007bff;
		    margin-bottom: 1rem;
		}
		
		#wrongAnswersModal .options {
		    margin: 0.5rem 0;
		}
		
		#wrongAnswersModal .option {
		    padding: 0.5rem 0.75rem;
		    margin-bottom: 2px;
		    border-radius: 4px;
		    font-size: 0.9rem;
		    position: relative;
		    transition: background-color 0.15s;
		}
		
		#wrongAnswersModal .option.user-answer {
		    background-color: rgba(220, 53, 69, 0.05);
		    border-left: 3px solid #dc3545;
		    padding-right: 2rem;
		}
		
		#wrongAnswersModal .option.correct-answer {
		    background-color: rgba(40, 167, 69, 0.05);
		    border-left: 3px solid #28a745;
		    padding-right: 2rem;
		}
		
		#wrongAnswersModal .option i {
		    position: absolute;
		    right: 0.5rem;
		    top: 50%;
		    transform: translateY(-50%);
		    font-size: 0.8rem;
		}
		
		#wrongAnswersModal .options-container {
		    background: #fafbfc;
		    border-radius: 6px;
		    padding: 0.75rem;
		    margin-bottom: 1rem;
		}
		
		#wrongAnswersModal .options-title {
		    font-size: 0.8rem;
		    text-transform: uppercase;
		    color: #6c757d;
		    margin-bottom: 0.5rem;
		    font-weight: 600;
		}
		
		#wrongAnswersModal .wrong-answer-detail {
		    display: flex;
		    gap: 1rem;
		    font-size: 0.85rem;
		    padding: 0.75rem;
		    margin-top: 0.75rem;
		    background: #f8f9fa;
		    border-radius: 6px;
		    border: 1px dashed rgba(0,0,0,0.1);
		}
		
		#wrongAnswersModal .answer-group {
		    flex: 1;
		}
		
		#wrongAnswersModal .answer-label {
		    display: block;
		    font-size: 0.75rem;
		    text-transform: uppercase;
		    color: #6c757d;
		    margin-bottom: 0.25rem;
		    font-weight: 600;
		}
		#wrongAnswersModal .wrong-answer-detail {
		    background-color: #f8f9fa;
		    padding: 1.5rem;
		    border-radius: 8px;
		    margin-top: 1.5rem;
		}
		
		#wrongAnswersModal .modal-title {
		    font-size: 1.5rem;
		    color: #2c3e50;
		}
		
		#wrongAnswersModal .modal-header {
		    padding: 1.5rem;
		    background-color: #f8f9fa;
		    border-bottom: 2px solid rgba(0,0,0,0.1);
		}
		
		#wrongAnswersModal h5.question-number {
		    color: #6c757d;
		    font-size: 1.1rem;
		    margin-bottom: 1rem;
		}
	</style>
</head>

<body>
	<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
		<div class="container">
			<a class="navbar-brand" href="javascript:void(0)" onclick="showQuestionSets()">AWS Practice Exam Manager</a>
			<div class="text-white" id="examInfo"></div>
		</div>
	</nav>

	<div class="container">
		<!-- Question Sets View -->
		<div id="questionSetsContainer">
			<!-- Navigation Tabs -->
			<div class="d-flex justify-content-between align-items-center mb-4">
			    <div class="btn-group" role="group">
			        <button type="button" class="btn btn-primary active" data-bs-toggle="tab" data-bs-target="#practice-tab-pane">
			            Practice Sets
			        </button>
			        <button type="button" class="btn btn-primary" data-bs-toggle="tab" data-bs-target="#review-tab-pane">
			            Wrong Answers Review
			            <span class="badge bg-danger ms-1" id="wrongAnswersCount"></span>
			        </button>
			    </div>
			</div>

			<!-- Tab Content -->
			<div class="tab-content">
				<!-- Practice Sets Tab -->
				<div class="tab-pane fade show active" id="practice-tab-pane" role="tabpanel">
					<h2 class="mb-4">Question Sets Progress</h2>

					<!-- Overall Progress Summary -->
					<div class="card mb-4">
						<div class="card-body">
							<div class="row">
								<div class="col-md-4 text-center">
									<h5>Total Progress</h5>
									<div class="h3"><span id="overallProgress">0</span>%</div>
									<div class="text-muted"><span id="totalAnswered">0</span>/<span id="totalQuestions">0</span> Questions</div>
								</div>
								<div class="col-md-4 text-center">
									<h5>Success Rate</h5>
									<div class="h3"><span id="overallSuccess">0</span>%</div>
									<div class="text-muted"><span id="totalCorrect">0</span> Correct Answers</div>
								</div>
								<div class="col-md-4 text-center">
									<h5>Sets Completed</h5>
									<div class="h3"><span id="completedSets">0</span>/<span id="totalSets">0</span></div>
									<div class="text-muted">Question Sets</div>
								</div>
							</div>
						</div>
					</div>

					<h3 class="mb-3">Available Sets (<?php echo count($questionSets); ?> found)</h3>
					<?php if (empty($questionSets)): ?>
						<div class="alert alert-info">
							No question sets found in directory: <?php echo htmlspecialchars($questionDir); ?>
						</div>
					<?php else: ?>
						<div class="row">
							<?php foreach ($questionSets as $set): ?>
								<div class="col-md-3 mb-3">
									<div class="card question-set-card <?php
																											if ($set['stats']['answered'] === 0) {
																												echo 'card-status-not-started';
																											} elseif ($set['stats']['answered'] === $set['stats']['total']) {
																												echo 'card-status-completed';
																											} else {
																												echo 'card-status-in-progress';
																											}
																											?>"
										data-filename="<?php echo htmlspecialchars($set['file']); ?>"
										onclick="loadQuestionSet('<?php echo htmlspecialchars($set['file']); ?>')">
										<div class="card-header">
											<h6 class="card-title mb-0 d-flex justify-content-between align-items-center">
												<?php echo htmlspecialchars($set['title']); ?>
												<?php if ($set['stats']['answered'] > 0): ?>
													<span class="badge <?php echo $set['stats']['answered'] === $set['stats']['total'] ? 'bg-success' : 'bg-warning'; ?>" style="font-size: 0.7em;">
														<?php echo $set['stats']['answered'] === $set['stats']['total'] ? 'Completed' : 'In Progress'; ?>
													</span>
												<?php endif; ?>
											</h6>
										</div>
										<div class="card-body">
											<div class="question-set-stats">
												<!-- Progress Bar -->
												<div class="mb-1">
													<div class="d-flex justify-content-between align-items-center" style="margin-bottom: 0.2rem;">
														<small class="text-muted">Progress</small>
														<small class="fw-bold"><?php echo $set['stats']['answered']; ?>/<?php echo $set['stats']['total']; ?></small>
													</div>
													<div class="progress" style="height: 4px;">
														<?php
														$progressPercent = $set['stats']['total'] > 0 ?
															($set['stats']['answered'] / $set['stats']['total'] * 100) : 0;
														?>
														<div class="progress-bar <?php echo $progressPercent == 100 ? 'bg-success' : 'bg-primary'; ?>"
															role="progressbar"
															style="width: <?php echo $progressPercent; ?>%">
														</div>
													</div>
												</div>

												<!-- Success Rate -->
												<div>
													<small class="text-muted d-flex justify-content-between mb-1">
														<strong>Success Rate</strong>
														<span class="correct-stats">
															<?php
															$answered = $set['stats']['answered'];
															$correct = $set['stats']['correct'];
															$successRate = $answered > 0 ? round(($correct / $answered) * 100, 1) : 0;
															echo "{$correct}/{$answered} ({$successRate}%)";
															?>
														</span>
													</small>
													<div class="progress" style="height: 8px;">
														<div class="progress-bar <?php echo
																											$successRate >= 70 ? 'bg-success' : ($successRate >= 50 ? 'bg-warning' : ($answered > 0 ? 'bg-danger' : 'bg-secondary')); ?>"
															role="progressbar"
															style="width: <?php echo $successRate; ?>%">
														</div>
													</div>
												</div>
											</div>

											<!-- Question Stats -->
											<div class="mt-3 pt-2 border-top">
												<div class="row text-center">
													<div class="col-6">
														<small class="text-muted">Questions</small><br>
														<strong><?php echo $set['stats']['total']; ?></strong>
													</div>
													<div class="col-6">
														<small class="text-muted">Correct</small><br>
														<strong class="<?php echo $correct > 0 ? 'text-success' : 'text-muted'; ?>">
															<?php echo $correct; ?>
														</strong>
													</div>
												</div>
											</div>
										</div>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
			<!-- Wrong Answers Review Tab -->
			<div class="tab-pane fade" id="review-tab-pane" role="tabpanel">
			    <h2 class="mb-4">Wrong Answers Review</h2>
			    <div class="row mb-4" id="wrongAnswersContainer">
			        <!-- Wrong answers will be loaded here -->
			    </div>
			</div>

			<!-- Wrong Answers Modal -->
			<div class="modal fade" id="wrongAnswersModal" tabindex="-1">
			    <div class="modal-dialog modal-xl">
			        <div class="modal-content">
			            <div class="modal-header py-2 bg-light border-bottom">
			                <h5 class="modal-title fs-6"></h5>
			                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
			            </div>
			            <div class="modal-body p-0" style="max-height: 85vh; overflow-y: auto;">
			                <div id="wrongAnswersDetail" class="p-0">
			                    <!-- Wrong answers details will be loaded here -->
			                </div>
			            </div>
			        </div>
			    </div>
			</div>
		</div>
	</div>

	<!-- Exam View -->
	<div id="examContainer">
		<div class="row margem">
			<div class="col-md-8">
				<div id="questionContainer">
					<!-- Questions will be loaded here -->
				</div>
				<div class="d-flex justify-content-between mt-4">
					<button id="prevBtn" class="btn btn-secondary">Previous</button>
					<button onclick="showQuestionSets()" class="btn btn-info">Back to Question Sets</button>
					<button id="nextBtn" class="btn btn-primary">Next</button>
				</div>
			</div>
			<div class="col-md-4">
				<div class="card">
					<div class="card-header">
						Progress
					</div>
					<div class="card-body">
						<div class="progress mb-3">
							<div id="progressBar" class="progress-bar" role="progressbar" style="width: 0%"></div>
						</div>
						<div id="stats">
							Answered: <span id="answeredCount">0</span> / <span id="totalQuestions">0</span><br>
							Correct: <span id="correctCount">0</span> / <span id="answeredCount2">0</span>
							(<span id="successRate">0</span>% success rate)<br>
							Overall: (<span id="correctPercentage">0</span>% of total)
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
	<script>
		// Global variables
		let questions = [];
		let currentQuestion = 0;
		let answers = {};
		let correctAnswers = 0;
		let currentFile = '';
		let wrongAnswers = {};

		// Wrong answers functionality
		function loadWrongAnswers() {
			$.getJSON('api/wrong-answers.php', function(response) {
				if (response.success) {
					wrongAnswers = response.data;
					renderWrongAnswers();
				} else {
					console.error('Failed to load wrong answers:', response.error);
					$('#wrongAnswersContainer').html(
						'<div class="col-12"><div class="alert alert-danger">Failed to load wrong answers.</div></div>'
					);
				}
			});
		}

		function renderWrongAnswers() {
		    console.log('Rendering wrong answers:', wrongAnswers);
		    let html = '';
		    if (Object.keys(wrongAnswers).length === 0) {
		        html = '<div class="col-12"><div class="alert alert-info">No wrong answers found.</div></div>';
		    } else {
		        Object.entries(wrongAnswers).forEach(([fileId, data]) => {
		            const examNumber = fileId.match(/\d+/)[0];
		            html += `
		                <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
		                    <div class="card h-100">
		                        <div class="card-header py-2 px-3 bg-light d-flex justify-content-between align-items-center">
		                            <h6 class="mb-0">Exam ${examNumber}</h6>
		                            <span class="badge bg-danger">${data.totalWrong}</span>
		                        </div>
		                        <div class="card-body p-3">
		                            <button class="btn btn-sm btn-primary w-100"
		                                    onclick="showWrongAnswersDetail('${fileId}')"
		                                    data-bs-toggle="modal"
		                                    data-bs-target="#wrongAnswersModal">
		                                Review ${data.totalWrong} Wrong Answer${data.totalWrong > 1 ? 's' : ''}
		                            </button>
		                        </div>
		                    </div>
		                </div>`;
		        });
		    }
		    $('#wrongAnswersContainer').html(html);
		}
		
		// Function to show wrong answers detail in modal
		function showWrongAnswersDetail(fileId) {
		    const data = wrongAnswers[fileId];
		    const examNumber = fileId.match(/\d+/)[0];
		    $('.modal-title').text(`Practice Exam ${examNumber} - Wrong Answers`);
		    
		    let html = '<div class="list-group list-group-flush">';
		    
		    data.questions.forEach(q => {
		        html += `
		            <div class="list-group-item">
		                <div class="row g-0">
		                    <div class="col-12">
		                        <div class="px-3 py-2">
		                            <div class="question-header">
		                                <div class="question-number">Question ${q.questionNumber}</div>
		                            </div>
		                            <div class="question-text">${q.questionText || 'Question text not available'}</div>
		                            <div class="options">
		                                ${Object.entries(q.options || {}).map(([key, value]) => {
		                                    const isUserAnswer = Array.isArray(q.userAnswer) ? q.userAnswer.includes(key) : q.userAnswer === key;
		                                    const isCorrectAnswer = Array.isArray(q.correctAnswer) ? q.correctAnswer.includes(key) : q.correctAnswer === key;
		                                    return `
		                                        <div class="option small py-1 ${isUserAnswer ? 'user-answer' : ''} ${isCorrectAnswer ? 'correct-answer' : ''}">
		                                            ${key}) ${value}
		                                            ${isUserAnswer || isCorrectAnswer ?
		                                                `<i class="fas fa-${isCorrectAnswer ? 'check text-success' : 'times text-danger'} float-end"></i>`
		                                                : ''}
		                                        </div>`;
		                                }).join('')}
		                            </div>
		                            <div class="d-flex gap-3 mt-2 small">
		                                <div class="text-danger">
		                                    <small class="text-muted">Your Answer:</small>
		                                    <span class="ms-1">${Array.isArray(q.userAnswer) ? q.userAnswer.join(', ') : q.userAnswer}</span>
		                                </div>
		                                <div class="text-success">
		                                    <small class="text-muted">Correct:</small>
		                                    <span class="ms-1">${Array.isArray(q.correctAnswer) ? q.correctAnswer.join(', ') : q.correctAnswer}</span>
		                                </div>
		                            </div>
		                        </div>
		                    </div>
		                </div>
		            </div>`;
		    });
		    
		    html += '</div>';
		    $('#wrongAnswersDetail').html(html);
		}

// Initialize wrong answers count on load
function loadWrongAnswersCount() {
$.getJSON('api/wrong-answers.php', function(response) {
if (response.success && response.data) {
			let totalWrong = 0;
			Object.values(response.data).forEach(data => {
			    totalWrong += data.totalWrong;
			});
			if (totalWrong > 0) {
			    $('#wrongAnswersCount').text(totalWrong);
			}
}
});
}

		// Add tab event listener in document ready
		$(document).ready(function() {
			// Load initial stats and refresh every 30 seconds
			loadAndUpdateHomeStats();
			setInterval(loadAndUpdateHomeStats, 30000);

			// Initialize tabs and load initial data
			$('[data-bs-toggle="tab"]').on('click', function(e) {
			    e.preventDefault();
			    const target = $(this).data('bs-target');
			    
			    // Remove active class from all buttons and add to clicked one
			    $('[data-bs-toggle="tab"]').removeClass('active');
			    $(this).addClass('active');
			    
			    // Hide all tab panes and show the target one
			    $('.tab-pane').removeClass('show active');
			    $(target).addClass('show active');
			    
			    // If wrong answers tab is clicked, load the data
			    if (target === '#review-tab-pane') {
			        loadWrongAnswers();
			    }
			});

			// Initial load of wrong answers count
			loadWrongAnswersCount();

			function loadWrongAnswersCount() {
			    $.getJSON('api/wrong-answers.php', function(response) {
			        if (response.success && response.data) {
			            let totalWrong = 0;
			            Object.values(response.data).forEach(data => {
			                totalWrong += data.totalWrong;
			            });
			            if (totalWrong > 0) {
			                $('#wrongAnswersCount').text(totalWrong);
			            }
			        }
			    });
			}
		});

		function loadAndUpdateHomeStats() {
		console.log('Loading stats...');
		$.getJSON('api/load-stats.php', function(response) {
		if (response.success) {
		console.log('Loaded home stats:', response.stats);
		if (!response.stats || Object.keys(response.stats).length === 0) {
		    console.log('No stats data available');
		    return;
		}
					let totalQuestions = 0;
					let totalAnswered = 0;
					let totalCorrect = 0;
					let completedSets = 0;
					const totalSets = Object.keys(response.stats).length;

					// Calculate totals
					console.log('Processing stats data...');
					Object.entries(response.stats).forEach(([fileId, fileStats]) => {
					    console.log(`Processing file ${fileId}:`, fileStats);
					    totalQuestions += fileStats.total || 0;
					    totalAnswered += fileStats.answered || 0;
					    totalCorrect += fileStats.correct || 0;
					
					    if (fileStats.answered === fileStats.total && fileStats.total > 0) {
					        completedSets++;
					    }
					
					    // Update individual card stats
					    updateCardStats(fileId, fileStats);
					});
					
					console.log('Calculated totals:', {
					    totalQuestions,
					    totalAnswered,
					    totalCorrect,
					    completedSets
					});

					// Update overall progress
					const overallProgress = totalQuestions > 0 ? ((totalAnswered / totalQuestions) * 100).toFixed(1) : 0;
					const overallSuccess = totalAnswered > 0 ? ((totalCorrect / totalAnswered) * 100).toFixed(1) : 0;

					// Update UI elements
					$('#overallProgress').text(overallProgress);
					$('#totalAnswered').text(totalAnswered);
					$('#totalQuestions').text(totalQuestions);
					$('#overallSuccess').text(overallSuccess);
					$('#totalCorrect').text(totalCorrect);
					$('#completedSets').text(completedSets);
					$('#totalSets').text(totalSets);

					console.log('Home stats updated successfully');
				} else {
					console.error('Failed to load home stats:', response.error);
				}
			});
		}

		$(document).ready(function() {
			// Load initial stats and refresh every 30 seconds
			loadAndUpdateHomeStats();
			setInterval(loadAndUpdateHomeStats, 30000);
		});

		function showQuestionSets() {
			$('#examContainer').hide();
			$('#questionSetsContainer').show();
			$('#examInfo').text('');
			// Reset current exam state
			questions = [];
			currentQuestion = 0;
			answers = {};
			correctAnswers = 0;
			currentFile = '';

			// Load fresh stats when returning to question sets view
			loadAndUpdateHomeStats();
		}

		function loadQuestionSet(filename) {
			console.log('Loading question set:', filename);
			if (!filename) {
				console.error('No filename provided');
				return;
			}
			currentFile = filename;
			answers = {};
			correctAnswers = 0;
			currentQuestion = 0;

			$('#questionSetsContainer').hide();
			$('#examContainer').show();

			loadQuestions();
		}

		function loadQuestions() {
			console.log('Loading questions for file:', currentFile);
			$.getJSON('api/questions.php', {
				file: currentFile
			}, function(data) {
				console.log('API response:', data);
				if (data.success) {
					questions = data.data;
					// Load saved answers
					$.getJSON('api/load-progress.php', {
						file: currentFile
					}, function(progressData) {
						if (progressData.success && progressData.data) {
							answers = progressData.data.answers;
							correctAnswers = Object.values(answers).filter(a => a.correct).length;
							updateProgress();

							// Find the first unanswered question
							currentQuestion = findFirstUnansweredQuestion();
						}
						$('#totalQuestions').text(questions.length);
						$('#examInfo').text('File: ' + currentFile);
						showQuestion(currentQuestion);

						// Set up navigation button handlers
						$('#prevBtn').off('click').on('click', function() {
							if (currentQuestion > 0) {
								currentQuestion--;
								showQuestion(currentQuestion);
							}
						});

						$('#nextBtn').off('click').on('click', function() {
							const nextQuestion = findNextUnansweredQuestion(currentQuestion);
							if (nextQuestion !== -1) {
								currentQuestion = nextQuestion;
								showQuestion(currentQuestion);
							} else if (currentQuestion < questions.length - 1) {
								currentQuestion++;
								showQuestion(currentQuestion);
							}
						});
					});
				} else {
					alert('Error loading questions: ' + data.error);
				}
			});
		}

		function showQuestion(index) {
			const question = questions[index];
			let hasMultipleAnswers = Array.isArray(question.answer);

			let html = `
                <div class="question-card" data-question-id="${question.id}">
                    <h5 class="card-title">
                        Question ${question.id}
                        ${answers[question.id]?.answered ? 
                            `<span class="question-status-icon ${answers[question.id].correct ? 'status-correct' : 'status-incorrect'}">
                                ${answers[question.id].correct ? '✓' : '✗'}
                            </span>` : 
                            '<span class="question-status-icon status-unanswered">•</span>'}
                    </h5>
                    <p class="question-text">${question.question}</p>`;

			if (hasMultipleAnswers) {
				html += `
                    <div class="multiple-answer-notice">
                        This question has multiple correct answers. Select all that apply.
                    </div>`;
			}

			html += `<div class="options">`;

			for (const [key, value] of Object.entries(question.options)) {
				const isAnswered = answers[question.id]?.answered;
				const isSelected = isAnswered && (hasMultipleAnswers ?
					answers[question.id].answer.includes(key) :
					answers[question.id].answer[0] === key);
				const isCorrect = hasMultipleAnswers ?
					question.answer.includes(key) :
					question.answer === key;

				let optionClass = 'option-label';
				if (isSelected) optionClass += ' selected';
				if (isAnswered) {
					optionClass += ' disabled';
					if (isSelected && isCorrect) optionClass += ' option-correct';
					if (isSelected && !isCorrect) optionClass += ' option-incorrect';
					if (!isSelected && isCorrect) optionClass += ' option-correct';
				}

				html += `
                    <label class="${optionClass}">
                        <input type="${hasMultipleAnswers ? 'checkbox' : 'radio'}" 
                               name="question${question.id}" 
                               value="${key}" 
                               ${isSelected ? 'checked' : ''} 
                               ${isAnswered ? 'disabled' : ''}>
                        ${key}) ${value}
                    </label>`;
			}

			html += `
                    </div>
                    <div class="mt-3">
                        <button class="btn btn-success submit-answer" onclick="submitAnswer(${question.id})" ${answers[question.id]?.answered ? 'disabled' : ''}>
                            Submit Answer
                        </button>
                    </div>
                    <div class="answer-status" id="status-${question.id}"></div>
                    <div class="explanation" id="explanation-${question.id}"></div>
                </div>`;

			$('#questionContainer').html(html);

			if (hasMultipleAnswers) {
				$('.multiple-answer-notice').show();
			}

			// Update navigation buttons
			$('#prevBtn').prop('disabled', index === 0);
			$('#nextBtn').prop('disabled', index === questions.length - 1);

			// Show saved answer status if exists
			if (answers[question.id]?.answered) {
				showAnswerStatus(question.id);
			}
		}

		function submitAnswer(questionId) {
			const question = questions.find(q => q.id == questionId);
			const hasMultipleAnswers = Array.isArray(question.answer);

			let selectedAnswers = [];
			if (hasMultipleAnswers) {
				$(`.question-card[data-question-id="${questionId}"] input[type="checkbox"]:checked`).each(function() {
					selectedAnswers.push($(this).val());
				});
				if (selectedAnswers.length === 0) {
					alert('Please select at least one answer.');
					return;
				}
			} else {
				const selected = $(`.question-card[data-question-id="${questionId}"] input[type="radio"]:checked`).val();
				if (!selected) {
					alert('Please select an answer.');
					return;
				}
				selectedAnswers = [selected];
			}

			const isCorrect = hasMultipleAnswers ?
				arraysEqual(selectedAnswers.sort(), question.answer.sort()) :
				selectedAnswers[0] === question.answer;

			answers[questionId] = {
				answer: selectedAnswers,
				answered: true,
				correct: isCorrect
			};

			if (isCorrect) {
				correctAnswers++;
			}

			updateProgress();

			// Prepare the answer data in the format ExamManager expects
			const answer = {
			    questionId: parseInt(questionId, 10),
			    examFile: currentFile,
			    answer: selectedAnswers,
			    correctAnswer: question.answer,
			    correct: isCorrect,
			    timestamp: Math.floor(Date.now() / 1000),
			    answered: true
			};
			
			console.log('Preparing answer:', answer);
			
			// Prepare the answer with questionId as key
			const answerData = {};
			answerData[answer.questionId] = {
			    questionNumber: answer.questionId,
			    answer: answer.answer,
			    correctAnswer: answer.correctAnswer,
			    correct: answer.correct,
			    answered: true,
			    timestamp: Math.floor(Date.now() / 1000)
			};
			
			// Create the request data
			const requestData = {
			    answers: JSON.stringify({
			        questionId: answer.questionId,
			        answer: answer.answer,
			        file: currentFile,
			        correct: answer.correct,
			        correctAnswer: answer.correctAnswer
			    })
			};
			
			console.log('Submitting answer:', {
			    data: JSON.parse(requestData.answers)
			});
			
			console.log('Sending request:', requestData);

			$.post('api/save-progress.php', requestData, function(response) {
			    console.log('Server response:', response);
			    
			    if (response.success) {
			        console.log('Answer saved successfully');
			        showAnswerStatus(questionId);
			        
			        const card = $(`.question-card[data-question-id="${questionId}"]`);
			        
			        // Disable inputs and button
			        card.find('input').prop('disabled', true);
			        card.find('.submit-answer').prop('disabled', true);
			        
			        // Mark correct/incorrect answers
			        card.find('.option-label').each(function() {
			            const value = $(this).find('input').val();
			            const isSelected = selectedAnswers.includes(value);
			            const isCorrect = hasMultipleAnswers ?
			                question.answer.includes(value) :
			                question.answer === value;
			                
			            if (isSelected && isCorrect) {
			                $(this).addClass('option-correct');
			            } else if (isSelected && !isCorrect) {
			                $(this).addClass('option-incorrect');
			            } else if (!isSelected && isCorrect) {
			                $(this).addClass('option-correct');
			            }
			        });
			        
			        // Update stats
			        loadAndUpdateHomeStats();
			    } else {
			        console.error('Error saving answer:', response.error);
			        alert('Error saving answer: ' + response.error);
			    }
			}, 'json').fail(function(xhr, status, error) {
			    console.error('AJAX request failed:', {
			        status: status,
			        error: error,
			        response: xhr.responseText
			    });
			    alert('Failed to save answer. Please try again.');
				});
		}

		function showAnswerStatus(questionId) {
			const question = questions.find(q => q.id == questionId);
			const answer = answers[questionId];
			const statusDiv = $(`#status-${questionId}`);
			const explanationDiv = $(`#explanation-${questionId}`);
			const hasMultipleAnswers = Array.isArray(question.answer);

			statusDiv.show();
			
			if (answer.correct) {
				statusDiv.html('Correct! 🎉').removeClass('incorrect').addClass('correct');
			} else {
				let correctAnswer = hasMultipleAnswers ?
					question.answer.join(', ') :
					question.answer;
				statusDiv.html(`Incorrect. The correct answer is: ${correctAnswer}`).removeClass('correct').addClass('incorrect');
			}

			if (question.explanation && question.explanation.trim()) {
			    explanationDiv.html(`<strong>Explanation:</strong> ${question.explanation}`).show();
			} else {
			    explanationDiv.hide();
			}
		}

		function updateProgress() {
			const answered = Object.values(answers).filter(a => a.answered).length;
			const total = questions.length;
			const percentage = (answered / total) * 100;
			const correctPercentage = (correctAnswers / total * 100).toFixed(1);
			const successRate = answered > 0 ? (correctAnswers / answered * 100).toFixed(1) : 0;

			$('#progressBar').css('width', percentage + '%');
			$('#answeredCount').text(answered);
			$('#answeredCount2').text(answered);
			$('#correctCount').text(correctAnswers);
			$('#correctPercentage').text(correctPercentage);
			$('#successRate').text(successRate);

			// Update progress bar color based on success rate
			const progressBar = $('#progressBar');
			if (successRate >= 70) {
				progressBar.removeClass('bg-warning bg-danger').addClass('bg-success');
			} else if (successRate >= 50) {
				progressBar.removeClass('bg-success bg-danger').addClass('bg-warning');
			} else if (answered > 0) {
				progressBar.removeClass('bg-success bg-warning').addClass('bg-danger');
			}
		}

		function findFirstUnansweredQuestion() {
			for (let i = 0; i < questions.length; i++) {
				if (!answers[questions[i].id]?.answered) {
					return i;
				}
			}
			return 0; // If all questions are answered, return to the first question
		}

		function findNextUnansweredQuestion(currentIndex) {
			for (let i = currentIndex + 1; i < questions.length; i++) {
				if (!answers[questions[i].id]?.answered) {
					return i;
				}
			}
			return -1; // No more unanswered questions after current
		}

		function arraysEqual(a, b) {
			if (a === b) return true;
			if (a == null || b == null) return false;
			if (a.length !== b.length) return false;
			for (let i = 0; i < a.length; ++i) {
				if (a[i] !== b[i]) return false;
			}
			return true;
		}

		function updateCardStats(filename, stats) {
			console.log('Updating stats for file:', filename, stats);
			// Find the card for this file
			const card = $(`.question-set-card[data-filename="${filename}"]`);

			if (!stats) {
				console.error('No stats provided for file:', filename);
				return;
			}

			if (card.length) {
				console.log('Found card, updating with stats:', {
					answered: stats.answered,
					total: stats.total,
					correct: stats.correct,
					successRate: stats.answered > 0 ? ((stats.correct / stats.answered) * 100).toFixed(1) : 0
				});
				// Update progress numbers
				card.find('.progress-numbers').text(`${stats.answered}/${stats.total}`);

				// Update progress bar
				const percentage = stats.total > 0 ? (stats.answered / stats.total * 100) : 0;
				card.find('.progress-bar').css('width', `${percentage}%`);

				// Update success rate
				const successRate = stats.answered > 0 ? ((stats.correct / stats.answered) * 100).toFixed(1) : 0;
				card.find('.correct-stats').text(
					`${stats.correct}/${stats.answered} (${successRate}%)`
				);

				console.log('Card stats updated successfully');
			} else {
				console.log('Card not found for file:', filename);
			}
		}
	</script>
</body>

</html>