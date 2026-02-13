<?php
require_once __DIR__ . '/../includes/session_init.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../includes/ErrorHandler.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}
$role = str_replace(' ', '_', strtolower(trim($_SESSION['role'] ?? '')));
if ($role !== 'super_admin') {
    echo json_encode(['success' => false, 'message' => 'Accès réservé au Super Admin']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Jeton CSRF invalide. Rechargez la page.']);
        exit;
    }
}

$action = $_POST['action'] ?? '';

if ($action === 'save_settings') {
    $settings = $_POST['settings'] ?? [];
    if (!is_array($settings)) {
        echo json_encode(['success' => false, 'message' => 'Données invalides']);
        exit;
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO system_settings (`key`, `value`, category) 
                               VALUES (:key, :value, :category) 
                               ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), category = VALUES(category)");

        foreach ($settings as $key => $data) {
            if (!is_array($data)) continue;
            $value = $data['value'] ?? '';
            if (is_array($value)) $value = end($value);
            $category = $data['category'] ?? 'general';
            if (is_array($category)) $category = end($category);
            $stmt->execute([
                'key' => $key,
                'value' => (string) $value,
                'category' => (string) $category
            ]);
        }

        $pdo->commit();
        if (function_exists('logActivity')) {
            logActivity($pdo, "Système", "Mise à jour des paramètres système", "", true);
        }
        echo json_encode(['success' => true, 'message' => 'Paramètres enregistrés avec succès']);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Erreur base de données. Vérifiez que la table system_settings existe.']);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'upload_logo') {
    if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Erreur lors du téléchargement (fichier manquant ou erreur ' . ($_FILES['logo']['error'] ?? '') . ')']);
        exit;
    }

    $upload_dir = __DIR__ . '/../../frontend/assets/img/';
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0755, true);
    }
    if (!is_dir($upload_dir) || !is_writable($upload_dir)) {
        echo json_encode(['success' => false, 'message' => 'Dossier assets/img inaccessible en écriture']);
        exit;
    }

    $file_ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
    if (!in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        echo json_encode(['success' => false, 'message' => 'Format non autorisé (PNG, JPG, GIF, WebP uniquement)']);
        exit;
    }
    $file_name = 'logo_denis_' . time() . '.' . $file_ext;
    $target_file = $upload_dir . $file_name;

    if (move_uploaded_file($_FILES['logo']['tmp_name'], $target_file)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO system_settings (`key`, `value`, category) 
                                   VALUES ('company_logo', :value, 'general') 
                                   ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
            $stmt->execute(['value' => 'assets/img/' . $file_name]);
        } catch (PDOException $e) { /* ignore */ }
        if (function_exists('logActivity')) {
            logActivity($pdo, "Système", "Changement du logo de l'entreprise", "", true);
        }
        echo json_encode(['success' => true, 'message' => 'Logo mis à jour', 'path' => 'assets/img/' . $file_name]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Impossible d\'enregistrer le fichier sur le serveur']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
