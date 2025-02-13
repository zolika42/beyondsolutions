<?php

require_once __DIR__ . '/1LanguageClass.php';
require_once __DIR__ . '/6HTMLTemplateClass.php';

class StaticPageGenerator
{
    private string $outputDir;
    private HTMLTemplateClass $templateEngine;
    private Language $languageClass;

    public function __construct()
    {
        $this->templateEngine = new HTMLTemplateClass();
        $this->languageClass = new Language();
        $this->outputDir = __DIR__ . '/../../dist/' . $this->languageClass->getLanguage();
    }

    public function generateStaticPages(): void
    {
        if (!is_dir($this->getOutputDir())) {
            mkdir($this->getOutputDir(), 0755, true);
        }

        // Generate the fully processed HTML content
        $htmlContent = $this->templateEngine->buildHTML();
        $htmlContent = $this->templateEngine->minifyHTML($htmlContent);

        // Define the output file
        $outputFile = $this->getOutputDir() . '/index.html';
        file_put_contents($outputFile, $htmlContent);
    }

    public function getStaticPages() {
        return $this->getOutputDir() . '/index.html';
    }

    public function getOutputDir() {
        return $this->outputDir;
    }
}

/*$generator = new StaticPageGenerator();
$generator->generateStaticPages();*/
