<?php
class ExamManager
{
	private $dataPath;
	private $resultsPath;
	private $statsPath;

	public function __construct($dataPath = 'data', $resultsPath = 'data/results', $statsPath = 'data/stats')
	{
		$this->dataPath = $dataPath;
		$this->resultsPath = $resultsPath;
		$this->statsPath = $statsPath;

		// Ensure all required directories exist
		$requiredDirs = [
			$dataPath,
			$resultsPath,
			$statsPath,
			$dataPath . '/questions',
			$dataPath . '/cache'
		];

		foreach ($requiredDirs as $path) {
			if (!is_dir($path)) {
				error_log("Creating directory: $path");
				if (!mkdir($path, 0777, true)) {
					error_log("Failed to create directory: $path");
				}
			} else {
				error_log("Directory exists: $path");
			}
		}

		// Initialize stats for all question files
		$this->initializeAllStats();
	}

	private function initializeAllStats()
	{
		error_log("Initializing stats for all question files");
		$files = glob($this->dataPath . '/questions/*.md');

		if ($files === false || empty($files)) {
			error_log("No question files found in: " . $this->dataPath . '/questions/');
			return;
		}

		error_log("Found " . count($files) . " question files");

		// Initialize stats for each question file
		foreach ($files as $file) {
			$fileId = basename($file);
			$this->initializeStatsForFile($fileId);
		}
		foreach ($files as $file) {
			$fileId = basename($file);
			error_log("Processing file: $fileId");

			// Count questions in file
			$content = file_get_contents($file);
			if ($content === false) {
				error_log("Failed to read file: $fileId");
				continue;
			}

			// First try to match all question blocks
			preg_match_all('/^#+ Question \d+|<details>|Answer:/mi', $content, $matches);
			$totalQuestions = count($matches[0]);

			// Fallback to simpler count if no questions found
			if ($totalQuestions === 0) {
				$detailsCount = substr_count($content, '<details>');
				$answerCount = substr_count($content, 'Answer:');
				$questionCount = substr_count($content, 'Question ');

				error_log("File $fileId counts: details=$detailsCount, answers=$answerCount, questions=$questionCount");

				$totalQuestions = max($detailsCount, $answerCount, $questionCount);

				if ($totalQuestions === 0) {
					error_log("WARNING: No questions found in file: $fileId");
					error_log("File content sample: " . substr($content, 0, 200));
				}
			}
			error_log("Found $totalQuestions questions in $fileId");

			// Check existing progress files for this question set
			$progressFiles = glob($this->resultsPath . '/*_' . $fileId . '.json');
			if (!empty($progressFiles)) {
				foreach ($progressFiles as $progressFile) {
					$userId = basename($progressFile, '_' . $fileId . '.json');
					error_log("Calculating stats for user $userId and file $fileId");
					$this->calculateAndSaveStats($userId, $fileId);
				}
			} else {
				error_log("No existing progress found for $fileId");
			}
		}
	}

