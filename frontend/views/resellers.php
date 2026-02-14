<?php
define('PAGE_ACCESS', 'resellers');
require_once '../../backend/includes/auth_required.php';
$pageTitle = "Gestion des Revendeurs";

// Fetch Resellers
$stmt = $pdo->query("SELECT r.*, 
                     (SELECT COUNT(*) FROM ventes v WHERE v.id_revendeur = r.id_revendeur) as sales_count,
                     (SELECT COALESCE(SUM(v.prix_revente_final * (r.taux_commission_fixe / 100)), 0) FROM ventes v WHERE v.id_revendeur = r.id_revendeur) as pending_commission
                     FROM revendeurs r ORDER BY r.nom_partenaire ASC");
$resellers = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revendeurs | DENIS FBI STORE</title>
    <link href="../assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=1.5">
    <script src="../assets/vendor/sweetalert2/sweetalert2.all.min.js"></script>
    <style>
        .reseller-card {
            background: linear-gradient(145deg, rgba(30, 41, 59, 0.6), rgba(15, 23, 42, 0.4));
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 16px;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .reseller-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
            border-color: rgba(99, 102, 241, 0.3);
        }

        .commission-badge {
            background: rgba(99, 102, 241, 0.1);
            color: #8b5cf6;
            border: 1px solid rgba(99, 102, 241, 0.2);
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 700;
        }
    </style>
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
                            <input type="text" id="cardSearch"
                                class="form-control bg-dark text-white border-secondary py-2"
                                placeholder="Rechercher un revendeur..." style="border-radius: 0 12px 12px 0;">
                        </div>
                    </div>
                    <button class="btn btn-premium px-4" onclick="openModal('add')">
                        <i class="fa-solid fa-user-plus me-2"></i> Nouveau Revendeur
                    </button>
                </div>

                <div class="row g-4" id="resellerList">
                    <?php foreach ($resellers as $reseller): ?>
                        <div class="col-xl-4 col-md-6 reseller-card-item">
                            <div class="reseller-card p-4 h-100 position-relative">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="stat-icon bg-gradient-primary text-white"
                                            style="width: 48px; height: 48px; border-radius: 12px; font-size: 1.2rem;">
                                            <i class="fa-solid fa-user-tag"></i>
                                        </div>
                                        <div>
                                            <h5 class="text-white fw-bold mb-0">
                                                <?= htmlspecialchars($reseller['nom_partenaire']) ?>
                                            </h5>
                                            <div class="text-muted small"><i class="fa-solid fa-phone me-1"></i>
                                                <?= htmlspecialchars($reseller['telephone']) ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="dropdown">
                                        <button class="btn btn-link text-muted p-0" data-bs-toggle="dropdown">
                                            <i class="fa-solid fa-ellipsis-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end">
                                            <li><a class="dropdown-item" href="#"
                                                    onclick="openModal('update', <?= htmlspecialchars(json_encode($reseller)) ?>)"><i
                                                        class="fa-solid fa-pen me-2"></i>Modifier</a></li>
                                            <li><a class="dropdown-item" href="#"
                                                    onclick="toggleStatus(<?= $reseller['id_revendeur'] ?>)"><i
                                                        class="fa-solid fa-power-off me-2"></i>
                                                    <?= $reseller['is_active'] ? 'Désactiver' : 'Activer' ?>
                                                </a></li>
                                            <li>
                                                <hr class="dropdown-divider">
                                            </li>
                                            <li><a class="dropdown-item text-danger" href="#"
                                                    onclick="deleteReseller(<?= $reseller['id_revendeur'] ?>)"><i
                                                        class="fa-solid fa-trash me-2"></i>Supprimer</a></li>
                                        </ul>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <span class="commission-badge">
                                        Com: <?= $reseller['taux_commission_fixe'] ?>%
                                    </span>
                                    <span
                                        class="badge <?= $reseller['is_active'] ? 'bg-success bg-opacity-10 text-success' : 'bg-danger bg-opacity-10 text-danger' ?> ms-2">
                                        <?= $reseller['is_active'] ? 'Actif' : 'Inactif' ?>
                                    </span>
                                </div>

                                <div class="row g-2 mt-3 pt-3 border-top border-secondary border-opacity-20 text-center">
                                    <div class="col-6 border-end border-secondary border-opacity-20">
                                        <div class="h5 text-white fw-bold mb-0">
                                            <?= $reseller['sales_count'] ?>
                                        </div>
                                        <div class="extra-small text-muted">Ventes Totales</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="h5 text-warning fw-bold mb-0">
                                            <?= number_format($reseller['pending_commission'] ?? 0, 0, ',', ' ') ?>
                                        </div>
                                        <div class="extra-small text-muted">Marge à payer</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (empty($resellers)): ?>
                        <div class="col-12 text-center py-5">
                            <div class="text-muted opacity-50 mb-3"><i class="fa-solid fa-users-slash fa-3x"></i></div>
                            <h5 class="text-muted">Aucun revendeur enregistré</h5>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Form -->
    <div class="modal fade" id="resellerModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark text-white border-0 glass-panel">
                <div class="modal-header border-bottom border-secondary border-opacity-20">
                    <h5 class="modal-title fw-bold" id="modalTitle">Nouveau Revendeur</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="resellerForm">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" id="action">
                        <input type="hidden" name="id_reseller" id="id_reseller">

                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">NOM COMPLET</label>
                            <input type="text" name="fullname" id="fullname"
                                class="form-control bg-dark text-white border-secondary" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">TÉLÉPHONE</label>
                            <input type="tel" name="phone" id="phone"
                                class="form-control bg-dark text-white border-secondary">
                        </div>

                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label text-muted small fw-bold">TAUX COMMISSION (%)</label>
                                <input type="number" step="0.01" name="commission_value" id="commission_value"
                                    class="form-control bg-dark text-white border-secondary" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top border-secondary border-opacity-10">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-premium px-4">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
    <script>
        const modal = new bootstrap.Modal(document.getElementById('resellerModal'));

        function openModal(type, data = null) {
            document.getElementById('action').value = type;
            document.getElementById('modalTitle').innerText = type === 'add' ? 'Nouveau Revendeur' : 'Modifier Revendeur';

            if (type === 'update' && data) {
                document.getElementById('id_reseller').value = data.id_revendeur;
                document.getElementById('fullname').value = data.nom_partenaire;
                document.getElementById('phone').value = data.telephone;
                document.getElementById('commission_value').value = data.taux_commission_fixe;
            } else {
                document.getElementById('resellerForm').reset();
                document.getElementById('action').value = 'add';
            }
            modal.show();
        }

        document.getElementById('resellerForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch('../../backend/actions/process_reseller.php', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('Succès', data.message, 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Erreur', data.message, 'error');
                    }
                });
        });

        function toggleStatus(id) {
            const formData = new FormData();
            formData.append('action', 'toggle_status');
            formData.append('id_reseller', id);

            fetch('../../backend/actions/process_reseller.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) location.reload();
                });
        }

        function deleteReseller(id) {
            Swal.fire({
                title: 'Êtes-vous sûr ?',
                text: "Cette action est irréversible !",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Oui, supprimer'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'delete');
                    formData.append('id_reseller', id);

                    fetch('../../backend/actions/process_reseller.php', { method: 'POST', body: formData })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('Supprimé!', data.message, 'success').then(() => location.reload());
                            } else {
                                Swal.fire('Erreur', data.message, 'error');
                            }
                        });
                }
            })
        }

        document.getElementById('cardSearch').addEventListener('input', function () {
            const term = this.value.toLowerCase().trim();
            document.querySelectorAll('.reseller-card-item').forEach(item => {
                const text = item.innerText.toLowerCase();
                item.style.display = text.includes(term) ? '' : 'none';
            });
        });
    </script>
</body>

</html>