<?php
require_once 'config/db.php';

echo "<h1>Initialisation Admin</h1>";

$username = 'admin';
$password = 'password';
$hash = password_hash($password, PASSWORD_DEFAULT);

try {
    // 1. Check if table exists
    $pdo->query("SELECT 1 FROM users LIMIT 1");

    // 2. Delete old admin if exists
    $stmt = $pdo->prepare("DELETE FROM users WHERE username = ?");
    $stmt->execute([$username]);

    // 3. Create new admin
    $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'admin')");
    $stmt->execute([$username, $hash]);

    echo "<div style='color: green; border: 1px solid green; padding: 10px; border-radius: 5px; background: #e6fffa;'>";
    echo "<strong>✅ Succès !</strong><br>";
    echo "Utilisateur administrateur recréé.<br>";
    echo "Identifiant : <strong>$username</strong><br>";
    echo "Mot de passe : <strong>$password</strong><br><br>";
    echo "<a href='index.php'>Retour à la connexion</a>";
    echo "</div>";

} catch (PDOException $e) {
    echo "<div style='color: red; border: 1px solid red; padding: 10px; border-radius: 5px; background: #fff5f5;'>";
    echo "<strong>❌ Erreur :</strong> " . $e->getMessage() . "<br><br>";
    echo "Avez-vous bien importé le fichier <code>database.sql</code> dans phpMyAdmin ?";
    echo "</div>";
}
?>