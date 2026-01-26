<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

require_once '../config/db.php';
$pageTitle = "Gestion des Catégories";

// Handle Add
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $name = trim($_POST['name']);
    $desc = trim($_POST['description']);

    try {
        $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
        $stmt->execute([$name, $desc]);
        $success = "Catégorie ajoutée.";
    } catch (PDOException $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id_category = ?");
        $stmt->execute([$id]);
        header("Location: categories.php");
        exit();
    } catch (PDOException $e) {
        $error = "Impossible de supprimer : cette catégorie est probablement liée à des produits.";
    }
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY id_category DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catégories | DENIS FBI STORE</title>
    <link href="../assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- Custom CSS -->
</head>
</head>

<body>

    <div class="wrapper">
        <?php include '../includes/sidebar.php'; ?>

        <div id="content">
            <?php include '../includes/header.php'; ?>

            <?php if (isset($success)): ?>
                <div class="alert alert-success mt-3">
                    <?= $success ?>
                </div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger mt-3">
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <div class="fade-in mt-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="text-white">Liste des Catégories</h5>
                    <button class="btn btn-premium" data-bs-toggle="modal" data-bs-target="#addCatModal">
                        <i class="fa-solid fa-plus me-2"></i> Nouvelle Catégorie
                    </button>
                </div>

                <div class="row">
                    <div class="col-md-8">
                        <div class="card bg-dark border-0 glass-panel">
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-dark table-hover mb-0 align-middle">
                                        <thead class="bg-transparent border-bottom border-secondary">
                                            <tr>
                                                <th class="py-3 px-4">Nom</th>
                                                <th class="py-3">Description</th>
                                                <th class="py-3 text-end px-4">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($categories)): ?>
                                                <tr>
                                                    <td colspan="3" class="text-center text-muted py-4">Aucune catégorie
                                                        trouvée</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($categories as $cat): ?>
                                                    <tr>
                                                        <td class="px-4 fw-bold text-primary">
                                                            <?= htmlspecialchars($cat['name']) ?>
                                                        </td>
                                                        <td class="text-muted">
                                                            <?= htmlspecialchars($cat['description']) ?>
                                                        </td>
                                                        <td class="text-end px-4">
                                                            <a href="categories.php?delete=<?= $cat['id_category'] ?>"
                                                                class="btn btn-sm btn-outline-danger"
                                                                onclick="return confirm('Supprimer cette catégorie ?');">
                                                                <i class="fa-solid fa-trash"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Mini Help Section or Stats could go here in col-md-4 -->
                </div>

                <div class="mt-4">
                    <a href="products.php" class="btn btn-outline-light"><i class="fa-solid fa-arrow-left me-2"></i>
                        Retour aux Produits</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Modal -->
    <div class="modal fade" id="addCatModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark text-white border-0 glass-panel">
                <div class="modal-header border-bottom border-secondary">
                    <h5 class="modal-title">Nouvelle Catégorie</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="categories.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label text-muted">Nom de la catégorie</label>
                            <input type="text" name="name" class="form-control bg-dark text-white border-secondary"
                                required placeholder="Ex: Ordinateurs">
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted">Description</label>
                            <textarea name="description" class="form-control bg-dark text-white border-secondary"
                                rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-top border-secondary">
                        <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-premium">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
</body>

</html>
