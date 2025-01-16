<?php
/**
 * Autoload all PHP files from the specified directory by including them,
 * ensuring that HTMLTemplateClass.php is loaded last.
 */

include_once '../config.php';

// Define the directory to scan for PHP files
$phpDirectory = __DIR__;

// Verify the directory exists
if (!is_dir($phpDirectory)) {
    throw new Exception("PHP directory not found: $phpDirectory");
}

// Get all PHP files in the directory
$phpFiles = glob("$phpDirectory/*.php");
include_once $phpDirectory . '/LanguageClass.php';

// Separate HTMLTemplateClass.php from other files
$htmlTemplateClassFile = null;
$otherPhpFiles = [];

// Separate HTMLTemplateClass.php to load it last
foreach ($phpFiles as $file) {
    if (basename($file) === 'HTMLTemplateClass.php') {
        $htmlTemplateClassFile = $file;
    } else if (basename($file) !== 'LanguageClass.php') {
        $otherPhpFiles[] = $file;
    }
}

// Include all other PHP files first
foreach ($otherPhpFiles as $file) {
    include_once $file;
}

// Include HTMLTemplateClass.php last
if ($htmlTemplateClassFile) {
    include_once $htmlTemplateClassFile;
}
