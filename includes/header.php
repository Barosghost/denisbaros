<?php
// Ensure session is started if not already
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<nav class="navbar navbar-custom">
    <div class="d-flex justify-content-between align-items-center w-100">
        <div class="d-flex align-items-center">
            <button type="button" id="sidebarCollapse" class="btn btn-outline-light d-md-none me-3">
                <i class="fa-solid fa-bars"></i>
            </button>
            <h4 class="mb-0 text-white">
                <?= isset($pageTitle) ? $pageTitle : 'DENIS FBI STORE' ?>
            </h4>
        </div>
        <div class="d-flex align-items-center">
            <span class="text-muted me-3">Bienvenue, <strong class="text-white">
                    <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?>
                </strong></span>
            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center"
                style="width: 40px; height: 40px;">
                <?= strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)) ?>
            </div>
            <a href="../auth/logout.php" class="btn btn-danger ms-3 px-3 rounded-pill"
                onclick="return confirmAction(this.href, 'Voulez-vous vraiment vous déconnecter ?')">
                <i class="fa-solid fa-power-off"></i>
            </a>
        </div>
    </div>
</nav>