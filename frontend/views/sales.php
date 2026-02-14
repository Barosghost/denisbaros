<?php
define('PAGE_ACCESS', 'sales');
require_once '../../backend/includes/auth_required.php';
require_once '../../backend/config/db.php';
require_once '../../backend/config/functions.php';
generateCsrfToken();
$pageTitle = "Point de Vente PRO";

// Statistics for the Session (Today)
$today_revenue = $pdo->query("SELECT SUM(prix_revente_final) FROM ventes WHERE DATE(date_vente) = CURDATE()")->fetchColumn() ?: 0;
$today_count = $pdo->query("SELECT COUNT(*) FROM ventes WHERE DATE(date_vente) = CURDATE()")->fetchColumn() ?: 0;

// Fetch Clients
$clients = $pdo->query("SELECT id_client, nom_client as fullname, telephone FROM clients ORDER BY nom_client ASC")->fetchAll();

// Fetch Categories using new categories table
$categories = $pdo->query("SELECT id_category, name FROM categories ORDER BY name ASC")->fetchAll();
if (empty($categories)) {
    // Fallback to distinct products categories if table is empty
    $categories = $pdo->query("SELECT DISTINCT categorie as name, categorie as id_category FROM produits WHERE categorie IS NOT NULL ORDER BY categorie ASC")->fetchAll();
}