	public function saveProgress($userId, $fileId, $answers)
	{
		error_log("SaveProgress called with userId: $userId, fileId: $fileId");
		error_log("Answers data: " . print_r($answers, true));

		if (!is_array($answers)) {
			throw new Exception("Invalid answers format");
		}

		$filename = $this->resultsPath . "/{$userId}_{$fileId}.json";
		error_log("Saving to file: $filename");

		// Ensure the results directory exists
		if (!is_dir($this->resultsPath)) {
			if (!mkdir($this->resultsPath, 0777, true)) {
				throw new Exception("Failed to create results directory");
			}
		}

		// Load existing data if it exists
		$existingData = [];
		if (file_exists($filename)) {
			$existingContent = file_get_contents($filename);
			if ($existingContent === false) {
				throw new Exception("Failed to read existing progress file");
			}
			error_log("Existing content: " . $existingContent);
			$existingData = json_decode($existingContent, true) ?: [];
		}

		// Initialize data structure with validation
		$data = [
			'userId' => $userId,
			'fileId' => $fileId,
			'answers' => [],
			'timestamp' => time()
		];

		// Merge existing answers if they exist and are valid
		if (isset($existingData['answers']) && is_array($existingData['answers'])) {
			foreach ($existingData['answers'] as $qNum => $answer) {
				if (isset($answer['questionNumber']) && isset($answer['answer'])) {
					$data['answers'][$qNum] = $answer;
				}
			}
		}

		// Merge new answers, ensuring proper structure
		foreach ($answers as $qNum => $answer) {
			if (!isset($answer['questionNumber'])) {
				$answer['questionNumber'] = (int)$qNum;
			}
			if (!isset($answer['examFile'])) {
				$answer['examFile'] = $fileId;
			}
			$data['answers'][$qNum] = $answer;
		}

		error_log("Final data structure: " . print_r($data, true));

		// Handle single answer update
		if (isset($answers['questionId'])) {
			$questionNumber = (int)$answers['questionId'];
			$data['answers'][$questionNumber] = [
				'questionNumber' => $questionNumber,
				'examFile' => $fileId,
				'answer' => $answers['answer'],
				'correctAnswer' => $answers['correctAnswer'],
				'correct' => (bool)$answers['correct'],
				'answered' => true,
				'timestamp' => time()
			];
		}
		// Handle bulk answers update
		else if (is_array($answers)) {
			foreach ($answers as $answer) {
				// Check if the answer has both questionId and data
				if (isset($answer['questionId'])) {
					$questionNumber = (int)$answer['questionId'];
				} else if (isset($answer['question']) && preg_match('/^Question\s+(\d+)/i', $answer['question'], $matches)) {
					$questionNumber = (int)$matches[1];
				} else {
					continue; // Skip if no valid question number found
				}

				$data['answers'][$questionNumber] = [
					'questionNumber' => $questionNumber,
					'examFile' => $fileId,
					'answer' => $answer['answer'],
					'correctAnswer' => $answer['correctAnswer'],
					'answered' => true,
					'correct' => (bool)$answer['correct'],
					'timestamp' => time()
				];
			}
		}

		// Save the file with updated answers
		file_put_contents($filename, json_encode($data));

		// Calculate and save updated stats
		$stats = $this->calculateAndSaveStats($userId, $fileId);
		error_log("Updated stats: " . print_r($stats, true));

		return $stats;
	}

	private function initializeStatsForFile($fileId)
	{
		// Make sure we're using the correct data path for questions
		$filePath = __DIR__ . '/../data/questions/' . $fileId;
		if (!file_exists($filePath)) {
			error_log("File not found: $filePath");
			// Try alternate path
			$filePath = $this->dataPath . '/questions/' . $fileId;
			if (!file_exists($filePath)) {
				error_log("File not found in alternate path: $filePath");
				return;
			}
		}

		error_log("Reading questions from: $filePath");
		// Count questions by looking for numbered questions in the file
		$content = file_get_contents($filePath);
		if ($content === false) {
			error_log("Failed to read file content from: $filePath");
			return;
		}

		// Count <details> tags as they indicate answer sections
		$detailsCount = substr_count($content, '<details>');

		// Count "Answer:" occurrences
		$answerCount = substr_count($content, 'Answer:');

		// Count actual numbered questions by pattern '\d+\.' at the start of lines
		preg_match_all('/^\d+\./m', $content, $matches);
		$numberedQuestions = count($matches[0]);

		error_log("File $fileId question counts - Details: $detailsCount, Answers: $answerCount, Numbered: $numberedQuestions");

		// Use the most reliable count (numbered questions should match details/answers)
		$totalQuestions = max($detailsCount, $answerCount, $numberedQuestions);

		if ($totalQuestions === 0) {
			error_log("WARNING: No questions found in file: $fileId");
		}

		// Create initial stats file
		$statsFile = $this->statsPath . "/{$fileId}_stats.json";
		$stats = [
			'total' => $totalQuestions,
			'answered' => 0,
			'correct' => 0,
			'percentage' => 0,
			'lastUpdated' => time()
		];

		error_log("Initializing stats for $fileId with $totalQuestions questions");
		file_put_contents($statsFile, json_encode($stats));
		return $stats;
	}

	public function loadProgress($userId, $fileId)
	{
		$filename = $this->resultsPath . "/{$userId}_{$fileId}.json";
		if (file_exists($filename)) {
			return json_decode(file_get_contents($filename), true);
		}
		return null;
	}

	public function getFileStats($userId, $fileId)
	{
		// First reinitialize stats to ensure correct total
		$this->initializeStatsForFile($fileId);
		// Then recalculate stats from actual progress file
		return $this->calculateAndSaveStats($userId, $fileId);
	}

