<?php
require_once '../includes/session_init.php';
require_once '../config/db.php';
require_once '../config/functions.php'; // Pour verifyCsrfToken
require_once '../includes/ErrorHandler.php';
require_once '../includes/Logger.php';

// Vérification des droits Super Admin
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'super_admin') {
    ErrorHandler::handleApiError('Accès non autorisé', 403);
}

// Vérification CSRF pour les modifications (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        ErrorHandler::handleApiError('Erreur de sécurité : Jeton CSRF invalide', 403);
    }
}

// L'action peut venir de POST ou GET (pour la récupération)
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Sauvegarde ou mise à jour d'un rôle
if ($action === 'save_role') {
    $id_role = $_POST['id_role'] ?? null;
    $role_name = trim($_POST['role_name']);
    $label = trim($_POST['label']);
    $permissions = $_POST['permissions'] ?? [];
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    try {
        if ($id_role) {
            $stmt = $pdo->prepare("UPDATE roles SET label = ?, permissions = ?, is_active = ? WHERE id_role = ?");
            $stmt->execute([$label, json_encode($permissions), $is_active, $id_role]);
            $msg = "Rôle mis à jour avec succès";
            logActivity($pdo, "Système", "Modification du rôle : $label");
        } else {
            // Vérifier si le nom existe déjà
            $check = $pdo->prepare("SELECT COUNT(*) FROM roles WHERE role_name = ?");
            $check->execute([$role_name]);
            if ($check->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => "Le nom de rôle '$role_name' existe déjà"]);
                exit();
            }

            $stmt = $pdo->prepare("INSERT INTO roles (role_name, label, permissions, is_active) VALUES (?, ?, ?, ?)");
            $stmt->execute([$role_name, $label, json_encode($permissions), $is_active]);
            $msg = "Nouveau rôle créé : $label";
            logActivity($pdo, "Système", $msg);
        }
        echo json_encode(['success' => true, 'message' => $msg]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur technique : ' . $e->getMessage()]);
    }
    exit();
}

// Récupération des détails d'un rôle (AJAX)
if ($action === 'get_role') {
    $id_role = $_GET['id_role'] ?? $_POST['id_role'] ?? null;
    if (!$id_role) {
        echo json_encode(['success' => false, 'message' => 'ID de rôle manquant']);
        exit();
    }

    $stmt = $pdo->prepare("SELECT * FROM roles WHERE id_role = ?");
    $stmt->execute([$id_role]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($role) {
        echo json_encode($role);
    } else {
        echo json_encode(['success' => false, 'message' => 'Rôle non trouvé']);
    }
    exit();
}

// Suppression d'un rôle
if ($action === 'delete_role') {
    $id_role = $_POST['id_role'] ?? null;
    if (!$id_role) {
        echo json_encode(['success' => false, 'message' => 'ID de rôle manquant']);
        exit();
    }

    try {
        // Récupérer le nom du rôle pour vérifier s'il est utilisé
        $stmt_name = $pdo->prepare("SELECT role_name, label FROM roles WHERE id_role = ?");
        $stmt_name->execute([$id_role]);
        $role_data = $stmt_name->fetch();

        if (!$role_data) {
            echo json_encode(['success' => false, 'message' => 'Rôle introuvable']);
            exit();
        }

        // Empêcher la suppression des rôles système critiques
        if (in_array($role_data['role_name'], ['super_admin', 'admin', 'vendeur', 'technicien'])) {
            echo json_encode(['success' => false, 'message' => 'Impossible de supprimer un rôle système protégé']);
            exit();
        }

        // Vérifier si des utilisateurs utilisent ce rôle (la table users utilise la colonne 'role')
        $check_users = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = ?");
        $check_users->execute([$role_data['role_name']]);
        if ($check_users->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'Ce rôle est assigné à des utilisateurs et ne peut pas être supprimé']);
            exit();
        }

        $stmt = $pdo->prepare("DELETE FROM roles WHERE id_role = ?");
        $stmt->execute([$id_role]);

        logActivity($pdo, "Système", "Suppression du rôle : " . $role_data['label']);
        echo json_encode(['success' => true, 'message' => 'Rôle supprimé avec succès']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur technique : ' . $e->getMessage()]);
    }
    exit();
}
