<?php
define('PAGE_ACCESS', 'reports');
require_once '../../backend/includes/auth_required.php';
require_once '../../backend/config/db.php';
$pageTitle = "Rapports & Statistiques";

// Defaults: Current Month
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// --- 1. DATA PROCESSING ---

// A. SALES & BOUTIQUE BREAKDOWN
$query = "SELECT v.*, u.username, c.nom_client as client_name, r.nom_partenaire as reseller_name, r.taux_commission_fixe
          FROM ventes v 
          LEFT JOIN utilisateurs u ON v.id_vendeur = u.id_user 
          LEFT JOIN clients c ON v.id_client = c.id_client 
          LEFT JOIN revendeurs r ON v.id_revendeur = r.id_revendeur
          WHERE DATE(v.date_vente) BETWEEN ? AND ? 
          ORDER BY v.date_vente DESC";
$stmt = $pdo->prepare($query);
$stmt->execute([$start_date, $end_date]);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

$boutique_total = 0; // Total Sales Amount
$boutique_internal = 0;
$boutique_reseller = 0;
$total_commissions = 0;
$total_cost = 0;

foreach ($sales as $sale) {
    if ($sale['id_revendeur']) {
        // Dynamic commission calculation: taux_commission_fixe * prix_revente_final
        $commission = ($sale['taux_commission_fixe'] / 100) * $sale['prix_revente_final'];
        $total_commissions += $commission;
        $boutique_reseller += $sale['prix_revente_final'];
    } else {
        $boutique_internal += $sale['prix_revente_final'];
    }
    $boutique_total += $sale['prix_revente_final'];
}

