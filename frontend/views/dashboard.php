<?php
session_start();

// Access Control
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

require_once '../../backend/config/db.php';
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

    // 6. Chart Data: Last 7 Days Revenue
    $revenue_dates = [];
    $revenue_totals = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $revenue_dates[] = date('d/m', strtotime($date));
        $stmt = $pdo->prepare("SELECT SUM(total_amount) FROM sales WHERE DATE(sale_date) = ?");
        $stmt->execute([$date]);
        $revenue_totals[] = $stmt->fetchColumn() ?: 0;
    }

    // 7. Chart Data: Top 5 Products (Quantity)
    $stmt = $pdo->query("SELECT p.name, SUM(s.quantity * s.unit_price) as total_sold 
                         FROM sale_details s 
                         JOIN products p ON s.id_product = p.id_product 
                         GROUP BY s.id_product 
                         ORDER BY total_sold DESC LIMIT 5");
    $top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $top_product_names = array_column($top_products, 'name');
    $top_product_values = array_column($top_products, 'total_sold');

} catch (PDOException $e) {
    // Handle error gracefully
    $today_sales = 0;
    $products_count = 0;
    $low_stock = 0;
    $clients_count = 0;
    $recent_sales = [];
    $revenue_dates = [];
    $revenue_totals = [];
    $top_product_names = [];
    $top_product_values = [];
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
    <link rel="stylesheet" href="../assets/css/style.css?v=1.4">
    <!-- PWA Manifest -->
    <link rel="manifest" href="../manifest.json">
    <style>
        .dashboard-stat-card {
            background: rgba(30, 41, 59, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 24px;
            /* More rounded */
            padding: 30px;
            /* Larger padding */
            display: flex;
            align-items: center;
            gap: 25px;
            transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
            backdrop-filter: blur(10px);
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        /* Unique Gradients for each card type */
        .card-premium-1 {
            background: linear-gradient(145deg, rgba(16, 185, 129, 0.1) 0%, rgba(6, 78, 59, 0.2) 100%);
            border-top: 1px solid rgba(16, 185, 129, 0.3);
        }

        .card-premium-2 {
            background: linear-gradient(145deg, rgba(59, 130, 246, 0.1) 0%, rgba(30, 58, 138, 0.2) 100%);
            border-top: 1px solid rgba(59, 130, 246, 0.3);
        }

        .card-premium-3 {
            background: linear-gradient(145deg, rgba(245, 158, 11, 0.1) 0%, rgba(120, 53, 15, 0.2) 100%);
            border-top: 1px solid rgba(245, 158, 11, 0.3);
        }

        .card-premium-4 {
            background: linear-gradient(145deg, rgba(139, 92, 246, 0.1) 0%, rgba(76, 29, 149, 0.2) 100%);
            border-top: 1px solid rgba(139, 92, 246, 0.3);
        }

        .dashboard-stat-card:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .stat-icon-large {
            width: 70px;
            height: 70px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.05);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            /* Larger icon */
            box-shadow: inset 0 0 20px rgba(0, 0, 0, 0.2);
        }

        /* Decorative Background Circle */
        .decorative-circle {
            position: absolute;
            width: 150px;
            height: 150px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0) 70%);
            border-radius: 50%;
            top: -50px;
            right: -50px;
            filter: blur(20px);
        }

        .recent-card {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .action-btn-premium {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #94a3b8;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .action-btn-premium:hover {
            background: rgba(99, 102, 241, 0.1);
            border-color: rgba(99, 102, 241, 0.4);
            color: white;
            transform: translateX(5px);
        }

        .action-btn-premium i {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.05);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            transition: all 0.3s;
        }

        .action-btn-premium:hover i {
            background: var(--primary-color);
            color: white;
        }
    </style>

