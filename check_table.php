<?php
require_once 'config/db.php';
try {
    $stmt = $pdo->query("DESCRIBE loyalty_rewards");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<h1>Structure de loyalty_rewards</h1><pre>";
    print_r($columns);
    echo "</pre>";
} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage();
}
?>