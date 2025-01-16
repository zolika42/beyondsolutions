<?php
class Language {
    public $lang;
    public $defaultLanguage = 'en';

    public function __construct($lang = 'en') {
        $lang = isset($_GET['lang']) ? $_GET['lang'] : $this->defaultLanguage;
        $this->setLanguage($lang);
    }

    public function getLanguage() {
        return $this->lang;
    }

    public function setLanguage($lang) {
        $this->lang = $lang;
    }

    public function setDefaultLanguage() {
        $this->lang = 'en';
    }

    public function loadLanguage() {
        $file = __DIR__ . "/../languages/$this->lang.json";
        if (!file_exists($file)) {
            $this->setDefaultLanguage();
        }
        return json_decode(file_get_contents($file), true); 
    }
}

$languageClass = new Language();
global $config;
$config['translations'] = $languageClass->loadLanguage();
$config['siteLanguage'] = $languageClass->getLanguage();
?>
