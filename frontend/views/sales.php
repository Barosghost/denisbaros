<?php
session_start();

if (!isset($_SESSION['logged_in'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../../backend/config/db.php';
require_once '../../backend/config/functions.php';
generateCsrfToken();
$pageTitle = "Point de Vente";

// Statistics for the Session (Today)
$today_revenue = $pdo->query("SELECT SUM(total_amount) FROM sales WHERE DATE(sale_date) = CURDATE()")->fetchColumn() ?: 0;
$today_count = $pdo->query("SELECT COUNT(*) FROM sales WHERE DATE(sale_date) = CURDATE()")->fetchColumn() ?: 0;

// Fetch Clients
$clients = $pdo->query("SELECT id_client, fullname FROM clients ORDER BY fullname ASC")->fetchAll();

// Fetch Categories
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

// Fetch Products (Active and with stock > 0)
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
    <meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?? '' ?>">
    <title>Vente Premium | DENIS FBI STORE</title>
    <link href="../assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=1.4">
    <link rel="manifest" href="../manifest.json">
    <script src="../assets/vendor/sweetalert2/sweetalert2.all.min.js"></script>
    <style>
        /* ===== PREMIUM POS INTERFACE STYLES ===== */

        /* Search Bar Enhancement */
        .search-container {
            position: relative;
        }

        .search-container i {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 1.1rem;
            z-index: 1;
        }

        .search-container input {
            padding-left: 55px !important;
            font-size: 0.95rem;
            border-radius: 16px !important;
            transition: all 0.3s ease;
            background: linear-gradient(145deg, rgba(30, 41, 59, 0.5), rgba(15, 23, 42, 0.7)) !important;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.08) !important;
        }

        .search-container input:focus {
            background: linear-gradient(145deg, rgba(30, 41, 59, 0.7), rgba(15, 23, 42, 0.9)) !important;
            border-color: var(--primary-color) !important;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1), 0 8px 24px rgba(0, 0, 0, 0.2);
            transform: translateY(-2px);
        }

        /* Product Card Enhancement - Larger & More Premium */
        .pos-product-card {
            background: linear-gradient(145deg, rgba(30, 41, 59, 0.6), rgba(15, 23, 42, 0.4));
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 24px;
            overflow: hidden;
            transition: all 0.5s cubic-bezier(0.165, 0.84, 0.44, 1);
            cursor: pointer;
            position: relative;
            backdrop-filter: blur(16px);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
        }

        .pos-product-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, transparent 0%, rgba(99, 102, 241, 0.05) 100%);
            opacity: 0;
            transition: opacity 0.4s ease;
            z-index: 0;
        }

        .pos-product-card:hover {
            transform: translateY(-12px) scale(1.02);
            background: linear-gradient(145deg, rgba(30, 41, 59, 0.9), rgba(15, 23, 42, 0.7));
            border-color: rgba(99, 102, 241, 0.4);
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.4),
                0 0 32px rgba(99, 102, 241, 0.2),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
        }

        .pos-product-card:hover::before {
            opacity: 1;
        }

        .pos-product-card:active {
            transform: translateY(-8px) scale(0.98);
        }

        /* Image Wrapper - Larger Display */
        .pos-img-wrapper {
            height: 180px;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.03) 0%, rgba(0, 0, 0, 0.1) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            overflow: hidden;
        }

        .pos-img-wrapper img {
            max-height: 100%;
            width: 100%;
            object-fit: cover;
            transition: transform 0.7s cubic-bezier(0.165, 0.84, 0.44, 1);
            filter: brightness(0.95);
        }

        .pos-product-card:hover .pos-img-wrapper img {
            transform: scale(1.2) rotate(2deg);
            filter: brightness(1.1);
        }

        /* Stock Indicator - Enhanced */
        .stock-indicator {
            position: absolute;
            top: 14px;
            right: 14px;
            z-index: 2;
        }

        .stock-indicator .badge {
            padding: 6px 12px;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.3px;
            text-transform: uppercase;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(8px);
        }

        /* Product Content - Better Typography */
        .pos-product-card .p-3 {
            padding: 1.25rem !important;
            position: relative;
            z-index: 1;
        }

        .pos-product-card .text-muted.small {
            font-size: 0.7rem;
            letter-spacing: 1px;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #64748b !important;
        }

        .pos-product-card .text-white.fw-bold {
            font-size: 1rem;
            font-weight: 600;
            line-height: 1.4;
            margin-bottom: 0.75rem;
            color: #f1f5f9 !important;
        }

        .pos-product-card .h5.text-primary {
            font-size: 1.4rem !important;
            font-weight: 700;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Category Pills - Enhanced */
        .category-pill {
            white-space: nowrap;
            padding: 12px 28px;
            border-radius: 100px;
            background: linear-gradient(145deg, rgba(15, 23, 42, 0.7), rgba(30, 41, 59, 0.5));
            color: #cbd5e1;
            border: 1px solid rgba(255, 255, 255, 0.08);
            transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(8px);
        }

        .category-pill.active {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            border-color: transparent;
            box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4),
                0 0 16px rgba(139, 92, 246, 0.3);
            transform: translateY(-2px);
        }

        .category-pill:hover:not(.active) {
            background: linear-gradient(145deg, rgba(30, 41, 59, 0.9), rgba(15, 23, 42, 0.7));
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
            border-color: rgba(255, 255, 255, 0.15);
        }

        /* Session Stats - Modernized */
        .session-stat-card {
            background: linear-gradient(145deg, rgba(30, 41, 59, 0.6), rgba(15, 23, 42, 0.4));
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 20px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: all 0.3s ease;
            backdrop-filter: blur(12px);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
        }

        .session-stat-card:hover {
            background: linear-gradient(145deg, rgba(30, 41, 59, 0.8), rgba(15, 23, 42, 0.6));
            border-color: rgba(255, 255, 255, 0.12);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
        }

        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            box-shadow: inset 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        /* Cart Panel - Glass Morphism */
        .cart-side {
            position: fixed;
            top: 0;
            right: -100%;
            width: 480px;
            height: 100vh;
            z-index: 9999 !important;
            transition: right 0.5s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex !important;
            flex-direction: column;
            box-shadow: -16px 0 48px rgba(0, 0, 0, 0.5);
        }

        .cart-side.active {
            right: 0;
        }

        .cart-panel-fixed {
            background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%) !important;
            border-left: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 0;
            height: 100%;
            max-height: 100vh;
            display: flex !important;
            flex-direction: column;
            overflow: hidden;
            position: relative;
        }

        .cart-panel-fixed::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 200px;
            background: radial-gradient(circle at top right, rgba(99, 102, 241, 0.1), transparent);
            pointer-events: none;
        }

        /* Cart List Items - Enhanced */
        .cart-list {
            flex: 1;
            overflow-y: auto;
            padding: 1.25rem;
        }

        .cart-item-row {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 16px;
            padding: 12px 16px;
            margin-bottom: 10px;
            border: 1px solid rgba(255, 255, 255, 0.06);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 14px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .cart-item-row:hover {
            border-color: rgba(99, 102, 241, 0.3);
            background: rgba(99, 102, 241, 0.05);
            transform: translateX(4px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .cart-item-info {
            flex: 1;
            min-width: 0;
        }

        .cart-item-info .text-white {
            font-weight: 600;
            font-size: 0.95rem;
        }

        .cart-item-info .text-muted {
            font-size: 0.85rem;
            color: #94a3b8 !important;
        }

        .cart-item-qty {
            width: 90px;
        }

        .cart-item-qty input {
            background: rgba(15, 23, 42, 0.8) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: white !important;
            text-align: center;
            font-weight: 700;
            border-radius: 10px;
            padding: 8px;
        }

        /* Cart Top Bar Trigger */
        .top-cart-trigger {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 16px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
            box-shadow: 0 8px 20px rgba(99, 102, 241, 0.4),
                0 0 16px rgba(139, 92, 246, 0.2);
            font-size: 1rem;
        }

        .top-cart-trigger:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(99, 102, 241, 0.5),
                0 0 24px rgba(139, 92, 246, 0.3);
        }

        .top-cart-trigger .cart-count-badge {
            background: rgba(255, 255, 255, 0.25);
            padding: 4px 12px;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 800;
            min-width: 32px;
            text-align: center;
        }

        /* Layout & Grid */
        .pos-content {
            min-height: calc(100vh - 250px);
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
            padding-bottom: 1rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .pos-content.cart-active {
            grid-template-columns: 1fr;
        }

        .grid-side {
            min-width: 0;
        }

        /* Overlay */
        .cart-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(8px);
            z-index: 1040;
        }

        .cart-overlay.active {
            display: block;
        }

        /* Checkout Button Animation */
        @keyframes checkout-pulse {

            0%,
            100% {
                transform: scale(1);
                box-shadow: 0 4px 16px rgba(16, 185, 129, 0.3);
            }

            50% {
                transform: scale(1.03);
                box-shadow: 0 8px 32px rgba(16, 185, 129, 0.6);
            }
        }

        .btn-checkout-active {
            animation: checkout-pulse 2s ease-in-out infinite;
            background: linear-gradient(135deg, #10b981, #059669) !important;
            border: none;
        }

        /* Mobile Cart Toggle */
        .mobile-cart-toggle {
            display: none;
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 1000;
            width: 68px;
            height: 68px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            border: none;
            box-shadow: 0 12px 32px rgba(99, 102, 241, 0.5);
            font-size: 1.6rem;
            align-items: center;
            justify-content: center;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .mobile-cart-toggle:active {
            transform: scale(0.92);
        }

        .cart-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            width: 26px;
            height: 26px;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid #0f172a;
            font-weight: 800;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .pos-content {
                grid-template-columns: 1fr !important;
            }

            .cart-side {
                position: fixed;
                top: 0;
                right: -100%;
                width: 100% !important;
                max-width: 480px;
                height: 100dvh;
                z-index: 1050;
                transition: right 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                display: flex !important;
            }

            .cart-side.active {
                right: 0;
            }

            .cart-panel-fixed {
                border-radius: 0;
                max-height: 100vh;
            }

            .mobile-cart-toggle {
                display: flex;
            }
        }

        /* Custom Scrollbar */
        .custom-scrollbar::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(99, 102, 241, 0.3);
            border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(99, 102, 241, 0.5);
        }

        /* Mobile Checkout Bar */
        .mobile-checkout-bar {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(180deg, #1e293b, #0f172a);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding: 1rem 1.5rem;
            z-index: 999;
            box-shadow: 0 -8px 32px rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(16px);
        }

        @media (max-width: 1199px) {
            .mobile-checkout-bar {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include '../../backend/includes/sidebar.php'; ?>

        <div id="content">
            <?php include '../../backend/includes/header.php'; ?>

            <div class="fade-in">

                <!-- Session Header -->
                <div class="row g-2 mb-4 d-none d-lg-flex">
                    <div class="col-xl-6 col-lg-5">
                        <div class="d-flex flex-column gap-1">
                            <h3 class="text-white fw-bold mb-0">Terminal de Vente</h3>
                            <div class="d-flex align-items-center gap-2">
                                <span
                                    class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-20 px-3 py-1 rounded-pill extra-small">
                                    <i class="fa-solid fa-circle fa-2xs me-2 animate-pulse"></i>Session Active
                                </span>
                                <span class="text-muted extra-small">Dernière mise à jour: <?= date('H:i') ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-lg-3 col-md-6">
                        <div class="session-stat-card">
                            <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                                <i class="fa-solid fa-receipt"></i>
                            </div>
                            <div>
                                <div class="text-muted extra-small text-uppercase fw-bold">Ventes du jour</div>
                                <div class="text-white h5 fw-bold mb-0"><?= $today_count ?> <span
                                        class="small fw-normal text-muted">trans.</span></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Bar Cart Trigger Area (Visible when items added) -->
                <div id="topCartBar" class="d-none justify-content-end mb-3">
                    <button class="top-cart-trigger" onclick="toggleCart()">
                        <i class="fa-solid fa-cart-shopping"></i>
                        <span>Mon Panier</span>
                        <span class="cart-count-badge" id="topCartCount">0</span>
                    </button>
                </div>

                <div class="pos-content">
                    <!-- Left: Products -->
                    <div class="grid-side">
                        <!-- Search & Filters -->
                        <div class="mb-4 d-flex flex-column gap-3">
                            <div class="search-container">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input type="text" id="searchProduct"
                                    class="form-control bg-dark text-white border-0 py-3 ps-5"
                                    style="border-radius: 12px; background: rgba(30, 41, 59, 0.6) !important;"
                                    placeholder="Rechercher un produit ou scanner un code-barres...">
                            </div>

                            <div class="d-flex gap-2 overflow-auto pb-2 custom-scrollbar">
                                <a href="#" class="category-pill filter-btn active" data-filter="all">Tous les
                                    articles</a>
                                <?php foreach ($categories as $cat): ?>
                                    <a href="#" class="category-pill filter-btn" data-filter="<?= $cat['id_category'] ?>">
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Product Grid -->
                        <div class="row g-2 g-md-3" id="productList">
                            <?php foreach ($products as $prod): ?>
                                <div class="col-xl-3 col-lg-4 col-md-6 col-6 product-item"
                                    data-name="<?= strtolower($prod['name']) ?>"
                                    data-category="<?= $prod['id_category'] ?>">
                                    <div class="pos-product-card h-100 d-flex flex-column"
                                        onclick="addToCart(<?= $prod['id_product'] ?>, '<?= addslashes($prod['name']) ?>', <?= $prod['price'] ?>, <?= $prod['quantity'] ?>)">
                                        <div class="stock-indicator">
                                            <span
                                                class="badge <?= $prod['quantity'] > 5 ? 'bg-success' : ($prod['quantity'] > 0 ? 'bg-warning text-dark' : 'bg-danger') ?> rounded-pill">
                                                Stock: <?= $prod['quantity'] ?>
                                            </span>
                                        </div>
                                        <div class="pos-img-wrapper">
                                            <?php if ($prod['image']): ?>
                                                <img src="../<?= $prod['image'] ?>">
                                            <?php else: ?>
                                                <i class="fa-solid fa-cube fa-2x text-muted opacity-25"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="p-3">
                                            <div class="text-muted small text-uppercase fw-bold mb-1">
                                                <?= htmlspecialchars($prod['cat_name']) ?>
                                            </div>
                                            <div class="text-white fw-bold text-truncate mb-2">
                                                <?= htmlspecialchars($prod['name']) ?>
                                            </div>
                                            <div class="h5 text-primary fw-bold mb-0">
                                                <?= number_format($prod['price'], 0, ',', ' ') ?> FCFA
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Right: Cart (Offcanvas) -->
                    <div class="cart-side">
                        <div class="card cart-panel-fixed border-0">

                            <!-- Client Area -->
                            <div class="px-4 py-3 bg-white bg-opacity-5">
                                <label class="text-muted extra-small fw-bold text-uppercase mb-2">Sélectionner un
                                    Client</label>
                                <select id="clientSelect"
                                    class="form-select bg-dark text-white border-secondary border-opacity-50">
                                    <option value="">Client de Passage (Espèce)</option>
                                    <?php foreach ($clients as $client): ?>
                                        <option value="<?= $client['id_client'] ?>">
                                            <?= htmlspecialchars($client['fullname']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- List -->
                            <div class="cart-list custom-scrollbar" id="cartTableBody">
                                <!-- JS Populated -->
                                <div
                                    class="d-flex flex-column align-items-center justify-content-center h-100 opacity-20">
                                    <i class="fa-solid fa-cart-shopping fa-3x mb-3"></i>
                                    <div class="fw-medium">Votre panier est vide</div>
                                    <div class="small">Sélectionnez des articles à gauche</div>
                                </div>
                            </div>

                            <!-- Footer / Totals -->
                            <div class="p-4 bg-dark border-top border-secondary border-opacity-20">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-muted small">Articles (Total)</span>
                                    <span class="text-white fw-bold" id="totalItems">0</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <span class="text-white h5 fw-bold mb-0">TOTAL</span>
                                    <span class="text-success h3 fw-bold mb-0" id="totalAmount">0 FCFA</span>
                                </div>
                                <button class="btn btn-premium w-100 py-3 fw-bold text-uppercase"
                                    onclick="processSale()">
                                    <i class="fa-solid fa-check-double me-2"></i>Finaliser la commande
                                </button>
                                <div class="text-center mt-3">
                                    <span class="extra-small text-muted"><i
                                            class="fa-solid fa-shield-halved me-1"></i>Paiement Sécurisé</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>


                <!-- Cart Overlay Backdrop -->
                <div class="cart-overlay" id="cartOverlay" onclick="toggleCart()"></div>

                <!-- Mobile Sticky Checkout Notifier -->
                <div class="mobile-checkout-bar d-xl-none" id="mobileCheckoutBar" onclick="toggleMobileCart()">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon bg-primary bg-opacity-20 text-primary mb-0"
                            style="width: 40px; height: 40px;">
                            <i class="fa-solid fa-shopping-basket"></i>
                        </div>
                        <div>
                            <div class="text-white fw-bold" id="mobileCartTotal">0 FCFA</div>
                            <div class="extra-small text-muted"><span id="mobileCartCount">0</span> articles
                                sélectionné(s)</div>
                        </div>
                    </div>
                    <button class="btn btn-primary rounded-pill px-4 btn-sm fw-bold">
                        Détails <i class="fa-solid fa-chevron-right ms-2"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    </div>

    <!-- Scripts -->
    <script src="../assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <script src="../assets/vendor/jquery/jquery.min.js"></script>
    <script src="../assets/js/app.js"></script>
    <script src="../assets/js/pos.js?v=1.8"></script>
    <script>
        // Category Filtering
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                const filter = btn.getAttribute('data-filter');
                document.querySelectorAll('.product-item').forEach(item => {
                    item.style.display = (filter === 'all' || item.getAttribute('data-category') == filter) ? 'block' : 'none';
                });
            });
        });

        function clearCart() {
            showConfirmation("Voulez-vous vraiment vider tout le panier ?", () => {
                cart = [];
                renderCart();
                showAlert('Panier Vidé', 'Tous les articles ont été retirés.', 'success');
            });
        }

        // Cart Toggle Logic (Floating Button)
        function toggleCart() {
            const cartSide = document.querySelector('.cart-side');
            const overlay = document.getElementById('cartOverlay');

            cartSide.classList.toggle('active');
            overlay.classList.toggle('active');

            // Prevent body scroll when cart is open
            document.body.style.overflow = cartSide.classList.contains('active') ? 'hidden' : 'auto';
        }

        function openMobileCart() {
            const cartSide = document.querySelector('.cart-side');
            const overlay = document.getElementById('cartOverlay');
            if (window.innerWidth <= 1200 && !cartSide.classList.contains('active')) {
                cartSide.classList.add('active');
                overlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        }

        // Close cart on resize if it's open
        window.addEventListener('resize', () => {
            if (window.innerWidth > 1200) {
                const cartSide = document.querySelector('.cart-side');
                const overlay = document.getElementById('cartOverlay');
                if (cartSide.classList.contains('active')) {
                    cartSide.classList.remove('active');
                    overlay.classList.remove('active');
                    document.body.style.overflow = 'auto';
                }
            }
        });
    </script>
</body>

</html>
