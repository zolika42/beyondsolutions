<?php

use JetBrains\PhpStorm\NoReturn;

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/5ContactFormHandler.php';
require_once __DIR__ . '/1LanguageClass.php';
require_once __DIR__ . '/2LoggerClass.php';
require_once __DIR__ . '/3MinifierClass.php';

class APIClass
{
    private mixed $requestMethod;
    private mixed $endpoint;
    private array $queryParams;
    private mixed $callback;

    /**
     * Constructor to initialize the API class.
     */
    public function __construct()
    {
        // Capture the request method (e.g., GET, POST, PUT, DELETE)
        $this->requestMethod = $_SERVER['REQUEST_METHOD'];

        // Get the endpoint from the query string parameter 'endpoint'
        $this->endpoint = $_GET['endpoint'] ?? null;

        // Capture any additional query parameters
        $this->queryParams = $_GET;
        $this->callback = null;
        if (isset($_GET['callback'])) {
            $callback = html_entity_decode($_GET['callback']);
            $callback = rtrim($callback,"/");
            $this->callback = $callback;
        }
    }

    /**
     * Get the request method.
     *
     * @return string The HTTP request method
     */
    public function getRequestMethod(): string
    {
        return $this->requestMethod;
    }

    /**
     * Get the endpoint.
     *
     * @return string|null The API endpoint specified in the query string
     */
    public function getEndpoint(): ?string
    {
        return $this->endpoint;
    }

    /**
     * Process the request based on the endpoint and method.
     * If no endpoint is specified or an unknown endpoint is requested,
     * it allows the script to continue executing other tasks.
     *
     * @return false|string
     */
    public function processRequest(): false|string
    {
        // Check if the endpoint is provided
        if (empty($this->getEndpoint())) {
            // No API endpoint provided; gracefully return without interrupting the script
            return false;
        }

        // Process the request based on the endpoint
        switch ($this->getEndpoint()) {
            case 'validateForm':
                $this->handleFormValidation();

            case 'getLogs':
                $this->handleLogRetrieval();

            case 'statusMessage':
                return $this->respondWithJson(true, ['message' => $this->queryParams], 200);

            default:
                // Unknown endpoint; gracefully return without interrupting the script
                return false;
        }
    }

    /**
     * Handle the `validateForm` endpoint.
     *
     * @return HTMLTemplateClass
     * @throws Exception
     */
    #[NoReturn] private function handleFormValidation(): HTMLTemplateClass
    {
        // Only accept POST requests for this endpoint
        if ($this->requestMethod !== 'POST') {
            $this->respondWithError("Invalid request method for this endpoint. Use POST.", 405);
        }

        // Initialize the ContactFormHandler and validate the form
        $formHandler = new ContactFormHandler();
        $formData = $_POST;

        $formErrors = $formHandler->handleFormSubmission($formData);
        global $languageClass;
        $lang = $languageClass->getLanguage();
        $languageKey = $languageClass->getLanguageKey();
        $formErrors[$languageKey] = $lang;
        if (isset($this->callback)) {
            $_POST['formErrors'] = $formErrors;
            require_once '7StaticPageGeneratorClass.php';
            require_once '6HTMLTemplateClass.php';
        }

        if (isset($formErrors['success'])) {
            $this->respondWithJson(true, ['success' => $formErrors['success']]);
        } else {
            $this->respondWithJson(false, ['errors' => $formErrors]);
        }
    }

    /**
     * Handle the `getLogs` endpoint.
     *
     * @return void
     */
    #[NoReturn] private function handleLogRetrieval(): void
    {
        // Ensure the method is GET
        if ($this->requestMethod !== 'GET') {
            $this->respondWithError("Invalid request method for this endpoint. Use GET.", 405);
            return;
        }

        // Define the log file location (adjust path as necessary)
        $logFile = __DIR__ . '/../logs/app.log';

        // Get the number of lines from query parameters or default to 10
        $lines = isset($this->queryParams['lines']) ? intval($this->queryParams['lines']) : 10;

        // Initialize LoggerClass and fetch log messages
        $LoggerClass = new LoggerClass();
        $logMessages = $LoggerClass->getLastLines($lines);

        if (isset($logMessages['error'])) {
            $this->respondWithError($logMessages['error'], 500);
        } else {
            $this->respondWithJson(true, ['success' => true, 'logs' => $logMessages]);
        }
    }

    /**
     * Respond with a JSON object.
     *
     * @param bool $status
     * @param array $data The response data
     * @param int $statusCode The HTTP status code
     * @return string|false
     */
    #[NoReturn] private function respondWithJson(bool $status, array $data, int $statusCode = 200): string|false
    {
        header('Content-Type: application/json');
        http_response_code($statusCode);
        $data['status'] = $status;
        echo json_encode($data);
        exit;
    }

    /**
     * Respond with an error message.
     *
     * @param string $message The error message
     * @param int $statusCode The HTTP status code
     * @return void
     */
    #[NoReturn] private function respondWithError(string $message, int $statusCode): void
    {
        $this->respondWithJson(false, ['error' => $message], $statusCode);
    }
}

// Instantiate the API class
$api = new APIClass();

// Process the request if an endpoint is specified, otherwise allow script execution to continue
$api->processRequest();

// Proceed with rendering the website or other tasks
// For example, include the main website content
require_once __DIR__ . '/../../public/index.php';
