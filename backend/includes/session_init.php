<?php
/**
 * Centralized Session Initialization
 * Secure session configuration with best practices
 */

if (session_status() === PHP_SESSION_NONE) {
    // Secure session configuration
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Strict');

    // Enable secure cookies if HTTPS is available
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }

    // Use strict session ID mode
    ini_set('session.use_strict_mode', 1);

    // Session name (customize for your app)
    session_name('DENIS_SESSION');

    // Start session
    session_start();

    // Regenerate session ID periodically to prevent fixation
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 300) { // Every 5 minutes
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}
