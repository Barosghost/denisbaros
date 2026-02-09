<nav id="sidebar">
    <div class="sidebar-header">
        <h3><i class="fa-solid fa-store me-2"></i> DENIS FBI STORE</h3>
    </div>

    <ul class="list-unstyled components">
        <li>
            <a href="dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-line"></i> Tableau de bord
            </a>
        </li>
        <li>
            <a href="products.php" class="<?= basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-box-open"></i> Produits
            </a>
        </li>
        <li>
            <a href="stock.php" class="<?= basename($_SERVER['PHP_SELF']) == 'stock.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-boxes-stacked"></i> Stock
            </a>
        </li>
        <li>
            <a href="sales.php" class="<?= basename($_SERVER['PHP_SELF']) == 'sales.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-cart-shopping"></i> Ventes (POS)
            </a>
        </li>
        <li>
            <a href="clients.php" class="<?= basename($_SERVER['PHP_SELF']) == 'clients.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-users"></i> Clients
            </a>
        </li>
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <li>
                <a href="stock_movements.php"
                    class="<?= basename($_SERVER['PHP_SELF']) == 'stock_movements.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-exchange-alt"></i> Mouvements Stock
                </a>
            </li>
            <li>
                <a href="loyalty.php" class="<?= basename($_SERVER['PHP_SELF']) == 'loyalty.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-star"></i> Programme Fidélité
                </a>
            </li>
            <li>
                <a href="users.php" class="<?= basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-user-shield"></i> Utilisateurs
                </a>
            </li>
            <li>
                <a href="reports.php" class="<?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-file-invoice-dollar"></i> Rapports
                </a>
            </li>
        <?php endif; ?>
    </ul>

    <div class="mt-auto p-3 border-top border-secondary">
        <a href="../auth/logout.php" class="btn btn-outline-danger w-100"
            onclick="return confirmAction(this.href, 'Voulez-vous vraiment vous déconnecter ?')">
            <i class="fa-solid fa-power-off me-2"></i> Déconnexion
        </a>
    </div>
</nav>