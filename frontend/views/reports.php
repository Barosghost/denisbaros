<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

require_once '../../backend/config/db.php';
require_once '../../backend/config/functions.php';
$pageTitle = "Rapports & Statistiques";

// Defaults: Current Month
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// 1. Sales Data for Table
$query = "SELECT s.*, u.username, c.fullname as client_name 
          FROM sales s 
          JOIN users u ON s.id_user = u.id_user 
          LEFT JOIN clients c ON s.id_client = c.id_client 
          WHERE DATE(s.sale_date) BETWEEN ? AND ? 
          ORDER BY s.sale_date DESC";
$stmt = $pdo->prepare($query);
$stmt->execute([$start_date, $end_date]);
$sales = $stmt->fetchAll();

// 2. Metrics (Revenue & Margin)
$stmt_rev = $pdo->prepare("SELECT SUM(total_amount) FROM sales WHERE DATE(sale_date) BETWEEN ? AND ?");
$stmt_rev->execute([$start_date, $end_date]);
$total_revenue = $stmt_rev->fetchColumn() ?: 0;

$stmt_cost = $pdo->prepare("
    SELECT SUM(sd.quantity * p.purchase_price) 
    FROM sale_details sd 
    JOIN products p ON sd.id_product = p.id_product 
    JOIN sales s ON sd.id_sale = s.id_sale
    WHERE DATE(s.sale_date) BETWEEN ? AND ?
");
$stmt_cost->execute([$start_date, $end_date]);
$total_cost = $stmt_cost->fetchColumn() ?: 0;

$margin = $total_revenue - $total_cost;

// 3. Chart Data (Sales & Profit by Day)
$stmt = $pdo->prepare("
    SELECT 
        DATE(s.sale_date) as d, 
        SUM(s.total_amount) as t,
        SUM(s.total_amount) - SUM(sd.quantity * p.purchase_price) as p
    FROM sales s 
    JOIN sale_details sd ON s.id_sale = sd.id_sale
    JOIN products p ON sd.id_product = p.id_product
    WHERE DATE(s.sale_date) BETWEEN ? AND ? 
    GROUP BY DATE(s.sale_date) 
    ORDER BY d ASC
");
$stmt->execute([$start_date, $end_date]);
$chart_data = $stmt->fetchAll();

// 4. Activity Logs
$logs = $pdo->query("SELECT l.*, u.username FROM action_logs l JOIN users u ON l.id_user = u.id_user ORDER BY l.created_at DESC LIMIT 50")->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapports | DENIS FBI STORE</title>
    <link href="../assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=1.5">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @media print {
            @page {
                margin: 0;
            }

            body {
                padding: 15mm;
                background: white !important;
                color: black !important;
            }

            .no-print {
                display: none !important;
            }

            .print-only {
                display: block !important;
            }

            .wrapper {
                display: block;
            }

            #content {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
            }

            .card {
                background: white !important;
                color: black !important;
                border: 1px solid #ddd !important;
                box-shadow: none !important;
            }

            canvas {
                max-width: 100% !important;
            }
        }

        .print-only {
            display: none;
        }

        .chart-container-glow {
            position: relative;
            filter: drop-shadow(0 0 10px rgba(99, 102, 241, 0.2));
        }

        .chart-card {
            background: rgba(15, 23, 42, 0.4) !important;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.05) !important;
        }

        .report-stat-card {
            border-radius: 20px;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.05);
            background: rgba(15, 23, 42, 0.4);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .report-stat-card:hover {
            transform: translateY(-5px);
            background: rgba(15, 23, 42, 0.6);
            border-color: rgba(255, 255, 255, 0.1);
        }

        .report-stat-card .icon-box {
            width: 54px;
            height: 54px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .bg-revenue {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.2) 0%, rgba(99, 102, 241, 0.05) 100%);
            color: #818cf8;
        }

        .bg-margin {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2) 0%, rgba(16, 185, 129, 0.05) 100%);
            color: #34d399;
        }

        .filter-glass {
            background: rgba(15, 23, 42, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 1.25rem;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <div class="no-print">
            <?php include '../../backend/includes/sidebar.php'; ?>
        </div>
        <div id="content">
            <div class="no-print">
                <?php include '../../backend/includes/header.php'; ?>
            </div>

            <!-- Print Header -->
            <div class="print-only text-center mb-4">
                <h1 class="fw-bold" style="color: #000;">DENIS FBI STORE</h1>
                <p class="mb-0">Rapport d'Activité Commerciale</p>
                <p class="small text-muted">Généré le <?= date('d/m/Y H:i') ?></p>
                <hr>
            </div>

            <div class="fade-in mt-4">
                <!-- Filter Bar -->
                <div class="filter-glass mb-4 no-print">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label text-muted small fw-bold">DATE DÉBUT</label>
                            <input type="date" name="start_date"
                                class="form-control bg-dark text-white border-secondary" value="<?= $start_date ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small fw-bold">DATE FIN</label>
                            <input type="date" name="end_date" class="form-control bg-dark text-white border-secondary"
                                value="<?= $end_date ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-premium w-100 py-2">
                                <i class="fa-solid fa-sync-alt me-2"></i>Actualiser
                            </button>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="btn-group">
                                <button type="button" class="btn btn-outline-light px-3" onclick="window.print()">
                                    <i class="fa-solid fa-file-pdf me-2"></i>PDF
                                </button>
                                <button type="button" class="btn btn-outline-success px-3" onclick="exportExcel()">
                                    <i class="fa-solid fa-file-excel me-2"></i>Excel
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Summary Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="report-stat-card">
                            <div class="icon-box bg-revenue">
                                <i class="fa-solid fa-sack-dollar"></i>
                            </div>
                            <div class="text-muted small fw-bold text-uppercase mb-1">Chiffre d'Affaires</div>
                            <h2 class="text-white fw-bold mb-1"><?= number_format($total_revenue, 0, ',', ' ') ?> <span
                                    class="fs-6 fw-normal opacity-50">FCFA</span></h2>
                            <div class="text-info extra-small mt-2">
                                <i class="fa-solid fa-calendar-day me-1"></i>Période sélectionnée
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="report-stat-card">
                            <div class="icon-box bg-margin">
                                <i class="fa-solid fa-chart-line"></i>
                            </div>
                            <div class="text-muted small fw-bold text-uppercase mb-1">Marge Bénéficiaire</div>
                            <h2 class="text-white fw-bold mb-1"><?= number_format($margin, 0, ',', ' ') ?> <span
                                    class="fs-6 fw-normal opacity-50">FCFA</span></h2>
                            <div class="text-success extra-small mt-2">
                                <i class="fa-solid fa-check-circle me-1"></i>Calculé sur prix d'achat
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Chart & Logs -->
                <div class="row g-4 mb-4">
                    <div class="col-lg-12">
                        <div class="card chart-card border-0 glass-panel h-100">
                            <div
                                class="card-header border-bottom border-secondary border-opacity-20 text-white d-flex justify-content-between align-items-center py-3 px-4">
                                <span class="fw-bold">Performance des Ventes & Bénéfices</span>
                                <span class="badge bg-primary bg-opacity-10 text-primary small fw-normal">Temps
                                    Réel</span>
                            </div>
                            <div class="card-body p-4">
                                <div class="chart-container-glow" style="height: 350px;">
                                    <canvas id="salesChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-lg-8">
                        <div class="card chart-card border-0 glass-panel h-100">
                            <div
                                class="card-header border-bottom border-secondary border-opacity-20 text-white py-3 px-4 fw-bold">
                                Volume des Transactions</div>
                            <div class="card-body p-4">
                                <div style="height: 250px;">
                                    <canvas id="volumeChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 no-print">
                        <div class="card bg-dark border-0 glass-panel h-100 shadow-lg">
                            <div
                                class="card-header border-bottom border-secondary border-opacity-20 text-white py-3 px-4 fw-bold">
                                Fils d'Activité</div>
                            <div class="card-body p-0 overflow-auto" style="max-height: 250px;">
                                <div class="list-group list-group-flush bg-transparent">
                                    <?php foreach ($logs as $l): ?>
                                        <div
                                            class="list-group-item bg-transparent text-white border-secondary border-opacity-20 py-3 px-4">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span
                                                    class="small fw-bold text-premium"><?= htmlspecialchars($l['username']) ?></span>
                                                <span
                                                    class="extra-small text-muted"><?= date('H:i', strtotime($l['created_at'])) ?></span>
                                            </div>
                                            <div class="small fw-medium mb-1"><?= htmlspecialchars($l['action']) ?></div>
                                            <div class="text-muted extra-small">
                                                <?= htmlspecialchars($l['details']) ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Table -->
                <div class="card bg-dark border-0 glass-panel shadow-lg">
                    <div
                        class="card-header border-bottom border-secondary border-opacity-20 text-white py-3 px-4 d-flex justify-content-between align-items-center">
                        <span class="fw-bold">Journal des Ventes</span>
                        <span class="text-muted small"><?= count($sales) ?> transactions trouvées</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-dark table-hover mb-0 align-middle" id="salesTable">
                                <thead class="border-bottom border-secondary border-opacity-20">
                                    <tr class="text-muted small">
                                        <th class="py-3 px-4">DATE & HEURE</th>
                                        <th class="py-3">CLIENT</th>
                                        <th class="py-3 text-center">VENDEUR</th>
                                        <th class="py-3 text-end">MONTANT TOTAL</th>
                                        <th class="py-3 text-end px-4 no-print">ACTIONS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sales as $s): ?>
                                        <tr>
                                            <td class="px-4">
                                                <div class="text-white fw-medium small">
                                                    <?= date('d/m/Y', strtotime($s['sale_date'])) ?></div>
                                                <div class="text-muted extra-small">
                                                    <?= date('H:i', strtotime($s['sale_date'])) ?></div>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-white small">
                                                    <?= htmlspecialchars($s['client_name'] ?? 'Vente au Comptoir') ?></div>
                                            </td>
                                            <td class="text-center">
                                                <span
                                                    class="badge bg-secondary bg-opacity-10 text-muted extra-small px-2 fw-normal">@<?= htmlspecialchars($s['username']) ?></span>
                                            </td>
                                            <td class="text-end fw-bold text-success px-3">
                                                <?= number_format($s['total_amount'], 0, ',', ' ') ?> <span
                                                    class="extra-small opacity-50">FCFA</span>
                                            </td>
                                            <td class="text-end px-4 no-print">
                                                <a href="invoice.php?id=<?= $s['id_sale'] ?>" target="_blank"
                                                    class="btn btn-sm btn-outline-light border-0">
                                                    <i class="fa-solid fa-print"></i>
                                                </a>
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

    <script src="../assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
    <script>
        // Global Chart Defaults
        Chart.defaults.color = '#94a3b8';
        Chart.defaults.font.family = "'Inter', sans-serif";

        const ctx = document.getElementById('salesChart').getContext('2d');

        // Advanced Gradients
        const salesGradient = ctx.createLinearGradient(0, 0, 0, 400);
        salesGradient.addColorStop(0, 'rgba(99, 102, 241, 0.4)');
        salesGradient.addColorStop(0.5, 'rgba(99, 102, 241, 0.1)');
        salesGradient.addColorStop(1, 'rgba(99, 102, 241, 0)');

        const profitGradient = ctx.createLinearGradient(0, 0, 0, 400);
        profitGradient.addColorStop(0, 'rgba(16, 185, 129, 0.3)');
        profitGradient.addColorStop(0.5, 'rgba(16, 185, 129, 0.05)');
        profitGradient.addColorStop(1, 'rgba(16, 185, 129, 0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_column($chart_data, 'd')) ?>,
                datasets: [
                    {
                        label: 'Ventes',
                        data: <?= json_encode(array_column($chart_data, 't')) ?>,
                        borderColor: '#6366f1',
                        backgroundColor: salesGradient,
                        fill: true,
                        tension: 0.45,
                        borderWidth: 4,
                        pointBackgroundColor: '#6366f1',
                        pointBorderColor: 'rgba(255,255,255,0.8)',
                        pointBorderWidth: 2,
                        pointHoverRadius: 8,
                        pointRadius: 5,
                        shadowColor: 'rgba(99, 102, 241, 0.5)',
                        shadowBlur: 10
                    },
                    {
                        label: 'Bénéfice',
                        data: <?= json_encode(array_column($chart_data, 'p')) ?>,
                        borderColor: '#10b981',
                        backgroundColor: profitGradient,
                        fill: true,
                        tension: 0.45,
                        borderWidth: 3,
                        pointBackgroundColor: '#10b981',
                        pointBorderColor: 'rgba(255,255,255,0.8)',
                        pointBorderWidth: 2,
                        pointHoverRadius: 7,
                        pointRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: {
                    padding: { top: 10, bottom: 10, left: 10, right: 10 }
                },
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        border: { display: false },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.03)',
                            drawTicks: false
                        },
                        ticks: {
                            padding: 10,
                            callback: function (value) {
                                if (value >= 1000) return (value / 1000) + 'k';
                                return value;
                            }
                        }
                    },
                    x: {
                        border: { display: false },
                        grid: {
                            display: false
                        },
                        ticks: {
                            padding: 10
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        align: 'end',
                        labels: {
                            color: '#fff',
                            usePointStyle: true,
                            boxWidth: 8,
                            padding: 20,
                            font: { weight: 'bold' }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.95)',
                        titleColor: '#fff',
                        bodyColor: '#e2e8f0',
                        borderColor: 'rgba(99, 102, 241, 0.3)',
                        borderWidth: 1,
                        padding: 12,
                        displayColors: true,
                        bodySpacing: 8,
                        boxPadding: 6,
                        callbacks: {
                            label: function (context) {
                                let label = context.dataset.label || '';
                                if (label) label += ': ';
                                if (context.parsed.y !== null) {
                                    label += new Intl.NumberFormat('fr-FR').format(context.parsed.y) + ' FCFA';
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });

        // Volume Chart (Bar) - Matching reference
        const ctx2 = document.getElementById('volumeChart').getContext('2d');
        new Chart(ctx2, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($chart_data, 'd')) ?>,
                datasets: [{
                    label: 'Nb Transactions',
                    data: <?= json_encode(array_map(function ($d) use ($sales) {
                        return count(array_filter($sales, function ($s) use ($d) {
                            return date('Y-m-d', strtotime($s['sale_date'])) == $d['d'];
                        }));
                    }, $chart_data)) ?>,
                    backgroundColor: 'rgba(99, 102, 241, 0.6)',
                    hoverBackgroundColor: '#6366f1',
                    borderRadius: 6,
                    barThickness: 20
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                scales: {
                    y: { grid: { color: 'rgba(255,255,255,0.03)' }, border: { display: false } },
                    x: { grid: { display: false }, border: { display: false } }
                },
                plugins: { legend: { display: false } }
            }
        });

        function exportExcel() {
            let table = document.getElementById("salesTable");
            let csv = [];
            for (let i = 0; i < table.rows.length; i++) {
                let row = [], cols = table.rows[i].cells;
                for (let j = 0; j < cols.length - 1; j++) row.push(cols[j].innerText.replace(' FCFA', '').replace(/ /g, ''));
                csv.push(row.join(";"));
            }
            let blob = new Blob([csv.join("\n")], { type: 'text/csv;charset=utf-8;' });
            let link = document.createElement("a");
            link.href = URL.createObjectURL(blob);
            link.download = "rapport_ventes.csv";
            link.click();
        }
    </script>
</body>

</html>
