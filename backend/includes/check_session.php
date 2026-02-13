<?php
// Session Validation - Check if user is still active
// This file should be included at the very top of view files before any HTML output

// Use centralized session initialization
require_once __DIR__ . '/session_init.php';

// Load Logger
require_once __DIR__ . '/Logger.php';

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../config/db.php';

    try {
        // Match denis_fbi schema: utilisateurs table, statut column
        $stmt_check = $pdo->prepare("SELECT statut FROM utilisateurs WHERE id_user = ?");
        $stmt_check->execute([$_SESSION['user_id']]);
        $statut = $stmt_check->fetchColumn();

        if ($statut === 'bloque') {
            // Log security event
            Logger::security("Blocked user attempted access", [
                'user_id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'] ?? 'unknown'
            ]);

            // Force logout if blocked
            session_unset();
            session_destroy();
            header("Location: ../index.php?error=blocked");
            exit();
        }

        // Maintenance Mode Check
        $maintenance_file = __DIR__ . '/../../maintenance.lock';
        if (file_exists($maintenance_file) && $_SESSION['role'] !== 'super_admin') {
            Logger::info("Non-admin user redirected due to maintenance mode", [
                'user_id' => $_SESSION['user_id']
            ]);

            session_unset();
            session_destroy();
            header("Location: ../index.php?error=maintenance");
            exit();
        }
    } catch (PDOException $e) {
        Logger::error("Session check database error", [
            'error' => $e->getMessage(),
            'user_id' => $_SESSION['user_id'] ?? null
        ]);
    }
}