	public function reinitializeAllStats()
	{
		$files = glob($this->dataPath . '/questions/*.md');
		foreach ($files as $file) {
			$fileId = basename($file);
			$this->initializeStatsForFile($fileId);
			// Recalculate stats for each user that has progress
			$progressFiles = glob($this->resultsPath . '/*_' . $fileId . '.json');
			foreach ($progressFiles as $progressFile) {
				$userId = basename($progressFile, '_' . $fileId . '.json');
				$this->calculateAndSaveStats($userId, $fileId);
			}
		}
	}

	public function getAllFileStats($userId)
	{
		$stats = [];
		error_log("Getting stats for all files for user: $userId");

		// Try both possible paths for question files
		$questionDir = __DIR__ . '/../data/questions';
		$files = glob($questionDir . '/*.md');

		if (empty($files)) {
			error_log("No files found in primary path, trying alternate path");
			$questionDir = $this->dataPath . '/questions';
			if (!is_dir($questionDir)) {
				mkdir($questionDir, 0777, true);
			}
			$files = glob($questionDir . '/*.md');
		}

		error_log("Looking for files in: $questionDir");
		error_log("Found " . count($files) . " question files");

		foreach ($files as $file) {
			$fileId = basename($file);
			error_log("Processing file: $fileId");

			// Count total questions using standardized counting method
			$content = file_get_contents($file);
			if ($content === false) {
				error_log("Failed to read file: $fileId");
				continue;
			}

			// Count questions consistently
			$detailsCount = substr_count($content, '<details>');
			$answerCount = substr_count($content, 'Answer:');
			preg_match_all('/^\d+\./m', $content, $matches);
			$numberedQuestions = count($matches[0]);

			error_log("File $fileId question counts - Details: $detailsCount, Answers: $answerCount, Numbered: $numberedQuestions");

			// Use the most reliable count
			$totalQuestions = max($detailsCount, $answerCount, $numberedQuestions);

			if ($totalQuestions === 0) {
				error_log("WARNING: No questions found in file: $fileId");
			}
			error_log("Found $totalQuestions questions in $fileId");

			// Initialize stats for this file
			$stats[$fileId] = [
				'total' => $totalQuestions,
				'answered' => 0,
				'correct' => 0,
				'percentage' => 0,
				'lastUpdated' => time()
			];

			// Load progress if exists
			$progressFile = $this->resultsPath . "/{$userId}_{$fileId}.json";
			if (file_exists($progressFile)) {
				$progress = json_decode(file_get_contents($progressFile), true);
				if ($progress && isset($progress['answers'])) {
					$answers = $progress['answers'];
					// Handle both array and object formats for answers
					$stats[$fileId]['answered'] = count($answers);

					$stats[$fileId]['correct'] = count(array_filter($answers, function ($a) {
						return isset($a['correct']) && $a['correct'] === true;
					}));

					error_log("File $fileId stats - Questions: {$stats[$fileId]['total']}, Answered: {$stats[$fileId]['answered']}, Correct: {$stats[$fileId]['correct']}");
					if ($stats[$fileId]['answered'] > 0) {
						$stats[$fileId]['percentage'] = ($stats[$fileId]['correct'] / $stats[$fileId]['answered']) * 100;
					}
				}
			}
		}

		error_log("Final stats: " . print_r($stats, true));
		return $stats;
	}

