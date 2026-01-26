<?php
session_start();

if (!isset($_SESSION['logged_in'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../config/db.php';
$pageTitle = "Gestion des Produits";

// Handle Add Product
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $name = trim($_POST['name']);
    $desc = trim($_POST['description']);
    $price = $_POST['price'];
    $cat_id = $_POST['category'];
    $initial_stock = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    $status = 'actif';

    try {
        $pdo->beginTransaction();

        // 1. Insert Product
        $stmt = $pdo->prepare("INSERT INTO products (name, description, price, id_category, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $desc, $price, $cat_id, $status]);
        $product_id = $pdo->lastInsertId();

        // 2. Initialize Stock with provided quantity
        $stmt = $pdo->prepare("INSERT INTO stock (id_product, quantity) VALUES (?, ?)");
        $stmt->execute([$product_id, $initial_stock]);

        $pdo->commit();
        $success = "Produit ajouté avec succès.";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Erreur : " . $e->getMessage();
    }
}

// Handle Update Product
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update') {
    $id_product = $_POST['id_product'];
    $name = trim($_POST['name']);
    $desc = trim($_POST['description']);
    $price = $_POST['price'];
    $cat_id = $_POST['category'];

    try {
        $stmt = $pdo->prepare("UPDATE products SET name = ?, description = ?, price = ?, id_category = ? WHERE id_product = ?");
        $stmt->execute([$name, $desc, $price, $cat_id, $id_product]);
        $success = "Produit mis à jour avec succès.";
    } catch (PDOException $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

// Handle Delete (Logical Delete or Real Delete - let's do Real for now as requested, but check constraints)
if (isset($_GET['delete']) && $_SESSION['role'] === 'admin') {
    $id = $_GET['delete'];
    try {
        // Delete stock first (FK constraint)
        $pdo->prepare("DELETE FROM stock WHERE id_product = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM products WHERE id_product = ?")->execute([$id]);
        header("Location: products.php");
        exit();
    } catch (PDOException $e) {
        $error = "Impossible de supprimer ce produit (peut-être lié à des ventes).";
    }
}

// Fetch Products with Category Name and Stock
$sql = "SELECT p.*, c.name as cat_name, s.quantity as store_quantity 
        FROM products p 
        LEFT JOIN categories c ON p.id_category = c.id_category 
        LEFT JOIN stock s ON p.id_product = s.id_product 
        ORDER BY p.created_at DESC";
$products = $pdo->query($sql)->fetchAll();

// Fetch Categories for Modal
$categories = $pdo->query("SELECT * FROM categories")->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produits | DENIS FBI STORE</title>
    <link href="../assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* POS Specifics */
        .product-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.1);
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
            <?php if (isset($error)): ?>
                <div class="alert alert-danger mt-3">
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <div class="fade-in mt-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="text-white">Inventaire Produits</h5>
                    <div>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                            <a href="categories.php" class="btn btn-outline-light me-2">
                                <i class="fa-solid fa-tags me-2"></i> Gérer Catégories
                            </a>
                            <button class="btn btn-premium" data-bs-toggle="modal" data-bs-target="#addProductModal">
                                <i class="fa-solid fa-plus me-2"></i> Nouveau Produit
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card bg-dark border-0 glass-panel">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-dark table-hover mb-0 align-middle">
                                <thead class="bg-transparent border-bottom border-secondary">
                                    <tr>
                                        <th class="py-3 px-4">Produit</th>
                                        <th class="py-3">Catégorie</th>
                                        <th class="py-3">Prix Unitaire</th>
                                        <th class="py-3 text-center">Stock</th>
                                        <th class="py-3 text-center">Status</th>
                                        <?php if ($_SESSION['role'] === 'admin'): ?>
                                            <th class="py-3 text-end px-4">Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($products)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-5">Aucun produit dans
                                                l'inventaire</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($products as $prod): ?>
                                            <tr>
                                                <td class="px-4">
                                                    <div class="d-flex align-items-center">
                                                        <div class="product-icon text-white me-3">
                                                            <i class="fa-solid fa-cube"></i>
                                                        </div>
                                                        <div>
                                                            <div class="fw-bold">
                                                                <?= htmlspecialchars($prod['name']) ?>
                                                            </div>
                                                            <div class="small text-muted text-truncate"
                                                                style="max-width: 150px;">
                                                                <?= htmlspecialchars($prod['description']) ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><span
                                                        class="badge bg-secondary bg-opacity-50 text-light border border-secondary">
                                                        <?= htmlspecialchars($prod['cat_name'] ?? 'Non classé') ?>
                                                    </span></td>
                                                <td class="fw-bold text-success">
                                                    <?= number_format($prod['price'], 0, ',', ' ') ?> FCFA
                                                </td>
                                                <td class="text-center">
                                                    <?php
                                                    $qty = $prod['store_quantity'];
                                                    $color = $qty > 10 ? 'success' : ($qty > 0 ? 'warning' : 'danger');
                                                    ?>
                                                    <span class="badge bg-<?= $color ?> rounded-pill px-3">
                                                        <?= $qty ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($prod['status'] == 'actif'): ?>
                                                        <i class="fa-solid fa-circle-check text-success"></i>
                                                    <?php else: ?>
                                                        <i class="fa-solid fa-circle-xmark text-danger"></i>
                                                    <?php endif; ?>
                                                </td>
                                                <?php if ($_SESSION['role'] === 'admin'): ?>
                                                    <td class="text-end px-4">
                                                        <button class="btn btn-sm btn-outline-primary me-2"
                                                            onclick="openEditProductModal(<?= $prod['id_product'] ?>, '<?= addslashes($prod['name']) ?>', <?= $prod['id_category'] ?>, <?= $prod['price'] ?>, '<?= addslashes(str_replace(array('\r', '\n'), '', $prod['description'])) ?>')">
                                                            <i class="fa-solid fa-pen"></i>
                                                        </button>
                                                        <a href="products.php?delete=<?= $prod['id_product'] ?>"
                                                            class="btn btn-sm btn-outline-danger"
                                                            onclick="return confirmAction(this.href, 'Confirmer la suppression ?');">
                                                            <i class="fa-solid fa-trash"></i>
                                                        </a>
                                                    </td>
                                                <?php endif; ?>
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

    <!-- Add Product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content bg-dark text-white border-0 glass-panel">
                <div class="modal-header border-bottom border-secondary">
                    <h5 class="modal-title">Nouveau Produit</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="products.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-white">Nom du produit</label>
                                <input type="text" name="name" class="form-control bg-dark text-white border-secondary"
                                    placeholder="Ex: Ordinateur Portable" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-white">Catégorie</label>
                                <select name="category" class="form-select bg-dark text-white border-secondary"
                                    required>
                                    <option value="" selected disabled>Choisir une catégorie...</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id_category'] ?>">
                                            <?= htmlspecialchars($cat['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (empty($categories)): ?>
                                    <small class="text-warning">Créez d'abord une catégorie !</small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-white">Description</label>
                            <textarea name="description" class="form-control bg-dark text-white border-secondary"
                                rows="2" placeholder="Ex: Processeur i7, 16GB RAM..."></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-white">Prix Unitaire (FCFA)</label>
                                <input type="number" name="price"
                                    class="form-control bg-dark text-white border-secondary" required min="0"
                                    placeholder="Ex: 150000">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-white">Stock Initial</label>
                                <input type="number" name="quantity"
                                    class="form-control bg-dark text-white border-secondary" required min="0"
                                    value="0">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top border-secondary">
                        <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-premium">Ajouter le produit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div class="modal fade" id="editProductModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content bg-dark text-white border-0 glass-panel">
                <div class="modal-header border-bottom border-secondary">
                    <h5 class="modal-title">Modifier Produit</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="products.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id_product" id="edit_id_product">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-white">Nom du produit</label>
                                <input type="text" name="name" id="edit_name"
                                    class="form-control bg-dark text-white border-secondary" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-white">Catégorie</label>
                                <select name="category" id="edit_category"
                                    class="form-select bg-dark text-white border-secondary" required>
                                    <option value="" disabled>Choisir une catégorie...</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id_category'] ?>">
                                            <?= htmlspecialchars($cat['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-white">Description</label>
                            <textarea name="description" id="edit_description"
                                class="form-control bg-dark text-white border-secondary" rows="2"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-white">Prix Unitaire (FCFA)</label>
                                <input type="number" name="price" id="edit_price"
                                    class="form-control bg-dark text-white border-secondary" required min="0">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top border-secondary">
                        <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-premium">Mettre à jour</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
</body>

</html>
