-- Migration pour le Programme de Fidélité
-- Date: 2026-01-30

USE denis_fbi_store_baros;

-- Ajouter les colonnes de fidélité à la table clients
ALTER TABLE clients 
ADD COLUMN IF NOT EXISTS loyalty_points INT DEFAULT 0 COMMENT 'Points de fidélité accumulés',
ADD COLUMN IF NOT EXISTS loyalty_level ENUM('Bronze', 'Argent', 'Or', 'Platine') DEFAULT 'Bronze' COMMENT 'Niveau de fidélité',
ADD COLUMN IF NOT EXISTS total_spent DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Montant total dépensé (pour calcul niveau)',
ADD INDEX idx_loyalty_level (loyalty_level);

-- Table pour l'historique des transactions de points
CREATE TABLE IF NOT EXISTS loyalty_transactions (
    id_transaction INT AUTO_INCREMENT PRIMARY KEY,
    id_client INT NOT NULL,
    id_sale INT NULL COMMENT 'Lié à une vente si applicable',
    transaction_type ENUM('EARN', 'REDEEM', 'ADJUST') NOT NULL COMMENT 'EARN=Gagné, REDEEM=Utilisé, ADJUST=Ajustement',
    points INT NOT NULL COMMENT 'Nombre de points (positif ou négatif)',
    description VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_client) REFERENCES clients(id_client) ON DELETE CASCADE,
    FOREIGN KEY (id_sale) REFERENCES sales(id_sale) ON DELETE SET NULL,
    INDEX idx_client (id_client),
    INDEX idx_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Historique des points de fidélité';

-- Table pour les récompenses configurables
CREATE TABLE IF NOT EXISTS loyalty_rewards (
    id_reward INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL COMMENT 'Nom de la récompense',
    description TEXT COMMENT 'Description détaillée',
    points_required INT NOT NULL COMMENT 'Points nécessaires',
    discount_amount DECIMAL(10,2) NOT NULL COMMENT 'Montant de la réduction en FCFA',
    min_level ENUM('Bronze', 'Argent', 'Or', 'Platine') DEFAULT 'Bronze' COMMENT 'Niveau minimum requis',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_points (points_required)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Catalogue des récompenses';

-- Insérer des récompenses par défaut
INSERT INTO loyalty_rewards (name, description, points_required, discount_amount, min_level) VALUES
('Réduction Bronze 5K', 'Réduction de 5,000 FCFA sur votre prochain achat', 50, 5000.00, 'Bronze'),
('Réduction Argent 15K', 'Réduction de 15,000 FCFA sur votre prochain achat (Membres Argent+)', 150, 15000.00, 'Argent'),
('Réduction Or 50K', 'Réduction de 50,000 FCFA sur votre prochain achat (Membres Or+)', 500, 50000.00, 'Or'),
('Réduction Platine 100K', 'Réduction de 100,000 FCFA sur votre prochain achat (VIP Platine)', 1000, 100000.00, 'Platine');

-- Vue pour les statistiques du programme de fidélité
CREATE OR REPLACE VIEW loyalty_stats AS
SELECT 
    loyalty_level,
    COUNT(*) AS nb_clients,
    SUM(loyalty_points) AS total_points,
    AVG(loyalty_points) AS avg_points,
    SUM(total_spent) AS total_revenue
FROM clients
GROUP BY loyalty_level;
