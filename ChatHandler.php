<?php
class ChatHandler {
    private $apiKey;

    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
    }

    public function ask($question, $context) {
        $systemPrompt = "You are AskHOA. Answer ONLY from the excerpts. If not present, say 'I cannot find that in the documents.'\n\nExcerpts:\n$context";
        
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $question]
                ],
                'temperature' => 0.2
            ])
        ]);

        $response = curl_exec($ch);
        $data = json_decode($response, true);
        return $data['choices'][0]['message']['content'] ?? "Error connecting to AI service.";
    }
}