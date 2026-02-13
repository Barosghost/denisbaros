<?php
define('PAGE_ACCESS', 'audit_logs');
require_once '../../backend/includes/auth_required.php';

require_once '../../backend/config/db.php';
require_once '../../backend/config/functions.php';
$pageTitle = "Audit Logs Système";

// Filters
$filter_user = $_GET['user'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';

// Table: logs_systeme (id_log, id_user, action_detaillee, colonne_critique, date_action)
$query = "SELECT l.*, u.username
          FROM logs_systeme l
          LEFT JOIN utilisateurs u ON l.id_user = u.id_user
          WHERE 1=1";
$params = [];

if ($filter_user) {
    $query .= " AND l.id_user = ?";
    $params[] = $filter_user;
}

if ($filter_date_from) {
    $query .= " AND DATE(l.date_action) >= ?";
    $params[] = $filter_date_from;
}

if ($filter_date_to) {
    $query .= " AND DATE(l.date_action) <= ?";
    $params[] = $filter_date_to;
}

$query .= " ORDER BY l.id_log DESC LIMIT 500";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get all users for filter
$all_users = $pdo->query("SELECT id_user, username FROM utilisateurs ORDER BY username")->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Logs d'Audit | DENIS FBI STORE</title>
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
                <h4 class="text-white mb-4">Logs d'Audit Système</h4>

                <div class="card bg-dark border-0 glass-panel p-4 mb-4">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="text-muted small fw-bold">UTILISATEUR</label>
                            <select name="user" class="form-select bg-dark text-white border-secondary">
                                <option value="">Tous</option>
                                <?php foreach ($all_users as $u): ?>
                                    <option value="<?= $u['id_user'] ?>" <?= $filter_user == $u['id_user'] ? 'selected' : '' ?>><?= htmlspecialchars($u['username']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="text-muted small fw-bold">DEPUIS</label>
                            <input type="date" name="date_from" class="form-control bg-dark text-white border-secondary"
                                value="<?= htmlspecialchars($filter_date_from) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="text-muted small fw-bold">JUSQU'À</label>
                            <input type="date" name="date_to" class="form-control bg-dark text-white border-secondary"
                                value="<?= htmlspecialchars($filter_date_to) ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Filtrer</button>
                        </div>
                    </form>
                </div>

                <div class="card bg-dark border-0 glass-panel">
                    <div class="table-responsive">
                        <table class="table table-dark table-hover mb-0 small">
                            <thead>
                                <tr class="text-muted">
                                    <th class="px-4">Date</th>
                                    <th>Utilisateur</th>
                                    <th>Action Détallée</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td class="px-4"><?= date('d/m/Y H:i', strtotime($log['date_action'])) ?></td>
                                        <td class="text-info fw-bold">
                                            @<?= htmlspecialchars($log['username'] ?? 'système') ?></td>
                                        <td class="text-white"><?= htmlspecialchars($log['action_detaillee']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>