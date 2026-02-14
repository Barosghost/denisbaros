<?php
define('PAGE_ACCESS', 'products');
require_once '../../backend/includes/auth_required.php';
require_once '../../backend/config/db.php';
require_once '../../backend/config/functions.php';

$pageTitle = "Gestion des Produits";

// Handle Add Product
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die("Erreur de sécurité (CSRF). Veuillez actualiser la page.");
    }

    // Schema: designation, categorie, prix_achat, prix_boutique_fixe, stock_actuel, seuil_alerte, duree_garantie_mois
    $designation = trim($_POST['designation']);
    $categorie = trim($_POST['categorie']);
    $prix_achat = $_POST['prix_achat'];
    $prix_boutique_fixe = $_POST['prix_boutique_fixe'];
    $stock_actuel = isset($_POST['stock_actuel']) ? (int) $_POST['stock_actuel'] : 0;
    $seuil_alerte = isset($_POST['seuil_alerte']) ? (int) $_POST['seuil_alerte'] : 5;
    $duree_garantie_mois = isset($_POST['duree_garantie_mois']) ? (int) $_POST['duree_garantie_mois'] : 12;

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO produits (designation, categorie, prix_achat, prix_boutique_fixe, stock_actuel, seuil_alerte, duree_garantie_mois) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$designation, $categorie, $prix_achat, $prix_boutique_fixe, $stock_actuel, $seuil_alerte, $duree_garantie_mois]);
        $id_produit = $pdo->lastInsertId();

        // Log movement if initial stock > 0
        if ($stock_actuel > 0) {
            // logStockMovement uses: id_produit, id_user, type, quantity, reason
            // We need to make sure logStockMovement is compatible or updated. 
            // I updated logStockMovement to use 'produits' table.
            logStockMovement($pdo, $id_produit, $_SESSION['user_id'] ?? 1, 'entree', $stock_actuel, "Stock initial à la création");
        }

        logActivity($pdo, $_SESSION['user_id'] ?? 1, "Ajout produit", "Produit: $designation, Prix: $prix_boutique_fixe");

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
    $id_produit = $_POST['id_produit'];
    $designation = trim($_POST['designation']);
    $categorie = trim($_POST['categorie']);
    $prix_achat = $_POST['prix_achat'];
    $prix_boutique_fixe = $_POST['prix_boutique_fixe'];
    $seuil_alerte = isset($_POST['seuil_alerte']) ? (int) $_POST['seuil_alerte'] : 5;
    $duree_garantie_mois = isset($_POST['duree_garantie_mois']) ? (int) $_POST['duree_garantie_mois'] : 12;

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE produits SET designation = ?, categorie = ?, prix_achat = ?, prix_boutique_fixe = ?, seuil_alerte = ?, duree_garantie_mois = ? WHERE id_produit = ?");
        $stmt->execute([$designation, $categorie, $prix_achat, $prix_boutique_fixe, $seuil_alerte, $duree_garantie_mois, $id_produit]);

        logActivity($pdo, $_SESSION['user_id'] ?? 1, "Modification produit", "Produit ID: $id_produit, Nom: $designation");

        $pdo->commit();
        $success = "Produit mis à jour avec succès.";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Erreur : " . $e->getMessage();
    }
}

// Handle Delete
$session_role = str_replace(' ', '_', strtolower($_SESSION['role'] ?? ''));
if (isset($_GET['delete']) && in_array($session_role, ['admin', 'super_admin'])) {
    $id = $_GET['delete'];
    try {
        // Check dependencies (sales, etc.)? Foreign keys might restrict.
        // Schema has ON DELETE CASCADE for packs, logic probably restrict for sales.
        $pdo->prepare("DELETE FROM produits WHERE id_produit = ?")->execute([$id]);
        logActivity($pdo, $_SESSION['user_id'] ?? 1, "Suppression produit", "Produit ID: $id");
        header("Location: products.php");
        exit();
    } catch (PDOException $e) {
        $error = "Impossible de supprimer ce produit (probablement lié à des ventes).";
    }
}

