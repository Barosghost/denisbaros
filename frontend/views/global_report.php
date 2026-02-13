<?php
define('PAGE_ACCESS', 'global_report');
require_once '../../backend/includes/auth_required.php';
require_once '../../backend/config/db.php';
require_once '../../backend/config/functions.php';

$pageTitle = "Rapport Global d'Activité";

// Date Filters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// --- DATA FETCHING ---

// 1. BOUTIQUE (SALES)
$sql_sales = "SELECT v.*, u.username, c.nom_client as client_name, r.nom_partenaire as reseller_name, r.taux_commission_fixe
              FROM ventes v
              LEFT JOIN utilisateurs u ON v.id_vendeur = u.id_user
              LEFT JOIN clients c ON v.id_client = c.id_client
              LEFT JOIN revendeurs r ON v.id_revendeur = r.id_revendeur
              WHERE DATE(v.date_vente) BETWEEN ? AND ?
              ORDER BY v.date_vente DESC";
$stmt_sales = $pdo->prepare($sql_sales);
$stmt_sales->execute([$start_date, $end_date]);
$sales_data = $stmt_sales->fetchAll(PDO::FETCH_ASSOC);

$shop_revenue_total = 0;
$shop_revenue_internal = 0;
$shop_revenue_reseller = 0;
$reseller_margins_total = 0;
$client_paid_total = 0;
$sales_count = 0;

foreach ($sales_data as $sale) {
    $total_transaction = $sale['prix_revente_final'];
    $r_margin = 0;

    if ($sale['id_revendeur']) {
        $r_margin = ($sale['taux_commission_fixe'] / 100) * $total_transaction;
        $shop_revenue_reseller += ($total_transaction - $r_margin);
        $reseller_margins_total += $r_margin;
    } else {
        $shop_revenue_internal += $total_transaction;
    }

    $client_paid_total += $total_transaction;
    $shop_revenue_total += ($total_transaction - $r_margin);
    $sales_count++;
}

// 2. RESELLERS SUMMARY
$sql_resellers = "SELECT r.nom_partenaire as fullname, 
                         COUNT(v.id_vente) as sale_count, 
                         SUM(v.prix_revente_final) as client_total_generated,
                         SUM((r.taux_commission_fixe / 100) * v.prix_revente_final) as margin_total
                  FROM revendeurs r
                  LEFT JOIN ventes v ON r.id_revendeur = v.id_revendeur AND DATE(v.date_vente) BETWEEN ? AND ?
                  GROUP BY r.id_revendeur";
$stmt_resellers = $pdo->prepare($sql_resellers);
$stmt_resellers->execute([$start_date, $end_date]);
$resellers_stats = $stmt_resellers->fetchAll(PDO::FETCH_ASSOC);

// 3. SAV (TECHNICAL SERVICE)
$sql_tech_req = "SELECT s.*, t.fullname as tech_name 
                 FROM sav_dossiers s 
                 LEFT JOIN technicians t ON s.id_technicien = t.id_technician
                 WHERE DATE(s.date_depot) BETWEEN ? AND ?
                 ORDER BY s.date_depot DESC";
$stmt_tech_req = $pdo->prepare($sql_tech_req);
$stmt_tech_req->execute([$start_date, $end_date]);
$tech_requests = $stmt_tech_req->fetchAll(PDO::FETCH_ASSOC);

$tech_stats = [
    'count' => 0,
    'repaired' => 0,
    'lost' => 0,
    'pending' => 0,
    'total_est_cost' => 0
];

