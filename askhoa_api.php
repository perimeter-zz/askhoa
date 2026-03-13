<?php
// askhoa_api.php
ini_set('display_errors', 0);
error_reporting(E_ALL);
set_error_handler(function($errno, $errstr) {
    echo json_encode(['message' => "PHP Error: $errstr"]);
    exit;
});
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR])) {
        echo json_encode(['message' => 'PHP Fatal: ' . $error['message']]);
    }
});
// ─────────────────────────────────────────────────────────────
//  AskHOA Backend  |  Pure PHP + OpenAI
//  Drop this in willbotti/askhoa/ on Hostinger — just works.
//  Set your key in the line below (or use an .env file).
// ─────────────────────────────────────────────────────────────

// Update this line at the top of askhoa_api.php
$config = include(__DIR__ . '/../config/env.php');
define('OPENAI_API_KEY', $config['OPENAI_API_KEY'] ?? 'fallback-if-needed');

define('OPENAI_MODEL',   'gpt-4o-mini');
define('CHUNK_SIZE',     800);   // words per chunk
define('CHUNK_OVERLAP',  100);
define('TOP_CHUNKS',     4);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

// ── Persistent chunk storage (one file per session via temp dir) ──
// Hostinger keeps /tmp available between requests in the same session.
session_start();
$store_file = sys_get_temp_dir() . '/askhoa_' . session_id() . '.json';

function load_store(): array {
    global $store_file;
    if (file_exists($store_file)) {
        return json_decode(file_get_contents($store_file), true) ?? [];
    }
    return ['filename' => null, 'chunks' => []];
}

function save_store(array $store): void {
    global $store_file;
    file_put_contents($store_file, json_encode($store));
}

// ──────────────────────────────────────────────
//  HELPERS
// ──────────────────────────────────────────────

function extract_text_from_pdf(string $filepath): string {
    $raw = file_get_contents($filepath);
    $text = '';

    // Method 1: BT...ET text blocks
    if (preg_match_all('/BT\s*(.*?)\s*ET/s', $raw, $blocks)) {
        foreach ($blocks[1] as $block) {
            if (preg_match_all('/\(((?:[^()\\\\]|\\\\.)*)\)\s*Tj/', $block, $tj)) {
                foreach ($tj[1] as $t) { $text .= stripslashes($t) . ' '; }
            }
            if (preg_match_all('/\[((?:[^\[\]]|\((?:[^()\\\\]|\\\\.)*\))*)\]\s*TJ/', $block, $tj)) {
                foreach ($tj[1] as $t) {
                    preg_match_all('/\(((?:[^()\\\\]|\\\\.)*)\)/', $t, $strs);
                    foreach ($strs[1] as $s) { $text .= stripslashes($s) . ' '; }
                }
            }
        }
    }

    // Method 2: Fallback for simpler PDFs
    if (strlen(trim($text)) < 100) {
        preg_match_all('/\(([^\)]{3,})\)/', $raw, $matches);
        $text = implode(' ', $matches[1]);
    }

    $text = preg_replace('/[^\x20-\x7E\n\r]/', ' ', $text);
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}

function extract_text_from_txt(string $filepath): string {
    return file_get_contents($filepath);
}

function chunk_text(string $text): array {
    $words  = preg_split('/\s+/', trim($text));
    $chunks = [];
    $start  = 0;
    $idx    = 1;
    $total  = count($words);
    while ($start < $total) {
        $end        = min($start + CHUNK_SIZE, $total);
        $chunk_text = implode(' ', array_slice($words, $start, $end - $start));
        $chunks[]   = ['index' => $idx, 'text' => $chunk_text];
        $idx++;
        $start += CHUNK_SIZE - CHUNK_OVERLAP;
    }
    return $chunks;
}

