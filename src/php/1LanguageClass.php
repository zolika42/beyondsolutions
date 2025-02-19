<?php
class Language {
    public $lang;
    public $defaultLanguage = 'en';
    public $languageKey = 'lang';

    public function __construct($lang = 'en') {
        $sessionLang = null;
        if (isset($_SESSION['lang'])) {
            $sessionLang = $_SESSION['lang'];
        }
        $lang = $_GET[$this->getLanguageKey()] ?? $sessionLang ?? $this->defaultLanguage;
        $this->setLanguage($lang);
    }

    public function getLanguage() {
        return $this->lang;
    }

    public function getLanguageKey() {
        return $this->languageKey;
    }

    public function setLanguage($lang) {
        $this->lang = $lang;
        $_SESSION['lang'] = $lang;
    }

    public function setDefaultLanguage() {
        $this->lang = $this->defaultLanguage;
    }

    public function loadLanguage() {
        $file = __DIR__ . "/../languages/$this->lang.json";
        if (!file_exists($file)) {
            $this->setDefaultLanguage();
            $file = __DIR__ . "/../languages/$this->lang.json";
        }
        return json_decode(file_get_contents($file), true);
    }
}

$languageClass = new Language();
global $config;
$config['translations'] = $languageClass->loadLanguage();
$config['siteLanguage'] = $languageClass->getLanguage();
