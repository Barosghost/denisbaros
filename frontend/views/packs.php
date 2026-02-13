<?php
define('PAGE_ACCESS', 'packs');
require_once '../../backend/includes/auth_required.php';
require_once '../../backend/config/db.php';

$pageTitle = "Packs Produits";

// Liste des packs avec résumé des composants
$sql = "
    SELECT p.*,
           GROUP_CONCAT(CONCAT(pc.quantite, 'x ', pr.designation) SEPARATOR ', ') as components_label
    FROM packs p
    LEFT JOIN pack_composants pc ON pc.id_pack = p.id_pack
    LEFT JOIN produits pr ON pr.id_produit = pc.id_produit
    GROUP BY p.id_pack
    ORDER BY p.nom_pack ASC
";
$packs = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Produits pour composer les packs
$products = $pdo->query("SELECT id_produit, designation FROM produits ORDER BY designation ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Packs | DENIS FBI STORE</title>
    <link href="../assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=1.6">
    <script src="../assets/vendor/sweetalert2/sweetalert2.all.min.js"></script>
    <style>
        .pack-card {
            background: linear-gradient(145deg, rgba(30, 41, 59, 0.6), rgba(15, 23, 42, 0.8));
            border: 1px solid rgba(148, 163, 184, 0.25);
            border-radius: 18px;
            padding: 20px;
            height: 100%;
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        }

        .pack-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.7);
            border-color: rgba(94, 234, 212, 0.7);
        }

        .pack-name {
            font-weight: 700;
            font-size: 1.05rem;
        }

        .pack-price {
            font-size: 1.15rem;
            font-weight: 700;
        }

        .component-pill {
            background: rgba(15, 23, 42, 0.9);
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 0.75rem;
            border: 1px solid rgba(148, 163, 184, 0.4);
            margin: 2px;
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
                    <div>
                        <h3 class="text-white fw-bold mb-1">
                            <i class="fa-solid fa-layer-group me-2 text-info"></i>Packs Produits
                        </h3>
                        <p class="text-muted small mb-0">
                            Créez des bundles de produits (packs) pour les ventes groupées et les offres promo.
                        </p>
                    </div>
                    <button class="btn btn-premium px-4" onclick="openPackModal('add')">
                        <i class="fa-solid fa-plus me-2"></i>Nouveau Pack
                    </button>
                </div>

                <div class="row g-4">
                    <?php foreach ($packs as $pack): ?>
                        <div class="col-xl-4 col-md-6">
                            <div class="pack-card position-relative">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <div class="pack-name text-white mb-1">
                                            <?= htmlspecialchars($pack['nom_pack']) ?>
                                        </div>
                                        <div class="text-muted extra-small">
                                            ID #<?= (int) $pack['id_pack'] ?>
                                        </div>
                                    </div>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-light border-0" data-bs-toggle="dropdown">
                                            <i class="fa-solid fa-ellipsis-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end">
                                            <li>
                                                <a href="#" class="dropdown-item"
                                                    onclick='openPackModal("update", <?= json_encode($pack, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>)'>
                                                    <i class="fa-solid fa-pen me-2"></i>Modifier
                                                </a>
                                            </li>
                                            <li>
            <a href="#" class="dropdown-item text-danger" onclick="deletePack(<?= (int) $pack['id_pack'] ?>)">
                                                    <i class="fa-solid fa-trash me-2"></i>Supprimer
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="pack-price text-teal-300 text-info">
                                        <?= number_format($pack['prix_pack'], 0, ',', ' ') ?> FCFA
                                    </div>
                                </div>

                                <div class="mb-2">
                                    <div class="text-muted extra-small text-uppercase fw-bold mb-1">
                                        Composants
                                    </div>
                                    <div>
                                        <?php if (!empty($pack['components_label'])): ?>
                                            <?php foreach (explode(',', $pack['components_label']) as $comp): ?>
                                                <span class="component-pill text-muted">
                                                    <?= htmlspecialchars(trim($comp)) ?>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="text-muted extra-small">Aucun produit attaché.</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if (!empty($pack['description'])): ?>
                                    <div class="mt-3 text-muted small">
                                        <?= nl2br(htmlspecialchars($pack['description'])) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (empty($packs)): ?>
                        <div class="col-12 text-center py-5">
                            <div class="text-muted opacity-50 mb-3">
                                <i class="fa-solid fa-layer-group fa-3x"></i>
                            </div>
                            <h5 class="text-muted">Aucun pack configuré pour le moment.</h5>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Pack -->
    <div class="modal fade" id="packModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content bg-dark text-white border-0 glass-panel">
                <div class="modal-header border-bottom border-secondary border-opacity-20">
                    <h5 class="modal-title fw-bold" id="packModalTitle">Nouveau Pack</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="packForm">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" id="pack_action" value="add">
                        <input type="hidden" name="id_pack" id="id_pack">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold text-uppercase">Nom du pack *</label>
                                <input type="text" name="nom_pack" id="nom_pack"
                                    class="form-control bg-dark text-white border-secondary" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold text-uppercase">Prix du pack (FCFA) *</label>
                                <input type="number" name="prix_pack" id="prix_pack" min="0"
                                    class="form-control bg-dark text-white border-secondary" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label text-muted small fw-bold text-uppercase">Description</label>
                                <textarea name="description" id="description" rows="2"
                                    class="form-control bg-dark text-white border-secondary"
                                    placeholder="Détails de l'offre..."></textarea>
                            </div>
                        </div>

                        <hr class="border-secondary border-opacity-25 my-3">

                        <div class="mb-2 d-flex justify-content-between align-items-center">
                            <span class="text-muted extra-small text-uppercase fw-bold">Produits du pack</span>
                            <button type="button" class="btn btn-sm btn-outline-info" onclick="addComponentRow()">
                                <i class="fa-solid fa-plus me-1"></i> Ajouter un produit
                            </button>
                        </div>

                        <div id="componentsContainer" class="border border-secondary border-opacity-25 rounded p-2">
                            <!-- lignes de composants insérées via JS -->
                        </div>
                    </div>
                    <div class="modal-footer border-top border-secondary border-opacity-20">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-premium px-4">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <script>
        const packModal = new bootstrap.Modal(document.getElementById('packModal'));
        const products = <?= json_encode($products) ?>;

        function buildProductSelect(selectedId) {
            const sel = document.createElement('select');
            sel.className = 'form-select bg-dark text-white border-secondary';
            sel.name = 'component_product';
            const optEmpty = document.createElement('option');
            optEmpty.value = '';
            optEmpty.textContent = '-- Produit --';
            sel.appendChild(optEmpty);
            products.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id_produit;
                opt.textContent = p.designation;
                if (selectedId && String(selectedId) === String(p.id_produit)) opt.selected = true;
                sel.appendChild(opt);
            });
            return sel;
        }

        function addComponentRow(existing) {
            const container = document.getElementById('componentsContainer');
            const row = document.createElement('div');
            row.className = 'row g-2 align-items-center mb-2 component-row';

            const colProd = document.createElement('div');
            colProd.className = 'col-md-7';
            colProd.appendChild(buildProductSelect(existing && existing.id_produit));

            const colQty = document.createElement('div');
            colQty.className = 'col-md-3';
            const qtyInput = document.createElement('input');
            qtyInput.type = 'number';
            qtyInput.min = '1';
            qtyInput.value = existing && existing.quantite ? existing.quantite : 1;
            qtyInput.className = 'form-control bg-dark text-white border-secondary';
            qtyInput.name = 'component_qty';
            colQty.appendChild(qtyInput);

            const colDel = document.createElement('div');
            colDel.className = 'col-md-2 text-end';
            const btnDel = document.createElement('button');
            btnDel.type = 'button';
            btnDel.className = 'btn btn-sm btn-outline-danger';
            btnDel.innerHTML = '<i class="fa-solid fa-trash"></i>';
            btnDel.onclick = () => row.remove();
            colDel.appendChild(btnDel);

            row.appendChild(colProd);
            row.appendChild(colQty);
            row.appendChild(colDel);
            container.appendChild(row);
        }

        function openPackModal(type, data) {
            document.getElementById('pack_action').value = type;
            document.getElementById('packModalTitle').textContent = type === 'add' ? 'Nouveau Pack' : 'Modifier Pack';
            document.getElementById('packForm').reset();
            document.getElementById('componentsContainer').innerHTML = '';
            document.getElementById('id_pack').value = '';

            if (type === 'update' && data) {
                document.getElementById('id_pack').value = data.id_pack;
                document.getElementById('nom_pack').value = data.nom_pack;
                document.getElementById('prix_pack').value = data.prix_pack;
                document.getElementById('description').value = data.description || '';
                // Pour l'instant on ne recharge pas la liste détaillée des composants,
                // elle pourra être gérée via une API dédiée si nécessaire.
            }

            if (type === 'add') {
                addComponentRow();
            }

            packModal.show();
        }

        document.getElementById('packForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const form = this;
            const action = document.getElementById('pack_action').value;

            const rows = document.querySelectorAll('#componentsContainer .component-row');
            const components = [];
            rows.forEach(row => {
                const prodSel = row.querySelector('select[name="component_product"]');
                const qtyInput = row.querySelector('input[name="component_qty"]');
                if (prodSel && prodSel.value && qtyInput && qtyInput.value > 0) {
                    components.push({
                        id_produit: prodSel.value,
                        quantite: qtyInput.value
                    });
                }
            });

            const fd = new FormData(form);
            fd.append('components', JSON.stringify(components));

            fetch('../../backend/actions/process_pack.php', {
                method: 'POST',
                body: fd
            }).then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('Succès', data.message, 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Erreur', data.message || 'Erreur lors de l\'enregistrement du pack.', 'error');
                    }
                }).catch(() => Swal.fire('Erreur', 'Requête impossible.', 'error'));
        });

        function deletePack(id) {
            Swal.fire({
                title: 'Supprimer ce pack ?',
                text: 'Cette action est irréversible.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Oui, supprimer',
                cancelButtonText: 'Annuler'
            }).then(result => {
                if (!result.isConfirmed) return;
                const fd = new FormData();
                fd.append('action', 'delete');
                fd.append('id_pack', id);
                fetch('../../backend/actions/process_pack.php', {
                    method: 'POST',
                    body: fd
                }).then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Supprimé', data.message, 'success').then(() => location.reload());
                        } else {
                            Swal.fire('Erreur', data.message || 'Suppression impossible.', 'error');
                        }
                    }).catch(() => Swal.fire('Erreur', 'Requête impossible.', 'error'));
            });
        }
    </script>
</body>

</html>

