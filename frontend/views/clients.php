<?php
define('PAGE_ACCESS', 'clients');
require_once '../../backend/includes/auth_required.php';
require_once '../../backend/config/db.php';
require_once '../../backend/config/functions.php';
require_once '../../backend/config/loyalty_config.php';
$pageTitle = "Gestion des Clients";

// Handle Add Client
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $fullname = trim($_POST['fullname']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);

    try {
        $stmt = $pdo->prepare("INSERT INTO clients (nom_client, telephone, adresse) VALUES (?, ?, ?)");
        $stmt->execute([$fullname, $phone, $address]);
        logActivity($pdo, $_SESSION['user_id'] ?? 1, "Ajout client", "Client: $fullname");
        $success = "Client enregistré avec succès.";
    } catch (PDOException $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

if (isset($_GET['success'])) {
    $success = $_GET['success'];
}

// Handle Update Client
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update') {
    $id_client = $_POST['id_client'];
    $fullname = trim($_POST['fullname']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);

    try {
        $stmt = $pdo->prepare("UPDATE clients SET nom_client = ?, telephone = ?, adresse = ? WHERE id_client = ?");
        $stmt->execute([$fullname, $phone, $address, $id_client]);
        logActivity($pdo, $_SESSION['user_id'] ?? 1, "Modification client", "ID: $id_client, Client: $fullname");
        $success = "Client mis à jour.";
    } catch (PDOException $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

// Summary Stats
$total_clients = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
$active_clients_30d = $pdo->query("SELECT COUNT(DISTINCT id_client) FROM ventes WHERE date_vente >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
$vip_clients = 0;
try {
    $vip_clients = $pdo->query("SELECT COUNT(*) FROM clients WHERE loyalty_level IN ('Or', 'Platine')")->fetchColumn();
} catch (PDOException $e) { /* colonne optionnelle */
}

// Handle Delete Client
if (isset($_GET['delete'])) {
    if (!in_array($_SESSION['role'], ['admin', 'super_admin'])) {
        $error = "Accès refusé. Droits insuffisants.";
    } else {
        $id = $_GET['delete'];
        try {
            // Check for sales first
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM ventes WHERE id_client = ?");
            $stmt_check->execute([$id]);
            if ($stmt_check->fetchColumn() > 0) {
                $error = "Impossible de supprimer ce client car il possède un historique d'achats. Pour préserver l'intégrité des données, vous pouvez seulement modifier ses informations.";
            } else {
                $pdo->prepare("DELETE FROM clients WHERE id_client = ?")->execute([$id]);
                logActivity($pdo, $_SESSION['user_id'] ?? 1, "Suppression client", "ID: $id");
                header("Location: clients.php?success=" . urlencode("Le profil client a été définitivement supprimé."));
                exit();
            }
        } catch (PDOException $e) {
            $error = "Erreur database : " . $e->getMessage();
        }
    }
}

// Fetch Clients with Total Sales
$clients = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM ventes s WHERE s.id_client = c.id_client) as total_sales, (SELECT SUM(prix_revente_final) FROM ventes s WHERE s.id_client = c.id_client) as total_spent FROM clients c ORDER BY c.date_inscription DESC")->fetchAll();

// Handle AJAX for history
if (isset($_GET['get_history'])) {
    $id = $_GET['get_history'];
    $stmt = $pdo->prepare("SELECT s.*, u.username FROM ventes s JOIN utilisateurs u ON s.id_vendeur = u.id_user WHERE s.id_client = ? ORDER BY s.date_vente DESC");
    $stmt->execute([$id]);
    $history = $stmt->fetchAll();
    header('Content-Type: application/json');
    echo json_encode($history);
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clients | DENIS FBI STORE</title>
    <link href="../assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=1.5">
    <script src="../assets/vendor/sweetalert2/sweetalert2.all.min.js"></script>
    <style>
        .client-avatar {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.05);
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary-color);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .client-stat-card {
            background: rgba(15, 23, 42, 0.4);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 1.5rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .client-stat-card:hover {
            transform: translateY(-5px);
            background: rgba(15, 23, 42, 0.6);
            border-color: rgba(255, 255, 255, 0.1);
        }

        .level-badge {
            padding: 0.45rem 0.9rem;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
        }

        .level-or {
            background: rgba(255, 215, 0, 0.1);
            color: #ffd700;
            border: 1px solid rgba(255, 215, 0, 0.2);
        }

        .level-argent {
            background: rgba(192, 192, 192, 0.1);
            color: #e2e8f0;
            border: 1px solid rgba(192, 192, 192, 0.2);
        }

        .level-bronze {
            background: rgba(205, 127, 50, 0.1);
            color: #cd7f32;
            border: 1px solid rgba(205, 127, 50, 0.2);
        }

        .level-platine {
            background: rgba(229, 228, 226, 0.1);
            color: #e5e4e2;
            border: 1px solid rgba(229, 228, 226, 0.2);
        }

        .search-container {
            position: relative;
        }

        .search-container i {
            position: absolute;
            left: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
        }

        .search-container input {
            padding-left: 3rem;
            border-radius: 14px;
            background: rgba(15, 23, 42, 0.4) !important;
            border-color: rgba(255, 255, 255, 0.05);
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include '../../backend/includes/sidebar.php'; ?>
        <div id="content">
            <?php include '../../backend/includes/header.php'; ?>

            <div class="fade-in mt-4">
                <!-- Summary Stats -->
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="client-stat-card d-flex align-items-center">
                            <div class="icon-box bg-primary bg-opacity-10 text-primary me-4"
                                style="width: 54px; height: 54px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                                <i class="fa-solid fa-users"></i>
                            </div>
                            <div>
                                <div class="text-muted extra-small fw-bold text-uppercase">Base Clients</div>
                                <div class="h3 text-white fw-bold mb-0"><?= $total_clients ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="client-stat-card d-flex align-items-center">
                            <div class="icon-box bg-success bg-opacity-10 text-success me-4"
                                style="width: 54px; height: 54px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                                <i class="fa-solid fa-user-check"></i>
                            </div>
                            <div>
                                <div class="text-muted extra-small fw-bold text-uppercase">Actifs (30j)</div>
                                <div class="h3 text-white fw-bold mb-0"><?= $active_clients_30d ?>
                                    <span
                                        class="fs-6 fw-normal opacity-50 ms-1">(<?= ($total_clients > 0) ? round(($active_clients_30d / $total_clients) * 100) : 0 ?>%)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="client-stat-card d-flex align-items-center">
                            <div class="icon-box bg-warning bg-opacity-10 text-warning me-4"
                                style="width: 54px; height: 54px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                                <i class="fa-solid fa-crown"></i>
                            </div>
                            <div>
                                <div class="text-muted extra-small fw-bold text-uppercase">Membres VIP</div>
                                <div class="h3 text-white fw-bold mb-0"><?= $vip_clients ?> <span
                                        class="fs-6 fw-normal opacity-50 ms-1">Or & Platine</span></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-4 gap-3">
                    <div class="search-container flex-grow-1">
                        <i class="fa-solid fa-search"></i>
                        <input type="text" id="searchClient" class="form-control text-white py-2"
                            placeholder="Rechercher un client (nom, téléphone, email)...">
                    </div>
                    <button class="btn btn-premium px-4" data-bs-toggle="modal" data-bs-target="#addClientModal">
                        <i class="fa-solid fa-user-plus me-2"></i>Nouveau Client
                    </button>
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success border-0 bg-success bg-opacity-10 text-success mb-4"><?= $success ?>
                    </div>
                <?php endif; ?>

                <div class="card bg-dark border-0 glass-panel shadow-lg">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-dark table-hover mb-0 align-middle">
                                <thead class="border-bottom border-secondary border-opacity-20">
                                    <tr class="text-muted small">
                                        <th class="py-3 px-4">CLIENT</th>
                                        <th class="py-3">CONTACT</th>
                                        <th class="py-3 text-center">NIVEAU FIDÉLITÉ</th>
                                        <th class="py-3 text-center">POINTS</th>
                                        <th class="py-3 text-end">VOLUME ACHATS</th>
                                        <th class="py-3 text-end px-4">ACTIONS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($clients as $c):
                                        $level = !empty($c['loyalty_level']) ? $c['loyalty_level'] : 'Bronze';
                                        $levelInfo = getLevelInfo($level);
                                        $initials = strtoupper(substr($c['nom_client'], 0, 1) . (strpos($c['nom_client'], ' ') !== false ? substr($c['nom_client'], strpos($c['nom_client'], ' ') + 1, 1) : ''));
                                        ?>
                                        <tr class="client-row">
                                            <td class="px-4">
                                                <div class="d-flex align-items-center">
                                                    <div class="client-avatar me-3"><?= $initials ?></div>
                                                    <div>
                                                        <div class="text-white fw-bold">
                                                            <?= htmlspecialchars($c['nom_client']) ?>
                                                        </div>
                                                        <div class="text-muted extra-small">Inscrit le
                                                            <?= date('d/m/Y', strtotime($c['date_inscription'])) ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="small fw-medium text-white mb-1">
                                                    <i
                                                        class="fa-solid fa-phone me-2 text-muted extra-small"></i><?= htmlspecialchars($c['telephone'] ?? '-') ?>
                                                </div>
                                                <div class="extra-small text-muted">
                                                    <i
                                                        class="fa-solid fa-location-dot me-2 extra-small"></i><?= htmlspecialchars($c['adresse'] ?? 'Aucune adresse') ?>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="level-badge level-<?= strtolower($level) ?>">
                                                    <?php if (strpos($levelInfo['icon'], 'fa-') !== false): ?>
                                                        <i class="<?= $levelInfo['icon'] ?> me-2"></i>
                                                    <?php else: ?>
                                                        <span class="me-2 fs-6"><?= $levelInfo['icon'] ?></span>
                                                    <?php endif; ?>
                                                    <?= $level ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <div class="fw-bold text-info"><i
                                                        class="fa-solid fa-star me-1 small"></i><?= number_format($c['loyalty_points'] ?? 0, 0, ',', ' ') ?>
                                                </div>
                                                <div class="extra-small text-muted"><?= $c['total_sales'] ?> ventes</div>
                                            </td>
                                            <td class="text-end fw-bold text-success">
                                                <?= number_format($c['total_spent'] ?? 0, 0, ',', ' ') ?> <span
                                                    class="small font-normal opacity-50">FCFA</span>
                                            </td>
                                            <td class="text-end px-4">
                                                <div class="btn-group">
                                                    <button class="btn btn-sm btn-outline-info border-0" title="Historique"
                                                        onclick="viewHistory(<?= $c['id_client'] ?>, '<?= addslashes($c['nom_client']) ?>')">
                                                        <i class="fa-solid fa-history"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-primary border-0 ms-2"
                                                        title="Modifier"
                                                        onclick='openEdit(<?= htmlspecialchars(json_encode($c), ENT_QUOTES, "UTF-8") ?>)'>
                                                        <i class="fa-solid fa-pen"></i>
                                                    </button>
                                                    <?php if (in_array($_SESSION['role'], ['admin', 'super_admin'])): ?>
                                                        <button class="btn btn-sm btn-outline-danger border-0 ms-2"
                                                            title="Supprimer" onclick="confirmDelete(<?= $c['id_client'] ?>)">
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

    <!-- Modals -->
    <div class="modal fade" id="addClientModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark text-white glass-panel shadow-lg border-secondary border-opacity-10">
                <form action="clients.php" method="POST">
                    <div class="modal-header border-secondary border-opacity-20 px-4">
                        <h5 class="modal-title fw-bold">Nouveau Profil Client</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">NOM COMPLET</label>
                            <input type="text" name="fullname" class="form-control bg-dark text-white border-secondary"
                                placeholder="Ex: Jean Dupont" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">TÉLÉPHONE</label>
                            <input type="text" name="phone" class="form-control bg-dark text-white border-secondary"
                                placeholder="Ex: +237 ...">
                        </div>
                        <div class="mb-0">
                            <label class="form-label text-muted small fw-bold">ADRESSE</label>
                            <input type="text" name="address" class="form-control bg-dark text-white border-secondary"
                                placeholder="Ex: Douala, Akwa">
                        </div>
                    </div>
                    <div class="modal-footer border-secondary border-opacity-10 p-4">
                        <button type="submit" class="btn btn-premium w-100 py-2 fw-bold">Enregistrer le Profil</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editClientModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark text-white glass-panel shadow-lg border-secondary border-opacity-10">
                <form action="clients.php" method="POST">
                    <div class="modal-header border-secondary border-opacity-20 px-4">
                        <h5 class="modal-title fw-bold">Modifier Profil Client</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id_client" id="ed_id">
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">NOM COMPLET</label>
                            <input type="text" name="fullname" id="ed_name"
                                class="form-control bg-dark text-white border-secondary" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">TÉLÉPHONE</label>
                            <input type="text" name="phone" id="ed_phone"
                                class="form-control bg-dark text-white border-secondary">
                        </div>
                        <div class="mb-0">
                            <label class="form-label text-muted small fw-bold">ADRESSE</label>
                            <input type="text" name="address" id="ed_address"
                                class="form-control bg-dark text-white border-secondary">
                        </div>
                    </div>
                    <div class="modal-footer border-secondary border-opacity-10 p-4">
                        <button type="submit" class="btn btn-premium w-100 py-2 fw-bold">Mettre à jour le
                            Profil</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- History Modal -->
    <div class="modal fade" id="historyModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content bg-dark text-white glass-panel shadow-lg border-secondary border-opacity-10">
                <div class="modal-header border-secondary border-opacity-20 px-4">
                    <h5 class="modal-title fw-bold" id="histName">Historique d'achats</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="table-responsive">
                        <table class="table table-dark table-hover mb-0 align-middle">
                            <thead class="text-muted small border-bottom border-secondary border-opacity-20">
                                <tr>
                                    <th class="py-3 px-4">DATE & HEURE</th>
                                    <th class="py-3">VENDEUR</th>
                                    <th class="py-3 text-end">MONTANT TOTAL</th>
                                    <th class="py-3 text-end px-4">ACTION</th>
                                </tr>
                            </thead>
                            <tbody id="histBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.getElementById('searchClient');
            const rows = document.querySelectorAll('.client-row');

            searchInput.addEventListener('input', function () {
                const term = this.value.toLowerCase().trim();
                rows.forEach(row => {
                    const text = row.innerText.toLowerCase();
                    row.style.display = text.includes(term) ? '' : 'none';
                });
            });
        });

        function openEdit(c) {
            document.getElementById('ed_id').value = c.id_client;
            document.getElementById('ed_name').value = c.nom_client;
            document.getElementById('ed_phone').value = c.telephone || '';
            document.getElementById('ed_address').value = c.adresse || '';
            new bootstrap.Modal(document.getElementById('editClientModal')).show();
        }

        function viewHistory(id, name) {
            document.getElementById('histName').innerText = "Historique: " + name;
            document.getElementById('histBody').innerHTML = '<tr><td colspan="4" class="text-center py-5"><div class="spinner-border text-primary spinner-border-sm"></div> Chargement...</td></tr>';

            fetch('clients.php?get_history=' + id)
                .then(r => r.json())
                .then(data => {
                    let html = '';
                    if (data.length === 0) html = '<tr><td colspan="4" class="text-center py-5 text-muted">Aucun achat enregistré.</td></tr>';
                    else data.forEach(s => {
                        const date = new Date(s.date_vente).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
                        html += `<tr>
                            <td class="px-4 text-white">${date}</td>
                            <td class="text-muted">${s.username}</td>
                            <td class="text-end fw-bold text-success">${new Intl.NumberFormat('fr-FR').format(s.prix_revente_final)} FCFA</td>
                            <td class="text-end px-4"><a href="invoice.php?id=${s.id_vente}" target="_blank" class="btn btn-sm btn-outline-light"><i class="fa-solid fa-print"></i></a></td>
                        </tr>`;
                    });
                    document.getElementById('histBody').innerHTML = html;
                    new bootstrap.Modal(document.getElementById('historyModal')).show();
                });
        }

        function confirmDelete(id) {
            Swal.fire({
                title: 'Êtes-vous sûr ?',
                text: "Cette action supprimera définitivement le profil de ce client.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Oui, supprimer',
                cancelButtonText: 'Annuler',
                background: '#0f172a',
                color: '#ffffff'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'clients.php?delete=' + id;
                }
            });
        }
    </script>
</body>

</html>