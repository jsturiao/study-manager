<?php
class Parser {
    private $cachePath;

    public function __construct($cachePath = 'data/cache') {
        $this->cachePath = $cachePath;
        if (!is_dir($cachePath)) {
            mkdir($cachePath, 0777, true);
        }
    }

    public function parseFile($filePath) {
        // Get JSON cache path
        $cacheFile = $this->cachePath . '/' . basename($filePath, '.md') . '.json';
        
        // Return cached version if it exists and is newer than MD file
        if (file_exists($cacheFile) && filemtime($cacheFile) > filemtime($filePath)) {
            return json_decode(file_get_contents($cacheFile), true);
        }

        // Parse markdown and create cache
        $questions = $this->parseMarkdown($filePath);
        
        // Save to cache
        file_put_contents($cacheFile, json_encode($questions, JSON_PRETTY_PRINT));
        
        return $questions;
    }

    private function parseMarkdown($filePath) {
        $content = file_get_contents($filePath);
        $lines = explode("\n", $content);
        $questions = [];
        $currentQuestion = null;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip empty lines and markdown frontmatter
            if (empty($line) || $line === '---' || strpos($line, 'layout:') === 0) {
                continue;
            }
            
            // Question can start with # or a number
            if (preg_match('/^#+\s*Question\s+(\d+)\.?\s*(.*)/', $line, $matches) ||
                preg_match('/^(\d+)\.\s+(.*)/', $line, $matches)) {
                if ($currentQuestion) {
                    $questions[] = $currentQuestion;
                }
                $currentQuestion = [
                    'id' => $matches[1],
                    'question' => $matches[2],
                    'options' => [],
                    'answer' => [],
                    'explanation' => ''
                ];
                continue;
            }

            // Parse options
            if (preg_match('/^\s*-\s+([A-E])\.\s+(.*)/', $line, $matches)) {
                $currentQuestion['options'][$matches[1]] = $matches[2];
                continue;
            }

            // Handle <details> format
            if (strpos($line, '<details>') === 0 && $currentQuestion === null) {
                $currentQuestion = [
                    'id' => count($questions) + 1,
                    'question' => '',
                    'options' => [],
                    'answer' => [],
                    'explanation' => ''
                ];
                continue;
            }

            // Parse answer (support multiple formats)
            if (preg_match('/(?:Correct )?[Aa]nswer:\s*([A-E],?\s*)+/', $line, $matches) ||
                preg_match('/^Answer:\s*([A-E],?\s*)+/', $line, $matches)) {
                
                // Extract answers from the line, handling various formats
                $answerPart = preg_replace('/^(?:Correct )?[Aa]nswer:\s*/', '', $line);
                $answers = preg_split('/[,\s]+/', $answerPart);
                
                // Clean and validate answers
                $answers = array_filter(array_map('trim', $answers), function($answer) {
                    return preg_match('/^[A-E]$/', $answer);
                });
                
                if (!empty($answers)) {
                    $currentQuestion['answer'] = count($answers) === 1 ? reset($answers) : array_values($answers);
                }
                continue;
            }

            // If we're in a question but haven't set the question text yet
            if ($currentQuestion && empty($currentQuestion['question']) && $line && strpos($line, '-') !== 0) {
                $currentQuestion['question'] = $line;
                continue;
            }
        }
        
        // Add the last question
        if ($currentQuestion) {
            $questions[] = $currentQuestion;
        }
        
        return $questions;
    }
}