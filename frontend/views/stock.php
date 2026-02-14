<?php
define('PAGE_ACCESS', 'stock');
require_once '../../backend/includes/auth_required.php';
require_once '../../backend/config/db.php';
require_once '../../backend/config/functions.php';
require_once '../../backend/config/settings.php';

$pageTitle = "Gestion du Stock";

// Handle Stock Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_stock') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die("Erreur de sécurité (CSRF). Veuillez actualiser la page.");
    }

    if (getSystemSetting('allow_manual_adjustment', '1', $pdo) === '0') {
        $error = "Les ajustements manuels de stock sont désactivés dans les paramètres.";
    } else {
        $user_id = $_SESSION['user_id'] ?? 1;
        $prod_id = $_POST['product_id'];
        $qty_input = (int) $_POST['quantity'];
        $type = $_POST['type']; // 'add', 'remove', 'set'
        $motif = trim($_POST['motif'] ?? 'Ajustement manuel');

        if (getSystemSetting('mandatory_adjustment_reason', '0', $pdo) === '1' && $motif === '') {
            $error = "Le motif de l'ajustement est obligatoire.";
        } else {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("SELECT stock_actuel FROM produits WHERE id_produit = ? FOR UPDATE");
                $stmt->execute([$prod_id]);
                $current_qty = $stmt->fetchColumn();

                if ($current_qty === false)
                    throw new Exception("Produit introuvable.");

                $new_qty = $current_qty;
                $mouv_type = 'ajustement_manuel';

                if ($type == 'add') {
                    $new_qty = $current_qty + $qty_input;
                    $mouv_type = 'entree';
                } elseif ($type == 'remove') {
                    $new_qty = $current_qty - $qty_input;
                    $mouv_type = 'ajustement_manuel';
                } elseif ($type == 'set') {
                    $new_qty = $qty_input;
                    $mouv_type = 'ajustement_manuel';
                }

                if (getSystemSetting('block_negative_stock', '1', $pdo) === '1' && $new_qty < 0) {
                    throw new Exception("Stock négatif interdit. Paramètre 'Bloquer stock négatif' activé.");
                }

                // Update product
                $stmt = $pdo->prepare("UPDATE produits SET stock_actuel = ? WHERE id_produit = ?");
                $stmt->execute([$new_qty, $prod_id]);

                $delta = abs($new_qty - $current_qty);
                if ($delta > 0 || $type == 'set') {
                    $stmt = $pdo->prepare("INSERT INTO mouvements_stock (id_produit, id_user, type_mouvement, quantite_avant, quantite_apres, motif_ajustement) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$prod_id, $user_id, $mouv_type, $current_qty, $new_qty, $motif]);
                }

                $pdo->commit();
                $success = "Stock mis à jour avec succès. Nouveau stock : $new_qty";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Erreur : " . $e->getMessage();
            }
        }
    }
}

// Calculations for Stock Summary
$total_products = $pdo->query("SELECT COUNT(*) FROM produits")->fetchColumn();
$low_stock_count = $pdo->query("SELECT COUNT(*) FROM produits WHERE stock_actuel <= seuil_alerte")->fetchColumn();
$out_of_stock_count = $pdo->query("SELECT COUNT(*) FROM produits WHERE stock_actuel = 0")->fetchColumn();

// Fetch Stock Data
// We want to see last movement date too.
$sql = "
    SELECT p.*, 
           (SELECT date_mouvement FROM mouvements_stock WHERE id_produit = p.id_produit ORDER BY date_mouvement DESC LIMIT 1) as last_update
    FROM produits p 
    ORDER BY p.stock_actuel ASC
