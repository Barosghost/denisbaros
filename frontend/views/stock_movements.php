<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

require_once '../../backend/config/db.php';
require_once '../../backend/config/loyalty_config.php';
$pageTitle = "Mouvements de Stock";

// Filters
$filter_product = $_GET['product'] ?? '';
$filter_type = $_GET['type'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';

// Build query
$sql = "SELECT sm.*, p.name as product_name, u.username 
        FROM stock_movements sm
        JOIN products p ON sm.id_product = p.id_product
        JOIN users u ON sm.id_user = u.id_user
        WHERE 1=1";

$params = [];

if ($filter_product) {
    $sql .= " AND sm.id_product = ?";
    $params[] = $filter_product;
}

if ($filter_type) {
    $sql .= " AND sm.movement_type = ?";
    $params[] = $filter_type;
}

if ($filter_date_from) {
    $sql .= " AND DATE(sm.created_at) >= ?";
    $params[] = $filter_date_from;
}

if ($filter_date_to) {
    $sql .= " AND DATE(sm.created_at) <= ?";
    $params[] = $filter_date_to;
}

$sql .= " ORDER BY sm.created_at DESC LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$movements = $stmt->fetchAll();

// Summary Stats (30 days)
$stats_in = $pdo->query("SELECT SUM(quantity) FROM stock_movements WHERE movement_type = 'IN' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn() ?: 0;
$stats_out = $pdo->query("SELECT SUM(quantity) FROM stock_movements WHERE movement_type = 'OUT' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn() ?: 0;
$stats_low = $pdo->query("SELECT COUNT(*) FROM stock WHERE quantity < 5")->fetchColumn();

// Get all products for filter
$products = $pdo->query("SELECT id_product, name FROM products WHERE status = 'actif' ORDER BY name")->fetchAll();

// Get rotation report
$rotation = $pdo->query("SELECT * FROM stock_rotation_report ORDER BY total_out_30days DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mouvements Stock | DENIS FBI STORE</title>
    <link href="../assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=1.4">
    <style>
        .rotation-badge {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }

        .bg-forte {
            background: #10b981;
            box-shadow: 0 0 10px #10b981;
        }

        .bg-moyenne {
            background: #3b82f6;
            box-shadow: 0 0 10px #3b82f6;
        }

        .bg-faible {
            background: #f59e0b;
            box-shadow: 0 0 10px #f59e0b;
        }

        .bg-rupture {
            background: #ef4444;
            box-shadow: 0 0 10px #ef4444;
        }

        .bg-inactif {
            background: #6b7280;
            box-shadow: 0 0 10px #6b7280;
        }

        .movement-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }

        .filter-card {
            border-left: 4px solid var(--primary-color);
        }

        .summary-card-small {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 1rem;
            transition: all 0.3s ease;
        }

        .summary-card-small:hover {
            background: rgba(255, 255, 255, 0.05);
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include '../../backend/includes/sidebar.php'; ?>

        <div id="content">
            <?php include '../../backend/includes/header.php'; ?>

            <div class="fade-in mt-4">

                <!-- Impact Stats -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="summary-card-small d-flex align-items-center">
                            <div class="movement-icon bg-success bg-opacity-10 text-success me-3">
                                <i class="fa-solid fa-arrow-down"></i>
                            </div>
                            <div>
                                <div class="text-muted small">Entrées (30j)</div>
                                <div class="h5 text-white mb-0">+<?= number_format($stats_in, 0, ',', ' ') ?> unités
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="summary-card-small d-flex align-items-center">
                            <div class="movement-icon bg-danger bg-opacity-10 text-danger me-3">
                                <i class="fa-solid fa-arrow-up"></i>
                            </div>
                            <div>
                                <div class="text-muted small">Sorties (30j)</div>
                                <div class="h5 text-white mb-0">-<?= number_format($stats_out, 0, ',', ' ') ?> unités
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="summary-card-small d-flex align-items-center">
                            <div class="movement-icon bg-warning bg-opacity-10 text-warning me-3">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                            </div>
                            <div>
                                <div class="text-muted small">Alertes Stock</div>
                                <div class="h5 text-white mb-0"><?= $stats_low ?> articles critiques</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card bg-dark border-0 glass-panel mb-4 filter-card">
                    <div class="card-body">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label text-muted small fw-bold text-uppercase">Produit</label>
                                <select name="product" class="form-select bg-dark text-white border-secondary">
                                    <option value="">Tous les produits</option>
                                    <?php foreach ($products as $p): ?>
                                        <option value="<?= $p['id_product'] ?>" <?= $filter_product == $p['id_product'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($p['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label text-muted small fw-bold text-uppercase">Type</label>
                                <select name="type" class="form-select bg-dark text-white border-secondary">
                                    <option value="">Tout type</option>
                                    <option value="IN" <?= $filter_type == 'IN' ? 'selected' : '' ?>>Entrée</option>
                                    <option value="OUT" <?= $filter_type == 'OUT' ? 'selected' : '' ?>>Sortie</option>
                                    <option value="ADJUST" <?= $filter_type == 'ADJUST' ? 'selected' : '' ?>>Ajustement
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label text-muted small fw-bold text-uppercase">Période du</label>
                                <input type="date" name="date_from" value="<?= $filter_date_from ?>"
                                    class="form-control bg-dark text-white border-secondary">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label text-muted small fw-bold text-uppercase">Au</label>
                                <input type="date" name="date_to" value="<?= $filter_date_to ?>"
                                    class="form-control bg-dark text-white border-secondary">
                            </div>
                            <div class="col-md-3 text-end">
                                <div class="btn-group w-100">
                                    <button type="submit" class="btn btn-premium flex-grow-1"><i
                                            class="fa-solid fa-magnifying-glass me-2"></i>Analyser</button>
                                    <a href="stock_movements.php" class="btn btn-outline-light"><i
                                            class="fa-solid fa-rotate-left"></i></a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- History Column -->
                    <div class="col-lg-8">
                        <div class="card bg-dark border-0 glass-panel h-100">
                            <div
                                class="card-header bg-transparent d-flex justify-content-between align-items-center py-3">
                                <h6 class="text-white mb-0"><i class="fa-solid fa-list-ul me-2 text-primary"></i>Flux de
                                    Stock Recent</h6>
                                <div class="btn-group">
                                    <button onclick="exportToExcel()" class="btn btn-sm btn-outline-success border-0"><i
                                            class="fa-solid fa-file-csv me-1"></i>Excel</button>
                                    <button onclick="exportToPDF()" class="btn btn-sm btn-outline-danger border-0"><i
                                            class="fa-solid fa-file-pdf me-1"></i>PDF</button>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-dark table-hover align-middle mb-0">
                                        <thead>
                                            <tr class="text-muted small border-bottom border-secondary">
                                                <th class="px-4">Produit / Raison</th>
                                                <th class="text-center">Mouvement</th>
                                                <th class="text-center">Stock</th>
                                                <th class="text-end px-4">Utilisateur / Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($movements as $m):
                                                $isOut = $m['movement_type'] === 'OUT';
                                                $isIn = $m['movement_type'] === 'IN';
                                                $color = $isIn ? 'success' : ($isOut ? 'danger' : 'warning');
                                                $icon = $isIn ? 'arrow-down' : ($isOut ? 'arrow-up' : 'edit');
                                                ?>
                                                <tr>
                                                    <td class="px-4">
                                                        <div class="text-white fw-bold">
                                                            <?= htmlspecialchars($m['product_name']) ?></div>
                                                        <div class="small text-muted italic">
                                                            <?= htmlspecialchars($m['reason']) ?></div>
                                                    </td>
                                                    <td class="text-center">
                                                        <span
                                                            class="badge bg-<?= $color ?> bg-opacity-10 text-<?= $color ?> border border-<?= $color ?> border-opacity-25 rounded-pill px-3 py-1">
                                                            <i
                                                                class="fa-solid fa-<?= $icon ?> me-1 small"></i><?= $m['quantity'] ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <div class="small text-muted line-through"><?= $m['previous_qty'] ?>
                                                        </div>
                                                        <div class="fw-bold text-info"><?= $m['new_qty'] ?></div>
                                                    </td>
                                                    <td class="text-end px-4">
                                                        <div class="text-white small">
                                                            <?= htmlspecialchars($m['username']) ?></div>
                                                        <div class="text-muted extra-small">
                                                            <?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Rotation Status Column -->
                    <div class="col-lg-4">
                        <div class="card bg-dark border-0 glass-panel h-100">
                            <div class="card-header bg-transparent py-3">
                                <h6 class="text-white mb-0"><i
                                        class="fa-solid fa-gauge-high me-2 text-warning"></i>Santé de la Rotation</h6>
                            </div>
                            <div class="card-body p-0">
                                <?php foreach ($rotation as $index => $r):
                                    if ($index >= 12)
                                        break; // Top 12 products
                                    $statusMap = [
                                        'FORTE_ROTATION' => ['label' => 'Forte', 'class' => 'forte'],
                                        'ROTATION_MOYENNE' => ['label' => 'Moyenne', 'class' => 'moyenne'],
                                        'FAIBLE_ROTATION' => ['label' => 'Faible', 'class' => 'faible'],
                                        'RUPTURE' => ['label' => 'Rupture', 'class' => 'rupture'],
                                        'INACTIF' => ['label' => 'Inactif', 'class' => 'inactif']
                                    ];
                                    $st = $statusMap[$r['rotation_status']];
                                    ?>
                                    <div
                                        class="p-3 border-bottom border-secondary border-opacity-10 d-flex justify-content-between align-items-center">
                                        <div class="flex-grow-1">
                                            <div class="text-white small fw-bold">
                                                <?= htmlspecialchars($r['product_name']) ?></div>
                                            <div class="d-flex align-items-center">
                                                <span class="rotation-badge bg-<?= $st['class'] ?>"></span>
                                                <span
                                                    class="extra-small text-muted text-uppercase"><?= $st['label'] ?></span>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <div class="text-white small fw-bold"><?= $r['total_out_30days'] ?> vendus</div>
                                            <div class="progress-micro bg-secondary bg-opacity-20" style="width: 60px">
                                                <div class="progress-bar bg-<?= $st['class'] ?>"
                                                    style="width: <?= min(100, $r['total_out_30days'] * 5) ?>%"></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <div class="p-3 text-center">
                                    <button
                                        class="btn btn-sm btn-outline-light w-100 extra-small text-uppercase fw-bold"
                                        data-bs-toggle="modal" data-bs-target="#rotationModal">Voir tout le
                                        rapport</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Rotation Modal Full -->
    <div class="modal fade" id="rotationModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content bg-dark text-white glass-panel">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title">Rapport Complet de Rotation</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="table-responsive">
                        <table class="table table-dark table-hover mb-0">
                            <thead>
                                <tr class="text-muted small">
                                    <th class="px-4">Produit</th>
                                    <th>Catégorie</th>
                                    <th class="text-center">Stock Actuel</th>
                                    <th class="text-center">Sorties 30j</th>
                                    <th class="text-center">Rotation</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rotation as $r):
                                    $st = $statusMap[$r['rotation_status']];
                                    ?>
                                    <tr>
                                        <td class="px-4 text-white fw-bold"><?= htmlspecialchars($r['product_name']) ?></td>
                                        <td class="text-muted"><?= htmlspecialchars($r['category_name'] ?? '-') ?></td>
                                        <td class="text-center">
                                            <span
                                                class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25"><?= $r['current_stock'] ?></span>
                                        </td>
                                        <td class="text-center text-danger fw-bold"><?= $r['total_out_30days'] ?></td>
                                        <td>
                                            <span
                                                class="badge bg-<?= $st['class'] ?> bg-opacity-10 text-<?= $st['class'] ?> border border-<?= $st['class'] ?> border-opacity-25">
                                                <?= $st['label'] ?>
                                            </span>
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
    <script>
        function exportToExcel() {
            const movements = <?= json_encode($movements) ?>;
            if (movements.length === 0) return;
            let csv = "\ufeffDate,Produit,Type,Quantité,Stock Avant,Stock Après,Raison,Utilisateur\n";
            movements.forEach(m => {
                const type = m.movement_type === 'IN' ? 'Entrée' : (m.movement_type === 'OUT' ? 'Sortie' : 'Ajustement');
                csv += `"${m.created_at}","${m.product_name}","${type}",${m.quantity},${m.previous_qty},${m.new_qty},"${m.reason}","${m.username}"\n`;
            });
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'mouvements_stock_' + new Date().toISOString().split('T')[0] + '.csv';
            link.click();
        }

        function exportToPDF() {
            const movements = <?= json_encode($movements) ?>;
            if (movements.length === 0) return;
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('l', 'mm', 'a4');
            doc.setFontSize(18); doc.text('Historique des Mouvements de Stock', 14, 15);
            doc.setFontSize(10); doc.text('DENIS FBI STORE - Rapport Automatisé', 14, 22);
            doc.autoTable({
                head: [['Date', 'Produit', 'Type', 'Qté', 'Avant', 'Après', 'Raison', 'Utilisateur']],
                body: movements.map(m => [m.created_at, m.product_name, m.movement_type, m.quantity, m.previous_qty, m.new_qty, m.reason, m.username]),
                startY: 28, styles: { fontSize: 8 }, headStyles: { fillColor: [15, 23, 42] }
            });
            doc.save('stock_report_' + new Date().toISOString().split('T')[0] + '.pdf');
        }
    </script>
</body>

</html>
