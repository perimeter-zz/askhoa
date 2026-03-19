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
                    ['role' => 'system', 'content' => "You are an expert on HOA CC&Rs and bylaws.\n\nAnswer ONLY using the excerpts below. Quote the exact rule when possible.\nIf the information is not in the excerpts, reply exactly: 'Information not found in the provided document excerpts.'\nNever guess or use outside knowledge.\n\nAt the very end of your answer, on its own line, add:\nSource: [section title or 'document text']"],
                    ['role' => 'user', 'content' => $question . "\n\nExcerpts:\n" . $context]
                ],
                'temperature' => 0.0
            ])
        ]);

        $res = json_decode(curl_exec($ch), true);
        $raw = $res['choices'][0]['message']['content'] ?? "Error reaching AI.";
        
        $parts = explode('Source:', $raw, 2);
        return [
            'answer' => trim($parts[0]),
            'citation' => isset($parts[1]) ? 'Source:' . trim($parts[1]) : 'Source: [document text]'
        ];
    }
}