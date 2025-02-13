<?php
/**
 * Autoload all PHP files from the specified directory by including them,
 * ensuring that 6HTMLTemplateClass.php is loaded last.
 */


// Define the directory to scan for PHP files
$phpDirectory = __DIR__;

include_once $phpDirectory . '/../../config.php';

// Verify the directory exists
if (!is_dir($phpDirectory)) {
    throw new Exception("PHP directory not found: $phpDirectory");
}

// Get all PHP files in the directory
$phpFiles = glob("$phpDirectory/*.php");

foreach ($phpFiles as $file) {
    include_once $file;
}