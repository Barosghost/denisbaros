-- ==========================================================
-- NOM DU SYSTÈME : DENIS FBI STORE
-- VERSION : 6.0 (PRO, SÉCURISÉE)
-- DESCRIPTION : Gestion complète Stock / Ventes / SAV / Rapports / Promotions
-- ==========================================================

-- ------------------------------
-- 1. CRÉATION DE LA BASE
-- ------------------------------
CREATE DATABASE IF NOT EXISTS denis_fbi_store
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
USE denis_fbi_store;

-- ------------------------------
-- 2. MODULE SÉCURITÉ & ACCÈS
-- ------------------------------
CREATE TABLE roles (
    id_role INT PRIMARY KEY AUTO_INCREMENT,
    nom_role VARCHAR(50) NOT NULL -- Super Admin, Admin, Chef Technique, Vendeur, Technicien
);

CREATE TABLE utilisateurs (
    id_user INT PRIMARY KEY AUTO_INCREMENT,
    id_role INT NOT NULL,
    nom_complet VARCHAR(100) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    permissions_specifiques JSON DEFAULT NULL,
    statut ENUM('actif', 'bloque') DEFAULT 'actif',
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_role) REFERENCES roles(id_role) ON DELETE RESTRICT
);

CREATE TABLE logs_systeme (
    id_log INT PRIMARY KEY AUTO_INCREMENT,
    id_user INT NOT NULL,
    action_detaillee TEXT NOT NULL,
    colonne_critique BOOLEAN DEFAULT FALSE,
    date_action TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_user) REFERENCES utilisateurs(id_user)
);

-- ------------------------------
-- 3. MODULE PRODUITS & PACKS
-- ------------------------------
CREATE TABLE IF NOT EXISTS categories (
    id_category INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE produits (
    id_produit INT PRIMARY KEY AUTO_INCREMENT,
    designation VARCHAR(150) NOT NULL,
    categorie VARCHAR(50),
    prix_achat DECIMAL(12,2) NOT NULL,
    prix_boutique_fixe DECIMAL(12,2) NOT NULL,
    stock_actuel INT DEFAULT 0,
    seuil_alerte INT DEFAULT 5,
    duree_garantie_mois INT DEFAULT 12
);

CREATE TABLE packs (
    id_pack INT PRIMARY KEY AUTO_INCREMENT,
    nom_pack VARCHAR(100) NOT NULL,
    prix_pack DECIMAL(12,2) NOT NULL,
    description TEXT
);

CREATE TABLE pack_composants (
    id_pack INT NOT NULL,
    id_produit INT NOT NULL,
    quantite INT DEFAULT 1,
    PRIMARY KEY (id_pack, id_produit),
    FOREIGN KEY (id_pack) REFERENCES packs(id_pack) ON DELETE CASCADE,
    FOREIGN KEY (id_produit) REFERENCES produits(id_produit) ON DELETE CASCADE
);

CREATE TABLE promotions (
    id_promotion INT PRIMARY KEY AUTO_INCREMENT,
    id_produit INT NOT NULL,
    type_remise ENUM('fixe','pourcentage') NOT NULL,
    valeur DECIMAL(10,2) NOT NULL,
    date_debut DATE NOT NULL,
    date_fin DATE NOT NULL,
    active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (id_produit) REFERENCES produits(id_produit)
);

-- ------------------------------
-- 4. MODULE STOCK & TRAÇABILITÉ
-- ------------------------------
CREATE TABLE mouvements_stock (
    id_mouvement INT PRIMARY KEY AUTO_INCREMENT,
    id_produit INT NOT NULL,
    id_user INT NOT NULL,
    type_mouvement ENUM('entree','vente','transfert_sav','ajustement_manuel','retour_fournisseur') NOT NULL,
    quantite_avant INT NOT NULL,
    quantite_apres INT NOT NULL,
    motif_ajustement TEXT NOT NULL,
    date_mouvement TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_produit) REFERENCES produits(id_produit),
    FOREIGN KEY (id_user) REFERENCES utilisateurs(id_user)
);

-- ------------------------------
-- 5. MODULE CLIENTS / VENTES / REVENDEURS
-- ------------------------------
CREATE TABLE clients (
    id_client INT PRIMARY KEY AUTO_INCREMENT,
    nom_client VARCHAR(100) NOT NULL,
    telephone VARCHAR(25) UNIQUE NOT NULL,
    adresse TEXT,
    date_inscription TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE revendeurs (
    id_revendeur INT PRIMARY KEY AUTO_INCREMENT,
    nom_partenaire VARCHAR(100) NOT NULL,
    telephone VARCHAR(25) NOT NULL,
    taux_commission_fixe DECIMAL(5,2) DEFAULT 0.00
);

CREATE TABLE ventes (
    id_vente INT PRIMARY KEY AUTO_INCREMENT,
    id_client INT NOT NULL,
    id_vendeur INT NOT NULL,
    id_revendeur INT DEFAULT NULL,
    prix_revente_final DECIMAL(12,2) NOT NULL,
    commission_partenaire DECIMAL(12,2) DEFAULT 0.00,
    type_paiement ENUM('cash','mobile_money','virement') DEFAULT 'cash',
    date_vente TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_client) REFERENCES clients(id_client),
    FOREIGN KEY (id_vendeur) REFERENCES utilisateurs(id_user),
    FOREIGN KEY (id_revendeur) REFERENCES revendeurs(id_revendeur)
);

