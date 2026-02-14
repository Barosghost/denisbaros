<?php
define('PAGE_ACCESS', 'dashboard');
require_once '../../backend/includes/auth_required.php';
require_once '../../backend/config/db.php';
require_once '../../backend/config/functions.php';

$pageTitle = "Tableau de Bord";

// Statistics - Count products
$stmt_products = $pdo->query("SELECT COUNT(*) FROM produits");
$total_products = $stmt_products->fetchColumn();

// Statistics - Out of stock
$stmt_stock = $pdo->query("SELECT COUNT(*) FROM produits WHERE stock <= 0");
$out_of_stock = $stmt_stock->fetchColumn();

// Statistics - Today Sales (if table exists)
$today_sales = 0;
try {
    $stmt_sales = $pdo->query("SELECT SUM(total) FROM ventes WHERE DATE(date_vente) = CURDATE()");
    $today_sales = $stmt_sales->fetchColumn() ?: 0;
} catch (PDOException $e) { /* Table might not exist yet */ }

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | DENIS FBI STORE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= time() ?>">
    <style>
        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 25px;
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.2);
        }
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include '../../backend/includes/sidebar.php'; ?>
        <div id="content">
            <?php include '../../backend/includes/header.php'; ?>
            
            <div class="p-4 fade-in">
                <div class="row g-4 mb-5">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                                <i class="fa-solid fa-box"></i>
                            </div>
                            <h3 class="text-white fw-bold mb-1"><?= $total_products ?></h3>
                            <p class="text-muted mb-0">Total Produits</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                            </div>
                            <h3 class="text-white fw-bold mb-1"><?= $out_of_stock ?></h3>
                            <p class="text-muted mb-0">Ruptures de Stock</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon bg-success bg-opacity-10 text-success">
                                <i class="fa-solid fa-cart-shopping"></i>
                            </div>
                            <h3 class="text-white fw-bold mb-1"><?= number_format($today_sales, 0, ',', ' ') ?> F</h3>
                            <p class="text-muted mb-0">Ventes du Jour</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon bg-info bg-opacity-10 text-info">
                                <i class="fa-solid fa-users"></i>
                            </div>
                            <h3 class="text-white fw-bold mb-1">Actif</h3>
                            <p class="text-muted mb-0">Statut Système</p>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="card bg-dark border-0 glass-panel p-4 h-100">
                            <h5 class="text-white fw-bold mb-4">Activités Récentes</h5>
                            <div class="text-center py-5">
                                <i class="fa-solid fa-clock-rotate-left fa-3x text-muted opacity-20 mb-3"></i>
                                <p class="text-muted">Aucune activité récente à afficher.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card bg-dark border-0 glass-panel p-4 h-100">
                            <h5 class="text-white fw-bold mb-4">Accès Rapide</h5>
                            <div class="d-grid gap-3">
                                <a href="sales.php" class="btn btn-outline-primary py-3">
                                    <i class="fa-solid fa-plus me-2"></i>Nouvelle Vente
                                </a>
                                <a href="products.php" class="btn btn-outline-light py-3">
                                    <i class="fa-solid fa-keyboard me-2"></i>Inventaire
                                </a>
                                <a href="daily_reports.php" class="btn btn-outline-info py-3">
                                    <i class="fa-solid fa-file-lines me-2"></i>Faire un Rapport
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
</body>
</html>