foreach ($tech_requests as $tr) {
    $tech_stats['count']++;
    $tech_stats['total_est_cost'] += $tr['cout_estime'];

    if ($tr['statut_sav'] === 'livre' || $tr['statut_sav'] === 'pret')
        $tech_stats['repaired']++;
    elseif ($tr['statut_sav'] === 'neuf_hs')
        $tech_stats['lost']++;
    else
        $tech_stats['pending']++;
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Rapport Global | DENIS FBI STORE</title>
    <link href="../assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    <style>
        :root {
            --primary: #0f172a;
            --accent: #6366f1;
        }

        body {
            background: #e2e8f0;
            color: #1e293b;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 0.85rem;
        }

        .report-container {
            max-width: 210mm;
            margin: 20px auto;
            background: white;
            padding: 40px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .header-box {
            border-bottom: 3px solid var(--primary);
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .section-header {
            background: var(--primary);
            color: white;
            padding: 8px 15px;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 30px;
            border-left: 5px solid var(--accent);
        }

        .stat-box {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            padding: 15px;
            text-align: center;
            border-radius: 6px;
        }

        .stat-value {
            font-size: 1.4rem;
            font-weight: bold;
            color: var(--primary);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th,
        td {
            border: 1px solid #cbd5e1;
            padding: 8px;
            text-align: left;
        }

        th {
            background: #f1f5f9;
            font-size: 0.75rem;
        }

        @media print {
            body {
                background: white;
            }

            .report-container {
                box-shadow: none;
                margin: 0;
                padding: 0;
                max-width: 100%;
            }

            .no-print {
                display: none !important;
            }
        }
    </style>
</head>

<body>

    <div class="container-fluid no-print py-3 bg-dark text-white mb-4">
        <div class="row align-items-center justify-content-center">
            <div class="col-md-8">
                <form class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="small text-muted">Début</label>
                        <input type="date" name="start_date"
                            class="form-control form-control-sm bg-dark text-white border-secondary"
                            value="<?= $start_date ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="small text-muted">Fin</label>
                        <input type="date" name="end_date"
                            class="form-control form-control-sm bg-dark text-white border-secondary"
                            value="<?= $end_date ?>">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary btn-sm w-100">Actualiser</button>
                    </div>
                    <div class="col-md-2">
                        <button type="button" onclick="window.print()" class="btn btn-light btn-sm w-100"><i
                                class="fa-solid fa-print"></i> Imprimer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="report-container">
        <div class="header-box d-flex justify-content-between">
            <div>
                <h1 class="h3 fw-bold mb-0">DENIS FBI STORE</h1>
                <div class="text-muted small">Rapport d'Activité Global</div>
            </div>
            <div class="text-end">
                <div>Du <?= date('d/m/Y', strtotime($start_date)) ?> au <?= date('d/m/Y', strtotime($end_date)) ?></div>
            </div>
        </div>

        <div class="section-header">1. Résumé Boutique</div>
        <div class="row g-3 mt-1 text-center">
            <div class="col-4">
                <div class="stat-box">
                    <div class="stat-value"><?= number_format($client_paid_total, 0, ',', ' ') ?></div>
                    <div class="stat-label">Total Encaissé Clients</div>
                </div>
            </div>
            <div class="col-4">
                <div class="stat-box">
                    <div class="stat-value text-primary"><?= number_format($shop_revenue_total, 0, ',', ' ') ?></div>
                    <div class="stat-label">Revenu Net Boutique</div>
                </div>
            </div>
            <div class="col-4">
                <div class="stat-box">
                    <div class="stat-value text-warning"><?= number_format($reseller_margins_total, 0, ',', ' ') ?>
                    </div>
                    <div class="stat-label">Commissions Revendeurs</div>
                </div>
            </div>
        </div>

        <div class="section-header">2. Performance Partenaires</div>
        <table>
            <thead>
                <tr>
                    <th>Revendeur</th>
                    <th class="text-center">Ventes</th>
                    <th class="text-end">CA Brut</th>
                    <th class="text-end">Commission</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($resellers_stats as $rs): ?>
                    <tr>
                        <td><?= htmlspecialchars($rs['fullname']) ?></td>
                        <td class="text-center"><?= $rs['sale_count'] ?></td>
                        <td class="text-end"><?= number_format($rs['client_total_generated'], 0, ',', ' ') ?></td>
                        <td class="text-end text-success"><?= number_format($rs['margin_total'], 0, ',', ' ') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="section-header">3. Service Technique (SAV)</div>
        <div class="row mt-2 text-center">
            <div class="col-6">
                <div class="stat-box">
                    <div class="stat-value"><?= $tech_stats['count'] ?></div>
                    <div class="stat-label">Total Dossiers</div>
                </div>
            </div>
            <div class="col-6">
                <div class="stat-box">
                    <div class="stat-value text-success"><?= $tech_stats['repaired'] ?></div>
                    <div class="stat-label">Terminés / Livrés</div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>