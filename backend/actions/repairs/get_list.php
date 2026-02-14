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

try {
    $status_filter = $_GET['status'] ?? '';
    $technician_filter = $_GET['technician'] ?? '';

    $query = "
        SELECT s.id_sav as id, 
               s.appareil_modele as model,
               s.num_serie as serial_number,
               s.statut_sav as status,
               s.date_depot as entry_date,
               t.fullname as technician_name,
               s.id_technicien as id_technician
        FROM sav_dossiers s
        LEFT JOIN technicians t ON s.id_technicien = t.id_technician
        WHERE 1=1
    ";
    $params = [];

    // Technicien : uniquement ses dossiers assignés
    if ($role === 'technicien') {
        $tech_stmt = $pdo->prepare("SELECT id_technician FROM technicians WHERE id_user = ?");
        $tech_stmt->execute([$_SESSION['user_id']]);
        $my_tech_id = $tech_stmt->fetchColumn();
        if ($my_tech_id) {
            $query .= " AND s.id_technicien = ?";
            $params[] = $my_tech_id;
        } else {
            $query .= " AND 1=0";
        }
    }

    if ($status_filter) {
        $query .= " AND s.statut_sav = ?";
        $params[] = $status_filter;
    }

    if ($technician_filter && $role !== 'technicien') {
        $query .= " AND s.id_technicien = ?";
        $params[] = $technician_filter;
    }

    $query .= " ORDER BY s.date_depot DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $repairs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'repairs' => $repairs]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
