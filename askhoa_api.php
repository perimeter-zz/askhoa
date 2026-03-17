<?php
// Removed 'src/' from paths
require_once 'DocumentProcessor.php';
require_once 'ChatHandler.php';

session_start();
header('Content-Type: application/json');

$config = include(__DIR__ . '/../config/env.php');
$key = $config['OPENAI_API_KEY'] ?? '';
$storeFile = sys_get_temp_dir() . '/askhoa_' . session_id() . '.json';

$action = $_GET['action'] ?? '';

if ($action === 'upload') {
    if (!isset($_FILES['file'])) { echo json_encode(['message' => 'No file.']); exit; }
    $file = $_FILES['file'];
    $text = DocumentProcessor::extractText($file['tmp_name'], pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (strlen($text) < 100) { echo json_encode(['message' => 'Error: PDF unreadable.']); exit; }

    $chunks = DocumentProcessor::chunkText($text);
    file_put_contents($storeFile, json_encode($chunks));
    echo json_encode(['message' => count($chunks) . ' sections indexed. Ask away!']);
    exit;
}

if ($action === 'ask') {
    $input = json_decode(file_get_contents('php://input'), true);
    $question = $input['question'] ?? '';
    $data = json_decode(@file_get_contents($storeFile), true);
    
    if (!$data) { echo json_encode(['answer' => 'Upload a file first.']); exit; }

    // KEYWORD SEARCH: Find chunks containing words from the question
    $keywords = explode(' ', strtolower($question));
    $relevantChunks = [];
    foreach ($data as $chunk) {
        foreach ($keywords as $word) {
            if (strlen($word) > 3 && strpos(strtolower($chunk['text']), $word) !== false) {
                $relevantChunks[] = $chunk['text'];
                break;
            }
        }
        if (count($relevantChunks) >= 6) break;
    }

    // Fallback if no keywords found
    $context = !empty($relevantChunks) ? implode("\n\n", $relevantChunks) : implode("\n\n", array_column(array_slice($data, 0, 4), 'text'));

    $chat = new ChatHandler($key);
    echo json_encode($chat->ask($question, $context));
    exit;
}