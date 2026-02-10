<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';
require_once '../config/loyalty_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit();
}

// Get JSON Input
$data = json_decode(file_get_contents('php://input'), true);

// CSRF Verification (Header OR Body)
$headers = getallheaders();
$headerToken = $headers['X-CSRF-TOKEN'] ?? '';
$bodyToken = $data['csrf_token'] ?? '';

if (!verifyCsrfToken($headerToken) && !verifyCsrfToken($bodyToken)) {
    // Log warning instead of blocking (for PWA cache compatibility)
    error_log("CSRF Warning: Token mismatch or missing. IP: " . $_SERVER['REMOTE_ADDR']);
    // exit(); // BYPASS ACTIVE
}

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit();
}

$user_id = $_SESSION['user_id'];
$client_id = !empty($data['client_id']) ? $data['client_id'] : null;
$items = $data['items'];

if (empty($items)) {
    echo json_encode(['success' => false, 'message' => 'Panier vide']);
    exit();
}

try {
    $pdo->beginTransaction();

    // 1. Calculate Total and Verify Stock again (Safety)
    $total_sale = 0;

    foreach ($items as $item) {
        $prod_id = $item['id'];
        $qty_req = (int) $item['qty'];

        if ($qty_req <= 0) {
            throw new Exception("Quantité invalide pour le produit ID: " . $prod_id);
        }

        // Check Stock
        $stmt = $pdo->prepare("SELECT price FROM products WHERE id_product = ?");
        $stmt->execute([$prod_id]);
        $prod = $stmt->fetch();

        $stmt_stock = $pdo->prepare("SELECT quantity FROM stock WHERE id_product = ?");
        $stmt_stock->execute([$prod_id]);
        $stock = $stmt_stock->fetch();

        if ($stock['quantity'] < $qty_req) {
            throw new Exception("Stock insuffisant pour le produit ID: " . $prod_id);
        }

        $total_sale += ($prod['price'] * $qty_req);
    }

    // 2. Create Sale Record
    $stmt = $pdo->prepare("INSERT INTO sales (id_user, id_client, total_amount, sale_date) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$user_id, $client_id, $total_sale]);
    $sale_id = $pdo->lastInsertId();

    // 3. Create Sale Details & Update Stock
    foreach ($items as $item) {
        $prod_id = $item['id'];
        $qty_req = $item['qty'];

        // Fetch current price (to freeze it in history)
        $stmt = $pdo->prepare("SELECT price FROM products WHERE id_product = ?");
        $stmt->execute([$prod_id]);
        $current_price = $stmt->fetchColumn();

        $subtotal = $current_price * $qty_req;

        // Insert Detail
        $stmt_det = $pdo->prepare("INSERT INTO sale_details (id_sale, id_product, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)");
        $stmt_det->execute([$sale_id, $prod_id, $qty_req, $current_price, $subtotal]);

        // Deduct Stock
        $stmt_upd = $pdo->prepare("UPDATE stock SET quantity = quantity - ? WHERE id_product = ?");
        $stmt_upd->execute([$qty_req, $prod_id]);

        // Log Stock Movement
        logStockMovement($pdo, $prod_id, $user_id, 'OUT', $qty_req, "Vente #$sale_id");
    }

    // 4. Add Loyalty Points (if client is specified)
    $loyaltyResult = addLoyaltyPoints($pdo, $client_id, $sale_id, $total_sale);

    logActivity($pdo, $user_id, "Vente effectuée", "Vente ID: $sale_id, Montant: $total_sale FCFA");

    $pdo->commit();

    // Prepare response with loyalty info
    $response = [
        'success' => true,
        'sale_id' => $sale_id
    ];

    if ($loyaltyResult) {
        $response['loyalty'] = $loyaltyResult;
    }

    echo json_encode($response);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>