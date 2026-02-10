<?php
require_once 'config/db.php';
try {
    $stmt = $pdo->query("SHOW CREATE TABLE loyalty_rewards");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    print_r($result);
} catch (Exception $e) {
    echo $e->getMessage();
}
?>