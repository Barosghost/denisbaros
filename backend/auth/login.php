<?php
session_start();
require_once '../config/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        header("Location: ../../frontend/index.php?error=empty");
        exit();
    }

    require_once '../config/functions.php';
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        header("Location: ../../frontend/index.php?error=csrf");
        exit();
    }

    try {
        // Prepare statement to find user
        $stmt = $pdo->prepare("SELECT id_user, username, password, role FROM users WHERE username = :username");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        // Verify password
        if ($user && password_verify($password, $user['password'])) {
            // Login Success
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id_user'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['logged_in'] = true;

            // Redirect based on role or to dashboard
            header("Location: ../../frontend/views/dashboard.php");
            exit();
        } else {
            // Invalid credentials
            header("Location: ../../frontend/index.php?error=invalid");
            exit();
        }
    } catch (PDOException $e) {
        // Log error and redirect
        error_log($e->getMessage());
        header("Location: ../../frontend/index.php?error=db");
        exit();
    }
} else {
    header("Location: ../../frontend/index.php");
    exit();
}
?>