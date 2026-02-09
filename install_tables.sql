-- Script SQL complet pour créer toutes les tables nécessaires
-- DENIS FBI STORE - Mouvements de Stock & Programme de Fidélité
-- Exécutez ce fichier dans phpMyAdmin ou via ligne de commande

USE denis_fbi_store_baros;

-- ========================================
-- MODULE 1: MOUVEMENTS DE STOCK
-- ========================================

-- Table pour l'historique des mouvements de stock
CREATE TABLE IF NOT EXISTS stock_movements (
    id_movement INT AUTO_INCREMENT PRIMARY KEY,
    id_product INT NOT NULL,
    id_user INT NOT NULL,
    movement_type ENUM('IN', 'OUT', 'ADJUST') NOT NULL COMMENT 'IN=Entrée, OUT=Sortie, ADJUST=Ajustement',
    quantity INT NOT NULL COMMENT 'Quantité du mouvement',
    previous_qty INT NOT NULL COMMENT 'Quantité avant le mouvement',
    new_qty INT NOT NULL COMMENT 'Quantité après le mouvement',
    reason VARCHAR(255) NOT NULL COMMENT 'Raison du mouvement',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_product) REFERENCES products(id_product) ON DELETE CASCADE,
    FOREIGN KEY (id_user) REFERENCES users(id_user),
    INDEX idx_product (id_product),
    INDEX idx_date (created_at),
    INDEX idx_type (movement_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Historique complet des mouvements de stock';

-- Vue pour le rapport de rotation
DROP VIEW IF EXISTS stock_rotation_report;
CREATE VIEW stock_rotation_report AS
SELECT 
    p.id_product,
    p.name AS product_name,
    c.name AS category_name,
    s.quantity AS current_stock,
    COALESCE(SUM(CASE WHEN sm.movement_type = 'OUT' THEN sm.quantity ELSE 0 END), 0) AS total_out_30days,
    COALESCE(SUM(CASE WHEN sm.movement_type = 'IN' THEN sm.quantity ELSE 0 END), 0) AS total_in_30days,
    COALESCE(COUNT(CASE WHEN sm.movement_type = 'OUT' THEN 1 END), 0) AS nb_movements_30days,
    CASE 
        WHEN s.quantity = 0 THEN 'RUPTURE'
        WHEN COALESCE(SUM(CASE WHEN sm.movement_type = 'OUT' THEN sm.quantity ELSE 0 END), 0) = 0 THEN 'INACTIF'
        WHEN COALESCE(SUM(CASE WHEN sm.movement_type = 'OUT' THEN sm.quantity ELSE 0 END), 0) > 50 THEN 'FORTE_ROTATION'
        WHEN COALESCE(SUM(CASE WHEN sm.movement_type = 'OUT' THEN sm.quantity ELSE 0 END), 0) > 20 THEN 'ROTATION_MOYENNE'
        ELSE 'FAIBLE_ROTATION'
    END AS rotation_status
FROM products p
LEFT JOIN categories c ON p.id_category = c.id_category
LEFT JOIN stock s ON p.id_product = s.id_product
LEFT JOIN stock_movements sm ON p.id_product = sm.id_product 
    AND sm.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
WHERE p.status = 'actif'
GROUP BY p.id_product, p.name, c.name, s.quantity;

-- ========================================
-- MODULE 2: PROGRAMME DE FIDÉLITÉ
-- ========================================

-- Ajouter les colonnes de fidélité à la table clients
ALTER TABLE clients 
ADD COLUMN IF NOT EXISTS loyalty_points INT DEFAULT 0 COMMENT 'Points de fidélité accumulés',
ADD COLUMN IF NOT EXISTS loyalty_level ENUM('Bronze', 'Argent', 'Or') DEFAULT 'Bronze' COMMENT 'Niveau de fidélité',
ADD COLUMN IF NOT EXISTS total_spent DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Montant total dépensé';

-- Table pour l'historique des transactions de points
CREATE TABLE IF NOT EXISTS loyalty_transactions (
    id_transaction INT AUTO_INCREMENT PRIMARY KEY,
    id_client INT NOT NULL,
    id_sale INT NULL COMMENT 'Lié à une vente si applicable',
    transaction_type ENUM('EARN', 'REDEEM', 'ADJUST') NOT NULL COMMENT 'EARN=Gagné, REDEEM=Utilisé, ADJUST=Ajustement',
    points INT NOT NULL COMMENT 'Nombre de points',
    description VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_client) REFERENCES clients(id_client) ON DELETE CASCADE,
    FOREIGN KEY (id_sale) REFERENCES sales(id_sale) ON DELETE SET NULL,
    INDEX idx_client (id_client),
    INDEX idx_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Historique des points de fidélité';

-- Table pour le catalogue de récompenses
CREATE TABLE IF NOT EXISTS loyalty_rewards (
    id_reward INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL COMMENT 'Nom de la récompense',
    description TEXT COMMENT 'Description détaillée',
    points_required INT NOT NULL COMMENT 'Points nécessaires',
    discount_amount DECIMAL(10,2) NOT NULL COMMENT 'Montant de la réduction en FCFA',
    min_level ENUM('Bronze', 'Argent', 'Or') DEFAULT 'Bronze' COMMENT 'Niveau minimum requis',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_points (points_required)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Catalogue des récompenses';

-- Insérer les récompenses par défaut
INSERT IGNORE INTO loyalty_rewards (name, description, points_required, discount_amount, min_level) VALUES
('Réduction Bronze 5K', 'Réduction de 5,000 FCFA sur votre prochain achat', 100, 5000.00, 'Bronze'),
('Réduction Argent 10K', 'Réduction de 10,000 FCFA sur votre prochain achat', 200, 10000.00, 'Argent'),
('Réduction Or 20K', 'Réduction de 20,000 FCFA sur votre prochain achat', 300, 20000.00, 'Or'),
('Réduction Premium 50K', 'Réduction de 50,000 FCFA sur votre prochain achat', 500, 50000.00, 'Or');

-- Vue pour les statistiques du programme de fidélité
DROP VIEW IF EXISTS loyalty_stats;
CREATE VIEW loyalty_stats AS
SELECT 
    loyalty_level,
    COUNT(*) AS nb_clients,
    SUM(loyalty_points) AS total_points,
    AVG(loyalty_points) AS avg_points,
    SUM(total_spent) AS total_revenue
FROM clients
GROUP BY loyalty_level;

-- ========================================
-- VÉRIFICATION
-- ========================================

-- Afficher les tables créées
SHOW TABLES LIKE '%stock_movements%';
SHOW TABLES LIKE '%loyalty%';

-- Afficher les vues créées
SHOW FULL TABLES WHERE TABLE_TYPE LIKE 'VIEW';
