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

// --- UPLOAD (receives raw text from browser, no PHP PDF parsing needed) ---
if ($action === 'upload') {
    $input = json_decode(file_get_contents('php://input'), true);
    $text = $input['text'] ?? '';

    if (strlen(trim($text)) < 100) {
        echo json_encode(['message' => 'No text received. Please try again.']);
        exit;
    }

    $chunks = DocumentProcessor::chunkText($text);
    $docId = uniqid();
    $storeFile = sys_get_temp_dir() . '/askhoa_' . $docId . '.json';
    file_put_contents($storeFile, json_encode($chunks));

    echo json_encode([
        'message' => count($chunks) . ' sections indexed. Ask away!',
        'docId'   => $docId
    ]);
    exit;
}

// --- ASK ---
if (!$docId) {
    echo json_encode(['answer' => 'Missing document ID. Please re-upload.']);
    exit;
}

if ($action === 'ask') {
    $input = json_decode(file_get_contents('php://input'), true);
    $question = $input['question'] ?? '';
    $data = json_decode(@file_get_contents($storeFile), true);

    if (!$data) {
        echo json_encode(['answer' => 'Please upload a document first.']);
        exit;
    }

    // Smart keyword scoring
    $questionLower = strtolower($question);
    $keywords = explode(' ', preg_replace('/[^a-z0-9 ]/', '', $questionLower));
    $scoredChunks = [];

    foreach ($data as $chunk) {
        $chunkText = strtolower($chunk['text']);
        $score = 0;

        if (strpos($chunkText, $questionLower) !== false) $score += 20;

        foreach ($keywords as $word) {
            if (strlen($word) > 3) {
                $score += substr_count($chunkText, $word) * 4;
            }
        }

        if ($score > 0) {
            $scoredChunks[] = ['text' => $chunk['text'], 'score' => $score];
        }
    }

    usort($scoredChunks, fn($a, $b) => $b['score'] - $a['score']);
    $bestMatches = array_slice($scoredChunks, 0, 5);

    $context = '';
    if (empty($bestMatches)) {
        foreach (array_slice($data, 0, 4) as $c) { $context .= $c['text'] . "\n\n"; }
    } else {
        foreach ($bestMatches as $c) { $context .= $c['text'] . "\n\n"; }
    }

    $chat = new ChatHandler($key);
    echo json_encode($chat->ask($question, $context));
    exit;
}
