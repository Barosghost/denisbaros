<?php
require_once 'config/db.php';

try {
    // Create product_images table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS product_images (
        id_image INT AUTO_INCREMENT PRIMARY KEY,
        id_product INT NOT NULL,
        image_path VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT__STAMP,
        FOREIGN KEY (id_product) REFERENCES products(id_product) ON DELETE CASCADE
    )";
    // Note: fixing the typo in TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    $sql = "CREATE TABLE IF NOT EXISTS product_images (
        id_image INT AUTO_INCREMENT PRIMARY KEY,
        id_product INT NOT NULL,
        image_path VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_product) REFERENCES products(id_product) ON DELETE CASCADE
    )";
    $pdo->exec($sql);
    echo "Table product_images created or already exists.\n";

    // Check products table columns
    $stmt = $pdo->query("DESCRIBE products");
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Products columns: " . implode(", ", $cols) . "\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>