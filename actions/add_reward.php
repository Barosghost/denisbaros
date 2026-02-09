<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $points = intval($_POST['points_required'] ?? 0);
    $description = $_POST['description'] ?? '';
    $discount = floatval($_POST['discount_amount'] ?? 0);
    $min_level = $_POST['min_level'] ?? 'Bronze';

    if (empty($name) || $points <= 0) {
        echo json_encode(['success' => false, 'message' => 'Nom et points requis obligatoires']);
        exit();
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO loyalty_rewards (name, description, points_required, discount_amount, min_level) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $description, $points, $discount, $min_level]);

        logActivity($pdo, $_SESSION['user_id'], "Ajout récompense", "Récompense: $name ($points pts)");

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur DB: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Méthode invalide']);
}
?>