-- Migration pour le système de traçabilité des mouvements de stock
-- Date: 2026-01-30

USE denis_fbi_store_baros;

-- Table pour enregistrer tous les mouvements de stock
CREATE TABLE IF NOT EXISTS stock_movements (
    id_movement INT AUTO_INCREMENT PRIMARY KEY,
    id_product INT NOT NULL,
    id_user INT NOT NULL,
    movement_type ENUM('IN', 'OUT', 'ADJUST') NOT NULL COMMENT 'IN=Entrée, OUT=Sortie, ADJUST=Ajustement',
    quantity INT NOT NULL COMMENT 'Quantité du mouvement (positif ou négatif)',
    previous_qty INT NOT NULL COMMENT 'Quantité avant le mouvement',
    new_qty INT NOT NULL COMMENT 'Quantité après le mouvement',
    reason VARCHAR(255) NOT NULL COMMENT 'Raison du mouvement (ex: Vente #123, Réapprovisionnement, Inventaire)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_product) REFERENCES products(id_product) ON DELETE CASCADE,
    FOREIGN KEY (id_user) REFERENCES users(id_user),
    INDEX idx_product (id_product),
    INDEX idx_date (created_at),
    INDEX idx_type (movement_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Historique complet des mouvements de stock';

-- Vue pour faciliter les rapports de rotation
CREATE OR REPLACE VIEW stock_rotation_report AS
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

-- Insertion de données de test (optionnel - à supprimer en production)
-- Cette ligne sera commentée pour éviter les doublons
-- INSERT INTO stock_movements (id_product, id_user, movement_type, quantity, previous_qty, new_qty, reason)
-- SELECT s.id_product, 1, 'ADJUST', s.quantity, 0, s.quantity, 'Migration initiale - Stock existant'
-- FROM stock s;
