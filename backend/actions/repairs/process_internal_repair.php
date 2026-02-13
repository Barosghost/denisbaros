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
if (!canDoAction('sav_technician')) {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$user_id = $_SESSION['user_id'];
$id_product = $data['id_product'] ?? 0;
$serial = trim($data['serial_number'] ?? '');
$description = trim($data['description'] ?? 'Transfert Interne');

if (!$id_product || empty($description)) {
    echo json_encode(['success' => false, 'message' => 'ID produit et description obligatoires']);
    exit();
}

try {
    $pdo->beginTransaction();

    // 1. Get Product Details and Lock
    $stmt = $pdo->prepare("SELECT designation, stock_actuel FROM produits WHERE id_produit = ? FOR UPDATE");
    $stmt->execute([$id_product]);
    $prod = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$prod)
        throw new Exception("Produit non trouvé");
    if ($prod['stock_actuel'] <= 0)
        throw new Exception("Stock insuffisant");

    // 2. Find or Create Internal Client
    $stmt = $pdo->prepare("SELECT id_client FROM clients WHERE nom_client = 'FBI STORE (INTERNE)' LIMIT 1");
    $stmt->execute();
    $id_client = $stmt->fetchColumn();

    if (!$id_client) {
        $stmt = $pdo->prepare("INSERT INTO clients (nom_client, telephone, adresse) VALUES ('FBI STORE (INTERNE)', '0000', 'Magasin')");
        $stmt->execute();
        $id_client = $pdo->lastInsertId();
    }

    // 3. Update Product Stock
    $new_stock = $prod['stock_actuel'] - 1;
    $stmt_upd = $pdo->prepare("UPDATE produits SET stock_actuel = ? WHERE id_produit = ?");
    $stmt_upd->execute([$new_stock, $id_product]);

    // 4. Create SAV Dossier
    $stmt_sav = $pdo->prepare("
        INSERT INTO sav_dossiers (
            id_client, appareil_modele, num_serie, etat_physique_entree, 
            panne_declaree, statut_sav, est_sous_garantie, date_depot
        ) VALUES (?, ?, ?, 'Transfert Stock', ?, 'en_attente', 1, NOW())
    ");
    $stmt_sav->execute([
        $id_client,
        $prod['designation'],
        $serial,
        "TRANSFERT INTERNE: " . $description
    ]);
    $id_sav = $pdo->lastInsertId();

    // 5. Log Stock Movement
    logStockMovement($pdo, $id_product, $user_id, 'transfert_sav', 1, "Transfert SAV Dossier #$id_sav");

    // 6. Log to service_logs
    $stmt_log = $pdo->prepare("INSERT INTO service_logs (id_sav, id_user, action, details) VALUES (?, ?, 'Ouverture', 'Transfert depuis le stock de vente')");
    $stmt_log->execute([$id_sav, $user_id]);

    logActivity($pdo, "Transfert Réparation Interne", "Produit: {$prod['designation']} (SN: $serial) envoyé en SAV #$id_sav");

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Produit transféré au Service Technique avec succès',
        'id_sav' => $id_sav
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction())
        $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
