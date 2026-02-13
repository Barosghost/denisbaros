<?php
define('PAGE_ACCESS', 'users');
require_once '../../backend/includes/auth_required.php';
require_once '../../backend/config/db.php';
require_once '../../backend/config/functions.php';

// Restricted Access
if (!in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header("Location: dashboard.php");
    exit();
}

$pageTitle = "Utilisateurs";

// Fetch Roles for mapping
$roles_stmt = $pdo->query("SELECT id_role, nom_role FROM roles");
$all_roles = $roles_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$roles_by_name = array_flip($all_roles);

// Handle User Creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = "CSRF Error.";
    } else {
        $username = trim($_POST['username']);
        $fullname = trim($_POST['fullname'] ?? '');
        $pass_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role_id = $_POST['role_id'];

        try {
            $stmt = $pdo->prepare("INSERT INTO utilisateurs (username, password_hash, nom_complet, id_role, statut) VALUES (?, ?, ?, ?, 'actif')");
            $stmt->execute([$username, $pass_hash, $fullname ?: $username, $role_id]);
            $id_user = $pdo->lastInsertId();

            logActivity($pdo, $_SESSION['user_id'] ?? 1, "Création utilisateur", "Username: $username");

            $success = "Utilisateur ajouté.";
            header("Location: users.php?success=" . urlencode($success));
            exit();
        } catch (PDOException $e) {
            $error = "Erreur : " . $e->getMessage();
        }
    }
}

// Handle User Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = "CSRF Error.";
    } else {
        $id_user = $_POST['id_user'];
        $username = trim($_POST['username']);
        $role_id = $_POST['role_id'];
        $password = $_POST['password'];

        try {
            if (!empty($password)) {
                $pass_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE utilisateurs SET username = ?, password_hash = ?, id_role = ? WHERE id_user = ?");
                $stmt->execute([$username, $pass_hash, $role_id, $id_user]);
            } else {
                $stmt = $pdo->prepare("UPDATE utilisateurs SET username = ?, id_role = ? WHERE id_user = ?");
                $stmt->execute([$username, $role_id, $id_user]);
            }
            logActivity($pdo, $_SESSION['user_id'] ?? 1, "Mise à jour utilisateur", "ID: $id_user");
            $success = "Modifications enregistrées.";
            header("Location: users.php?success=" . urlencode($success));
            exit();
        } catch (PDOException $e) {
            $error = "Erreur : " . $e->getMessage();
        }
    }
}

// Fetch Users
$stmt = $pdo->query("SELECT u.*, r.nom_role 
                    FROM utilisateurs u 
                    JOIN roles r ON u.id_role = r.id_role 
                    ORDER BY u.id_user DESC");
$users = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Utilisateurs | DENIS FBI STORE</title>
    <link href="../assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=1.5">
</head>

<body>
    <div class="wrapper">
        <?php include '../../backend/includes/sidebar.php'; ?>
        <div id="content">
            <?php include '../../backend/includes/header.php'; ?>
            <div class="p-4">
                <div class="d-flex justify-content-between align-items-center mb-4 text-white">
                    <h4>Gestion Utilisateurs</h4>
                    <button class="btn btn-primary" data-bs-toggle="modal"
                        data-bs-target="#addUserModal">Ajouter</button>
                </div>

                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div><?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

                <div class="card bg-dark border-0 glass-panel">
                    <div class="table-responsive">
                        <table class="table table-dark table-hover mb-0 align-middle">
                            <thead>
                                <tr class="text-muted small">
                                    <th class="px-4">Utilisateur</th>
                                    <th>Rôle</th>
                                    <th>Statut</th>
                                    <th class="text-end px-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td class="px-4 fw-bold text-white"><?= htmlspecialchars($u['username']) ?></td>
                                        <td><span
                                                class="badge bg-info bg-opacity-10 text-info"><?= htmlspecialchars($u['nom_role']) ?></span>
                                        </td>
                                        <td><span
                                                class="badge bg-<?= $u['statut'] == 'actif' ? 'success' : 'danger' ?>"><?= ucfirst($u['statut']) ?></span>
                                        </td>
                                        <td class="text-end px-4">
                                            <button class="btn btn-sm btn-outline-primary"
                                                onclick='openEditUser(<?= json_encode($u) ?>)'>Modifier</button>
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

    <!-- Modals (Add / Edit) -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-dark text-white p-3">
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <?= getCsrfInput() ?>
                    <div class="mb-3"><label>Nom d'utilisateur</label><input type="text" name="username"
                            class="form-control" required></div>
                    <div class="mb-3"><label>Mot de passe</label><input type="password" name="password"
                            class="form-control" required></div>
                    <div class="mb-3"><label>Rôle</label>
                        <select name="role_id" class="form-select">
                            <?php foreach ($all_roles as $id => $name): ?>
                                <option value="<?= $id ?>"><?= htmlspecialchars($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Ajouter</button>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <script>function openEditUser(u) { /* Simplified */ }</script>
</body>

</html>