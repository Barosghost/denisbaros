<?php
define('PAGE_ACCESS', 'devices');
require_once '../../backend/includes/auth_required.php';
require_once '../../backend/config/functions.php';
$pageTitle = "Parc Appareils";

// Handle Add Device
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $id_client = $_POST['id_client'];
    $device_type = trim($_POST['device_type']);
    $brand = trim($_POST['brand']);
    $model = trim($_POST['model']);
    $serial_number = trim($_POST['serial_number']);
    $configuration = trim($_POST['configuration']);

    try {
        $stmt = $pdo->prepare("INSERT INTO devices (id_client, device_type, brand, model, serial_number, configuration) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$id_client, $device_type, $brand, $model, $serial_number, $configuration]);
        $success = "Appareil enregistré dans le parc.";
    } catch (PDOException $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

// Handle Update Device
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update') {
    $id_device = $_POST['id_device'];
    $device_type = trim($_POST['device_type']);
    $brand = trim($_POST['brand']);
    $model = trim($_POST['model']);
    $serial_number = trim($_POST['serial_number']);
    $configuration = trim($_POST['configuration']);

    try {
        $stmt = $pdo->prepare("UPDATE devices SET device_type = ?, brand = ?, model = ?, serial_number = ?, configuration = ?, status = ? WHERE id_device = ?");
        $stmt->execute([$device_type, $brand, $model, $serial_number, $configuration, $_POST['status'], $id_device]);
        $success = "Informations de l'appareil mises à jour.";
    } catch (PDOException $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

// Fetch Devices with Client names
$stmt = $pdo->query("SELECT d.*, c.fullname as client_name 
                     FROM devices d 
                     JOIN clients c ON d.id_client = c.id_client 
                     ORDER BY d.created_at DESC");
$devices = $stmt->fetchAll();

// Fetch Clients for dropdown
$clients = $pdo->query("SELECT id_client, fullname FROM clients ORDER BY fullname ASC")->fetchAll();

?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appareils | DENIS FBI STORE</title>
    <link href="../assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=1.5">
    <script src="../assets/vendor/sweetalert2/sweetalert2.all.min.js"></script>
    <style>
        .device-card {
            background: rgba(15, 23, 42, 0.4);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: all 0.3s ease;
        }

        .device-card:hover {
            transform: translateY(-5px);
            background: rgba(15, 23, 42, 0.6);
            border-color: var(--primary-color);
        }

        .device-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-disponible {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .status-en_panne {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .status-en_réparation {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        .status-réparé {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .status-non_réparable {
            background: rgba(100, 116, 139, 0.1);
            color: #64748b;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include '../../backend/includes/sidebar.php'; ?>
        <div id="content">
            <?php include '../../backend/includes/header.php'; ?>

            <div class="fade-in mt-4">
                <h4 class="text-white mb-0">Parc Appareils</h4>
                <p class="text-muted small mb-3">Inventaire des dispositifs clients enregistrés</p>
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
                    <div class="flex-grow-1" style="max-width: 500px;">
                        <div class="input-group">
                            <span class="input-group-text bg-dark border-secondary text-muted px-3"
                                style="border-radius: 12px 0 0 12px;"><i class="fa-solid fa-search"></i></span>
                            <input type="text" id="cardSearch"
                                class="form-control bg-dark text-white border-secondary py-2"
                                placeholder="Rechercher un appareil (marque, modèle, série, client)..."
                                style="border-radius: 0 12px 12px 0;">
                        </div>
                    </div>
                    <button class="btn btn-premium px-4" data-bs-toggle="modal" data-bs-target="#addDeviceModal">
                        <i class="fa-solid fa-plus me-2"></i> Enregistrer un Appareil
                    </button>
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success border-0 bg-success bg-opacity-10 text-success mb-4">
                        <?= $success ?>
                    </div>
                <?php endif; ?>

                <div class="row g-4" id="deviceList">
                    <?php if (empty($devices)): ?>
                        <div class="col-12 text-center py-5">
                            <i class="fa-solid fa-laptop-code fa-3x text-muted mb-3 opacity-20"></i>
                            <p class="text-muted">Aucun appareil enregistré dans le système.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($devices as $d):
                            $icon = "fa-laptop";
                            if (stripos($d['device_type'], 'téléphone') !== false || stripos($d['device_type'], 'smartphone') !== false)
                                $icon = "fa-mobile-screen";
                            if (stripos($d['device_type'], 'tablette') !== false)
                                $icon = "fa-tablet-screen-button";
                            if (stripos($d['device_type'], 'imprimante') !== false)
                                $icon = "fa-print";
                            ?>
                            <div class="col-xl-4 col-md-6 device-card-item">
                                <div class="device-card p-4 h-100 position-relative">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="device-icon me-3">
                                            <i class="fa-solid <?= $icon ?>"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="extra-small text-muted text-uppercase fw-bold">
                                                    <?= htmlspecialchars($d['device_type']) ?>
                                                </div>
                                                <span class="status-badge status-<?= str_replace(' ', '_', $d['status']) ?>">
                                                    <?= htmlspecialchars($d['status']) ?>
                                                </span>
                                            </div>
                                            <div class="text-white fw-bold">
                                                <?= htmlspecialchars($d['brand'] . ' ' . $d['model']) ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="extra-small text-muted mb-1">PROPRIÉTAIRE</div>
                                        <div class="small text-white"><i class="fa-solid fa-user me-2 opacity-50"></i>
                                            <?= htmlspecialchars($d['client_name']) ?>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="extra-small text-muted mb-1">NUMÉRO DE SÉRIE</div>
                                        <div class="small font-monospace text-info">
                                            <?= htmlspecialchars($d['serial_number'] ?: 'N/A') ?>
                                        </div>
                                    </div>

                                    <div
                                        class="mb-0 pt-3 border-top border-white border-opacity-5 d-flex justify-content-between align-items-center">
                                        <button class="btn btn-sm btn-outline-light border-0 opacity-50 hover-opacity-100"
                                            onclick='openEditDevice(<?= htmlspecialchars(json_encode($d), ENT_QUOTES, "UTF-8") ?>)'>
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-info border-0 opacity-50 hover-opacity-100 mx-2"
                                            onclick="showDeviceHistory(<?= $d['id_device'] ?>)">
                                            <i class="fa-solid fa-history"></i>
                                        </button>
                                        <a href="service_requests.php?id_device=<?= $d['id_device'] ?>"
                                            class="btn btn-sm btn-primary py-1 px-3 rounded-pill shadow-sm flex-grow-1 text-center">
                                            <i class="fa-solid fa-tools me-1"></i> RÉPARER
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Modal -->
    <div class="modal fade" id="addDeviceModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark text-white glass-panel shadow-lg border-secondary border-opacity-10">
                <form action="devices.php" method="POST">
                    <div class="modal-header border-secondary border-opacity-20 px-4">
                        <h5 class="modal-title fw-bold">Enregistrer un Appareil</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">PROPRIÉTAIRE (CLIENT)</label>
                            <select name="id_client" class="form-select bg-dark text-white border-secondary" required>
                                <option value="">--- Sélectionner un client ---</option>
                                <?php foreach ($clients as $c): ?>
                                    <option value="<?= $c['id_client'] ?>">
                                        <?= htmlspecialchars($c['fullname']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">TYPE D'APPAREIL</label>
                            <input type="text" name="device_type"
                                class="form-control bg-dark text-white border-secondary"
                                placeholder="Ex: Ordinateur Portable, Smartphone..." required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted small fw-bold">MARQUE</label>
                                <input type="text" name="brand" class="form-control bg-dark text-white border-secondary"
                                    placeholder="Ex: HP, Samsung..." required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted small fw-bold">MODÈLE</label>
                                <input type="text" name="model" class="form-control bg-dark text-white border-secondary"
                                    placeholder="Ex: Pavilion 15, Galaxy S21..." required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">NUMÉRO DE SÉRIE / IMEI</label>
                            <input type="text" name="serial_number"
                                class="form-control bg-dark text-white border-secondary"
                                placeholder="Laissez vide si inconnu">
                        </div>
                        <div class="mb-0">
                            <label class="form-label text-muted small fw-bold">CONFIGURATION / ÉTAT</label>
                            <textarea name="configuration" class="form-control bg-dark text-white border-secondary"
                                rows="2" placeholder="Ex: Core i5, 8GB RAM, Écran fissuré..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-secondary border-opacity-10 p-4">
                        <button type="submit" class="btn btn-premium w-100 py-2 fw-bold">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editDeviceModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark text-white glass-panel shadow-lg border-secondary border-opacity-10">
                <form action="devices.php" method="POST">
                    <div class="modal-header border-secondary border-opacity-20 px-4">
                        <h5 class="modal-title fw-bold">Modifier l'Appareil</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id_device" id="ed_id_device">

                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">TYPE D'APPAREIL</label>
                            <input type="text" name="device_type" id="ed_type"
                                class="form-control bg-dark text-white border-secondary" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted small fw-bold">MARQUE</label>
                                <input type="text" name="brand" id="ed_brand"
                                    class="form-control bg-dark text-white border-secondary" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted small fw-bold">MODÈLE</label>
                                <input type="text" name="model" id="ed_model"
                                    class="form-control bg-dark text-white border-secondary" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">NUMÉRO DE SÉRIE</label>
                            <input type="text" name="serial_number" id="ed_serial"
                                class="form-control bg-dark text-white border-secondary">
                        </div>
                        <div class="mb-0">
                            <textarea name="configuration" id="ed_config"
                                class="form-control bg-dark text-white border-secondary" rows="2"></textarea>
                        </div>
                        <div class="mt-3">
                            <label class="form-label text-muted small fw-bold">ÉTAT / STATUS</label>
                            <select name="status" id="ed_status"
                                class="form-select bg-dark text-white border-secondary">
                                <option value="disponible">Disponible</option>
                                <option value="en_panne">En panne</option>
                                <option value="en_réparation">En réparation</option>
                                <option value="réparé">Réparé</option>
                                <option value="non_réparable">Non réparable</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer border-secondary border-opacity-10 p-4">
                        <button type="submit" class="btn btn-premium w-100 py-2 fw-bold">Mettre à jour</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
    <script>
        function openEditDevice(d) {
            document.getElementById('ed_id_device').value = d.id_device;
            document.getElementById('ed_type').value = d.device_type;
            document.getElementById('ed_brand').value = d.brand;
            document.getElementById('ed_model').value = d.model;
            document.getElementById('ed_serial').value = d.serial_number || '';
            document.getElementById('ed_config').value = d.configuration || '';
            document.getElementById('ed_status').value = d.status;
            new bootstrap.Modal(document.getElementById('editDeviceModal')).show();
        }

        function showDeviceHistory(id) {
            Swal.fire({
                title: 'Chargement de l\'historique...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            fetch('service_actions.php?action=get_device_history&id=' + id)
                .then(response => response.text())
                .then(html => {
                    Swal.fire({
                        title: 'Historique de l\'appareil',
                        html: html,
                        width: '800px',
                        showConfirmButton: false,
                        showCloseButton: true,
                        customClass: {
                            popup: 'bg-dark text-white glass-panel',
                        }
                    });
                });
        }

        document.getElementById('cardSearch').addEventListener('input', function () {
            const term = this.value.toLowerCase().trim();
            document.querySelectorAll('.device-card-item').forEach(item => {
                const text = item.innerText.toLowerCase();
                item.style.display = text.includes(term) ? '' : 'none';
            });
        });
    </script>
</body>

</html>