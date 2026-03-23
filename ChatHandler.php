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
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json', 
                'Authorization: Bearer ' . $this->key
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system', 
                        'content' => "You are an expert on HOA CC&Rs and bylaws.
Answer ONLY using the excerpts below. Quote the exact rule when possible.
If the information is not in the excerpts, reply exactly: 'Information not found in the provided document excerpts.'
Never guess or use outside knowledge.

At the very end of your answer, on its own line, add:
Source: [section title or 'document text']"
                    ],
                    [
                        'role' => 'user', 
                        'content' => $question . "\n\nExcerpts:\n" . $context
                    ]
                ],
                'temperature' => 0.0
            ])
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            return [
                'answer' => 'cURL error: ' . curl_error($ch),
                'citation' => ''
            ];
        }

        $res = json_decode($response, true);

        if (!isset($res['choices'][0]['message']['content'])) {
            return [
                'answer' => 'API error: ' . json_encode($res),
                'citation' => ''
            ];
        }

        $raw = $res['choices'][0]['message']['content'];

        $parts = explode('Source:', $raw, 2);

        return [
            'answer' => trim($parts[0]),
            'citation' => isset($parts[1]) 
                ? 'Source:' . trim($parts[1]) 
                : 'Source: [document text]'
        ];
    }
}