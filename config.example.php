<?php
$config['environment'] = 'dev';
$config['theme'] = 'beyondstart';
$config['appName'] = 'BeyondStartSolution';
$config['siteLanguage'] = 'en';
$config['siteEmail'] = 'zoltan@beyondstart.solutions';

// Application password from gMail.
$config['siteEmailPassword'] = '';
if ($config['environment'] === 'dev') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Log
$config['logFile'] = __DIR__ . '/src/logs/app.log'; // Log file path
$config['maxLogSize'] = 10 * 1024 * 1024; // Max log size (10MB)
$config['logRetentionDays'] = 7; // Delete logs older than 7 days

//Re-Captcha (V2) settings. ONLY V2!!!
$config['recaptcha_secret'] = '';
$config['recaptcha_site_key'] = '';
