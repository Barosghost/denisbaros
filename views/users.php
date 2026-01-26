<?php
session_start();

// Access Control - Admin Only
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

require_once '../config/db.php';
$pageTitle = "Gestion des Utilisateurs";

// Handle User Creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $new_user = trim($_POST['username']);
    $new_pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $new_role = $_POST['role'];

    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt->execute([$new_user, $new_pass, $new_role]);
        $success = "Utilisateur ajouté avec succès.";
    } catch (PDOException $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

// Handle User Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update') {
    $id_user = $_POST['id_user'];
    $username = trim($_POST['username']);
    $role = $_POST['role'];
    $password = $_POST['password'];

    try {
        if (!empty($password)) {
            // Update with password
            $pass_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, role = ? WHERE id_user = ?");
            $stmt->execute([$username, $pass_hash, $role, $id_user]);
        } else {
            // Update without password
            $stmt = $pdo->prepare("UPDATE users SET username = ?, role = ? WHERE id_user = ?");
            $stmt->execute([$username, $role, $id_user]);
        }
        $success = "Utilisateur mis à jour.";
    } catch (PDOException $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

// Handle User Deletion
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    // Prevent self-deletion
    if ($id != $_SESSION['user_id']) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id_user = ?");
        $stmt->execute([$id]);
        header("Location: users.php");
        exit();
    }
}

// Fetch Users
$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Utilisateurs | DENIS FBI STORE</title>
    <link href="../assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- Custom CSS -->
</head>
</head>

<body>

    <div class="wrapper">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>

        <!-- Page Content -->
        <div id="content">
            <!-- Header -->
            <?php include '../includes/header.php'; ?>

            <!-- Messages -->
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

            <!-- Content -->
            <div class="fade-in mt-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="text-white">Liste des Utilisateurs</h5>
                    <button class="btn btn-premium" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="fa-solid fa-plus me-2"></i> Ajouter un utilisateur
                    </button>
                </div>

                <div class="card bg-dark border-0 glass-panel">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-dark table-hover mb-0 align-middle text-center">
                                <thead class="bg-transparent border-bottom border-secondary">
                                    <tr>
                                        <!-- ID Column Removed -->
                                        <th class="py-3">Utilisateur</th>
                                        <th class="py-3">Rôle</th>
                                        <th class="py-3">Date de création</th>
                                        <th class="py-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <!-- ID Cell Removed -->
                                            <td class="fw-bold">
                                                <?= htmlspecialchars($user['username']) ?>
                                            </td>
                                            <td>
                                                <?php if ($user['role'] == 'admin'): ?>
                                                    <span class="badge bg-danger">Administrateur</span>
                                                <?php else: ?>
                                                    <span class="badge bg-primary">Vendeur</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-muted">
                                                <?= date('d/m/Y H:i', strtotime($user['created_at'])) ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary me-2"
                                                    onclick="openEditUserModal(<?= $user['id_user'] ?>, '<?= addslashes($user['username']) ?>', '<?= $user['role'] ?>')">
                                                    <i class="fa-solid fa-pen"></i>
                                                </button>
                                                <?php if ($user['id_user'] != $_SESSION['user_id']): ?>
                                                    <a href="users.php?delete=<?= $user['id_user'] ?>"
                                                        class="btn btn-sm btn-outline-danger"
                                                        onclick="return confirmAction(this.href, 'Êtes-vous sûr de vouloir supprimer cet utilisateur ?');">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted small">Moi</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark text-white border-0 glass-panel">
                <div class="modal-header border-bottom border-secondary">
                    <h5 class="modal-title">Nouvel Utilisateur</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <form action="users.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label text-muted">Nom d'utilisateur</label>
                            <input type="text" name="username" class="form-control bg-dark text-white border-secondary"
                                required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted">Mot de passe</label>
                            <div class="input-group">
                                <input type="password" name="password" id="add_password"
                                    class="form-control bg-dark text-white border-secondary" required>
                                <button class="btn btn-outline-secondary" type="button"
                                    onclick="togglePassword('add_password', 'toggleIconAdd')">
                                    <i class="fa-solid fa-eye" id="toggleIconAdd"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted">Rôle</label>
                            <select name="role" class="form-select bg-dark text-white border-secondary">
                                <option value="vendeur">Vendeur</option>
                                <option value="admin">Administrateur</option>
                            </select>
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

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark text-white border-0 glass-panel">
                <div class="modal-header border-bottom border-secondary">
                    <h5 class="modal-title">Modifier Utilisateur</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="users.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id_user" id="edit_id_user">

                        <div class="mb-3">
                            <label class="form-label text-muted">Nom d'utilisateur</label>
                            <input type="text" name="username" id="edit_username"
                                class="form-control bg-dark text-white border-secondary" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted">Nouveau Mot de passe (laisser vide pour ne pas
                                changer)</label>
                            <div class="input-group">
                                <input type="password" name="password" id="edit_password"
                                    class="form-control bg-dark text-white border-secondary">
                                <button class="btn btn-outline-secondary" type="button"
                                    onclick="togglePassword('edit_password', 'toggleIconEdit')">
                                    <i class="fa-solid fa-eye" id="toggleIconEdit"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted">Rôle</label>
                            <select name="role" id="edit_role" class="form-select bg-dark text-white border-secondary">
                                <option value="vendeur">Vendeur</option>
                                <option value="admin">Administrateur</option>
                            </select>
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
