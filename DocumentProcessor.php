<?php
class DocumentProcessor {

    // PDF parsing removed — text is now extracted client-side via pdf.js in the browser.
    // This class only handles chunking the clean text received from the frontend.

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
