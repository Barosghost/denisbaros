<?php
session_start();
if (isset($_SESSION['logged_in'])) {
    header("Location: views/dashboard.php");
    exit();
}
require_once 'config/functions.php';
generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion | DENIS FBI STORE</title>
    <!-- Bootstrap 5 CSS -->
    <link href="assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <!-- Custom Premium CSS -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <!-- Icons -->
    <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">
</head>

<body>

    <div class="login-container">
        <!-- Background Animations -->
        <div class="bg-shape shape-1"></div>
        <div class="bg-shape shape-2"></div>

        <div class="glass-panel login-card animate-in">
            <div class="row g-0">
                <!-- LEft Side: Branding -->
                <div class="col-md-5 login-brand-side d-none d-md-flex">
                    <div
                        class="mb-4 d-inline-block p-4 rounded-circle bg-white bg-opacity-10 text-white shadow-lg backdrop-blur">
                        <i class="fa-solid fa-store fa-4x"></i>
                    </div>
                    <h2 class="text-white mb-2 fw-bold">DENIS FBI STORE</h2>
                    <p class="text-white text-opacity-75 mb-0">Gestion Commerciale & Stock</p>
                </div>

                <!-- Right Side: Form -->
                <div class="col-md-7 login-form-side bg-dark bg-opacity-25">
                    <div class="text-center mb-4 d-md-none">
                        <i class="fa-solid fa-store fa-2x text-primary"></i>
                        <h3 class="fw-bold mt-2">DENIS FBI STORE</h3>
                    </div>

                    <h3 class="fw-bold mb-1">Bienvenue</h3>
                    <p class="text-muted mb-4">Connectez-vous à votre espace.</p>

                    <?php if (isset($_GET['error'])): ?>
                        <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger fade show mb-4 d-flex align-items-center"
                            role="alert">
                            <i class="fa-solid fa-circle-exclamation me-2"></i>
                            <div><strong>Erreur :</strong> Identifiants incorrects.</div>
                        </div>
                    <?php endif; ?>

                    <form action="auth/login.php" method="POST">
                        <?= getCsrfInput() ?>
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="username" name="username"
                                placeholder="Nom d'utilisateur" required>
                            <label for="username"><i
                                    class="fa-solid fa-user me-2 text-primary opacity-50"></i>Identifiant</label>
                        </div>
                        <div class="mb-4">
                            <div class="form-floating position-relative">
                                <input type="password" class="form-control" id="password" name="password"
                                    placeholder="Mot de passe" required>
                                <label for="password"><i class="fa-solid fa-lock me-2 text-primary opacity-50"></i>Mot
                                    de passe</label>
                                <span
                                    class="position-absolute top-50 end-0 translate-middle-y me-3 cursor-pointer z-index-10"
                                    onclick="togglePassword('password', 'toggleIconLogin')">
                                    <i class="fa-solid fa-eye text-muted" id="toggleIconLogin"></i>
                                </span>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-premium py-3 shadow-lg">
                                <span class="fs-5">Se Connecter</span>
                            </button>
                        </div>
                    </form>

                    <div class="text-center mt-4">
                        <small class="text-muted opacity-50">&copy; 2026 Denis FBI Store</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
</body>

</html>