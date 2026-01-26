<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit();
}

// Get JSON Input
$data = json_decode(file_get_contents('php://input'), true);

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
        $qty_req = $item['qty'];

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
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'sale_id' => $sale_id]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>