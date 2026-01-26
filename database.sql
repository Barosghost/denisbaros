CREATE DATABASE IF NOT EXISTS denis_fbi_store_baros;
USE denis_fbi_store_baros;

CREATE TABLE IF NOT EXISTS users (
    id_user INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'vendeur') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS categories (
    id_category INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT
);

CREATE TABLE IF NOT EXISTS products (
    id_product INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    id_category INT,
    status ENUM('actif', 'inactif') DEFAULT 'actif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_category) REFERENCES categories(id_category)
);

CREATE TABLE IF NOT EXISTS stock (
    id_stock INT AUTO_INCREMENT PRIMARY KEY,
    id_product INT NOT NULL,
    quantity INT NOT NULL,
    last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_product) REFERENCES products(id_product)
);

CREATE TABLE IF NOT EXISTS clients (
    id_client INT AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS sales (
    id_sale INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    id_client INT,
    total_amount DECIMAL(10,2) NOT NULL,
    sale_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_user) REFERENCES users(id_user),
    FOREIGN KEY (id_client) REFERENCES clients(id_client)
);

CREATE TABLE IF NOT EXISTS sale_details (
    id_detail INT AUTO_INCREMENT PRIMARY KEY,
    id_sale INT NOT NULL,
    id_product INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (id_sale) REFERENCES sales(id_sale),
    FOREIGN KEY (id_product) REFERENCES products(id_product)
);

-- Default Admin User (username: admin, password: password)
-- NOTE: In production, use hashed passwords! This is for initial setup.
-- We will implement proper hashing in the PHP login script.
INSERT IGNORE INTO users (username, password, role) VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
