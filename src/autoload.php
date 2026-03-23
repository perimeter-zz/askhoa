<?php
/**
 * Autoloader for smalot/pdfparser library
 * Recursively requires all PHP files in Smalot/PdfParser
 */

function autoload_pdfparser($dir = null) {
    if ($dir === null) {
        $dir = __DIR__ . '/Smalot/PdfParser';
    }

    $files = scandir($dir);
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $path = $dir . '/' . $file;
        
        if (is_dir($path)) {
            // Recursively load subdirectories
            autoload_pdfparser($path);
        } elseif (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
            // Require PHP files
            if (!class_exists('Smalot\PdfParser\Parser', false)) {
                require_once $path;
            }
        }
    }
}

// Auto-load all PDF parser classes
autoload_pdfparser();
