<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../includes/session_init.php';
require_once __DIR__ . '/../../includes/check_session.php';
require_once __DIR__ . '/../../includes/roles.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}
$role = getSessionRole();
if (!in_array($role, ['super_admin', 'admin', 'chef_technique', 'technicien'])) {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit();
}

$user_id = $_SESSION['user_id'];
$id_sav = $data['id_repair'] ?? 0;

// Technicien : ne peut réparer que ses dossiers assignés
if ($role === 'technicien') {
    $tstmt = $pdo->prepare("SELECT id_technician FROM technicians WHERE id_user = ?");
    $tstmt->execute([$user_id]);
    $my_tech_id = $tstmt->fetchColumn();
    $dstmt = $pdo->prepare("SELECT id_technicien FROM sav_dossiers WHERE id_sav = ?");
    $dstmt->execute([$id_sav]);
    $assigned = $dstmt->fetchColumn();
    if (!$my_tech_id || (int)$assigned !== (int)$my_tech_id) {
        echo json_encode(['success' => false, 'message' => 'Vous ne pouvez modifier que vos dossiers assignés']);
        exit;
    }
}
$parts = $data['parts'] ?? []; // Array of {id_product, quantity}
$actions_performed = $data['actions_performed'] ?? '';

if (!$id_sav) {
    echo json_encode(['success' => false, 'message' => 'ID dossier manquant']);
    exit();
}

try {
    $pdo->beginTransaction();

    // 1. Add Parts Used to technical_stock
    foreach ($parts as $part) {
        $id_product = $part['id_product'];
        $quantity = $part['quantity'];

        // Get current stock for logging
        $stmt_prod = $pdo->prepare("SELECT designation, stock_actuel FROM produits WHERE id_produit = ? FOR UPDATE");
        $stmt_prod->execute([$id_product]);
        $prod = $stmt_prod->fetch(PDO::FETCH_ASSOC);

        if (!$prod)
            throw new Exception("Produit ID $id_product non trouvé");
        if ($prod['stock_actuel'] < $quantity)
            throw new Exception("Stock insuffisant pour {$prod['designation']}");

        $stmt = $pdo->prepare("INSERT INTO technical_stock (id_sav, id_produit, quantite) VALUES (?, ?, ?)");
        $stmt->execute([$id_sav, $id_product, $quantity]);

        logStockMovement($pdo, $id_product, $user_id, 'transfert_sav', $quantity, "Réparation Dossier #$id_sav");

        $new_stock = $prod['stock_actuel'] - $quantity;
        $stmt_upd = $pdo->prepare("UPDATE produits SET stock_actuel = ? WHERE id_produit = ?");
        $stmt_upd->execute([$new_stock, $id_product]);
    }

    // 2. Update SAV Status to 'pret' (since there is no 'attente_test' in new schema)
    // Enum: 'en_attente','en_diagnostic','en_reparation','pret','livre','neuf_hs'
    $stmt_sav = $pdo->prepare("UPDATE sav_dossiers SET statut_sav = 'pret' WHERE id_sav = ?");
    $stmt_sav->execute([$id_sav]);

    // 3. Log to service_logs (id_technicien = technicians.id_technician)
    $tech_id = null;
    $tstmt = $pdo->prepare("SELECT id_technician FROM technicians WHERE id_user = ?");
    $tstmt->execute([$user_id]);
    if ($row = $tstmt->fetch(PDO::FETCH_ASSOC)) $tech_id = $row['id_technician'];
    $stmt_log = $pdo->prepare("
        INSERT INTO service_logs (id_sav, id_user, id_technicien, action, details) 
        VALUES (?, ?, ?, 'Réparation', ?)
    ");
    $details = "Actions: $actions_performed | Pièces utilisées: " . count($parts);
    $stmt_log->execute([$id_sav, $user_id, $tech_id, $details]);

    logActivity($pdo, "Réparation SAV", "Dossier ID: $id_sav, Statut: pret");

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Réparation enregistrée avec succès. Statut passé à Prêt.',
        'new_status' => 'pret'
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction())
        $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
