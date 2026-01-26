<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

require_once '../config/db.php';
$pageTitle = "Rapports & Statistiques";

// Defaults: Current Month
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Fetch Sales in Range
$query = "SELECT s.*, u.username, c.fullname as client_name 
          FROM sales s 
          JOIN users u ON s.id_user = u.id_user 
          LEFT JOIN clients c ON s.id_client = c.id_client 
          WHERE DATE(s.sale_date) BETWEEN ? AND ? 
          ORDER BY s.sale_date DESC";
$stmt = $pdo->prepare($query);
$stmt->execute([$start_date, $end_date]);
$sales = $stmt->fetchAll();

// Calculate Total
$total_revenue = 0;
foreach ($sales as $sale) {
    $total_revenue += $sale['total_amount'];
}
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
    <!-- Custom CSS applied via style.css -->
</head>

<body>

    <div class="wrapper">
        <?php include '../includes/sidebar.php'; ?>

        <div id="content">
            <?php include '../includes/header.php'; ?>

            <div class="fade-in mt-4">

                <!-- Filter Bar -->
                <div class="card bg-dark border-0 glass-panel mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label text-muted">Date Début</label>
                                <input type="date" name="start_date"
                                    class="form-control bg-dark text-white border-secondary" value="<?= $start_date ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-muted">Date Fin</label>
                                <input type="date" name="end_date"
                                    class="form-control bg-dark text-white border-secondary" value="<?= $end_date ?>">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100"><i
                                        class="fa-solid fa-filter me-2"></i> Filtrer</button>
                            </div>
                            <div class="col-md-4 text-end">
                                <button type="button" class="btn btn-outline-light" onclick="window.print()"><i
                                        class="fa-solid fa-print me-2"></i> Imprimer</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Summary -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div
                            class="card bg-primary bg-opacity-10 border-primary border-opacity-25 glass-panel text-center p-4">
                            <h5 class="text-primary text-uppercase mb-2">Chiffre d'Affaires (Période)</h5>
                            <h1 class="display-4 fw-bold text-white mb-0">
                                <?= number_format($total_revenue, 0, ',', ' ') ?> <span class="fs-4">FCFA</span>
                            </h1>
                        </div>
                    </div>
                </div>

                <!-- Sales Table -->
                <div class="card bg-dark border-0 glass-panel">
                    <div class="card-header border-bottom border-secondary bg-transparent">
                        <h5 class="mb-0 text-white">Détail des Ventes</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-dark table-hover mb-0 align-middle text-center">
                                <thead class="bg-transparent border-bottom border-secondary">
                                    <tr>
                                        <!-- ID Removed -->
                                        <th class="py-3">Date</th>
                                        <th class="py-3">Client</th>
                                        <th class="py-3">Vendeur</th>
                                        <th class="py-3">Montant</th>
                                        <th class="py-3">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($sales)): ?>
                                        <tr>
                                            <td colspan="5" class="text-muted py-4">Aucune vente sur cette période.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($sales as $sale): ?>
                                            <tr>
                                                <!-- ID Removed -->
                                                <td><?= date('d/m/Y H:i', strtotime($sale['sale_date'])) ?></td>
                                                <td><?= htmlspecialchars($sale['client_name'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars($sale['username']) ?></td>
                                                <td class="fw-bold text-success">
                                                    <?= number_format($sale['total_amount'], 0, ',', ' ') ?> FCFA
                                                </td>
                                                <td>
                                                    <a href="invoice.php?id=<?= $sale['id_sale'] ?>" target="_blank"
                                                        class="btn btn-sm btn-outline-light">
                                                        <i class="fa-solid fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
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
