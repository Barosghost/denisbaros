<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $rewardId = $data['reward_id'] ?? 0;
    $clientId = $data['client_id'] ?? 0; // In admin view, we might want to select a client. For now let's assume admin redeems FOR a client?

    // NOTE: The current UI in loyalty.php is an ADMIN view.
    // If the Admin clicks "Echanger", they should probably select WHICH client behaves receives the reward.
    // However, to keep it simple as per the UI, maybe we just mock it or ask for client ID?

    // Let's look at the UI. It just says "Echanger". 
    // Since this is the admin dashboard, we need to know WHO is redeeming.
    // I will return an error asking for client selection if missing.

    if (empty($clientId)) {
        echo json_encode(['success' => false, 'message' => 'Client non spécifié.']);
        exit();
    }

    try {
        $pdo->beginTransaction();

        // 1. Get Reward Info
        $stmt = $pdo->prepare("SELECT * FROM loyalty_rewards WHERE id_reward = ?");
        $stmt->execute([$rewardId]);
        $reward = $stmt->fetch();

        if (!$reward) {
            throw new Exception("Récompense introuvable");
        }

        // 2. Get Client Info
        $stmt = $pdo->prepare("SELECT loyalty_points FROM clients WHERE id_client = ?");
        $stmt->execute([$clientId]);
        $client = $stmt->fetch();

        if (!$client) {
            throw new Exception("Client introuvable");
        }

        if ($client['loyalty_points'] < $reward['points_required']) {
            throw new Exception("Points insuffisants ({$client['loyalty_points']} / {$reward['points_required']})");
        }

        // 3. Deduct Points
        $newPoints = $client['loyalty_points'] - $reward['points_required'];
        $stmt = $pdo->prepare("UPDATE clients SET loyalty_points = ? WHERE id_client = ?");
        $stmt->execute([$newPoints, $clientId]);

        // 4. Log Transaction
        $stmt = $pdo->prepare("INSERT INTO loyalty_transactions (id_client, transaction_type, points, description) VALUES (?, 'SPEND', ?, ?)");
        $stmt->execute([$clientId, $reward['points_required'], "Échange récompense: " . $reward['name']]);

        // 5. Log Activity
        logActivity($pdo, $_SESSION['user_id'], "Échange fidélité", "Client #$clientId - " . $reward['name']);

        $pdo->commit();
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>