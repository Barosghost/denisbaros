<?php
// Test Database Connection
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'denis_fbi_store');

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS);

    // Connection was successful
    echo "✅ Connection to the database was successful!";
} catch (PDOException $e) {
    // Connection failed
    echo "❌ Error connecting to the database: " . $e->getMessage();
}