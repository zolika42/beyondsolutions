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
            '/\s*([{};:>])\s*/' // Remove space around CSS special characters
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
            '/\s*([{};:>])\s*/' // Remove space around JS special characters
        ], ['', '', ' ', '$1'], $jsContent);
    }

    /**
     * Minify all CSS files in the CSS directory, excluding files with ".min." in the filename.
     *
     * @throws Exception If the CSS directory is not found
     */
    public function minifyCSSFiles(): int|null
    {
        $counter = 0;
        if (!is_dir($this->cssDirectory)) {
            throw new Exception("CSS directory not found: {$this->cssDirectory}");
        }

        $cssFiles = glob("{$this->cssDirectory}/*.css");

        foreach ($cssFiles as $file) {
            // Skip files that already have '.min.' in their filename
            if (strpos($file, '.min.') !== false) {
                continue;
            }

            $minifiedFile = str_replace('.css', '.min.css', $file);
            $originalContent = file_get_contents($file);
            $minifiedContent = $this->minifyCSS($originalContent);
            file_put_contents($minifiedFile, $minifiedContent);
            $counter = $counter + 1;
        }
        return $counter;
    }

    /**
     * Minify all JS files in the JS directory, excluding files with ".min." in the filename.
     *
     * @throws Exception If the JS directory is not found
     */
    public function minifyJSFiles(): int|null
    {
        $counter = 0;
        if (!is_dir($this->jsDirectory)) {
            throw new Exception("JS directory not found: {$this->jsDirectory}");
        }

        $jsFiles = glob("{$this->jsDirectory}/*.js");

        foreach ($jsFiles as $file) {
            // Skip files that already have '.min.' in their filename
            if (strpos($file, '.min.') !== false) {
                continue;
            }

            $minifiedFile = str_replace('.js', '.min.js', $file);
            $originalContent = file_get_contents($file);
            $minifiedContent = $this->minifyJS($originalContent);
            file_put_contents($minifiedFile, $minifiedContent);
            $counter = $counter + 1;
        }
        return $counter;
    }

    /**
     * Minify all CSS and JS files, excluding those with ".min." in their filename.
     *
     * @throws Exception If either CSS or JS directory is not found
     */
    public function minifyAll(): ?int
    {
        $numberCSS = $this->minifyCSSFiles();
        $numberJS = $this->minifyJSFiles();
        return $numberCSS + $numberJS;
    }
}

try {
    global $config;
    if (isset($config['environment']) && $config['environment'] === 'dev') {
        $logger = new LoggerClass();
        $minifier = new MinifierClass();
        $counter = $minifier->minifyAll(); // Minify all files except those with '.min.'
        $logger->info("Minification complete. Number of files: $counter");
    } else {
        return;
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