CREATE TABLE vente_details (
    id_detail INT PRIMARY KEY AUTO_INCREMENT,
    id_vente INT NOT NULL,
    id_produit INT NOT NULL,
    quantite INT NOT NULL,
    prix_unitaire DECIMAL(12,2) NOT NULL,
    sous_total DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (id_vente) REFERENCES ventes(id_vente),
    FOREIGN KEY (id_produit) REFERENCES produits(id_produit)
);

-- ------------------------------
-- 6. MODULE SAV
-- ------------------------------
CREATE TABLE technicians (
    id_technician INT PRIMARY KEY AUTO_INCREMENT,
    id_user INT UNIQUE,
    fullname VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(100),
    availability ENUM('disponible','occupé','absent') DEFAULT 'disponible',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_user) REFERENCES utilisateurs(id_user)
);

CREATE TABLE sav_dossiers (
    id_sav INT PRIMARY KEY AUTO_INCREMENT,
    id_client INT NOT NULL,
    id_technicien INT DEFAULT NULL,
    appareil_modele VARCHAR(100) NOT NULL,
    num_serie VARCHAR(100),
    etat_physique_entree TEXT NOT NULL,
    panne_declaree TEXT,
    diagnostic_final TEXT,
    statut_sav ENUM('en_attente','en_diagnostic','en_reparation','pret','livre','neuf_hs') DEFAULT 'en_attente',
    est_sous_garantie BOOLEAN DEFAULT FALSE,
    cout_estime DECIMAL(12,2) DEFAULT 0.00,
    date_depot TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_client) REFERENCES clients(id_client),
    FOREIGN KEY (id_technicien) REFERENCES technicians(id_technician)
);

CREATE TABLE technical_stock (
    id_tech_stock INT PRIMARY KEY AUTO_INCREMENT,
    id_sav INT NOT NULL,
    id_produit INT NOT NULL,
    quantite INT NOT NULL,
    date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_sav) REFERENCES sav_dossiers(id_sav),
    FOREIGN KEY (id_produit) REFERENCES produits(id_produit)
);

CREATE TABLE service_logs (
    id_log INT PRIMARY KEY AUTO_INCREMENT,
    id_sav INT NOT NULL,
    id_user INT NOT NULL,
    id_technicien INT,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_sav) REFERENCES sav_dossiers(id_sav),
    FOREIGN KEY (id_user) REFERENCES utilisateurs(id_user),
    FOREIGN KEY (id_technicien) REFERENCES technicians(id_technician)
);

CREATE TABLE sav_photos (
    id_photo INT PRIMARY KEY AUTO_INCREMENT,
    id_sav INT NOT NULL,
    chemin_photo VARCHAR(255),
    date_ajout TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_sav) REFERENCES sav_dossiers(id_sav)
);

-- ------------------------------
-- 7. MODULE RAPPORTS / DISCIPLINE
-- ------------------------------
CREATE TABLE rapports_journaliers (
    id_rapport INT PRIMARY KEY AUTO_INCREMENT,
    id_user INT NOT NULL,
    bilan_activite TEXT NOT NULL,
    problemes_rencontres TEXT,
    statut_approbation ENUM('en_attente','valide','rejete') DEFAULT 'en_attente',
    reponse_super_admin TEXT,
    date_rapport TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_user) REFERENCES utilisateurs(id_user)
);

-- ------------------------------
-- PARAMÈTRES SYSTÈME (optionnel, pour system_settings.php)
-- ------------------------------
CREATE TABLE IF NOT EXISTS system_settings (
    id_setting INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(100) UNIQUE NOT NULL,
    `value` TEXT,
    category VARCHAR(50) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO system_settings (`key`, `value`, category) VALUES
('company_name', 'DENIS FBI STORE', 'general'),
('company_logo', '', 'general'),
('timezone', 'Africa/Douala', 'general'),
('default_language', 'fr', 'general'),
('date_format', 'd/m/Y', 'general'),
('currency', 'FCFA', 'general'),
('stock_critical_threshold', '5', 'stock'),
('repair_max_days', '7', 'business'),
('enable_stock_alerts', '1', 'alerts');

-- ------------------------------
-- 8. INITIALISATION DES RÔLES
-- ------------------------------
INSERT INTO roles (nom_role) VALUES
('Super Admin'),
('Admin'),
('Chef Technique'),
('Vendeur'),
('Technicien');

-- ------------------------------
-- 9. UTILISATEURS DE TEST
-- ------------------------------
-- Mot de passe commun pour les comptes de test : password
INSERT INTO utilisateurs (id_role, nom_complet, username, password_hash) VALUES
(1, 'Super Admin', 'superadmin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
(2, 'Admin Test', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
(4, 'Vendeur Test', 'vendeur', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
(5, 'Technicien Test', 'tech', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

INSERT INTO technicians (id_user, fullname, phone, email) 
SELECT id_user, 'Technicien Test', '600000000', 'tech@fbi.com' FROM utilisateurs WHERE username='tech';
