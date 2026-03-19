<?php
// askhoa_api.php
require_once 'DocumentProcessor.php';
require_once 'ChatHandler.php';

session_start();
header('Content-Type: application/json');

$config = include(__DIR__ . '/../config/env.php');
$key = $config['OPENAI_API_KEY'] ?? '';

$docId = $_GET['docId'] ?? null;
$storeFile = $docId 
    ? sys_get_temp_dir() . '/askhoa_' . $docId . '.json'
    : null;

$action = $_GET['action'] ?? '';

// --- UPLOAD ---
if ($action === 'upload') {
    if (!isset($_FILES['file'])) { echo json_encode(['message' => 'No file.']); exit; }
    $file = $_FILES['file'];
    $text = DocumentProcessor::extractText($file['tmp_name'], pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (strlen($text) < 100) { echo json_encode(['message' => 'PDF unreadable.']); exit; }

    $chunks = DocumentProcessor::chunkText($text);

    $docId = uniqid();
    $storeFile = sys_get_temp_dir() . '/askhoa_' . $docId . '.json';

    file_put_contents($storeFile, json_encode($chunks));

    echo json_encode([
        'message' => count($chunks) . ' sections indexed. Ask away!',
        'docId' => $docId
    ]);

    exit;
}

// --- ASK (Fixed Retrieval) ---
if (!$docId) {

    echo json_encode([
        'debug' => [
        'docId' => $docId ?? 'NULL',
        'storeFile' => $storeFile ?? 'NULL',
        'file_exists' => file_exists($storeFile),
        'file_size' => file_exists($storeFile) ? filesize($storeFile) : 0
            ]
        ]);

    echo json_encode(['answer' => 'Missing document ID. Please re-upload.']);
    exit;
}

if ($action === 'ask') {
    $input = json_decode(file_get_contents('php://input'), true);
    $question = strtolower($input['question'] ?? '');
    $data = json_decode(@file_get_contents($storeFile), true);
    
    if (!$data) { echo json_encode(['answer' => 'Please upload a document first.']); exit; }

    // Smart Keyword Search: Score chunks based on question words
    $scoredChunks = [];
    $questionLower = strtolower($question);
    $keywords = explode(' ', preg_replace('/[^a-z0-9 ]/', '', $questionLower));
    
    foreach ($data as $chunk) {
        $chunkText = strtolower($chunk['text']);
        $score = 0;
        
        // Bonus for exact phrase match
        if (strpos($chunkText, $questionLower) !== false) $score += 20;
        
        // Word matches with count weighting
        foreach ($keywords as $word) {
            if (strlen($word) > 3) {
                $count = substr_count($chunkText, $word);
                $score += $count * 4;
            }
        }
        
        if ($score > 0) {
            $scoredChunks[] = ['text' => $chunk['text'], 'score' => $score];
        }
    }

    usort($scoredChunks, fn($a, $b) => $b['score'] - $a['score']);

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