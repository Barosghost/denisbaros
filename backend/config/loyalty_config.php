<?php
/**
 * Configuration du Programme de Fidélité
 * DENIS FBI STORE
 */

// ========================================
// CONFIGURATION DES POINTS
// ========================================

// Ratio de conversion : 1 point pour chaque 500 FCFA dépensés
define('LOYALTY_POINTS_PER_FCFA', 500);

// ========================================
// CONFIGURATION DES NIVEAUX
// ========================================

// Seuils de montant total dépensé pour chaque niveau
define('LEVEL_BRONZE_MIN', 0);
define('LEVEL_SILVER_MIN', 150000);
define('LEVEL_GOLD_MIN', 500000);
define('LEVEL_PLATINUM_MIN', 1000000);

// Noms des niveaux (pour affichage)
define('LOYALTY_LEVELS', [
    'Bronze' => [
        'name' => 'Bronze',
        'icon' => '🟤',
        'color' => '#8D6E63', // Brownish
        'min_spent' => LEVEL_BRONZE_MIN,
        'multiplier' => 1.0 // Standard
    ],
    'Argent' => [
        'name' => 'Argent',
        'icon' => '⚪',
        'color' => '#C0C0C0',
        'min_spent' => LEVEL_SILVER_MIN,
        'multiplier' => 1.10 // +10%
    ],
    'Or' => [
        'name' => 'Or',
        'icon' => '🟡',
        'color' => '#FFD700',
        'min_spent' => LEVEL_GOLD_MIN,
        'multiplier' => 1.25 // +25%
    ],
    'Platine' => [
        'name' => 'Platine',
        'icon' => '🔵',
        'color' => '#E5E4E2', // Platinum/Blueish
        'min_spent' => LEVEL_PLATINUM_MIN,
        'multiplier' => 1.50 // +50%
    ]
]);

// ========================================
// FONCTIONS UTILITAIRES
// ========================================

/**
 * Calcule le nombre de points gagnés pour un montant donné
 */
function calculateLoyaltyPoints($amount, $level = 'Bronze')
{
    $basePoints = floor($amount / LOYALTY_POINTS_PER_FCFA);
    $info = getLevelInfo($level);
    $multiplier = $info['multiplier'] ?? 1.0;

    return ceil($basePoints * $multiplier);
}

/**
 * Détermine le niveau de fidélité basé sur le montant total dépensé
 */
function calculateLoyaltyLevel($totalSpent)
{
    if ($totalSpent >= LEVEL_PLATINUM_MIN) {
        return 'Platine';
    } elseif ($totalSpent >= LEVEL_GOLD_MIN) {
        return 'Or';
    } elseif ($totalSpent >= LEVEL_SILVER_MIN) {
        return 'Argent';
    } else {
        return 'Bronze';
    }
}

/**
 * Récupère les informations d'un niveau
 */
function getLevelInfo($level)
{
    return LOYALTY_LEVELS[$level] ?? LOYALTY_LEVELS['Bronze'];
}

/**
 * Ajoute des points de fidélité à un client
 * @param PDO $pdo - Connexion à la base de données
 * @param int $clientId - ID du client
 * @param int $saleId - ID de la vente
 * @param float $amount - Montant de la vente
 * @return array|null - Informations sur les points ajoutés et le niveau, ou null si pas de client
 */
function addLoyaltyPoints($pdo, $clientId, $saleId, $amount)
{
    if (!$clientId) {
        return null; // Pas de client (vente comptoir)
    }

    try {
        // Récupérer les données actuelles du client
        $stmt = $pdo->prepare("SELECT loyalty_points, total_spent, loyalty_level FROM clients WHERE id_client = ?");
        $stmt->execute([$clientId]);
        $client = $stmt->fetch();

        if (!$client) {
            return null;
        }

        // Calculer les points avec le multiplicateur du niveau ACTUEL
        $pointsEarned = calculateLoyaltyPoints($amount, $client['loyalty_level']);

        if ($pointsEarned <= 0) {
            return null;
        }

        // Calculer les nouvelles valeurs
        $newPoints = $client['loyalty_points'] + $pointsEarned;
        $newTotalSpent = $client['total_spent'] + $amount;
        $oldLevel = $client['loyalty_level'];
        $newLevel = calculateLoyaltyLevel($newTotalSpent);

        // Mettre à jour le client
        $stmt = $pdo->prepare("
            UPDATE clients 
            SET loyalty_points = ?, 
                total_spent = ?, 
                loyalty_level = ?
            WHERE id_client = ?
        ");
        $stmt->execute([$newPoints, $newTotalSpent, $newLevel, $clientId]);

        // Enregistrer la transaction de points
        $stmt = $pdo->prepare("
            INSERT INTO loyalty_transactions 
            (id_client, id_sale, transaction_type, points, description) 
            VALUES (?, ?, 'EARN', ?, ?)
        ");
        $description = "Achat de " . number_format($amount, 0, ',', ' ') . " FCFA - Vente #" . $saleId;
        $stmt->execute([$clientId, $saleId, $pointsEarned, $description]);

        return [
            'points_earned' => $pointsEarned,
            'total_points' => $newPoints,
            'old_level' => $oldLevel,
            'new_level' => $newLevel,
            'level_changed' => ($oldLevel !== $newLevel)
        ];

    } catch (Exception $e) {
        error_log("Erreur ajout points fidélité: " . $e->getMessage());
        return null;
    }
}
?>