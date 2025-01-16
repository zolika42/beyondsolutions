<?php
// Include PHPMailer files (adjust path as necessary)
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

// Include LoggerClass
require_once __DIR__ . '/LoggerClass.php';

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

    /**
     * Constructor to initialize the contact form handler.
     */
    public function __construct()
    {
        global $config;
        $this->recaptchaSecret = $config['recaptcha_secret'];
        $this->logger = new LoggerClass();  // Initialize LoggerClass
    }

    /**
     * Handle the form submission.
     *
     * @param array $formData The form data from POST
     */
    public function handleFormSubmission(array $formData): void
    {
        // Assign form data to properties
        $this->fullName = trim($formData['full_name'] ?? '');
        $this->company = trim($formData['company'] ?? '');
        $this->countryCode = trim($formData['countryCode'] ?? '');
        $this->phone = trim($formData['phone'] ?? '');
        $this->email = trim($formData['email'] ?? '');
        $this->message = trim($formData['message'] ?? '');

        // Log the form data
        $this->logger->info('Form submitted: ' . json_encode($formData));

        // Validate the form fields
        $validationResult = $this->validateFormFields();
        if ($validationResult !== true) {
            $this->logger->error($validationResult); // Return error if validation fails
        }

        // Verify the reCAPTCHA
        if (!$this->verifyCaptcha($formData['g-recaptcha-response'] ?? '')) {
            $this->logger->error('reCAPTCHA verification failed.');
        }

        // Send the email
        $this->sendEmail();
    }

    /**
     * Validate the form fields.
     *
     * @return bool|string True if validation passes, error message if validation fails
     */
    private function validateFormFields(): bool|string
    {
        if (empty($this->fullName) || empty($this->company) || empty($this->countryCode)  || empty($this->phone) || empty($this->email) || empty($this->message)) {
            return 'All fields are required.';
        }

        // Phone number validation
        if (!preg_match("/^[0-9]+$/", $this->phone)) {
            $this->logger->warning("Invalid phone number: {$this->phone}");
            return 'Phone number must only contain numbers.';
        }

        // Email validation
        $emailValidation = $this->validateEmail($this->email);
        if ($emailValidation !== true) {
            $this->logger->warning("Invalid email: {$this->email}. Error: {$emailValidation}");
            return $emailValidation;
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
        // Make a POST request to verify the CAPTCHA with Google's API
        $url = 'https://www.google.com/recaptcha/api/siteverify';
        $response = file_get_contents($url . '?secret=' . $this->recaptchaSecret . '&response=' . $captchaResponse);
        $responseKeys = json_decode($response, true);
        return $responseKeys['success'] ?? false;
    }

    /**
     * Validate the email address (check MX record and disposable email domains).
     *
     * @param string $email The email address to validate
     * @return bool|string True if valid, error message if invalid
     */
    private function validateEmail($email): bool|string
    {
        $domain = substr(strrchr($email, "@"), 1); // Get domain part of email

        // Check if domain has a valid MX record
        if (!checkdnsrr($domain, 'MX')) {
            return "Invalid email domain. No MX record found.";
        }

        // List of disposable email domains
        $disposableDomains = ["tempmail.com", "mailinator.com", "guerrillamail.com", "10minutemail.com"];
        foreach ($disposableDomains as $disposableDomain) {
            if (str_contains($domain, $disposableDomain)) {
                return "Disposable email addresses are not allowed.";
            }
        }

        return true; // Email is valid
    }

    /**
     * Log the form data to the log file.
     *
     * @param array $formData The form data to log
     */
    private function logFormData(array $formData): void
    {
        // Log form data
        $this->logger->info('Form Data: ' . json_encode($formData));
    }

    /**
     * Send the contact form email using Gmail SMTP with PHPMailer.
     */
    private function sendEmail(): void
    {
        global $config;

        // Create a new PHPMailer instance
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();  // Use SMTP
            $mail->Host = 'smtp.gmail.com';  // Gmail SMTP server
            $mail->SMTPAuth = true;         // Enable SMTP authentication
            $mail->Username = $config['siteEmail'];  // Gmail address
            $mail->Password = $config['siteEmailPassword'];  // App password (not regular Gmail password)
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;  // Use STARTTLS
            $mail->Port = 587;  // Use port 587 for TLS

            // Sender and recipient
            $mail->setFrom($this->email, $this->fullName);  // From field (ensure this is UTF-8)
            $mail->addAddress($config['siteEmail']);  // Recipient's email address

            // Set the character set to UTF-8
            $mail->CharSet = 'UTF-8';

            // Content
            $mail->isHTML(true);  // Set email format to HTML
            $mail->Subject = $config['translations']['email']['contact_email_title'];

            $messageWithBreaks = nl2br(htmlspecialchars($this->message));

            $mail->Body    = "
                <h3>" . $config['translations']['email']['contact_email_title'] . "</h3>
                <strong>" . $config['translations']['email']['contact_email_name'] . ":</strong> {$this->fullName}<br>
                <strong>" . $config['translations']['email']['contact_email_company'] . ":</strong> {$this->company}<br>
                <strong>" . $config['translations']['email']['contact_email_country_code'] . ":</strong> {$this->countryCode}<br>
                <strong>" . $config['translations']['email']['contact_email_phone'] . ":</strong> {$this->phone}<br>
                <strong>" . $config['translations']['email']['contact_email_email'] . ":</strong> {$this->email}<br>
                <strong>" . $config['translations']['email']['contact_email_message'] . ":</strong><br>
                <p>{$messageWithBreaks}</p>
            ";

            // Send email
            $mail->send();
            $this->logger->info('Message has been sent successfully!');
        } catch (Exception $e) {
            // Log the error
            $this->logger->error("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        }
    }
}

// Instantiate the ContactFormHandler class
$formHandler = new ContactFormHandler();

// Handle the form submission and echo the result message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resultMessage = $formHandler->handleFormSubmission($_POST);
    echo $resultMessage; // Display the result message
}
