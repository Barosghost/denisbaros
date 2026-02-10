<?php
require_once 'config/db.php';
header('Content-Type: application/json');
$response = [];

try {
    // Check Rewards
    $stmt = $pdo->query("DESCRIBE loyalty_rewards");
    $response['loyalty_rewards'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Check Transactions
    $stmt = $pdo->query("DESCRIBE loyalty_transactions");
    $response['loyalty_transactions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>