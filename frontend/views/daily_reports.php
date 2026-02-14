<?php
define('PAGE_ACCESS', 'daily_reports');
require_once '../../backend/includes/auth_required.php';
require_once '../../backend/config/db.php';
require_once '../../backend/config/functions.php';

$pageTitle = "Rapports Journaliers";
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Fetch Reports from rapports_journaliers
$session_role = str_replace(' ', '_', strtolower($role ?? ''));
if ($session_role === 'super_admin') {
    $stmt = $pdo->query("SELECT dr.*, u.username, r.nom_role as user_role 
                         FROM rapports_journaliers dr 
                         JOIN utilisateurs u ON dr.id_user = u.id_user 
                         LEFT JOIN roles r ON u.id_role = r.id_role
                         ORDER BY dr.date_rapport DESC");
} else {
    $stmt = $pdo->prepare("SELECT dr.*, u.username, r.nom_role as user_role 
                           FROM rapports_journaliers dr 
                           JOIN utilisateurs u ON dr.id_user = u.id_user 
                           LEFT JOIN roles r ON u.id_role = r.id_role
                           WHERE dr.id_user = ? 
                           ORDER BY dr.date_rapport DESC");
    $stmt->execute([$user_id]);
}
$reports = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title><?= $pageTitle ?> | DENIS FBI STORE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/premium-ui.css">
    <style>
        .report-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            transition: all 0.3s ease;
        }

        .report-card:hover {
            background: rgba(255, 255, 255, 0.05);
            transform: translateY(-2px);
        }

        .unread {
            border-left: 4px solid #f5576c !important;
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
                    <div class="flex-grow-1" style="max-width: 500px;">
                        <div class="input-group">
                            <span class="input-group-text bg-dark border-secondary text-muted px-3"
                                style="border-radius: 12px 0 0 12px;"><i class="fa-solid fa-search"></i></span>
                            <input type="text" id="cardSearch"
                                class="form-control bg-dark text-white border-secondary py-2"
                                placeholder="Rechercher un rapport (date, auteur, tâches)..."
                                style="border-radius: 0 12px 12px 0;">
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-light px-4" onclick="exportReportsPDF()">
                            <i class="fa-solid fa-file-pdf me-2"></i>Export PDF Global
                        </button>
                        <button class="btn btn-premium px-4" data-bs-toggle="modal" data-bs-target="#addReportModal">
                            <i class="fa-solid fa-plus-circle me-2"></i>Soumettre mon Rapport
                        </button>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <?php if (empty($reports)): ?>
                            <div class="card bg-dark bg-opacity-50 border-0 rounded-4 p-5 text-center">
                                <i class="fa-solid fa-clipboard-list fa-3x text-muted mb-3 opacity-20"></i>
                                <p class="text-muted mb-0">Aucun rapport enregistré pour le moment.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($reports as $r): ?>
                                <div class="report-card report-card-item p-4 mb-3">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-primary bg-opacity-10 p-3 rounded-3 me-3">
                                                <i class="fa-solid fa-calendar-day text-primary"></i>
                                            </div>
                                            <div>
                                                <h6 class="text-white mb-0">Rapport du
                                                    <?= date('d/m/Y', strtotime($r['date_rapport'])) ?>
                                                    <?php
                                                    $status_badge = match ($r['statut_approbation']) {
                                                        'valide' => '<span class="badge bg-success bg-opacity-10 text-success ms-2"><i class="fa-solid fa-check-circle me-1"></i>Validé</span>',
                                                        'rejete' => '<span class="badge bg-danger bg-opacity-10 text-danger ms-2"><i class="fa-solid fa-times-circle me-1"></i>À refaire</span>',
                                                        default => '<span class="badge bg-warning bg-opacity-10 text-warning ms-2"><i class="fa-solid fa-clock me-1"></i>En attente</span>'
                                                    };
                                                    echo $status_badge;
                                                    ?>
                                                </h6>
                                                <span class="text-muted extra-small">
                                                    Soumis par <strong>
                                                        <?= htmlspecialchars($r['username']) ?>
                                                    </strong>
                                                    (<?= ucfirst($r['user_role'] ?? 'Utilisateur') ?>)
                                                </span>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <?php if ($role == 'super_admin'): ?>
                                                <?php if ($r['statut_approbation'] == 'en_attente'): ?>
                                                    <button onclick="approveReport(<?= $r['id_rapport'] ?>)"
                                                        class="btn btn-sm btn-success extra-small py-1 me-1">
                                                        <i class="fa-solid fa-check"></i> Valider
                                                    </button>
                                                    <button onclick="openRejectModal(<?= $r['id_rapport'] ?>)"
                                                        class="btn btn-sm btn-danger extra-small py-1">
                                                        <i class="fa-solid fa-ban"></i> Rejeter
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <button onclick="downloadPDF(<?= $r['id_rapport'] ?>)"
                                                class="btn btn-sm btn-outline-light extra-small py-1 ms-1">
                                                <i class="fa-solid fa-file-pdf"></i> PDF
                                            </button>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label class="text-muted extra-small fw-bold text-uppercase mb-2">Bilan d'Activité /
                                                Tâches</label>
                                            <div class="small text-white-50">
                                                <?= nl2br(htmlspecialchars($r['bilan_activite'])) ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="text-muted extra-small fw-bold text-uppercase mb-2">Problèmes /
                                                Bloquants</label>
                                            <div class="small text-white-50">
                                                <?= nl2br(htmlspecialchars($r['problemes_rencontres'] ?: 'Aucun')) ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php if ($r['statut_approbation'] == 'rejete' && $r['reponse_super_admin']): ?>
                                        <div
                                            class="mt-3 p-3 bg-danger bg-opacity-10 border border-danger border-opacity-20 rounded-3">
                                            <div class="extra-small text-danger fw-bold text-uppercase mb-1">Réponse Admin / Motif
                                                du rejet</div>
                                            <div class="small text-white-50 italic">
                                                <?= nl2br(htmlspecialchars($r['reponse_super_admin'])) ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Report Modal -->
    <div class="modal fade" id="addReportModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark text-white glass-panel border-secondary border-opacity-10">
                <form id="reportForm">
                    <div class="modal-header border-secondary border-opacity-20">
                        <h5 class="modal-title fw-bold">Soumettre mon Rapport</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">DATE DU RAPPORT</label>
                            <input type="date" name="report_date"
                                class="form-control bg-dark text-white border-secondary" value="<?= date('Y-m-d') ?>"
                                required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">BILAN / TÂCHES EFFECTUÉES</label>
                            <textarea name="tasks_completed" class="form-control bg-dark text-white border-secondary"
                                rows="4" placeholder="Qu'avez-vous fait aujourd'hui ?" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">PROBLÈMES RENCONTRÉS</label>
                            <textarea name="issues_found" class="form-control bg-dark text-white border-secondary"
                                rows="2" placeholder="Des obstacles ou pannes ?"></textarea>
                        </div>
                        <div class="mb-0">
                            <label class="form-label text-muted small fw-bold">PRÉVISIONS / OBJECTIFS DEMAIN
                                (Optionnel)</label>
                            <textarea name="planned_next" class="form-control bg-dark text-white border-secondary"
                                rows="2" placeholder="Quels sont les objectifs de demain ?"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-secondary border-opacity-10 p-4">
                        <button type="submit" class="btn btn-premium w-100 py-2 fw-bold">Envoyer le Rapport</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Report Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark text-white glass-panel border-secondary border-opacity-10">
                <form id="rejectForm">
                    <div class="modal-header border-secondary border-opacity-20">
                        <h5 class="modal-title fw-bold">Rejeter le Rapport</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <input type="hidden" name="id_report" id="reject_id_report">
                        <div class="mb-0">
                            <label class="form-label text-muted small fw-bold">MOTIF DU REJET / RÉPONSE ADMIN</label>
                            <textarea name="rejection_reason" class="form-control bg-dark text-white border-secondary"
                                rows="4" placeholder="Pourquoi ce rapport doit-il être refait ?" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-secondary border-opacity-10 p-4">
                        <button type="submit" class="btn btn-danger w-100 py-2 fw-bold">Confirmer le Rejet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
    <script src="../assets/vendor/sweetalert2/sweetalert2.all.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        document.getElementById('reportForm')?.addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'submit_report');

            fetch('../../backend/actions/process_daily_report.php', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Soumis!',
                            text: data.message,
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => window.location.reload());
                    } else {
                        Swal.fire('Erreur', data.message, 'error');
                    }
                });
        });

        function approveReport(id) {
            Swal.fire({
                title: 'Valider ce rapport ?',
                text: "Le rapport sera marqué comme validé.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Oui, valider',
                cancelButtonText: 'Annuler'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'approve_report');
                    formData.append('id_report', id);

                    fetch('../../backend/actions/process_daily_report.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('Validé!', data.message, 'success').then(() => window.location.reload());
                            } else {
                                Swal.fire('Erreur', data.message, 'error');
                            }
                        });
                }
            });
        }

        function openRejectModal(id) {
            document.getElementById('reject_id_report').value = id;
            new bootstrap.Modal(document.getElementById('rejectModal')).show();
        }

        document.getElementById('rejectForm')?.addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'reject_report');

            fetch('../../backend/actions/process_daily_report.php', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Rejeté',
                            text: data.message,
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => window.location.reload());
                    } else {
                        Swal.fire('Erreur', data.message, 'error');
                    }
                });
        });

        function downloadPDF(id) {
            const reportElement = document.querySelector(`div[class*="report-card"] button[onclick*="downloadPDF(${id})"]`).closest('.report-card');

            // Create a clone for PDF to remove buttons and adjust styling
            const clone = reportElement.cloneNode(true);
            clone.style.background = "white";
            clone.style.color = "black";
            clone.style.padding = "40px";
            clone.style.border = "none";

            // Remove all buttons from clone
            const buttons = clone.querySelectorAll('button');
            buttons.forEach(btn => btn.remove());

            // Fix text colors for PDF readability
            clone.querySelectorAll('.text-white, .text-white-50').forEach(el => {
                el.classList.remove('text-white', 'text-white-50');
                el.style.color = "black";
            });

            clone.querySelectorAll('.text-muted').forEach(el => {
                el.style.color = "#666";
            });

            const opt = {
                margin: 10,
                filename: `Rapport_Journalier_${id}.pdf`,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };

            // Header for PDF
            const header = `
                <div style="text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 15px;">
                    <h1 style="margin: 0; font-size: 24px;">DENIS FBI STORE</h1>
                    <p style="margin: 5px 0; color: #666;">Rapport Journalier d'Activité</p>
                </div>
            `;

            const container = document.createElement('div');
            container.innerHTML = header;
            container.appendChild(clone);

            html2pdf().set(opt).from(container).save();
        }

        function exportReportsPDF() {
            const container = document.createElement('div');
            container.style.padding = "20px";
            container.style.background = "white";
            container.style.color = "black";

            const header = `
                <div style="text-align: center; margin-bottom: 30px; border-bottom: 2px solid #000; padding-bottom: 15px;">
                    <h1 style="margin: 0; font-size: 24px;">DENIS FBI STORE</h1>
                    <h2 style="margin: 5px 0; font-size: 18px; color: #666;">Bilan des Rapports Journaliers</h2>
                    <p style="margin: 5px 0; font-size: 12px; color: #888;">Généré le : ${new Date().toLocaleDateString('fr-FR')} à ${new Date().toLocaleTimeString('fr-FR')}</p>
                </div>
            `;
            container.innerHTML = header;

            const visibleReports = Array.from(document.querySelectorAll('.report-card-item')).filter(r => r.style.display !== 'none');

            if (visibleReports.length === 0) {
                Swal.fire('Info', 'Aucun rapport visible à exporter.', 'info');
                return;
            }

            visibleReports.forEach((report, index) => {
                const clone = report.cloneNode(true);
                clone.style.background = "white";
                clone.style.color = "black";
                clone.style.padding = "20px";
                clone.style.border = "1px solid #eee";
                clone.style.marginBottom = "20px";
                clone.style.pageBreakInside = "avoid";

                // Remove buttons
                clone.querySelectorAll('button').forEach(btn => btn.remove());

                // Fix text colors
                clone.querySelectorAll('.text-white, .text-white-50').forEach(el => {
                    el.classList.remove('text-white', 'text-white-50');
                    el.style.color = "black";
                });

                container.appendChild(clone);
            });

            const opt = {
                margin: 10,
                filename: `Export_Rapports_${new Date().getTime()}.pdf`,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };

            html2pdf().set(opt).from(container).save();
        }

        document.getElementById('cardSearch').addEventListener('input', function () {
            const term = this.value.toLowerCase().trim();
            document.querySelectorAll('.report-card-item').forEach(item => {
                const text = item.innerText.toLowerCase();
                item.style.display = text.includes(term) ? '' : 'none';
            });
        });
    </script>
</body>

</html>