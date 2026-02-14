<?php
define('PAGE_ACCESS', 'stock_movements');
require_once '../../backend/includes/auth_required.php';
$pageTitle = "Mouvements de Stock";

// Filters
$filter_product = $_GET['product'] ?? '';
$filter_type = $_GET['type'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';

// Build query - colonnes réelles: type_mouvement, quantite_avant, quantite_apres, motif_ajustement, date_mouvement
$sql = "SELECT sm.*, sm.motif_ajustement as reason, p.designation as product_name, u.username 
        FROM mouvements_stock sm
        JOIN produits p ON sm.id_produit = p.id_produit
        JOIN utilisateurs u ON sm.id_user = u.id_user
        WHERE 1=1";

$params = [];

if ($filter_product) {
    $sql .= " AND sm.id_produit = ?";
    $params[] = $filter_product;
}

if ($filter_type) {
    if ($filter_type === 'sortie') {
        $sql .= " AND sm.type_mouvement IN ('vente','transfert_sav','retour_fournisseur')";
    } elseif (in_array($filter_type, ['ajustement_manuel', 'entree', 'vente', 'transfert_sav', 'retour_fournisseur'], true)) {
        $sql .= " AND sm.type_mouvement = ?";
        $params[] = $filter_type;
    }
}

if ($filter_date_from) {
    $sql .= " AND DATE(sm.date_mouvement) >= ?";
    $params[] = $filter_date_from;
}

if ($filter_date_to) {
    $sql .= " AND DATE(sm.date_mouvement) <= ?";
    $params[] = $filter_date_to;
}

$sql .= " ORDER BY sm.date_mouvement DESC LIMIT 200";

$movements = [];
$stats_in = 0;
$stats_out = 0;
$stats_low = 0;
$products = [];
$rotation = [];

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $movements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stats_in = (int) ($pdo->query("SELECT COALESCE(SUM(quantite_apres - quantite_avant), 0) FROM mouvements_stock WHERE type_mouvement = 'entree' AND date_mouvement >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn() ?: 0);
    $stats_out = (int) ($pdo->query("SELECT COALESCE(SUM(quantite_avant - quantite_apres), 0) FROM mouvements_stock WHERE type_mouvement IN ('vente','transfert_sav','retour_fournisseur') AND quantite_apres < quantite_avant AND date_mouvement >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn() ?: 0);
    $stats_low = (int) ($pdo->query("SELECT COUNT(*) FROM produits WHERE stock_actuel < 5")->fetchColumn() ?: 0);

    $products = $pdo->query("SELECT id_produit as id_product, designation as name FROM produits ORDER BY designation")->fetchAll(PDO::FETCH_ASSOC);

    $rotation = $pdo->query("SELECT p.designation as product_name, p.stock_actuel as current_stock, 'NORMAL' as rotation_status, 
                        (SELECT COALESCE(SUM(sm.quantite_avant - sm.quantite_apres), 0) FROM mouvements_stock sm WHERE sm.id_produit = p.id_produit AND sm.type_mouvement IN ('vente','transfert_sav') AND sm.date_mouvement >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as total_out_30days
                        FROM produits p ORDER BY p.designation")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // En cas d'erreur (table absente, etc.) les variables restent à [] / 0
}
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
                                    <option value="entree" <?= $filter_type == 'entree' ? 'selected' : '' ?>>Entrée</option>
                                    <option value="sortie" <?= $filter_type == 'sortie' ? 'selected' : '' ?>>Sortie</option>
                                    <option value="ajustement_manuel" <?= $filter_type == 'ajustement_manuel' ? 'selected' : '' ?>>Ajustement</option>
                                    <option value="vente" <?= $filter_type == 'vente' ? 'selected' : '' ?>>Vente</option>
                                    <option value="transfert_sav" <?= $filter_type == 'transfert_sav' ? 'selected' : '' ?>>Transfert SAV</option>
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
                                <div class="d-flex align-items-center gap-3">
                                    <div class="input-group input-group-sm" style="width: 250px;">
                                        <span class="input-group-text bg-dark border-secondary text-muted"><i
                                                class="fa-solid fa-search"></i></span>
                                        <input type="text" id="tableSearch"
                                            class="form-control bg-dark text-white border-secondary"
                                            placeholder="Filtrer la liste...">
                                    </div>
                                    <div class="btn-group">
                                        <button onclick="exportToExcel()"
                                            class="btn btn-sm btn-outline-success border-0"><i
                                                class="fa-solid fa-file-csv me-1"></i>Excel</button>
                                        <button onclick="exportToPDF()"
                                            class="btn btn-sm btn-outline-danger border-0"><i
                                                class="fa-solid fa-file-pdf me-1"></i>PDF</button>
                                    </div>
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
                                                $isIn = ($m['type_mouvement'] === 'entree');
                                                $isOut = in_array($m['type_mouvement'], ['vente','transfert_sav','retour_fournisseur'], true);
                                                $color = $isIn ? 'success' : ($isOut ? 'danger' : 'warning');
                                                $icon = $isIn ? 'arrow-down' : ($isOut ? 'arrow-up' : 'edit');
                                                $qty_delta = (int)abs(($m['quantite_apres'] ?? 0) - ($m['quantite_avant'] ?? 0));
                                                $reason = $m['motif_ajustement'] ?? $m['reason'] ?? '';
                                                ?>
                                                <tr class="movement-row">
                                                    <td class="px-4">
                                                        <div class="text-white fw-bold">
                                                            <?= htmlspecialchars($m['product_name'] ?? '') ?>
                                                        </div>
                                                        <div class="small text-muted italic">
                                                            <?= htmlspecialchars($reason) ?>
                                                        </div>
                                                    </td>
                                                    <td class="text-center">
                                                        <span
                                                            class="badge bg-<?= $color ?> bg-opacity-10 text-<?= $color ?> border border-<?= $color ?> border-opacity-25 rounded-pill px-3 py-1">
                                                            <i class="fa-solid fa-<?= $icon ?> me-1 small"></i><?= $qty_delta ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <div class="text-muted small"><?= (int)($m['quantite_avant'] ?? 0) ?> → <?= (int)($m['quantite_apres'] ?? 0) ?></div>
                                                    </td>
                                                    <td class="text-end px-4">
                                                        <div class="text-white small">
                                                            <?= htmlspecialchars($m['username']) ?>
                                                        </div>
                                                        <div class="text-muted extra-small">
                                                            <?= !empty($m['date_mouvement']) ? date('d/m/Y H:i', strtotime($m['date_mouvement'])) : '-' ?>
                                                        </div>
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
                                <?php
                                $statusMap = [
                                    'NORMAL' => ['label' => 'Normal', 'class' => 'moyenne'],
                                    'FORTE_ROTATION' => ['label' => 'Forte', 'class' => 'forte'],
                                    'ROTATION_MOYENNE' => ['label' => 'Moyenne', 'class' => 'moyenne'],
                                    'FAIBLE_ROTATION' => ['label' => 'Faible', 'class' => 'faible'],
                                    'RUPTURE' => ['label' => 'Rupture', 'class' => 'rupture'],
                                    'INACTIF' => ['label' => 'Inactif', 'class' => 'inactif']
                                ];
                                foreach ($rotation as $index => $r):
                                    if ($index >= 12) break;
                                    $st = $statusMap[$r['rotation_status'] ?? 'NORMAL'] ?? $statusMap['NORMAL'];
                                    ?>
                                    <div
                                        class="p-3 border-bottom border-secondary border-opacity-10 d-flex justify-content-between align-items-center">
                                        <div class="flex-grow-1">
                                            <div class="text-white small fw-bold">
                                                <?= htmlspecialchars($r['product_name']) ?>
                                            </div>
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
                                    $st = $statusMap[$r['rotation_status'] ?? 'NORMAL'] ?? $statusMap['NORMAL'];
                                    ?>
                                    <tr>
                                        <td class="px-4 text-white fw-bold"><?= htmlspecialchars($r['product_name'] ?? '') ?></td>
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
            const typeLabel = (t) => ({ entree: 'Entrée', vente: 'Vente', transfert_sav: 'Transfert SAV', ajustement_manuel: 'Ajustement', retour_fournisseur: 'Retour fournisseur' })[t] || t;
            let csv = "\ufeffDate,Produit,Type,Quantité,Stock Avant,Stock Après,Raison,Utilisateur\n";
            movements.forEach(m => {
                const qty = Math.abs((m.quantite_apres || 0) - (m.quantite_avant || 0));
                csv += `"${m.date_mouvement || ''}","${(m.product_name || '').replace(/"/g, '""')}","${typeLabel(m.type_mouvement)}",${qty},${m.quantite_avant || 0},${m.quantite_apres || 0},"${(m.motif_ajustement || m.reason || '').replace(/"/g, '""')}","${m.username || ''}"\n`;
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
            const typeLabel = (t) => ({ entree: 'Entrée', vente: 'Vente', transfert_sav: 'Transfert SAV', ajustement_manuel: 'Ajustement', retour_fournisseur: 'Retour fourn.' })[t] || t;
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('l', 'mm', 'a4');
            doc.setFontSize(18); doc.text('Historique des Mouvements de Stock', 14, 15);
            doc.setFontSize(10); doc.text('DENIS FBI STORE - Rapport Automatisé', 14, 22);
            doc.autoTable({
                head: [['Date', 'Produit', 'Type', 'Qté', 'Avant', 'Après', 'Raison', 'Utilisateur']],
                body: movements.map(m => [
                    m.date_mouvement || '',
                    m.product_name || '',
                    typeLabel(m.type_mouvement),
                    Math.abs((m.quantite_apres || 0) - (m.quantite_avant || 0)),
                    m.quantite_avant || 0,
                    m.quantite_apres || 0,
                    (m.motif_ajustement || m.reason || '').substring(0, 30),
                    m.username || ''
                ]),
                startY: 28, styles: { fontSize: 8 }, headStyles: { fillColor: [15, 23, 42] }
            });
            doc.save('stock_report_' + new Date().toISOString().split('T')[0] + '.pdf');
        }

        document.getElementById('tableSearch').addEventListener('input', function () {
            const term = this.value.toLowerCase().trim();
            document.querySelectorAll('.movement-row').forEach(row => {
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(term) ? '' : 'none';
            });
        });
    </script>
</body>

</html>