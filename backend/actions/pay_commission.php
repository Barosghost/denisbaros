<?php
require_once '../includes/session_init.php';
require_once '../config/db.php';
require_once '../includes/ErrorHandler.php';
require_once '../includes/Logger.php';

header('Content-Type: application/json');

// Admin/Super Admin Only
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    ErrorHandler::handleApiError('Non autorisé', 403);
}

$data = json_decode(file_get_contents('php://input'), true);
$sales_ids = $data['sales_ids'] ?? [];

if (empty($sales_ids)) {
    echo json_encode(['success' => false, 'message' => 'Aucune vente sélectionnée']);
    exit();
}

try {
    $ids_placeholder = implode(',', array_fill(0, count($sales_ids), '?'));

    $stmt = $pdo->prepare("UPDATE sales SET margin_status = 'paid', paid_at = NOW() WHERE id_sale IN ($ids_placeholder) AND margin_status = 'pending'");
    $stmt->execute($sales_ids);

    $count = $stmt->rowCount();

    if ($count > 0) {
        echo json_encode(['success' => true, 'message' => "$count commissions marquées comme payées."]);
    } else {
        echo json_encode(['success' => false, 'message' => "Aucune commission mise à jour (peut-être déjà payées)."]);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => "Erreur SGBD: " . $e->getMessage()]);
}
?>