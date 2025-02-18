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
        $this->themeDirectory = __DIR__ . '/../../public/' . $this->themeDirectory;

        if (!is_dir($this->themeDirectory)) {
            throw new Exception("Theme directory not found: {$this->themeDirectory}");
        }
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
     * Returns embedded CSS from the local CSS file as a <style> block.
     */
    private function getEmbeddedCSS(): string
    {
        $cssFilePath = $this->themeDirectory . '/css/style.min.css';
        if (file_exists($cssFilePath)) {
            $cssContent = file_get_contents($cssFilePath);
            // Optionally, further minify or process the CSS content if needed.
            return "<style>" . $cssContent . "</style>";
        }
        return "";
    }

    /**
     * @return false|string
     * @throws Exception
     */
    public function loadGeneratedHTML(): false|string
    {
        $filePath = __DIR__ . "/../../dist/" . $this->config['siteLanguage'] . "/index.html";
        //var_dump($filePath);exit();
        if (!file_exists($filePath)) {
            $this->render();
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

        // Get the embedded CSS
        $embeddedCSS = $this->getEmbeddedCSS();

        // Replace placeholders in the layout with actual content
        $htmlDocument = str_replace(
            ['{{head}}', '{{body}}', '{{footer}}', '{{local-css}}'],
            [$header, $body, $footer, $embeddedCSS],
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

    /**
     * @throws Exception
     */
    public function renderGeneratedHTML(): void
    {
        echo $this->loadGeneratedHTML();
    }
}

// Instantiate and render the template
$HTMLTemplateClass = new HTMLTemplateClass();
$HTMLTemplateClass->render();
