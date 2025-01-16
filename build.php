<?php
/**
 * Build script to process and minify assets and templates into a `dist` directory.
 */

require_once __DIR__ . '/src/php/MinifierClass.php';
require_once __DIR__ . '/src/php/HTMLTemplateClass.php';

// Load the global configuration
$config = include __DIR__ . '/config.php';

// Define directories
$srcDir = __DIR__ . '/src';
$distDir = __DIR__ . '/dist';
$assetsDir = "$srcDir/assets";
$htmlDir = "$srcDir/html";

// Ensure the `dist` directory exists
if (!is_dir($distDir)) {
    mkdir($distDir, 0755, true);
}

// Function to copy non-processed files (images, fonts, etc.)
function copyDirectory($source, $destination)
{
    if (!is_dir($source)) {
        echo "Warning: Source directory $source does not exist.\n";
        return;
    }
    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }

    $files = scandir($source);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $srcPath = "$source/$file";
        $destPath = "$destination/$file";

        if (is_dir($srcPath)) {
            copyDirectory($srcPath, $destPath);
        } else {
            copy($srcPath, $destPath);
        }
    }
}

// Copy assets (images, fonts) to the `dist` folder
echo "Copying non-processed assets to dist...\n";
copyDirectory("$assetsDir/images", "$distDir/assets/images");
copyDirectory("$assetsDir/fonts", "$distDir/assets/fonts");

// Minify CSS and JS files using MinifierClass
$minifier = new MinifierClass();

// Minify CSS files
echo "Minifying CSS files...\n";
$cssDistDir = "$distDir/assets/css";
if (!is_dir($cssDistDir)) {
    mkdir($cssDistDir, 0755, true);
}
$cssFiles = glob("$assetsDir/css/*.css");
foreach ($cssFiles as $cssFile) {
    $minifiedContent = $minifier->minifyCSS(file_get_contents($cssFile));
    $minifiedPath = str_replace("$assetsDir/css", $cssDistDir, str_replace('.css', '.min.css', $cssFile));
    file_put_contents($minifiedPath, $minifiedContent);
}

// Minify JS files
echo "Minifying JS files...\n";
$jsDistDir = "$distDir/assets/js";
if (!is_dir($jsDistDir)) {
    mkdir($jsDistDir, 0755, true);
}
$jsFiles = glob("$assetsDir/js/*.js");
foreach ($jsFiles as $jsFile) {
    $minifiedContent = $minifier->minifyJS(file_get_contents($jsFile));
    $minifiedPath = str_replace("$assetsDir/js", $jsDistDir, str_replace('.js', '.min.js', $jsFile));
    file_put_contents($minifiedPath, $minifiedContent);
}

// Process HTML templates using HTMLTemplateClass
echo "Processing HTML templates...\n";
$htmlDistDir = "$distDir/html";
if (!is_dir($htmlDistDir)) {
    mkdir($htmlDistDir, 0755, true);
}

$htmlFiles = glob("$htmlDir/*.html");
foreach ($htmlFiles as $htmlFile) {
    try {
        // Initialize the HTMLTemplateClass for rendering
        $templateProcessor = new HTMLTemplateClass();
        $renderedContent = $templateProcessor->render(); // Render HTML content

        if ($config['environment'] !== 'dev') {
            $renderedContent = $templateProcessor->minifyHTML($renderedContent);
        }

        $minifiedPath = str_replace("$htmlDir", $htmlDistDir, str_replace('.html', '.min.html', $htmlFile));
        file_put_contents($minifiedPath, $renderedContent);
        echo "Processed: " . basename($htmlFile) . " -> " . basename($minifiedPath) . "\n";
    } catch (Exception $e) {
        echo "Error processing " . basename($htmlFile) . ": " . $e->getMessage() . "\n";
    }
}

echo "Build process completed successfully. Output available in the `dist` directory.\n";
?>
