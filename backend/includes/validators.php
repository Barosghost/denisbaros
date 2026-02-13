<?php
/**
 * Input Validation Functions
 * Server-side validation utilities
 */

/**
 * Validate email address
 * @param string $email Email to validate
 * @return bool True if valid, false otherwise
 */
function validateEmail($email)
{
    if (empty($email)) {
        return false;
    }
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number (9-15 digits)
 * @param string $phone Phone number to validate
 * @return bool True if valid, false otherwise
 */
function validatePhone($phone)
{
    if (empty($phone)) {
        return false;
    }
    // Remove spaces, dashes, parentheses
    $cleaned = preg_replace('/[\s\-\(\)]/', '', $phone);
    return preg_match('/^[0-9]{9,15}$/', $cleaned);
}

/**
 * Sanitize input string
 * @param string $data Input to sanitize
 * @return string Sanitized string
 */
function sanitizeInput($data)
{
    if (empty($data)) {
        return '';
    }
    $data = trim($data);
    $data = strip_tags($data);
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize string (alias for sanitizeInput)
 * @param string $str String to sanitize
 * @return string Sanitized string
 */
function sanitizeString($str)
{
    return sanitizeInput($str);
}

/**
 * Sanitize integer value
 * @param mixed $value Value to sanitize
 * @return int Sanitized integer
 */
function sanitizeInt($value)
{
    return (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT);
}

/**
 * Validate required field
 * @param mixed $value Value to check
 * @return bool True if not empty, false otherwise
 */
function validateRequired($value)
{
    if (is_string($value)) {
        return trim($value) !== '';
    }
    return !empty($value);
}

/**
 * Validate numeric value
 * @param mixed $value Value to check
 * @param float $min Minimum value (optional)
 * @param float $max Maximum value (optional)
 * @return bool True if valid number, false otherwise
 */
function validateNumeric($value, $min = null, $max = null)
{
    if (!is_numeric($value)) {
        return false;
    }

    $num = floatval($value);

    if ($min !== null && $num < $min) {
        return false;
    }

    if ($max !== null && $num > $max) {
        return false;
    }

    return true;
}

/**
 * Validate string length
 * @param string $value String to check
 * @param int $min Minimum length
 * @param int $max Maximum length (optional)
 * @return bool True if valid length, false otherwise
 */
function validateLength($value, $min, $max = null)
{
    $length = mb_strlen($value, 'UTF-8');

    if ($length < $min) {
        return false;
    }

    if ($max !== null && $length > $max) {
        return false;
    }

    return true;
}

/**
 * Validate date format
 * @param string $date Date string
 * @param string $format Expected format (default: Y-m-d)
 * @return bool True if valid date, false otherwise
 */
function validateDate($date, $format = 'Y-m-d')
{
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * Validate array of values against allowed values
 * @param mixed $value Value to check
 * @param array $allowed Allowed values
 * @return bool True if value is in allowed array, false otherwise
 */
function validateInArray($value, array $allowed)
{
    return in_array($value, $allowed, true);
}

/**
 * Validate password strength
 * @param string $password Password to validate
 * @param int $minLength Minimum length (default: 8)
 * @return array ['valid' => bool, 'errors' => array]
 */
function validatePassword($password, $minLength = 8)
{
    $errors = [];

    if (strlen($password) < $minLength) {
        $errors[] = "Le mot de passe doit contenir au moins $minLength caractères";
    }

    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Le mot de passe doit contenir au moins une majuscule";
    }

    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Le mot de passe doit contenir au moins une minuscule";
    }

    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Le mot de passe doit contenir au moins un chiffre";
    }

    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * Validate file upload
 * @param array $file $_FILES array element
 * @param array $allowedTypes Allowed MIME types
 * @param int $maxSize Maximum file size in bytes
 * @return array ['valid' => bool, 'error' => string]
 */
function validateFileUpload($file, array $allowedTypes, $maxSize)
{
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['valid' => false, 'error' => 'Paramètres invalides'];
    }

    // Check upload errors
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return ['valid' => false, 'error' => 'Aucun fichier envoyé'];
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return ['valid' => false, 'error' => 'Fichier trop volumineux'];
        default:
            return ['valid' => false, 'error' => 'Erreur inconnue'];
    }

    // Check file size
    if ($file['size'] > $maxSize) {
        return ['valid' => false, 'error' => 'Fichier trop volumineux'];
    }

    // Check MIME type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!in_array($mimeType, $allowedTypes)) {
        return ['valid' => false, 'error' => 'Type de fichier non autorisé'];
    }

    return ['valid' => true, 'error' => null];
}
