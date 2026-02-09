<?php
/**
 * Log an activity to the database
 * 
 * @param PDO $pdo The PDO connection object
 * @param int $user_id The ID of the user performing the action
 * @param string $action The action performed
 * @param string $details Optional details about the action
 * @return bool True on success, false on failure
 */
function logActivity($pdo, $user_id, $action, $details = "")
{
    try {
        $stmt = $pdo->prepare("INSERT INTO action_logs (id_user, action, details) VALUES (?, ?, ?)");
        return $stmt->execute([$user_id, $action, $details]);
    } catch (PDOException $e) {
        error_log("Activity Log Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate a CSRF token and store it in the session
 * @return string The generated token
 */
function generateCsrfToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify the CSRF token from a request
 * @param string $token The token to verify
 * @return bool True if valid, false otherwise
 */
function verifyCsrfToken($token)
{
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get the HTML input field for CSRF token
 * @return string The HTML input field
 */
function getCsrfInput()
{
    $token = generateCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}
?>