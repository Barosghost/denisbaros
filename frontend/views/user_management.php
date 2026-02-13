<?php
define('PAGE_ACCESS', 'user_management');
require_once '../../backend/includes/auth_required.php';
$pageTitle = "Gestion des Utilisateurs";

require_once '../../backend/config/db.php';
require_once '../../backend/includes/check_session.php';
require_once '../../backend/config/functions.php';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action == 'toggle_active') {
        $id_user = $_POST['id_user'];
        $current_status = $_POST['current_status'];
        $new_status = $current_status == 'actif' ? 'inactif' : 'actif';

        $stmt = $pdo->prepare("UPDATE utilisateurs SET statut = ? WHERE id_user = ?");
        $stmt->execute([$new_status, $id_user]);

        logActivity($pdo, $_SESSION['user_id'] ?? 1, 'Modification utilisateur', "Compte " . ($new_status == 'actif' ? 'activé' : 'désactivé') . " pour l'utilisateur ID: $id_user");
        $success = "Statut du compte modifié avec succès.";
    }

    if ($action == 'change_role') {
        $id_user = $_POST['id_user'];
        $new_role = $_POST['new_role'];

        $stmt = $pdo->prepare("UPDATE utilisateurs SET role = ? WHERE id_user = ?");
        $stmt->execute([$new_role, $id_user]);

        logActivity($pdo, $_SESSION['user_id'] ?? 1, 'Modification utilisateur', "Rôle changé en '$new_role' pour l'utilisateur ID: $id_user");
        $success = "Rôle modifié avec succès.";
    }

    if ($action == 'reset_password') {
        $id_user = $_POST['id_user'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if ($new_password !== $confirm_password) {
            $error = "Les mots de passe ne correspondent pas.";
        } else {
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE utilisateurs SET password = ? WHERE id_user = ?");
            $stmt->execute([$hash, $id_user]);
            
            // Get username for log
            $u = $pdo->prepare("SELECT username FROM utilisateurs WHERE id_user = ?");
            $u->execute([$id_user]);
            $username = $u->fetchColumn();

            logActivity($pdo, $_SESSION['user_id'] ?? 1, 'Réinitialisation mot de passe', "Mot de passe réinitialisé pour l'utilisateur $username");
            $success = "Mot de passe réinitialisé avec succès.";
        }
    }
}

