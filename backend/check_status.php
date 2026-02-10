<?php
require_once 'config/db.php';

// 1. Check Last Sale
$stmt = $pdo->query("SELECT * FROM sales ORDER BY id_sale DESC LIMIT 1");
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h1>Diagnostic Rapport</h1>";
echo "<h2>1. Dernières Ventes (DB)</h2>";
if ($sales) {
    echo "<table border='1'><tr><th>ID</th><th>Date</th><th>Montant</th></tr>";
    foreach ($sales as $s) {
        echo "<tr><td>{$s['id_sale']}</td><td>{$s['sale_date']}</td><td>{$s['total_amount']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "Aucune vente trouvée.";
}

// 2. Check Action Logs
$stmt = $pdo->query("SELECT * FROM action_logs ORDER BY id_log DESC LIMIT 5");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>2. Logs Système</h2>";
echo "<ul>";
foreach ($logs as $l) {
    echo "<li>[{$l['timestamp']}] {$l['action']} - {$l['details']}</li>";
}
echo "</ul>";

echo "<h2>3. Heure Serveur</h2>";
echo date('Y-m-d H:i:s');
?>