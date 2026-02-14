<?php
define('PAGE_ACCESS', 'invoice');
require_once '../../backend/includes/auth_required.php';
require_once '../../backend/config/db.php';

if (!isset($_GET['id'])) {
    die("ID Vente manquant");
}

$sale_id = $_GET['id'];

// Fetch Sale Info
// Schema: ventes v, utilisateurs u, clients c
$query_sale = "SELECT v.*, u.username, c.nom_client as client_name, c.telephone, c.adresse
               FROM ventes v 
               JOIN utilisateurs u ON v.id_vendeur = u.id_user 
               LEFT JOIN clients c ON v.id_client = c.id_client 
               WHERE v.id_vente = ?";
$stmt = $pdo->prepare($query_sale);
$stmt->execute([$sale_id]);
$sale = $stmt->fetch();

if (!$sale) {
    die("Vente introuvable");
}

// Fetch Details
// Schema: vente_details d, produits p
$query_details = "SELECT d.*, p.designation as name 
                  FROM vente_details d 
                  JOIN produits p ON d.id_produit = p.id_produit 
                  WHERE d.id_vente = ?";
$stmt = $pdo->prepare($query_details);
$stmt->execute([$sale_id]);
$details = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Facture #<?= str_pad($sale['id_vente'], 6, '0', STR_PAD_LEFT) ?> | DENIS FBI STORE</title>
    <link href="../assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            color: #000;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .invoice-box {
            max-width: 800px;
            margin: auto;
            padding: 40px;
            border: 1px solid #eee;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
            background: white;
            margin-top: 50px;
        }

        .header-title {
            font-weight: 800;
            font-size: 28px;
            color: #1e293b;
            letter-spacing: -0.5px;
        }

        .invoice-details h5 {
            color: #64748b;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.5rem;
        }

        .table thead th {
            background: #f1f5f9;
            color: #475569;
            font-weight: 600;
            border-bottom: 2px solid #e2e8f0;
        }

        .total-row {
            background: #1e293b !important;
            color: white !important;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            .invoice-box {
                box-shadow: none;
                border: none;
                margin-top: 0;
                padding: 0;
            }

            body {
                background: white;
            }
        }
    </style>
</head>

<body>

    <div class="invoice-box">
        <div class="text-center mb-5">
            <h1 class="header-title">DENIS FBI STORE</h1>
            <p class="text-muted">Vente de Matériel Informatique & Accessoires</p>
            <div class="small text-muted">Douala, Cameroun | Tél: 699 99 99 99</div>
        </div>

        <div class="row invoice-details mb-5">
            <div class="col-6">
                <h5>Information Client</h5>
                <div class="fw-bold fs-5">
                    <?= $sale['client_name'] ? htmlspecialchars($sale['client_name']) : 'Client Comptoir' ?></div>
                <div class="text-muted small">
                    <?php if ($sale['telephone'] && $sale['telephone'] !== '000000000'): ?>
                        <div>Tél: <?= htmlspecialchars($sale['telephone']) ?></div>
                    <?php endif; ?>
                    <?php if ($sale['adresse'] && $sale['adresse'] !== 'Sur Place'): ?>
                        <div><?= htmlspecialchars($sale['adresse']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-6 text-end">
                <h5>Détails Facture</h5>
                <div class="fw-bold fs-5">#<?= str_pad($sale['id_vente'], 6, '0', STR_PAD_LEFT) ?></div>
                <div class="text-muted small">
                    <div>Date: <?= date('d/m/Y H:i', strtotime($sale['date_vente'])) ?></div>
                    <div>Vendeur: <?= htmlspecialchars($sale['username']) ?></div>
                    <div>Paiement: <span class="text-uppercase"><?= htmlspecialchars($sale['type_paiement']) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th class="py-3 ps-4">Désignation</th>
                    <th class="text-center py-3">Qté</th>
                    <th class="text-end py-3">Prix Unit.</th>
                    <th class="text-end py-3 pe-4">Sous-total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($details as $item): ?>
                    <tr>
                        <td class="ps-4 py-3">
                            <div class="fw-bold text-dark"><?= htmlspecialchars($item['name']) ?></div>
                        </td>
                        <td class="text-center py-3">
                            <?= $item['quantite'] ?>
                        </td>
                        <td class="text-end py-3">
                            <?= number_format($item['prix_unitaire'], 0, ',', ' ') ?> FCFA
                        </td>
                        <td class="text-end py-3 pe-4 fw-bold">
                            <?= number_format($item['sous_total'], 0, ',', ' ') ?> FCFA
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="3" class="text-end fw-bold py-3">TOTAL NET A PAYER</td>
                    <td class="text-end fw-bold py-3 pe-4 fs-5">
                        <?= number_format($sale['prix_revente_final'], 0, ',', ' ') ?> FCFA
                    </td>
                </tr>
            </tfoot>
        </table>

        <div class="text-center mt-5 pt-4 border-top">
            <p class="fw-bold mb-1">Merci pour votre confiance !</p>
            <p class="small text-muted">Les articles vendus ne sont ni repris ni échangés sauf cas de garantie.</p>
        </div>

        <div class="text-center mt-4 no-print d-flex gap-2 justify-content-center">
            <button class="btn btn-dark px-4 rounded-pill" onclick="window.print()">
                <i class="fa-solid fa-print me-2"></i>Imprimer
            </button>
            <a href="sales.php" class="btn btn-outline-secondary px-4 rounded-pill">
                <i class="fa-solid fa-arrow-left me-2"></i>Retour
            </a>
        </div>
    </div>

    <script src="../assets/vendor/fontawesome/js/all.min.js"></script>
</body>

</html>