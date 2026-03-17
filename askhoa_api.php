<?php
require_once 'DocumentProcessor.php';
require_once 'ChatHandler.php';

session_start();
header('Content-Type: application/json');

// 1. Load Config (Adjust path as needed)
$config = include(__DIR__ . '/../config/env.php');
$apiKey = $config['OPENAI_API_KEY'] ?? '';

$store_file = sys_get_temp_dir() . '/askhoa_' . session_id() . '.json';
$action = $_GET['action'] ?? 'status';

if ($action === 'upload') {
    $file = $_FILES['file'];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    
    $text = DocumentProcessor::extractText($file['tmp_name'], $ext);
    
    if (strlen($text) < 150) {
        echo json_encode(['message' => "Extraction failed. PDF may be a scanned image or encrypted."]);
        exit;
    }

    $chunks = DocumentProcessor::chunkText($text);
    file_put_contents($store_file, json_encode(['filename' => $file['name'], 'chunks' => $chunks]));
    echo json_encode(['message' => "Document indexed: " . count($chunks) . " sections found."]);
    exit;
}

if ($action === 'ask') {
    $input = json_decode(file_get_contents('php://input'), true);
    $question = $input['question'] ?? '';
    
    $data = json_decode(file_get_contents($store_file), true);
    if (!$data) { echo json_encode(['answer' => "Please upload a file first."]); exit; }

    // Simple keyword search for context
    $context = "";
    foreach (array_slice($data['chunks'], 0, 5) as $c) { $context .= $c['text'] . "\n"; }

    $chat = new ChatHandler($apiKey);
    $answer = $chat->ask($question, $context);
    
    echo json_encode(['answer' => $answer]);
    exit;
}