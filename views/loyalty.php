<?php
session_start();

if (!isset($_SESSION['logged_in'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../config/db.php';
require_once '../config/functions.php';
require_once '../config/loyalty_config.php';

$pageTitle = "Programme de Fidélité";

// 1. Fetch Rewards
$rewards = $pdo->query("SELECT * FROM loyalty_rewards WHERE is_active = 1 ORDER BY points_required ASC")->fetchAll();

// 2. Fetch Recent Transactions (All for now, can be filtered by client)
$stmt = $pdo->query("SELECT t.*, c.fullname, c.loyalty_points as current_balance 
                     FROM loyalty_transactions t 
                     JOIN clients c ON t.id_client = c.id_client 
                     ORDER BY t.created_at DESC LIMIT 20");
$transactions = $stmt->fetchAll();

// 3. Top Loyal Clients
$top_clients = $pdo->query("SELECT * FROM clients ORDER BY loyalty_points DESC LIMIT 5")->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fidélité | DENIS FBI STORE</title>
    <link href="../assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=1.6">
    <style>
        .reward-card {
            background: rgba(30, 41, 59, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .reward-card:hover {
            transform: translateY(-5px);
            background: rgba(30, 41, 59, 0.6);
            border-color: rgba(99, 102, 241, 0.3);
            box-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.3);
        }

        .level-progress-card {
            background: linear-gradient(145deg, rgba(15, 23, 42, 0.8) 0%, rgba(30, 41, 59, 0.9) 100%);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            position: relative;
            overflow: hidden;
        }

        .level-badge-lg {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            background: rgba(255, 255, 255, 0.05);
            box-shadow: inset 0 0 20px rgba(0, 0, 0, 0.3);
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include '../includes/sidebar.php'; ?>
        <div id="content">
            <?php include '../includes/header.php'; ?>

            <div class="fade-in mt-4">

                <!-- Levels Overview -->
                <div class="row g-4 mb-5">
                    <?php foreach (LOYALTY_LEVELS as $key => $lvl): ?>
                        <div class="col-xl-3 col-md-6">
                            <div class="level-progress-card h-100 p-4" style="border-top: 3px solid <?= $lvl['color'] ?>;">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="level-badge-lg me-3" style="color: <?= $lvl['color'] ?>;">
                                        <?= str_replace(['Bronze ', 'Argent ', 'Or ', 'Platine '], '', $lvl['icon']) ?>
                                    </div>
                                    <div>
                                        <h4 class="text-white fw-bold mb-1"><?= $lvl['name'] ?></h4>
                                        <div class="text-muted small">Bonus: <span
                                                class="text-success fw-bold">+<?= ($lvl['multiplier'] - 1) * 100 ?>%</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="progress bg-white bg-opacity-5" style="height: 6px;">
                                    <div class="progress-bar" role="progressbar"
                                        style="width: 100%; background-color: <?= $lvl['color'] ?>;"></div>
                                </div>
                                <div class="mt-3 text-white-50 extra-small">
                                    Dès <?= number_format($lvl['min_spent'], 0, ',', ' ') ?> FCFA d'achats
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="row g-4">
                    <!-- Left Column: Transactions & Rewards -->
                    <div class="col-lg-8">
                        <!-- Rewards Catalog -->
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h5 class="text-white fw-bold mb-0">🎁 Récompenses Disponibles</h5>
                            <button class="btn btn-sm btn-outline-light border-0" data-bs-toggle="modal"
                                data-bs-target="#addRewardModal"><i class="fa-solid fa-plus me-1"></i>Ajouter</button>
                        </div>

                        <div class="row g-3 mb-5">
                            <?php if (empty($rewards)): ?>
                                <div class="col-12">
                                    <div class="text-center py-5 text-muted bg-dark bg-opacity-50 rounded-4">
                                        <i class="fa-solid fa-gift fa-3x mb-3 opacity-25"></i>
                                        <p>Aucune récompense configurée pour le moment.</p>
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($rewards as $reward): ?>
                                    <div class="col-md-6">
                                        <div class="reward-card p-3 d-flex align-items-center">
                                            <div class="rounded-3 bg-primary bg-opacity-10 p-3 me-3 text-primary">
                                                <i class="fa-solid fa-ticket fa-2x"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="text-white fw-bold mb-1"><?= htmlspecialchars($reward['name']) ?>
                                                </h6>
                                                <div class="text-info fw-bold small">
                                                    <?= number_format($reward['points_required'], 0, ',', ' ') ?> pts
                                                </div>
                                            </div>
                                            <button class="btn btn-sm btn-light rounded-pill px-3 fw-bold"
                                                onclick="openRedeemModal(<?= $reward['id_reward'] ?>, '<?= htmlspecialchars($reward['name'], ENT_QUOTES) ?>', <?= $reward['points_required'] ?>)">
                                                Échanger
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Recent History -->
                        <h5 class="text-white fw-bold mb-3">📜 Historique des Points</h5>
                        <div class="card bg-dark border-0 glass-panel shadow-lg">
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-dark table-hover mb-0 align-middle">
                                        <thead
                                            class="text-muted extra-small text-uppercase border-bottom border-secondary border-opacity-20">
                                            <tr>
                                                <th class="ps-4">Date</th>
                                                <th>Client</th>
                                                <th>Description</th>
                                                <th class="text-end pe-4">Points</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($transactions as $t): ?>
                                                <tr>
                                                    <td class="ps-4 text-white-50 small">
                                                        <?= date('d/m H:i', strtotime($t['created_at'])) ?>
                                                    </td>
                                                    <td>
                                                        <div class="text-white fw-bold small">
                                                            <?= htmlspecialchars($t['fullname']) ?>
                                                        </div>
                                                    </td>
                                                    <td class="text-muted small">
                                                        <?= htmlspecialchars($t['description']) ?>
                                                    </td>
                                                    <td class="text-end pe-4">
                                                        <?php if ($t['transaction_type'] == 'EARN'): ?>
                                                            <span class="text-success fw-bold">+<?= $t['points'] ?></span>
                                                        <?php else: ?>
                                                            <span class="text-danger fw-bold">-<?= $t['points'] ?></span>
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

                    <!-- Right Column: Top Clients -->
                    <div class="col-lg-4">
                        <h5 class="text-white fw-bold mb-3">🏆 Top Fidélité</h5>
                        <div class="card bg-dark border-0 glass-panel shadow-lg">
                            <div class="card-body p-0">
                                <div class="list-group list-group-flush bg-transparent">
                                    <?php foreach ($top_clients as $index => $c):
                                        $levelInfo = getLevelInfo($c['loyalty_level'] ?? 'Bronze');
                                        ?>
                                        <div
                                            class="list-group-item bg-transparent border-secondary border-opacity-10 d-flex align-items-center p-3">
                                            <div class="fw-bold text-muted me-3 fs-5">#<?= $index + 1 ?></div>
                                            <div class="flex-grow-1">
                                                <div class="d-flex align-items-center justify-content-between mb-1">
                                                    <div class="text-white fw-bold"><?= htmlspecialchars($c['fullname']) ?>
                                                    </div>
                                                    <div class="add-level-badge">
                                                        <?php if (strpos($levelInfo['icon'], 'fa-') !== false): ?>
                                                            <i class="<?= $levelInfo['icon'] ?> text-muted small"></i>
                                                        <?php else: ?>
                                                            <span class="small"><?= $levelInfo['icon'] ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div class="text-info small fw-bold">
                                                        <?= number_format($c['loyalty_points'], 0, ',', ' ') ?> pts
                                                    </div>
                                                    <div class="text-muted extra-small">
                                                        <?= number_format($c['total_spent'], 0, ',', ' ') ?> FCFA
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Add Reward Modal -->
    <div class="modal fade" id="addRewardModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content glass-panel text-white">
                <div class="modal-header border-bottom border-secondary border-opacity-25">
                    <h5 class="modal-title fw-bold">🎁 Nouvelle Récompense</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addRewardForm">
                        <div class="mb-3">
                            <label class="form-label text-white-50 small text-uppercase fw-bold">Nom</label>
                            <input type="text" name="name" class="form-control bg-dark text-white border-secondary"
                                required placeholder="Ex: Bon d'achat 5000">
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-white-50 small text-uppercase fw-bold">Coût en Points</label>
                            <input type="number" name="points_required"
                                class="form-control bg-dark text-white border-secondary" required min="1">
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-white-50 small text-uppercase fw-bold">Description
                                (Optionnel)</label>
                            <textarea name="description" class="form-control bg-dark text-white border-secondary"
                                rows="2"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-white-50 small text-uppercase fw-bold">Valeur
                                    (FCFA)</label>
                                <input type="number" name="discount_amount"
                                    class="form-control bg-dark text-white border-secondary" value="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-white-50 small text-uppercase fw-bold">Niveau Min.</label>
                                <select name="min_level" class="form-select bg-dark text-white border-secondary">
                                    <option value="Bronze">Bronze</option>
                                    <option value="Argent">Argent</option>
                                    <option value="Or">Or</option>
                                    <option value="Platine">Platine</option>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-top border-secondary border-opacity-25">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-primary" onclick="submitReward()">Enregistrer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Redeem Modal -->
    <div class="modal fade" id="redeemModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content glass-panel text-white">
                <div class="modal-header border-bottom border-secondary border-opacity-25">
                    <h5 class="modal-title fw-bold">🎁 Échanger Récompense</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-white-50">Récompense : <strong id="redeemRewardName" class="text-white"></strong></p>
                    <p class="text-white-50">Coût : <strong id="redeemRewardCost" class="text-info"></strong> points</p>

                    <form id="redeemForm">
                        <input type="hidden" name="reward_id" id="redeemRewardId">
                        <div class="mb-3">
                            <label class="form-label text-white-50 small text-uppercase fw-bold">Choisir le
                                Client</label>
                            <!-- Simple Select for now, ideally AJAX search -->
                            <select name="client_id" class="form-select bg-dark text-white border-secondary" required>
                                <option value="">-- Sélectionner --</option>
                                <?php
                                // Re-query clients to be safe or reuse if available. 
                                // Note: $top_clients is limited. Let's make a quick full query or reuse $clients from clients.php logic if included? 
                                // Better to just run a query here for the dropdown.
                                $all_clients = $pdo->query("SELECT id_client, fullname, loyalty_points FROM clients ORDER BY fullname")->fetchAll();
                                foreach ($all_clients as $cl): ?>
                                    <option value="<?= $cl['id_client'] ?>">
                                        <?= htmlspecialchars($cl['fullname']) ?> (<?= $cl['loyalty_points'] ?> pts)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-top border-secondary border-opacity-25">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-success" onclick="confirmRedeem()">Valider l'échange</button>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <script src="../assets/vendor/sweetalert2/sweetalert2.all.min.js"></script>
    <script src="../assets/js/app.js"></script>
    <script>
        function submitReward() {
            const form = document.getElementById('addRewardForm');
            const data = new FormData(form);

            fetch('../actions/add_reward.php', {
                method: 'POST',
                body: data
            })
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Récompense ajoutée !',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => location.reload());
                    } else {
                        Swal.fire('Erreur', res.message, 'error');
                    }
                })
                .catch(err => {
                    console.error(err);
                    Swal.fire('Erreur', 'Une erreur est survenue', 'error');
                });
        }

        let redeemModal;

        function openRedeemModal(id, name, cost) {
            document.getElementById('redeemRewardId').value = id;
            document.getElementById('redeemRewardName').textContent = name;
            document.getElementById('redeemRewardCost').textContent = cost;

            redeemModal = new bootstrap.Modal(document.getElementById('redeemModal'));
            redeemModal.show();
        }

        function confirmRedeem() {
            const rewardId = document.getElementById('redeemRewardId').value;
            const clientId = document.querySelector('#redeemForm select[name="client_id"]').value;

            if (!clientId) {
                Swal.fire('Attention', 'Veuillez sélectionner un client', 'warning');
                return;
            }

            fetch('../actions/redeem_reward.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ reward_id: rewardId, client_id: clientId })
            })
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        redeemModal.hide();
                        Swal.fire({
                            icon: 'success',
                            title: 'Échange effectué !',
                            text: 'Les points ont été déduits.',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => location.reload());
                    } else {
                        Swal.fire('Erreur', res.message, 'error');
                    }
                })
                .catch(err => {
                    console.error(err);
                    Swal.fire('Erreur', 'Erreur technique', 'error');
                });
        }
    </script>
</body>

</html>