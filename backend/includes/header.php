<?php
// Ensure session is initialized with secure configuration
require_once __DIR__ . '/session_init.php';

// Lancer le backup automatique (vérifie si c'est le moment une fois par mois)
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    require_once __DIR__ . '/../actions/auto_backup.php';
}

require_once __DIR__ . '/../config/functions.php'; // Ensure functions are available
generateCsrfToken(); // Generate token for the session

// Technical Notifications
$stmt_notif = $pdo->query("SELECT COUNT(*) FROM sav_dossiers WHERE statut_sav = 'en_attente'");
$pending_internal_count = $stmt_notif->fetchColumn();

// Daily Report Notifications (Super Admin Only)
$unread_reports_count = 0;
if (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin') {
    $stmt_rep = $pdo->query("SELECT COUNT(*) FROM rapports_journaliers WHERE statut_approbation = 'en_attente'");
    $unread_reports_count = $stmt_rep->fetchColumn();
}
?>
<nav class="navbar navbar-custom">
    <div class="d-flex justify-content-between align-items-center w-100 flex-wrap gap-2">
        <div class="d-flex align-items-center flex-grow-1 min-w-0">
            <button type="button" id="sidebarCollapse" class="btn btn-outline-light d-md-none me-2 flex-shrink-0" aria-label="Menu">
                <i class="fa-solid fa-bars"></i>
            </button>
            <h4 class="mb-0 text-white text-truncate">
                <?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'DENIS FBI STORE' ?>
            </h4>
        </div>
        <div class="d-flex align-items-center flex-shrink-0 gap-1 gap-md-2">
            <?php if ($unread_reports_count > 0 && $_SESSION['role'] === 'super_admin'): ?>
                <a href="daily_reports.php" class="text-info position-relative p-2" title="Rapports en attente">
                    <i class="fa-solid fa-clipboard-list fa-lg"></i>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-info" style="font-size: 0.5rem; padding: 0.2rem 0.35rem;"><?= $unread_reports_count ?></span>
                </a>
            <?php endif; ?>
            <?php if ($pending_internal_count > 0): ?>
                <a href="repairs.php" class="text-warning position-relative p-2" title="SAV en attente">
                    <i class="fa-solid fa-bell fa-lg"></i>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.5rem; padding: 0.2rem 0.35rem;"><?= $pending_internal_count ?></span>
                </a>
            <?php endif; ?>
            <a href="help.php" class="text-white-50 p-2 hover-white d-none d-sm-inline-block" title="Aide"><i class="fa-solid fa-circle-question fa-lg"></i></a>
            <span class="text-muted d-none d-md-inline me-2">Bienvenue, <strong class="text-white"><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></strong></span>
            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width: 40px; height: 40px; font-size: 0.9rem;">
                <?= strtoupper(mb_substr($_SESSION['username'] ?? 'U', 0, 1)) ?>
            </div>
            <a href="../../backend/auth/logout.php" class="btn btn-danger px-2 px-md-3 rounded-pill"
                onclick="return confirmAction(this.href, 'Voulez-vous vraiment vous déconnecter ?')" title="Déconnexion">
                <i class="fa-solid fa-power-off"></i>
            </a>
        </div>
    </div>
</nav>
<?php if (!empty($_GET['logout_blocked'])): ?>
<div class="alert alert-warning alert-dismissible fade show mx-2 mt-2 mb-0" role="alert">
    <i class="fa-solid fa-exclamation-triangle me-2"></i>
    Vous devez soumettre votre rapport journalier avant de pouvoir vous déconnecter.
    <a href="daily_reports.php" class="alert-link">Soumettre un rapport</a>
    <?php if (($_SESSION['role'] ?? '') === 'super_admin'): ?>
    | <a href="../../backend/auth/logout.php?force=1" class="alert-link">Déconnecter quand même</a>
    <?php endif; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
</div>
<?php endif; ?>