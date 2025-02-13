<?php
// Include PHPMailer files (adjust path as necessary)
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

// Include LoggerClass
require_once __DIR__ . '/2LoggerClass.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class ContactFormHandler
{
    private string $fullName;
    private string $company;
    private string $countryCode;
    private string $phone;
    private string $email;
    private string $message;
    private string $recaptchaSecret;
    private LoggerClass $logger;

    public function __construct()
    {
        global $config;
        $this->recaptchaSecret = $config['recaptcha_secret'];
        $this->logger = new LoggerClass(); // Initialize LoggerClass
    }

    public function getFormUrl(): string
    {
        global $languageClass;
        $url = dirname($_SERVER['PHP_SELF']) . '/index.php';
        $lang = $languageClass->getLanguage();
        $languageKey = $languageClass->getLanguageKey();
        $queryParams[$languageKey] = $lang;
        $queryParams['endpoint'] = 'validateForm';
        $queryParams['callback'] = dirname($_SERVER['PHP_SELF']) . '/index.php';
        return $url . '?' . http_build_query($queryParams);
    }

    /**
     * Handle the form submission.
     *
     * @param array $formData The form data from POST
     */
    public function handleFormSubmission(array $formData): array|bool
    {
        global $config;
        $errors = [];

        // Sanitize user inputs
        $this->fullName = $this->sanitizeInput($formData['full_name'] ?? '');
        $this->company = $this->sanitizeInput($formData['company'] ?? '');
        $this->countryCode = $this->sanitizeInput($formData['countryCode'] ?? '');
        $this->phone = $this->sanitizeInput($formData['phone'] ?? '');
        $this->email = $this->sanitizeInput($formData['email'] ?? '');
        $this->message = htmlspecialchars(trim($formData['message'] ?? ''), ENT_QUOTES, 'UTF-8');

        // Log sanitized form data
        $this->logger->info('Sanitized form submitted: ' . json_encode($formData));

        // Validate inputs
        if (empty($this->fullName)) {
            $errors['full_name'] = $config['translations']['email']['validation']['full_name'];
        }
        if (empty($this->company)) {
            $errors['company'] = $config['translations']['email']['validation']['company'];
        }
        if (empty($this->countryCode)) {
            $errors['countryCode'] = $config['translations']['email']['validation']['countryCode'];
        }
        if (empty($this->phone) || !preg_match("/^[0-9]+$/", $this->phone)) {
            $errors['phone'] = $config['translations']['email']['validation']['phone'];
        }
        if (empty($this->email) || ($emailValidation = $this->validateEmail($this->email)) !== true) {
            $errors['email'] = $emailValidation;
        }
        if (empty($this->message)) {
            $errors['message'] = $config['translations']['email']['validation']['message'];
        }

        // Verify reCAPTCHA
        if (!$this->verifyCaptcha($formData['g-recaptcha-response'] ?? '')) {
            $errors['g-recaptcha-response'] = $config['translations']['email']['validation']['recaptcha'];
        }

        if (!empty($errors)) {
            $this->logger->error('Validation failed: ' . json_encode($errors));
            return $errors;
        }

        // Send email
        $this->sendEmail();
        $this->logger->info('Email has sent.' . json_encode($formData));
        return ['success' => $config['translations']['email']['contact_email_success_message']];
    }

    /**
     * Sanitize user inputs.
     * Removes unwanted characters, trims spaces, and encodes special characters.
     *
     * @param string $input The user input to sanitize
     * @return string Sanitized input
     */
    private function sanitizeInput(string $input): string
    {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Validate the email address (check MX record and disposable email domains).
     *
     * @param string $email The email address to validate
     * @return bool|string True if valid, error message if invalid
     */
    private function validateEmail(string $email): bool|string
    {
        global $config;
        $domain = substr(strrchr($email, "@"), 1); // Get domain part of email

        // Check if the email is valid
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $config['translations']['email']['validation']['email1'];
        }

        // Check if domain has a valid MX record
        if (!checkdnsrr($domain)) {
            return $config['translations']['email']['validation']['email2'];
        }

        // List of disposable email domains
        $disposableDomains = ["tempmail.com", "mailinator.com", "guerrillamail.com", "10minutemail.com"];
        foreach ($disposableDomains as $disposableDomain) {
            if (str_contains($domain, $disposableDomain)) {
                return $config['translations']['email']['validation']['email3'];
            }
        }

        return true;
    }

    /**
     * Verify the Google reCAPTCHA response.
     *
     * @param string $captchaResponse The reCAPTCHA response from the form
     * @return bool True if the CAPTCHA is valid, false otherwise
     */
    private function verifyCaptcha(string $captchaResponse): bool
    {
        $url = 'https://www.google.com/recaptcha/api/siteverify';
        $response = file_get_contents($url . '?secret=' . $this->recaptchaSecret . '&response=' . $captchaResponse);
        $responseKeys = json_decode($response, true);
        return $responseKeys['success'] ?? false;
    }

    /**
     * Send the contact form email using Gmail SMTP with PHPMailer.
     */
    private function sendEmail(): void
    {
        global $config;

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP(); // Use SMTP
            $mail->Host = 'smtp.gmail.com'; // Gmail SMTP server
            $mail->SMTPAuth = true;
            $mail->Username = $config['siteEmail']; // Gmail address
            $mail->Password = $config['siteEmailPassword']; // App password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom($this->email, $this->fullName);
            $mail->addAddress($config['siteEmail']);

            $mail->CharSet = 'UTF-8';
            $mail->isHTML(true);
            $mail->Subject = $config['translations']['email']['contact_email_title'];
            $mail->Body = "
                <h3>" . $config['translations']['email']['contact_email_title'] . "</h3>
                <strong>Name:</strong> {$this->fullName}<br>
                <strong>Company:</strong> {$this->company}<br>
                <strong>Country Code:</strong> {$this->countryCode}<br>
                <strong>Phone:</strong> {$this->phone}<br>
                <strong>Email:</strong> {$this->email}<br>
                <strong>Message:</strong><br>
                <p>" . nl2br($this->message) . "</p>
            ";

            $mail->send();
            $this->logger->info('Message sent successfully.');
        } catch (Exception $e) {
            $this->logger->error("Message could not be sent. Error: {$mail->ErrorInfo}. {$e}");
        }
    }
}