// Fetch Products (Active and with stock > 0)
$products = $pdo->query("
    SELECT id_produit as id, 
           designation as name, 
           categorie as cat_name, 
           prix_boutique_fixe as price, 
           stock_actuel as stock
    FROM produits 
    WHERE stock_actuel > 0 
    ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Active Resellers
$active_resellers = $pdo->query("SELECT id_revendeur as id, nom_partenaire as name, taux_commission_fixe as rate FROM revendeurs ORDER BY nom_partenaire ASC")->fetchAll();

?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Premium | DENIS FBI STORE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=2.0">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --pos-bg: #0f172a;
            --pos-card-bg: rgba(30, 41, 59, 0.4);
            --pos-accent: #6366f1;
            --pos-accent-gradient: linear-gradient(135deg, #6366f1, #8b5cf6);
            --pos-success: #10b981;
            --pos-border: rgba(255, 255, 255, 0.08);
            --font-outfit: 'Outfit', sans-serif;
        }

        body {
            background-color: var(--pos-bg) !important;
            font-family: var(--font-outfit);
            color: #f1f5f9;
            overflow-x: hidden;
        }

        /* Layout Structure */
        .pos-wrapper {
            display: grid;
            grid-template-columns: 1fr 380px;
            height: 100vh;
            gap: 0;
            overflow: hidden;
        }

        @media (max-width: 1200px) {
            .pos-wrapper {
                grid-template-columns: 1fr;
            }

            .cart-sidebar {
                position: fixed;
                right: -100%;
                top: 0;
                width: 100%;
                max-width: 400px;
                height: 100%;
                z-index: 1050;
                transition: 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .cart-sidebar.active {
                right: 0;
            }
        }

        /* Main Content */
        .pos-main {
            padding: 24px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        /* Top Bar */
        .pos-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .search-box {
            position: relative;
            flex: 1;
            max-width: 600px;
        }

        .search-box input {
            width: 100%;
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid var(--pos-border);
            border-radius: 16px;
            padding: 14px 20px 14px 50px;
            color: white;
            transition: 0.3s;
            backdrop-filter: blur(10px);
        }

        .search-box i {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }

        .search-box input:focus {
            background: rgba(30, 41, 59, 0.9);
            border-color: var(--pos-accent);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
            outline: none;
        }

        /* Category Scroll */
        .category-scroll {
            display: flex;
            gap: 12px;
            overflow-x: auto;
            padding-bottom: 8px;
            scrollbar-width: none;
        }

        .category-scroll::-webkit-scrollbar {
            display: none;
        }

        .cat-btn {
            padding: 10px 24px;
            border-radius: 100px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--pos-border);
            color: #94a3b8;
            font-weight: 600;
            transition: 0.3s;
            white-space: nowrap;
            cursor: pointer;
        }

        .cat-btn.active {
            background: var(--pos-accent-gradient);
            color: white;
            border-color: transparent;
            box-shadow: 0 8px 16px rgba(99, 102, 241, 0.3);
        }

        /* Product Grid */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
        }

        .product-card {
            background: var(--pos-card-bg);
            border: 1px solid var(--pos-border);
            border-radius: 24px;
            padding: 20px;
            transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
            cursor: pointer;
            position: relative;
            backdrop-filter: blur(8px);
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .product-card:hover {
            transform: translateY(-8px);
            background: rgba(30, 41, 59, 0.7);
            border-color: var(--pos-accent);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        .product-card .stock-tag {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 11px;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 8px;
            text-transform: uppercase;
        }

        .stock-ok {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
        }

        .stock-low {
            background: rgba(245, 158, 11, 0.15);
            color: #f59e0b;
        }

        .product-card .name {
            font-size: 1.1rem;
            font-weight: 700;
            color: #f8fafc;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            height: 2.8rem;
        }

        .product-card .price {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--pos-success);
        }

        .product-card .btn-add {
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--pos-border);
            color: white;
            border-radius: 12px;
            padding: 10px;
            font-weight: 600;
            transition: 0.2s;
        }

        .product-card:hover .btn-add {
            background: var(--pos-accent-gradient);
            border-color: transparent;
        }

        /* Sidebar Cart */
        .cart-sidebar {
            background: rgba(15, 23, 42, 0.95);
            border-left: 1px solid var(--pos-border);
            display: flex;
            flex-direction: column;
            backdrop-filter: blur(20px);
        }

        .cart-header {
            padding: 24px;
            border-bottom: 1px solid var(--pos-border);
        }

        .cart-items {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .cart-item {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 16px;
            padding: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid transparent;
            transition: 0.2s;
        }

        .cart-item:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: var(--pos-border);
        }

        .item-info {
            flex: 1;
        }

        .item-info .title {
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 4px;
        }

        .item-info .price {
            color: var(--pos-success);
            font-weight: 700;
            font-size: 0.9rem;
        }

        .item-qty {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(0, 0, 0, 0.2);
            padding: 4px;
            border-radius: 10px;
        }

        .item-qty button {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            border: none;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .item-qty span {
            min-width: 20px;
            text-align: center;
            font-weight: 700;
        }

        .cart-footer {
            padding: 24px;
            background: rgba(30, 41, 59, 0.4);
            border-top: 1px solid var(--pos-border);
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .summary-row.total {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--pos-accent);
        }

        .btn-pay {
            width: 100%;
            background: var(--pos-accent-gradient);
            border: none;
            color: white;
            padding: 18px;
            border-radius: 16px;
            font-size: 1.2rem;
            font-weight: 800;
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.3);
            transition: 0.3s;
        }

        .btn-pay:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(99, 102, 241, 0.5);
        }

        .btn-pay:disabled {
            background: #475569;
            box-shadow: none;
            cursor: not-allowed;
            transform: none;
        }

        /* Modal Customization */
        .modal-content {
            background: var(--pos-bg);
            border: 1px solid var(--pos-border);
            border-radius: 24px;
        }

        .modal-header {
            border-bottom: 1px solid var(--pos-border);
            padding: 24px;
        }

        .modal-footer {
            border-top: 1px solid var(--pos-border);
            padding: 24px;
        }

        .form-select,
        .form-control {
            background: rgba(0, 0, 0, 0.2) !important;
            border: 1px solid var(--pos-border) !important;
            color: white !important;
            border-radius: 12px;
            padding: 12px;
        }

        /* Mobile Action Bar */
        .mobile-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background: rgba(15, 23, 42, 0.9);
            backdrop-filter: blur(10px);
            padding: 16px;
            display: none;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid var(--pos-border);
            z-index: 1000;
        }

        @media (max-width: 1200px) {
            .mobile-bar {
                display: flex;
            }

            .pos-main {
                padding-bottom: 100px;
            }
        }
    </style>
</head>

