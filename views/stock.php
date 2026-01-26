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

// Fetch Stock Data
$sql = "SELECT p.name, c.name as cat_name, s.quantity, s.last_update, p.id_product 
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
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- Custom CSS -->
</head>
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
                <h5 class="text-white mb-4">État des Stocks</h5>

                <div class="card bg-dark border-0 glass-panel">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-dark table-hover mb-0 align-middle">
                                <thead class="bg-transparent border-bottom border-secondary">
                                    <tr>
                                        <th class="py-3 px-4">Produit</th>
                                        <th class="py-3">Catégorie</th>
                                        <th class="py-3 text-center">Quantité Actuelle</th>
                                        <th class="py-3">Dernière MàJ</th>
                                        <th class="py-3 text-end px-4">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stocks as $item): ?>
                                        <tr>
                                            <td class="px-4 fw-bold">
                                                <?= htmlspecialchars($item['name']) ?>
                                            </td>
                                            <td class="text-muted">
                                                <?= htmlspecialchars($item['cat_name'] ?? '-') ?>
                                            </td>
                                            <td class="text-center">
                                                <?php
                                                $qty = $item['quantity'];
                                                $badge = $qty == 0 ? 'bg-danger' : ($qty < 10 ? 'bg-warning' : 'bg-success');
                                                ?>
                                                <span class="badge <?= $badge ?> rounded-pill px-3">
                                                    <?= $qty ?>
                                                </span>
                                            </td>
                                            <td class="text-muted small">
                                                <?= date('d/m/Y H:i', strtotime($item['last_update'])) ?>
                                            </td>
                                            <td class="text-end px-4">
                                                <button class="btn btn-sm btn-outline-primary"
                                                    onclick="openStockModal(<?= $item['id_product'] ?>, '<?= addslashes($item['name']) ?>')">
                                                    <i class="fa-solid fa-pen-to-square"></i> Modifier
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
            <div class="modal-content bg-dark text-white glass-panel border-0">
                <div class="modal-header border-bottom border-secondary">
                    <h5 class="modal-title" id="modalTitle">Stock</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="stock.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_stock">
                        <input type="hidden" name="product_id" id="modalProdId">

                        <div class="mb-3">
                            <label class="form-label text-muted">Opération</label>
                            <select name="type" class="form-select bg-dark text-white border-secondary">
                                <option value="add">Ajouter (Entrée de stock)</option>
                                <option value="set">Définir (Correction d'inventaire)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted">Quantité</label>
                            <input type="number" name="quantity"
                                class="form-control bg-dark text-white border-secondary" required min="0">
                        </div>
                    </div>
                    <div class="modal-footer border-top border-secondary">
                        <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-premium">Valider</button>
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
