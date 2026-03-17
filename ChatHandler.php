<?php
class ChatHandler {
    private $key;

    public function __construct($key) {
        $this->key = $key;
    }

    public function ask($question, $context) {
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $this->key],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => "Answer from excerpts only. If not found, say 'Information not found in excerpts'. End with 'Source: [Page X] / [Section Y]'.\n\nContext:\n$context"],
                    ['role' => 'user', 'content' => $question]
                ],
                'temperature' => 0.1
            ])
        ]);

        $res = json_decode(curl_exec($ch), true);
        $raw = $res['choices'][0]['message']['content'] ?? "Error reaching AI.";
        
        $parts = explode('Source:', $raw);
        return [
            'answer' => trim($parts[0]),
            'citation' => isset($parts[1]) ? 'Source:' . trim($parts[1]) : 'Source: [N/A]'
        ];
    }
}