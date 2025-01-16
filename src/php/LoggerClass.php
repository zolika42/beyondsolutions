<?php

class LoggerClass
{
    private string $environment;
    private string $logFile;
    private int $maxLogSize;
    private int $logRetentionDays;
    private string $appName;

    /**
     * LoggerClass constructor.
     * Initializes the logger class with configuration values.
     * Sets up error handlers and performs log rotation and cleanup.
     */
    public function __construct()
    {
        global $config;

        // Set configuration values with defaults
        $this->environment = $config['environment'] ?? 'production';
        $this->logFile = $config['logFile'] ?? __DIR__ . '/../logs/app.log';
        $this->maxLogSize = $config['maxLogSize'] ?? (10 * 1024 * 1024); // 10MB
        $this->logRetentionDays = $config['logRetentionDays'] ?? 7; // Delete logs older than 7 days
        $this->appName = $config['appName'] ?? 'BeyondStartSolutions'; // Default app name if not provided

        // Ensure the log directory exists
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // Perform log rotation if needed
        $this->rotateLogIfNeeded();

        // Clean up old log files
        $this->deleteOldLogFiles();

        // Set up custom error handler to capture PHP errors
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    /**
     * Handle PHP errors and log them.
     *
     * @param int $severity Error severity level
     * @param string $message Error message
     * @param string $file File where the error occurred
     * @param int $line Line number where the error occurred
     */
    public function handleError(int $severity, string $message, string $file, int $line): void
    {
        // Only log if the error is not suppressed
        if (!(error_reporting() & $severity)) {
            return;
        }

        $this->logToFile('error', "[Error] $message in $file on line $line");
        $this->logToOutput('error', "[Error] $message in $file on line $line");
    }

    /**
     * Handle uncaught exceptions and log them.
     *
     * @param Throwable $exception Uncaught exception
     */
    public function handleException(Throwable $exception): void
    {
        $message = "Uncaught exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine();
        $this->logToFile('error', $message);
        $this->logToOutput('error', $message);
    }

    /**
     * Handle shutdown function to capture fatal errors.
     */
    public function handleShutdown(): void
    {
        $lastError = error_get_last();
        if ($lastError !== NULL) {
            $this->logToFile('error', "[Shutdown] {$lastError['message']} in {$lastError['file']} on line {$lastError['line']}");
            $this->logToOutput('error', "[Shutdown] {$lastError['message']} in {$lastError['file']} on line {$lastError['line']}");
        }
    }

    /**
     * Perform log rotation if the file exceeds the max size or if it's a new day.
     */
    private function rotateLogIfNeeded(): void
    {
        $logDir = dirname($this->logFile);
        $logFileName = basename($this->logFile);
        $logFilePath = $this->logFile;

        // Check if we need to rotate the log based on size or date
        if (file_exists($logFilePath)) {
            $logFileSize = filesize($logFilePath);

            // Rotate based on size if the log exceeds the max size
            if ($logFileSize >= $this->maxLogSize) {
                $this->rotateLog($logFileName, $logDir);
            }
        }

        // Rotate log at midnight (if it's a new day)
        $logDate = date('Y-m-d');
        $logFileDate = date('Y-m-d', filemtime($logFilePath));
        if ($logDate !== $logFileDate) {
            $this->rotateLog($logFileName, $logDir);
        }
    }

    /**
     * Rotate the log by renaming the current log and creating a new one.
     *
     * @param string $logFileName The name of the log file
     * @param string $logDir The directory where the log file is located
     */
    private function rotateLog(string $logFileName, string $logDir): void
    {
        // Rename the current log file with a timestamp (appending .YYYY-MM-DD)
        $newLogFilePath = $logDir . '/' . $logFileName . '.' . date('Y-m-d_H-i-s');
        rename($this->logFile, $newLogFilePath);
    }

    /**
     * Delete log files older than the configured retention period (7 days).
     */
    private function deleteOldLogFiles(): void
    {
        $logDir = dirname($this->logFile);

        // Iterate through all files in the log directory
        foreach (glob($logDir . '/*.log*') as $logFile) {
            // Check the file's last modified time
            $fileModifiedTime = filemtime($logFile);
            $daysOld = (time() - $fileModifiedTime) / (60 * 60 * 24);

            // If the log file is older than the retention period, delete it
            if ($daysOld > $this->logRetentionDays) {
                unlink($logFile);
            }
        }
    }

    /**
     * Write a log message to the log file if in 'dev' environment.
     *
     * @param string $level Log level (e.g., info, error)
     * @param string $message The message to log
     */
    private function logToFile(string $level, string $message): void
    {
        if ($this->environment !== 'dev') {
            return;
        }

        $this->rotateLogIfNeeded();  // Check if log rotation is required

        // Format the log message according to the defined pattern
        $formattedMessage = sprintf(
            "[%s] %s.%s: %s\n",
            date('Y-m-d H:i:s'),             // Time format: yyyy-MM-dd HH:mm:ss
            $this->appName,                  // App name
            strtoupper($level),              // Log level (INFO, ERROR, WARNING)
            $message                         // Log message
        );

        file_put_contents($this->logFile, $formattedMessage, FILE_APPEND);
    }

    /**
     * Display a log message directly to the output if in 'dev' environment.
     *
     * @param string $level Log level (e.g., info, error)
     * @param string $message The message to display
     */
    private function logToOutput(string $level, string $message): void
    {
        if ($this->environment !== 'dev') {
            return;
        }

        echo sprintf(
            '<div class="debug-log" style="border-left: 5px solid %s;">[%s] %s</div>',
            $this->getLevelColor($level),
            strtoupper($level),
            htmlspecialchars($message)
        );
    }

    /**
     * Log an informational message.
     *
     * @param string $message The informational message to log
     */
    public function info(string $message): void
    {
        $this->logToFile('info', $message);
        $this->logToOutput('info', $message);
    }

    /**
     * Log a warning message.
     *
     * @param string $message The warning message to log
     */
    public function warning(string $message): void
    {
        $this->logToFile('warning', $message);
        $this->logToOutput('warning', $message);
    }

    /**
     * Log an error message.
     *
     * @param string $message The error message to log
     */
    public function error(string $message): void
    {
        $this->logToFile('error', $message);
        $this->logToOutput('error', $message);
    }

    /**
     * Get color for a specific log level.
     *
     * @param string $level Log level (e.g., info, warning, error)
     * @return string The color associated with the log level
     */
    private function getLevelColor(string $level): string
    {
        $colors = [
            'info' => '#2b7a78',
            'warning' => '#f4a261',
            'error' => '#e63946'
        ];

        return $colors[strtolower($level)] ?? '#333';
    }
}
