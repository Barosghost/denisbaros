<?php
require_once '../../config/db.php';
require_once '../../config/functions.php';
require_once '../../includes/auth_required.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit;
}

$fullname = trim($input['fullname'] ?? '');
$phone = trim($input['phone'] ?? '');
$address = trim($input['address'] ?? '');

if (empty($fullname) || empty($phone)) {
    echo json_encode(['success' => false, 'message' => 'Le nom et le téléphone sont obligatoires']);
    exit;
}

try {
    // Check if client already exists by phone
    $stmt = $pdo->prepare("SELECT id_client FROM clients WHERE telephone = ?");
    $stmt->execute([$phone]);
    $existing_id = $stmt->fetchColumn();

    if ($existing_id) {
        echo json_encode([
            'success' => true,
            'message' => 'Client existant utilisé',
            'client_id' => $existing_id
        ]);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO clients (nom_client, telephone, adresse) VALUES (?, ?, ?)");
    $stmt->execute([$fullname, $phone, $address]);
    $client_id = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'Client créé avec succès',
        'client_id' => $client_id
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => "Erreur: " . $e->getMessage()]);
}
