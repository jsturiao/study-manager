<?php
class ExamManager
{
    private $dataPath;
    private $resultsPath;
    private $statsPath;

    public function __construct()
    {
        // Use existing directories in api/data
        $this->dataPath = 'api/data';
        $this->resultsPath = 'api/data/results';
        $this->statsPath = 'api/data/stats';

        // Convert MD files to JSON only if they don't exist in cache
        $this->convertMdFilesToJson();
    }

    private function convertMdFilesToJson(): bool
    {
        error_log("Checking for MD files to convert to JSON");
        $mdFiles = glob($this->dataPath . '/questions/*.md');
        
        if ($mdFiles === false) {
            error_log("Failed to read MD files from directory");
            return false;
        }

        $success = true;
        foreach ($mdFiles as $mdFile) {
            $baseFileName = basename($mdFile, '.md');
            $jsonCacheFile = $this->dataPath . '/cache/' . $baseFileName . '.json';
            
            // Skip if JSON already exists in cache
            if (file_exists($jsonCacheFile)) {
                error_log("Cache file already exists for: $baseFileName");
                continue;
            }

            error_log("Converting $baseFileName.md to JSON");
            
            // Read MD file content
            $content = file_get_contents($mdFile);
            if ($content === false) {
                error_log("Failed to read MD file: $mdFile");
                continue;
            }

            // Parse questions from MD content
            $questions = $this->parseQuestionsFromMd($content);
            
            if (empty($questions)) {
                error_log("No questions found in: $mdFile");
                continue;
            }

            // Save to cache
            if (file_put_contents($jsonCacheFile, json_encode($questions, JSON_PRETTY_PRINT))) {
                error_log("Successfully created cache file: $jsonCacheFile");
            } else {
                error_log("Failed to create cache file: $jsonCacheFile");
                $success = false;
            }
        }
        return $success;
    }

    // [Resto do código permanece o mesmo, apenas alterado o caminho base no construtor]
    
    private function parseQuestionsFromMd($content)
    {
        $questions = [];
        $currentQuestion = null;
        
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            if (preg_match('/^#+ Question (\d+)/i', $line, $matches)) {
                if ($currentQuestion) {
                    $questions[] = $currentQuestion;
                }
                
                $currentQuestion = [
                    'id' => $matches[1],
                    'question' => '',
                    'options' => [],
                    'answer' => null,
                    'explanation' => ''
                ];
                continue;
            }

            if ($currentQuestion) {
                if (empty($currentQuestion['question']) && trim($line) && !preg_match('/^[A-Z]\)/', $line)) {
                    $currentQuestion['question'] = trim($line);
                }
                
                if (preg_match('/^([A-Z])\) (.+)/', $line, $matches)) {
                    $currentQuestion['options'][$matches[1]] = trim($matches[2]);
                }
                
                if (preg_match('/^Answer: ([A-Z])$/i', $line, $matches)) {
                    $currentQuestion['answer'] = $matches[1];
                }
                
                if (preg_match('/^Explanation:(.+)/i', $line, $matches)) {
                    $currentQuestion['explanation'] = trim($matches[1]);
                }
            }
        }
        
        if ($currentQuestion) {
            $questions[] = $currentQuestion;
        }
        
