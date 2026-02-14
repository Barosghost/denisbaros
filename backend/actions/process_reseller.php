<?php
require_once '../includes/session_init.php';
require_once '../config/db.php';
require_once '../config/functions.php';
require_once '../includes/ErrorHandler.php';
require_once '../includes/Logger.php';

header('Content-Type: application/json');

// Admin/Super Admin Only
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    ErrorHandler::handleApiError('Non autorisé', 403);
}

$action = $_POST['action'] ?? '';

try {
    if ($action === 'add') {
        $fullname = trim($_POST['fullname']);
        $phone = trim($_POST['phone']);
        $commission_value = floatval($_POST['commission_value']);

        if (empty($fullname))
            throw new Exception("Le nom est requis");

        $stmt = $pdo->prepare("INSERT INTO revendeurs (nom_partenaire, telephone, taux_commission_fixe, is_active) VALUES (?, ?, ?, 1)");
        $stmt->execute([$fullname, $phone, $commission_value]);

        echo json_encode(['success' => true, 'message' => 'Revendeur ajouté avec succès']);

    } elseif ($action === 'update') {
        $id = $_POST['id_reseller'];
        $fullname = trim($_POST['fullname']);
        $phone = trim($_POST['phone']);
        $commission_value = floatval($_POST['commission_value']);

        $stmt = $pdo->prepare("UPDATE revendeurs SET nom_partenaire = ?, telephone = ?, taux_commission_fixe = ? WHERE id_revendeur = ?");
        $stmt->execute([$fullname, $phone, $commission_value, $id]);

        echo json_encode(['success' => true, 'message' => 'Revendeur mis à jour']);

    } elseif ($action === 'toggle_status') {
        $id = $_POST['id_reseller'];

        $stmt = $pdo->prepare("UPDATE revendeurs SET is_active = NOT is_active WHERE id_revendeur = ?");
        $stmt->execute([$id]);

        echo json_encode(['success' => true, 'message' => 'Statut modifié']);

    } elseif ($action === 'delete') {
        $id = $_POST['id_reseller'];

        // Check if sales exist
        $check = $pdo->prepare("SELECT COUNT(*) FROM ventes WHERE id_revendeur = ?");
        $check->execute([$id]);
        if ($check->fetchColumn() > 0) {
            throw new Exception("Impossible de supprimer : ce revendeur a des ventes associées. Désactivez-le plutôt.");
        }

        $stmt = $pdo->prepare("DELETE FROM revendeurs WHERE id_revendeur = ?");
        $stmt->execute([$id]);

        echo json_encode(['success' => true, 'message' => 'Revendeur supprimé']);

    } else {
        throw new Exception("Action non reconnue");
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>