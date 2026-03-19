<?php
// askhoa_api.php
require_once 'DocumentProcessor.php';
require_once 'ChatHandler.php';

session_start();
header('Content-Type: application/json');

$config = include(__DIR__ . '/../config/env.php');
$key = $config['OPENAI_API_KEY'] ?? '';
$storeFile = sys_get_temp_dir() . '/askhoa_' . session_id() . '.json';

$action = $_GET['action'] ?? '';

// --- UPLOAD ---
if ($action === 'upload') {
    if (!isset($_FILES['file'])) { echo json_encode(['message' => 'No file.']); exit; }
    $file = $_FILES['file'];
    $text = DocumentProcessor::extractText($file['tmp_name'], pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (strlen($text) < 100) { echo json_encode(['message' => 'PDF unreadable.']); exit; }

    $chunks = DocumentProcessor::chunkText($text);
    file_put_contents($storeFile, json_encode($chunks));
    echo json_encode(['message' => count($chunks) . ' sections indexed. Ask away!']);
    exit;
}

// --- ASK (Fixed Retrieval) ---
if ($action === 'ask') {
    $input = json_decode(file_get_contents('php://input'), true);
    $question = strtolower($input['question'] ?? '');
    $data = json_decode(@file_get_contents($storeFile), true);
    
    if (!$data) { echo json_encode(['answer' => 'Please upload a document first.']); exit; }

    // Smart Keyword Search: Score chunks based on question words
    $scoredChunks = [];
    $keywords = explode(' ', preg_replace('/[^a-z0-9 ]/', '', $question));
    
    foreach ($data as $chunk) {
        $score = 0;
        $chunkText = strtolower($chunk['text']);
        foreach ($keywords as $word) {
            if (strlen($word) > 3 && strpos($chunkText, $word) !== false) {
                $score += 2; // Match found
            }
        }
        if ($score > 0) $scoredChunks[] = ['text' => $chunk['text'], 'score' => $score];
    }

    // Sort by relevance score
    usort($scoredChunks, function($a, $b) { return $b['score'] - $a['score']; });
    
    // Pick top 5 matches
    $bestMatches = array_slice($scoredChunks, 0, 5);
    $context = "";

    if (empty($bestMatches)) {
        // Fallback to first few pages if no specific keywords match
        foreach (array_slice($data, 0, 4) as $c) { $context .= $c['text'] . "\n\n"; }
    } else {
        foreach ($bestMatches as $c) { $context .= $c['text'] . "\n\n"; }
    }

    $chat = new ChatHandler($key);
    echo json_encode($chat->ask($input['question'], $context));
    exit;
}