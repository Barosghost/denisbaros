<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../backend/config/db.php';

echo "--- DIAGNOSTIC START ---\n";

try {
    // 1. Check Columns in Sales Table
    echo "1. Checking 'sales' table columns...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM sales");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $has_margin = in_array('reseller_margin', $columns);
    $has_commission = in_array('commission_amount', $columns);
    $has_status_margin = in_array('margin_status', $columns);
    $has_status_commission = in_array('commission_status', $columns);

    echo "   - reseller_margin: " . ($has_margin ? "EXISTS" : "MISSING") . "\n";
    echo "   - commission_amount: " . ($has_commission ? "EXISTS" : "MISSING") . "\n";
    echo "   - margin_status: " . ($has_status_margin ? "EXISTS" : "MISSING") . "\n";
    echo "   - commission_status: " . ($has_status_commission ? "EXISTS" : "MISSING") . "\n";

    if (!$has_margin) {
        echo "CREATE ERROR: reseller_margin missing!\n";
    }

    // 2. Test Reports Query (Main Sales)
    echo "\n2. Testing Reports Main Query...\n";
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-t');

    $query = "SELECT s.*, u.username, c.fullname as client_name, r.fullname as reseller_name
              FROM sales s 
              LEFT JOIN users u ON s.id_user = u.id_user 
              LEFT JOIN clients c ON s.id_client = c.id_client 
              LEFT JOIN resellers r ON s.id_reseller = r.id_reseller
              WHERE DATE(s.sale_date) BETWEEN ? AND ? 
              ORDER BY s.sale_date DESC LIMIT 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$start_date, $end_date]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   - Query Executed Successfully\n";
    if ($sale) {
        echo "   - Sample Row Fetched (ID: " . $sale['id_sale'] . ")\n";
        echo "   - reseller_margin value: " . ($sale['reseller_margin'] ?? 'NULL/UNDEFINED') . "\n";
    } else {
        echo "   - No sales found in current month (not an error)\n";
    }

    // 3. Test Reports Reseller Query
    echo "\n3. Testing Reports Reseller Query...\n";
    $sql_resellers = "SELECT r.fullname, 
                         COUNT(s.id_sale) as sale_count, 
                         SUM(s.total_amount) as revenue_generated, 
                         SUM(s.reseller_margin) as commission_total,
                         SUM(CASE WHEN s.margin_status = 'paid' THEN s.reseller_margin ELSE 0 END) as commission_paid,
                         SUM(CASE WHEN s.margin_status = 'pending' THEN s.reseller_margin ELSE 0 END) as commission_due
                  FROM resellers r
                  LEFT JOIN sales s ON r.id_reseller = s.id_reseller AND DATE(s.sale_date) BETWEEN ? AND ?
                  WHERE r.is_active = 1 OR s.id_sale IS NOT NULL
                  GROUP BY r.id_reseller LIMIT 1";
    $stmt = $pdo->prepare($sql_resellers);
    $stmt->execute([$start_date, $end_date]);
    $reseller = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   - Query Executed Successfully\n";

    // 4. Test Commissions Query
    echo "\n4. Testing Commissions Query...\n";
    $sql_comm = "SELECT s.*, r.fullname as reseller_name, c.fullname as client_name 
                 FROM sales s 
                 JOIN resellers r ON s.id_reseller = r.id_reseller 
                 LEFT JOIN clients c ON s.id_client = c.id_client
                 WHERE s.sale_type = 'revendeur' AND s.margin_status = 'pending'
                 ORDER BY s.sale_date DESC LIMIT 1";
    $pdo->query($sql_comm);
    echo "   - Query Executed Successfully\n";

} catch (PDOException $e) {
    echo "\n!!! SQL ERROR !!!\n";
    echo $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "\n!!! GENERIC ERROR !!!\n";
    echo $e->getMessage() . "\n";
}

echo "\n--- DIAGNOSTIC END ---\n";
?>