";
$stocks = $pdo->query($sql)->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock | DENIS FBI STORE</title>
    <link href="../assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=1.4">
    <script src="../assets/vendor/sweetalert2/sweetalert2.all.min.js"></script>
    <style>
        .stock-stat-card {
            background: rgba(30, 41, 59, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 24px;
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
            backdrop-filter: blur(10px);
        }

        .stock-stat-card:hover {
            background: rgba(30, 41, 59, 0.6);
            transform: translateY(-5px);
            border-color: rgba(255, 255, 255, 0.1);
        }

        .stat-icon-large {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .inventory-card {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .table-dark {
            --bs-table-bg: transparent;
        }

        .badge-stock {
            padding: 8px 16px;
            font-weight: 600;
            font-size: 0.85rem;
        }

        /* Adjustment Modal Styles */
        .adjustment-type-selector {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }

        .type-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 16px 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .type-card:hover {
            background: rgba(255, 255, 255, 0.06);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .type-card.active {
            background: rgba(59, 130, 246, 0.15);
            border-color: #3b82f6;
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.3);
        }

        .type-card i {
            font-size: 1.25rem;
            margin-bottom: 8px;
            display: block;
        }

        .type-card span {
            font-size: 0.75rem;
            font-weight: 600;
            text-uppercase;
            letter-spacing: 0.5px;
            display: block;
        }

        .preview-box {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 12px;
            padding: 15px;
            margin-top: 20px;
            border-left: 4px solid #3b82f6;
        }

        .preview-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }

        .preview-item:last-child {
            margin-bottom: 0;
            padding-top: 5px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }
    </style>
</head>

<body>

    <div class="wrapper">
        <?php include '../../backend/includes/sidebar.php'; ?>

        <div id="content">
            <?php include '../../backend/includes/header.php'; ?>

            <?php if (isset($success)): ?>
                <div class="alert alert-success mt-3"><?= $success ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger mt-3"><?= $error ?></div>
            <?php endif; ?>

            <div class="fade-in mt-4">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <h3 class="text-white fw-bold mb-0">Inventaire & Stocks</h3>
                    <div class="text-muted extra-small">Dernière mise à jour: <?= date('d/m/Y H:i') ?></div>
                </div>

                <!-- Stock Summary -->
                <div class="row g-4 mb-5">
                    <div class="col-xl-4 col-md-6">
                        <div class="stock-stat-card">
                            <div class="stat-icon-large bg-primary bg-opacity-10 text-primary">
                                <i class="fa-solid fa-boxes-stacked"></i>
                            </div>
                            <div>
                                <div class="text-muted small text-uppercase fw-bold mb-1">Total Articles</div>
                                <div class="text-white h3 fw-bold mb-0"><?= $total_products ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-md-6">
                        <div class="stock-stat-card">
                            <div class="stat-icon-large bg-warning bg-opacity-10 text-warning">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                            </div>
                            <div>
                                <div class="text-muted small text-uppercase fw-bold mb-1">Stock Faible</div>
                                <div class="text-white h3 fw-bold mb-0"><?= $low_stock_count ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-md-6">
                        <div class="stock-stat-card">
                            <div class="stat-icon-large bg-danger bg-opacity-10 text-danger">
                                <i class="fa-solid fa-circle-xmark"></i>
                            </div>
                            <div>
                                <div class="text-muted small text-uppercase fw-bold mb-1">Rupture</div>
                                <div class="text-white h3 fw-bold mb-0"><?= $out_of_stock_count ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="inventory-card">
                    <div class="table-responsive">
                        <table class="table table-dark table-hover mb-0 align-middle">
                            <thead>
                                <tr class="text-white-50 extra-small text-uppercase fw-bold">
                                    <th class="px-4 py-3">Produit</th>
                                    <th>Catégorie</th>
                                    <th class="text-center">Quantité</th>
                                    <th>Dernière Mvt</th>
                                    <th class="text-end px-4">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stocks as $item): ?>
                                    <tr>
                                        <td class="px-4">
                                            <div class="text-white fw-bold"><?= htmlspecialchars($item['designation']) ?>
                                            </div>
                                            <div class="extra-small text-muted">ID:
                                                #<?= str_pad($item['id_produit'], 4, '0', STR_PAD_LEFT) ?></div>
                                        </td>
                                        <td>
                                            <span
                                                class="badge bg-white bg-opacity-5 text-muted fw-normal border border-white border-opacity-10 px-3 py-2 rounded-pill">
                                                <?= htmlspecialchars($item['categorie'] ?? 'Non classé') ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?php
                                            $qty = $item['stock_actuel'];
                                            $alert = $item['seuil_alerte'];
                                            $statusClass = $qty == 0 ? 'bg-danger' : ($qty <= $alert ? 'bg-warning text-dark' : 'bg-success');
                                            ?>
                                            <span class="badge <?= $statusClass ?> badge-stock rounded-pill">
                                                <?= $qty ?> <span class="extra-small opacity-75 ms-1">unités</span>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="text-muted small">
                                                <?php if ($item['last_update']): ?>
                                                    <i class="fa-regular fa-clock me-1 opacity-50"></i>
                                                    <?= date('d/m/Y H:i', strtotime($item['last_update'])) ?>
                                                <?php else: ?>
                                                    <span class="opacity-50">-</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="text-end px-4">
                                            <button class="btn btn-sm btn-premium px-3"
                                                onclick="openStockModal(<?= $item['id_produit'] ?>, '<?= addslashes($item['designation']) ?>', <?= $item['stock_actuel'] ?>)">
                                                <i class="fa-solid fa-plus-minus me-1"></i> Ajuster
                                            </button>
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

    <!-- Stock Modal -->
    <div class="modal fade" id="stockModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark border border-white border-opacity-10 text-white rounded-4 shadow-lg">
                <form method="POST">
                    <div class="modal-header border-bottom border-white border-opacity-10">
                        <h5 class="modal-title fw-bold" id="modalTitle">Ajustement Stock</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <?= getCsrfInput() ?>
                        <input type="hidden" name="action" value="update_stock">
                        <input type="hidden" name="product_id" id="modalProdId">

                        <div class="mb-4">
                            <label class="form-label small text-muted text-uppercase fw-bold mb-3">Type
                                d'opération</label>
                            <div class="adjustment-type-selector">
                                <div class="type-card active" data-type="add">
                                    <i class="fa-solid fa-circle-plus text-success"></i>
                                    <span>Ajouter</span>
                                </div>
                                <div class="type-card" data-type="remove">
                                    <i class="fa-solid fa-circle-minus text-danger"></i>
                                    <span>Retirer</span>
                                </div>
                                <div class="type-card" data-type="set">
                                    <i class="fa-solid fa-equals text-primary"></i>
                                    <span>Définir</span>
                                </div>
                            </div>
                            <input type="hidden" name="type" id="adjustmentType" value="add">
                        </div>

                        <div class="row g-3">
                            <div class="col-8">
                                <label class="form-label small text-muted text-uppercase fw-bold">Quantité</label>
                                <input type="number" name="quantity" id="adjustmentQty"
                                    class="form-control bg-black bg-opacity-25 text-white border-white border-opacity-10 rounded-3"
                                    required min="1" placeholder="Entrez la quantité">
                            </div>
                            <div class="col-4">
                                <label class="form-label small text-muted text-uppercase fw-bold">Actuel</label>
                                <div class="h4 mb-0 mt-1 text-white-50" id="currentStockDisplay">0</div>
                            </div>
                        </div>

                        <div class="mt-4 mb-3">
                            <label class="form-label small text-muted text-uppercase fw-bold">Motif /
                                Commentaire</label>
                            <input type="text" name="motif"
                                class="form-control bg-black bg-opacity-25 text-white border-white border-opacity-10 rounded-3"
                                placeholder="Livraison, Error, Inventaire...">
                        </div>

                        <div class="preview-box">
                            <div class="preview-item">
                                <span class="small text-muted">Projection Stock</span>
                                <div class="d-flex align-items-center">
                                    <span id="previewCurrent" class="fw-bold">0</span>
                                    <i id="previewArrow" class="fa-solid fa-arrow-right mx-2 small opacity-50"></i>
                                    <span id="previewNew" class="fw-bold text-primary">0</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top-0 pt-0">
                        <button type="button" class="btn btn-outline-light rounded-pill"
                            data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4">Valider</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <script>
        let currentStockVal = 0;

        function openStockModal(id, name, stock) {
            currentStockVal = stock;
            document.getElementById('modalProdId').value = id;
            document.getElementById('modalTitle').innerText = 'Stock : ' + name;
            document.getElementById('currentStockDisplay').innerText = stock;
            document.getElementById('previewCurrent').innerText = stock;
            document.getElementById('previewNew').innerText = stock;
            document.getElementById('adjustmentQty').value = '';

            updatePreview();
            new bootstrap.Modal(document.getElementById('stockModal')).show();
        }

        // Type selection handled by cards
        document.querySelectorAll('.type-card').forEach(card => {
            card.addEventListener('click', function () {
                document.querySelectorAll('.type-card').forEach(c => c.classList.remove('active'));
                this.classList.add('active');
                document.getElementById('adjustmentType').value = this.dataset.type;
                updatePreview();
            });
        });

        // Live Preview calculation
        document.getElementById('adjustmentQty').addEventListener('input', updatePreview);

        function updatePreview() {
            const qty = parseInt(document.getElementById('adjustmentQty').value) || 0;
            const type = document.getElementById('adjustmentType').value;
            let newVal = currentStockVal;

            if (type === 'add') {
                newVal = currentStockVal + qty;
                document.getElementById('previewNew').className = 'fw-bold text-success';
            } else if (type === 'remove') {
                newVal = Math.max(0, currentStockVal - qty);
                document.getElementById('previewNew').className = 'fw-bold text-danger';
            } else if (type === 'set') {
                newVal = qty;
                document.getElementById('previewNew').className = 'fw-bold text-primary';
            }

            document.getElementById('previewNew').innerText = newVal;
        }
    </script>
</body>

</html>