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
            
            // Skip headers
            if (strpos($line, '#') === 0) {
                continue;
            }

            // Question starts with a number
            if (preg_match('/^(\d+)\.\s+(.*)/', $line, $matches)) {
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

            // Parse answer
            if (preg_match('/Correct answer:\s*([A-E],?\s*)+/', $line, $matches)) {
                $answers = explode(',', str_replace(['Correct answer:', ' '], '', $line));
                $currentQuestion['answer'] = array_map('trim', $answers);
                if (count($currentQuestion['answer']) === 1) {
                    $currentQuestion['answer'] = $currentQuestion['answer'][0];
                }
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