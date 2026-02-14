<?php
define('PAGE_ACCESS', 'help');
require_once '../../backend/includes/auth_required.php';
$pageTitle = "Manuel d'utilisation";
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manuel d'utilisation | DENIS FBI STORE</title>
    <link href="../assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=1.5">
    <script src="../assets/vendor/sweetalert2/sweetalert2.all.min.js"></script>
    <style>
        .help-section {
            background: rgba(30, 41, 59, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
        }

        .help-icon-lg {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 15px;
        }

        .nav-help {
            position: sticky;
            top: 20px;
        }

        .nav-help .list-group-item {
            background: transparent;
            border: none;
            color: #94a3b8;
            padding: 10px 15px;
            border-radius: 10px;
            transition: all 0.3s;
        }

        .nav-help .list-group-item:hover,
        .nav-help .list-group-item.active {
            background: rgba(99, 102, 241, 0.1);
            color: white;
            padding-left: 20px;
        }

        h2 {
            font-weight: 700;
            color: white;
            margin-bottom: 25px;
        }

        h4 {
            color: #f8fafc;
            margin-top: 20px;
        }

        p,
        li {
            color: #cbd5e1;
            line-height: 1.7;
        }

        .feature-badge {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include '../../backend/includes/sidebar.php'; ?>
        <div id="content">
            <?php include '../../backend/includes/header.php'; ?>

            <div class="container-fluid py-4">
                <div class="row">
                    <!-- Navigation Latérale Aide -->
                    <div class="col-lg-3 d-none d-lg-block">
                        <div class="nav-help">
                            <h5 class="text-white fw-bold mb-3 px-3">Sommaire</h5>
                            <div class="list-group">
                                <a href="#intro" class="list-group-item active">Introduction</a>
                                <a href="#dashboard" class="list-group-item">Tableau de Bord</a>
                                <a href="#pos" class="list-group-item">Ventes (POS)</a>
                                <a href="#products" class="list-group-item">Produits & Stock</a>
                                <a href="#service" class="list-group-item">Service Technique</a>
                                <a href="#clients" class="list-group-item">Clients & Fidélité</a>
                                <a href="#reports" class="list-group-item">Rapports & Logs</a>
                                <a href="#users" class="list-group-item">Utilisateurs</a>
                            </div>
                        </div>
                    </div>

                    <!-- Contenu de l'aide -->
                    <div class="col-lg-9">
                        <div id="intro" class="help-section fade-in">
                            <div class="help-icon-lg">
                                <i class="fa-solid fa-rocket"></i>
                            </div>
                            <h2>Bienvenue sur DENIS FBI STORE</h2>
                            <p>Ce manuel d'utilisation a été conçu pour vous aider à maîtriser toutes les
                                fonctionnalités de votre application de gestion commerciale. Que vous soyez
                                administrateur ou vendeur, vous trouverez ici toutes les explications nécessaires pour
                                optimiser votre travail quotidien.</p>
                        </div>

                        <div id="dashboard" class="help-section">
                            <div class="help-icon-lg">
                                <i class="fa-solid fa-chart-pie"></i>
                            </div>
                            <h2>Tableau de Bord</h2>
                            <p>C'est votre centre de contrôle. Il affiche une vue globale de l'activité commerciale en
                                temps réel.</p>
                            <ul>
                                <li><strong>Statistiques Clés</strong> : Ventes du jour, nombre de produits, alertes de
                                    stock faible et base clients.</li>
                                <li><strong>Graphiques d'Évolution</strong> : Visualisez le chiffre d'affaires des 7
                                    derniers jours et le top des produits vendus.</li>
                                <li><strong>Transactions Récentes</strong> : Un aperçu rapide des dernières
                                    modifications et ventes effectuées.</li>
                            </ul>
                        </div>

                        <div id="pos" class="help-section">
                            <div class="help-icon-lg">
                                <i class="fa-solid fa-cash-register"></i>
                            </div>
                            <h2>Point de Vente (POS)</h2>
                            <p>L'interface de vente rapide est conçue pour être fluide et intuitive.</p>
                            <h4>Processus de Vente :</h4>
                            <ol>
                                <li><strong>Recherche</strong> : Utilisez la barre de recherche ou les catégories pour
                                    trouver un produit.</li>
                                <li><strong>Sélection</strong> : Cliquez sur un article pour l'ajouter au panier.</li>
                                <li><strong>Panier</strong> : Modifiez les quantités ou retirez des articles directement
                                    depuis le panneau latéral droit.</li>
                                <li><strong>Client</strong> : Sélectionnez un client enregistré pour lui attribuer des
                                    points de fidélité.</li>
                                <li><strong>Validation</strong> : Cliquez sur "Finaliser la commande" pour enregistrer
                                    la vente et imprimer la facture.</li>
                            </ol>
                        </div>

                        <div id="products" class="help-section">
                            <div class="help-icon-lg">
                                <i class="fa-solid fa-boxes-stacked"></i>
                            </div>
                            <h2>Gestion des Produits & Stock</h2>
                            <p>Gérez votre catalogue avec précision.</p>
                            <ul>
                                <li><strong>Produits</strong> : Ajoutez, modifiez ou désactivez des articles. Vous
                                    pouvez également uploader des images pour chaque produit.</li>
                                <li><strong>Stock</strong> : Ajustez manuellement les quantités lors de nouvelles
                                    réceptions ou de corrections d'inventaire.</li>
                                <li><strong>Transfert Automatique</strong> : Les machines envoyées en réparation depuis
                                    l'inventaire déduisent automatiquement le stock global.</li>
                                <li><strong>Catégories</strong> : Organisez vos produits pour faciliter la recherche au
                                    POS.</li>
                                <li><strong>Alertes</strong> : Les produits dont le stock est inférieur à 5 apparaissent
                                    en orange pour vous prévenir.</li>
                            </ul>
                        </div>

                        <div id="service" class="help-section">
                            <div class="help-icon-lg">
                                <i class="fa-solid fa-screwdriver-wrench"></i>
                            </div>
                            <h2>Service Technique & Réparations</h2>
                            <p>Gérez le cycle de vie des appareils défectueux.</p>
                            <ul>
                                <li><strong>Réception</strong> : Enregistrez les machines (S/N, modèle, panne) dès leur
                                    arrivée.</li>
                                <li><strong>Diagnostic</strong> : Les techniciens évaluent la panne et décident de la
                                    réparabilité.</li>
                                <li><strong>Réparation</strong> : Suivi des travaux et déduction automatique des pièces
                                    utilisées.</li>
                                <li><strong>Sortie Automatisée</strong> : Les machines réparées retournent
                                    automatiquement en stock vendable avec l'état "Réparé".</li>
                            </ul>
                        </div>

                        <div id="clients" class="help-section">
                            <div class="help-icon-lg">
                                <i class="fa-solid fa-star"></i>
                            </div>
                            <h2>Clients & Fidélité</h2>
                            <p>Fidélisez vos clients pour augmenter vos revenus.</p>
                            <ul>
                                <li><strong>Fichier Client</strong> : Enregistrez les coordonnées de vos clients.</li>
                                <li><strong>Points de Fidélité</strong> : Chaque achat permet au client de cumuler des
                                    points automatiquement.</li>
                                <li><strong>Récompenses</strong> : Les clients peuvent échanger leurs points contre des
                                    cadeaux ou des réductions pré-configurées par l'administrateur.</li>
                                <li><strong>Niveaux</strong> : Les clients évoluent entre Bronze, Argent, Or et Platine
                                    selon leurs dépenses.</li>
                            </ul>
                        </div>

                        <div id="reports" class="help-section">
                            <div class="help-icon-lg">
                                <i class="fa-solid fa-file-invoice-dollar"></i>
                            </div>
                            <h2>Rapports & Audit</h2>
                            <p><span class="badge bg-danger feature-badge">Admin Uniquement</span></p>
                            <ul>
                                <li><strong>Analyse des Ventes</strong> : Filtrez les ventes par période pour analyser
                                    vos performances (Chiffre d'affaires, Marge, Top ventes).</li>
                                <li><strong>Logs d'Audit</strong> : <span class="badge bg-info">Super Admin</span>
                                    Suivez précisément qui a fait quoi avec l'IP et l'appareil utilisé.</li>
                                <li><strong>Rapports Journaliers</strong> : Chaque employé soumet ses tâches accomplies
                                    pour un suivi quotidien.</li>
                                <li><strong>Export</strong> : Consultez et réimprimez n'importe quelle facture passée.
                                </li>
                            </ul>
                        </div>

                        <div id="users" class="help-section">
                            <div class="help-icon-lg">
                                <i class="fa-solid fa-user-shield"></i>
                            </div>
                            <h2>Gestion des Utilisateurs</h2>
                            <p><span class="badge bg-danger feature-badge">Admin Uniquement</span></p>
                            <p>Gérez les accès à l'application.</p>
                            <ul>
                                <li><strong>Super Admin</strong> : Contrôle total, supervision système, gestion des
                                    utilisateurs et logs d'audit.</li>
                                <li><strong>Admin</strong> : Gestion des produits, stocks, ventes et rapports standards.
                                </li>
                                <li><strong>Vendeur</strong> : Accès au POS, au catalogue et aux clients.</li>
                                <li><strong>Technicien</strong> : Accès complet au module de service technique et de
                                    réparation.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Smooth scroll pour les ancres
            document.querySelectorAll('.nav-help a').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    document.querySelectorAll('.nav-help a').forEach(a => a.classList.remove('active'));
                    this.classList.add('active');

                    const targetId = this.getAttribute('href').substring(1);
                    const targetElement = document.getElementById(targetId);
                    if (targetElement) {
                        window.scrollTo({
                            top: targetElement.offsetTop - 100,
                            behavior: 'smooth'
                        });
                    }
                });
            });

            // Highlight active section on scroll
            window.addEventListener('scroll', () => {
                let current = '';
                document.querySelectorAll('.help-section').forEach(section => {
                    const sectionTop = section.offsetTop;
                    if (pageYOffset >= sectionTop - 150) {
                        current = section.getAttribute('id');
                    }
                });

                document.querySelectorAll('.nav-help a').forEach(a => {
                    a.classList.remove('active');
                    if (a.getAttribute('href').substring(1) === current) {
                        a.classList.add('active');
                    }
                });
            });
        });
    </script>
</body>

</html>