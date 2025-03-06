<?php
// Include required files
require_once __DIR__ . '/includes/Parser.php';
require_once __DIR__ . '/includes/ExamManager.php';

// Initialize session for user tracking
session_start();
if (!isset($_SESSION['userId'])) {
	$_SESSION['userId'] = uniqid('user_');
}

$parser = new Parser();
$examManager = new ExamManager();

// Get question sets and their stats
$questionSets = [];
$questionDir = __DIR__ . '/data/questions';

// Ensure question directory exists
if (!is_dir($questionDir)) {
	mkdir($questionDir, 0777, true);
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
	<style>
		.question-card {
			margin-bottom: 20px;
			padding: 20px;
			border: 1px solid #ddd;
			border-radius: 8px;
		}

		.option-label {
			display: block;
			padding: 10px;
			margin: 5px 0;
			border: 1px solid #ddd;
			border-radius: 5px;
			cursor: pointer;
		}

		.option-label:hover {
			background-color: #f8f9fa;
		}

		.selected {
			background-color: #e7f3ff;
			border-color: #0d6efd;
		}

		.answer-status {
			margin-top: 15px;
			padding: 15px;
			border-radius: 5px;
			display: none;
		}

		.answer-status.correct {
			background-color: #d4edda;
			border-color: #c3e6cb;
			color: #155724;
		}

		.answer-status.incorrect {
			background-color: #f8d7da;
			border-color: #f5c6cb;
			color: #721c24;
		}

		.explanation {
			margin-top: 15px;
			padding: 15px;
			background-color: #f8f9fa;
			border-radius: 5px;
			display: none;
		}

		.option-correct {
			background-color: #d4edda !important;
			border-color: #28a745 !important;
		}

		.option-incorrect {
			background-color: #f8d7da !important;
			border-color: #dc3545 !important;
		}

		.question-set-card {
			cursor: pointer;
			transition: transform 0.2s;
			position: relative;
			overflow: hidden;
		}

		.question-set-card:hover {
			transform: translateY(-5px);
			box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
		}

		.question-set-stats {
			font-size: 0.9em;
			color: #666;
			margin-top: 10px;
			padding-top: 10px;
			border-top: 1px solid #eee;
		}

		.progress-mini {
			height: 4px;
			margin-top: 5px;
		}

		.question-status-icon {
			position: absolute;
			top: 5px;
			right: 5px;
			font-size: 1.2em;
		}

		.status-correct {
			color: #28a745;
		}

		.status-incorrect {
			color: #dc3545;
		}

		.status-unanswered {
			color: #6c757d;
		}

		#examContainer {
			display: none;
		}

		#questionSetsContainer {
			display: block;
		}

		.multiple-answer-notice {
			color: #856404;
			background-color: #fff3cd;
			border: 1px solid #ffeeba;
			padding: 10px;
			margin-bottom: 15px;
			border-radius: 5px;
			display: none;
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
						<div class="col-md-4 mb-4">
							<div class="card question-set-card"
								data-filename="<?php echo htmlspecialchars($set['file']); ?>"
								onclick="loadQuestionSet('<?php echo htmlspecialchars($set['file']); ?>')">
								<div class="card-header bg-light">
									<h5 class="card-title mb-0 d-flex justify-content-between align-items-center">
										<?php echo htmlspecialchars($set['title']); ?>
										<?php if ($set['stats']['answered'] > 0): ?>
											<span class="badge <?php echo $set['stats']['answered'] === $set['stats']['total'] ? 'bg-success' : 'bg-warning'; ?>">
												<?php echo $set['stats']['answered'] === $set['stats']['total'] ? 'Completed' : 'In Progress'; ?>
											</span>
										<?php endif; ?>
									</h5>
								</div>
								<div class="card-body">
									<div class="question-set-stats p-2 bg-light rounded">
										<!-- Progress Bar -->
										<div class="mb-3">
											<small class="text-muted d-flex justify-content-between mb-1">
												<strong>Completion</strong>
												<span class="progress-numbers"><?php echo $set['stats']['answered']; ?>/<?php echo $set['stats']['total']; ?></span>
											</small>
											<div class="progress" style="height: 8px;">
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

		<!-- Exam View -->
		<div id="examContainer">
			<div class="row">
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
		let questions = [];
		let currentQuestion = 0;
		let answers = {};
		let correctAnswers = 0;
		let currentFile = '';

		function loadAndUpdateHomeStats() {
		    $.getJSON('api/load-stats.php', function(response) {
		        if (response.success) {
		            console.log('Loaded home stats:', response.stats);
		            let totalQuestions = 0;
		            let totalAnswered = 0;
		            let totalCorrect = 0;
		            let completedSets = 0;
		            const totalSets = Object.keys(response.stats).length;

		            // Calculate totals
		            Object.entries(response.stats).forEach(([fileId, fileStats]) => {
		                totalQuestions += fileStats.total || 0;
		                totalAnswered += fileStats.answered || 0;
		                totalCorrect += fileStats.correct || 0;

		                if (fileStats.answered === fileStats.total && fileStats.total > 0) {
		                    completedSets++;
		                }

		                // Update individual card stats
		                updateCardStats(fileId, fileStats);
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

			// Prepare answer data with proper types
			const answerData = {
				questionId: parseInt(questionId, 10),
				answer: selectedAnswers,
				file: currentFile,
				correct: Boolean(isCorrect),
				correctAnswer: question.answer
			};

			console.log('Question details:', question);
			console.log('Submitting answer:', answerData);

			// Save to server and update statistics
			// Log the complete request
			console.log('Current question:', question);
			console.log('Selected answers:', selectedAnswers);
			console.log('Current file:', currentFile);

			const requestData = {
				answers: JSON.stringify(answerData)
			};
			console.log('Request data:', requestData);

			$.post('api/save-progress.php', requestData)
				.done(function(response) {
					console.log('Server response:', response);
					if (response.success) {
						console.log('Answer saved successfully. Stats:', response.stats);
					} else {
						console.error('Failed to save answer:', response.error);
					}
					if (response.success) {
					    showAnswerStatus(questionId);

					    // Update all stats after successful answer submission
					    loadAndUpdateHomeStats();
						// Disable all options and the submit button
						const card = $(`.question-card[data-question-id="${questionId}"]`);
						card.find('input').prop('disabled', true);
						card.find('.submit-answer').prop('disabled', true);
						// Mark correct and incorrect options
						card.find('.option-label').addClass('disabled');

						card.find('.option-label').each(function() {
							const value = $(this).find('input').val();
							const isSelected = selectedAnswers.includes(value);
							const isCorrectAnswer = hasMultipleAnswers ?
								question.answer.includes(value) :
								question.answer === value;

							if (isSelected && isCorrectAnswer) {
								$(this).addClass('option-correct');
							} else if (isSelected && !isCorrectAnswer) {
								$(this).addClass('option-incorrect');
							} else if (!isSelected && isCorrectAnswer) {
								$(this).addClass('option-correct');
							}
						});
					} else {
						alert('Error saving answer: ' + response.error);
					}
				});
		}

		function showAnswerStatus(questionId) {
			const question = questions.find(q => q.id == questionId);
			const answer = answers[questionId];
			const statusDiv = $(`#status-${questionId}`);
			const explanationDiv = $(`#explanation-${questionId}`);
			const hasMultipleAnswers = Array.isArray(question.answer);

			statusDiv.show();
			explanationDiv.show();

			if (answer.correct) {
				statusDiv.html('Correct! 🎉').removeClass('incorrect').addClass('correct');
			} else {
				let correctAnswer = hasMultipleAnswers ?
					question.answer.join(', ') :
					question.answer;
				statusDiv.html(`Incorrect. The correct answer is: ${correctAnswer}`).removeClass('correct').addClass('incorrect');
			}

			if (question.explanation) {
				explanationDiv.html(`<strong>Explanation:</strong> ${question.explanation}`);
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