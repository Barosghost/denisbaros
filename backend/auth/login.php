<?php
// Use centralized session initialization
require_once '../includes/session_init.php';
require_once '../config/db.php';
require_once '../config/functions.php';
require_once '../includes/validators.php';
require_once '../includes/ErrorHandler.php';
require_once '../includes/Logger.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = sanitizeString($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validate inputs
    if (!validateRequired($username) || !validateRequired($password)) {
        Logger::warning("Login attempt with empty credentials", ['username' => $username]);
        header("Location: ../../frontend/index.php?error=empty");
        exit();
    }

    // Verify CSRF token
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        Logger::warning("CSRF token validation failed on login", [
            'username' => $username,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        header("Location: ../../frontend/index.php?error=csrf");
        exit();
    }

    try {
        // Prepare statement to find user
        $stmt = $pdo->prepare("
                SELECT u.id_user, u.username, u.password_hash, r.nom_role as role, u.statut 
                FROM utilisateurs u
                JOIN roles r ON u.id_role = r.id_role
                WHERE u.username = :username
            ");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        // Verify password
        if ($user && password_verify($password, $user['password_hash'])) {
            // Check if account is active
            if ($user['statut'] !== 'actif') {
                Logger::warning("Blocked user login attempt", [
                    'username' => $username,
                    'user_id' => $user['id_user']
                ]);
                header("Location: ../../frontend/index.php?error=blocked");
                exit();
            }

            // Login Success
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id_user'];
            $_SESSION['username'] = $user['username'];
            // Normalize role to slug-like format (e.g., 'Super Admin' -> 'super_admin')
            $_SESSION['role'] = str_replace(' ', '_', strtolower($user['role']));
            $_SESSION['logged_in'] = true;

            // Update last login (Wait, 'utilisateurs' table doesn't have last_login column in the provided schema. 
            // Let's check `logs_systeme` usage or omit last_login update if column missing.
            // The schema has `logs_systeme` for tracking actions. I'll omit direct update to `utilisateurs` for now or add it later if needed.)

            // Log successful login
            Logger::info("User logged in successfully", [
                'user_id' => $user['id_user'],
                'username' => $user['username'],
                'role' => $user['role']
            ]);

            // Log activity
            logActivity($pdo, $user['id_user'], "Connexion", "Connexion réussie");

            // Redirect to dashboard
            header("Location: ../../frontend/views/dashboard.php");
            exit();
        } else {
            // Invalid credentials
            Logger::warning("Failed login attempt", [
                'username' => $username,
                'reason' => 'invalid_credentials'
            ]);
            header("Location: ../../frontend/index.php?error=invalid");
            exit();
        }
    } catch (PDOException $e) {
        // Log error and redirect
        Logger::error("Login database error", [
            'error' => $e->getMessage(),
            'username' => $username
        ]);
        header("Location: ../../frontend/index.php?error=db");
        exit();
    }
} else {
    header("Location: ../../frontend/index.php");
    exit();
}
