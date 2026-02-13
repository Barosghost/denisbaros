<?php
define('PAGE_ACCESS', 'commissions');
require_once '../../backend/includes/auth_required.php';
$pageTitle = "Suivi des Commissions";

// ventes: id_vendeur, id_revendeur (si non NULL = vente revendeur), pas de type_vente ni statut_commission
// Commission = prix_revente_final * (taux_commission_fixe / 100)
$sql = "SELECT v.*, r.nom_partenaire as reseller_name, c.nom_client as client_name, (v.prix_revente_final * (r.taux_commission_fixe / 100)) as commission_amount
        FROM ventes v 
        JOIN revendeurs r ON v.id_revendeur = r.id_revendeur 
        LEFT JOIN clients c ON v.id_client = c.id_client
        WHERE v.id_revendeur IS NOT NULL
        ORDER BY v.date_vente DESC";
$sales = $pdo->query($sql)->fetchAll();

$total_commissions = $pdo->query("SELECT COALESCE(SUM(v.prix_revente_final * (r.taux_commission_fixe / 100)), 0) FROM ventes v JOIN revendeurs r ON v.id_revendeur = r.id_revendeur WHERE v.id_revendeur IS NOT NULL")->fetchColumn() ?: 0;
$total_pending = $total_commissions;
$total_paid = 0;
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commissions | DENIS FBI STORE</title>
    <link href="../assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=1.5">
    <script src="../assets/vendor/sweetalert2/sweetalert2.all.min.js"></script>
</head>

<body>
    <div class="wrapper">
        <?php include '../../backend/includes/sidebar.php'; ?>
        <div id="content">
            <?php include '../../backend/includes/header.php'; ?>

            <div class="fade-in mt-4">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card bg-dark border-0 glass-panel p-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="stat-icon bg-warning bg-opacity-10 text-warning rounded-3 p-3">
                                    <i class="fa-solid fa-hourglass-half fa-2x"></i>
                                </div>
                                <div>
                                    <h3 class="text-white fw-bold mb-0">
                                        <?= number_format($total_pending, 0, ',', ' ') ?> FCFA
                                    </h3>
                                    <div class="text-muted small">Commissions en attente</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-dark border-0 glass-panel p-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="stat-icon bg-success bg-opacity-10 text-success rounded-3 p-3">
                                    <i class="fa-solid fa-check-double fa-2x"></i>
                                </div>
                                <div>
                                    <h3 class="text-white fw-bold mb-0">
                                        <?= number_format($total_paid, 0, ',', ' ') ?> FCFA
                                    </h3>
                                    <div class="text-muted small">Commissions payées</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
                    <div class="flex-grow-1" style="max-width: 400px;">
                        <div class="input-group">
                            <span class="input-group-text bg-dark border-secondary text-muted px-3"
                                style="border-radius: 12px 0 0 12px;"><i class="fa-solid fa-search"></i></span>
                            <input type="text" id="tableSearch"
                                class="form-control bg-dark text-white border-secondary py-2"
                                placeholder="Rechercher une commission..." style="border-radius: 0 12px 12px 0;">
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-premium" onclick="paySelected()">
                            <i class="fa-solid fa-hand-holding-dollar me-2"></i> Payer la sélection
                        </button>
                    </div>
                </div>

                <div class="card bg-dark border-0 glass-panel">
                    <div class="table-responsive">
                        <table class="table table-dark table-hover mb-0 align-middle">
                            <thead class="border-bottom border-secondary border-opacity-20">
                                <tr class="text-muted small">
                                    <th class="ps-4"><input type="checkbox" id="selectAll"></th>
                                    <th>DATE</th>
                                    <th>REVENDEUR</th>
                                    <th>VENTE</th>
                                    <th>COMMISSION</th>
                                    <th class="text-center">STATUT</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sales as $s): ?>
                                    <tr class="commission-row">
                                        <td class="ps-4">
                                            <input type="checkbox" class="sale-check" value="<?= $s['id_vente'] ?>">
                                        </td>
                                        <td>
                                            <div>
                                                <?= date('d/m/Y', strtotime($s['date_vente'])) ?>
                                            </div>
                                            <div class="extra-small text-muted">
                                                <?= date('H:i', strtotime($s['date_vente'])) ?>
                                            </div>
                                        </td>
                                        <td class="fw-bold text-white">
                                            <?= htmlspecialchars($s['reseller_name']) ?>
                                        </td>
                                        <td>
                                            <div>
                                                <?= number_format($s['prix_revente_final'], 0, ',', ' ') ?> FCFA
                                            </div>
                                            <div class="extra-small text-muted">Client:
                                                <?= htmlspecialchars($s['client_name'] ?? 'Passage') ?>
                                            </div>
                                        </td>
                                        <td class="text-warning fw-bold">
                                            <?= number_format($s['commission_amount'], 0, ',', ' ') ?> FCFA
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-warning bg-opacity-10 text-warning">En attente</span>
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

    <script src="../assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
    <script>
        document.getElementById('selectAll').addEventListener('change', function () {
            document.querySelectorAll('.sale-check').forEach(cb => cb.checked = this.checked);
        });

        function paySelected() {
            const selected = Array.from(document.querySelectorAll('.sale-check:checked')).map(cb => cb.value);

            if (selected.length === 0) {
                Swal.fire('Info', 'Aucune commission sélectionnée', 'info');
                return;
            }

            Swal.fire({
                title: 'Confirmer le paiement ?',
                text: `${selected.length} commissions sélectionnées. Êtes-vous sûr de marquer comme PAYÉ ?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                confirmButtonText: 'Oui, Payer'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('../../backend/actions/pay_commission.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ sales_ids: selected })
                    })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('Succès', data.message, 'success').then(() => location.reload());
                            } else {
                                Swal.fire('Erreur', data.message, 'error');
                            }
                        });
                }
            });
        }

        document.getElementById('tableSearch').addEventListener('input', function () {
            const term = this.value.toLowerCase().trim();
            document.querySelectorAll('.commission-row').forEach(row => {
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(term) ? '' : 'none';
            });
        });
    </script>
</body>

</html>