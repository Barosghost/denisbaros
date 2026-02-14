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
if (!canDoAction('send_to_sav')) {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé pour votre rôle']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit;
}

// Fields for sav_dossiers:
// id_client, appareil_modele, num_serie, etat_physique_entree, panne_declaree, statut_sav, est_sous_garantie, cout_estime, date_depot

$num_serie = $input['num_serie'] ?? '';
$appareil_modele = $input['appareil_modele'] ?? '';
$etat_physique = $input['etat_physique_entree'] ?? 'Bon état';
$panne_declaree = $input['panne_declaree'] ?? '';
$id_client = $input['id_client'] ?? null;
$cout_estime = $input['cout_estime'] ?? 0;
$est_sous_garantie = $input['est_sous_garantie'] ?? 0;

if (empty($appareil_modele) || empty($panne_declaree) || empty($id_client)) {
    echo json_encode(['success' => false, 'message' => 'Champs obligatoires manquants']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO sav_dossiers (
        id_client, 
        appareil_modele, 
        num_serie, 
        etat_physique_entree, 
        panne_declaree, 
        statut_sav, 
        est_sous_garantie, 
        cout_estime, 
        date_depot
    ) VALUES (?, ?, ?, ?, ?, 'en_attente', ?, ?, NOW())");

    $stmt->execute([
        $id_client,
        $appareil_modele,
        $num_serie,
        $etat_physique,
        $panne_declaree,
        $est_sous_garantie,
        $cout_estime
    ]);

    $id_sav = $pdo->lastInsertId();

    echo json_encode(['success' => true, 'message' => 'Dossier SAV créé avec succès', 'id_sav' => $id_sav]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => "Erreur: " . $e->getMessage()]);
}
