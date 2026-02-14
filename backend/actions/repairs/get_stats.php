<?php
require_once __DIR__ . '/../../config/db.php';
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

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM sav_dossiers");
    $total_repairs = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT statut_sav as status, COUNT(*) as count FROM sav_dossiers GROUP BY statut_sav");
    $by_status = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("SELECT COUNT(*) FROM sav_dossiers WHERE statut_sav IN ('en_attente', 'en_diagnostic')");
    $pending = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM sav_dossiers WHERE statut_sav = 'en_reparation'");
    $in_progress = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM sav_dossiers WHERE statut_sav = 'livre' AND MONTH(date_depot) = MONTH(CURDATE()) AND YEAR(date_depot) = YEAR(CURDATE())");
    $completed_month = $stmt->fetchColumn();

    $stmt = $pdo->query("
        SELECT t.id_technician, t.fullname as technician_name, COUNT(s.id_sav) as dossier_count
        FROM sav_dossiers s
        JOIN technicians t ON s.id_technicien = t.id_technician
        GROUP BY s.id_technicien, t.id_technician, t.fullname
        ORDER BY dossier_count DESC
        LIMIT 5
    ");
    $top_technicians = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("SELECT id_technician, fullname FROM technicians ORDER BY fullname");
    $technicians = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'stats' => [
            'total_repairs' => (int) $total_repairs,
            'by_status' => $by_status,
            'pending' => (int) $pending,
            'in_progress' => (int) $in_progress,
            'completed_month' => (int) $completed_month,
            'top_technicians' => $top_technicians,
            'technicians' => $technicians
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
