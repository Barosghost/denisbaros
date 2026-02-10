<?php
require_once 'config/db.php';
try {
    $stmt = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'denis_fbi_store_baros' AND TABLE_NAME = 'loyalty_rewards'");
    echo "REWARDS COLUMNS:\n";
    print_r($stmt->fetchAll(PDO::FETCH_COLUMN));

    $stmt = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'denis_fbi_store_baros' AND TABLE_NAME = 'loyalty_transactions'");
    echo "\nTRANSACTIONS COLUMNS:\n";
    print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
} catch (Exception $e) {
    echo $e->getMessage();
}
?>