// Fetch all users with stats
$stmt = $pdo->query("SELECT u.*, 
                     (SELECT COUNT(*) FROM ventes WHERE id_vendeur = u.id_user) as sales_count,
                     (SELECT COUNT(*) FROM logs_systeme WHERE id_user = u.id_user) as actions_count
                     FROM utilisateurs u 
                     ORDER BY u.date_creation DESC");
$users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion Utilisateurs | DENIS FBI STORE</title>
    <link href="../assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=1.5">
    <script src="../assets/vendor/sweetalert2/sweetalert2.all.min.js"></script>
    <style>
        .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            font-weight: 700;
            color: #fff;
        }

        .role-super_admin {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .role-admin {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .role-vendeur {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .role-technicien {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        }

        .status-active {
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e;
            border: 1px solid rgba(34, 197, 94, 0.2);
        }

        .status-inactive {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.2);
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
                        <h4 class="text-white mb-0">
                            <i class="fa-solid fa-users-gear me-2"></i>
                            Gestion des Utilisateurs
                        </h4>
                        <p class="text-muted small mb-0">Contrôle des comptes et permissions</p>
                    </div>
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success border-0 bg-success bg-opacity-10 text-success mb-4">
                        <?= $success ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger mb-4">
                        <?= $error ?>
                    </div>
                <?php endif; ?>

                <div class="card bg-dark border-0 glass-panel shadow-lg">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-dark table-hover mb-0 align-middle">
                                <thead class="border-bottom border-secondary border-opacity-20">
                                    <tr class="text-muted small">
                                        <th class="py-3 px-4">UTILISATEUR</th>
                                        <th class="py-3">RÔLE</th>
                                        <th class="py-3 text-center">STATUT</th>
                                        <th class="py-3 text-center">ACTIVITÉ</th>
                                        <th class="py-3">DERNIÈRE CONNEXION</th>
                                        <th class="py-3 text-end px-4">ACTIONS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user):
                                        $fullName = $user['fullname'] ?? $user['username'] ?? 'Utilisateur';
                                        $initials = strtoupper(substr($fullName, 0, 1));
                                        $role_class = 'role-' . $user['role'];
                                        ?>
                                        <tr>
                                            <td class="px-4">
                                                <div class="d-flex align-items-center">
                                                    <div class="user-avatar <?= $role_class ?> me-3">
                                                        <?= $initials ?>
                                                    </div>
                                                    <div>
                                                        <div class="text-white fw-bold">
                                                            <?= htmlspecialchars($fullName) ?>
                                                        </div>
                                                        <div class="text-muted extra-small">@
                                                            <?= htmlspecialchars($user['username']) ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <form method="POST" class="d-inline"
                                                    onsubmit="return confirm('Changer le rôle de cet utilisateur ?');">
                                                    <input type="hidden" name="action" value="change_role">
                                                    <input type="hidden" name="id_user" value="<?= $user['id_user'] ?>">
                                                    <select name="new_role"
                                                        class="form-select form-select-sm bg-dark text-white border-secondary"
                                                        onchange="this.form.submit()" <?= $user['role'] == 'super_admin' ? 'disabled' : '' ?>>
                                                        <option value="super_admin" <?= $user['role'] == 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                                                        <option value="admin" <?= $user['role'] == 'admin' ? 'selected' : '' ?>
                                                            >Admin</option>
                                                        <option value="vendeur" <?= $user['role'] == 'vendeur' ? 'selected' : '' ?>>Vendeur</option>
                                                        <option value="technicien" <?= $user['role'] == 'technicien' ? 'selected' : '' ?>>Technicien</option>
                                                    </select>
                                                </form>
                                            </td>
                                            <td class="text-center">
                                                <span
                                                    class="badge <?= $user['statut'] == 'actif' ? 'status-active' : 'status-inactive' ?> px-3 py-2">
                                                    <?= ucfirst($user['statut']) ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <div class="small text-white">
                                                    <?= $user['sales_count'] ?> ventes
                                                </div>
                                                <div class="extra-small text-muted">
                                                    <?= $user['actions_count'] ?> actions
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($user['last_login']): ?>
                                                    <div class="small text-white">
                                                        <?= date('d/m/Y', strtotime($user['last_login'])) ?>
                                                    </div>
                                                    <div class="extra-small text-muted">
                                                        <?= date('H:i', strtotime($user['last_login'])) ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted small italic">Jamais connecté</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end px-4">
                                                <?php if ($user['role'] != 'super_admin'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="toggle_active">
                                                        <input type="hidden" name="id_user" value="<?= $user['id_user'] ?>">
                                                        <input type="hidden" name="current_status"
                                                            value="<?= $user['statut'] ?>">
                                                        <button type="submit"
                                                            class="btn btn-sm btn-outline-<?= $user['statut'] == 'actif' ? 'danger' : 'success' ?> border-0"
                                                            onclick="return confirm('<?= $user['statut'] == 'actif' ? 'Désactiver' : 'Activer' ?> ce compte ?');">
                                                            <i
                                                                class="fa-solid fa-<?= $user['statut'] == 'actif' ? 'ban' : 'check' ?>"></i>
                                                        </button>
                                                    </form>
                                                    <button type="button" class="btn btn-sm btn-outline-warning border-0 ms-1"
                                                        data-bs-toggle="modal" data-bs-target="#resetPasswordModal"
                                                        onclick="setResetUser(<?= $user['id_user'] ?>, '<?= htmlspecialchars($user['username']) ?>')">
                                                        <i class="fa-solid fa-key"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <span
                                                        class="badge bg-warning bg-opacity-20 text-warning extra-small">Protégé</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="row g-4 mt-4">
                    <div class="col-md-3">
                        <div class="card bg-dark border-0 glass-panel p-3">
                            <div class="text-muted small mb-2">TOTAL UTILISATEURS</div>
                            <h3 class="text-white fw-bold mb-0">
                                <?= count($users) ?>
                            </h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-dark border-0 glass-panel p-3">
                            <div class="text-muted small mb-2">COMPTES ACTIFS</div>
                            <h3 class="text-success fw-bold mb-0">
                                <?= count(array_filter($users, fn($u) => $u['statut'] == 'actif')) ?>
                            </h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-dark border-0 glass-panel p-3">
                            <div class="text-muted small mb-2">VENDEURS</div>
                            <h3 class="text-info fw-bold mb-0">
                                <?= count(array_filter($users, fn($u) => $u['role'] == 'vendeur')) ?>
                            </h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-dark border-0 glass-panel p-3">
                            <div class="text-muted small mb-2">TECHNICIENS</div>
                            <h3 class="text-primary fw-bold mb-0">
                                <?= count(array_filter($users, fn($u) => $u['role'] == 'technicien')) ?>
                            </h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div class="modal fade" id="resetPasswordModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark text-white glass-panel shadow-lg border-secondary border-opacity-10">
                <form method="POST">
                    <div class="modal-header border-secondary border-opacity-20 px-4">
                        <h5 class="modal-title fw-bold">Réinitialiser le mot de passe</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="id_user" id="reset_id_user">
                        <p class="text-muted mb-3">Réinitialisation pour l'utilisateur: <strong id="reset_username" class="text-white"></strong></p>
                        
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">NOUVEAU MOT DE PASSE</label>
                            <input type="password" name="new_password" class="form-control bg-dark text-white border-secondary" required minlength="6">
                        </div>
                        <div class="mb-0">
                            <label class="form-label text-muted small fw-bold">CONFIRMER LE MOT DE PASSE</label>
                            <input type="password" name="confirm_password" class="form-control bg-dark text-white border-secondary" required minlength="6">
                        </div>
                    </div>
                    <div class="modal-footer border-secondary border-opacity-10 p-4">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-warning fw-bold">Réinitialiser</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function setResetUser(id, username) {
            document.getElementById('reset_id_user').value = id;
            document.getElementById('reset_username').textContent = username;
        }
    </script>

    <script src="../assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
</body>

</html>