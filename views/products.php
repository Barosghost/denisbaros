<?php
session_start();

if (!isset($_SESSION['logged_in'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../config/db.php';
require_once '../config/functions.php';
$pageTitle = "Gestion des Produits";

// Handle Add Product
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die("Erreur de sécurité (CSRF). Veuillez actualiser la page.");
    }
    $name = trim($_POST['name']);
    $desc = trim($_POST['description']);
    $price = $_POST['price'];
    $purchase_price = $_POST['purchase_price'];
    $cat_id = $_POST['category'];
    $initial_stock = isset($_POST['quantity']) ? (int) $_POST['quantity'] : 0;
    $status = 'actif';
    $image_path = null;

    // Handle Multiple Image Uploads
    $uploaded_images = [];
    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
        $target_dir = "../uploads/products/";
        if (!is_dir($target_dir))
            mkdir($target_dir, 0777, true);

        $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['images']['error'][$key] == 0) {
                $mime_type = finfo_file($finfo, $tmp_name);

                if (!in_array($mime_type, $allowed_mime_types)) {
                    // Skip invalid files or handle error
                    continue;
                }

                $file_extension = pathinfo($_FILES["images"]["name"][$key], PATHINFO_EXTENSION);
                $file_name = time() . "_" . uniqid() . "." . $file_extension;
                $target_file = $target_dir . $file_name;

                if (move_uploaded_file($tmp_name, $target_file)) {
                    $path = "uploads/products/" . $file_name;
                    $uploaded_images[] = $path;
                    if ($image_path === null)
                        $image_path = $path; // First image is main
                }
            }
        }
        finfo_close($finfo);
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO products (name, description, price, purchase_price, image, id_category, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $desc, $price, $purchase_price, $image_path, $cat_id, $status]);
        $product_id = $pdo->lastInsertId();

        // Save to gallery
        if (!empty($uploaded_images)) {
            $gallery_stmt = $pdo->prepare("INSERT INTO product_images (id_product, image_path) VALUES (?, ?)");
            foreach ($uploaded_images as $img) {
                $gallery_stmt->execute([$product_id, $img]);
            }
        }

        $stmt = $pdo->prepare("INSERT INTO stock (id_product, quantity) VALUES (?, ?)");
        $stmt->execute([$product_id, $initial_stock]);

        logActivity($pdo, $_SESSION['user_id'] ?? 1, "Ajout produit", "Produit: $name, Prix: $price");

        $pdo->commit();
        $success = "Produit ajouté avec succès.";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Erreur : " . $e->getMessage();
    }
}

// Handle Update Product
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die("Erreur de sécurité (CSRF). Veuillez actualiser la page.");
    }
    $id_product = $_POST['id_product'];
    $name = trim($_POST['name']);
    $desc = trim($_POST['description']);
    $price = $_POST['price'];
    $purchase_price = $_POST['purchase_price'];
    $cat_id = $_POST['category'];

    try {
        $stmt = $pdo->prepare("SELECT image FROM products WHERE id_product = ?");
        $stmt->execute([$id_product]);
        $current_product = $stmt->fetch();
        $image_path = $current_product['image'];

        // Handle Multiple Image Uploads (Update)
        $new_images = [];
        if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
            $target_dir = "../uploads/products/";
            if (!is_dir($target_dir))
                mkdir($target_dir, 0777, true);

            $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);

            foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['images']['error'][$key] == 0) {
                    $mime_type = finfo_file($finfo, $tmp_name);

                    if (!in_array($mime_type, $allowed_mime_types)) {
                        continue;
                    }

                    $file_extension = pathinfo($_FILES["images"]["name"][$key], PATHINFO_EXTENSION);
                    $file_name = time() . "_" . uniqid() . "." . $file_extension;
                    $target_file = $target_dir . $file_name;

                    if (move_uploaded_file($tmp_name, $target_file)) {
                        $path = "uploads/products/" . $file_name;
                        $new_images[] = $path;
                        if ($image_path === null)
                            $image_path = $path;
                    }
                }
            }
            finfo_close($finfo);
        }

        $pdo->beginTransaction();

        // Update main product info
        $stmt = $pdo->prepare("UPDATE products SET name = ?, description = ?, price = ?, purchase_price = ?, image = ?, id_category = ? WHERE id_product = ?");
        $stmt->execute([$name, $desc, $price, $purchase_price, $image_path, $cat_id, $id_product]);

        // Add to gallery
        if (!empty($new_images)) {
            $gallery_stmt = $pdo->prepare("INSERT INTO product_images (id_product, image_path) VALUES (?, ?)");
            foreach ($new_images as $img) {
                $gallery_stmt->execute([$id_product, $img]);
            }
        }

        $pdo->commit();
        logActivity($pdo, $_SESSION['user_id'] ?? 1, "Modification produit", "Produit ID: $id_product, Nom: $name");
        $success = "Produit mis à jour avec succès.";
    } catch (PDOException $e) {
        if ($pdo->inTransaction())
            $pdo->rollBack();
        $error = "Erreur : " . $e->getMessage();
    }
}

