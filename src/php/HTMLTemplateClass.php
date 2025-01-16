<?php

class HTMLTemplateClass
{
    private string $themeDirectory;
    private mixed $config;

    /**
     * @throws Exception
     */
    public function __construct()
    {
        global $config;

        $this->config = $config;
        $selectedTheme = $config['theme'] ?? 'default';
        $this->themeDirectory = rtrim("themes/$selectedTheme", '/');

        if (!is_dir($this->themeDirectory)) {
            throw new Exception("Theme directory not found: {$this->themeDirectory}");
        }

        $this->render();
    }

    /**
     * Load an HTML/PHP file from the directory and execute it.
     * @throws Exception
     */
    private function loadHTMLFile($filename): false|string
    {
        $filePath = "{$this->themeDirectory}/html/$filename";
        if (!file_exists($filePath)) {
            throw new Exception("File not found in theme: $filePath");
        }

        // Start output buffering and include the file
        ob_start();
        $config = $this->config;
        include $filePath;
        return ob_get_clean();
    }

    /**
     * Minify HTML content.
     */
    public function minifyHTML($htmlContent): array|string|null
    {
        return preg_replace(
            [
                '/\s{2,}/',                // Remove excessive whitespace
                '/\>[\n\r\s]+</',          // Remove whitespace between tags
                '/<!--.*?-->/',            // Remove comments
            ],
            [
                ' ',
                '><',
                '',
            ],
            $htmlContent
        );
    }

    /**
     * Build the complete HTML document.
     * @throws Exception
     */
    public function buildHTML(): array|false|string|null
    {
        // Load each part of the HTML (processed as PHP)
        $layout = $this->loadHTMLFile('layout.html');
        $header = $this->loadHTMLFile('header.html');
        $body = $this->loadHTMLFile('body.html');
        $footer = $this->loadHTMLFile('footer.html');

        // Replace placeholders in the layout with actual content
        $htmlDocument = str_replace(
            ['{{head}}', '{{body}}', '{{footer}}'],
            [$header, $body, $footer],
            $layout
        );

        // Minify the HTML document if in production
        if ($this->config['environment'] !== 'dev') {
            $htmlDocument = $this->minifyHTML($htmlDocument);
        }

        return $htmlDocument;
    }

    /**
     * Render the HTML document.
     * @throws Exception
     */
    public function render(): void
    {
        echo $this->buildHTML();
    }
}

// Instantiate and render the template
$HTMLTemplateClass = new HTMLTemplateClass();
