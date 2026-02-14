<?php
define('PAGE_ACCESS', 'repair_reception');
require_once '../../backend/includes/auth_required.php';
require_once '../../backend/config/db.php';

$pageTitle = "Réception Machine";

try {
    $clients = $pdo->query("SELECT id_client, nom_client as fullname FROM clients ORDER BY nom_client ASC")->fetchAll();
    // Produits pour lier le dossier SAV à un article (garantie par produit)
    $products = $pdo->query("SELECT id_produit, designation, duree_garantie_mois FROM produits ORDER BY designation ASC")->fetchAll();
} catch (Exception $e) {
    $clients = [];
    $products = [];
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réception Machine | DENIS FBI STORE</title>
    <link href="../assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=1.4">
    <script src="../assets/vendor/sweetalert2/sweetalert2.all.min.js"></script>
</head>

<body>

    <div class="wrapper">
        <?php include '../../backend/includes/sidebar.php'; ?>

        <div id="content">
            <?php include '../../backend/includes/header.php'; ?>

            <div class="fade-in mt-4">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <h3 class="text-white fw-bold mb-0">Réception de Machine</h3>
                    <button class="btn btn-outline-light" onclick="location.href='repairs.php'">
                        <i class="fa-solid fa-arrow-left me-2"></i>Retour
                    </button>
                </div>

                <div class="row">
                    <div class="col-lg-8 mx-auto">
                        <div class="card bg-dark border-0 shadow-lg">
                            <div class="card-body p-5">
                                <form id="receptionForm">
                                    <div class="row g-4">
                                        <!-- Serial Number -->
                                        <div class="col-md-6">
                                            <label
                                                class="form-label text-muted extra-small text-uppercase fw-bold">Numéro
                                                de Série</label>
                                            <input type="text"
                                                class="form-control bg-dark text-white border-white border-opacity-20"
                                                id="num_serie" placeholder="Ex: SN-2024-XXXX">
                                        </div>

                                        <!-- Model -->
                                        <div class="col-md-6">
                                            <label
                                                class="form-label text-muted extra-small text-uppercase fw-bold">Modèle
                                                / Marque *</label>
                                            <input type="text"
                                                class="form-control bg-dark text-white border-white border-opacity-20"
                                                id="appareil_modele" required
                                                placeholder="Ex: iPhone 14 Pro, HP EliteBook...">
                                        </div>

                                        <!-- Linked Product (for warranty & stats) -->
                                        <div class="col-md-6">
                                            <label
                                                class="form-label text-muted extra-small text-uppercase fw-bold">Produit lié
                                                (optionnel)</label>
                                            <select
                                                class="form-select bg-dark text-white border-white border-opacity-20"
                                                id="id_produit">
                                                <option value="">-- Aucun produit spécifique --</option>
                                                <?php foreach ($products as $prod): ?>
                                                    <option value="<?= $prod['id_produit'] ?>">
                                                        <?= htmlspecialchars($prod['designation']) ?>
                                                        (<?= (int) $prod['duree_garantie_mois'] ?> mois garantie)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="text-muted extra-small mt-1">
                                                Permet de lier le dossier SAV à un produit du stock
                                                (durée de garantie suivie par produit).
                                            </div>
                                        </div>

                                        <!-- Entry State -->
                                        <div class="col-md-6">
                                            <label class="form-label text-muted extra-small text-uppercase fw-bold">État
                                                Physique *</label>
                                            <select
                                                class="form-select bg-dark text-white border-white border-opacity-20"
                                                id="etat_physique_entree" required>
                                                <option value="Bon état">Bon état</option>
                                                <option value="Rayures d'usage">Rayures d'usage</option>
                                                <option value="Écran fissuré">Écran fissuré</option>
                                                <option value="Choc visible">Choc visible</option>
                                                <option value="Oxydation">Traces d'oxydation</option>
                                                <option value="Neuf défaut">Neuf (Défaut déballage)</option>
                                            </select>
                                        </div>

                                        <!-- Client -->
                                        <div class="col-md-6">
                                            <label
                                                class="form-label text-muted extra-small text-uppercase fw-bold">Client
                                                *</label>
                                            <div class="input-group">
                                                <select
                                                    class="form-select bg-dark text-white border-white border-opacity-20"
                                                    id="id_client" required>
                                                    <option value="">-- Sélectionner un client --</option>
                                                    <?php foreach ($clients as $client): ?>
                                                        <option value="<?= $client['id_client'] ?>">
                                                            <?= htmlspecialchars($client['fullname']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button class="btn btn-outline-info" type="button"
                                                    onclick="openNewClientModal()">
                                                    <i class="fa-solid fa-plus"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Estimated Cost -->
                                        <div class="col-md-6">
                                            <label class="form-label text-muted extra-small text-uppercase fw-bold">Coût
                                                Estimé (FCFA)</label>
                                            <input type="number"
                                                class="form-control bg-dark text-white border-white border-opacity-20"
                                                id="cout_estime" placeholder="0" min="0">
                                        </div>

                                        <!-- Warranty -->
                                        <div class="col-md-6">
                                            <label
                                                class="form-label text-muted extra-small text-uppercase fw-bold">Garantie</label>
                                            <div class="form-check form-switch mt-2">
                                                <input class="form-check-input" type="checkbox" id="est_sous_garantie">
                                                <label class="form-check-label text-white" for="est_sous_garantie">Sous
                                                    Garantie</label>
                                            </div>
                                        </div>

                                        <!-- Failure Reason -->
                                        <div class="col-12">
                                            <label
                                                class="form-label text-muted extra-small text-uppercase fw-bold">Panne
                                                Déclarée *</label>
                                            <textarea
                                                class="form-control bg-dark text-white border-white border-opacity-20"
                                                id="panne_declaree" rows="3" required
                                                placeholder="Décrivez la panne signalée par le client..."></textarea>
                                        </div>

                                        <!-- Submit -->
                                        <div class="col-12">
                                            <button type="submit"
                                                class="btn btn-premium w-100 py-3 fw-bold text-uppercase">
                                                <i class="fa-solid fa-check me-2"></i>Enregistrer le Dossier
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- New Client Modal -->
    <div class="modal fade" id="newClientModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark border-0 shadow-lg">
                <div class="modal-header border-bottom border-white border-opacity-10">
                    <h5 class="modal-title text-white">
                        <i class="fa-solid fa-user-plus me-2"></i>Nouveau Client
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="newClientForm">
                        <div class="mb-3">
                            <label class="form-label text-muted extra-small text-uppercase fw-bold">Nom Complet
                                *</label>
                            <input type="text" class="form-control bg-dark text-white border-white border-opacity-20"
                                id="client_fullname" required placeholder="Ex: Jean Dupont">
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted extra-small text-uppercase fw-bold">Téléphone *</label>
                            <input type="text" class="form-control bg-dark text-white border-white border-opacity-20"
                                id="client_phone" required placeholder="Ex: 6XX XX XX XX">
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted extra-small text-uppercase fw-bold">Adresse</label>
                            <textarea class="form-control bg-dark text-white border-white border-opacity-20"
                                id="client_address" rows="2" placeholder="Quartier, Ville..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-top border-white border-opacity-10">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-info" onclick="createNewClient()">
                        <i class="fa-solid fa-save me-2"></i>Enregistrer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
    <script>
        let newClientModal; // Define globally

        function openNewClientModal() {
            var el = document.getElementById('newClientModal');
            if (!newClientModal) {
                newClientModal = new bootstrap.Modal(el);
            }
            newClientModal.show();
        }

        function createNewClient() {
            const fullname = document.getElementById('client_fullname').value;
            const phone = document.getElementById('client_phone').value;
            const address = document.getElementById('client_address').value;

            if (!fullname || !phone) {
                Swal.fire('Erreur', 'Nom et Téléphone requis.', 'error');
                return;
            }

            fetch('../../backend/actions/repairs/create_client.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ fullname, phone, address })
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const select = document.getElementById('id_client');
                        const opt = document.createElement('option');
                        opt.value = data.client_id;
                        opt.textContent = fullname;
                        opt.selected = true;
                        select.appendChild(opt);

                        if (newClientModal) newClientModal.hide();
                        document.getElementById('newClientForm').reset();
                        Swal.fire('Succès', 'Client ajouté.', 'success');
                    } else {
                        Swal.fire('Erreur', data.message, 'error');
                    }
                });
        }

        document.getElementById('receptionForm').addEventListener('submit', function (e) {
            e.preventDefault();

            const data = {
                num_serie: document.getElementById('num_serie').value,
                appareil_modele: document.getElementById('appareil_modele').value,
                etat_physique_entree: document.getElementById('etat_physique_entree').value,
                id_client: document.getElementById('id_client').value,
                id_produit: document.getElementById('id_produit') ? (document.getElementById('id_produit').value || null) : null,
                cout_estime: document.getElementById('cout_estime').value || 0,
                est_sous_garantie: document.getElementById('est_sous_garantie').checked ? 1 : 0,
                panne_declaree: document.getElementById('panne_declaree').value
            };

            fetch('../../backend/actions/repairs/reception.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Dossier Créé',
                            text: 'Le dossier SAV a été ouvert avec succès.',
                            confirmButtonText: 'Voir la liste'
                        }).then(() => {
                            location.href = 'repairs.php';
                        });
                    } else {
                        Swal.fire('Erreur', data.message, 'error');
                    }
                })
                .catch(err => Swal.fire('Erreur', 'Erreur de connexion', 'error'));
        });
    </script>
</body>

</html>