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
	<title> AWS Practice Exam Manager </title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
	<link href="assets/style.css" rel="stylesheet">
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
	<script src="assets/main.js"></script>
</body>

</html>