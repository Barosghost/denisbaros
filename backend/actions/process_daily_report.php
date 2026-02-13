<?php
require_once '../includes/session_init.php';
require_once '../config/db.php';
require_once '../includes/ErrorHandler.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in'])) {
    ErrorHandler::handleApiError('Non authentifié', 401);
}

$action = $_REQUEST['action'] ?? '';
$user_id = $_SESSION['user_id'];

// 1. SUBMIT REPORT
if ($action == 'submit_report' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $report_date = $_POST['report_date'] ?? date('Y-m-d');
    $tasks = trim($_POST['tasks_completed'] ?? '');
    $issues = trim($_POST['issues_found'] ?? '');
    $planned = trim($_POST['planned_next'] ?? '');

    // Combine tasks and planned if needed or just use bilan_activite
    $bilan = $tasks;
    if (!empty($planned)) {
        $bilan .= "\n\nObjectifs de demain :\n" . $planned;
    }

    if (empty($tasks)) {
        echo json_encode(['success' => false, 'message' => 'Les tâches effectuées sont obligatoires']);
        exit();
    }

    try {
        // Check if report already exists for this user on this date
        // Note: date_rapport is a timestamp, we check the date part
        $check = $pdo->prepare("SELECT id_rapport, statut_approbation FROM rapports_journaliers WHERE id_user = ? AND DATE(date_rapport) = ?");
        $check->execute([$user_id, $report_date]);
        $existing = $check->fetch();

        if ($existing) {
            if ($existing['statut_approbation'] == 'valide') {
                echo json_encode(['success' => false, 'message' => 'Vous avez déjà un rapport validé pour cette date']);
                exit();
            }
            // If rejected or pending, we update it
            // Mappings: tasks_completed -> bilan_activite, issues_found -> problemes_rencontres, status -> statut_approbation
            $stmt = $pdo->prepare("UPDATE rapports_journaliers SET bilan_activite = ?, problemes_rencontres = ?, statut_approbation = 'en_attente', reponse_super_admin = NULL WHERE id_rapport = ?");
            $stmt->execute([$bilan, $issues, $existing['id_rapport']]);
            echo json_encode(['success' => true, 'message' => 'Rapport mis à jour avec succès']);
        } else {
            $stmt = $pdo->prepare("INSERT INTO rapports_journaliers (id_user, bilan_activite, problemes_rencontres, statut_approbation) VALUES (?, ?, ?, 'en_attente')");
            $stmt->execute([$user_id, $bilan, $issues]);
            echo json_encode(['success' => true, 'message' => 'Rapport soumis avec succès']);
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
    exit();
}

// 2. APPROVE REPORT (SuperAdmin only)
if ($action == 'approve_report' && $_SESSION['role'] == 'super_admin') {
    $id = $_POST['id_report'];
    try {
        $stmt = $pdo->prepare("UPDATE rapports_journaliers SET statut_approbation = 'valide' WHERE id_rapport = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Rapport validé avec succès']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
    exit();
}

// 3. REJECT REPORT (SuperAdmin only)
if ($action == 'reject_report' && $_SESSION['role'] == 'super_admin') {
    $id = $_POST['id_report'];
    $reason = trim($_POST['rejection_reason'] ?? '');

    if (empty($reason)) {
        echo json_encode(['success' => false, 'message' => 'Le motif du rejet est obligatoire']);
        exit();
    }

    try {
        $stmt = $pdo->prepare("UPDATE rapports_journaliers SET statut_approbation = 'rejete', reponse_super_admin = ? WHERE id_rapport = ?");
        $stmt->execute([$reason, $id]);
        echo json_encode(['success' => true, 'message' => 'Rapport rejeté']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
    exit();
}
