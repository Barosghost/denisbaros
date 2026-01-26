<?php
session_start();

if (!isset($_SESSION['logged_in'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../config/db.php';
$pageTitle = "Point de Vente";

// Fetch Clients
$clients = $pdo->query("SELECT id_client, fullname FROM clients ORDER BY fullname ASC")->fetchAll();

// Fetch Categories
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

// Fetch Products (Active and with stock > 0 ideally, but let's show all and blocking JS if no stock)
$products = $pdo->query("SELECT p.*, s.quantity, c.name as cat_name 
                         FROM products p 
                         JOIN stock s ON p.id_product = s.id_product 
                         LEFT JOIN categories c ON p.id_category = c.id_category 
                         WHERE p.status = 'actif' 
                         ORDER BY c.name ASC, p.name ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vente (POS) | DENIS FBI STORE</title>
    <link href="../assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* POS Specifics */
        .product-card {
            cursor: pointer;
            transition: transform 0.2s;
            border: 1px solid var(--glass-border);
        }

        .product-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary-color);
        }

        .product-grid {
            height: calc(100vh - 180px);
            overflow-y: auto;
        }

        .cart-panel {
            height: calc(100vh - 180px);
            display: flex;
            flex-direction: column;
        }

        .cart-items {
            flex-grow: 1;
            overflow-y: auto;
        }
    </style>
</head>

<body>

    <div class="wrapper">
        <?php include '../includes/sidebar.php'; ?>

        <div id="content">
            <?php include '../includes/header.php'; ?>

            <div class="row g-4 fade-in">
                <!-- Left Column: Product Selection -->
                <div class="col-lg-7">
                    <div class="card bg-dark border-0 glass-panel h-100">
                        <div
                            class="card-header border-bottom border-secondary bg-transparent d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 text-white"><i class="fa-solid fa-boxes-stacked me-2"></i> Produits</h5>
                            <input type="text" id="searchProduct"
                                class="form-control bg-dark text-white border-secondary w-50"
                                placeholder="Rechercher un produit...">
                        </div>

                        <!-- Category Filters -->
                        <div class="px-3 pt-3 pb-0">
                            <div class="d-flex gap-2 overflow-auto custom-scrollbar pb-2">
                                <button class="btn btn-sm btn-premium rounded-pill px-3 filter-btn active"
                                    data-filter="all">
                                    Tout
                                </button>
                                <?php foreach ($categories as $cat): ?>
                                    <button class="btn btn-sm btn-outline-light rounded-pill px-3 filter-btn"
                                        data-filter="<?= $cat['id_category'] ?>">
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="card-body product-grid p-3">
                            <div class="row g-3" id="productList">
                                <?php foreach ($products as $prod): ?>
                                    <div class="col-md-6 product-item" data-name="<?= strtolower($prod['name']) ?>"
                                        data-category="<?= $prod['id_category'] ?>">
                                        <div class="card product-card bg-dark text-white p-3 h-100"
                                            onclick="addToCart(<?= $prod['id_product'] ?>, '<?= addslashes($prod['name']) ?>', <?= $prod['price'] ?>, <?= $prod['quantity'] ?>)">
                                            <div class="d-flex justify-content-between mb-2">
                                                <span class="badge bg-secondary">
                                                    <?= htmlspecialchars($prod['cat_name']) ?>
                                                </span>
                                                <span
                                                    class="badge <?= $prod['quantity'] > 0 ? 'bg-success' : 'bg-danger' ?>">Stock:
                                                    <?= $prod['quantity'] ?>
                                                </span>
                                            </div>
                                            <h6 class="fw-bold mb-2">
                                                <?= htmlspecialchars($prod['name']) ?>
                                            </h6>
                                            <h5 class="text-primary mb-0">
                                                <?= number_format($prod['price'], 0, ',', ' ') ?> FCFA
                                            </h5>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Cart -->
                <div class="col-lg-5">
                    <div class="card bg-dark border-0 glass-panel h-100">
                        <div class="card-header border-bottom border-secondary bg-transparent">
                            <h5 class="mb-0 text-white"><i class="fa-solid fa-cart-shopping me-2"></i> Panier</h5>
                        </div>
                        <div class="card-body cart-panel p-3">

                            <!-- Client Select -->
                            <div class="mb-3">
                                <label class="text-muted small">Client</label>
                                <select id="clientSelect" class="form-select bg-dark text-white border-secondary">
                                    <option value="">Client Comptoir (Anonyme)</option>
                                    <?php foreach ($clients as $client): ?>
                                        <option value="<?= $client['id_client'] ?>">
                                            <?= htmlspecialchars($client['fullname']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="text-end mt-1">
                                    <a href="clients.php" class="small text-primary text-decoration-none">Nouveau Client
                                        ?</a>
                                </div>
                            </div>

                            <!-- Cart Items Table -->
                            <div class="cart-items mb-3 custom-scrollbar">
                                <table class="table table-dark align-middle">
                                    <thead class="text-muted small">
                                        <tr>
                                            <th>Produit</th>
                                            <th class="text-center" style="width: 70px;">Qté</th>
                                            <th class="text-end">Total</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="cartTableBody">
                                        <!-- JS will populate this -->
                                        <tr id="emptyCart" class="text-center text-muted">
                                            <td colspan="4" class="py-4">Panier vide</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Totals & Actions -->
                            <div class="mt-auto border-top border-secondary pt-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Total Articles:</span>
                                    <span class="text-white fw-bold" id="totalItems">0</span>
                                </div>
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="text-white h5">Total à Payer:</span>
                                    <span class="text-success h4 fw-bold" id="totalAmount">0 FCFA</span>
                                </div>
                                <button class="btn btn-premium w-100 py-3" onclick="processSale()">
                                    <i class="fa-solid fa-check-circle me-2"></i> Valider la Vente
                                </button>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="../assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <script src="../assets/vendor/jquery/jquery.min.js"></script> <!-- Utilizing jQuery for easier Ajax -->
    <script src="../assets/js/app.js"></script>
    <script src="../assets/js/pos.js"></script>
    <script>
        // Simple Category Filter Script
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                // Remove active class from all
                document.querySelectorAll('.filter-btn').forEach(b => {
                    b.classList.remove('btn-premium', 'active');
                    b.classList.add('btn-outline-light');
                });

                // Add active to clicked
                btn.classList.remove('btn-outline-light');
                btn.classList.add('btn-premium', 'active');

                const filter = btn.getAttribute('data-filter');
                const items = document.querySelectorAll('.product-item');

                items.forEach(item => {
                    if (filter === 'all' || item.getAttribute('data-category') == filter) {
                        item.classList.remove('d-none');
                    } else {
                        item.classList.add('d-none');
                    }
                });
            });
        });
    </script>
</body>

</html>
