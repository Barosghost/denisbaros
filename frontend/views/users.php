<?php
session_start();

// Access Control - Admin Only
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

require_once '../../backend/config/db.php';
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
    <link rel="stylesheet" href="../assets/css/style.css?v=1.5">
    <style>
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.05);
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary-color);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .role-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .role-admin {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .role-vendeur {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
            border: 1px solid rgba(59, 130, 246, 0.2);
        }
    </style>
</head>

<body>

    <div class="wrapper">
        <?php include '../../backend/includes/sidebar.php'; ?>

        <div id="content">
            <?php include '../../backend/includes/header.php'; ?>

            <div class="fade-in mt-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="text-white mb-0">Gestion des Utilisateurs</h4>
                        <p class="text-muted small mb-0">Contrôle des accès et permissions du système</p>
                    </div>
                    <button class="btn btn-premium px-4" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="fa-solid fa-user-plus me-2"></i> Ajouter un utilisateur
                    </button>
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success border-0 bg-success bg-opacity-10 text-success mb-4"><?= $success ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger mb-4"><?= $error ?></div>
                <?php endif; ?>

                <div class="card bg-dark border-0 glass-panel">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-dark table-hover mb-0 align-middle">
                                <thead class="border-bottom border-secondary border-opacity-20">
                                    <tr class="text-muted small">
                                        <th class="py-3 px-4" style="width: 40%;">UTILISATEUR</th>
                                        <th class="py-3 text-center">RÔLE</th>
                                        <th class="py-3 text-center">CRÉÉ LE</th>
                                        <th class="py-3 text-end px-4">ACTIONS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user):
                                        $initials = strtoupper(substr($user['username'], 0, 1));
                                        $roleClass = ($user['role'] == 'admin') ? 'role-admin' : 'role-vendeur';
                                        $roleLabel = ($user['role'] == 'admin') ? 'Administrateur' : 'Vendeur';
                                        ?>
                                        <tr>
                                            <td class="px-4">
                                                <div class="d-flex align-items-center">
                                                    <div class="user-avatar me-3"><?= $initials ?></div>
                                                    <div>
                                                        <div class="text-white fw-bold">
                                                            <?= htmlspecialchars($user['username']) ?>
                                                        </div>
                                                        <?php if ($user['id_user'] == $_SESSION['user_id']): ?>
                                                            <span
                                                                class="badge bg-success bg-opacity-10 text-success extra-small">C'est
                                                                vous</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="role-badge <?= $roleClass ?>"><?= $roleLabel ?></span>
                                            </td>
                                            <td class="text-center text-muted small">
                                                <i class="fa-regular fa-calendar-alt me-1"></i>
                                                <?= date('d/m/Y', strtotime($user['created_at'])) ?>
                                            </td>
                                            <td class="text-end px-4">
                                                <div class="btn-group">
                                                    <button class="btn btn-sm btn-outline-primary" title="Modifier"
                                                        onclick="openEditUserModal(<?= $user['id_user'] ?>, '<?= addslashes($user['username']) ?>', '<?= $user['role'] ?>')">
                                                        <i class="fa-solid fa-pen"></i>
                                                    </button>
                                                    <?php if ($user['id_user'] != $_SESSION['user_id']): ?>
                                                        <button class="btn btn-sm btn-outline-danger ms-2" title="Supprimer"
                                                            onclick="confirmDelete(<?= $user['id_user'] ?>)">
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
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
            <div class="modal-content bg-dark text-white border-0 glass-panel shadow-lg">
                <div class="modal-header border-bottom border-secondary border-opacity-20 px-4">
                    <h5 class="modal-title fw-bold">Nouvel Utilisateur</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="users.php" method="POST">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">NOM D'UTILISATEUR</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-muted px-3"><i
                                        class="fa-solid fa-user"></i></span>
                                <input type="text" name="username"
                                    class="form-control bg-dark text-white border-secondary ps-2"
                                    placeholder="Ex: admin_denis" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">MOT DE PASSE</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-muted px-3"><i
                                        class="fa-solid fa-lock"></i></span>
                                <input type="password" name="password" id="add_password"
                                    class="form-control bg-dark text-white border-secondary ps-2" placeholder="••••••••"
                                    required>
                                <button class="btn btn-outline-secondary border-secondary" type="button"
                                    onclick="togglePassword('add_password', 'toggleIconAdd')">
                                    <i class="fa-solid fa-eye" id="toggleIconAdd"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-0">
                            <label class="form-label text-muted small fw-bold">RÔLE SYSTÈME</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-muted px-3"><i
                                        class="fa-solid fa-shield-halved"></i></span>
                                <select name="role" class="form-select bg-dark text-white border-secondary ps-2">
                                    <option value="vendeur">Vendeur (Accès limité)</option>
                                    <option value="admin">Administrateur (Accès total)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top border-secondary border-opacity-10 p-4">
                        <button type="submit" class="btn btn-premium w-100 py-2 fw-bold">
                            <i class="fa-solid fa-check me-2"></i>Créer le Compte
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark text-white border-0 glass-panel shadow-lg">
                <div class="modal-header border-bottom border-secondary border-opacity-20 px-4">
                    <h5 class="modal-title fw-bold">Modifier Utilisateur</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="users.php" method="POST">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id_user" id="edit_id_user">

                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">NOM D'UTILISATEUR</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-muted px-3"><i
                                        class="fa-solid fa-user"></i></span>
                                <input type="text" name="username" id="edit_username"
                                    class="form-control bg-dark text-white border-secondary ps-2" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">NOUVEAU MOT DE PASSE</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-muted px-3"><i
                                        class="fa-solid fa-lock"></i></span>
                                <input type="password" name="password" id="edit_password"
                                    class="form-control bg-dark text-white border-secondary ps-2"
                                    placeholder="Laisser vide pour ne pas changer">
                                <button class="btn btn-outline-secondary border-secondary" type="button"
                                    onclick="togglePassword('edit_password', 'toggleIconEdit')">
                                    <i class="fa-solid fa-eye" id="toggleIconEdit"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-0">
                            <label class="form-label text-muted small fw-bold">RÔLE SYSTÈME</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-muted px-3"><i
                                        class="fa-solid fa-shield-halved"></i></span>
                                <select name="role" id="edit_role"
                                    class="form-select bg-dark text-white border-secondary ps-2">
                                    <option value="vendeur">Vendeur</option>
                                    <option value="admin">Administrateur</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top border-secondary border-opacity-10 p-4">
                        <button type="submit" class="btn btn-premium w-100 py-2 fw-bold">
                            <i class="fa-solid fa-save me-2"></i>Enregistrer les modifications
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
    <script>
        function openEditUserModal(id, username, role) {
            document.getElementById('edit_id_user').value = id;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_role').value = role;
            new bootstrap.Modal(document.getElementById('editUserModal')).show();
        }

        function togglePassword(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            if (input.type === "password") {
                input.type = "text";
                icon.classList.replace("fa-eye", "fa-eye-slash");
            } else {
                input.type = "password";
                icon.classList.replace("fa-eye-slash", "fa-eye");
            }
        }

        function confirmDelete(id) {
            if (confirm('Voulez-vous vraiment supprimer cet utilisateur ? Cette action est irréversible.')) {
                window.location.href = 'users.php?delete=' + id;
            }
        }
    </script>
</body>

</html>
