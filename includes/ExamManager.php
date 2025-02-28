<?php
class ExamManager {
    private $dataPath;
    private $resultsPath;
    private $statsPath;

    public function __construct($dataPath = 'data', $resultsPath = 'data/results', $statsPath = 'data/stats') {
        $this->dataPath = $dataPath;
        $this->resultsPath = $resultsPath;
        $this->statsPath = $statsPath;
        
        // Create directories if they don't exist
        foreach ([$dataPath, $resultsPath, $statsPath] as $path) {
            if (!is_dir($path)) {
                mkdir($path, 0777, true);
            }
        }

        // Initialize stats for all question files
        $this->initializeAllStats();
    }

    private function initializeAllStats() {
        foreach (glob($this->dataPath . '/questions/*.md') as $file) {
            $fileId = basename($file);
            foreach (glob($this->resultsPath . '/*_' . $fileId . '.json') as $progressFile) {
                $userId = basename($progressFile, '_' . $fileId . '.json');
                $this->calculateAndSaveStats($userId, $fileId);
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
        
        // Get all question files first
        foreach (glob($this->dataPath . '/questions/*.md') as $file) {
            $fileId = basename($file);
            // Initialize with empty stats but correct total questions
            $content = file_get_contents($file);
            $totalQuestions = substr_count($content, "Answer:");
            
            $stats[$fileId] = [
                'total' => $totalQuestions,
                'answered' => 0,
                'correct' => 0,
                'percentage' => 0,
                'lastUpdated' => time()
            ];
        }
        
        // Now load any existing progress
        $pattern = $this->resultsPath . "/{$userId}_*.json";
        foreach (glob($pattern) as $file) {
            $fileId = basename($file);
            $fileId = preg_replace("/^{$userId}_/", '', $fileId);
            $fileId = preg_replace('/\.json$/', '', $fileId);
            
            if ($fileId && isset($stats[$fileId])) {
                $stats[$fileId] = $this->getFileStats($userId, $fileId);
            }
        }
        
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