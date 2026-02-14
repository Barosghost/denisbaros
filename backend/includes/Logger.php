<?php
/**
 * Structured Logging Class
 * Provides consistent logging with levels and context
 */

class Logger
{
    const LEVEL_DEBUG = 'DEBUG';
    const LEVEL_INFO = 'INFO';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR = 'ERROR';
    const LEVEL_CRITICAL = 'CRITICAL';

    private static $logDir;

    /**
     * Initialize logger
     */
    private static function init()
    {
        if (self::$logDir === null) {
            self::$logDir = __DIR__ . '/../../logs';

            // Create logs directory if it doesn't exist
            if (!is_dir(self::$logDir)) {
                mkdir(self::$logDir, 0755, true);
            }
        }
    }

    /**
     * Log a debug message
     * @param string $message Log message
     * @param array $context Additional context
     */
    public static function debug($message, $context = [])
    {
        self::log(self::LEVEL_DEBUG, $message, $context);
    }

    /**
     * Log an info message
     * @param string $message Log message
     * @param array $context Additional context
     */
    public static function info($message, $context = [])
    {
        self::log(self::LEVEL_INFO, $message, $context);
    }

    /**
     * Log a warning message
     * @param string $message Log message
     * @param array $context Additional context
     */
    public static function warning($message, $context = [])
    {
        self::log(self::LEVEL_WARNING, $message, $context);
    }

    /**
     * Log an error message
     * @param string $message Log message
     * @param array $context Additional context
     */
    public static function error($message, $context = [])
    {
        self::log(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * Log a critical message
     * @param string $message Log message
     * @param array $context Additional context
     */
    public static function critical($message, $context = [])
    {
        self::log(self::LEVEL_CRITICAL, $message, $context);
    }

    /**
     * Main logging method
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context
     */
    private static function log($level, $message, $context = [])
    {
        self::init();

        $logFile = self::$logDir . '/app_' . date('Y-m-d') . '.log';

        // Build log entry
        $logEntry = self::formatLogEntry($level, $message, $context);

        // Write to file
        error_log($logEntry . PHP_EOL, 3, $logFile);

        // Also log critical errors to PHP error log
        if ($level === self::LEVEL_CRITICAL) {
            error_log("[CRITICAL] $message");
        }
    }

    /**
     * Format log entry
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context
     * @return string Formatted log entry
     */
    private static function formatLogEntry($level, $message, $context)
    {
        $timestamp = date('Y-m-d H:i:s');
        $userId = $_SESSION['user_id'] ?? 'guest';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $uri = $_SERVER['REQUEST_URI'] ?? '';

        $entry = "[$timestamp] [$level] $message";

        // Add context if provided
        if (!empty($context)) {
            $entry .= " | Context: " . json_encode($context);
        }

        // Add request info
        $entry .= " | User: $userId | IP: $ip | URI: $uri";

        return $entry;
    }

    /**
     * Log activity (user action)
     * @param string $action Action performed
     * @param string $details Action details
     * @param array $context Additional context
     */
    public static function activity($action, $details = '', $context = [])
    {
        $message = "Activity: $action";
        if ($details) {
            $message .= " - $details";
        }

        self::info($message, $context);
    }

    /**
     * Log security event
     * @param string $event Security event description
     * @param array $context Additional context
     */
    public static function security($event, $context = [])
    {
        self::init();

        $logFile = self::$logDir . '/security_' . date('Y-m-d') . '.log';
        $logEntry = self::formatLogEntry('SECURITY', $event, $context);

        error_log($logEntry . PHP_EOL, 3, $logFile);
    }

    /**
     * Log performance metric
     * @param string $metric Metric name
     * @param float $value Metric value
     * @param string $unit Unit of measurement
     */
    public static function performance($metric, $value, $unit = 'ms')
    {
        self::debug("Performance: $metric = $value $unit");
    }

    /**
     * Clean old log files
     * @param int $days Number of days to keep (default: 30)
     */
    public static function cleanOldLogs($days = 30)
    {
        self::init();

        $files = glob(self::$logDir . '/*.log');
        $now = time();

        foreach ($files as $file) {
            if (is_file($file)) {
                if ($now - filemtime($file) >= 60 * 60 * 24 * $days) {
                    unlink($file);
                    self::info("Deleted old log file: " . basename($file));
                }
            }
        }
    }
}
