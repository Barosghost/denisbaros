<?php
/**
 * Log an activity to the database
 *
 * @param PDO $pdo The PDO connection object
 * @param string|int $action_or_user_id The action performed OR user ID (for backward compatibility)
 * @param string $action_or_details The action or details
 * @param string $details Optional details about the action
 * @param bool $critical If true and param log_critical_actions=1, log with colonne_critique=1
 * @return bool True on success, false on failure
 */
function logActivity($pdo, $action_or_user_id, $action_or_details = "", $details = "", $critical = false)
{
    try {
        if (is_numeric($action_or_user_id)) {
            $user_id = $action_or_user_id;
            $action = $action_or_details;
        } else {
            $action = $action_or_user_id;
            $details = $action_or_details;
            $user_id = $_SESSION['user_id'] ?? null;
        }

        $full_action = $action;
        if (!empty($details)) {
            $full_action .= " - " . $details;
        }
        $log_ip = '1';
        $log_critical = '1';
        try {
            $stmt = $pdo->query("SELECT `key`, `value` FROM system_settings WHERE `key` IN ('log_user_ip','log_critical_actions')");
            if ($stmt) {
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    if ($row['key'] === 'log_user_ip') $log_ip = $row['value'];
                    if ($row['key'] === 'log_critical_actions') $log_critical = $row['value'];
                }
            }
        } catch (PDOException $e) { /* ignore */ }
        if ($log_ip === '1') {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            if ($ip) $full_action .= " [IP: " . $ip . "]";
        }
        $is_critical = $critical && ($log_critical === '1');
        $stmt = $pdo->prepare("INSERT INTO logs_systeme (id_user, action_detaillee, colonne_critique) VALUES (?, ?, ?)");
        return $stmt->execute([$user_id, $full_action, $is_critical ? 1 : 0]);
    } catch (PDOException $e) {
        error_log("Activity Log Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate a CSRF token and store it in the session
 * @return string The generated token
 */
function generateCsrfToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify the CSRF token from a request
 * @param string $token The token to verify
 * @return bool True if valid, false otherwise
 */
function verifyCsrfToken($token)
{
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get the HTML input field for CSRF token
 * @return string The HTML input field
 */
function getCsrfInput()
{
    $token = generateCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Enregistre un mouvement de stock
 * @param PDO $pdo - Connexion à la base de données
 * @param int $productId - ID du produit
 * @param int $userId - ID de l'utilisateur
 * @param string $type - Type de mouvement (IN, OUT, ADJUST)
 * @param int $quantity - Quantité du mouvement
 * @param string $reason - Raison du mouvement
 */
function logStockMovement($pdo, $productId, $userId, $type, $quantity, $reason)
{
    try {
        // Récupérer la quantité actuelle
        // Use 'produits' table and 'stock_actuel'
        $stmt = $pdo->prepare("SELECT stock_actuel FROM produits WHERE id_produit = ?");
        $stmt->execute([$productId]);
        $stock = $stmt->fetch();

        if (!$stock) {
            return false;
        }

        $previousQty = $stock['stock_actuel'];

        // Calculer la nouvelle quantité selon le type
        // 'entree','vente','transfert_sav','ajustement_manuel','retour_fournisseur'
        if ($type === 'entree') {
            $newQty = $previousQty + $quantity;
        } elseif ($type === 'vente' || $type === 'transfert_sav' || $type === 'retour_fournisseur') {
            $newQty = $previousQty - $quantity;
        } else { // 'ajustement_manuel' (depends on logic, assume + or - handled by caller or sign? 
            // Usually ajustement replaces or adds. 
            // Given the simple logic before, let's assume 'ajustement_manuel' is just setting it or adding?
            // The old code: 'ADJUST' -> $newQty = $quantity. 
            // Let's keep that logic for manual adjustment if that's the intent, or just assume it's a delta.
            // Actually, best to just log what happened. 
            // If the caller provides the delta, we calculate new. If caller provides new, we calc delta.
            // Let's fit the old function signature: $quantity is the amount changed.
            // If 'ajustement_manuel' implies setting to a value, we need to know that value.
            // But the function takes $quantity. Let's assume $quantity is the delta for simplicity or the new value?
            // Old code: if ADJUST, $newQty = $quantity. So $quantity was the target value.
            // Let's stick to that for 'ajustement_manuel'.
            $newQty = $quantity;
        }

        // Mouvements stock table: id_mouvement, id_produit, id_user, type_mouvement, quantite_avant, quantite_apres, motif_ajustement, date_mouvement
        $stmt = $pdo->prepare("
            INSERT INTO mouvements_stock 
            (id_produit, id_user, type_mouvement, quantite_avant, quantite_apres, motif_ajustement) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$productId, $userId, $type, $previousQty, $newQty, $reason]);

        return true;

    } catch (Exception $e) {
        error_log("Erreur log mouvement stock: " . $e->getMessage());
        return false;
    }
}
?>