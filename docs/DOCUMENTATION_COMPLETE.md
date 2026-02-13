# 📚 DOCUMENTATION COMPLÈTE - DENIS FBI STORE

## 📋 TABLE DES MATIÈRES

1. [Vue d'ensemble du projet](#vue-densemble-du-projet)
2. [Architecture du système](#architecture-du-système)
3. [Rôles et utilisateurs](#rôles-et-utilisateurs)
4. [Base de données](#base-de-données)
5. [Modules et fonctionnalités](#modules-et-fonctionnalités)
6. [Guide d'utilisation](#guide-dutilisation)
7. [Guides techniques](#guides-techniques)

---

## 🎯 VUE D'ENSEMBLE DU PROJET

**DENIS FBI STORE** est un système de gestion intégré pour un magasin de vente et de réparation d'appareils électroniques. Le système gère l'ensemble des opérations commerciales, du service après-vente, de la gestion des stocks et de la fidélisation client.

### Objectifs principaux
- 📦 Gestion complète des stocks et produits
- 💰 Point de vente  professionnel
- 👥 Gestion de la clientèle et fidélité
- 🔧 Service technique et réparations
- 📊 Rapports et statistiques en temps réel
- 🤝 Gestion des revendeurs et commissions
- 👨‍💼 Administration multi-niveaux

---

## 🏗️ ARCHITECTURE DU SYSTÈME

### Structure des dossiers
```
denis/
├── backend/
│   ├── actions/          # Actions CRUD et traitements
│   │   ├── repairs/      # Module réparation
│   │   └── ...
│   ├── auth/             # Authentification
│   ├── config/           # Configuration (DB, fonctions)
│   └── includes/         # Composants réutilisables (sidebar, header)
├── frontend/
│   ├── views/            # Pages de l'application
│   ├── assets/           # CSS, JS, images
│   └── ...
├── database/             # Scripts SQL et migrations
├── docs/                 # Documentation
└── maintenance/          # Scripts de maintenance
```

### Technologies utilisées
- **Backend**: PHP 7.4+, PDO MySQL
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Framework CSS**: Bootstrap 5
- **Base de données**: MySQL/MariaDB
- **Outils**: SweetAlert2, Font Awesome, Chart.js

---

## 👥 RÔLES ET UTILISATEURS

### 1. Super Admin 👑
**Accès**: Complet sans restriction

**Fonctionnalités**:
- Supervision globale de tous les services
- Gestion complète des utilisateurs
- Accès aux logs d'audit
- Paramètres système
- Tous les rapports et statistiques
- Validation des opérations critiques

**Pages accessibles**:
- Dashboard, Supervision
- Gestion Utilisateurs
- Logs d'Audit
- Paramètres Système
- Tous les modules (Produits, Ventes, Clients, Service Technique, etc.)

---

### 2. Admin 🛡️
**Accès**: Gestion opérationnelle complète

**Fonctionnalités**:
- Gestion des produits et stocks
- Gestion des ventes et clients
- Programme fidélité
- Gestion des revendeurs et commissions
- Rapports financiers et d'activité
- Service technique (accès limité)

**Pages accessibles**:
- Dashboard
- Produits, Stock
- Ventes (POS), Clients
- Programme Fidélité
- Revendeurs, Commissions
- Rapports, Rapport Global
- Mouvements Stock

**Restrictions**:
- Pas d'accès à la Supervision
- Pas d'accès aux Logs d'Audit
- Pas d'accès aux Paramètres Système

---

### 3. Vendeur 🛒
**Accès**: Vente et service client

**Fonctionnalités**:
- Utilisation du Point de Vente (POS)
- Consultation des produits et stocks et ajout des produit et entree en stock mais sans modificattion
- Gestion des clients (ajout, consultation)
- Attribution de points de fidélité
- Rapports journaliers

**Pages accessibles**:
- Dashboard (vue limitée)
- Produits (lecture seule)
- Stock peut voir le stock mais ne peux modifier
- Ventes (POS)
- Clients
- Rapports Journaliers
acces au revendeur et au commission

**Restrictions**:
- Pas de modification des produits
- Pas d'accès aux paramètres
- Pas d'accès au service technique

---

### 4. Technicien 🔧
**Accès**: Service technique et réparations

**Fonctionnalités**:
- Réception de machines défectueuses
- Diagnostics techniques
- Réparation et test
- Gestion des demandes de service
- Gestion des appareils
- Création de clients (pour retours)

**Pages accessibles**:
- Dashboard (vue technique)
- Service Réparation
- Réparations (ancien module)
- Appareils
- Rapports Journaliers

**Restrictions**:
- Pas d'accès aux ventes
- Pas d'accès aux stocks (sauf pièces pour réparation)
- Pas d'accès aux clients généraux
- Pas d'accès aux rapports financiers

---

### 5. Chef Technique 👨‍🔧
**Accès**: Supervision du service technique

**Fonctionnalités**:
- Toutes les fonctionnalités du Technicien
- Validation des diagnostics
- Gestion des techniciens
- Statistiques du service technique
- Validation des décisions critiques (irréparable, rebut)

**Pages accessibles**:
- Toutes les pages du Technicien
- Techniciens (gestion)
- Statistiques avancées du service

---

## 🗄️ BASE DE DONNÉES

### Tables principales

#### **users** - Utilisateurs du système
```sql
- id_user (PK)
- username
- password_hash
- fullname
- role (super_admin, admin, vendeur, technicien)
- id_store
- created_at
```

#### **clients** - Clients du magasin
```sql
- id_client (PK)
- fullname
- phone
- email
- address
- loyalty_level (bronze, argent, or, platine)
- loyalty_points
- total_spent
- created_at
```

#### **products** - Produits en vente
```sql
- id_product (PK)
- name
- description
- id_category (FK)
- id_supplier (FK)
- cost_price (prix d'achat)
- selling_price (prix de vente)
- VAT_percentage
- is_serialized (0/1)
- created_at
```

#### **stock** - Gestion des stocks
```sql
- id_stock (PK)
- id_product (FK)
- quantity
- location
- min_quantity (seuil d'alerte)
- last_updated
```

#### **serialized_stock** - Articles avec numéro de série
```sql
- id_serialized (PK)
- id_product (FK)
- serial_number (UNIQUE)
- status (available, sold, reserved, in_repair)
- id_sale (FK, nullable)
```

#### **sales** - Ventes
```sql
- id_sale (PK)
- id_client (FK, nullable)
- id_user (FK - vendeur)
- id_reseller (FK, nullable)
- total_amount
- payment_method (cash, mobile_money, card, credit)
- loyalty_points_earned
- created_at
```

#### **sale_details** - Détails des ventes
```sql
- id (PK)
- id_sale (FK)
- id_product (FK)
- quantity
- unit_price
- subtotal
```

#### **repairs** - Réparations (NOUVEAU MODULE)
```sql
- id (PK)
- serial_number
- model
- id_supplier (FK, nullable)
- id_client (FK, nullable)
- entry_date
- entry_service (reception, ventes, retour_client)
- entry_state (neuve_defectueuse, retour_client, panne_interne)
- failure_reason
- status (10 états différents)
- id_technician (FK)
- id_creator (FK)
```

#### **repair_diagnostics** - Diagnostics techniques
```sql
- id (PK)
- id_repair (FK)
- failure_type (hardware, software)
- affected_component
- gravity (mineure, majeure, critique)
- notes
- decision (reparable, reparable_pieces, irreparable)
- id_technician (FK)
```

#### **repair_parts** - Pièces utilisées
```sql
- id (PK)
- id_repair (FK)
- id_product (FK)
- quantity
- unit_cost
```

#### **repair_costs** - Analyse des coûts
```sql
- id_repair (PK, FK)
- parts_cost
- labor_cost
- total_cost
- machine_value
```

#### **resellers** - Revendeurs
```sql
- id_reseller (PK)
- name
- contact
- phone
- commission_percentage
- total_sales
- total_commission
```

#### **loyalty_transactions** - Transactions de fidélité
```sql
- id (PK)
- id_client (FK)
- points_change (+ ou -)
- transaction_type
- description
- created_at
```

#### **action_logs** - Logs d'activité
```sql
- id (PK)
- id_user (FK)
- action_type
- description
- created_at
```

#### **daily_reports** - Rapports journaliers
```sql
- id (PK)
- id_user (FK)
- report_date
- tasks_completed
- observations
- submitted_at
```

---

## 🎯 MODULES ET FONCTIONNALITÉS

### 1. 📊 Dashboard
**Accessible à**: Tous les rôles (vues adaptées)

**Fonctionnalités**:
- Vue d'ensemble des statistiques
- Graphiques d'évolution des ventes
- Alertes stock faible
- Activités récentes
- Ventes du jour/mois
- Nombre de clients actifs

---

### 2. 📦 Gestion des Produits
**Accessible à**: Super Admin, Admin (Vendeur en lecture seule)

**Fonctionnalités**:
- ✅ Ajout, modification, suppression de produits
- ✅ Classification par catégories
- ✅ Gestion des prix (achat, vente, TVA)
- ✅ Importation/Exportation Excel
- ✅ Photos de produits
- ✅ Produits sérialisés ou non sérialisés
- ✅ Association aux fournisseurs

**Actions possibles**:
- Créer un produit standard
- Créer un produit sérialisé (avec numéros de série)
- Modifier les prix
- Changer de catégorie/fournisseur
- Supprimer (si aucune vente liée)

---

### 3. 📦 Gestion du Stock
**Accessible à**: Super Admin, Admin (Vendeur en lecture seule)

**Fonctionnalités**:
- ✅ Vue en temps réel des quantités
- ✅ Alertes de stock minimum
- ✅ Ajustements manuels (entrées/sorties)
- ✅ Gestion des numéros de série
- ✅ Historique des mouvements
- ✅ Localisation des stocks
- ✅ Inventaire physique

**Mouvements de stock**:
- **Entrée**: Réception fournisseur, Retour client, Ajustement
- **Sortie**: Vente, Perte, Vol, Réparation, Ajustement
- **Transfert**: Entre emplacements ou vers le Service Technique (Automatisé)

---

### 3.5 🔄 Transfert Automatique Stock <-> Technique
Le système intègre désormais un flux automatisé pour les machines internes :
1. **Envoi en Réparation** : Depuis le "Détail Inventaire", l'action "Réparation" déduit automatiquement l'unité du stock global et crée un ticket technique.
2. **Retour de Réparation** : Dès qu'un ticket interne est marqué comme "Terminé", la machine est réintégrée au stock vendable avec l'état "Réparé".

---

### 4. 💰 Point de Vente (POS)
**Accessible à**: Super Admin, Admin, Vendeur

**Fonctionnalités**:
- ✅ Interface tactile optimisée
- ✅ Recherche rapide de produits
- ✅ Scan de codes-barres (si disponible)
- ✅ Gestion du panier
- ✅ Application automatique de la TVA
- ✅ Multiples méthodes de paiement:
  - Espèces
  - Mobile Money (Orange, MTN, etc.)
  - Carte bancaire
  - Crédit client
- ✅ Attribution automatique de points de fidélité
- ✅ Gestion des remises
- ✅ Impression de factures
- ✅ Association à un revendeur (commission automatique)

**Processus de vente**:
1. Sélection des produits
2. Ajout au panier
3. Sélection du client (optionnel mais recommandé pour fidélité)
4. Choix du mode de paiement
5. Validation
6. Attribution points de fidélité
7. Impression facture
8. Mise à jour stock automatique

---

### 5. 👥 Gestion des Clients
**Accessible à**: Super Admin, Admin, Vendeur

**Fonctionnalités**:
- ✅ Fiche client complète
- ✅ Historique d'achats
- ✅ Solde de points de fidélité
- ✅ Niveau de fidélité (Bronze, Argent, Or, Platine)
- ✅ Statistiques par client
- ✅ Recherche et filtrage
- ✅ Import/Export Excel

**Informations client**:
- Nom complet, téléphone, email, adresse
- Total dépensé
- Points de fidélité
- Niveau de fidélité
- Historique des transactions
- Dernière visite

---

### 6. ⭐ Programme de Fidélité
**Accessible à**: Super Admin, Admin

**Fonctionnalités**:
- ✅ Configuration des niveaux (Bronze, Argent, Or, Platine)
- ✅ Taux de conversion points/FCFA
- ✅ Seuils de passage de niveaux
- ✅ Avantages par niveau
- ✅ Attribution automatique de points à chaque achat
- ✅ Historique des transactions de points
- ✅ Statistiques du programme

**Niveaux de fidélité** (configurables):
- **Bronze**: 0 - 50,000 FCFA dépensés
- **Argent**: 50,001 - 200,000 FCFA
- **Or**: 200,001 - 500,000 FCFA
- **Platine**: > 500,000 FCFA

---

### 7. 🤝 Gestion des Revendeurs
**Accessible à**: Super Admin, Admin

**Fonctionnalités**:
- ✅ Enregistrement des revendeurs
- ✅ Taux de commission personnalisés
- ✅ Suivi des ventes par revendeur
- ✅ Calcul automatique des commissions
- ✅ Historique des commissions versées
- ✅ Statistiques de performance

**Processus**:
1. Créer un revendeur avec son taux de commission
2. Lors d'une vente, sélectionner le revendeur
3. Commission calculée automatiquement
4. Suivi et paiement des commissions

---

### 8. 🔧 Service Réparation (NOUVEAU)
**Accessible à**: Super Admin, Admin, Chef Technique, Technicien

**Fonctionnalités complètes**:

#### A. Réception de Machines
- ✅ Enregistrement du numéro de série
- ✅ Identification du modèle
- ✅ Source d'arrivée (réception, ventes, retour client)
- ✅ État à l'arrivée (neuve défectueuse, retour, panne interne)
- ✅ Association au fournisseur ou client
- ✅ Photo de l'appareil (optionnel)
- ✅ Description de la panne
- ✅ Valeur estimée de la machine
- ✅ **Création de client à la volée** (bouton +)

#### B. Diagnostic Technique
- ✅ Affectation au technicien
- ✅ Type de panne (hardware/software)
- ✅ Composant affecté (carte mère, écran, batterie, etc.)
- ✅ Niveau de gravité (mineure, majeure, critique)
- ✅ Notes techniques détaillées
- ✅ Décision:
  - Réparable immédiatement
  - Réparable avec pièces (attente)
  - Irréparable

#### C. Réparation
- ✅ Enregistrement des actions effectuées
- ✅ Ajout des pièces utilisées (pioche dans le stock)
- ✅ **Déduction automatique du stock**
- ✅ Calcul automatique du coût (pièces + main d'œuvre)
- ✅ Temps passé
- ✅ Photos avant/après

#### D. Test et Validation
- ✅ Tests fonctionnels
- ✅ Résultat (réussi/échec)
- ✅ Notes de test
- ✅ Validation finale

#### E. Sortie du Service
- ✅ **Retour vers ventes** (machine reconditionnée, prête à revendre)
- ✅ **Rebut/Perte**:
  - Perte fournisseur (retour SAV fournisseur)
  - Perte interne (irréparable, coût > valeur)
  - Récupération pièces
- ✅ Génération de rapports
- ✅ Mise à jour comptable

#### F. Statistiques du Service
- ✅ Nombre total de réparations
- ✅ Taux de réussite
- ✅ Taux de perte
- ✅ Coût total des réparations
- ✅ Temps moyen de réparation
- ✅ Pannes les plus fréquentes
- ✅ Performance des techniciens
- ✅ Analyse coût/bénéfice

**Statuts de réparation**:
1. `attente_diagnostic` - Machine reçue, en attente
2. `en_diagnostic` - Diagnostic en cours
3. `attente_pieces` - Pièces commandées
4. `en_reparation` - Réparation en cours
5. `attente_test` - Réparation terminée, en attente de test
6. `reparee_prete` - Réparée et testée avec succès
7. `echec_reparation` - Test échoué
8. `irreparable` - Déclarée irréparable
9. `retournee_ventes` - Retournée au stock de vente
10. `rebut_perte` - Mise au rebut ou déclarée perte

---

### 9. 📊 Rapports et Statistiques

#### A. Rapports Journaliers
**Accessible à**: Tous les utilisateurs

**Fonctionnalités**:
- ✅ Soumission quotidienne des activités
- ✅ Tâches accomplies
- ✅ Observations
- ✅ Consultation par le Super Admin

#### B. Rapport Global
**Accessible à**: Super Admin, Admin

**Fonctionnalités**:
- ✅ Ventes totales par période
- ✅ Bénéfices nets
- ✅ Produits les plus vendus
- ✅ Clients les plus actifs
- ✅ Performance des vendeurs
- ✅ Évolution des stocks
- ✅ Graphiques interactifs

#### C. Rapports Financiers
**Accessible à**: Super Admin, Admin

**Fonctionnalités**:
- ✅ Chiffre d'affaires par période
- ✅ Marge bénéficiaire
- ✅ Coûts opérationnels
- ✅ Commissions versées
- ✅ Points de fidélité distribués
- ✅ Export PDF/Excel

---

### 10. 👨‍💼 Administration

#### A. Gestion des Utilisateurs
**Accessible à**: Super Admin, Admin (limité)

**Fonctionnalités**:
- ✅ Création d'utilisateurs
- ✅ Attribution des rôles
- ✅ Modification des permissions
- ✅ Désactivation/Activation
- ✅ Réinitialisation mot de passe
- ✅ Historique de connexions

#### B. Supervision (Super Admin uniquement)
- ✅ Vue d'ensemble de tous les services
- ✅ Alertes système
- ✅ Performance globale
- ✅ Accès rapide à tous les modules

#### C. Logs d'Audit (Super Admin uniquement)
- ✅ Traçabilité complète des actions
- ✅ Qui a fait quoi et quand
- ✅ Détection d'anomalies
- ✅ Recherche et filtrage avancés

#### D. Paramètres Système (Super Admin uniquement)
- ✅ Configuration générale
- ✅ Paramètres de sécurité
- ✅ Sauvegarde de base de données
- ✅ Maintenance système

---

## 📖 GUIDE D'UTILISATION

### Démarrage rapide

#### 1. Installation
```bash
1. Placer le dossier dans c:\wamp64\www\denis
2. Démarrer WAMP
3. Créer la base de données "denis_fbi_store_baros"
4. Importer les fichiers SQL du dossier database/
5. Accéder à http://localhost/denis
```

#### 2. Première connexion

**Super Admin par défaut**:
- Username: `super_admin`
- Password: `admin123` (à changer immédiatement)

#### 3. Configuration initiale
1. Aller dans Paramètres Système
2. Configurer les informations du magasin
3. Créer les catégories de produits
4. Ajouter les fournisseurs
5. Créer les utilisateurs (vendeurs, techniciens)
6. Configurer le programme de fidélité

---

### Scénarios d'utilisation

#### Scénario 1: Vente standard
1. Connexion en tant que Vendeur
2. Accéder au POS (Ventes)
3. Rechercher et ajouter les produits au panier
4. Sélectionner ou créer un client
5. Choisir le mode de paiement
6. Valider la vente
7. Imprimer la facture

#### Scénario 2: Réception d'une machine défectueuse
1. Connexion en tant que Technicien
2. Aller dans Service Réparation > Nouvelle Réception
3. Remplir le formulaire:
   - Numéro de série
   - Modèle
   - Source (retour client, neuve défectueuse, etc.)
   - Créer le client si nécessaire (bouton +)
   - Décrire la panne
4. Enregistrer
4. Enregistrer
5. La machine apparaît dans la liste avec statut "attente_diagnostic"

#### Scénario 3bis: Transfert interne (Machine du Stock)
1. Aller dans **Stock** > Sélectionner un produit > **Détail (S/N)**
2. Pour une machine en stock, cliquer sur **Réparation**
3. Saisir le motif. Le système s'occupe de tout :
   - Déduction du stock vendable
   - Création du ticket au Service Technique
   - Log dans les rapports de mouvement

#### Scénario 3: Diagnostic et réparation
1. Ouvrir la fiche de réparation
2. Cliquer sur "Effectuer le Diagnostic"
3. Remplir:
   - Type de panne
   - Composant affecté
   - Gravité
   - Décision (réparable, irréparable, etc.)
4. Le statut change automatiquement
5. Si réparable, cliquer sur "Enregistrer Réparation"
6. Ajouter les pièces utilisées (stock déduit automatiquement)
7. Enregistrer les actions effectuées
8. Effectuer le test
9. Valider la sortie (retour ventes ou rebut)

#### Scénario 4: Gestion de stock
1. Connexion Admin
2. Aller dans Stock
3. Voir les alertes de stock faible
4. Cliquer sur "Ajustement Manuel"
5. Choisir le type (Entrée/Sortie)
6. Sélectionner le produit
7. Saisir la quantité et le motif
8. Valider (historique enregistré automatiquement)

---

## 🔧 GUIDES TECHNIQUES

### Workflow des ventes
```
1. Sélection produits → Panier
2. Validation panier → Création vente
3. Enregistrement sale_details
4. Déduction stock automatique
5. Calcul points fidélité
6. Attribution points au client
7. Création loyalty_transaction
8. Si revendeur: calcul commission
9. Log action dans action_logs
10. Génération facture
```

### Workflow des réparations
```
1. Réception → repairs (status: attente_diagnostic)
2. Diagnostic → repair_diagnostics (status: en_reparation)
3. Ajout pièces → repair_parts + déduction stock
4. Calcul coûts → repair_costs (parts + labor)
5. Test → repair_tests (status: reparee_prete ou echec)
6. Sortie → repair_exit_details (status: retournee_ventes ou rebut_perte)
7. À chaque étape → repair_history (traçabilité complète)
```

### Sécurité
- ✅ Mots de passe hashés (password_hash)
- ✅ Protection CSRF
- ✅ Validation des entrées (prepared statements)
- ✅ Sessions sécurisées
- ✅ Contrôle d'accès basé sur les rôles
- ✅ Logs d'audit complets

### Performance
- ✅ Indexation des tables (serial_number, status, etc.)
- ✅ Requêtes optimisées avec JOINs
- ✅ Mise en cache des données statiques
- ✅ Pagination des listes longues

---

## 📝 NOTES IMPORTANTES

### Bonnes pratiques
1. **Toujours sélectionner un client** lors d'une vente pour la fidélisation
2. **Vérifier le stock** avant de promettre un produit
3. **Documenter les diagnostics** en détail pour la traçabilité
4. **Valider les sorties de réparation** uniquement après test complet
5. **Faire des sauvegardes régulières** de la base de données
6. **Consulter les rapports quotidiens** pour le suivi d'activité

### Limitations actuelles
- Un seul magasin (prévu multi-magasins dans v2)
- Pas de connexion API fournisseurs (prévu dans v2)
- Pas de notification email/SMS automatique (prévu dans v2)

### Support et maintenance
- Les logs d'erreurs sont dans `backend/logs/`
- Les sauvegardes de BD dans `maintenance/backups/`
- Documentation technique dans `docs/`

---

## 🎓 FORMATION RECOMMANDÉE

### Pour les Vendeurs
- Module POS (2 heures)
- Gestion clients et fidélité (1 heure)
- Procédures de caisse (1 heure)

### Pour les Techniciens
- Module Service Réparation complet (4 heures)
- Gestion des pièces et stock (1 heure)
- Diagnostic et tests (2 heures)

### Pour les Administrateurs
- Vue d'ensemble système (3 heures)
- Gestion produits et stocks (2 heures)
- Rapports et analyses (2 heures)
- Configuration et paramètres (1 heure)

---

## 📞 CONTACT & SUPPORT

Pour toute question ou problème:
- Consulter la documentation dans `/docs`
- Vérifier les logs dans `/backend/logs`
- Contacter l'administrateur système

---

**Version de la documentation**: 1.0  
**Dernière mise à jour**: 11 février 2026  
**Système**: DENIS FBI STORE v2.0
