<?php
session_start();
require_once '../config/db.php';

if (!isset($_GET['id'])) {
    die("ID Vente manquant");
}

$sale_id = $_GET['id'];

// Fetch Sale Info
$query_sale = "SELECT s.*, u.username, c.fullname as client_name 
               FROM sales s 
               JOIN users u ON s.id_user = u.id_user 
               LEFT JOIN clients c ON s.id_client = c.id_client 
               WHERE s.id_sale = ?";
$stmt = $pdo->prepare($query_sale);
$stmt->execute([$sale_id]);
$sale = $stmt->fetch();

if (!$sale) {
    die("Vente introuvable");
}

// Fetch Details
$query_details = "SELECT d.*, p.name 
                  FROM sale_details d 
                  JOIN products p ON d.id_product = p.id_product 
                  WHERE d.id_sale = ?";
$stmt = $pdo->prepare($query_details);
$stmt->execute([$sale_id]);
$details = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Facture #
        <?= $sale['id_sale'] ?> | DENIS FBI STORE
    </title>
    <link href="../assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            color: #000;
            font-family: 'Arial', sans-serif;
        }

        .invoice-box {
            max-width: 800px;
            margin: auto;
            padding: 30px;
            border: 1px solid #eee;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.15);
            background: white;
            margin-top: 50px;
        }

        .header-title {
            font-weight: bold;
            font-size: 24px;
            color: #333;
        }

        .invoice-details {
            margin-top: 20px;
        }

        @media print {
            .no-print {
                display: none;
            }

            .invoice-box {
                box-shadow: none;
                border: none;
                margin-top: 0;
            }
        }
    </style>
</head>

<body>

    <div class="invoice-box">
        <div class="text-center mb-4">
            <h1 class="header-title">DENIS FBI STORE</h1>
            <p>Vente de Matériel Informatique & Accessoires</p>
            <hr>
        </div>

        <div class="row invoice-details mb-4">
            <div class="col-6">
                <h5 class="fw-bold">Client</h5>
                <p class="mb-0">
                    <?= $sale['client_name'] ? htmlspecialchars($sale['client_name']) : 'Client Comptoir' ?>
                </p>
            </div>
            <div class="col-6 text-end">
                <h5 class="fw-bold">Facture N° #
                    <?= str_pad($sale['id_sale'], 6, '0', STR_PAD_LEFT) ?>
                </h5>
                <p class="mb-0">Date :
                    <?= date('d/m/Y H:i', strtotime($sale['sale_date'])) ?>
                </p>
                <p class="mb-0">Vendeur :
                    <?= htmlspecialchars($sale['username']) ?>
                </p>
            </div>
        </div>

        <table class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>Désignation</th>
                    <th class="text-center">Qté</th>
                    <th class="text-end">Prix Unit.</th>
                    <th class="text-end">Sous-total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($details as $item): ?>
                    <tr>
                        <td>
                            <?= htmlspecialchars($item['name']) ?>
                        </td>
                        <td class="text-center">
                            <?= $item['quantity'] ?>
                        </td>
                        <td class="text-end">
                            <?= number_format($item['unit_price'], 0, ',', ' ') ?> FCFA
                        </td>
                        <td class="text-end">
                            <?= number_format($item['subtotal'], 0, ',', ' ') ?> FCFA
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="table-dark text-white">
                    <td colspan="3" class="text-end fw-bold">TOTAL NET A PAYER</td>
                    <td class="text-end fw-bold">
                        <?= number_format($sale['total_amount'], 0, ',', ' ') ?> FCFA
                    </td>
                </tr>
            </tfoot>
        </table>

        <div class="text-center mt-5">
            <p class="fw-bold">Merci pour votre confiance !</p>
            <p class="small text-muted">Les articles vendus ne sont ni repris ni échangés.</p>
        </div>

        <div class="text-center mt-4 no-print">
            <button class="btn btn-primary" onclick="window.print()">Imprimer</button>
            <a href="sales.php" class="btn btn-secondary">Retour à la caisse</a>
        </div>
    </div>

</body>

</html>
