<?php
/**
 * Centralized Error Handler
 * Handles API and page errors consistently
 */

class ErrorHandler
{
    /**
     * Handle API errors (JSON response)
     * @param string $message Error message
     * @param int $code HTTP status code
     * @param array $details Additional error details (optional)
     */
    public static function handleApiError($message, $code = 500, $details = [])
    {
        // Log error
        self::logError($message, $details);

        // Set HTTP response code
        http_response_code($code);

        // Set JSON header
        header('Content-Type: application/json');

        // Build response
        $response = [
            'success' => false,
            'message' => $message
        ];

        // Add details in development mode
        if (self::isDevelopment() && !empty($details)) {
            $response['details'] = $details;
        }

        echo json_encode($response);
        exit();
    }

    /**
     * Handle page errors (redirect or display error page)
     * @param string $message Error message
     * @param string|null $redirect Redirect URL (optional)
     * @param int $code HTTP status code
     */
    public static function handlePageError($message, $redirect = null, $code = 500)
    {
        // Log error
        self::logError($message);

        // Set HTTP response code
        http_response_code($code);

        if ($redirect) {
            // Redirect with error parameter
            $separator = strpos($redirect, '?') !== false ? '&' : '?';
            header("Location: {$redirect}{$separator}error=" . urlencode($message));
        } else {
            // Display error page
            self::displayErrorPage($message, $code);
        }

        exit();
    }

    /**
     * Handle database errors
     * @param PDOException $e Exception object
     * @param bool $isApi Whether this is an API call
     */
    public static function handleDatabaseError(PDOException $e, $isApi = false)
    {
        $message = "Une erreur technique est survenue. Veuillez réessayer.";

        // Log detailed error
        self::logError("Database Error: " . $e->getMessage(), [
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);

        if ($isApi) {
            self::handleApiError($message, 500);
        } else {
            self::handlePageError($message, null, 500);
        }
    }

    /**
     * Log error to file
     * @param string $message Error message
     * @param array $context Additional context
     */
    private static function logError($message, $context = [])
    {
        $logDir = __DIR__ . '/../../logs';

        // Create logs directory if it doesn't exist
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/error_' . date('Y-m-d') . '.log';

        $logEntry = date('Y-m-d H:i:s') . " [ERROR] " . $message;

        if (!empty($context)) {
            $logEntry .= " | Context: " . json_encode($context);
        }

        $logEntry .= " | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $logEntry .= " | User: " . ($_SESSION['user_id'] ?? 'guest');
        $logEntry .= PHP_EOL;

        error_log($logEntry, 3, $logFile);
    }

    /**
     * Display error page
     * @param string $message Error message
     * @param int $code HTTP status code
     */
    private static function displayErrorPage($message, $code)
    {
        ?>
        <!DOCTYPE html>
        <html lang="fr">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Erreur - DENIS FBI STORE</title>
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }

                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
                    color: white;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }

                .error-container {
                    text-align: center;
                    max-width: 600px;
                }

                .error-code {
                    font-size: 120px;
                    font-weight: 700;
                    background: linear-gradient(135deg, #6366f1, #8b5cf6);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    margin-bottom: 20px;
                }

                .error-message {
                    font-size: 24px;
                    margin-bottom: 30px;
                    color: #cbd5e1;
                }

                .error-description {
                    font-size: 16px;
                    color: #94a3b8;
                    margin-bottom: 40px;
                }

                .btn-home {
                    display: inline-block;
                    padding: 12px 32px;
                    background: linear-gradient(135deg, #6366f1, #8b5cf6);
                    color: white;
                    text-decoration: none;
                    border-radius: 8px;
                    font-weight: 600;
                    transition: transform 0.2s;
                }

                .btn-home:hover {
                    transform: translateY(-2px);
                }
            </style>
        </head>

        <body>
            <div class="error-container">
                <div class="error-code">
                    <?= $code ?>
                </div>
                <div class="error-message">Oups ! Une erreur est survenue</div>
                <div class="error-description">
                    <?= htmlspecialchars($message) ?>
                </div>
                <a href="/" class="btn-home">Retour à l'accueil</a>
            </div>
        </body>

        </html>
        <?php
    }

    /**
     * Check if running in development mode
     * @return bool
     */
    private static function isDevelopment()
    {
        return defined('APP_ENV') ? APP_ENV === 'development' : true;
    }

    /**
     * Handle validation errors
     * @param array $errors Array of validation errors
     * @param bool $isApi Whether this is an API call
     */
    public static function handleValidationErrors(array $errors, $isApi = false)
    {
        $message = "Erreur de validation des données";

        if ($isApi) {
            http_response_code(422);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $message,
                'errors' => $errors
            ]);
        } else {
            $_SESSION['validation_errors'] = $errors;
            header("Location: " . ($_SERVER['HTTP_REFERER'] ?? '/'));
        }

        exit();
    }
}
