<?php
session_start();

if (!isset($_SESSION['logged_in'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../config/db.php';
$pageTitle = "Gestion des Clients";

// Handle Add Client
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $fullname = trim($_POST['fullname']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);

    try {
        $stmt = $pdo->prepare("INSERT INTO clients (fullname, phone, email) VALUES (?, ?, ?)");
        $stmt->execute([$fullname, $phone, $email]);
        $success = "Client enregistré avec succès.";
    } catch (PDOException $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

// Handle Update Client
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update') {
    $id_client = $_POST['id_client'];
    $fullname = trim($_POST['fullname']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);

    try {
        $stmt = $pdo->prepare("UPDATE clients SET fullname = ?, phone = ?, email = ? WHERE id_client = ?");
        $stmt->execute([$fullname, $phone, $email, $id_client]);
        $success = "Client mis à jour.";
    } catch (PDOException $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

// Handle Delete Client
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM clients WHERE id_client = ?");
        $stmt->execute([$id]);
        header("Location: clients.php");
        exit();
    } catch (PDOException $e) {
        $error = "Impossible de supprimer : ce client a déjà effectué des achats.";
    }
}

$clients = $pdo->query("SELECT * FROM clients ORDER BY created_at DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clients | DENIS FBI STORE</title>
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

            <div class="fade-in mt-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="text-white">Répertoire Clients</h5>
                    <button class="btn btn-premium" data-bs-toggle="modal" data-bs-target="#addClientModal">
                        <i class="fa-solid fa-user-plus me-2"></i> Nouveau Client
                    </button>
                </div>

                <div class="card bg-dark border-0 glass-panel">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-dark table-hover mb-0 align-middle">
                                <thead class="bg-transparent border-bottom border-secondary">
                                    <tr>
                                        <th class="py-3 px-4">Nom Complet</th>
                                        <th class="py-3">Téléphone</th>
                                        <th class="py-3">Email</th>
                                        <th class="py-3">Inscrit le</th>
                                        <th class="py-3 text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($clients)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">Aucun client enregistré</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($clients as $client): ?>
                                            <tr>
                                                <td class="px-4 fw-bold">
                                                    <?= htmlspecialchars($client['fullname']) ?>
                                                </td>
                                                <td>
                                                    <?= htmlspecialchars($client['phone'] ?? '-') ?>
                                                </td>
                                                <td>
                                                    <?= htmlspecialchars($client['email'] ?? '-') ?>
                                                </td>
                                                <td class="text-muted">
                                                    <?= date('d/m/Y', strtotime($client['created_at'])) ?>
                                                </td>
                                                <td class="text-end">
                                                    <button class="btn btn-sm btn-outline-primary me-2"
                                                        onclick="openEditClientModal(<?= $client['id_client'] ?>, '<?= addslashes($client['fullname']) ?>', '<?= addslashes($client['phone'] ?? '') ?>', '<?= addslashes($client['email'] ?? '') ?>')">
                                                        <i class="fa-solid fa-pen"></i>
                                                    </button>
                                                    <a href="clients.php?delete=<?= $client['id_client'] ?>"
                                                        class="btn btn-sm btn-outline-danger"
                                                        onclick="return confirmAction(this.href, 'Supprimer ce client ?');">
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
        </div>
    </div>

    <!-- Add Client Modal -->
    <div class="modal fade" id="addClientModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark text-white border-0 glass-panel">
                <div class="modal-header border-bottom border-secondary">
                    <h5 class="modal-title">Nouveau Client</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="clients.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label text-muted">Nom Complet</label>
                            <input type="text" name="fullname" class="form-control bg-dark text-white border-secondary"
                                required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted">Téléphone</label>
                            <input type="text" name="phone" class="form-control bg-dark text-white border-secondary"
                                placeholder="ex: 699000000">
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted">Email (Optionnel)</label>
                            <input type="email" name="email" class="form-control bg-dark text-white border-secondary">
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

    <!-- Edit Client Modal -->
    <div class="modal fade" id="editClientModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark text-white border-0 glass-panel">
                <div class="modal-header border-bottom border-secondary">
                    <h5 class="modal-title">Modifier Client</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="clients.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id_client" id="edit_id_client">
                        <div class="mb-3">
                            <label class="form-label text-muted">Nom Complet</label>
                            <input type="text" name="fullname" id="edit_fullname"
                                class="form-control bg-dark text-white border-secondary" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted">Téléphone</label>
                            <input type="text" name="phone" id="edit_phone"
                                class="form-control bg-dark text-white border-secondary">
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted">Email (Optionnel)</label>
                            <input type="email" name="email" id="edit_email"
                                class="form-control bg-dark text-white border-secondary">
                        </div>
                    </div>
                    <div class="modal-footer border-top border-secondary">
                        <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-premium">Mettre à jour</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
</body>

</html>