// Handle Delete Image from Gallery
if (isset($_GET['delete_image']) && $_SESSION['role'] === 'admin') {
    $img_id = (int) $_GET['delete_image'];
    try {
        $stmt = $pdo->prepare("SELECT image_path FROM product_images WHERE id_image = ?");
        $stmt->execute([$img_id]);
        $img = $stmt->fetch();
        if ($img) {
            @unlink("../" . $img['image_path']);
            $pdo->prepare("DELETE FROM product_images WHERE id_image = ?")->execute([$img_id]);
        }
        header("Location: products.php");
        exit();
    } catch (PDOException $e) {
        $error = "Erreur lors de la suppression de l'image.";
    }
}

// Handle Status Toggle
if (isset($_GET['toggle_status']) && $_SESSION['role'] === 'admin') {
    $id = $_GET['toggle_status'];
    $status = $_GET['status'] == 'actif' ? 'inactif' : 'actif';
    try {
        $stmt = $pdo->prepare("UPDATE products SET status = ? WHERE id_product = ?");
        $stmt->execute([$status, $id]);
        logActivity($pdo, $_SESSION['user_id'] ?? 1, "Changement statut produit", "Produit ID: $id, Nouveau statut: $status");
        header("Location: products.php");
        exit();
    } catch (PDOException $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

// Handle Delete
if (isset($_GET['delete']) && $_SESSION['role'] === 'admin') {
    $id = $_GET['delete'];
    try {
        $pdo->prepare("DELETE FROM stock WHERE id_product = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM products WHERE id_product = ?")->execute([$id]);
        logActivity($pdo, $_SESSION['user_id'] ?? 1, "Suppression produit", "Produit ID: $id");
        header("Location: products.php");
        exit();
    } catch (PDOException $e) {
        $error = "Impossible de supprimer ce produit.";
    }
}

// Calculations for Products Summary
$total_products = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$active_products = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'actif'")->fetchColumn();
$inactive_products = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'inactif'")->fetchColumn();

// Calculate Total Stock Value
$stock_value_stmt = $pdo->query("SELECT SUM(p.price * s.quantity) 
                                 FROM products p 
                                 JOIN stock s ON p.id_product = s.id_product 
                                 WHERE p.status = 'actif'");
$total_stock_value = $stock_value_stmt->fetchColumn() ?: 0;

// Fetch Data
$sql = "SELECT p.*, c.name as cat_name, s.quantity as store_quantity,
        (SELECT GROUP_CONCAT(image_path) FROM product_images WHERE id_product = p.id_product) as gallery
        FROM products p 
        LEFT JOIN categories c ON p.id_category = c.id_category 
        LEFT JOIN stock s ON p.id_product = s.id_product 
        ORDER BY p.created_at DESC";
$products = $pdo->query($sql)->fetchAll();
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produits | DENIS FBI STORE</title>
    <link href="../assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=1.4">
    <link rel="manifest" href="../manifest.json">
    <script src="../assets/vendor/sweetalert2/sweetalert2.all.min.js"></script>
    <style>
        /* Premium Card Styles (Shared with Dashboard) */
        .product-stat-card {
            background: rgba(30, 41, 59, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 24px;
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
            backdrop-filter: blur(10px);
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        .product-stat-card:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        /* Gradients */
        .card-stock-total {
            background: linear-gradient(145deg, rgba(59, 130, 246, 0.1) 0%, rgba(30, 58, 138, 0.2) 100%);
            border-top: 1px solid rgba(59, 130, 246, 0.3);
        }

        .card-stock-value {
            background: linear-gradient(145deg, rgba(16, 185, 129, 0.1) 0%, rgba(6, 78, 59, 0.2) 100%);
            border-top: 1px solid rgba(16, 185, 129, 0.3);
        }

        .card-stock-inactive {
            background: linear-gradient(145deg, rgba(239, 68, 68, 0.1) 0%, rgba(153, 27, 27, 0.2) 100%);
            border-top: 1px solid rgba(239, 68, 68, 0.3);
        }

        .stat-icon-large {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.05);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            box-shadow: inset 0 0 20px rgba(0, 0, 0, 0.2);
        }

        .decorative-circle {
            position: absolute;
            width: 120px;
            height: 120px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0) 70%);
            border-radius: 50%;
            top: -40px;
            right: -40px;
            filter: blur(15px);
        }

        .product-table-card {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .product-thumbnail {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            object-fit: cover;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: transform 0.3s;
        }

        .product-row:hover .product-thumbnail {
            transform: scale(1.1);
        }

        .badge-premium {
            padding: 6px 14px;
            font-weight: 600;
            font-size: 0.8rem;
            border-radius: 100px;
        }

        .search-wrapper {
            position: relative;
        }

        .search-wrapper i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
        }

        .search-wrapper input {
            padding-left: 44px;
            border-radius: 14px;
            background: rgba(30, 41, 59, 0.6) !important;
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white !important;
            height: 50px;
        }

        .search-wrapper input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include '../includes/sidebar.php'; ?>
        <div id="content">
            <?php include '../includes/header.php'; ?>

            <div class="content-body">

                <?php if (isset($success)): ?>
                    <div class="alert alert-success mt-3"><?= $success ?></div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger mt-3"><?= $error ?></div>
                <?php endif; ?>

                <div class="fade-in mt-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 g-3">
                        <div class="mb-2 mb-md-0">
                            <h3 class="text-white fw-bold mb-0">Gestion du Catalogue</h3>
                            <div class="text-muted extra-small">Optimisez votre inventaire et vos ventes</div>
                        </div>
                        <div class="d-flex gap-2">
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <a href="categories.php"
                                    class="btn btn-outline-secondary border-white border-opacity-10 text-white rounded-pill px-4">
                                    <i class="fa-solid fa-tags me-2"></i>Catégories
                                </a>
                                <button class="btn btn-premium rounded-pill px-4" data-bs-toggle="modal"
                                    data-bs-target="#addProductModal">
                                    <i class="fa-solid fa-plus me-2"></i>Nouveau Produit
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Products Summary -->
                    <div class="row g-4 mb-4">
                        <div class="col-md-4">
                            <div class="product-stat-card card-stock-total">
                                <div class="stat-icon-large text-primary">
                                    <i class="fa-solid fa-box-archive"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="text-white-50 extra-small text-uppercase fw-bold mb-1">Total Produits
                                    </div>
                                    <div class="text-white h3 fw-bold mb-0"><?= $total_products ?></div>
                                </div>
                                <div class="decorative-circle opacity-10"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="product-stat-card card-stock-value">
                                <div class="stat-icon-large text-success">
                                    <i class="fa-solid fa-coins"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="text-white h3 fw-bold mb-0" style="color: #4ade80 !important;">
                                        <?= number_format($total_stock_value, 0, ',', ' ') ?> <small
                                            class="fs-6 fw-normal opacity-75">FCFA</small>
                                    </div>
                                </div>
                                <div class="decorative-circle opacity-10"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="product-stat-card card-stock-inactive">
                                <div class="stat-icon-large text-danger">
                                    <i class="fa-solid fa-eye-slash"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="text-white-50 extra-small text-uppercase fw-bold mb-1">Produits Inactifs
                                    </div>
                                    <div class="text-white h3 fw-bold mb-0"><?= $inactive_products ?></div>
                                </div>
                                <div class="decorative-circle opacity-10"></div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="search-wrapper">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="text" id="tableSearch" class="form-control"
                                placeholder="Rechercher par nom, catégorie, description...">
                        </div>
                    </div>

                    <div class="product-table-card">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-dark table-hover mb-0 align-middle"
                                    style="background: transparent;">
                                    <thead>
                                        <tr
                                            class="text-white-50 extra-small text-uppercase fw-bold border-white border-opacity-10">
                                            <th class="px-4 py-3">Produit</th>
                                            <th class="py-3">Catégorie</th>
                                            <th class="py-3">Tarification (FCFA)</th>
                                            <th class="py-3 text-center">Stock</th>
                                            <th class="py-3 text-center">État</th>
                                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                                <th class="py-3 text-end px-4">Actions</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody class="border-top-0">
                                        <?php foreach ($products as $prod): ?>
                                            <tr class="product-row border-white border-opacity-5"
                                                data-name="<?= strtolower(htmlspecialchars($prod['name'])) ?>"
                                                data-category="<?= strtolower(htmlspecialchars($prod['cat_name'] ?? '')) ?>">
                                                <td class="px-4 py-3">
                                                    <div class="d-flex align-items-center gap-3" style="cursor:pointer"
                                                        onclick='viewProductDetails(<?= htmlspecialchars(json_encode($prod), ENT_QUOTES, "UTF-8") ?>)'>
                                                        <?php if ($prod['image']): ?>
                                                            <img src="../<?= $prod['image'] ?>"
                                                                class="product-thumbnail shadow-sm">
                                                        <?php else: ?>
                                                            <div
                                                                class="product-thumbnail d-flex align-items-center justify-content-center">
                                                                <i class="fa-solid fa-cube text-white-50 opacity-50"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div>
                                                            <div class="text-white fw-bold mb-1">
                                                                <?= htmlspecialchars($prod['name']) ?>
                                                            </div>
                                                            <div class="extra-small text-muted text-truncate"
                                                                style="max-width:200px">
                                                                <?= htmlspecialchars($prod['description'] ?? 'Aucune description disponible') ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge rounded-pill px-3 py-2 fw-normal"
                                                        style="background: rgba(139, 92, 246, 0.2); color: #c4b5fd; border: 1px solid rgba(139, 92, 246, 0.3);">
                                                        <?= htmlspecialchars($prod['cat_name'] ?? 'Non classé') ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="text-success fw-bold mb-1">
                                                        <?= number_format($prod['price'], 0, ',', ' ') ?>
                                                    </div>
                                                    <div class="extra-small fw-bold" style="color: #fbbf24;">Achat:
                                                        <?= number_format($prod['purchase_price'], 0, ',', ' ') ?>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <?php
                                                    $qty = $prod['store_quantity'] ?? 0;
                                                    if ($qty > 10) {
                                                        $badgeStyle = 'background: rgba(34, 197, 94, 0.2); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.3);';
                                                    } elseif ($qty > 0) {
                                                        $badgeStyle = 'background: rgba(234, 179, 8, 0.2); color: #facc15; border: 1px solid rgba(234, 179, 8, 0.3);';
                                                    } else {
                                                        $badgeStyle = 'background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3);';
                                                    }
                                                    ?>
                                                    <span class="badge rounded-pill px-3 py-2" style="<?= $badgeStyle ?>">
                                                        <span class="fw-bold"><?= $qty ?></span> <span
                                                            class="fw-normal opacity-75">unités</span>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <?php
                                                    $statusLabel = $prod['status'] == 'actif' ? 'Actif' : 'Inactif';
                                                    if ($prod['status'] == 'actif') {
                                                        $statusStyle = 'background: rgba(34, 197, 94, 0.1); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.2);';
                                                        $dotColor = '#4ade80';
                                                    } else {
                                                        $statusStyle = 'background: rgba(239, 68, 68, 0.1); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.2);';
                                                        $dotColor = '#f87171';
                                                    }
                                                    ?>
                                                    <?php if ($_SESSION['role'] === 'admin'): ?>
                                                        <a href="products.php?toggle_status=<?= $prod['id_product'] ?>&status=<?= $prod['status'] ?>"
                                                            class="badge rounded-pill px-3 py-2 text-decoration-none hover-scale"
                                                            style="<?= $statusStyle ?>">
                                                            <span class="status-dot me-1"
                                                                style="background-color: <?= $dotColor ?>"></span><?= $statusLabel ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="badge rounded-pill px-3 py-2" style="<?= $statusStyle ?>">
                                                            <span class="status-dot me-1"
                                                                style="background-color: <?= $dotColor ?>"></span><?= $statusLabel ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <?php if ($_SESSION['role'] === 'admin'): ?>
                                                    <td class="text-end px-4">
                                                        <div class="d-flex justify-content-end gap-2">
                                                            <button class="btn btn-icon btn-sm"
                                                                style="background: rgba(56, 189, 248, 0.1); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.3);"
                                                                onclick='openEditProductModal(<?= htmlspecialchars(json_encode($prod), ENT_QUOTES, "UTF-8") ?>)'
                                                                title="Modifier">
                                                                <i class="fa-solid fa-pen"></i>
                                                            </button>
                                                            <a href="products.php?delete=<?= $prod['id_product'] ?>"
                                                                class="btn btn-icon btn-sm"
                                                                style="background: rgba(248, 113, 113, 0.1); color: #f87171; border: 1px solid rgba(248, 113, 113, 0.3);"
                                                                onclick="return confirmAction(this.href, 'Supprimer ce produit de manère permanente ?')"
                                                                title="Supprimer">
                                                                <i class="fa-solid fa-trash"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                <?php endif; ?>
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

        <!-- Modals -->
        <!-- Add Modal -->
        <div class="modal fade" id="addProductModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0"
                    style="background: #0f172a; border-radius: 24px; box-shadow: 0 0 50px rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.1);">
                    <form action="products.php" method="POST" enctype="multipart/form-data">
                        <div class="modal-header border-white border-opacity-10 p-4">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-primary bg-opacity-10 p-3 rounded-circle text-primary">
                                    <i class="fa-solid fa-box-open fa-lg"></i>
                                </div>
                                <div>
                                    <h5 class="modal-title text-white fw-bold mb-0">Nouveau Produit</h5>
                                    <div class="text-muted extra-small">Ajouter une référence au catalogue</div>
                                </div>
                            </div>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-4">
                            <?= getCsrfInput() ?>
                            <input type="hidden" name="action" value="add">
                            <div class="row g-4">
                                <div class="col-md-7">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label
                                                class="form-label text-muted extra-small text-uppercase fw-bold mb-2">Désignation</label>
                                            <input type="text" name="name"
                                                class="form-control bg-dark border-white border-opacity-10 text-white py-3"
                                                style="border-radius: 12px;" required placeholder="Ex: Ciment 50kg">
                                        </div>
                                        <div class="col-12">
                                            <label
                                                class="form-label text-muted extra-small text-uppercase fw-bold mb-2">Catégorie</label>
                                            <select name="category"
                                                class="form-select bg-dark border-white border-opacity-10 text-white py-3"
                                                style="border-radius: 12px;" required>
                                                <option value="" disabled selected>Sélectionner...</option>
                                                <?php foreach ($categories as $cat): ?>
                                                    <option value="<?= $cat['id_category'] ?>"><?= $cat['name'] ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-12">
                                            <label
                                                class="form-label text-muted extra-small text-uppercase fw-bold mb-2">Description</label>
                                            <textarea name="description"
                                                class="form-control bg-dark border-white border-opacity-10 text-white"
                                                rows="3" style="border-radius: 12px;"
                                                placeholder="Détails techniques..."></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <div class="upload-area border-dashed border-2 border-white border-opacity-10 rounded-4 d-flex align-items-center justify-content-center text-center p-4 bg-dark bg-opacity-50 hover-effect"
                                        style="height: 100%; min-height: 200px; cursor: pointer; transition: 0.3s;"
                                        onclick="document.getElementById('imgIn').click()">
                                        <input type="file" name="images[]" id="imgIn" class="d-none" multiple
                                            onchange="previewMultiple(this, 'galleryAdd')">
                                        <div id="galleryAdd"
                                            class="w-100 d-flex flex-wrap gap-2 justify-content-center p-2">
                                            <div class="text-muted">
                                                <div class="mb-3">
                                                    <i class="fa-solid fa-images fa-3x opacity-25"></i>
                                                </div>
                                                <div class="small fw-bold text-white mb-1">Téléverser des images</div>
                                                <div class="extra-small opacity-50">Sélection multiple (Plus de 4!)
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row g-3 mt-4">
                                <div class="col-md-4">
                                    <div
                                        class="p-3 rounded-4 bg-white bg-opacity-5 border border-white border-opacity-5">
                                        <label class="extra-small text-muted text-uppercase fw-bold mb-2">Prix
                                            Achat</label>
                                        <div class="input-group">
                                            <input type="number" name="purchase_price"
                                                class="form-control bg-transparent border-0 fw-bold p-0"
                                                style="color: #fbbf24;" required placeholder="0">
                                            <span class="small fw-bold"
                                                style="color: #fbbf24; opacity: 0.8;">FCFA</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div
                                        class="p-3 rounded-4 bg-success bg-opacity-10 border border-success border-opacity-20">
                                        <label class="extra-small text-success text-uppercase fw-bold mb-2">Prix
                                            Vente</label>
                                        <div class="input-group">
                                            <input type="number" name="price"
                                                class="form-control bg-transparent border-0 fw-bold p-0"
                                                style="color: #4ade80;" required placeholder="0">
                                            <span class="small fw-bold"
                                                style="color: #4ade80; opacity: 0.8;">FCFA</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div
                                        class="p-3 rounded-4 bg-warning bg-opacity-10 border border-warning border-opacity-20">
                                        <label class="extra-small text-warning text-uppercase fw-bold mb-2">Stock
                                            Init.</label>
                                        <input type="number" name="quantity"
                                            class="form-control bg-transparent border-0 text-white fw-bold p-0"
                                            value="0">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer border-top-0 p-4">
                            <button type="button" class="btn btn-outline-light border-0 rounded-pill px-4"
                                data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-primary px-5 py-3 rounded-pill fw-bold shadow-lg">
                                <i class="fa-solid fa-check me-2"></i> Enregistrer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Edit Modal -->
        <div class="modal fade" id="editProductModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0"
                    style="background: #0f172a; border-radius: 24px; box-shadow: 0 0 50px rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.1);">
                    <form action="products.php" method="POST" enctype="multipart/form-data">
                        <div class="modal-header border-white border-opacity-10 p-4">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-info bg-opacity-10 p-3 rounded-circle text-info">
                                    <i class="fa-solid fa-pen-to-square fa-lg"></i>
                                </div>
                                <div>
                                    <h5 class="modal-title text-white fw-bold mb-0">Modifier Produit</h5>
                                    <div class="text-muted extra-small">Mise à jour des informations</div>
                                </div>
                            </div>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-4">
                            <?= getCsrfInput() ?>
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="id_product" id="ed_id">
                            <div class="row g-4">
                                <div class="col-md-7">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="text-muted extra-small text-uppercase fw-bold mb-2">Nom du
                                                Produit</label>
                                            <input type="text" name="name" id="ed_name"
                                                class="form-control bg-dark border-white border-opacity-10 text-white py-3"
                                                style="border-radius: 12px;" required>
                                        </div>
                                        <div class="col-12">
                                            <label
                                                class="text-muted extra-small text-uppercase fw-bold mb-2">Catégorie</label>
                                            <select name="category" id="ed_cat"
                                                class="form-select bg-dark border-white border-opacity-10 text-white py-3"
                                                style="border-radius: 12px;" required>
                                                <?php foreach ($categories as $cat): ?>
                                                    <option value="<?= $cat['id_category'] ?>"><?= $cat['name'] ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-12">
                                            <label
                                                class="text-muted extra-small text-uppercase fw-bold mb-2">Description</label>
                                            <textarea name="description" id="ed_desc"
                                                class="form-control bg-dark border-white border-opacity-10 text-white"
                                                rows="3" style="border-radius: 12px;"></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <label class="text-muted extra-small text-uppercase fw-bold mb-2">Galerie
                                        Photos</label>
                                    <div class="upload-area border-dashed border-2 border-white border-opacity-10 rounded-4 p-3 bg-dark bg-opacity-50"
                                        style="min-height: 200px; cursor: pointer;"
                                        onclick="if(event.target === this || event.target.id === 'galleryEd') document.getElementById('imgEd').click()">
                                        <input type="file" name="images[]" id="imgEd" class="d-none" multiple
                                            onchange="previewMultiple(this, 'galleryEd', true)">
                                        <div id="galleryEd" class="d-flex flex-wrap gap-2">
                                            <!-- Existing and new images loaded here -->
                                        </div>
                                        <div class="text-center mt-2 text-muted extra-small">
                                            Cliquez pour ajouter d'autres photos
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row g-3 mt-4">
                                <div class="col-md-6">
                                    <div
                                        class="p-3 rounded-4 bg-white bg-opacity-5 border border-white border-opacity-5">
                                        <label class="extra-small text-muted text-uppercase fw-bold mb-2">Prix
                                            Achat</label>
                                        <div class="input-group">
                                            <input type="number" name="purchase_price" id="ed_pa"
                                                class="form-control bg-transparent border-0 fw-bold p-0"
                                                style="color: #fbbf24;" required>
                                            <span class="small fw-bold"
                                                style="color: #fbbf24; opacity: 0.8;">FCFA</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div
                                        class="p-3 rounded-4 bg-success bg-opacity-10 border border-success border-opacity-20">
                                        <label class="extra-small text-success text-uppercase fw-bold mb-2">Prix
                                            Vente</label>
                                        <div class="input-group">
                                            <input type="number" name="price" id="ed_pv"
                                                class="form-control bg-transparent border-0 fw-bold p-0"
                                                style="color: #4ade80;" required>
                                            <span class="small fw-bold"
                                                style="color: #4ade80; opacity: 0.8;">FCFA</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer border-top-0 p-4">
                            <button type="button" class="btn btn-outline-light border-0 rounded-pill px-4"
                                data-bs-dismiss="modal">Annuler</button>
                            <button type="submit"
                                class="btn btn-info px-5 py-3 rounded-pill fw-bold text-white shadow-lg">
                                <i class="fa-solid fa-save me-2"></i> Mettre à jour
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Details Modal -->
        <div class="modal fade" id="viewProductModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0"
                    style="background: #0f172a; border-radius: 28px; box-shadow: 0 40px 100px -20px rgba(0, 0, 0, 0.6); border: 1px solid rgba(255,255,255,0.1);">
                    <div class="modal-header border-white border-opacity-10 p-4">
                        <h5 class="modal-title text-white fw-bold">Détails du Produit</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4 text-center">
                        <div id="det_img" class="mb-4"></div>
                        <div class="row text-start g-4">
                            <div class="col-6">
                                <small class="text-muted extra-small text-uppercase fw-bold">Désignation</small>
                                <div id="det_name" class="text-white fw-bold"></div>
                            </div>
                            <div class="col-6">
                                <small class="text-muted extra-small text-uppercase fw-bold">Catégorie</small>
                                <div id="det_cat" class="text-white"></div>
                            </div>
                            <div class="col-12">
                                <small class="text-muted extra-small text-uppercase fw-bold">Description</small>
                                <div id="det_desc" class="text-muted small"></div>
                            </div>
                            <div class="col-6">
                                <small class="text-muted extra-small text-uppercase fw-bold">Tarif Achat</small>
                                <div id="det_pa" class="h5 fw-bold mb-0" style="color: #fbbf24;"></div>
                            </div>
                            <div class="col-6">
                                <small class="text-muted extra-small text-uppercase fw-bold">Tarif Vente</small>
                                <div id="det_pv" class="text-success h5 fw-bold mb-0"></div>
                            </div>
                            <div class="col-6">
                                <small class="text-muted extra-small text-uppercase fw-bold">Stock Actuel</small>
                                <div id="det_stock" class="text-white h5 fw-bold mb-0"></div>
                            </div>
                            <div class="col-6">
                                <small class="text-muted extra-small text-uppercase fw-bold">État</small>
                                <div id="det_status" class="extra-small fw-bold"></div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-white border-opacity-5 p-4">
                        <button type="button" class="btn btn-outline-secondary border-0 text-white w-100"
                            data-bs-dismiss="modal">Fermer la vue</button>
                    </div>
                </div>
            </div>
        </div>

        <script src="../assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
        <script src="../assets/js/app.js"></script>
        <script>
            function previewMultiple(input, containerId, keepExisting = false) {
                const container = document.getElementById(containerId);
                if (!keepExisting) container.innerHTML = '';

                if (input.files) {
                    Array.from(input.files).forEach(file => {
                        const reader = new FileReader();
                        reader.onload = e => {
                            const div = document.createElement('div');
                            div.className = 'position-relative';
                            div.style.width = '80px';
                            div.style.height = '80px';
                            div.innerHTML = `
                                <img src="${e.target.result}" class="rounded shadow-sm" style="width:100%; height:100%; object-fit:cover; border:1px solid rgba(255,255,255,0.1)">
                            `;
                            container.appendChild(div);
                        };
                        reader.readAsDataURL(file);
                    });
                }
            }

            function openEditProductModal(p) {
                document.getElementById('ed_id').value = p.id_product;
                document.getElementById('ed_name').value = p.name;
                document.getElementById('ed_cat').value = p.id_category;
                document.getElementById('ed_desc').value = p.description;
                document.getElementById('ed_pv').value = p.price;
                document.getElementById('ed_pa').value = p.purchase_price;

                // Load Gallery in Edit Modal
                const galleryEd = document.getElementById('galleryEd');
                galleryEd.innerHTML = '';

                // Main image first
                if (p.image) {
                    const div = document.createElement('div');
                    div.className = 'position-relative';
                    div.style.width = '80px';
                    div.style.height = '80px';
                    div.innerHTML = `<img src="../${p.image}" class="rounded" style="width:100%; height:100%; object-fit:cover; border:2px solid #38bdf8">`;
                    galleryEd.appendChild(div);
                }

                if (p.gallery) {
                    const paths = p.gallery.split(',');
                    // We need the image IDs too for deletion, but GROUP_CONCAT only gave paths.
                    // For now, let's just show them. If we want deletion, we'd need a separate API call or more complex SQL.
                    paths.forEach(path => {
                        if (path === p.image) return; // Skip main if already shown
                        const div = document.createElement('div');
                        div.className = 'position-relative';
                        div.style.width = '80px';
                        div.style.height = '80px';
                        div.innerHTML = `<img src="../${path}" class="rounded" style="width:100%; height:100%; object-fit:cover; border:1px solid rgba(255,255,255,0.1)">`;
                        galleryEd.appendChild(div);
                    });
                }

                new bootstrap.Modal(document.getElementById('editProductModal')).show();
            }

            function viewProductDetails(p) {
                const detImg = document.getElementById('det_img');
                detImg.innerHTML = '';

                if (p.gallery || p.image) {
                    const allImages = p.gallery ? p.gallery.split(',') : [p.image];
                    let carouselHtml = `
                        <div id="productGallery" class="carousel slide" data-bs-ride="carousel">
                            <div class="carousel-inner rounded-4 shadow-lg" style="height:300px">
                                ${allImages.map((img, i) => `
                                    <div class="carousel-item ${i === 0 ? 'active' : ''} h-100">
                                        <img src="../${img}" class="d-block w-100 h-100" style="object-fit:contain; background:#000">
                                    </div>
                                `).join('')}
                            </div>
                            ${allImages.length > 1 ? `
                                <button class="carousel-control-prev" type="button" data-bs-target="#productGallery" data-bs-slide="prev">
                                    <span class="carousel-control-prev-icon"></span>
                                </button>
                                <button class="carousel-control-next" type="button" data-bs-target="#productGallery" data-bs-slide="next">
                                    <span class="carousel-control-next-icon"></span>
                                </button>
                            ` : ''}
                        </div>
                    `;
                    detImg.innerHTML = carouselHtml;
                } else {
                    detImg.innerHTML = '<i class="fa-solid fa-cube fa-4x text-muted opacity-25"></i>';
                }

                document.getElementById('det_name').innerText = p.name;
                document.getElementById('det_cat').innerText = p.cat_name || 'Non classé';
                document.getElementById('det_desc').innerText = p.description || 'N/A';
                document.getElementById('det_pa').innerText = p.purchase_price + ' FCFA';
                document.getElementById('det_pv').innerText = p.price + ' FCFA';
                document.getElementById('det_stock').innerText = p.store_quantity;

                const statusBadge = document.getElementById('det_status');
                statusBadge.innerText = (p.status || 'actif').toUpperCase();
                statusBadge.className = 'badge rounded-pill px-3 py-2 ' + (p.status === 'actif' ? 'bg-success bg-opacity-10 text-success' : 'bg-danger bg-opacity-10 text-danger');

                new bootstrap.Modal(document.getElementById('viewProductModal')).show();
            }
            document.getElementById('tableSearch').addEventListener('input', e => {
                const term = e.target.value.toLowerCase();
                document.querySelectorAll('.product-row').forEach(row => {
                    row.style.display = (row.dataset.name.includes(term) || row.dataset.category.includes(term)) ? '' : 'none';
                });
            });
        </script>
</body>

</html>