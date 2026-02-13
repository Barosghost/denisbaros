<?php
define('PAGE_ACCESS', 'repairs');
require_once '../../backend/includes/auth_required.php';
require_once '../../backend/config/db.php';
$pageTitle = "Gestion des Réparations";
// Stats et liste chargés via JS (get_stats.php, get_list.php)
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réparations | DENIS FBI STORE</title>
    <link href="../assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=1.4">
    <script src="../assets/vendor/sweetalert2/sweetalert2.all.min.js"></script>
    <style>
        .repair-stat-card {
            background: rgba(30, 41, 59, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 24px;
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
            backdrop-filter: blur(10px);
        }

        .repair-stat-card:hover {
            background: rgba(30, 41, 59, 0.6);
            transform: translateY(-5px);
            border-color: rgba(255, 255, 255, 0.1);
        }

        .stat-icon-large {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .repairs-card {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .status-badge {
            padding: 8px 16px;
            font-weight: 600;
            font-size: 0.85rem;
            border-radius: 12px;
        }

        .table-dark {
            --bs-table-bg: transparent;
        }

        .table thead th {
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            font-weight: 700;
            color: #94a3b8;
            padding: 20px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
        }

        .table tbody td {
            padding: 16px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
            vertical-align: middle;
        }

        .table-hover tbody tr:hover {
            background: rgba(255, 255, 255, 0.02);
        }
    </style>
</head>

<body>

    <div class="wrapper">
        <?php include '../../backend/includes/sidebar.php'; ?>

        <div id="content">
            <?php include '../../backend/includes/header.php'; ?>

            <div class="fade-in mt-4">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <h3 class="text-white fw-bold mb-0">Service Réparation</h3>
                    <button class="btn btn-premium px-4" onclick="location.href='repair_reception.php'">
                        <i class="fa-solid fa-plus me-2"></i>Nouvelle Réception
                    </button>
                </div>

                <!-- Statistics Cards -->
                <div class="row g-4 mb-5" id="statsContainer">
                    <div class="col-xl-3 col-md-6">
                        <div class="repair-stat-card">
                            <div class="stat-icon-large bg-primary bg-opacity-10 text-primary">
                                <i class="fa-solid fa-screwdriver-wrench"></i>
                            </div>
                            <div>
                                <div class="text-muted small text-uppercase fw-bold mb-1">Total Réparations</div>
                                <div class="text-white h3 fw-bold mb-0" id="totalRepairs">0</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="repair-stat-card">
                            <div class="stat-icon-large bg-success bg-opacity-10 text-success">
                                <i class="fa-solid fa-circle-check"></i>
                            </div>
                            <div>
                                <div class="text-muted small text-uppercase fw-bold mb-1">En Attente / Diag</div>
                                <div class="text-white h3 fw-bold mb-0" id="pendingRepairs">0</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="repair-stat-card">
                            <div class="stat-icon-large bg-warning bg-opacity-10 text-warning">
                                <i class="fa-solid fa-coins"></i>
                            </div>
                            <div>
                                <div class="text-muted small text-uppercase fw-bold mb-1">En Réparation</div>
                                <div class="text-white h3 fw-bold mb-0" id="inProgressRepairs">0</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="repair-stat-card">
                            <div class="stat-icon-large bg-info bg-opacity-10 text-info">
                                <i class="fa-solid fa-clock"></i>
                            </div>
                            <div>
                                <div class="text-muted small text-uppercase fw-bold mb-1">Livrées (Mois)</div>
                                <div class="text-white h3 fw-bold mb-0" id="completedMonth">0</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="repairs-card mb-4">
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label text-muted extra-small text-uppercase fw-bold">Statut</label>
                                <select id="statusFilter"
                                    class="form-select bg-dark text-white border-white border-opacity-20">
                                    <option value="">Tous les statuts</option>
                                    <option value="en_attente">En attente</option>
                                    <option value="en_diagnostic">En diagnostic</option>
                                    <option value="en_reparation">En réparation</option>
                                    <option value="pret">Prêt (Réparé)</option>
                                    <option value="livre">Livré / Sorti</option>
                                    <option value="neuf_hs">Neuf HS / Irréparable</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label
                                    class="form-label text-muted extra-small text-uppercase fw-bold">Technicien</label>
                                <select id="technicianFilter"
                                    class="form-select bg-dark text-white border-white border-opacity-20">
                                    <option value="">Tous les techniciens</option>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button class="btn btn-premium w-100" onclick="loadRepairs()">
                                    <i class="fa-solid fa-filter me-2"></i>Filtrer
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Repairs Table -->
                <div class="repairs-card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-dark table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>N° Série</th>
                                        <th>Modèle</th>
                                        <th>Date Entrée</th>
                                        <th>Statut</th>
                                        <th>Technicien</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="repairsTable">
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-5">
                                            <i class="fa-solid fa-spinner fa-spin fa-2x mb-3"></i>
                                            <div>Chargement...</div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
    <script>
        async function loadStats() {
            try {
                const response = await fetch('../../backend/actions/repairs/get_stats.php');
                const data = await response.json();

                if (data.success) {
                    const s = data.stats;
                    document.getElementById('totalRepairs').textContent = s.total_repairs;
                    document.getElementById('pendingRepairs').textContent = s.pending;
                    document.getElementById('inProgressRepairs').textContent = s.in_progress;
                    document.getElementById('completedMonth').textContent = s.completed_month;
                    const sel = document.getElementById('technicianFilter');
                    if (sel && s.technicians && s.technicians.length) {
                        sel.innerHTML = '<option value="">Tous les techniciens</option>' +
                            s.technicians.map(t => '<option value="' + t.id_technician + '">' + (t.fullname || t.technician_name || '') + '</option>').join('');
                    }
                }
            } catch (error) {
                console.error('Error loading stats:', error);
            }
        }

        // Load repairs list
        async function loadRepairs() {
            const status = document.getElementById('statusFilter').value;
            const technician = document.getElementById('technicianFilter').value;

            let url = '../../backend/actions/repairs/get_list.php?';
            if (status) url += 'status=' + status + '&';
            if (technician) url += 'technician=' + technician;

            try {
                const response = await fetch(url);
                const data = await response.json();

                if (data.success) {
                    const tbody = document.getElementById('repairsTable');
                    tbody.innerHTML = '';

                    if (data.repairs.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-5">Aucune réparation trouvée</td></tr>';
                        return;
                    }

                    data.repairs.forEach(repair => {
                        const statusClass = getStatusClass(repair.status);
                        const statusText = getStatusText(repair.status);

                        const row = `
                            <tr>
                                <td>
                                    <div class="text-white fw-bold">${repair.serial_number}</div>
                                    <div class="extra-small text-muted">ID: #${String(repair.id).padStart(4, '0')}</div>
                                </td>
                                <td>${repair.model}</td>
                                <td>
                                    <div class="text-muted small">
                                        <i class="fa-regular fa-calendar me-1"></i>
                                        ${new Date(repair.entry_date).toLocaleDateString('fr-FR')}
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge ${statusClass}">${statusText}</span>
                                </td>
                                <td>
                                    <span class="text-muted">${repair.technician_name || 'Non assigné'}</span>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-light px-3" onclick="viewDetails(${repair.id})">
                                        <i class="fa-solid fa-eye me-1"></i>Détails
                                    </button>
                                </td>
                            </tr>
                        `;
                        tbody.innerHTML += row;
                    });
                }
            } catch (error) {
                console.error('Error loading repairs:', error);
            }
        }

        function getStatusClass(status) {
            const classes = {
                'en_attente': 'bg-secondary',
                'en_diagnostic': 'bg-info',
                'en_reparation': 'bg-primary',
                'pret': 'bg-success',
                'livre': 'bg-dark',
                'neuf_hs': 'bg-danger'
            };
            return classes[status] || 'bg-secondary';
        }

        function getStatusText(status) {
            const texts = {
                'en_attente': 'En attente',
                'en_diagnostic': 'Diagnostic',
                'en_reparation': 'En réparation',
                'pret': 'Réparé / Prêt',
                'livre': 'Livré',
                'neuf_hs': 'Neuf HS / Irrép.'
            };
            return texts[status] || status;
        }

        function viewDetails(id) {
            location.href = 'repair_details.php?id=' + id;
        }

        // Initialize
        loadStats();
        loadRepairs();
    </script>
</body>

</html>