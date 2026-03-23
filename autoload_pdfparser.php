<?php
/**
 * Autoloader for smalot/pdfparser library
 * Recursively requires all PHP files in src/Smalot/PdfParser
 * Uses multiple passes to handle circular dependencies
 */

function autoload_pdfparser($dir = null, $maxPasses = 5) {
    if ($dir === null) {
        $dir = __DIR__ . '/src/Smalot/PdfParser';
    }

    $loadedFiles = [];
    $pass = 0;

    while ($pass < $maxPasses) {
        $pass++;
        $filesLoaded = 0;
        
        $files = scandir($dir);
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $path = $dir . '/' . $file;
            
            if (is_dir($path)) {
                // Recursively load subdirectories
                autoload_pdfparser($path, 1);
            } elseif (pathinfo($file, PATHINFO_EXTENSION) === 'php' && !isset($loadedFiles[$path])) {
                // Try to load PHP files
                try {
                    ob_start();
                    require_once $path;
                    ob_end_clean();
                    $loadedFiles[$path] = true;
                    $filesLoaded++;
                } catch (Throwable $e) {
                    // Silently fail - might be a dependency issue, try again next pass
                    ob_end_clean();
                }
            }
        }
        
        // If no files loaded in this pass, we're done or stuck
        if ($filesLoaded === 0) break;
    }
}

// Auto-load all PDF parser classes
autoload_pdfparser();

