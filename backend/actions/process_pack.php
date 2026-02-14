<?php
require_once __DIR__ . '/../includes/session_init.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}
$role = str_replace(' ', '_', strtolower($_SESSION['role'] ?? ''));
if (!in_array($role, ['admin', 'super_admin'], true)) {
    echo json_encode(['success' => false, 'message' => 'Accès réservé à l\'administration']);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    if ($action === 'add' || $action === 'update') {
        $id_pack = isset($_POST['id_pack']) ? (int) $_POST['id_pack'] : 0;
        $name = trim($_POST['nom_pack'] ?? '');
        $price = (float) ($_POST['prix_pack'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $componentsJson = $_POST['components'] ?? '[]';
        $components = json_decode($componentsJson, true) ?: [];

        if ($name === '' || $price <= 0) {
            throw new Exception("Nom du pack et prix sont obligatoires.");
        }
        if (empty($components)) {
            throw new Exception("Ajoutez au moins un produit dans le pack.");
        }

        $pdo->beginTransaction();

        if ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO packs (nom_pack, prix_pack, description) VALUES (?, ?, ?)");
            $stmt->execute([$name, $price, $description]);
            $id_pack = (int) $pdo->lastInsertId();
        } else {
            if ($id_pack <= 0) {
                throw new Exception("Pack introuvable pour la mise à jour.");
            }
            $stmt = $pdo->prepare("UPDATE packs SET nom_pack = ?, prix_pack = ?, description = ? WHERE id_pack = ?");
            $stmt->execute([$name, $price, $description, $id_pack]);
            $pdo->prepare("DELETE FROM pack_composants WHERE id_pack = ?")->execute([$id_pack]);
        }

        $stmtComp = $pdo->prepare("INSERT INTO pack_composants (id_pack, id_produit, quantite) VALUES (?, ?, ?)");
        foreach ($components as $comp) {
            $pid = isset($comp['id_produit']) ? (int) $comp['id_produit'] : 0;
            $qty = isset($comp['quantite']) ? (int) $comp['quantite'] : 0;
            if ($pid <= 0 || $qty <= 0) continue;
            $stmtComp->execute([$id_pack, $pid, $qty]);
        }

        $pdo->commit();

        if (function_exists('logActivity')) {
            $label = $action === 'add' ? 'Création pack' : 'Mise à jour pack';
            logActivity($pdo, $_SESSION['user_id'] ?? 0, 'Packs', "$label: $name");
        }

        echo json_encode(['success' => true, 'message' => 'Pack enregistré avec succès']);
        exit;
    }

    if ($action === 'delete') {
        $id_pack = isset($_POST['id_pack']) ? (int) $_POST['id_pack'] : 0;
        if ($id_pack <= 0) {
            throw new Exception("Pack introuvable.");
        }
        $stmt = $pdo->prepare("DELETE FROM packs WHERE id_pack = ?");
        $stmt->execute([$id_pack]);
        if (function_exists('logActivity')) {
            logActivity($pdo, $_SESSION['user_id'] ?? 0, 'Packs', "Suppression pack ID: $id_pack");
        }
        echo json_encode(['success' => true, 'message' => 'Pack supprimé avec succès']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Action pack non reconnue']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

