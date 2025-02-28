<?php
class ExamManager {
    private $dataPath;
    private $resultsPath;
    private $statsPath;

    public function __construct($dataPath = 'data', $resultsPath = 'data/results', $statsPath = 'data/stats') {
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

    private function initializeAllStats() {
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

    public function saveProgress($userId, $fileId, $answers) {
        $filename = $this->resultsPath . "/{$userId}_{$fileId}.json";
        $data = [
            'userId' => $userId,
            'fileId' => $fileId,
            'answers' => $answers,
            'timestamp' => time()
        ];
        
        error_log("Saving progress to file: $filename");
        error_log("Progress data: " . print_r($data, true));
        
        file_put_contents($filename, json_encode($data));
        
        // Calculate and save updated stats
        $stats = $this->calculateAndSaveStats($userId, $fileId);
        error_log("Updated stats: " . print_r($stats, true));
        
        return $stats;
    }

    private function initializeStatsForFile($fileId) {
        $filePath = $this->dataPath . '/questions/' . $fileId;
        if (!file_exists($filePath)) {
            error_log("File not found: $filePath");
            return;
        }

        // Count questions in the file
        $content = file_get_contents($filePath);
        preg_match_all('/^#+ Question \d+|<details>|Answer:/mi', $content, $matches);
        $totalQuestions = count($matches[0]);
        
        if ($totalQuestions === 0) {
            $detailsCount = substr_count($content, '<details>');
            $answerCount = substr_count($content, 'Answer:');
            $questionCount = substr_count($content, 'Question ');
            $totalQuestions = max($detailsCount, $answerCount, $questionCount);
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

    public function loadProgress($userId, $fileId) {
        $filename = $this->resultsPath . "/{$userId}_{$fileId}.json";
        if (file_exists($filename)) {
            return json_decode(file_get_contents($filename), true);
        }
        return null;
    }

    public function getFileStats($userId, $fileId) {
        // Always recalculate stats from actual progress file
        return $this->calculateAndSaveStats($userId, $fileId);
    }

    public function getAllFileStats($userId) {
        $stats = [];
        error_log("Getting stats for all files for user: $userId");
        
        // Create directory if it doesn't exist
        $questionDir = $this->dataPath . '/questions';
        if (!is_dir($questionDir)) {
            mkdir($questionDir, 0777, true);
        }
        
        // Get all question files first
        $files = glob($questionDir . '/*.md');
        error_log("Found " . count($files) . " question files");
        
        foreach ($files as $file) {
            $fileId = basename($file);
            error_log("Processing file: $fileId");
            
            // Count total questions
            $content = file_get_contents($file);
            // Look for lines containing 'Answer:' or '<details>' to count questions
            $totalQuestions = max(
                substr_count($content, '<details>'),
                substr_count($content, 'Answer:')
            );
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
                    $stats[$fileId]['answered'] = count($answers);
                    $stats[$fileId]['correct'] = count(array_filter($answers, function($a) {
                        return isset($a['correct']) && $a['correct'];
                    }));
                    if ($stats[$fileId]['answered'] > 0) {
                        $stats[$fileId]['percentage'] = ($stats[$fileId]['correct'] / $stats[$fileId]['answered']) * 100;
                    }
                }
            }
        }
        
        error_log("Final stats: " . print_r($stats, true));
        return $stats;
    }

    private function calculateAndSaveStats($userId, $fileId) {
        $progress = $this->loadProgress($userId, $fileId);
        error_log("Calculating stats for user $userId, file $fileId");
        
        // Get total questions from the question file
        $questionFile = $this->dataPath . '/questions/' . $fileId;
        $totalQuestions = 0;
        if (file_exists($questionFile)) {
            $content = file_get_contents($questionFile);
            // Count questions by counting "Answer:" occurrences
            $totalQuestions = substr_count($content, "Answer:");
        }
        
        $stats = [
            'total' => $totalQuestions,
            'answered' => 0,
            'correct' => 0,
            'percentage' => 0,
            'lastUpdated' => time()
        ];

        if ($progress && isset($progress['answers'])) {
            error_log("Found answers in progress data");
            $answers = $progress['answers'];
            
            foreach ($answers as $answer) {
                if (isset($answer['answered']) && $answer['answered']) {
                    $stats['answered']++;
                    if (isset($answer['correct']) && $answer['correct']) {
                        $stats['correct']++;
                    }
                }
            }
            
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
}