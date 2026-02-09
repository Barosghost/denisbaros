<?php
require_once 'config/db.php';
try {
    $q = $pdo->query("DESCRIBE loyalty_rewards");
    $cols = $q->fetchAll(PDO::FETCH_COLUMN);
    file_put_contents('cols.txt', implode("\n", $cols));
} catch (Exception $e) {
    file_put_contents('cols.txt', "Error: " . $e->getMessage());
}
?>