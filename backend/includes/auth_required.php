<?php
/**
 * Authentication Required
 * Include this file at the top of protected pages
 * Handles session initialization, authentication check, and user status validation
 */

// Initialize session
require_once __DIR__ . '/session_init.php';

// Load database connection
require_once __DIR__ . '/../config/db.php';

// Check session validation
require_once __DIR__ . '/check_session.php';

// Verify user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

// Contrôle d'accès par rôle : si la page définit PAGE_ACCESS, vérifier que le rôle peut y accéder
require_once __DIR__ . '/roles.php';
if (defined('PAGE_ACCESS')) {
    requirePageAccess(PAGE_ACCESS);
}

// Optional: Set page title if not already set
if (!isset($pageTitle)) {
    $pageTitle = "DENIS FBI STORE";
}
