<?php
class DocumentProcessor {

    // PDF parsing now handled server-side via smalot/pdfparser.
    // This class chunks the clean text received from the server-side extraction.

    public static function chunkText($text, $size = 800, $overlap = 100) {
        $words = explode(' ', $text);
        $chunks = [];
        $total = count($words);

        for ($i = 0; $i < $total; $i += ($size - $overlap)) {
            $chunk = implode(' ', array_slice($words, $i, $size));
            if (trim($chunk) !== '') {
                $chunks[] = ['text' => $chunk];
            }
            if ($i + $size >= $total) break;
        }

        return $chunks;
    }
}
