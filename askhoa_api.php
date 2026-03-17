<?php
// askhoa_api.php

// 1. Load Dependencies (Flat paths - no src folder)
require_once 'DocumentProcessor.php';
require_once 'ChatHandler.php';

session_start();
header('Content-Type: application/json');

// 2. Load Configuration
$config = include(__DIR__ . '/../config/env.php');
$key = $config['OPENAI_API_KEY'] ?? '';
$storeFile = sys_get_temp_dir() . '/askhoa_' . session_id() . '.json';

$action = $_GET['action'] ?? '';

// --- UPLOAD ACTION ---
if ($action === 'upload') {
    if (!isset($_FILES['file'])) { 
        echo json_encode(['message' => 'No file received.']); 
        exit; 
    }
    
    $file = $_FILES['file'];
    $text = DocumentProcessor::extractText($file['tmp_name'], pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (strlen($text) < 100) {
        echo json_encode(['message' => 'Error: Could not extract enough text from PDF.']); 
        exit;
    }

    $chunks = DocumentProcessor::chunkText($text);
    file_put_contents($storeFile, json_encode($chunks));
    
    echo json_encode(['message' => count($chunks) . ' sections indexed. Ask me an HOA question!']);
    exit;
}

// --- ASK ACTION (Smart Search Fix) ---
if ($action === 'ask') {
    $input = json_decode(file_get_contents('php://input'), true);
    $question = strtolower($input['question'] ?? '');
    $data = json_decode(@file_get_contents($storeFile), true);
    
    if (!$data) { 
        echo json_encode(['answer' => 'Please upload and process your document first.']); 
        exit; 
    }

    // Keyword Scoring System to find the right excerpts
    $scoredChunks = [];
    $keywords = explode(' ', $question);
    
    foreach ($data as $chunk) {
        $score = 0;
        $chunkText = strtolower($chunk['text']);
        foreach ($keywords as $word) {
            // Only score meaningful words (over 3 letters)
            if (strlen($word) > 3 && strpos($chunkText, $word) !== false) {
                $score += 2;
            }
        }
        if ($score > 0) {
            $scoredChunks[] = ['text' => $chunk['text'], 'score' => $score];
        }
    }

    // Sort by relevance score (highest first)
    usort($scoredChunks, function($a, $b) { 
        return $b['score'] - $a['score']; 
    });
    
    // Pick the top 5 most relevant sections to send to the AI
    $relevantContext = array_slice($scoredChunks, 0, 5);
    $context = "";
    
    if (empty($relevantContext)) {
        // Fallback to the first 4 chunks if no keywords match
        foreach (array_slice($data, 0, 4) as $c) { 
            $context .= $c['text'] . "\n\n"; 
        }
    } else {
        foreach ($relevantContext as $c) { 
            $context .= $c['text'] . "\n\n"; 
        }
    }

    // Send to ChatHandler for OpenAI completion
    $chat = new ChatHandler($key);
    echo json_encode($chat->ask($input['question'], $context));
    exit;
}