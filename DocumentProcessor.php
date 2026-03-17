<?php
class DocumentProcessor {
    public static function extractText($path, $ext) {
        if ($ext === 'txt') return file_get_contents($path);
        
        $content = file_get_contents($path);
        $text = "";

        // Attempt to find text between ( ) in PDF streams
        if (preg_match_all('/\((.*?)\) Tj/s', $content, $matches)) {
            $text = implode(' ', $matches[1]);
        }

        // Fallback: If regex failed, try a raw strip (works for some uncompressed PDFs)
        if (strlen(trim($text)) < 100) {
            $text = preg_replace('/[^a-zA-Z0-9\s\.\,\?\!]/', '', $content);
        }

        return self::cleanText($text);
    }

    private static function cleanText($text) {
        $text = html_entity_decode($text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    public static function chunkText($text, $size = 800, $overlap = 100) {
        $words = explode(' ', $text);
        $chunks = [];
        for ($i = 0; $i < count($words); $i += ($size - $overlap)) {
            $chunk = implode(' ', array_slice($words, $i, $size));
            $chunks[] = ['index' => count($chunks) + 1, 'text' => $chunk];
            if ($i + $size >= count($words)) break;
        }
        return $chunks;
    }
}