<?php
class DocumentProcessor {
    public static function extractText($path, $ext) {
        if ($ext === 'txt') return file_get_contents($path);
        
        $content = file_get_contents($path);
        $text = "";

        // Stream extraction
        if (preg_match_all('/\((.*?)\) Tj/s', $content, $matches)) {
            $text = implode(' ', $matches[1]);
        }

        // Fallback for flat PDFs
        if (strlen(trim($text)) < 150) {
            $text = preg_replace('/[^a-zA-Z0-9\s\.\,\?\!\-]/', '', $content);
        }

        return self::clean($text);
    }

    private static function clean($text) {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode($text)));
    }

    public static function chunkText($text, $size = 800, $overlap = 100) {
        $words = explode(' ', $text);
        $chunks = [];
        for ($i = 0; $i < count($words); $i += ($size - $overlap)) {
            $chunk = implode(' ', array_slice($words, $i, $size));
            $chunks[] = ['text' => $chunk];
            if ($i + $size >= count($words)) break;
        }
        return $chunks;
    }
}