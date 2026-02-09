<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

require_once '../config/db.php';
$pageTitle = "Gestion du Stock";

// Handle Stock Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_stock') {
    $prod_id = $_POST['product_id'];
    $new_qty = (int) $_POST['quantity'];
    $type = $_POST['type']; // 'add' or 'set'

    try {
        if ($type == 'add') {
            $stmt = $pdo->prepare("UPDATE stock SET quantity = quantity + ?, last_update = NOW() WHERE id_product = ?");
            $stmt->execute([$new_qty, $prod_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE stock SET quantity = ?, last_update = NOW() WHERE id_product = ?");
            $stmt->execute([$new_qty, $prod_id]);
        }
        $success = "Stock mis à jour.";
    } catch (PDOException $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

// Calculations for Stock Summary
$total_products = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$low_stock_count = $pdo->query("SELECT COUNT(*) FROM stock WHERE quantity > 0 AND quantity < 10")->fetchColumn();
$out_of_stock_count = $pdo->query("SELECT COUNT(*) FROM stock WHERE quantity = 0")->fetchColumn();

// Fetch Stock Data
$sql = "SELECT p.name, c.name as cat_name, s.quantity, s.last_update, p.id_product, p.image
        FROM products p 
        LEFT JOIN categories c ON p.id_category = c.id_category 
        JOIN stock s ON p.id_product = s.id_product 
        ORDER BY s.quantity ASC";
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

        .product-thumb {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            object-fit: cover;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .table-dark {
            --bs-table-bg: transparent;
        }

        .table thead th {
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            font-weight: 700;
            color: #94a3b8;
            padding: 20px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
        }

        .table tbody td {
            padding: 16px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
            vertical-align: middle;
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        .table-hover tbody tr:hover {
            background: rgba(255, 255, 255, 0.02);
        }

        .badge-stock {
            padding: 8px 16px;
            font-weight: 600;
            font-size: 0.85rem;
        }
    </style>
</head>

<body>

    <div class="wrapper">
        <?php include '../includes/sidebar.php'; ?>

        <div id="content">
            <?php include '../includes/header.php'; ?>

            <?php if (isset($success)): ?>
                <div class="alert alert-success mt-3">
                    <?= $success ?>
                </div>
            <?php endif; ?>

            <div class="fade-in mt-4">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <h3 class="text-white fw-bold mb-0">Inventaire & Stocks</h3>
                    <div class="text-muted extra-small">Dernière mise à jour: <?= date('d/m/Y H:i') ?></div>
                </div>

                <!-- Stock Summary Bar -->
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
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-dark table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Produit</th>
                                        <th>Catégorie</th>
                                        <th class="text-center">Quantité</th>
                                        <th>Dernière MàJ</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stocks as $item): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-3">
                                                    <?php if ($item['image']): ?>
                                                        <img src="../<?= $item['image'] ?>" class="product-thumb">
                                                    <?php else: ?>
                                                        <div
                                                            class="product-thumb d-flex align-items-center justify-content-center">
                                                            <i class="fa-solid fa-cube text-muted opacity-25"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <div class="text-white fw-bold">
                                                            <?= htmlspecialchars($item['name']) ?></div>
                                                        <div class="extra-small text-muted">ID:
                                                            #<?= str_pad($item['id_product'], 4, '0', STR_PAD_LEFT) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span
                                                    class="badge bg-white bg-opacity-5 text-muted fw-normal border border-white border-opacity-10 px-3 py-2 rounded-pill">
                                                    <?= htmlspecialchars($item['cat_name'] ?? 'Non classé') ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <?php
                                                $qty = $item['quantity'];
                                                $statusClass = $qty == 0 ? 'bg-danger' : ($qty < 10 ? 'bg-warning text-dark' : 'bg-success');
                                                ?>
                                                <span class="badge <?= $statusClass ?> badge-stock rounded-pill">
                                                    <?= $qty ?> <span class="extra-small opacity-75 ms-1">unités</span>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="text-muted small">
                                                    <i class="fa-regular fa-clock me-1 opacity-50"></i>
                                                    <?= date('d/m/Y', strtotime($item['last_update'])) ?>
                                                    <div class="extra-small opacity-50">
                                                        <?= date('H:i', strtotime($item['last_update'])) ?></div>
                                                </div>
                                            </td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-premium px-3"
                                                    onclick="openStockModal(<?= $item['id_product'] ?>, '<?= addslashes($item['name']) ?>')">
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
    </div>

    <!-- Single Stock Modal -->
    <div class="modal fade" id="stockModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0"
                style="background: rgba(15, 23, 42, 0.95); backdrop-filter: blur(20px); border-radius: 24px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);">
                <div class="modal-header border-bottom border-white border-opacity-10 p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary"
                            style="width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                            <i class="fa-solid fa-sliders"></i>
                        </div>
                        <h5 class="modal-title text-white fw-bold" id="modalTitle">Ajustement du Stock</h5>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="stock.php" method="POST">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="update_stock">
                        <input type="hidden" name="product_id" id="modalProdId">

                        <div class="mb-4">
                            <label class="form-label text-muted extra-small text-uppercase fw-bold mb-2">Méthode
                                d'Ajustement</label>
                            <select name="type"
                                class="form-select bg-dark text-white border-white border-opacity-20 py-2"
                                style="border-radius: 12px;">
                                <option value="add">Ajouter (Nouvelle Livraison)</option>
                                <option value="set">Remplacer (Correction Inventaire)</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label
                                class="form-label text-muted extra-small text-uppercase fw-bold mb-2">Quantité</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-white border-opacity-20 text-muted"><i
                                        class="fa-solid fa-layer-group"></i></span>
                                <input type="number" name="quantity"
                                    class="form-control bg-dark text-white border-white border-opacity-20 py-2" required
                                    min="0" placeholder="0.00">
                            </div>
                            <div class="form-text text-muted extra-small mt-2">Saisissez la valeur numérique positive.
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top border-white border-opacity-10 p-4">
                        <button type="button" class="btn btn-outline-secondary border-0 text-white px-4"
                            data-bs-dismiss="modal">Fermer</button>
                        <button type="submit" class="btn btn-premium px-5 py-2 fw-bold text-uppercase">
                            <i class="fa-solid fa-check me-2"></i>Mettre à jour
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
    <script>
        function openStockModal(id, name) {
            document.getElementById('modalProdId').value = id;
            document.getElementById('modalTitle').innerText = 'Stock : ' + name;
            var myModal = new bootstrap.Modal(document.getElementById('stockModal'));
            myModal.show();
        }
    </script>
</body>

</html>