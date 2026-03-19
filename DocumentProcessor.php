<?php
class DocumentProcessor {
    public static function extractText($path, $ext) {
        if ($ext === 'txt') {
            return file_get_contents($path);
        }

        $pdf = file_get_contents($path);
        if (empty($pdf)) return '';

        $text = '';

        // Best extraction: BT/ET blocks + all text operators (Tj, TJ, ', ")
        if (preg_match_all('/BT(.*?)ET/s', $pdf, $blocks)) {
            foreach ($blocks[1] as $block) {
                if (preg_match_all('/\(([^)]*?)\)\s*[TjTJ\'\"]/s', $block, $matches)) {
                    $text .= ' ' . implode(' ', $matches[1]);
                }
            }
        }

        // Direct Tj/TJ fallback
        if (strlen(trim($text)) < 400) {
            preg_match_all('/\(([^)]*?)\)\s*[TjTJ]/s', $pdf, $matches);
            $text = implode(' ', $matches[1]);
        }

        // Decode PDF escapes (\( \), \\, octal \nnn)
        $text = preg_replace_callback('/\\\\([()\\\\])|\\\\([0-7]{1,3})/', function ($m) {
            return isset($m[2]) ? chr(octdec($m[2])) : $m[1];
        }, $text);

        // Strong fallback for stubborn PDFs
        if (strlen(trim($text)) < 500) {
            $text = preg_replace('/[^\x20-\x7E\s\n\r\t]/', '', $pdf);
            $text = preg_replace('/\s+/', ' ', $text);
        }

        return self::clean($text);
    }

    private static function clean($text) {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = preg_replace('/Page \d+ of \d+/i', '', $text); // remove junk headers
        return trim($text);
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