        return $questions;
    }

    private function getTotalQuestions($fileId)
    {
        $filePath = $this->dataPath . '/questions/' . $fileId;
        if (!file_exists($filePath)) {
            error_log("Question file not found: $fileId");
            return 0;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            error_log("Failed to read question file: $fileId");
            return 0;
        }

        $detailsCount = substr_count($content, '<details>');
        $answerCount = substr_count($content, 'Answer:');
        preg_match_all('/^\d+\./m', $content, $matches);
        $numberedQuestions = count($matches[0]);

        return max($detailsCount, $answerCount, $numberedQuestions);
    }

    public function saveProgress($userId, $fileId, $answers)
    {
        error_log("SaveProgress called with userId: $userId, fileId: $fileId");
        
        if (!is_array($answers)) {
            throw new Exception("Invalid answers format");
        }
        
        $filename = $this->resultsPath . "/{$userId}_{$fileId}.json";
        
        $existingData = [];
        if (file_exists($filename)) {
            $existingContent = file_get_contents($filename);
            if ($existingContent === false) {
                throw new Exception("Failed to read existing progress file");
            }
            $existingData = json_decode($existingContent, true) ?: [];
        }
        
        $data = [
            'userId' => $userId,
            'fileId' => $fileId,
            'answers' => [],
            'timestamp' => time()
        ];

        if (isset($existingData['answers']) && is_array($existingData['answers'])) {
            foreach ($existingData['answers'] as $qNum => $answer) {
                if (isset($answer['questionNumber']) && isset($answer['answer'])) {
                    $data['answers'][$qNum] = $answer;
                }
            }
        }

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
        } else if (is_array($answers)) {
            foreach ($answers as $answer) {
                if (isset($answer['questionId'])) {
                    $questionNumber = (int)$answer['questionId'];
                } else if (isset($answer['question']) && preg_match('/^Question\s+(\d+)/i', $answer['question'], $matches)) {
                    $questionNumber = (int)$matches[1];
                } else {
                    continue;
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
        
        file_put_contents($filename, json_encode($data));
        
        $stats = $this->calculateAndSaveStats($userId, $fileId);
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

    private function calculateAndSaveStats($userId, $fileId)
    {
        $progress = $this->loadProgress($userId, $fileId);
        $totalQuestions = $this->getTotalQuestions($fileId);
        
        $stats = [
            'total' => $totalQuestions,
            'answered' => 0,
            'correct' => 0,
            'percentage' => 0,
            'lastUpdated' => time()
        ];
        
        if ($progress && isset($progress['answers'])) {
            $answers = $progress['answers'];
            $stats['answered'] = count($answers);
            
            $stats['correct'] = count(array_filter($answers, function ($a) {
                return isset($a['correct']) && $a['correct'] === true;
            }));
            
            if ($stats['answered'] > 0) {
                $stats['percentage'] = ($stats['correct'] / $stats['answered']) * 100;
            }
        }
        
        $statsFile = $this->statsPath . "/{$userId}_{$fileId}_stats.json";
        file_put_contents($statsFile, json_encode($stats));
        
        return $stats;
    }

    public function getFileStats($userId, $fileId)
    {
        return $this->calculateAndSaveStats($userId, $fileId);
    }

    public function getAllFileStats($userId)
    {
        $stats = [];
        error_log("Loading stats for user: $userId");
        $files = glob($this->dataPath . '/questions/*.md');
        
        if ($files === false || empty($files)) {
            error_log("No question files found in: " . $this->dataPath . '/questions/');
            return $stats;
        }
        
        foreach ($files as $file) {
            $fileId = basename($file);
            $totalQuestions = $this->getTotalQuestions($fileId);
            error_log("Processing $fileId - Total questions: $totalQuestions");
            
            $stats[$fileId] = [
                'total' => $totalQuestions,
                'answered' => 0,
                'correct' => 0,
                'percentage' => 0,
                'lastUpdated' => time()
            ];
            
            $progressFile = $this->resultsPath . "/{$userId}_{$fileId}.json";
            error_log("Checking progress file: $progressFile");
            
            if (file_exists($progressFile)) {
                $progress = json_decode(file_get_contents($progressFile), true);
                if ($progress && isset($progress['answers'])) {
                    $answers = $progress['answers'];
                    $stats[$fileId]['answered'] = count($answers);
                    
                    $stats[$fileId]['correct'] = count(array_filter($answers, function ($a) {
                        return isset($a['correct']) && $a['correct'] === true;
                    }));
                    
                    if ($stats[$fileId]['answered'] > 0) {
                        $stats[$fileId]['percentage'] = ($stats[$fileId]['correct'] / $stats[$fileId]['answered']) * 100;
                    }
                    
                    error_log("Stats for $fileId: " . json_encode($stats[$fileId]));
                }
            }
        }
        
        return $stats;
    }

    private function loadQuestionFromFile($fileId, $questionId)
    {
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

    public function getWrongAnswers($userId, $fileId = null)
    {
        $result = [];
        
        if ($fileId !== null) {
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
