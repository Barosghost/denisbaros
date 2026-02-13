<?php
define('PAGE_ACCESS', 'sales');
require_once '../../backend/includes/auth_required.php';
require_once '../../backend/config/db.php';
require_once '../../backend/config/functions.php';
generateCsrfToken();
$pageTitle = "Point de Vente";

// Statistics for the Session (Today)
// Schema: ventes (prix_revente_final, date_vente)
$today_revenue = $pdo->query("SELECT SUM(prix_revente_final) FROM ventes WHERE DATE(date_vente) = CURDATE()")->fetchColumn() ?: 0;
$today_count = $pdo->query("SELECT COUNT(*) FROM ventes WHERE DATE(date_vente) = CURDATE()")->fetchColumn() ?: 0;

// Fetch Clients
// Schema: clients (id_client, nom_client)
$clients = $pdo->query("SELECT id_client, nom_client as fullname FROM clients ORDER BY nom_client ASC")->fetchAll();

// Fetch Categories (Just distinct strings now)
$categories = $pdo->query("SELECT DISTINCT categorie as name FROM produits WHERE categorie IS NOT NULL ORDER BY categorie ASC")->fetchAll();
// Map to array format expected by view if needed, or just use as is. 
// View uses $cat['id_category'] (for filter) and $cat['name']. 
// I'll simulate id with name for filtering.
$categories_mapped = [];
foreach ($categories as $c) {
    $categories_mapped[] = ['id_category' => $c['name'], 'name' => $c['name']];
}
$categories = $categories_mapped;

