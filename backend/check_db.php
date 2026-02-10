<?php
require_once 'config/db.php';
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "Tables:\n";
foreach ($tables as $table) {
    echo "- $table\n";
    $desc = $pdo->query("DESCRIBE $table")->fetchAll();
    foreach ($desc as $col) {
        echo "  " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
}
?>