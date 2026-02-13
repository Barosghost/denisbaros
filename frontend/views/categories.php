<?php
define('PAGE_ACCESS', 'categories');
require_once '../../backend/includes/auth_required.php';
require_once '../../backend/config/db.php';
$pageTitle = "Gestion des Catégories";

// Créer la table categories si elle n'existe pas (compatibilité anciennes bases)
try {
    $pdo->query("SELECT 1 FROM categories LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id_category INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL UNIQUE,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

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
    <link rel="stylesheet" href="../assets/css/style.css?v=1.5">
</head>

<body>
    <div class="wrapper">
        <?php include '../../backend/includes/sidebar.php'; ?>
        <div id="content">
            <?php include '../../backend/includes/header.php'; ?>

            <div class="fade-in mt-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
                    <div class="flex-grow-1" style="max-width: 500px;">
                        <div class="input-group">
                            <span class="input-group-text bg-dark border-secondary text-muted px-3"
                                style="border-radius: 12px 0 0 12px;"><i class="fa-solid fa-search"></i></span>
                            <input type="text" id="tableSearch"
                                class="form-control bg-dark text-white border-secondary py-2"
                                placeholder="Rechercher une catégorie..." style="border-radius: 0 12px 12px 0;">
                        </div>
                    </div>
                    <button class="btn btn-premium px-4" data-bs-toggle="modal" data-bs-target="#addCatModal">
                        <i class="fa-solid fa-plus-circle me-2"></i>Nouvelle Catégorie
                    </button>
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success border-0 bg-success bg-opacity-10 text-success mb-4"><?= $success ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger mb-4"><?= $error ?></div>
                <?php endif; ?>

                <div class="card bg-dark border-0 glass-panel shadow-lg">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-dark table-hover mb-0 align-middle">
                                <thead class="border-bottom border-secondary border-opacity-20">
                                    <tr class="text-muted small">
                                        <th class="py-3 px-4" style="width: 250px;">NOM</th>
                                        <th class="py-3">DESCRIPTION</th>
                                        <th class="py-3 text-center" style="width: 150px;">PRODUITS</th>
                                        <th class="py-3 text-end px-4" style="width: 150px;">ACTIONS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($categories)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">Aucune catégorie trouvée
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($categories as $c): ?>
                                            <tr class="category-row">
                                                <td class="px-4">
                                                    <div class="d-flex align-items-center">
                                                        <div class="icon-box bg-primary bg-opacity-10 text-primary me-3 text-center"
                                                            style="width: 40px; height: 40px; border-radius: 10px; line-height: 40px;">
                                                            <i class="fa-solid fa-tag"></i>
                                                        </div>
                                                        <span
                                                            class="fw-bold text-white"><?= htmlspecialchars($c['name']) ?></span>
                                                    </div>
                                                </td>
                                                <td class="text-muted small">
                                                    <?= htmlspecialchars($c['description'] ?: 'Aucune description') ?>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-secondary bg-opacity-10 text-muted fw-normal">
                                                        <?= $pdo->query("SELECT COUNT(*) FROM produits WHERE categorie = (SELECT name FROM categories WHERE id_category = " . $c['id_category'] . ")")->fetchColumn() ?>
                                                        items
                                                    </span>
                                                </td>
                                                <td class="text-end px-4">
                                                    <button onclick="confirmDel(<?= $c['id_category'] ?>)"
                                                        class="btn btn-sm btn-outline-danger border-0">
                                                        <i class="fa-solid fa-trash-can"></i>
                                                    </button>
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
        </div>
    </div>

    <!-- Add Modal -->
    <div class="modal fade" id="addCatModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark text-white glass-panel shadow-lg border-secondary border-opacity-10">
                <form action="categories.php" method="POST">
                    <div class="modal-header border-secondary border-opacity-20 px-4">
                        <h5 class="modal-title fw-bold">Nouvelle Catégorie</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">NOM DE LA CATÉGORIE</label>
                            <input type="text" name="name" class="form-control bg-dark text-white border-secondary ps-3"
                                placeholder="Ex: Boissons, Snacks..." required>
                        </div>
                        <div class="mb-0">
                            <label class="form-label text-muted small fw-bold">DESCRIPTION (OPTIONNEL)</label>
                            <textarea name="description" class="form-control bg-dark text-white border-secondary ps-3"
                                rows="3" placeholder="Brève description..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-secondary border-opacity-10 p-4">
                        <button type="submit" class="btn btn-premium w-100 py-2 fw-bold">Créer la Catégorie</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
    <script>
        function confirmDel(id) {
            if (confirm("Supprimer cette catégorie ? Cela n'affectera pas les produits existants (ils deviendront sans catégorie).")) {
                window.location.href = 'categories.php?delete=' + id;
            }
        }

        document.getElementById('tableSearch').addEventListener('input', function () {
            const term = this.value.toLowerCase().trim();
            document.querySelectorAll('.category-row').forEach(row => {
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(term) ? '' : 'none';
            });
        });
    </script>
</body>

</html>