<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__.'/askhoa_error.log');
error_reporting(E_ALL);

// askhoa_api.php
require_once 'DocumentProcessor.php';
require_once 'ChatHandler.php';

// Load PDF parser library with autoloader
require_once __DIR__ . '/autoload_pdfparser.php';
use Smalot\PdfParser\Parser;

session_start();
header('Content-Type: application/json; charset=utf-8');
ob_start();

$config = include(__DIR__ . '/../config/env.php');
$key = $config['OPENAI_API_KEY'] ?? '';

$storageDir = __DIR__ . '/../storage';
if (!is_dir($storageDir)) mkdir($storageDir, 0755, true);

$docId = $_GET['docId'] ?? null;
$storeFile = $docId
    ? $storageDir . '/askhoa_' . $docId . '.json'
    : null;

$action = $_GET['action'] ?? '';

$action = $_GET['action'] ?? '';

// --- UPLOAD (receives PDF file, extracts text server-side) ---
if ($action === 'upload') {
    if (!isset($_FILES['file'])) {
        echo json_encode(['message' => 'No file uploaded.']);
        exit;
    }

    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['message' => 'File upload error: ' . $file['error']]);
        exit;
    }

    $allowedTypes = ['application/pdf', 'text/plain'];
    if (!in_array($file['type'], $allowedTypes)) {
        echo json_encode(['message' => 'Only PDF or TXT files allowed.']);
        exit;
    }

    $maxSize = 10 * 1024 * 1024; // 10MB
    if ($file['size'] > $maxSize) {
        echo json_encode(['message' => 'File too large (max 10MB).']);
        exit;
    }

    $tempPath = $file['tmp_name'];
    $text = '';

    // Reject binary files masquerading as text/plain via magic-byte check
    if ($file['type'] === 'text/plain') {
        $header = file_get_contents($tempPath, false, null, 0, 12);
        $binarySignatures = ["\xFF\xD8\xFF", "\x89PNG", "GIF8", "%PDF", "PK\x03\x04"];
        foreach ($binarySignatures as $sig) {
            if (str_starts_with($header, $sig)) {
                echo json_encode(['message' => 'File content does not match declared type.']);
                exit;
            }
        }
    }

    try {
        if ($file['type'] === 'text/plain') {
            $text = file_get_contents($tempPath);
        } else {
            // Extract text from PDF using smalot/pdfparser
            $parser = new Parser();
            $pdf = $parser->parseFile($tempPath);
            $text = $pdf->getText();
        }

        if (strlen(trim($text)) < 100) {
            echo json_encode(['message' => 'This document appears to be a scanned (image-based) PDF. AskHOA requires a text-based PDF. Try re-saving the document as "PDF with text" from your HOA management software, or copy and paste the content into a .txt file.']);
            exit;
        }

        $chunks = DocumentProcessor::chunkText($text);
        $docId = uniqid();
        $storeFile = $storageDir . '/askhoa_' . $docId . '.json';
        file_put_contents($storeFile, json_encode($chunks));

        echo json_encode([
            'message' => count($chunks) . ' sections indexed. Ask away!',
            'docId'   => $docId
        ]);
    } catch (Exception $e) {
        echo json_encode(['message' => 'Error processing file: ' . $e->getMessage()]);
    }
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

// Clean up any stray output before sending response
$output = ob_get_clean();
if ($output !== '') {
    error_log("askhoa stray output: " . trim($output));
}