// Calculations for Products Summary
$total_products = $pdo->query("SELECT COUNT(*) FROM produits")->fetchColumn();
$active_products = $pdo->query("SELECT COUNT(*) FROM produits WHERE stock_actuel > 0")->fetchColumn();
$low_stock = $pdo->query("SELECT COUNT(*) FROM produits WHERE stock_actuel <= seuil_alerte")->fetchColumn();

// Calculate Total Stock Value
$total_stock_value = $pdo->query("SELECT SUM(prix_achat * stock_actuel) FROM produits")->fetchColumn() ?: 0;

// Fetch Data (produits.categorie = libellé direct, pas de table categories dans le schéma)
$products = $pdo->query("SELECT p.*, p.categorie as cat_label FROM produits p ORDER BY p.id_produit DESC")->fetchAll(PDO::FETCH_ASSOC);

// Catégories = valeurs distinctes de produits.categorie
$categories = $pdo->query("SELECT DISTINCT categorie FROM produits WHERE categorie IS NOT NULL AND categorie != '' ORDER BY categorie")->fetchAll(PDO::FETCH_COLUMN);

?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produits | DENIS FBI STORE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=1.4">
    <link rel="manifest" href="../manifest.json">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Reusing consistent styles */
        .product-stat-card {
            background: rgba(30, 41, 59, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 24px;
            display: flex;
            align-items: center;
            gap: 20px;
            backdrop-filter: blur(10px);
            height: 100%;
            transition: all 0.3s ease;
        }

        .product-stat-card:hover {
            transform: translateY(-5px);
        }

        .card-stock-total {
            border-top: 1px solid rgba(59, 130, 246, 0.3);
            background: linear-gradient(145deg, rgba(59, 130, 246, 0.1), rgba(30, 58, 138, 0.2));
        }

        .card-stock-value {
            border-top: 1px solid rgba(16, 185, 129, 0.3);
            background: linear-gradient(145deg, rgba(16, 185, 129, 0.1), rgba(6, 78, 59, 0.2));
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
        }

        .product-table-card {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            overflow: hidden;
        }

        .search-wrapper input {
            background: rgba(30, 41, 59, 0.6) !important;
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white !important;
            padding-left: 44px;
            height: 50px;
            border-radius: 14px;
        }

        .search-wrapper i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
        }

        .product-row {
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .product-row:hover {
            background: rgba(255, 255, 255, 0.05) !important;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .detail-row:last-child {
            border-bottom: none;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include '../../backend/includes/sidebar.php'; ?>
        <div id="content">
            <?php include '../../backend/includes/header.php'; ?>

            <div class="content-body">
                <?php if (isset($success)): ?>
                    <div class="alert alert-success mt-3"><?= $success ?></div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger mt-3"><?= $error ?></div>
                <?php endif; ?>

                <div class="fade-in mt-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 g-3">
                        <div>
                            <h3 class="text-white fw-bold mb-0">Gestion du Catalogue</h3>
                            <div class="text-muted extra-small">Base de données Produits</div>
                        </div>
                        <?php if (in_array($session_role, ['admin', 'vendeur', 'super_admin'])): ?>
                            <div class="d-flex gap-2">
                                <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal"
                                    data-bs-target="#addProductModal">
                                    <i class="fa-solid fa-plus me-2"></i>Ajouter un produit
                                </button>
                                <button class="btn btn-premium rounded-pill px-4" onclick="openCategoryModal()">
                                    <i class="fa-solid fa-tags me-2"></i>Catégorie
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Stats -->
                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <div class="product-stat-card card-stock-total">
                                <div class="stat-icon-large text-primary"><i class="fa-solid fa-box-archive"></i></div>
                                <div>
                                    <div class="text-white-50 extra-small text-uppercase fw-bold mb-1">Total Produits
                                    </div>
                                    <div class="text-white h3 fw-bold mb-0"><?= $total_products ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="product-stat-card card-stock-value">
                                <div class="stat-icon-large text-success"><i class="fa-solid fa-coins"></i></div>
                                <div>
                                    <div class="text-white-50 extra-small text-uppercase fw-bold mb-1">Valeur Stock
                                        (Achat)</div>
                                    <div class="text-white h3 fw-bold mb-0" style="color: #4ade80 !important;">
                                        <?= number_format($total_stock_value, 0, ',', ' ') ?> <small
                                            class="fs-6 opacity-75">FCFA</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Search -->
                    <div class="mb-4 search-wrapper">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" id="tableSearch" class="form-control"
                            placeholder="Rechercher par désignation, catégorie...">
                    </div>

                    <!-- Table -->
                    <div class="product-table-card">
                        <div class="table-responsive">
                            <table class="table table-dark table-hover mb-0 align-middle">
                                <thead>
                                    <tr class="text-white-50 extra-small text-uppercase fw-bold">
                                        <th class="px-4 py-3">Désignation</th>
                                        <th class="py-3">Catégorie</th>
                                        <th class="py-3">Prix Vente</th>
                                        <th class="py-3">Prix Achat</th>
                                        <th class="py-3 text-center">Stock</th>
                                        <th class="py-3 text-center">Alerte</th>
                                        <?php if (in_array(strtolower($_SESSION['role']), ['admin', 'super admin'])): ?>
                                            <th class="py-3 text-end px-4">Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $prod): ?>
                                        <tr class="product-row border-white border-opacity-5"
                                            onclick='viewProductDetails(<?= json_encode($prod) ?>)'
                                            data-search="<?= strtolower(htmlspecialchars($prod['designation'] . ' ' . $prod['categorie'])) ?>">
                                            <td class="px-4 py-3">
                                                <div class="fw-bold text-white">
                                                    <?= htmlspecialchars($prod['designation']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span
                                                    class="badge rounded-pill bg-secondary bg-opacity-25 text-white-50 border border-white border-opacity-10">
                                                    <?= htmlspecialchars($prod['categorie']) ?>
                                                </span>
                                            </td>
                                            <td class="text-success fw-bold">
                                                <?= number_format($prod['prix_boutique_fixe'], 0, ',', ' ') ?> FCFA
                                            </td>
                                            <td class="text-warning small">
                                                <?= number_format($prod['prix_achat'], 0, ',', ' ') ?> FCFA
                                            </td>
                                            <td class="text-center">
                                                <?php
                                                $stock = $prod['stock_actuel'];
                                                $alert = $prod['seuil_alerte'];
                                                $badgeClass = $stock <= $alert ? 'bg-danger text-danger' : ($stock < $alert * 2 ? 'bg-warning text-warning' : 'bg-success text-success');
                                                ?>
                                                <span
                                                    class="badge rounded-pill bg-opacity-10 border border-opacity-20 <?= $badgeClass ?>"
                                                    style="min-width: 60px;">
                                                    <?= $stock ?>
                                                </span>
                                            </td>
                                            <td class="text-center text-muted small"><?= $alert ?></td>
                                            <?php if (in_array(strtolower($_SESSION['role']), ['admin', 'super admin'])): ?>
                                                <td class="text-end px-4" onclick="event.stopPropagation()">
                                                    <button class="btn btn-sm btn-outline-info border-0 rounded-circle"
                                                        onclick='editProduct(<?= json_encode($prod) ?>)'>
                                                        <i class="fa-solid fa-pen"></i>
                                                    </button>
                                                    <a href="products.php?delete=<?= $prod['id_produit'] ?>"
                                                        class="btn btn-sm btn-outline-danger border-0 rounded-circle"
                                                        onclick="return confirm('Confirmer la suppression ?')">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </a>
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

    <!-- Add Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content bg-dark border border-white border-opacity-10 text-white rounded-4 shadow-lg">
                <form method="POST">
                    <div class="modal-header border-bottom border-white border-opacity-10">
                        <h5 class="modal-title fw-bold">Nouveau Produit</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <?= getCsrfInput() ?>
                        <input type="hidden" name="action" value="add">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label small text-muted text-uppercase fw-bold">Désignation</label>
                                <input type="text" name="designation"
                                    class="form-control bg-black bg-opacity-25 border-white border-opacity-10 text-white rounded-3"
                                    required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-muted text-uppercase fw-bold">Catégorie</label>
                                <input type="text" name="categorie" list="catList"
                                    class="form-control bg-black bg-opacity-25 border-white border-opacity-10 text-white rounded-3">
                                <datalist id="catList">
                                    <?php foreach ($categories as $c)
                                        echo "<option value='" . htmlspecialchars($c) . "'>"; ?>
                                </datalist>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-muted text-uppercase fw-bold">Prix Achat</label>
                                <input type="number" name="prix_achat"
                                    class="form-control bg-black bg-opacity-25 border-white border-opacity-10 text-white rounded-3"
                                    required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-muted text-uppercase fw-bold">Prix Vente</label>
                                <input type="number" name="prix_boutique_fixe"
                                    class="form-control bg-black bg-opacity-25 border-white border-opacity-10 text-white rounded-3"
                                    required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-muted text-uppercase fw-bold">Stock Initial</label>
                                <input type="number" name="stock_actuel" value="0"
                                    class="form-control bg-black bg-opacity-25 border-white border-opacity-10 text-white rounded-3">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-muted text-uppercase fw-bold">Seuil Alerte</label>
                                <input type="number" name="seuil_alerte" value="5"
                                    class="form-control bg-black bg-opacity-25 border-white border-opacity-10 text-white rounded-3">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-muted text-uppercase fw-bold">Garantie
                                    (mois)</label>
                                <input type="number" name="duree_garantie_mois" value="12"
                                    class="form-control bg-black bg-opacity-25 border-white border-opacity-10 text-white rounded-3">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top-0 pt-0">
                        <button type="button" class="btn btn-outline-light rounded-pill"
                            data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editProductModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content bg-dark border border-white border-opacity-10 text-white rounded-4 shadow-lg">
                <form method="POST">
                    <div class="modal-header border-bottom border-white border-opacity-10">
                        <h5 class="modal-title fw-bold">Modifier Produit</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <?= getCsrfInput() ?>
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id_produit" id="e_id">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label small text-muted text-uppercase fw-bold">Désignation</label>
                                <input type="text" name="designation" id="e_designation"
                                    class="form-control bg-black bg-opacity-25 border-white border-opacity-10 text-white rounded-3"
                                    required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-muted text-uppercase fw-bold">Catégorie</label>
                                <input type="text" name="categorie" id="e_categorie" list="catList"
                                    class="form-control bg-black bg-opacity-25 border-white border-opacity-10 text-white rounded-3">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-muted text-uppercase fw-bold">Prix Achat</label>
                                <input type="number" name="prix_achat" id="e_pa"
                                    class="form-control bg-black bg-opacity-25 border-white border-opacity-10 text-white rounded-3"
                                    required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-muted text-uppercase fw-bold">Prix Vente</label>
                                <input type="number" name="prix_boutique_fixe" id="e_pv"
                                    class="form-control bg-black bg-opacity-25 border-white border-opacity-10 text-white rounded-3"
                                    required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-muted text-uppercase fw-bold">Seuil Alerte</label>
                                <input type="number" name="seuil_alerte" id="e_seuil"
                                    class="form-control bg-black bg-opacity-25 border-white border-opacity-10 text-white rounded-3">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-muted text-uppercase fw-bold">Garantie
                                    (mois)</label>
                                <input type="number" name="duree_garantie_mois" id="e_garantie"
                                    class="form-control bg-black bg-opacity-25 border-white border-opacity-10 text-white rounded-3">
                            </div>
                        </div>
                        <div class="alert alert-info mt-3 small mb-0">
                            <i class="fa-solid fa-info-circle me-2"></i>Pour modifier le stock, utilisez le module
                            "Inventaire".
                        </div>
                    </div>
                    <div class="modal-footer border-top-0 pt-0">
                        <button type="button" class="btn btn-outline-light rounded-pill"
                            data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4">Mettre à jour</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Category management Modal -->
    <div class="modal fade" id="categoryModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content bg-dark border border-white border-opacity-10 text-white rounded-4 shadow-lg">
                <div class="modal-header border-bottom border-white border-opacity-10">
                    <h5 class="modal-title fw-bold">Gestion des Catégories</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="categoryForm" class="mb-4">
                        <input type="hidden" name="id_category" id="cat_id">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-5">
                                <label class="form-label small text-muted text-uppercase fw-bold">Nom</label>
                                <input type="text" name="name" id="cat_name"
                                    class="form-control bg-black bg-opacity-25 border-white border-opacity-10 text-white rounded-3"
                                    required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small text-muted text-uppercase fw-bold">Description</label>
                                <input type="text" name="description" id="cat_desc"
                                    class="form-control bg-black bg-opacity-25 border-white border-opacity-10 text-white rounded-3">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100 rounded-3" id="btnSaveCat">
                                    <i class="fa-solid fa-save"></i>
                                </button>
                            </div>
                        </div>
                    </form>

                    <div class="table-responsive" style="max-height: 300px;">
                        <table class="table table-dark table-hover mb-0 align-middle">
                            <thead class="extra-small text-uppercase text-muted">
                                <tr>
                                    <th>Nom</th>
                                    <th>Description</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="categoryTableBody">
                                <!-- AJAX content -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Details Modal -->
    <div class="modal fade" id="productDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark border border-white border-opacity-10 text-white rounded-4 shadow-lg">
                <div class="modal-header border-bottom border-white border-opacity-10">
                    <h5 class="modal-title fw-bold" id="detailTitle">Détails du Produit</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="detail-row">
                        <span class="text-muted small text-uppercase">ID Produit</span>
                        <span id="detId" class="fw-bold">#0000</span>
                    </div>
                    <div class="detail-row">
                        <span class="text-muted small text-uppercase">Désignation</span>
                        <span id="detName" class="fw-bold">---</span>
                    </div>
                    <div class="detail-row">
                        <span class="text-muted small text-uppercase">Catégorie</span>
                        <span id="detCat" class="badge rounded-pill bg-primary bg-opacity-10 text-primary">---</span>
                    </div>
                    <div class="detail-row">
                        <span class="text-muted small text-uppercase">Prix de Vente</span>
                        <span id="detPriceV" class="fw-bold text-success fs-5">0 FCFA</span>
                    </div>
                    <div class="detail-row">
                        <span class="text-muted small text-uppercase">Prix d'Achat</span>
                        <span id="detPriceA" class="fw-bold text-warning">0 FCFA</span>
                    </div>
                    <div class="detail-row">
                        <span class="text-muted small text-uppercase">Stock Actuel</span>
                        <span id="detStock" class="fw-bold text-white">0</span>
                    </div>
                    <div class="detail-row">
                        <span class="text-muted small text-uppercase">Seuil Alerte</span>
                        <span id="detAlert" class="fw-bold text-muted">0</span>
                    </div>
                    <div class="detail-row">
                        <span class="text-muted small text-uppercase">Garantie</span>
                        <span id="detGarantie" class="fw-bold text-info">---</span>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-premium w-100 rounded-pill"
                        data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewProductDetails(prod) {
            document.getElementById('detId').innerText = '#' + prod.id_produit.toString().padStart(4, '0');
            document.getElementById('detName').innerText = prod.designation;
            document.getElementById('detCat').innerText = prod.categorie || 'Non classé';
            document.getElementById('detPriceV').innerText = new Intl.NumberFormat('fr-FR').format(prod.prix_boutique_fixe) + ' FCFA';
            document.getElementById('detPriceA').innerText = new Intl.NumberFormat('fr-FR').format(prod.prix_achat) + ' FCFA';
            document.getElementById('detStock').innerText = prod.stock_actuel;
            document.getElementById('detAlert').innerText = prod.seuil_alerte;
            document.getElementById('detGarantie').innerText = (prod.duree_garantie_mois || 12) + ' mois';

            new bootstrap.Modal(document.getElementById('productDetailsModal')).show();
        }

        function editProduct(prod) {
            const form = document.querySelector('#editProductModal form');
            form.querySelector('[name=id_produit]').value = prod.id_produit;
            form.querySelector('[name=designation]').value = prod.designation;
            form.querySelector('[name=categorie]').value = prod.categorie;
            form.querySelector('[name=prix_achat]').value = prod.prix_achat;
            form.querySelector('[name=prix_boutique_fixe]').value = prod.prix_boutique_fixe;
            form.querySelector('[name=seuil_alerte]').value = prod.seuil_alerte;
            form.querySelector('[name=duree_garantie_mois]').value = prod.duree_garantie_mois;
            new bootstrap.Modal(document.getElementById('editProductModal')).show();
        }

        // Table Search
        document.getElementById('tableSearch').addEventListener('input', function () {
            const term = this.value.toLowerCase();
            document.querySelectorAll('.product-row').forEach(row => {
                const text = row.getAttribute('data-search');
                row.style.display = text.includes(term) ? '' : 'none';
            });
        });

        // Category AJAX logic
        let categories = [];
        const catModal = new bootstrap.Modal(document.getElementById('categoryModal'));

        function openCategoryModal() {
            loadCategories();
            catModal.show();
        }

        async function loadCategories() {
            try {
                const res = await fetch('../../backend/actions/categories/manage.php?action=list');
                const result = await res.json();
                if (result.success) {
                    categories = result.data;
                    renderCategories();
                    updateDatalist();
                }
            } catch (e) { console.error(e); }
        }

        function renderCategories() {
            const tbody = document.getElementById('categoryTableBody');
            tbody.innerHTML = categories.map(c => `
                <tr class="border-white border-opacity-5">
                    <td class="fw-bold text-white">${c.name}</td>
                    <td class="small text-muted">${c.description || ''}</td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-info border-0" onclick='editCategory(${JSON.stringify(c)})'>
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger border-0" onclick="deleteCategory(${c.id_category})">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
        }

        function updateDatalist() {
            const dl = document.getElementById('catList');
            dl.innerHTML = categories.map(c => `<option value="${c.name}">`).join('');
        }

        function editCategory(c) {
            document.getElementById('cat_id').value = c.id_category;
            document.getElementById('cat_name').value = c.name;
            document.getElementById('cat_desc').value = c.description;
            document.getElementById('btnSaveCat').innerHTML = '<i class="fa-solid fa-check"></i>';
        }

        document.getElementById('categoryForm').addEventListener('submit', async function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            const action = formData.get('id_category') ? 'update' : 'add';
            formData.append('action', action);

            try {
                const res = await fetch('../../backend/actions/categories/manage.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await res.json();
                if (result.success) {
                    this.reset();
                    document.getElementById('cat_id').value = '';
                    document.getElementById('btnSaveCat').innerHTML = '<i class="fa-solid fa-save"></i>';
                    loadCategories();
                    Swal.fire({ icon: 'success', title: result.message, toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
                } else {
                    Swal.fire({ icon: 'error', title: result.error });
                }
            } catch (e) { Swal.fire({ icon: 'error', title: 'Erreur réseau' }); }
        });

        async function deleteCategory(id) {
            if (!confirm("Supprimer cette catégorie ?")) return;
            try {
                const res = await fetch(`../../backend/actions/categories/manage.php?action=delete&id_category=${id}`);
                const result = await res.json();
                if (result.success) {
                    loadCategories();
                    Swal.fire({ icon: 'success', title: result.message, toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
                } else {
                    Swal.fire({ icon: 'error', title: result.error });
                }
            } catch (e) { Swal.fire({ icon: 'error', title: 'Erreur réseau' }); }
        }
    </script>
</body>

</html>