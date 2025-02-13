<?php
class MinifierClass
{
    private string $cssDirectory;
    private string $jsDirectory;

    public function __construct()
    {
        global $config;
        $this->cssDirectory = rtrim(__DIR__ . '/../themes/' . $config['theme'] . '/css', '/');
        $this->jsDirectory = rtrim(__DIR__ . '/../themes/' . $config['theme'] . '/js', '/');
    }

    /**
     * Minify CSS content.
     *
     * @param string $cssContent The CSS content to minify
     * @return string|null The minified CSS content
     */
    public function minifyCSS(string $cssContent): ?string
    {
        return preg_replace([
            '/\/\*.*?\*\//s',   // Remove multi-line comments
            '/\s{2,}/',         // Remove extra whitespace
            '/\s*([{};:>])\s*/'  // Remove space around CSS special characters
        ], ['', ' ', '$1'], $cssContent);
    }

    /**
     * Minify JS content.
     *
     * @param string $jsContent The JS content to minify
     * @return string|null The minified JS content
     */
    public function minifyJS(string $jsContent): ?string
    {
        return preg_replace([
            '/\/\*.*?\*\//s',   // Remove multi-line comments
            '/\/\/.*$/m',       // Remove single-line comments
            '/\s{2,}/',         // Remove extra whitespace
            '/\s*([{};:>])\s*/'  // Remove space around JS special characters
        ], ['', '', ' ', '$1'], $jsContent);
    }

    /**
     * Combine all CSS files in the CSS directory (excluding already minified ones),
     * ensuring that style.css is added last, minify the combined content,
     * and write to style.min.css.
     *
     * @return int|null The number of CSS files processed.
     * @throws Exception If the CSS directory is not found.
     */
    public function combineAndMinifyCSS(): ?int
    {
        if (!is_dir($this->cssDirectory)) {
            throw new Exception("CSS directory not found: {$this->cssDirectory}");
        }

        $cssFiles = glob("{$this->cssDirectory}/*.css");
        $combinedContent = '';
        $processedFiles = 0;
        $styleCssContent = '';

        foreach ($cssFiles as $file) {
            // Skip files that already have '.min.' in their filename
            if (strpos($file, '.min.') !== false) {
                continue;
            }
            // If the file is style.css, store its content to append it last
            if (basename($file) === 'style.css') {
                $styleCssContent = file_get_contents($file) . "\n";
                $processedFiles++;
                continue;
            }
            $originalContent = file_get_contents($file);
            $combinedContent .= $originalContent . "\n"; // Separate files with a newline
            $processedFiles++;
        }

        // Append style.css content at the end if available
        if ($styleCssContent) {
            $combinedContent .= $styleCssContent;
        }

        // Minify the combined CSS content
        $minifiedContent = $this->minifyCSS($combinedContent);
        $minifiedFile = "{$this->cssDirectory}/style.min.css";
        file_put_contents($minifiedFile, $minifiedContent);

        return $processedFiles;
    }

    /**
     * Combine all JS files in the JS directory (excluding already minified ones),
     * minify the combined content, and write to script.min.js.
     *
     * @return int|null The number of JS files processed.
     * @throws Exception If the JS directory is not found.
     */
    public function combineAndMinifyJS(): ?int
    {
        if (!is_dir($this->jsDirectory)) {
            throw new Exception("JS directory not found: {$this->jsDirectory}");
        }

        $jsFiles = glob("{$this->jsDirectory}/*.js");
        $combinedContent = '';
        $processedFiles = 0;

        foreach ($jsFiles as $file) {
            // Skip files that already have '.min.' in their filename
            if (strpos($file, '.min.') !== false) {
                continue;
            }
            $originalContent = file_get_contents($file);
            $combinedContent .= $originalContent . "\n"; // Separate files with a newline
            $processedFiles++;
        }

        // Minify the combined JS content
        $minifiedContent = $this->minifyJS($combinedContent);
        $minifiedFile = "{$this->jsDirectory}/script.min.js";
        file_put_contents($minifiedFile, $minifiedContent);

        return $processedFiles;
    }

    /**
     * Combine and minify both CSS and JS files.
     *
     * @return int|null The total number of files processed.
     * @throws Exception If either CSS or JS directory is not found.
     */
    public function minifyAll(): ?int
    {
        $numberCSS = $this->combineAndMinifyCSS();
        $numberJS  = $this->combineAndMinifyJS();
        return $numberCSS + $numberJS;
    }
}

try {
    global $config;
    if (isset($config['environment']) && $config['environment'] === 'dev') {
        $logger = new LoggerClass();
        $minifier = new MinifierClass();
        $counter = $minifier->minifyAll(); // Combine and minify CSS and JS files
        $logger->info("Minification complete. Number of files processed: $counter");
    } else {
        return;
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
