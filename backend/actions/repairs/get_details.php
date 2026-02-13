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
$role = str_replace(' ', '_', strtolower($_SESSION['role'] ?? ''));
if (!in_array($role, ['super_admin', 'admin', 'chef_technique', 'technicien'])) {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit;
}

$id_sav = $_GET['id'] ?? null;

if (!$id_sav) {
    echo json_encode(['success' => false, 'message' => 'ID de dossier manquant']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT s.*, 
               c.nom_client as client_name, 
               c.telephone as client_phone,
               t.fullname as technician_name
        FROM sav_dossiers s
        LEFT JOIN clients c ON s.id_client = c.id_client
        LEFT JOIN technicians t ON s.id_technicien = t.id_technician
        WHERE s.id_sav = ?
    ");
    $stmt->execute([$id_sav]);
    $repair = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$repair) {
        echo json_encode(['success' => false, 'message' => 'Dossier non trouvé']);
        exit;
    }

    // Technicien : accès uniquement à ses dossiers assignés
    if ($role === 'technicien') {
        $tech_stmt = $pdo->prepare("SELECT id_technician FROM technicians WHERE id_user = ?");
        $tech_stmt->execute([$_SESSION['user_id']]);
        $my_tech_id = $tech_stmt->fetchColumn();
        if (!$my_tech_id || (int)$repair['id_technicien'] !== (int)$my_tech_id) {
            echo json_encode(['success' => false, 'message' => 'Accès non autorisé à ce dossier']);
            exit;
        }
    }

    // Mapping for UI consistency (if needed)
    $repair['id'] = $repair['id_sav'];
    $repair['model'] = $repair['appareil_modele'];
    $repair['serial_number'] = $repair['num_serie'];
    $repair['status'] = $repair['statut_sav'];
    $repair['entry_date'] = $repair['date_depot'];
    $repair['failure_reason'] = $repair['panne_declaree'];
    $repair['entry_service'] = ($repair['est_sous_garantie'] ? 'Garantie' : 'Hors Garantie');

    // 2. Fetch History (service_logs)
    $stmt = $pdo->prepare("
        SELECT l.*, u.username as username
        FROM service_logs l
        LEFT JOIN utilisateurs u ON l.id_user = u.id_user
        WHERE l.id_sav = ?
        ORDER BY l.date DESC
    ");
    $stmt->execute([$id_sav]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch Parts (technical_stock)
    $stmt = $pdo->prepare("
        SELECT ts.*, p.designation as name, p.prix_boutique_fixe as price
        FROM technical_stock ts
        JOIN produits p ON ts.id_produit = p.id_produit
        WHERE ts.id_sav = ?
    ");
    $stmt->execute([$id_sav]);
    $parts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Photos
    $stmt = $pdo->prepare("SELECT * FROM sav_photos WHERE id_sav = ?");
    $stmt->execute([$id_sav]);
    $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Costs Calculation
    $parts_cost = 0;
    foreach ($parts as $p) {
        $parts_cost += ($p['price'] * $p['quantite']);
    }

    // We don't have a labor cost column in sav_dossiers, maybe stored in logs or just use cout_estime?
    // Let's assume cout_estime is the total target or labor component.
    $labor_cost = $repair['cout_estime'];
    $total_cost = $parts_cost + $labor_cost;

    echo json_encode([
        'success' => true,
        'repair' => $repair,
        'history' => $history,
        'parts' => $parts,
        'photos' => $photos,
        'costs' => [
            'parts_cost' => $parts_cost,
            'labor_cost' => $labor_cost,
            'total_cost' => $total_cost,
            'machine_value' => 0 // Not in schema
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
