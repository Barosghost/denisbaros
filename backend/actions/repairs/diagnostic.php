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

// Technicien : ne peut diagnostiquer que ses dossiers assignés
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
$decision = $data['decision'] ?? 'reparable';
$notes = $data['notes'] ?? '';
$affected_component = $data['affected_component'] ?? '';

if (!$id_sav) {
    echo json_encode(['success' => false, 'message' => 'ID dossier manquant']);
    exit();
}

try {
    $pdo->beginTransaction();

    // 1. Determine new status
    // Enum: 'en_attente','en_diagnostic','en_reparation','pret','livre','neuf_hs'
    $new_status = match ($decision) {
        'reparable' => 'en_reparation',
        'reparable_pieces' => 'en_reparation',
        'irreparable' => 'neuf_hs',
        default => 'en_diagnostic'
    };

    // id_technicien dans sav_dossiers = technicians.id_technician (pas id_user)
    $tech_id = null;
    $tstmt = $pdo->prepare("SELECT id_technician FROM technicians WHERE id_user = ?");
    $tstmt->execute([$user_id]);
    if ($row = $tstmt->fetch(PDO::FETCH_ASSOC)) $tech_id = $row['id_technician'];

    $diagnostic_final = $notes . ($affected_component ? " (Composant: $affected_component)" : "");
    $stmt = $pdo->prepare("
        UPDATE sav_dossiers 
        SET statut_sav = ?, diagnostic_final = ?, id_technicien = COALESCE(?, id_technicien) 
        WHERE id_sav = ?
    ");
    $stmt->execute([$new_status, $diagnostic_final, $tech_id, $id_sav]);

    // 3. Log to service_logs (id_technicien = technicians.id_technician)
    $stmt_log = $pdo->prepare("
        INSERT INTO service_logs (id_sav, id_user, id_technicien, action, details) 
        VALUES (?, ?, ?, 'Diagnostic', ?)
    ");
    $details = "Décision: $decision. Notes: $notes" . ($affected_component ? " | Composant: $affected_component" : "");
    $stmt_log->execute([$id_sav, $user_id, $tech_id, $details]);

    logActivity($pdo, "Diagnostic SAV", "Dossier ID: $id_sav, Statut: $new_status");

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Diagnostic enregistré avec succès',
        'new_status' => $new_status
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction())
        $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
