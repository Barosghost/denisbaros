<?php
define('PAGE_ACCESS', 'supervision');
require_once '../../backend/includes/auth_required.php';
require_once '../../backend/config/db.php';
require_once '../../backend/config/functions.php';
$pageTitle = "Supervision Système";

// --- 1. Global Machines Stats (SAV) ---
// statut_sav instead of status
// cout_estime instead of estimated_cost
$stmt = $pdo->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN statut_sav IN ('en_attente', 'en_diagnostic', 'en_reparation') THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN statut_sav IN ('pret', 'livre') THEN 1 ELSE 0 END) as repaired,
    SUM(CASE WHEN statut_sav = 'neuf_hs' THEN 1 ELSE 0 END) as lost,
    SUM(CASE WHEN statut_sav = 'neuf_hs' THEN cout_estime ELSE 0 END) as financial_loss,
    SUM(CASE WHEN statut_sav = 'en_reparation' THEN 1 ELSE 0 END) as in_progress
    FROM sav_dossiers");
$machine_stats = $stmt->fetch();

// --- 2. Global Business & System Stats ---
// ventes instead of sales
// prix_revente_final instead of montant_total
// date_vente instead of sale_date
$stmt = $pdo->query("SELECT 
    COALESCE(SUM(CASE WHEN DATE(date_vente) = CURDATE() THEN prix_revente_final ELSE 0 END), 0) as sales_today,
    COALESCE(SUM(CASE WHEN date_vente >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN prix_revente_final ELSE 0 END), 0) as sales_month
    FROM ventes");
$business_stats = $stmt->fetch();

// utilisateurs instead of users
$stmt = $pdo->query("SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN statut = 'actif' THEN 1 ELSE 0 END) as active_users
    FROM utilisateurs");
$user_stats = $stmt->fetch();

// produits instead of stock
$stmt = $pdo->query("SELECT COUNT(*) FROM produits WHERE stock_actuel <= seuil_alerte");
$critical_stock_count = $stmt->fetchColumn();

// --- 3. Service Supervision ---
$stmt = $pdo->query("SELECT statut_sav as status, COUNT(*) as count FROM sav_dossiers WHERE date_depot >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY statut_sav");
$service_periodic_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Late machines (> 3 days in same status) - approximating with date_depot since we don't have updated_at in sav_dossiers
$stmt = $pdo->query("SELECT COUNT(*) FROM sav_dossiers WHERE statut_sav NOT IN ('pret', 'livre') AND date_depot < DATE_SUB(NOW(), INTERVAL 3 DAY)");
$stats['late_machines'] = $stmt->fetchColumn();

// --- 4. Technical Performance ---
// Simplified performance metrics for new schema
$stats['success_rate'] = 0;
$stmt = $pdo->query("SELECT COUNT(*) as total FROM sav_dossiers");
$total_dossiers = $stmt->fetchColumn();
if ($total_dossiers > 0) {
    $stats['success_rate'] = round(($machine_stats['repaired'] / $total_dossiers) * 100, 1);
}

$stats['relapse_count'] = 0; // Requires complex serial number tracking across dossiers

// --- 5. Alerts & Anomalies ---
$alerts = [];
if ($machine_stats['financial_loss'] > 500000) {
    $alerts[] = ['type' => 'warning', 'msg' => "Pertes financières estimées élevées : " . number_format($machine_stats['financial_loss'], 0, ',', ' ') . " FCFA"];
}
if ($critical_stock_count > 0) {
    $alerts[] = ['type' => 'info', 'msg' => "$critical_stock_count produits sont en stock critique."];
}

// --- 6. Top technicians (last 30 days) ---
$stmt = $pdo->query("SELECT t.fullname, COUNT(sd.id_sav) as repairs_count, 
                     COALESCE(SUM(CASE WHEN sd.statut_sav IN ('pret', 'livre') THEN 1 ELSE 0 END), 0) as completed_count
                     FROM technicians t
                     LEFT JOIN sav_dossiers sd ON t.id_technician = sd.id_technicien AND sd.date_depot >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                     GROUP BY t.id_technician
                     ORDER BY completed_count DESC
                     LIMIT 10");
$top_technicians = $stmt->fetchAll();

// Trace SN if requested
$traceability_history = [];
if (isset($_GET['sn']) && !empty($_GET['sn'])) {
    $sn = trim($_GET['sn']);
    $stmt = $pdo->prepare("SELECT sl.*, u.username, sd.marque, sd.modele
                          FROM service_logs sl
                          JOIN sav_dossiers sd ON sl.id_sav = sd.id_sav
                          JOIN utilisateurs u ON sl.id_user = u.id_user
                          WHERE sd.n_serie = ?
                          ORDER BY sl.date DESC");
    $stmt->execute([$sn]);
    $traceability_history = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supervision | DENIS FBI STORE</title>
    <link href="../assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=1.5">
    <style>
        .stat-card-super {
            background: rgba(30, 41, 59, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 1.5rem;
            transition: all 0.3s;
        }

        .stat-card-super:hover {
            transform: translateY(-5px);
            border-color: rgba(59, 130, 246, 0.3);
        }

        .alert-item {
            border-left: 4px solid;
            padding: 12px;
            margin-bottom: 12px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.02);
        }

        .alert-item.warning {
            border-color: #f59e0b;
            color: #fcd34d;
        }

        .alert-item.info {
            border-color: #3b82f6;
            color: #93c5fd;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include '../../backend/includes/sidebar.php'; ?>
        <div id="content">
            <?php include '../../backend/includes/header.php'; ?>

            <div class="p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="text-white"><i class="fa-solid fa-crown me-2 text-warning"></i> Supervision Système</h4>
                    <span class="badge bg-danger p-2 px-3">SUPER ADMIN</span>
                </div>

                <!-- Financial Stats -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="stat-card-super">
                            <div class="text-muted small fw-bold mb-2">CA JOUR</div>
                            <h3 class="text-white fw-bold">
                                <?= number_format($business_stats['sales_today'], 0, ',', ' ') ?>
                            </h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card-super">
                            <div class="text-muted small fw-bold mb-2">CA MOIS</div>
                            <h3 class="text-white fw-bold">
                                <?= number_format($business_stats['sales_month'], 0, ',', ' ') ?>
                            </h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card-super">
                            <div class="text-muted small fw-bold mb-2">USERS ACTIFS</div>
                            <h3 class="text-white fw-bold"><?= $user_stats['active_users'] ?> /
                                <?= $user_stats['total_users'] ?>
                            </h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card-super">
                            <div class="text-muted small fw-bold mb-2">STOCK ALERTE</div>
                            <h3 class="text-warning fw-bold"><?= $critical_stock_count ?></h3>
                        </div>
                    </div>
                </div>

                <!-- SAV Stats -->
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="stat-card-super text-center">
                            <div class="text-muted small fw-bold mb-2">TOTAL MACHINES</div>
                            <h2 class="text-white fw-bold"><?= $machine_stats['total'] ?></h2>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card-super text-center">
                            <div class="text-muted small fw-bold mb-2">EN COURS</div>
                            <h2 class="text-warning fw-bold"><?= $machine_stats['active'] ?></h2>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card-super text-center">
                            <div class="text-muted small fw-bold mb-2">TERMINÉES</div>
                            <h2 class="text-success fw-bold"><?= $machine_stats['repaired'] ?></h2>
                        </div>
                    </div>
                </div>

                <!-- Alerts & SN Trace -->
                <div class="row g-4 mb-4">
                    <div class="col-lg-6">
                        <div class="stat-card-super h-100">
                            <h6 class="text-white mb-3">Alertes Critiques</h6>
                            <?php foreach ($alerts as $alert): ?>
                                <div class="alert-item <?= $alert['type'] ?> small"><?= $alert['msg'] ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="stat-card-super h-100">
                            <h6 class="text-white mb-3">Traçabilité Par N° Série</h6>
                            <form class="d-flex gap-2 mb-3">
                                <input type="text" name="sn" class="form-control bg-dark border-secondary text-white"
                                    placeholder="S/N..." value="<?= htmlspecialchars($_GET['sn'] ?? '') ?>">
                                <button type="submit" class="btn btn-info px-4">Tracer</button>
                            </form>
                            <?php if (!empty($traceability_history)): ?>
                                <div class="small" style="max-height: 200px; overflow-y: auto;">
                                    <?php foreach ($traceability_history as $log): ?>
                                        <div class="border-bottom border-white border-opacity-5 py-2">
                                            <div class="text-info fw-bold"><?= date('d/m H:i', strtotime($log['date'])) ?> -
                                                <?= htmlspecialchars($log['action']) ?>
                                            </div>
                                            <div class="text-muted extra-small">par @<?= htmlspecialchars($log['username']) ?> -
                                                <?= htmlspecialchars($log['details']) ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Performance Tech -->
                <div class="stat-card-super">
                    <h6 class="text-white mb-3">Performance Techniciens (Top 10)</h6>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover mb-0 small">
                            <thead>
                                <tr>
                                    <th>Technicien</th>
                                    <th class="text-center">Tickets</th>
                                    <th class="text-center">Terminés</th>
                                    <th class="text-end">Taux Succès</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_technicians as $tech):
                                    $rate = $tech['repairs_count'] > 0 ? round(($tech['completed_count'] / $tech['repairs_count']) * 100, 1) : 0;
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($tech['fullname']) ?></td>
                                        <td class="text-center"><?= $tech['repairs_count'] ?></td>
                                        <td class="text-center text-success fw-bold"><?= $tech['completed_count'] ?></td>
                                        <td class="text-end text-info"><?= $rate ?>%</td>
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
</body>

</html>