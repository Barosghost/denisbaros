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
$exit_type = $data['exit_type'] ?? 'livraison';
$notes = $data['notes'] ?? '';

if (!$id_sav) {
    echo json_encode(['success' => false, 'message' => 'ID dossier manquant']);
    exit();
}

try {
    $pdo->beginTransaction();

    // 1. Determine new status
    // Enum: 'en_attente','en_diagnostic','en_reparation','pret','livre','neuf_hs'
    $new_status = ($exit_type === 'livraison') ? 'livre' : 'neuf_hs';

    // 2. Update sav_dossiers
    $stmt = $pdo->prepare("UPDATE sav_dossiers SET statut_sav = ? WHERE id_sav = ?");
    $stmt->execute([$new_status, $id_sav]);

    // 3. Log to service_logs
    $stmt_log = $pdo->prepare("
        INSERT INTO service_logs (id_sav, id_user, action, details) 
        VALUES (?, ?, 'Sortie', ?)
    ");
    $details = "Type Sortie: $exit_type | Notes: $notes";
    $stmt_log->execute([$id_sav, $user_id, $details]);

    logActivity($pdo, "Sortie SAV", "Dossier ID: $id_sav, Type: $exit_type");

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Sortie enregistrée avec succès',
        'new_status' => $new_status
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction())
        $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
