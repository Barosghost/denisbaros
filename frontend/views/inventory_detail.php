<?php
define('PAGE_ACCESS', 'inventory_detail');
require_once '../../backend/includes/auth_required.php';
$pageTitle = "Détail Inventaire";
require_once '../../backend/config/db.php';
$id_product = $_GET['id'] ?? 0;

$product = $pdo->prepare("SELECT designation as name FROM produits WHERE id_produit = ?");
$product->execute([$id_product]);
$product = $product->fetch();
if (!$product) {
    die("Produit introuvable.");
}

// Check if product_items exists, if not, show empty
$items = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM product_items WHERE id_product = ? ORDER BY created_at DESC");
    $stmt->execute([$id_product]);
    $items = $stmt->fetchAll();
} catch (Exception $e) {
    // Table might not exist yet in this schema
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Détail Inventaire |
        <?= htmlspecialchars($product['name']) ?>
    </title>
    <link href="../assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=1.5">
</head>

<body class="bg-dark text-white">
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0">
                    <?= htmlspecialchars($product['name']) ?>
                </h2>
                <p class="text-muted">Inventaire détaillé des machines (S/N)</p>
            </div>
            <a href="stock.php" class="btn btn-outline-light"><i class="fa-solid fa-arrow-left me-2"></i>Retour au
                Stock</a>
        </div>

        <div class="card bg-dark border-secondary border-opacity-20 rounded-4 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0">
                    <thead>
                        <tr class="text-muted extra-small">
                            <th>NUMÉRO DE SÉRIE</th>
                            <th>ÉTAT ACTUEL</th>
                            <th>STATUT</th>
                            <th>VENDABLE</th>
                            <th>ENREGISTRÉ LE</th>
                            <th class="text-end">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted small">Aucune machine sérialisée
                                    enregistrée pour ce produit.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td class="fw-bold font-monospace text-info">
                                        <?= htmlspecialchars($item['serial_number']) ?>
                                    </td>
                                    <td>
                                        <?php
                                        $cond = $item['current_condition'];
                                        $badge = match ($cond) {
                                            'ok' => '<span class="badge bg-success bg-opacity-10 text-success">NEUF / OK</span>',
                                            'defaut_leger' => '<span class="badge bg-warning bg-opacity-10 text-warning">DÉFAUT LÉGER</span>',
                                            'defaut_critique' => '<span class="badge bg-danger bg-opacity-10 text-danger">DÉFAUT CRITIQUE</span>',
                                            'repare' => '<span class="badge bg-primary bg-opacity-10 text-primary">RÉPARÉ</span>',
                                            default => $cond
                                        };
                                        echo $badge;
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status = $item['status'];
                                        $sBadge = match ($status) {
                                            'en_stock' => '<span class="badge bg-light bg-opacity-10 text-white">EN STOCK</span>',
                                            'en_reparation' => '<span class="badge bg-info bg-opacity-10 text-info">EN RÉPARATION</span>',
                                            'vendu' => '<span class="badge bg-secondary">VENDU</span>',
                                            'perte' => '<span class="badge bg-danger">PERTE</span>',
                                            default => $status
                                        };
                                        echo $sBadge;
                                        ?>
                                    </td>
                                    <td>
                                        <?= $item['is_sellable'] ? '<i class="fa-solid fa-check-circle text-success"></i> Oui' : '<i class="fa-solid fa-times-circle text-danger"></i> Non' ?>
                                    </td>
                                    <td class="small text-muted">
                                        <?= date('d/m/Y H:i', strtotime($item['created_at'])) ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($item['status'] == 'en_stock'): ?>
                                            <button class="btn btn-sm btn-outline-warning extra-small py-1"
                                                onclick="sendToRepair(<?= $item['id_item'] ?>, '<?= addslashes($item['serial_number']) ?>')">
                                                <i class="fa-solid fa-screwdriver-wrench me-1"></i> Réparation
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted extra-small italic">Aucune action</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="../assets/vendor/sweetalert2/sweetalert2.all.min.js"></script>
    <script>
        function sendToRepair(itemId, serial) {
            Swal.fire({
                title: 'Envoyer en Réparation',
                text: "L'article (S/N: " + serial + ") sera décrémenté du stock vendable et envoyé au Service Technique.",
                icon: 'warning',
                input: 'textarea',
                inputPlaceholder: 'Décrivez la panne ou le motif...',
                showCancelButton: true,
                confirmButtonColor: '#3b82f6',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Confirmer l\'envoi',
                cancelButtonText: 'Annuler',
                preConfirm: (description) => {
                    if (!description) {
                        Swal.showValidationMessage('Veuillez saisir un motif');
                    }
                    return description;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('../../backend/actions/repairs/process_internal_repair.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            id_item: itemId,
                            description: result.value
                        })
                    })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('Succès', data.message, 'success').then(() => location.reload());
                            } else {
                                Swal.fire('Erreur', data.message, 'error');
                            }
                        })
                        .catch(err => Swal.fire('Erreur', 'Une erreur est survenue', 'error'));
                }
            });
        }
    </script>
</body>

</html>