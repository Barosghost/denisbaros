<?php
/**
 * DENIS FBI STORE - Système d'archivage automatique
 * Ce script sauvegarde les mouvements de stock du mois précédent dans un fichier CSV.
 */

require_once __DIR__ . '/../config/db.php';

function runAutoBackup($pdo)
{
    $backupDir = __DIR__ . '/../backups/stock_movements/';
    $lastBackupFile = $backupDir . '.last_backup';

    // Créer le dossier s'il n'existe pas
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0777, true);
    }

    $currentMonth = date('Y-m');
    $lastBackup = file_exists($lastBackupFile) ? trim(file_get_contents($lastBackupFile)) : '';

    // Si on a déjà fait le backup pour ce mois-ci, on s'arrête
    if ($lastBackup === $currentMonth) {
        return;
    }

    // On veut sauvegarder les données du mois PRÉCÉDENT
    $targetMonth = date('Y-m', strtotime('first day of last month'));
    $fileName = 'mouvements_stock_' . str_replace('-', '_', $targetMonth) . '.csv';
    $filePath = $backupDir . $fileName;

    // Si le fichier existe déjà, on ne veut pas l'écraser (on a déjà archivé ce mois)
    if (file_exists($filePath)) {
        file_put_contents($lastBackupFile, $currentMonth);
        return;
    }

    try {
        // Récupérer les données du mois précédent
        $stmt = $pdo->prepare("
            SELECT sm.*, p.name as product_name, u.username 
            FROM stock_movements sm
            JOIN products p ON sm.id_product = p.id_product
            JOIN users u ON sm.id_user = u.id_user
            WHERE DATE_FORMAT(sm.created_at, '%Y-%m') = ?
            ORDER BY sm.created_at ASC
        ");
        $stmt->execute([$targetMonth]);
        $movements = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($movements) > 0) {
            $fp = fopen($filePath, 'w');

            // UTF-8 BOM pour Excel
            fputs($fp, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));

            // En-têtes
            fputcsv($fp, ['ID', 'Date', 'Produit', 'Type', 'Quantité', 'Avant', 'Après', 'Raison', 'Utilisateur']);

            foreach ($movements as $m) {
                $type = $m['movement_type'] === 'IN' ? 'Entrée' : ($m['movement_type'] === 'OUT' ? 'Sortie' : 'Ajustement');
                fputcsv($fp, [
                    $m['id_movement'],
                    $m['created_at'],
                    $m['product_name'],
                    $type,
                    $m['quantity'],
                    $m['previous_qty'],
                    $m['new_qty'],
                    $m['reason'],
                    $m['username']
                ]);
            }

            fclose($fp);

            // Tracer l'activité
            $logMsg = "Archive automatique créée : $fileName (" . count($movements) . " mouvements)";
            $stmtLog = $pdo->prepare("INSERT INTO action_logs (id_user, action, details) VALUES (?, ?, ?)");
            $stmtLog->execute([1, 'Backup Auto', $logMsg]); // On utilise l'admin ID 1 par défaut
        }

        // Mettre à jour la date du dernier check réussi
        file_put_contents($lastBackupFile, $currentMonth);

    } catch (Exception $e) {
        // En cas d'erreur, on log mais on ne bloque pas l'application
        error_log("Erreur Backup Auto: " . $e->getMessage());
    }
}

// Exécuter si appelé directement ou inclus
if (isset($pdo)) {
    runAutoBackup($pdo);
}
?>