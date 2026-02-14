<?php
require_once '../includes/session_init.php';
require_once '../config/db.php';
require_once '../config/functions.php';
require_once '../config/settings.php';
require_once '../includes/Logger.php';

// Bloquer déconnexion si rapport journalier obligatoire et non soumis
if (isset($_SESSION['user_id']) && !isset($_GET['force'])) {
    $block = getSystemSetting('block_logout_without_report', '0', $pdo);
    $dailyRequired = getSystemSetting('daily_report_required', '0', $pdo);
    if ($block === '1' && $dailyRequired === '1') {
        $stmt = $pdo->prepare("SELECT 1 FROM rapports_journaliers WHERE id_user = ? AND DATE(date_rapport) = CURDATE() LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        if (!$stmt->fetchColumn()) {
            $redirect = $_SERVER['HTTP_REFERER'] ?? '../../frontend/dashboard.php';
            header("Location: $redirect?logout_blocked=1");
            exit;
        }
    }
}

// Log the logout action
if (isset($_SESSION['user_id'])) {
    Logger::info('User logged out', [
        'user_id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? 'unknown'
    ]);

    // Log to activity table
    logActivity($pdo, $_SESSION['user_id'], 'Déconnexion', 'Déconnexion réussie', true);
}

// Unset all session variables
$_SESSION = array();

// Destroy session cookie if it exists
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: ../../frontend/index.php");
exit();