<body>

    <div class="pos-wrapper">
        <!-- Main Panel -->
        <div class="pos-main">
            <!-- Header -->
            <div class="pos-header">
                <div class="search-box">
                    <i class="fa-solid fa-search"></i>
                    <input type="text" id="productSearch" placeholder="Rechercher un produit (ID ou Nom)...">
                </div>
                <div class="d-flex gap-3 align-items-center">
                    <div class="text-end d-none d-md-block">
                        <div class="text-muted small text-uppercase fw-bold">Session Aujourd'hui</div>
                        <div class="fw-bold h5 mb-0 text-white"><?= number_format($today_revenue, 0, ',', ' ') ?> FCFA
                        </div>
                    </div>
                </div>
            </div>

            <!-- Categories -->
            <div class="category-scroll">
                <button class="cat-btn active" data-cat="all">Tout</button>
                <?php foreach ($categories as $cat): ?>
                    <button class="cat-btn"
                        data-cat="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></button>
                <?php endforeach; ?>
            </div>

            <!-- Product Grid -->
            <div class="product-grid" id="productGrid">
                <?php foreach ($products as $p): ?>
                    <div class="product-card" data-cat="<?= htmlspecialchars($p['cat_name']) ?>"
                        data-search="<?= strtolower(htmlspecialchars($p['id'] . ' ' . $p['name'])) ?>"
                        onclick="addToCart(<?= htmlspecialchars(json_encode($p)) ?>)">
                        <div class="stock-tag <?= $p['stock'] <= 5 ? 'stock-low' : 'stock-ok' ?>">
                            Stock: <?= $p['stock'] ?>
                        </div>
                        <div class="name"><?= htmlspecialchars($p['name']) ?></div>
                        <div class="price"><?= number_format($p['price'], 0, ',', ' ') ?> <small>FCFA</small></div>
                        <button class="btn-add">Ajouter</button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Cart Sidebar -->
        <aside class="cart-sidebar" id="cartSidebar">
            <div class="cart-header">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="fw-bold mb-0 text-white"><i class="fa-solid fa-shopping-cart me-2"></i> Panier</h4>
                    <button class="btn btn-sm btn-outline-danger border-0 d-md-none" onclick="toggleCart()">
                        <i class="fa-solid fa-times"></i>
                    </button>
                </div>

                <div class="mb-3">
                    <label class="small text-muted text-uppercase fw-bold mb-2 d-block">Client</label>
                    <select id="clientSelect" class="form-select">
                        <option value="">Client de Passage</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?= $c['id_client'] ?>"><?= htmlspecialchars($c['fullname']) ?>
                                (<?= $c['telephone'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" id="resellerMode">
                    <label class="form-check-label small text-white" for="resellerMode">Mode Revendeur</label>
                </div>
            </div>

            <div class="cart-items" id="cartContainer">
                <div class="text-center text-muted py-5 mt-5">
                    <i class="fa-solid fa-shopping-basket fa-3x mb-3 opacity-25"></i>
                    <p>Le panier est vide</p>
                </div>
            </div>

            <div class="cart-footer">
                <div id="resellerFields" class="mb-2" style="display: none;">
                    <div class="mb-3">
                        <label class="small text-muted text-uppercase fw-bold mb-2 d-block">Partenaire</label>
                        <select id="resellerSelect" class="form-select mb-3">
                            <option value="">Choisir un partenaire</option>
                            <?php foreach ($active_resellers as $r): ?>
                                <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label class="small text-muted text-uppercase fw-bold mb-2 d-block">Prix de Revente</label>
                        <input type="number" id="finalPriceInput" class="form-control" placeholder="Prix final revente">
                    </div>
                </div>

                <div class="summary-panel-items">
                    <div class="summary-row mb-2">
                        <span class="text-muted">Total Articles</span>
                        <span class="fw-bold text-white" id="totalQty">0</span>
                    </div>
                    <div class="summary-row total">
                        <span>Total</span>
                        <span id="totalDisplay">0 <small style="font-size: 1rem;">FCFA</small></span>
                    </div>
                </div>

                <button class="btn-pay" id="btnPay" disabled onclick="openCheckoutModal()">
                    Encaisser
                </button>
            </div>
        </aside>
    </div>

    <!-- Mobile Action Bar -->
    <div class="mobile-bar">
        <div>
            <div class="text-muted small">Total</div>
            <div class="h4 fw-bold mb-0" id="totalMobile">0 FCFA</div>
        </div>
        <button class="top-cart-trigger" onclick="toggleCart()">
            <i class="fa-solid fa-shopping-cart"></i>
            <span class="cart-count-badge" id="badgeQty">0</span>
        </button>
    </div>

    <!-- Checkout Modal -->
    <div class="modal fade" id="checkoutModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-white">Finalisation du Paiement</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="text-center mb-4">
                        <div class="text-muted small text-uppercase">Montant à encaisser</div>
                        <div class="display-5 fw-bold text-success" id="checkoutTotal">0 FCFA</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-muted text-uppercase fw-bold">Méthode de Paiement</label>
                        <div class="row g-2">
                            <div class="col-4">
                                <input type="radio" class="btn-check" name="pay_method" id="pay_cash" value="cash"
                                    checked>
                                <label class="btn btn-outline-light w-100 py-3" for="pay_cash">
                                    <i class="fa-solid fa-money-bill-wave d-block mb-1"></i> Cash
                                </label>
                            </div>
                            <div class="col-4">
                                <input type="radio" class="btn-check" name="pay_method" id="pay_momo"
                                    value="mobile_money">
                                <label class="btn btn-outline-light w-100 py-3" for="pay_momo">
                                    <i class="fa-solid fa-mobile-screen-button d-block mb-1"></i> Mobile
                                </label>
                            </div>
                            <div class="col-4">
                                <input type="radio" class="btn-check" name="pay_method" id="pay_virement"
                                    value="virement">
                                <label class="btn btn-outline-light w-100 py-3" for="pay_virement">
                                    <i class="fa-solid fa-bank d-block mb-1"></i> Virement
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label small text-muted text-uppercase fw-bold">Garantie</label>
                        <select id="garantieSelect" class="form-select">
                            <option value="Sans garantie">Sans garantie</option>
                            <option value="6 mois">6 mois</option>
                            <option value="12 mois" selected>12 mois</option>
                            <option value="24 mois">24 mois</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-link text-muted text-decoration-none px-4"
                        data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-pay w-auto px-5" id="btnConfirmSale" onclick="processSale()">
                        Confirmer la Vente
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let cart = [];
        const productSearch = document.getElementById('productSearch');
        const productGrid = document.getElementById('productGrid');
        const catButtons = document.querySelectorAll('.cat-btn');
        const cartSidebar = document.getElementById('cartSidebar');
        const resellerMode = document.getElementById('resellerMode');
        const resellerFields = document.getElementById('resellerFields');

        // Toggle Reseller Fields
        resellerMode.addEventListener('change', () => {
            resellerFields.style.display = resellerMode.checked ? 'block' : 'none';
        });

        // Search Logic
        productSearch.addEventListener('input', e => {
            const term = e.target.value.toLowerCase().trim();
            document.querySelectorAll('.product-card').forEach(card => {
                const searchData = card.dataset.search;
                card.style.display = searchData.includes(term) ? '' : 'none';
            });
        });

        // Category Filter
        catButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                catButtons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                const cat = btn.dataset.cat;
                document.querySelectorAll('.product-card').forEach(card => {
                    if (cat === 'all' || card.dataset.cat === cat) card.style.display = '';
                    else card.style.display = 'none';
                });
            });
        });

        function toggleCart() {
            cartSidebar.classList.toggle('active');
        }

        function addToCart(product) {
            const existing = cart.find(item => item.id === product.id);
            if (existing) {
                if (existing.qty < product.stock) existing.qty++;
                else Swal.fire({ icon: 'warning', title: 'Stock limite atteint', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
            } else {
                cart.push({ ...product, qty: 1 });
            }
            renderCart();
            // Pulse animation on click
            if (window.innerWidth < 1200) {
                const badge = document.getElementById('badgeQty');
                badge.style.transform = 'scale(1.5)';
                setTimeout(() => badge.style.transform = 'scale(1)', 200);
            }
        }

        function updateQty(id, delta) {
            const item = cart.find(i => i.id === id);
            if (item) {
                item.qty += delta;
                if (item.qty <= 0) cart = cart.filter(i => i.id !== id);
                else if (item.qty > item.stock) {
                    item.qty = item.stock;
                    Swal.fire({ icon: 'warning', title: 'Stock maximum atteint' });
                }
            }
            renderCart();
        }

        function renderCart() {
            const container = document.getElementById('cartContainer');
            if (cart.length === 0) {
                container.innerHTML = `
                    <div class="text-center text-muted py-5 mt-5">
                        <i class="fa-solid fa-shopping-basket fa-3x mb-3 opacity-25"></i>
                        <p>Le panier est vide</p>
                    </div>`;
                document.getElementById('totalQty').innerText = '0';
                document.getElementById('totalDisplay').innerText = '0 FCFA';
                document.getElementById('totalMobile').innerText = '0 FCFA';
                document.getElementById('badgeQty').innerText = '0';
                document.getElementById('btnPay').disabled = true;
                return;
            }

            let html = '';
            let total = 0;
            let totalQty = 0;

            cart.forEach(item => {
                const subtotal = item.price * item.qty;
                total += subtotal;
                totalQty += item.qty;
                html += `
                    <div class="cart-item">
                        <div class="item-info">
                            <div class="title">${item.name}</div>
                            <div class="price">${new Intl.NumberFormat('fr-FR').format(item.price)} FCFA</div>
                        </div>
                        <div class="item-qty">
                            <button onclick="updateQty(${item.id}, -1)"><i class="fa-solid fa-minus"></i></button>
                            <span>${item.qty}</span>
                            <button onclick="updateQty(${item.id}, 1)"><i class="fa-solid fa-plus"></i></button>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
            const formattedTotal = new Intl.NumberFormat('fr-FR').format(total);
            document.getElementById('totalDisplay').innerHTML = `${formattedTotal} <small style="font-size: 1rem;">FCFA</small>`;
            document.getElementById('totalMobile').innerText = `${formattedTotal} FCFA`;
            document.getElementById('totalQty').innerText = totalQty;
            document.getElementById('badgeQty').innerText = totalQty;
            document.getElementById('btnPay').disabled = false;
        }

        function openCheckoutModal() {
            let total = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
            const isReseller = resellerMode.checked;
            const finalPriceVal = document.getElementById('finalPriceInput').value;
            const finalPrice = parseFloat(finalPriceVal);

            if (isReseller) {
                if (!document.getElementById('resellerSelect').value) {
                    Swal.fire({ icon: 'error', title: 'Partenaire manquant', text: 'Veuillez choisir un partenaire revendeur.' });
                    return;
                }
                if (finalPriceVal !== "" && !isNaN(finalPrice)) {
                    total = finalPrice;
                }
            }

            document.getElementById('checkoutTotal').innerText = new Intl.NumberFormat('fr-FR').format(total) + ' FCFA';
            new bootstrap.Modal(document.getElementById('checkoutModal')).show();
        }

        async function processSale() {
            const btn = document.getElementById('btnConfirmSale');
            if (btn.disabled) return;
            
            btn.disabled = true;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Validation...';

            const payload = {
                client_id: document.getElementById('clientSelect').value,
                reseller_id: resellerMode.checked ? document.getElementById('resellerSelect').value : null,
                final_price: resellerMode.checked ? parseFloat(document.getElementById('finalPriceInput').value) : null,
                type_paiement: document.querySelector('input[name="pay_method"]:checked').value,
                garantie: document.getElementById('garantieSelect').value,
                items: cart,
                csrf_token: document.querySelector('meta[name="csrf-token"]').content
            };

            try {
                const response = await fetch('../../backend/actions/process_sale.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const result = await response.json();

                if (result.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Vente Validée !',
                        text: result.message,
                        confirmButtonText: 'Imprimer Facture',
                        showCancelButton: true,
                        cancelButtonText: 'Nouvelle Vente'
                    }).then((res) => {
                        if (res.isConfirmed) {
                            window.location.href = `invoice.php?id=${result.sale_id}`;
                        } else {
                            location.reload();
                        }
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Erreur', text: result.message });
                    btn.disabled = false;
                    btn.innerText = 'Confirmer la Vente';
                }
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'Erreur réseau' });
                btn.disabled = false;
                btn.innerText = 'Confirmer la Vente';
            }
        }
    </script>
</body>

</html>