<body>

    <div class="wrapper">
        <!-- Sidebar -->
        <?php include '../../backend/includes/sidebar.php'; ?>

        <!-- Page Content -->
        <div id="content">
            <!-- Top Navbar -->
            <?php include '../../backend/includes/header.php'; ?>

            <!-- Stats Overview -->
            <div class="row g-4 mt-2 fade-in">
                <!-- Ventes du Jour -->
                <div class="col-xl-6 col-md-6">
                    <div class="dashboard-stat-card card-premium-1">
                        <div class="stat-icon-large text-success">
                            <i class="fa-solid fa-coins"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="text-white-50 small text-uppercase fw-bold mb-2">Ventes du Jour</div>
                            <div class="text-white h2 fw-bold mb-1"><?= number_format($today_sales, 0, ',', ' ') ?>
                                <small class="text-muted fs-6 fw-normal">FCFA</small>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-success bg-opacity-20 text-success rounded-pill px-2">
                                    <i class="fa-solid fa-arrow-trend-up me-1"></i>+12%
                                </span>
                                <span class="extra-small text-muted">vs hier</span>
                            </div>
                        </div>
                        <div class="decorative-circle opacity-10"></div>
                    </div>
                </div>

                <!-- Produits -->
                <div class="col-xl-6 col-md-6">
                    <div class="dashboard-stat-card card-premium-2">
                        <div class="stat-icon-large text-primary">
                            <i class="fa-solid fa-layer-group"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="text-white-50 small text-uppercase fw-bold mb-2">Catalogue Produits</div>
                            <div class="text-white h2 fw-bold mb-1"><?= $products_count ?></div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-primary bg-opacity-20 text-primary rounded-pill px-2">
                                    Actifs
                                </span>
                                <span class="extra-small text-muted">Total référencé</span>
                            </div>
                        </div>
                        <div class="decorative-circle opacity-10"></div>
                    </div>
                </div>

                <!-- Stock Faible -->
                <div class="col-xl-6 col-md-6">
                    <div class="dashboard-stat-card card-premium-3">
                        <div class="stat-icon-large text-warning">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="text-white-50 small text-uppercase fw-bold mb-2">Alerte Stock</div>
                            <div class="text-white h2 fw-bold mb-1"><?= $low_stock ?> <small
                                    class="text-muted fs-6 fw-normal">articles</small></div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-warning bg-opacity-20 text-warning rounded-pill px-2">
                                    Attention
                                </span>
                                <span class="extra-small text-muted">Quantité &lt; 5</span>
                            </div>
                        </div>
                        <div class="decorative-circle opacity-10"></div>
                    </div>
                </div>

                <!-- Clients -->
                <div class="col-xl-6 col-md-6">
                    <div class="dashboard-stat-card card-premium-4">
                        <div class="stat-icon-large text-info">
                            <i class="fa-solid fa-users"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="text-white-50 small text-uppercase fw-bold mb-2">Base Clients</div>
                            <div class="text-white h2 fw-bold mb-1"><?= $clients_count ?></div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-info bg-opacity-20 text-info rounded-pill px-2">
                                    <i class="fa-solid fa-file-export me-1"></i>Exporter
                                </span>
                                <span class="extra-small text-muted">Fichier client</span>
                            </div>
                        </div>
                        <div class="decorative-circle opacity-10"></div>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="row g-4 mt-2">
                <div class="col-lg-8">
                    <div class="recent-card h-100 p-4">
                        <h5 class="text-white fw-bold mb-4">Évolution des Ventes (7 jours)</h5>
                        <canvas id="salesChart" style="max-height: 300px;"></canvas>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="recent-card h-100 p-4">
                        <h5 class="text-white fw-bold mb-4">Top Produits (Revenus)</h5>
                        <div style="position: relative; height: 250px; display: flex; justify-content: center;">
                            <canvas id="productsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Transactions & Quick Actions -->
            <div class="row g-4 mt-2">
                <div class="col-lg-8">
                    <div class="recent-card h-100">
                        <div class="p-4 border-bottom border-white border-opacity-5">
                            <div class="d-flex align-items-center justify-content-between">
                                <h5 class="text-white fw-bold mb-0">Dernières Transactions</h5>
                                <a href="reports.php" class="extra-small text-primary text-decoration-none fw-bold">VOIR
                                    TOUT <i class="fa-solid fa-chevron-right ms-1"></i></a>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($recent_sales)): ?>
                                <div class="text-muted text-center py-5 opacity-50">
                                    <i class="fa-solid fa-receipt fa-2x mb-3"></i>
                                    <div>Aucune vente récente</div>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-dark table-hover align-middle mb-0">
                                        <thead>
                                            <tr class="text-muted extra-small text-uppercase">
                                                <th class="px-4">Date & Heure</th>
                                                <th>Client / Vendeur</th>
                                                <th class="text-end">Montant</th>
                                                <th class="text-end px-4">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_sales as $sale): ?>
                                                <tr>
                                                    <td class="px-4">
                                                        <div class="text-white small fw-bold">
                                                            <?= date('d M, Y', strtotime($sale['sale_date'])) ?>
                                                        </div>
                                                        <div class="extra-small text-muted">
                                                            <?= date('H:i', strtotime($sale['sale_date'])) ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="text-white small fw-bold">
                                                            <?= htmlspecialchars($sale['client_name'] ?? 'Client de passage') ?>
                                                        </div>
                                                        <div class="extra-small text-muted"><i
                                                                class="fa-solid fa-user-tie me-1"></i><?= htmlspecialchars($sale['username']) ?>
                                                        </div>
                                                    </td>
                                                    <td class="text-end fw-bold text-success">
                                                        <?= number_format($sale['total_amount'], 0, ',', ' ') ?> <span
                                                            class="extra-small fw-normal">FCFA</span>
                                                    </td>
                                                    <td class="text-end px-4">
                                                        <a href="invoice.php?id=<?= $sale['id_sale'] ?>"
                                                            class="btn btn-sm btn-outline-light border-0" target="_blank">
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

                <div class="col-lg-4">
                    <div class="recent-card">
                        <div class="p-4 border-bottom border-white border-opacity-5">
                            <h5 class="text-white fw-bold mb-0">Actions Rapides</h5>
                        </div>
                        <div class="p-4 d-flex flex-column gap-3">
                            <a href="sales.php" class="action-btn-premium">
                                <i class="fa-solid fa-cash-register"></i>
                                <div>
                                    <div class="text-white fw-bold small">Point de Vente</div>
                                    <div class="extra-small opacity-50">Effectuer une nouvelle vente</div>
                                </div>
                            </a>
                            <a href="products.php" class="action-btn-premium">
                                <i class="fa-solid fa-cart-plus"></i>
                                <div>
                                    <div class="text-white fw-bold small">Produits</div>
                                    <div class="extra-small opacity-50">Gérer le catalogue</div>
                                </div>
                            </a>
                            <a href="stock.php" class="action-btn-premium">
                                <i class="fa-solid fa-boxes-stacked"></i>
                                <div>
                                    <div class="text-white fw-bold small">Inventaire</div>
                                    <div class="extra-small opacity-50">Ajustement du stock</div>
                                </div>
                            </a>
                            <a href="reports.php" class="action-btn-premium border-0 mt-2 bg-primary bg-opacity-10"
                                style="color: white; border-radius: 20px;">
                                <i class="fa-solid fa-chart-line bg-primary"></i>
                                <div>
                                    <div class="fw-bold small">Rapports & Ventes</div>
                                    <div class="extra-small opacity-75">Statistiques détaillées</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="../assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <script src="../assets/vendor/chart.js/chart.umd.js"></script>
    <script src="../assets/js/app.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Chart Defaults
            Chart.defaults.color = '#94a3b8';
            Chart.defaults.borderColor = 'rgba(255, 255, 255, 0.05)';
            Chart.defaults.font.family = "'Inter', sans-serif";

            // Sales Chart
            const ctxSales = document.getElementById('salesChart');
            if (ctxSales) {
                new Chart(ctxSales, {
                    type: 'line',
                    data: {
                        labels: <?= json_encode($revenue_dates) ?>,
                        datasets: [{
                            label: 'Chiffre d\'affaires (FCFA)',
                            data: <?= json_encode($revenue_totals) ?>,
                            borderColor: '#3b82f6',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            borderWidth: 3,
                            pointBackgroundColor: '#3b82f6',
                            pointBorderColor: '#1e293b',
                            pointBorderWidth: 2,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            fill: true,
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                backgroundColor: 'rgba(15, 23, 42, 0.9)',
                                titleColor: '#f8fafc',
                                bodyColor: '#cbd5e1',
                                padding: 12,
                                cornerRadius: 8,
                                displayColors: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(255, 255, 255, 0.05)'
                                },
                                ticks: {
                                    callback: function (value) {
                                        return value >= 1000 ? (value / 1000) + 'k' : value;
                                    }
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                }
                            }
                        }
                    }
                });
            }

            // Top Products Chart
            const ctxProducts = document.getElementById('productsChart');
            if (ctxProducts) {
                new Chart(ctxProducts, {
                    type: 'doughnut',
                    data: {
                        labels: <?= json_encode($top_product_names) ?>,
                        datasets: [{
                            data: <?= json_encode($top_product_values) ?>,
                            backgroundColor: [
                                '#3b82f6', // Premium Blue
                                '#10b981', // Success Green
                                '#f59e0b', // Warning Orange
                                '#8b5cf6', // Purple
                                '#ec4899' // Pink
                            ],
                            borderWidth: 0,
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 20,
                                    usePointStyle: true,
                                    pointStyle: 'circle'
                                }
                            }
                        },
                        cutout: '70%'
                    }
                });
            }
        });
    </script>
    <script src="../assets/js/app.js"></script>
    <script>
        // FORCE PWA UPDATE (Self-Healing)
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.getRegistrations().then(function (registrations) {
                for (let registration of registrations) {
                    console.log('Unregistering SW to force update:', registration);
                    registration.unregister();
                }
                const wasReloaded = sessionStorage.getItem('pwa_force_reload');
                if (!wasReloaded) {
                    sessionStorage.setItem('pwa_force_reload', 'true');
                    console.log('Reloading page to apply updates...');
                    window.location.reload(true);
                }
            });
        }
    </script>
</body>

</html>
