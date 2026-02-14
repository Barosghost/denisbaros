<nav id="sidebar">
    <?php
    if (!function_exists('getSessionRole')) {
        require_once __DIR__ . '/roles.php';
    }
    $session_role = getSessionRole();
    ?>
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
            <a href="daily_reports.php"
                class="<?= basename($_SERVER['PHP_SELF']) == 'daily_reports.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-clipboard-check"></i> Rapports Journaliers
            </a>
        </li>

        <?php if (in_array($session_role, ['admin', 'vendeur', 'super_admin'])): ?>
            <li class="mt-2"><span class="text-muted small px-3 text-uppercase fw-bold" style="font-size: 0.65rem;">Commerce</span></li>
            <li>
                <a href="products.php" class="<?= basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-box-open"></i> Produits
                </a>
            </li>
            <?php if (in_array($session_role, ['admin', 'super_admin'])): ?>
                <li>
                    <a href="packs.php" class="<?= basename($_SERVER['PHP_SELF']) == 'packs.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-layer-group"></i> Packs Produits
                    </a>
                </li>
            <?php endif; ?>
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
        <?php endif; ?>

        <?php if (in_array($session_role, ['chef_technique'])): ?>
            <li class="mt-2"><span class="text-muted small px-3 text-uppercase fw-bold" style="font-size: 0.65rem;">Contexte</span></li>
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
        <?php endif; ?>

        <?php if (in_array($session_role, ['admin', 'technicien', 'super_admin', 'chef_technique'])): ?>
            <li class="mt-2"><span class="text-muted small px-3 text-uppercase fw-bold" style="font-size: 0.65rem;">SAV</span></li>
            <li>
                <a href="repairs.php"
                    class="<?= in_array(basename($_SERVER['PHP_SELF']), ['repairs.php', 'repair_reception.php', 'repair_details.php']) ? 'active' : '' ?>">
                    <i class="fa-solid fa-screwdriver-wrench"></i> SAV & Réparations
                </a>
            </li>
            <?php if (in_array($session_role, ['admin', 'super_admin', 'chef_technique'])): ?>
                <li>
                    <a href="technicians.php"
                        class="<?= basename($_SERVER['PHP_SELF']) == 'technicians.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-user-gear"></i> Techniciens
                    </a>
                </li>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (in_array($session_role, ['vendeur', 'admin', 'super_admin'])): ?>
            <li class="mt-2"><span class="text-muted small px-3 text-uppercase fw-bold" style="font-size: 0.65rem;">Partenaires</span></li>
            <li>
                <a href="resellers.php" class="<?= basename($_SERVER['PHP_SELF']) == 'resellers.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-user-tag"></i> Revendeurs
                </a>
            </li>
            <li>
                <a href="commissions.php"
                    class="<?= basename($_SERVER['PHP_SELF']) == 'commissions.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-hand-holding-dollar"></i> Commissions
                </a>
            </li>
        <?php endif; ?>

        <?php if (in_array($session_role, ['vendeur'])): ?>
            <li>
                <a href="repair_reception.php" class="<?= basename($_SERVER['PHP_SELF']) == 'repair_reception.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-truck-ramp-box"></i> Envoyer au SAV
                </a>
            </li>
        <?php endif; ?>

        <?php if ($session_role === 'super_admin'): ?>
            <li class="mt-3">
                <span class="text-muted small px-3 text-uppercase fw-bold" style="font-size: 0.65rem;">
                    <i class="fa-solid fa-crown me-1" style="color: #f5576c;"></i> Super Admin
                </span>
            </li>
            <li>
                <a href="supervision.php"
                    class="<?= basename($_SERVER['PHP_SELF']) == 'supervision.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-chart-pie"></i> Supervision
                </a>
            </li>
            <li>
                <a href="users.php" class="<?= basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-users-gear"></i> Gestion Utilisateurs
                </a>
            </li>
            <li>
                <a href="audit_logs.php" class="<?= basename($_SERVER['PHP_SELF']) == 'audit_logs.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-clipboard-list"></i> Logs d'Audit
                </a>
            </li>
            <li>
                <a href="system_settings.php"
                    class="<?= basename($_SERVER['PHP_SELF']) == 'system_settings.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-gears"></i> Paramètres Système
                </a>
            </li>
        <?php endif; ?>

        <?php if (in_array($session_role, ['admin'])): ?>
            <li class="mt-3">
                <span class="text-muted small px-3 text-uppercase fw-bold" style="font-size: 0.65rem;">Administration</span>
            </li>
            <li>
                <a href="users.php" class="<?= basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-user-shield"></i> Utilisateurs
                </a>
            </li>
            <li>
                <a href="audit_logs.php" class="<?= basename($_SERVER['PHP_SELF']) == 'audit_logs.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-clipboard-list"></i> Logs (lecture)
                </a>
            </li>
        <?php endif; ?>

        <?php if (in_array($session_role, ['admin', 'super_admin'])): ?>
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
                <a href="reports.php" class="<?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-file-invoice-dollar"></i> Rapports
                </a>
            </li>
            <li>
                <a href="global_report.php"
                    class="<?= basename($_SERVER['PHP_SELF']) == 'global_report.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-earth-africa"></i> Rapport Global
                </a>
            </li>
        <?php endif; ?>

        <?php if (in_array($session_role, ['vendeur', 'chef_technique'])): ?>
            <li>
                <a href="reports.php" class="<?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-file-invoice-dollar"></i> Rapports
                </a>
            </li>
        <?php endif; ?>

        <?php if (in_array($session_role, ['vendeur'])): ?>
            <li>
                <a href="loyalty.php" class="<?= basename($_SERVER['PHP_SELF']) == 'loyalty.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-star"></i> Fidélité
                </a>
            </li>
        <?php endif; ?>
    </ul>

    <div class="mt-auto p-3 border-top border-secondary">
        <a href="help.php" class="btn btn-outline-info w-100 mb-2">
            <i class="fa-solid fa-circle-info me-2"></i> Aide & Manuel
        </a>
        <a href="../../backend/auth/logout.php" class="btn btn-outline-danger w-100"
            onclick="return confirmAction(this.href, 'Voulez-vous vraiment vous déconnecter ?')">
            <i class="fa-solid fa-power-off me-2"></i> Déconnexion
        </a>
    </div>
</nav>
