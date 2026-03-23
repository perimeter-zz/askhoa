<?php
/**
 * Smart autoloader for smalot/pdfparser library
 * Uses SPL to load classes on-demand instead of loading all files upfront
 */

spl_autoload_register(function ($class) {
    // Only handle Smalot\PdfParser classes
    if (strpos($class, 'Smalot\\PdfParser\\') !== 0) {
        return;
    }

    // Convert class name to file path
    // Smalot\PdfParser\Parser => src/Smalot/PdfParser/Parser.php
    // Smalot\PdfParser\Element\ElementArray => src/Smalot/PdfParser/Element/ElementArray.php
    $relativePath = str_replace('\\', '/', substr($class, strlen('Smalot\\')));
    $filePath = __DIR__ . '/src/' . $relativePath . '.php';

    if (file_exists($filePath)) {
        require_once $filePath;
    }
}, true);