function find_relevant_chunks(string $question, array $chunks): array {
    $stop = ['the','a','an','is','are','was','were','be','been','i','my','can',
             'do','does','what','how','when','where','who','will','would','should','of','in','to'];
    preg_match_all('/\w+/', strtolower($question), $m);
    $q_words = array_diff(array_unique($m[0]), $stop);

    $scored = [];
    foreach ($chunks as $chunk) {
        preg_match_all('/\w+/', strtolower($chunk['text']), $cm);
        $c_words = array_unique($cm[0]);
        $score   = count(array_intersect($q_words, $c_words));
        $scored[] = ['score' => $score, 'chunk' => $chunk];
    }
    usort($scored, fn($a, $b) => $b['score'] - $a['score']);
    $top = array_slice($scored, 0, TOP_CHUNKS);
    $result = array_column($top, 'chunk');
    return $result ?: array_slice($chunks, 0, TOP_CHUNKS);
}

function build_context(array $chunks): string {
    $parts = [];
    foreach ($chunks as $c) {
        $parts[] = "[Chunk {$c['index']}]\n{$c['text']}";
    }
    return implode("\n\n---\n\n", $parts);
}

function openai_chat(string $system, string $user): string {
    $payload = json_encode([
        'model'       => OPENAI_MODEL,
        'temperature' => 0.2,
        'max_tokens'  => 400,
        'messages'    => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => $user]
        ]
    ]);

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENAI_API_KEY
        ]
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    return $data['choices'][0]['message']['content'] ?? 'Error: no response from OpenAI.';
}

// ──────────────────────────────────────────────
//  ROUTES  (action = upload | ask | status)
// ──────────────────────────────────────────────

$action = $_GET['action'] ?? 'status';

// ── UPLOAD ──
if ($action === 'upload') {
    if (empty($_FILES['file'])) {
        echo json_encode(['message' => 'No file attached.']); exit;
    }
    $file     = $_FILES['file'];
    $filename = basename($file['name']);
    $ext      = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $tmp      = $file['tmp_name'];

    if ($ext === 'pdf') {
        $text = extract_text_from_pdf($tmp);
    } elseif ($ext === 'txt') {
        $text = extract_text_from_txt($tmp);
    } else {
        echo json_encode(['message' => 'Only PDF or TXT files supported.']); exit;
    }

    if (strlen(trim($text)) < 50) {
        echo json_encode(['message' => 'Could not extract text. Try a text-based (not scanned) PDF.']); exit;
    }

    $chunks = chunk_text($text);
    save_store(['filename' => $filename, 'chunks' => $chunks]);
    echo json_encode(['message' => "\"$filename\" processed — " . count($chunks) . " sections indexed. Ask away!"]);
    exit;
}

// ── ASK ──
if ($action === 'ask') {
    $body     = json_decode(file_get_contents('php://input'), true);
    $question = trim($body['question'] ?? '');

    if (!$question) {
        echo json_encode(['answer' => 'Please enter a question.', 'citation' => '']); exit;
    }

    $store = load_store();
    if (empty($store['chunks'])) {
        echo json_encode(['answer' => 'No document loaded yet. Please upload your HOA bylaws or CC&Rs first.', 'citation' => '']); exit;
    }

    $relevant = find_relevant_chunks($question, $store['chunks']);
    $context  = build_context($relevant);

    $system = "You are AskHOA, a helpful assistant that answers homeowner questions about HOA governing documents (CC&Rs, bylaws, rules & regulations).\n\nRULES:\n1. Answer ONLY from the provided document excerpts below.\n2. If the answer is not in the excerpts, say so clearly — do not guess.\n3. Keep answers concise (2–4 sentences) and plain-English.\n4. When relevant, mention the section or page number from the source.\n5. End your answer with a one-line citation like: \"Source: [Page X] / [Section Y]\"\n\nDOCUMENT EXCERPTS:\n$context";

    $raw = openai_chat($system, $question);

    // Split out the Source: citation for the UI
    $answer   = $raw;
    $citation = '';
    if (strpos($raw, 'Source:') !== false) {
        $parts    = explode('Source:', $raw, 2);
        $answer   = trim($parts[0]);
        $citation = 'Source: ' . trim($parts[1]);
    }

    echo json_encode(['answer' => $answer, 'citation' => $citation]);
    exit;
}

// ── STATUS ──
$store = load_store();
echo json_encode([
    'loaded'   => !empty($store['filename']),
    'filename' => $store['filename'] ?? null,
    'chunks'   => count($store['chunks'] ?? [])
]);