	private function calculateAndSaveStats($userId, $fileId)
	{
		$progress = $this->loadProgress($userId, $fileId);
		error_log("Calculating stats for user $userId, file $fileId");

		// Get total questions from the question file
		$questionFile = $this->dataPath . '/questions/' . $fileId;
		$totalQuestions = 0;

		if (file_exists($questionFile)) {
			$content = file_get_contents($questionFile);
			if ($content !== false) {
				// Count questions consistently
				$detailsCount = substr_count($content, '<details>');
				$answerCount = substr_count($content, 'Answer:');
				preg_match_all('/^\d+\./m', $content, $matches);
				$numberedQuestions = count($matches[0]);

				error_log("File $fileId question counts - Details: $detailsCount, Answers: $answerCount, Numbered: $numberedQuestions");

				// Use the most reliable count
				$totalQuestions = max($detailsCount, $answerCount, $numberedQuestions);

				error_log("Total questions for $fileId: $totalQuestions");
			} else {
				error_log("Failed to read question file: $questionFile");
			}
		}

		$stats = [
			'total' => $totalQuestions,
			'answered' => 0,
			'correct' => 0,
			'percentage' => 0,
			'lastUpdated' => time()
		];

		if ($progress && isset($progress['answers'])) {
			error_log("Found answers in progress data: " . print_r($progress['answers'], true));
			$answers = $progress['answers'];
			// Handle both array and object formats for answers
			$stats['answered'] = count($answers);

			$stats['correct'] = count(array_filter($answers, function ($a) {
				return isset($a['correct']) && $a['correct'] === true;
			}));

			error_log("Calculated stats - Questions: {$stats['total']}, Answered: {$stats['answered']}, Correct: {$stats['correct']}");
			error_log("Calculated stats - Answered: {$stats['answered']}, Correct: {$stats['correct']}");

			// Calculate percentages
			if ($stats['answered'] > 0) {
				$stats['percentage'] = ($stats['correct'] / $stats['answered']) * 100;
			}
		}

		// Save stats
		$statsFile = $this->statsPath . "/{$userId}_{$fileId}_stats.json";
		file_put_contents($statsFile, json_encode($stats));

		return $stats;
}

/**
* Get wrong answers for a user, optionally filtered by file
*/
/**
 * Load question details from cache file
 */
private function loadQuestionFromFile($fileId, $questionId) {
    $cacheFile = $this->dataPath . '/cache/' . pathinfo($fileId, PATHINFO_FILENAME) . '.json';
    
    if (file_exists($cacheFile)) {
        $cache = json_decode(file_get_contents($cacheFile), true);
        if ($cache && is_array($cache)) {
            foreach ($cache as $question) {
                if ($question['id'] == $questionId) {
                    return [
                        'question' => $question['question'],
                        'options' => $question['options']
                    ];
                }
            }
        }
    }
    return null;
}

public function getWrongAnswers($userId, $fileId = null) {
		$result = [];
		
		if ($fileId !== null) {
		    // Get wrong answers for specific file
		    $progress = $this->loadProgress($userId, $fileId);
		    if ($progress && isset($progress['answers'])) {
		        $wrongAnswers = [];
		        $totalWrong = 0;
		        
		        foreach ($progress['answers'] as $questionId => $answer) {
		            if (isset($answer['correct']) && !$answer['correct']) {
		                $wrongAnswers[] = [
		                    'questionNumber' => $questionId,
		                    'question' => $answer['question'] ?? null,
		                    'userAnswer' => $answer['answer'],
		                    'correctAnswer' => $answer['correctAnswer'],
		                    'timestamp' => $answer['timestamp']
		                ];
		                $totalWrong++;
		            }
		        }
		        
		        if ($totalWrong > 0) {
		            $result[$fileId] = [
		                'fileId' => $fileId,
		                'totalWrong' => $totalWrong,
		                'questions' => $wrongAnswers
		            ];
		        }
		    }
		} else {
		    // Get wrong answers for all files
		    $files = glob($this->resultsPath . "/{$userId}_*.json");
		    foreach ($files as $file) {
		        $currentFileId = basename($file);
		        $currentFileId = preg_replace("/^{$userId}_/", '', $currentFileId);
		        $currentFileId = preg_replace('/.json$/', '', $currentFileId);
		        
		        $progress = $this->loadProgress($userId, $currentFileId);
		        if ($progress && isset($progress['answers'])) {
		            $wrongAnswers = [];
		            $totalWrong = 0;
		            
		            foreach ($progress['answers'] as $questionId => $answer) {
		                if (isset($answer['correct']) && !$answer['correct']) {
		                    // Load question details from the question file
		                    $questionData = $this->loadQuestionFromFile($currentFileId, $questionId);
		                    if ($questionData) {
		                        $wrongAnswers[] = [
		                            'questionNumber' => $questionId,
		                            'questionText' => $questionData['question'],
		                            'options' => $questionData['options'],
		                            'userAnswer' => $answer['answer'],
		                            'correctAnswer' => $answer['correctAnswer'],
		                            'timestamp' => $answer['timestamp']
		                        ];
		                        $totalWrong++;
		                    }
		                }
		            }
		            
		            if ($totalWrong > 0) {
		                $result[$currentFileId] = [
		                    'fileId' => $currentFileId,
		                    'totalWrong' => $totalWrong,
		                    'questions' => $wrongAnswers
		                ];
		            }
		        }
		    }
		}
		
		return $result;
}
}
