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

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit();
}

$user_id = $_SESSION['user_id'];
$id_sav = $data['id_repair'] ?? 0;
$test_result = $data['test_result'] ?? 'reussi';
$notes = $data['notes'] ?? '';

if (!$id_sav) {
    echo json_encode(['success' => false, 'message' => 'ID dossier manquant']);
    exit();
}

try {
    $pdo->beginTransaction();

    // 1. Determine new status
    // If failed, go back to repair. If success, stay/move to pret.
    $new_status = ($test_result === 'reussi') ? 'pret' : 'en_reparation';

    // 2. Update sav_dossiers
    $stmt = $pdo->prepare("UPDATE sav_dossiers SET statut_sav = ? WHERE id_sav = ?");
    $stmt->execute([$new_status, $id_sav]);

    $tech_id = null;
    $tstmt = $pdo->prepare("SELECT id_technician FROM technicians WHERE id_user = ?");
    $tstmt->execute([$user_id]);
    if ($row = $tstmt->fetch(PDO::FETCH_ASSOC)) $tech_id = $row['id_technician'];

    $stmt_log = $pdo->prepare("
        INSERT INTO service_logs (id_sav, id_user, id_technicien, action, details) 
        VALUES (?, ?, ?, 'Test', ?)
    ");
    $details = "Résultat: $test_result | Notes: $notes";
    $stmt_log->execute([$id_sav, $user_id, $tech_id, $details]);

    logActivity($pdo, "Test SAV", "Dossier ID: $id_sav, Résultat: $test_result");

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Test enregistré avec succès',
        'new_status' => $new_status
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction())
        $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
