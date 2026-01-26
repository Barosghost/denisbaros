<?php
session_start();

// Access Control
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

require_once '../config/db.php';
$pageTitle = "Tableau de Bord";

try {
    // 1. Today's Sales
    $stmt = $pdo->query("SELECT SUM(total_amount) FROM sales WHERE DATE(sale_date) = CURDATE()");
    $today_sales = $stmt->fetchColumn() ?: 0;

    // 2. Total Products
    $products_count = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();

    // 3. Low Stock (< 5)
    $low_stock = $pdo->query("SELECT COUNT(*) FROM stock WHERE quantity < 5")->fetchColumn();

    // 4. Total Clients
    $clients_count = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();

    // 5. Recent Transactions (Limit 5)
    $stmt = $pdo->query("SELECT s.*, u.username, c.fullname as client_name 
                         FROM sales s 
                         JOIN users u ON s.id_user = u.id_user 
                         LEFT JOIN clients c ON s.id_client = c.id_client 
                         ORDER BY sale_date DESC LIMIT 5");
    $recent_sales = $stmt->fetchAll();

} catch (PDOException $e) {
    // Handle error gracefully
    $today_sales = 0;
    $products_count = 0;
    $low_stock = 0;
    $clients_count = 0;
    $recent_sales = [];
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | DENIS FBI STORE</title>
    <!-- Bootstrap 5 -->
    <link href="../assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

    <div class="wrapper">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>

        <!-- Page Content -->
        <div id="content">
            <!-- Top Navbar -->
            <?php include '../includes/header.php'; ?>

            <!-- Stats Overview -->
            <div class="row g-4 fade-in">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon bg-success bg-opacity-25 text-success">
                            <i class="fa-solid fa-money-bill-wave"></i>
                        </div>
                        <h5 class="text-muted mb-1">Ventes du Jour</h5>
                        <h3 class="text-white mb-0"><?= number_format($today_sales, 0, ',', ' ') ?> FCFA</h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon bg-info bg-opacity-25 text-info">
                            <i class="fa-solid fa-box"></i>
                        </div>
                        <h5 class="text-muted mb-1">Produits Total</h5>
                        <h3 class="text-white mb-0"><?= $products_count ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon bg-warning bg-opacity-25 text-warning">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>
                        <h5 class="text-muted mb-1">Stock Faible</h5>
                        <h3 class="text-white mb-0"><?= $low_stock ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon bg-primary bg-opacity-25 text-primary">
                            <i class="fa-solid fa-users"></i>
                        </div>
                        <h5 class="text-muted mb-1">Clients Total</h5>
                        <h3 class="text-white mb-0"><?= $clients_count ?></h3>
                    </div>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="row mt-4">
                <div class="col-lg-8">
                    <div class="card bg-dark border-0 glass-panel h-100">
                        <div class="card-body">
                            <h5 class="card-title text-white mb-4">Dernières Transactions</h5>
                            <?php if (empty($recent_sales)): ?>
                                <div class="text-muted text-center py-5">Aucune vente récente.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-dark table-hover align-middle mb-0">
                                        <thead>
                                            <tr class="text-muted small">
                                                <!-- ID Removed -->
                                                <th>Date</th>
                                                <th>Client</th>
                                                <th>Vendeur</th>
                                                <th class="text-end">Montant</th>
                                                <th class="text-end">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_sales as $sale): ?>
                                                <tr>
                                                    <!-- ID Removed -->
                                                    <td><?= date('d/m/Y H:i', strtotime($sale['sale_date'])) ?></td>
                                                    <td><?= htmlspecialchars($sale['client_name'] ?? 'Comptoir') ?></td>
                                                    <td><?= htmlspecialchars($sale['username']) ?></td>
                                                    <td class="text-end fw-bold text-success">
                                                        <?= number_format($sale['total_amount'], 0, ',', ' ') ?></td>
                                                    <td class="text-end">
                                                        <a href="invoice.php?id=<?= $sale['id_sale'] ?>"
                                                            class="btn btn-sm btn-outline-light" target="_blank"
                                                            title="Facture">
                                                            <i class="fa-solid fa-print"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions / Mini Stats -->
                <div class="col-lg-4">
                    <div class="card bg-dark border-0 glass-panel mb-4">
                        <div class="card-body">
                            <h5 class="card-title text-white mb-3">Actions Rapides</h5>
                            <div class="d-grid gap-2">
                                <a href="sales.php" class="btn btn-premium"><i
                                        class="fa-solid fa-cash-register me-2"></i> Nouvelle Vente</a>
                                <?php if ($_SESSION['role'] === 'admin'): ?>
                                    <a href="products.php" class="btn btn-outline-light"><i
                                            class="fa-solid fa-plus me-2"></i> Ajouter Produit</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="card bg-dark border-0 glass-panel">
                        <div class="card-body">
                            <h5 class="card-title text-white mb-3">État du Système</h5>
                            <ul class="list-unstyled text-muted small mb-0">
                                <li class="mb-2"><i class="fa-solid fa-server me-2"></i> Base de données :
                                    <strong>Connectée</strong></li>
                                <li class="mb-2"><i class="fa-solid fa-user-clock me-2"></i> Session :
                                    <strong>Active</strong></li>
                                <li class="mb-0"><i class="fa-solid fa-code-branch me-2"></i> Version : <strong>1.0.0
                                        (Premium)</strong></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="../assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
</body>

</html>
