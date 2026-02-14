<?php
define('PAGE_ACCESS', 'dashboard');
require_once '../../backend/includes/auth_required.php';
$pageTitle = "Tableau de Bord";

// Initialize stats to handle case where queries fail or role logic branches
$my_active_repairs = 0;
$pending_diagnostics = 0;
$completed_today = 0;
$recent_logs = [];
$today_sales = 0;
$low_stock = 0;
$recent_sales = [];
$products_count = 0;
$clients_count = 0;
$revenue_dates = [];
$revenue_totals = [];
$top_product_names = [];
$top_product_values = [];
$total_stock_value = 0;

try {
    if (strtolower($_SESSION['role']) === 'technicien') {
        // --- TECHNICIAN STATS ---
        $user_id = $_SESSION['user_id'];

        // Find links and get tech ID
        $tech_stmt = $pdo->prepare("SELECT id_technician FROM technicians WHERE id_user = ?");
        $tech_stmt->execute([$user_id]);
        $tech_id = $tech_stmt->fetchColumn() ?: 0;

        // 1. My Active Repairs
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sav_dossiers WHERE id_technicien = ? AND statut_sav IN ('en_diagnostic', 'en_reparation')");
        $stmt->execute([$tech_id]);
        $my_active_repairs = $stmt->fetchColumn();

        // 2. Pending Diagnostics
        $stmt = $pdo->query("SELECT COUNT(*) FROM sav_dossiers WHERE statut_sav = 'en_attente'");
        $pending_diagnostics = $stmt->fetchColumn();

        // 3. Completed Today
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM service_logs 
            WHERE id_technicien = ? 
            AND (action LIKE '%Terminé%' OR action LIKE '%Prêt%' OR action LIKE '%Livré%')
            AND DATE(date) = CURDATE()
        ");
        $stmt->execute([$tech_id]);
        $completed_today = $stmt->fetchColumn();

        // 4. My Recent Services (sav_dossiers: appareil_modele, pas marque/modele)
        $stmt = $pdo->prepare("
            SELECT sl.date as created_at, sl.action, sl.details, sd.appareil_modele as request_desc
            FROM service_logs sl 
            JOIN sav_dossiers sd ON sl.id_sav = sd.id_sav
            WHERE sl.id_technicien = ? 
            ORDER BY sl.date DESC LIMIT 5
        ");
        $stmt->execute([$tech_id]);
        $recent_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } else {
        // --- ADMIN / VENDEUR STATS ---
        // 1. Today's Sales - prix_revente_final instead of montant_total
        $stmt = $pdo->query("SELECT SUM(prix_revente_final) FROM ventes WHERE DATE(date_vente) = CURDATE()");
        $today_sales = $stmt->fetchColumn() ?: 0;

        // 2. Total Products
        $products_count = $pdo->query("SELECT COUNT(*) FROM produits")->fetchColumn();

        // 3. Low Stock
        $low_stock = $pdo->query("SELECT COUNT(*) FROM produits WHERE stock_actuel < seuil_alerte")->fetchColumn();

        // 4. Total Clients
        $clients_count = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();

        // 4b. Valeur totale du stock
        $total_stock_value = $pdo->query("SELECT COALESCE(SUM(prix_achat * stock_actuel), 0) FROM produits")->fetchColumn() ?: 0;

        // 5. Recent Transactions
        $stmt = $pdo->query("
            SELECT v.id_vente as id_sale, v.prix_revente_final as total_amount, v.date_vente as sale_date,
                   u.username, c.nom_client as client_name
            FROM ventes v
            JOIN utilisateurs u ON v.id_vendeur = u.id_user
            LEFT JOIN clients c ON v.id_client = c.id_client
            ORDER BY v.date_vente DESC LIMIT 5
        ");
        $recent_sales = $stmt->fetchAll();

        // 6. Chart Data: Last 7 Days Revenue
        $revenue_dates = [];
        $revenue_totals = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $revenue_dates[] = date('d/m', strtotime($date));
            $stmt = $pdo->prepare("SELECT SUM(prix_revente_final) FROM ventes WHERE DATE(date_vente) = ?");
            $stmt->execute([$date]);
            $revenue_totals[] = $stmt->fetchColumn() ?: 0;
        }

        // 7. Chart Data: Top 5 Products (Revenue)
        $stmt = $pdo->query("
            SELECT p.designation as name, SUM(vd.quantite * vd.prix_unitaire) as total_sold
            FROM vente_details vd
            JOIN produits p ON vd.id_produit = p.id_produit
            GROUP BY vd.id_produit
            ORDER BY total_sold DESC LIMIT 5
        ");
        $top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $top_product_names = array_column($top_products, 'name');
        $top_product_values = array_column($top_products, 'total_sold');
    }

} catch (PDOException $e) {
    error_log("Dashboard Data Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | DENIS FBI STORE</title>
    <link href="../assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=1.4">
    <script src="../assets/vendor/sweetalert2/sweetalert2.all.min.js"></script>
    <style>
        .dashboard-stat-card {
            background: rgba(30, 41, 59, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 24px;
            padding: 30px;
            display: flex;
            align-items: center;
            gap: 25px;
            transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
            backdrop-filter: blur(10px);
            height: 100%;
            position: relative;
            overflow: hidden;
        }

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

        .stat-icon-large {
            width: 70px;
            height: 70px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.05);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            box-shadow: inset 0 0 20px rgba(0, 0, 0, 0.2);
        }

        .recent-card {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            overflow: hidden;
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
            text-decoration: none;
            transition: all 0.3s;
        }

        .action-btn-premium:hover {
            background: rgba(99, 102, 241, 0.1);
            color: white;
            transform: translateX(5px);
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include '../../backend/includes/sidebar.php'; ?>
        <div id="content">
            <?php include '../../backend/includes/header.php'; ?>

            <?php if (isset($_GET['error']) && $_GET['error'] === 'forbidden'): ?>
            <div class="alert alert-warning alert-dismissible fade show mx-3 mt-2" role="alert">
                <i class="fa-solid fa-lock me-2"></i> Vous n'avez pas accès à cette page avec votre rôle.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
            </div>
            <?php endif; ?>

            <div class="row g-4 mt-2 fade-in">
                <?php if (strtolower($_SESSION['role']) === 'technicien'): ?>
                    <div class="col-xl-4 col-md-6">
                        <div class="dashboard-stat-card card-premium-2">
                            <div class="stat-icon-large text-primary"><i class="fa-solid fa-screwdriver-wrench"></i></div>
                            <div class="flex-grow-1">
                                <div class="text-white-50 small text-uppercase fw-bold mb-2">Mes Réparations</div>
                                <div class="text-white h2 fw-bold mb-1"><?= $my_active_repairs ?></div>
                                <div class="extra-small text-muted">Assignées à moi</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-md-6">
                        <div class="dashboard-stat-card card-premium-3">
                            <div class="stat-icon-large text-warning"><i class="fa-solid fa-microscope"></i></div>
                            <div class="flex-grow-1">
                                <div class="text-white-50 small text-uppercase fw-bold mb-2">Diagnostics</div>
                                <div class="text-white h2 fw-bold mb-1"><?= $pending_diagnostics ?></div>
                                <div class="extra-small text-muted">En attente</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-md-12">
                        <div class="dashboard-stat-card card-premium-1">
                            <div class="stat-icon-large text-success"><i class="fa-solid fa-circle-check"></i></div>
                            <div class="flex-grow-1">
                                <div class="text-white-50 small text-uppercase fw-bold mb-2">Succès Journée</div>
                                <div class="text-white h2 fw-bold mb-1"><?= $completed_today ?></div>
                                <div class="extra-small text-muted">Aujourd'hui</div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <?php
                    $pending_sav = 0;
                    try {
                        $pending_sav = $pdo->query("SELECT COUNT(*) FROM sav_dossiers WHERE statut_sav = 'en_attente'")->fetchColumn();
                    } catch (PDOException $e) { }
                    ?>
                    <div class="col-xl-4 col-md-6">
                        <div class="dashboard-stat-card card-premium-1">
                            <div class="stat-icon-large text-success"><i class="fa-solid fa-coins"></i></div>
                            <div class="flex-grow-1">
                                <div class="text-white-50 small text-uppercase fw-bold mb-2">Ventes du Jour</div>
                                <div class="text-white h2 fw-bold mb-1"><?= number_format($today_sales, 0, ',', ' ') ?>
                                    <small class="text-muted fs-6">FCFA</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-md-6">
                        <div class="dashboard-stat-card card-premium-2">
                            <div class="stat-icon-large text-primary"><i class="fa-solid fa-box-open"></i></div>
                            <div class="flex-grow-1">
                                <div class="text-white-50 small text-uppercase fw-bold mb-2">Produits</div>
                                <div class="text-white h2 fw-bold mb-1"><?= $products_count ?></div>
                                <div class="extra-small text-muted">Références</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-md-6">
                        <div class="dashboard-stat-card card-premium-3">
                            <div class="stat-icon-large text-warning"><i class="fa-solid fa-triangle-exclamation"></i></div>
                            <div class="flex-grow-1">
                                <div class="text-white-50 small text-uppercase fw-bold mb-2">Stock Alerte</div>
                                <div class="text-white h2 fw-bold mb-1"><?= $low_stock ?> <small class="text-muted fs-6">articles</small></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-md-6">
                        <div class="dashboard-stat-card card-premium-4">
                            <div class="stat-icon-large text-info"><i class="fa-solid fa-users"></i></div>
                            <div class="flex-grow-1">
                                <div class="text-white-50 small text-uppercase fw-bold mb-2">Clients</div>
                                <div class="text-white h2 fw-bold mb-1"><?= $clients_count ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-md-6">
                        <div class="dashboard-stat-card card-premium-2">
                            <div class="stat-icon-large text-secondary"><i class="fa-solid fa-screwdriver-wrench"></i></div>
                            <div class="flex-grow-1">
                                <div class="text-white-50 small text-uppercase fw-bold mb-2">SAV en attente</div>
                                <div class="text-white h2 fw-bold mb-1"><?= $pending_sav ?></div>
                                <div class="extra-small text-muted">Dossiers</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-md-6">
                        <div class="dashboard-stat-card card-premium-1">
                            <div class="stat-icon-large text-success"><i class="fa-solid fa-wallet"></i></div>
                            <div class="flex-grow-1">
                                <div class="text-white-50 small text-uppercase fw-bold mb-2">Valeur stock</div>
                                <div class="text-white h2 fw-bold mb-1"><?= number_format($total_stock_value, 0, ',', ' ') ?> <small class="text-muted fs-6">FCFA</small></div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (strtolower($_SESSION['role']) !== 'technicien' && (count($revenue_dates) > 0 || count($top_product_names) > 0)): ?>
            <div class="row g-4 mt-2">
                <div class="col-lg-8">
                    <div class="recent-card p-4">
                        <h5 class="text-white fw-bold mb-4">Revenus des 7 derniers jours</h5>
                        <div style="height: 220px;"><canvas id="revenueChart"></canvas></div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="recent-card p-4">
                        <h5 class="text-white fw-bold mb-4">Top 5 produits (CA)</h5>
                        <div style="height: 220px;"><canvas id="topProductsChart"></canvas></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="row g-4 mt-2">
                <div class="col-lg-8">
                    <div class="recent-card p-4">
                        <h5 class="text-white fw-bold mb-4">
                            <?= strtolower($_SESSION['role']) === 'technicien' ? 'Mes Activités Récentes' : 'Dernières Ventes' ?>
                        </h5>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover align-middle mb-0">
                                <thead>
                                    <tr class="text-muted extra-small text-uppercase">
                                        <th class="px-4">Date</th>
                                        <th>Description</th>
                                        <th class="text-end px-4">Info</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (strtolower($_SESSION['role']) === 'technicien'): ?>
                                        <?php if (empty($recent_logs)): ?>
                                            <tr><td colspan="3" class="px-4 py-4 text-center text-muted">Aucune activité récente</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($recent_logs as $log): ?>
                                                <tr>
                                                    <td class="px-4 small"><?= date('d/m H:i', strtotime($log['created_at'] ?? $log['date'] ?? 'now')) ?></td>
                                                    <td class="small fw-bold text-info"><?= htmlspecialchars($log['action'] ?? '') ?> – <?= htmlspecialchars($log['request_desc'] ?? '') ?></td>
                                                    <td class="text-end px-4 extra-small text-muted"><?= htmlspecialchars($log['details'] ?? '') ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php if (empty($recent_sales)): ?>
                                            <tr><td colspan="3" class="px-4 py-4 text-center text-muted">Aucune vente récente</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($recent_sales as $sale): ?>
                                                <tr>
                                                    <td class="px-4 small"><?= date('d/m H:i', strtotime($sale['sale_date'])) ?></td>
                                                    <td class="small fw-bold">
                                                        <?= htmlspecialchars($sale['client_name'] ?: 'Passage') ?> <br>
                                                        <span class="extra-small text-muted">par <?= htmlspecialchars($sale['username']) ?></span>
                                                    </td>
                                                    <td class="text-end px-4 fw-bold text-success">
                                                        <?= number_format($sale['total_amount'], 0, ',', ' ') ?> FCFA
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="recent-card p-4">
                        <h5 class="text-white fw-bold mb-4">Actions Rapides</h5>
                        <div class="d-flex flex-column gap-3">
                            <a href="sales.php" class="action-btn-premium"><i class="fa-solid fa-cash-register"></i>
                                Point de Vente</a>
                            <a href="repairs.php" class="action-btn-premium"><i
                                    class="fa-solid fa-screwdriver-wrench"></i> SAV & Réparations</a>
                            <a href="stock.php" class="action-btn-premium"><i class="fa-solid fa-boxes-stacked"></i>
                                Gestion Stock</a>
                            <a href="daily_reports.php" class="action-btn-premium"><i
                                    class="fa-solid fa-clipboard-list"></i> Rapports Journaliers</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <script src="../assets/vendor/chart.js/chart.umd.js"></script>
    <script src="../assets/js/app.js"></script>
    <?php if (strtolower($_SESSION['role']) !== 'technicien' && (count($revenue_dates) > 0 || count($top_product_names) > 0)): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Chart.defaults.color = '#94a3b8';
            Chart.defaults.borderColor = 'rgba(255,255,255,0.08)';
            var revenueCtx = document.getElementById('revenueChart');
            if (revenueCtx) {
                new Chart(revenueCtx.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode($revenue_dates) ?>,
                        datasets: [{
                            label: 'Revenus (FCFA)',
                            data: <?= json_encode($revenue_totals) ?>,
                            backgroundColor: 'rgba(16, 185, 129, 0.4)',
                            borderColor: 'rgba(16, 185, 129, 0.8)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' } },
                            x: { grid: { display: false } }
                        }
                    }
                });
            }
            var topCtx = document.getElementById('topProductsChart');
            if (topCtx) {
                new Chart(topCtx.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: <?= json_encode(array_map(function($n) { return mb_substr($n, 0, 18) . (mb_strlen($n) > 18 ? '…' : ''); }, $top_product_names)) ?>,
                        datasets: [{
                            data: <?= json_encode($top_product_values) ?>,
                            backgroundColor: ['rgba(59, 130, 246, 0.7)', 'rgba(16, 185, 129, 0.7)', 'rgba(245, 158, 11, 0.7)', 'rgba(139, 92, 246, 0.7)', 'rgba(236, 72, 153, 0.7)'],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'bottom' } }
                    }
                });
            }
        });
    </script>
    <?php endif; ?>
</body>

</html>