// Fetch Products (Active and with stock > 0)
// Schema: produits (id_produit, designation, categorie, prix_boutique_fixe, stock_actuel)
// Filter by stock > 0.
$products = $pdo->query("
    SELECT id_produit as id_product, 
           designation as name, 
           categorie as cat_name, 
           categorie as id_category, -- Use name as ID for filter
           prix_boutique_fixe as price, 
           stock_actuel as quantity,
           NULL as image -- Image column not in schema? Or maybe I missed it? 
           -- Schema check: 'produits' has no image column in the snippet I saw?
           -- Wait, I wrote products.php earlier and didn't see image column in my INSERT.
           -- If no image, I'll pass null.
    FROM produits 
    WHERE stock_actuel > 0 
    ORDER BY categorie ASC, designation ASC
")->fetchAll();

// Fetch Active Resellers
// Schema: revendeurs (id_revendeur, nom_partenaire, taux_commission_fixe)
$active_resellers = $pdo->query("SELECT id_revendeur as id_reseller, nom_partenaire as fullname, 'fixe' as commission_type, taux_commission_fixe as commission_value FROM revendeurs ORDER BY nom_partenaire ASC")->fetchAll();

// Fetch Packs with Components (for POS quick add)
$packRows = $pdo->query("
    SELECT p.id_pack, p.nom_pack, p.prix_pack,
           pc.id_produit, pc.quantite,
           pr.designation, pr.prix_boutique_fixe, pr.stock_actuel
    FROM packs p
    JOIN pack_composants pc ON pc.id_pack = p.id_pack
    JOIN produits pr ON pr.id_produit = pc.id_produit
    ORDER BY p.nom_pack ASC, pr.designation ASC
")->fetchAll(PDO::FETCH_ASSOC);

$packs = [];
foreach ($packRows as $row) {
    $pid = (int) $row['id_pack'];
    if (!isset($packs[$pid])) {
        $packs[$pid] = [
            'id_pack' => $pid,
            'nom_pack' => $row['nom_pack'],
            'prix_pack' => (float) $row['prix_pack'],
            'components' => []
        ];
    }
    $packs[$pid]['components'][] = [
        'id_produit' => (int) $row['id_produit'],
        'designation' => $row['designation'],
        'prix_unitaire' => (float) $row['prix_boutique_fixe'],
        'quantite' => (int) $row['quantite'],
        'stock_actuel' => (int) $row['stock_actuel'],
    ];
}
$packs = array_values($packs);

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

        /* Legacy Cart Styles Removed - Replaced by .pos-right-col in .pos-layout */

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
        /* Layout & Grid Refresh */
        .pos-content {
            min-height: calc(100vh - 160px);
            padding-bottom: 1rem;
            display: block;
            /* Remove Grid */
        }

        .grid-side {
            min-width: 0;
            flex: 1;
        }

        /* Overlay - Only for mobile */
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

        @media (max-width: 1199px) {
            .cart-overlay.active {
                display: block;
            }
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

        /* Responsive (Mobile/Tablet < 992px) */
        @media (max-width: 991px) {
            .pos-content {
                display: block !important;
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

        @media (max-width: 991px) {
            .mobile-checkout-bar {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
        }

        /* ===== 2-COLUMN LAYOUT (REFRESHED) ===== */
        .pos-layout {
            display: flex;
            height: calc(100vh - 100px);
            overflow-x: auto; /* Fail-safe: allow scroll if too narrow */
            overflow-y: hidden;
            gap: 0;
            width: 100%;
        }

        .pos-center-col {
            flex: 1;
            overflow-y: auto;
            background: rgba(15, 23, 42, 0.1);
            border-right: 1px solid rgba(255, 255, 255, 0.05);
        }

        .pos-right-col {
            width: 450px;
            min-width: 450px;
            flex-shrink: 0;
            background: rgba(15, 23, 42, 0.9);
            backdrop-filter: blur(30px);
            border-left: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            flex-direction: column;
            z-index: 10;
        }

        /* Cart Card Style (Model-inspired) */
        .cart-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            margin-bottom: 1rem;
            padding: 1rem;
            position: relative;
            transition: all 0.3s ease;
        }

        .cart-card:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(99, 102, 241, 0.2);
        }

        .cart-card-main {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .cart-card-img {
            width: 70px;
            height: 70px;
            background: #000;
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .cart-card-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .cart-card-img i {
            color: rgba(255, 255, 255, 0.2);
        }

        .cart-card-info {
            flex: 1;
        }

        .cart-card-title {
            font-weight: 700;
            color: #fff;
            font-size: 1rem;
            margin-bottom: 4px;
        }

        .cart-card-prices {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .price-old {
            text-decoration: line-through;
            color: #64748b;
            font-size: 0.8rem;
        }

        .price-new {
            color: #10b981;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .cart-card-subtotal {
            color: #f59e0b;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .cart-card-actions {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 5px;
        }

        .btn-remove-item {
            color: #64748b;
            transition: color 0.3s;
            cursor: pointer;
        }

        .btn-remove-item:hover {
            color: #ef4444;
        }

        /* Nested Option Styles */
        .cart-card-options {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .cart-option-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
        }

        /* Summary Panel Enhancement */
        .summary-panel {
            box-shadow: -10px 0 30px rgba(0, 0, 0, 0.3);
        }

        .client-select-wrapper {
            position: relative;
        }

        .client-select-wrapper i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #f59e0b;
            font-size: 1.2rem;
        }

        .custom-pos-select {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(245, 158, 11, 0.02)) !important;
            border: 1px solid rgba(245, 158, 11, 0.2) !important;
            color: #fff !important;
            border-radius: 12px !important;
            height: 55px;
            font-weight: 600;
            padding-left: 50px !important;
        }

        .btn-finalize-pos {
            background: linear-gradient(135deg, #fbbf24 0%, #d97706 100%);
            border: none;
            color: #000;
            border-radius: 16px;
            font-weight: 800;
            letter-spacing: 0.5px;
            box-shadow: 0 10px 20px rgba(217, 119, 6, 0.3);
            transition: all 0.3s ease;
        }

        .btn-finalize-pos:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 15px 30px rgba(217, 119, 6, 0.5);
            filter: brightness(1.1);
        }

        .trust-pill {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 8px 12px;
            font-size: 0.65rem;
            font-weight: 600;
            color: #94a3b8;
            text-transform: uppercase;
            text-align: center;
        }

        /* Search Suggestion Styling */
        .search-results-overlay {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #1e293b;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 0 0 12px 12px;
            z-index: 1000;
            max-height: 400px;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
        }

        .search-suggestion-item {
            padding: 12px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .search-suggestion-item:hover {
            background: rgba(99, 102, 241, 0.1);
        }

        .stock-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.6rem;
            font-weight: 800;
            color: #fff;
            z-index: 2;
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

                <!-- Top Bar Cart Trigger Area (Visible only on Mobile) -->
                <div id="topCartBar" class="d-lg-none d-flex justify-content-end mb-3">
                    <button class="top-cart-trigger" onclick="toggleCart()">
                        <i class="fa-solid fa-cart-shopping"></i>
                        <span class="cart-count-badge" id="topCartCount">0</span>
                    </button>
                </div>

                <div class="pos-content">
                    <div class="pos-layout">
                        <!-- Column 1: Product Selection (LEFT) -->
                        <div class="pos-center-col border-0">
                            <div class="p-4">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h1 class="h4 fw-bold text-white mb-0"><i
                                            class="fa-solid fa-boxes-stacked me-2"></i>Sélection d'Articles</h1>
                                    <div class="d-flex align-items-center gap-2">
                                        <span
                                            class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-20 px-3 py-1 rounded-pill extra-small">
                                            Catalogue Actif
                                        </span>
                                    </div>
                                </div>

                                <!-- Search & Filter Area (LEFT) -->
                                <div class="mb-5 d-flex flex-column gap-3">
                                    <div class="search-container">
                                        <i class="fa-solid fa-magnifying-glass"></i>
                                        <input type="text" id="searchProduct"
                                            class="form-control bg-dark text-white border-0 py-3 ps-5 shadow-sm"
                                            style="border-radius: 12px; background: rgba(30, 41, 59, 0.6) !important;"
                                            placeholder="Rechercher un produit ou scanner un code-barres...">

                                        <!-- Dynamic Search Results Dropdown -->
                                        <div id="searchSuggestions" class="search-results-overlay"
                                            style="display: none;"></div>
                                    </div>

                                    <div class="d-flex gap-2 overflow-auto pb-2 custom-scrollbar">
                                        <a href="#" class="category-pill filter-btn active" data-filter="all">Tous
                                            les articles</a>
                                        <?php foreach ($categories as $cat): ?>
                                            <a href="#" class="category-pill filter-btn"
                                                data-filter="<?= htmlspecialchars($cat['id_category']) ?>">
                                                <?= htmlspecialchars($cat['name']) ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- Product Grid (LEFT) -->
                                <div id="productGridContainer">
                                    <div class="row g-3" id="productList">
                                        <?php foreach ($products as $prod): ?>
                                            <div class="col-xl-3 col-lg-4 col-md-6 col-6 product-item"
                                                data-name="<?= strtolower($prod['name']) ?>"
                                                data-category="<?= htmlspecialchars($prod['cat_name']) ?>">
                                                <div class="pos-product-card h-100 d-flex flex-column"
                                                    onclick="addToCart(<?= $prod['id_product'] ?>, '<?= addslashes($prod['name']) ?>', <?= $prod['price'] ?>, <?= $prod['quantity'] ?>, '<?= $prod['image'] ?>')">
                                                    <div
                                                        class="stock-badge <?= $prod['quantity'] > 5 ? 'bg-success' : ($prod['quantity'] > 0 ? 'bg-warning text-dark' : 'bg-danger') ?>">
                                                        STK: <?= $prod['quantity'] ?>
                                                    </div>
                                                    <div class="p-3">
                                                        <div class="text-white fw-bold small text-truncate mb-1">
                                                            <?= htmlspecialchars($prod['name']) ?>
                                                        </div>
                                                        <div class="text-primary fw-bold small">
                                                            <?= number_format($prod['price'], 0, ',', ' ') ?> FCFA
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Column 2: Cart & Summary (RIGHT) -->
                        <div class="pos-right-col cart-side">
                            <div class="summary-panel p-4 h-100 d-flex flex-column">

                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h2 class="h5 fw-bold text-white mb-0"><i
                                            class="fa-solid fa-cart-shopping me-2 text-warning"></i>Votre Panier</h2>
                                    <button
                                        class="btn btn-link text-danger text-decoration-none extra-small fw-bold p-0"
                                        onclick="clearCart()">
                                        <i class="fa-solid fa-trash-can me-1"></i>VIDER
                                    </button>
                                </div>

                                <!-- Banner Reseller -->
                                <div id="resellerBanner"
                                    class="alert alert-warning py-2 mb-3 border-0 rounded-3 d-none animate__animated animate__fadeIn"
                                    style="background: #f59e0b20; border-left: 4px solid #f59e0b !important;">
                                    <div class="d-flex align-items-center">
                                        <i class="fa-solid fa-crown text-warning me-2"></i>
                                        <span class="extra-small fw-bold text-uppercase text-warning">Mode
                                            Revendeur</span>
                                    </div>
                                </div>

                                <!-- Cart List (Cards) (RIGHT) -->
                                <div class="cart-cards-container custom-scrollbar mb-4" id="cartTableBody"
                                    style="flex: 1; overflow-y: auto;">
                                    <div
                                        class="d-flex flex-column align-items-center justify-content-center py-5 opacity-20 text-white">
                                        <i class="fa-solid fa-basket-shopping fa-3x mb-3 text-white"></i>
                                        <div class="fw-medium">Panier Vide</div>
                                    </div>
                                </div>

                                <!-- Client Area (RIGHT) -->
                                <div class="mb-4">
                                    <div class="client-select-wrapper">
                                        <i class="fa-solid fa-user-circle"></i>
                                        <select id="clientSelect" class="form-select custom-pos-select ps-5">
                                            <option value="">Client de Passage (Espèce)</option>
                                            <?php foreach ($clients as $client): ?>
                                                <option value="<?= $client['id_client'] ?>">
                                                    <?= htmlspecialchars($client['fullname']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- Reseller Toggle (RIGHT) -->
                                <div
                                    class="mb-4 p-3 rounded-3 border border-white border-opacity-10 bg-white bg-opacity-5">
                                    <div
                                        class="form-check form-switch d-flex justify-content-between align-items-center p-0">
                                        <label class="form-check-label text-white small fw-bold"
                                            for="resellerModeToggle">Vente par Revendeur</label>
                                        <input class="form-check-input ms-0 mt-0" type="checkbox"
                                            id="resellerModeToggle" onchange="toggleResellerMode()">
                                    </div>

                                    <div id="resellerSelectContainer" style="display: none;"
                                        class="mt-3 animate__animated animate__fadeIn">
                                        <select id="resellerSelect"
                                            class="form-select form-select-sm bg-dark text-white border-secondary border-opacity-50 mb-3"
                                            onchange="updateResellerValues()">
                                            <option value="">Sélectionner un revendeur...</option>
                                            <?php foreach ($active_resellers as $res): ?>
                                                <option value="<?= $res['id_reseller'] ?>">
                                                    <?= htmlspecialchars($res['fullname']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="input-group input-group-sm">
                                            <input type="number" id="resellerFinalPrice"
                                                class="form-control bg-dark text-white border-secondary border-opacity-50"
                                                placeholder="Prix final..." oninput="calculateResellerMargin()">
                                            <button class="btn btn-warning btn-sm fw-bold" type="button"
                                                onclick="openCheckoutSummary()">Ok</button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Totals Breakdown (RIGHT) -->
                                <div class="totals-area mb-4">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="text-muted small">Sous-total:</span>
                                        <span class="text-white fw-medium" id="subtotalDisplay">0 FCFA</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2"
                                        id="resellerDiscountRow" style="display: none !important;">
                                        <span class="text-muted small">Remise revendeur:</span>
                                        <span class="text-danger fw-bold" id="resellerDiscountDisplay">-0 FCFA</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="text-muted small">Garantie & Support:</span>
                                        <span class="text-info fw-bold" id="warrantyCostDisplay">0 FCFA</span>
                                    </div>
                                    <div class="border-top border-white border-opacity-10 pt-3 mt-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-white fw-bold">TOTAL À PAYER:</span>
                                            <span class="text-success h3 fw-bold mb-0" id="totalAmount">0 FCFA</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Action Button (RIGHT) -->
                                <button class="btn btn-finalize-pos w-100 py-3 shadow-lg mb-4"
                                    onclick="openCheckoutSummary()">
                                    <span class="h5 fw-bold mb-0 text-dark">ENCAISSER MAINTENANT</span>
                                </button>

                                <!-- Trust Badges (Inside Summary Panel) -->
                                <div class="mt-auto">
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <div class="trust-pill"><i
                                                    class="fa-solid fa-shield-halved text-primary me-2"></i>Paiement
                                                Sécurisé</div>
                                        </div>
                                        <div class="col-6">
                                            <div class="trust-pill"><i
                                                    class="fa-solid fa-clock-rotate-left text-warning me-2"></i>Historique
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="trust-pill"><i
                                                    class="fa-solid fa-file-invoice text-info me-2"></i>Facture Auto
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="trust-pill p-1 d-flex justify-content-center gap-1 opacity-75">
                                                <i class="fa-brands fa-cc-visa"></i>
                                                <i class="fa-brands fa-cc-mastercard"></i>
                                                <i class="fa-solid fa-money-check-dollar"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div> <!-- /.summary-panel -->
                        </div> <!-- /.pos-right-col -->
                    </div> <!-- /.pos-layout -->
                </div> <!-- /.pos-content -->
            </div> <!-- /.fade-in -->

            <!-- Checkout Summary Modal -->
            <div class="modal fade" id="checkoutSummaryModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div
                        class="modal-content bg-dark border border-white border-opacity-10 text-white rounded-4 shadow-lg">
                        <div class="modal-header border-bottom border-white border-opacity-10 bg-white bg-opacity-5">
                            <h5 class="modal-title fw-bold">Résumé de la Vente</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-4">
                            <div class="mb-4">
                                <div class="text-muted extra-small text-uppercase fw-bold mb-3">Articles à facturer
                                </div>
                                <div id="summaryItemsList" class="custom-scrollbar"
                                    style="max-height: 200px; overflow-y: auto;">
                                    <!-- Will be populated by JS -->
                                </div>
                            </div>

                            <div class="p-3 rounded-3 mb-4"
                                style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1);">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-muted">Total Net</span>
                                    <span class="fw-bold fs-5" id="summaryTotal">0 FCFA</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-0">
                                    <span class="text-muted">Mode de Paiement</span>
                                    <select id="paymentMethod"
                                        class="form-select form-select-sm bg-dark text-white border-secondary w-auto py-1 px-3">
                                        <option value="cash">Espèce / Cash</option>
                                        <option value="om">Orange Money</option>
                                        <option value="momo">MTN MoMo</option>
                                        <option value="card">Carte Bancaire</option>
                                        <option value="transfer">Virement</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="form-label text-muted extra-small text-uppercase fw-bold">Montant
                                        Reçu</label>
                                    <div class="input-group">
                                        <input type="number" id="amtReceived"
                                            class="form-control bg-dark text-white border-secondary fw-bold"
                                            placeholder="0">
                                        <span class="input-group-text bg-dark border-secondary text-muted">FCFA</span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <label class="form-label text-muted extra-small text-uppercase fw-bold">Monnaie
                                        à rendre</label>
                                    <div class="h4 fw-bold text-warning mt-2 mb-0" id="changeAmount">0 FCFA</div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer border-top border-white border-opacity-10 p-4">
                            <button type="button" class="btn btn-outline-light rounded-pill px-4"
                                data-bs-dismiss="modal">Modifier</button>
                            <button type="button" class="btn btn-premium rounded-pill px-5 py-2 fw-bold shadow-lg"
                                onclick="finalConfirmSale()">
                                <i class="fa-solid fa-check-circle me-2"></i>CONFIRMER ET ENCAISSER
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sale Success Modal -->
            <div class="modal fade" id="saleSuccessModal" tabindex="-1" data-bs-backdrop="static">
                <div class="modal-dialog modal-dialog-centered">
                    <div
                        class="modal-content bg-dark border border-success border-opacity-25 text-white rounded-4 shadow-lg overflow-hidden">
                        <div class="modal-body p-5 text-center">
                            <div class="mb-4">
                                <div class="bg-success bg-opacity-10 text-success rounded-circle d-inline-flex align-items-center justify-content-center"
                                    style="width: 80px; height: 80px;">
                                    <i class="fa-solid fa-check fa-3x"></i>
                                </div>
                            </div>
                            <h3 class="fw-bold mb-2">Vente Enregistrée !</h3>
                            <p class="text-muted mb-4">La transaction a été traitée avec succès et le stock a été
                                mis à jour.</p>

                            <div class="d-grid gap-3">
                                <button class="btn btn-premium btn-lg rounded-pill" id="btnPrintInvoice">
                                    <i class="fa-solid fa-print me-2"></i>Imprimer la Facture
                                </button>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <button class="btn btn-outline-light w-100 rounded-pill py-2"
                                            onclick="location.reload()">
                                            <i class="fa-solid fa-plus me-2"></i>Nouvelle Vente
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <button class="btn btn-outline-success w-100 rounded-pill py-2"
                                            id="btnShareWhatsApp">
                                            <i class="fa-brands fa-whatsapp me-2"></i>Partager
                                        </button>
                                    </div>
                                </div>
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
    <script>
        // Packs data for POS (used in pos.js)
        const packsData = <?= json_encode($packs) ?>;
    </script>
    <script src="../assets/js/pos.js?v=2.6"></script>
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

        function toggleCart() {
            const cartSide = document.querySelector('.cart-side');
            const overlay = document.getElementById('cartOverlay');

            cartSide.classList.toggle('active');
            overlay.classList.toggle('active');
            document.body.style.overflow = cartSide.classList.contains('active') ? 'hidden' : 'auto';
        }

        function openMobileCart() {
            const cartSide = document.querySelector('.cart-side');
            const overlay = document.getElementById('cartOverlay');
            if (window.innerWidth < 992 && !cartSide.classList.contains('active')) {
                cartSide.classList.add('active');
                overlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        }

        window.addEventListener('resize', () => {
            if (window.innerWidth >= 992) {
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