// B. COST CALCULATION (for margin)
$stmt_cost = $pdo->prepare("
    SELECT SUM(vd.quantite * p.prix_achat) 
    FROM vente_details vd 
    JOIN produits p ON vd.id_produit = p.id_produit 
    JOIN ventes v ON vd.id_vente = v.id_vente
    WHERE DATE(v.date_vente) BETWEEN ? AND ?
");
$stmt_cost->execute([$start_date, $end_date]);
$total_cost = $stmt_cost->fetchColumn() ?: 0;
$global_margin = $boutique_total - $total_commissions - $total_cost;

// C. RESELLERS PERFORMANCE
$sql_resellers = "SELECT r.nom_partenaire as fullname, 
                         COUNT(v.id_vente) as sale_count, 
                         SUM(v.prix_revente_final) as revenue_generated, 
                         SUM((r.taux_commission_fixe / 100) * v.prix_revente_final) as commission_total
                  FROM revendeurs r
                  LEFT JOIN ventes v ON r.id_revendeur = v.id_revendeur AND DATE(v.date_vente) BETWEEN ? AND ?
                  GROUP BY r.id_revendeur";
$stmt_resellers = $pdo->prepare($sql_resellers);
$stmt_resellers->execute([$start_date, $end_date]);
$resellers_stats = $stmt_resellers->fetchAll(PDO::FETCH_ASSOC);

// D. TECHNICAL SERVICE STATS
$stmt_tech_stats = $pdo->prepare("SELECT statut_sav as status, COUNT(*) as count FROM sav_dossiers WHERE DATE(date_depot) BETWEEN ? AND ? GROUP BY statut_sav");
$stmt_tech_stats->execute([$start_date, $end_date]);
$tech_status_stats = $stmt_tech_stats->fetchAll(PDO::FETCH_ASSOC);

$stmt_tech_perf = $pdo->prepare("
    SELECT t.fullname, COUNT(s.id_sav) as total, SUM(CASE WHEN s.statut_sav IN ('livre','pret') THEN 1 ELSE 0 END) as done
    FROM technicians t
    LEFT JOIN sav_dossiers s ON t.id_technician = s.id_technicien AND DATE(s.date_depot) BETWEEN ? AND ?
    GROUP BY t.id_technician
");
$stmt_tech_perf->execute([$start_date, $end_date]);
$tech_performance = $stmt_tech_perf->fetchAll(PDO::FETCH_ASSOC);

// E. CHART DATA
$stmt_chart = $pdo->prepare("
    SELECT 
        DATE(v.date_vente) as d, 
        SUM(v.prix_revente_final) as t
    FROM ventes v 
    WHERE DATE(v.date_vente) BETWEEN ? AND ? 
    GROUP BY DATE(v.date_vente) 
    ORDER BY d ASC
");
$stmt_chart->execute([$start_date, $end_date]);
$chart_data = $stmt_chart->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapports | DENIS FBI STORE</title>
    <link href="../assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="../assets/vendor/chart.js/chart.umd.js"></script>
    <style>
        .report-stat-card {
            border-radius: 16px;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.05);
            background: rgba(15, 23, 42, 0.4);
            transition: transform 0.2s;
        }

        .icon-box {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 1rem;
        }

        .section-header {
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            color: #94a3b8;
            font-weight: 700;
            margin-bottom: 1rem;
            border-left: 3px solid #6366f1;
            padding-left: 10px;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <div class="no-print">
            <?php include '../../backend/includes/sidebar.php'; ?>
        </div>
        <div id="content">
            <div class="no-print">
                <?php include '../../backend/includes/header.php'; ?>
            </div>

            <div class="fade-in mt-3">

                <!-- Filter -->
                <div class="card bg-dark border-0 glass-panel mb-4 p-3">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="text-muted small fw-bold">DÉBUT</label>
                            <input type="date" name="start_date"
                                class="form-control bg-dark text-white border-secondary" value="<?= $start_date ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="text-muted small fw-bold">FIN</label>
                            <input type="date" name="end_date" class="form-control bg-dark text-white border-secondary"
                                value="<?= $end_date ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-premium w-100"><i
                                    class="fa-solid fa-sync-alt me-2"></i>Filtrer</button>
                        </div>
                    </form>
                </div>

                <!-- 1. GLOBAL SUMMARY CARDS -->
                <div class="section-header">Vue d'ensemble Financière</div>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="report-stat-card">
                            <div class="icon-box bg-primary bg-opacity-10 text-primary"><i
                                    class="fa-solid fa-sack-dollar"></i></div>
                            <div class="text-muted extra-small fw-bold text-uppercase">Chiffre d'Affaires Total</div>
                            <h3 class="text-white fw-bold mb-0"><?= number_format($boutique_total, 0, ',', ' ') ?>
                                <small class="fs-6 text-muted">FCFA</small>
                            </h3>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="report-stat-card">
                            <div class="icon-box bg-success bg-opacity-10 text-success"><i class="fa-solid fa-shop"></i>
                            </div>
                            <div class="text-muted extra-small fw-bold text-uppercase">Bénéfice Net Boutique</div>
                            <h3 class="text-white fw-bold mb-0"><?= number_format($global_margin, 0, ',', ' ') ?> <small
                                    class="fs-6 text-muted">FCFA</small></h3>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="report-stat-card">
                            <div class="icon-box bg-warning bg-opacity-10 text-warning"><i
                                    class="fa-solid fa-handshake"></i></div>
                            <div class="text-muted extra-small fw-bold text-uppercase">Total Commissions</div>
                            <h3 class="text-white fw-bold mb-0"><?= number_format($total_commissions, 0, ',', ' ') ?>
                                <small class="fs-6 text-muted">FCFA</small>
                            </h3>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <!-- CHARTS -->
                    <div class="col-lg-8">
                        <div class="card chart-card border-0 glass-panel h-100">
                            <div
                                class="card-header border-bottom border-secondary border-opacity-20 text-white py-3 px-4 fw-bold">
                                Évolution des Ventes</div>
                            <div class="card-body p-4">
                                <div style="height: 300px;"><canvas id="salesChart"></canvas></div>
                            </div>
                        </div>
                    </div>

                    <!-- BOUTIQUE DETAILS -->
                    <div class="col-lg-4">
                        <div class="card border-0 glass-panel h-100">
                            <div
                                class="card-header border-bottom border-secondary border-opacity-20 text-white py-3 px-4 fw-bold">
                                Détail Ventes</div>
                            <div class="card-body p-0">
                                <ul class="list-group list-group-flush bg-transparent">
                                    <li
                                        class="list-group-item bg-transparent text-white d-flex justify-content-between py-3 border-secondary border-opacity-20">
                                        <span>Ventes Magasin</span>
                                        <span class="fw-bold"><?= number_format($boutique_internal, 0, ',', ' ') ?>
                                            FCFA</span>
                                    </li>
                                    <li
                                        class="list-group-item bg-transparent text-white d-flex justify-content-between py-3 border-secondary border-opacity-20">
                                        <span>Ventes Revendeurs</span>
                                        <span class="fw-bold"><?= number_format($boutique_reseller, 0, ',', ' ') ?>
                                            FCFA</span>
                                    </li>
                                    <li
                                        class="list-group-item bg-transparent text-white d-flex justify-content-between py-3 border-secondary border-opacity-20">
                                        <span class="text-muted">Coût Marchandises</span>
                                        <span class="text-muted">- <?= number_format($total_cost, 0, ',', ' ') ?>
                                            FCFA</span>
                                    </li>
                                    <li
                                        class="list-group-item bg-transparent text-white d-flex justify-content-between py-3 border-secondary border-opacity-20">
                                        <span class="text-muted">Commissions Dues</span>
                                        <span class="text-muted">- <?= number_format($total_commissions, 0, ',', ' ') ?>
                                            FCFA</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TABLES -->
                <div class="row g-4 mb-4">
                    <div class="col-lg-6">
                        <div class="card border-0 glass-panel h-100">
                            <div
                                class="card-header border-bottom border-secondary border-opacity-20 text-white py-3 px-4 fw-bold">
                                Performance Revendeurs</div>
                            <div class="table-responsive">
                                <table class="table table-dark table-hover mb-0 small">
                                    <thead>
                                        <tr>
                                            <th>Nom</th>
                                            <th class="text-center">Ventes</th>
                                            <th class="text-end">CA Généré</th>
                                            <th class="text-end">Commissions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($resellers_stats as $rs): ?>
                                            <tr>
                                                <td class="fw-bold"><?= htmlspecialchars($rs['fullname']) ?></td>
                                                <td class="text-center"><?= $rs['sale_count'] ?></td>
                                                <td class="text-end">
                                                    <?= number_format($rs['revenue_generated'], 0, ',', ' ') ?>
                                                </td>
                                                <td class="text-end text-warning">
                                                    <?= number_format($rs['commission_total'], 0, ',', ' ') ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card border-0 glass-panel h-100">
                            <div
                                class="card-header border-bottom border-secondary border-opacity-20 text-white py-3 px-4 fw-bold">
                                Service SAV</div>
                            <div class="table-responsive">
                                <table class="table table-dark table-hover mb-0 small">
                                    <thead>
                                        <tr>
                                            <th>Technicien</th>
                                            <th class="text-center">Dossiers</th>
                                            <th class="text-end">Terminés</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tech_performance as $tp): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($tp['fullname']) ?></td>
                                                <td class="text-center"><?= $tp['total'] ?></td>
                                                <td class="text-end text-success fw-bold"><?= $tp['done'] ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- JOURNAL -->
                <div class="card bg-dark border-0 glass-panel shadow-lg">
                    <div
                        class="card-header border-bottom border-secondary border-opacity-20 text-white py-3 px-4 d-flex justify-content-between align-items-center">
                        <span class="fw-bold">Journal des Ventes</span>
                        <span class="badge bg-secondary bg-opacity-20 text-muted"><?= count($sales) ?>
                            transactions</span>
                    </div>
                    <div class="table-responsive" style="max-height: 500px;">
                        <table class="table table-dark table-hover mb-0 align-middle small">
                            <thead class="bg-dark sticky-top">
                                <tr>
                                    <th class="px-3">Date</th>
                                    <th>Client</th>
                                    <th>Vendeur</th>
                                    <th class="text-end">Total</th>
                                    <th class="text-end">Comm.</th>
                                    <th class="text-end px-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sales as $s):
                                    $comm = ($s['id_revendeur']) ? ($s['taux_commission_fixe'] / 100) * $s['prix_revente_final'] : 0;
                                    ?>
                                    <tr>
                                        <td class="px-3 text-muted"><?= date('d/m H:i', strtotime($s['date_vente'])) ?></td>
                                        <td><?= htmlspecialchars($s['client_name'] ?? 'Passage') ?></td>
                                        <td><?= htmlspecialchars($s['username']) ?></td>
                                        <td class="text-end fw-bold">
                                            <?= number_format($s['prix_revente_final'], 0, ',', ' ') ?>
                                        </td>
                                        <td class="text-end text-muted">
                                            <?= $comm > 0 ? number_format($comm, 0, ',', ' ') : '-' ?>
                                        </td>
                                        <td class="text-end px-4">
                                            <a href="invoice.php?id=<?= $s['id_vente'] ?>" target="_blank"
                                                class="btn btn-sm btn-outline-light border-0"><i
                                                    class="fa-solid fa-print"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="../assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
    <script>
        Chart.defaults.color = '#94a3b8';
        const ctx = document.getElementById('salesChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_column($chart_data, 'd')) ?>,
                datasets: [{
                    label: 'Ventes',
                    data: <?= json_encode(array_column($chart_data, 't')) ?>,
                    borderColor: '#6366f1',
                    tension: 0.4,
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { grid: { color: 'rgba(255,255,255,0.05)' } },
                    x: { grid: { display: false } }
                }
            }
        });
    </script>
</body>

</html>