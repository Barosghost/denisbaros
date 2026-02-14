<?php
define('PAGE_ACCESS', 'system_settings');
require_once __DIR__ . '/../../backend/includes/auth_required.php';
require_once __DIR__ . '/../../backend/config/db.php';
require_once __DIR__ . '/../../backend/config/functions.php';

$session_role = str_replace(' ', '_', strtolower($_SESSION['role'] ?? ''));
if (!isset($_SESSION['logged_in']) || $session_role !== 'super_admin') {
    header("Location: dashboard.php");
    exit();
}

$pageTitle = "Paramètres Système";

$db_settings = [];
try {
    $stmt = $pdo->query("SELECT `key`, `value` FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $db_settings[$row['key']] = $row['value'];
    }
} catch (PDOException $e) {
    $db_settings = [];
}

function getSetting($key, $default = '') {
    global $db_settings;
    return $db_settings[$key] ?? $default;
}

$roles = $pdo->query("SELECT id_role, nom_role FROM roles ORDER BY id_role ASC")->fetchAll(PDO::FETCH_ASSOC);
$maintenance_file = __DIR__ . '/../../maintenance.lock';
$is_maintenance = file_exists($maintenance_file);
$process_settings_url = rtrim(dirname(dirname(dirname(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/'))), '/') . '/backend/actions/process_settings.php';
if (strpos($process_settings_url, 'backend') === false) $process_settings_url = '../../backend/actions/process_settings.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_maintenance') {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        if ($is_maintenance) {
            @unlink($maintenance_file);
            logActivity($pdo, "Système", "Désactivation du mode maintenance");
        } else {
            file_put_contents($maintenance_file, 'Maintenance par ' . ($_SESSION['username'] ?? 'admin'));
            logActivity($pdo, "Système", "Activation du mode maintenance");
        }
        header("Location: system_settings.php?success=1");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | DENIS FBI STORE</title>
    <link href="../assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=2.0">
    <style>
        .param-card { background: rgba(15, 23, 42, 0.5); border: 1px solid rgba(255,255,255,0.08); border-radius: 16px; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.4); }
        .param-nav { display: flex; flex-wrap: wrap; gap: 0.25rem; padding: 1rem; border-bottom: 1px solid rgba(255,255,255,0.06); background: rgba(0,0,0,0.2); }
        .param-nav .nav-link { padding: 0.6rem 1rem; color: #94a3b8; border-radius: 10px; font-weight: 600; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; transition: all 0.2s; border: 1px solid transparent; }
        .param-nav .nav-link:hover { background: rgba(255,255,255,0.06); color: #f1f5f9; }
        .param-nav .nav-link.active { background: linear-gradient(135deg, rgba(99,102,241,0.25), rgba(79,70,229,0.2)); color: #fff; border-color: rgba(99,102,241,0.4); }
        .param-nav .nav-link i { width: 20px; text-align: center; opacity: 0.9; }
        .param-body { padding: 2rem; }
        .param-section { margin-bottom: 2.5rem; }
        .param-section:last-child { margin-bottom: 0; }
        .param-section h6 { color: #e2e8f0; font-weight: 700; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid rgba(255,255,255,0.06); display: flex; align-items: center; gap: 0.5rem; }
        .param-section h6 i { color: #6366f1; opacity: 0.9; }
        .param-hint { font-size: 0.75rem; color: #64748b; margin-top: 0.35rem; }
        .form-label-settings { color: #94a3b8; font-weight: 600; font-size: 0.8rem; margin-bottom: 0.4rem; }
        .form-control-settings, .form-select-settings { background: rgba(15,23,42,0.6); border: 1px solid rgba(255,255,255,0.1); color: #fff; border-radius: 10px; padding: 0.65rem 1rem; }
        .form-control-settings:focus, .form-select-settings:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.15); background: rgba(15,23,42,0.8); color: #fff; }
        .switch-row { display: flex; align-items: center; justify-content: space-between; padding: 1rem; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-radius: 12px; margin-bottom: 0.5rem; }
        .switch-row .label { font-weight: 600; color: #e2e8f0; font-size: 0.9rem; }
        .switch-row .hint { font-size: 0.75rem; color: #64748b; margin-top: 0.2rem; }
        .btn-save-section { background: linear-gradient(135deg, #6366f1, #4f46e5); border: none; color: #fff; padding: 0.75rem 2rem; border-radius: 12px; font-weight: 700; transition: transform 0.2s, box-shadow 0.2s; }
        .btn-save-section:hover { color: #fff; transform: translateY(-1px); box-shadow: 0 10px 20px -5px rgba(99,102,241,0.4); }
        .logo-upload { width: 120px; height: 120px; border: 2px dashed rgba(255,255,255,0.15); border-radius: 16px; display: flex; align-items: center; justify-content: center; cursor: pointer; overflow: hidden; background-size: contain; background-position: center; background-repeat: no-repeat; transition: border-color 0.2s; }
        .logo-upload:hover { border-color: #6366f1; }
        .maintenance-box { background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.2); border-radius: 12px; padding: 1rem; margin-top: 1rem; }
        .roles-table-simple { font-size: 0.9rem; }
        .roles-table-simple th { color: #94a3b8; font-weight: 600; font-size: 0.75rem; text-transform: uppercase; }
        @media (max-width: 768px) { .param-body { padding: 1.25rem; } .param-nav { padding: 0.75rem; } .param-nav .nav-link { font-size: 0.75rem; padding: 0.5rem 0.75rem; } }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include __DIR__ . '/../../backend/includes/sidebar.php'; ?>
    <div id="content">
        <?php include __DIR__ . '/../../backend/includes/header.php'; ?>

        <div class="fade-in mt-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div>
                    <h4 class="text-white mb-1"><i class="fa-solid fa-sliders me-2 text-primary"></i>Paramètres Système</h4>
                    <p class="text-muted small mb-0">Configuration globale : général, sécurité, stock, ventes, SAV, rapports et audit.</p>
                </div>
                <?php if (!empty($_GET['success'])): ?>
                    <div class="alert alert-success py-2 px-3 mb-0">Paramètres enregistrés.</div>
                <?php endif; ?>
            </div>

            <div class="param-card">
                <nav class="param-nav" id="paramNav">
                    <a class="nav-link active" data-bs-toggle="tab" href="#tab-general"><i class="fa-solid fa-building"></i> Général</a>
                    <a class="nav-link" data-bs-toggle="tab" href="#tab-security"><i class="fa-solid fa-shield-halved"></i> Sécurité</a>
                    <a class="nav-link" data-bs-toggle="tab" href="#tab-roles"><i class="fa-solid fa-users-gear"></i> Rôles</a>
                    <a class="nav-link" data-bs-toggle="tab" href="#tab-stock"><i class="fa-solid fa-boxes-stacked"></i> Stock</a>
                    <a class="nav-link" data-bs-toggle="tab" href="#tab-pos"><i class="fa-solid fa-cash-register"></i> Ventes & POS</a>
                    <a class="nav-link" data-bs-toggle="tab" href="#tab-promo"><i class="fa-solid fa-tags"></i> Promos & Packs</a>
                    <a class="nav-link" data-bs-toggle="tab" href="#tab-sav"><i class="fa-solid fa-screwdriver-wrench"></i> SAV</a>
                    <a class="nav-link" data-bs-toggle="tab" href="#tab-reports"><i class="fa-solid fa-clipboard-list"></i> Rapports</a>
                    <a class="nav-link" data-bs-toggle="tab" href="#tab-audit"><i class="fa-solid fa-history"></i> Logs & Audit</a>
                </nav>

                <div class="tab-content param-body">
                    <!-- 1. GÉNÉRAL -->
                    <div class="tab-pane fade show active" id="tab-general">
                        <form class="param-form" data-category="general">
                            <?= getCsrfInput() ?>
                            <div class="row g-4">
                                <div class="col-lg-8">
                                    <div class="param-section">
                                        <h6><i class="fa-solid fa-building"></i> Identité du magasin</h6>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label-settings">Nom du magasin</label>
                                                <input type="text" name="company_name" class="form-control form-control-settings w-100" value="<?= htmlspecialchars(getSetting('company_name', 'DENIS FBI STORE')) ?>">
                                                <div class="param-hint">Factures, rapports PDF, en-têtes.</div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label-settings">Devise</label>
                                                <select name="currency" class="form-select form-select-settings w-100">
                                                    <option value="FCFA" <?= getSetting('currency') === 'FCFA' ? 'selected' : '' ?>>FCFA</option>
                                                    <option value="EUR" <?= getSetting('currency') === 'EUR' ? 'selected' : '' ?>>€ (EUR)</option>
                                                    <option value="USD" <?= getSetting('currency') === 'USD' ? 'selected' : '' ?>>$ (USD)</option>
                                                </select>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label-settings">Adresse</label>
                                                <input type="text" name="company_address" class="form-control form-control-settings" value="<?= htmlspecialchars(getSetting('company_address')) ?>" placeholder="Adresse complète">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label-settings">Téléphone</label>
                                                <input type="text" name="company_phone" class="form-control form-control-settings" value="<?= htmlspecialchars(getSetting('company_phone')) ?>" placeholder="+237 ...">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label-settings">Email</label>
                                                <input type="email" name="company_email" class="form-control form-control-settings" value="<?= htmlspecialchars(getSetting('company_email')) ?>" placeholder="contact@magasin.com">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="param-section">
                                        <h6><i class="fa-solid fa-globe"></i> Régionalisation</h6>
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label class="form-label-settings">Fuseau horaire</label>
                                                <select name="timezone" class="form-select form-select-settings w-100">
                                                    <option value="Africa/Douala" <?= getSetting('timezone', 'Africa/Douala') === 'Africa/Douala' ? 'selected' : '' ?>>Africa/Douala (GMT+1)</option>
                                                    <option value="Europe/Paris" <?= getSetting('timezone') === 'Europe/Paris' ? 'selected' : '' ?>>Europe/Paris</option>
                                                    <option value="UTC" <?= getSetting('timezone') === 'UTC' ? 'selected' : '' ?>>UTC</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label-settings">Format date</label>
                                                <select name="date_format" class="form-select form-select-settings w-100">
                                                    <option value="d/m/Y" <?= getSetting('date_format', 'd/m/Y') === 'd/m/Y' ? 'selected' : '' ?>>31/12/2026</option>
                                                    <option value="Y-m-d" <?= getSetting('date_format') === 'Y-m-d' ? 'selected' : '' ?>>2026-12-31</option>
                                                    <option value="d M Y" <?= getSetting('date_format') === 'd M Y' ? 'selected' : '' ?>>31 Déc 2026</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label-settings">Langue</label>
                                                <select name="default_language" class="form-select form-select-settings w-100">
                                                    <option value="fr" <?= getSetting('default_language', 'fr') === 'fr' ? 'selected' : '' ?>>Français</option>
                                                    <option value="en" <?= getSetting('default_language') === 'en' ? 'selected' : '' ?>>Anglais</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label-settings">Préfixe factures</label>
                                                <input type="text" name="invoice_prefix" class="form-control form-control-settings" value="<?= htmlspecialchars(getSetting('invoice_prefix', 'FAC-')) ?>" placeholder="FAC-">
                                                <div class="param-hint">Numérotation auto : FAC-2026-00001</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="param-section">
                                        <h6><i class="fa-solid fa-image"></i> Logo</h6>
                                        <div class="logo-upload" id="logoPreview" style="background-image: url('../<?= htmlspecialchars(getSetting('company_logo')) ?>')">
                                            <?php if (!getSetting('company_logo')): ?><i class="fa-solid fa-camera fa-2x text-muted"></i><?php endif; ?>
                                        </div>
                                        <input type="file" id="logoInput" hidden accept="image/*">
                                        <p class="param-hint mt-2">PNG/JPG, max 500 Ko. Cliquez pour changer.</p>
                                    </div>
                                    <div class="maintenance-box">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <span class="fw-bold text-danger small">Mode maintenance</span>
                                                <p class="param-hint mb-0">Seul le Super Admin peut se connecter.</p>
                                            </div>
                                            <div class="form-check form-switch mb-0">
                                                <input class="form-check-input" type="checkbox" id="maintenanceToggle" <?= $is_maintenance ? 'checked' : '' ?>>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4 pt-3 border-top border-white border-opacity-10">
                                <button type="submit" class="btn btn-save-section"><i class="fa-solid fa-floppy-disk me-2"></i>Enregistrer</button>
                            </div>
                        </form>
                    </div>

                    <!-- 2. SÉCURITÉ -->
                    <div class="tab-pane fade" id="tab-security">
                        <form class="param-form" data-category="security">
                            <?= getCsrfInput() ?>
                            <div class="param-section">
                                <h6><i class="fa-solid fa-shield-halved"></i> Comportement sécurité</h6>
                                <div class="switch-row">
                                    <div><div class="label">Logs des actions critiques</div><div class="hint">Enregistrer tentatives de suppression, échecs de connexion.</div></div>
                                    <input type="hidden" name="enable_critical_logs" value="0">
                                    <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="enable_critical_logs" value="1" <?= getSetting('enable_critical_logs', '1') === '1' ? 'checked' : '' ?>></div>
                                </div>
                                <div class="switch-row">
                                    <div><div class="label">Suppression logique uniquement (soft delete)</div><div class="hint">Interdire la suppression définitive ; marquer comme archivé.</div></div>
                                    <input type="hidden" name="soft_delete_only" value="0">
                                    <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="soft_delete_only" value="1" <?= getSetting('soft_delete_only') === '1' ? 'checked' : '' ?>></div>
                                </div>
                            </div>
                            <div class="mt-4 pt-3 border-top border-white border-opacity-10">
                                <button type="submit" class="btn btn-save-section"><i class="fa-solid fa-floppy-disk me-2"></i>Enregistrer</button>
                            </div>
                        </form>
                    </div>

                    <!-- 3. RÔLES -->
                    <div class="tab-pane fade" id="tab-roles">
                        <div class="param-section">
                            <h6><i class="fa-solid fa-users-gear"></i> Rôles système</h6>
                            <p class="text-muted small mb-3">Les rôles définissent les menus et actions accessibles. La gestion des utilisateurs et de leurs rôles se fait dans <a href="users.php" class="text-primary">Gestion des utilisateurs</a>.</p>
                            <div class="table-responsive">
                                <table class="table table-dark table-hover roles-table-simple">
                                    <thead><tr><th>Rôle</th><th>Description</th></tr></thead>
                                    <tbody>
                                        <?php
                                        $role_desc = ['Super Admin' => 'Pouvoir total', 'Admin' => 'Gestion opérationnelle', 'Chef Technique' => 'SAV et techniciens', 'Vendeur' => 'Ventes et clients', 'Technicien' => 'Dossiers SAV assignés'];
                                        foreach ($roles as $r):
                                            $nom = $r['nom_role'];
                                            ?><tr><td class="fw-bold"><?= htmlspecialchars($nom) ?></td><td class="text-muted small"><?= $role_desc[$nom] ?? '—' ?></td></tr><?php
                                        endforeach;
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- 4. STOCK -->
                    <div class="tab-pane fade" id="tab-stock">
                        <form class="param-form" data-category="stock">
                            <?= getCsrfInput() ?>
                            <div class="param-section">
                                <h6><i class="fa-solid fa-boxes-stacked"></i> Produits & stock</h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label-settings">Seuil d’alerte stock (par défaut)</label>
                                        <input type="number" name="stock_critical_threshold" class="form-control form-control-settings" value="<?= (int)getSetting('stock_critical_threshold', 5) ?>" min="0">
                                        <div class="param-hint">Alerte quand quantité &lt; seuil.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label-settings">Autoriser ajustement manuel</label>
                                        <select name="allow_manual_adjustment" class="form-select form-select-settings">
                                            <option value="1" <?= getSetting('allow_manual_adjustment', '1') === '1' ? 'selected' : '' ?>>Oui</option>
                                            <option value="0" <?= getSetting('allow_manual_adjustment') === '0' ? 'selected' : '' ?>>Non</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <div class="switch-row">
                                            <div><div class="label">Motif obligatoire pour correction</div><div class="hint">Exiger un motif sur chaque mouvement manuel.</div></div>
                                            <input type="hidden" name="mandatory_adjustment_reason" value="0">
                                            <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="mandatory_adjustment_reason" value="1" <?= getSetting('mandatory_adjustment_reason', '1') === '1' ? 'checked' : '' ?>></div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="switch-row">
                                            <div><div class="label">Bloquer stock négatif</div><div class="hint">Empêcher ventes ou sorties si stock insuffisant.</div></div>
                                            <input type="hidden" name="block_negative_stock" value="0">
                                            <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="block_negative_stock" value="1" <?= getSetting('block_negative_stock', '1') === '1' ? 'checked' : '' ?>></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4 pt-3 border-top border-white border-opacity-10">
                                <button type="submit" class="btn btn-save-section"><i class="fa-solid fa-floppy-disk me-2"></i>Enregistrer</button>
                            </div>
                        </form>
                    </div>

                    <!-- 5. VENTES & POS -->
                    <div class="tab-pane fade" id="tab-pos">
                        <form class="param-form" data-category="pos">
                            <?= getCsrfInput() ?>
                            <div class="param-section">
                                <h6><i class="fa-solid fa-cash-register"></i> Caisse (POS)</h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label-settings">Modes de paiement autorisés</label>
                                        <input type="text" name="payment_methods" class="form-control form-control-settings" value="<?= htmlspecialchars(getSetting('payment_methods', 'cash,mobile_money,virement')) ?>" placeholder="cash, mobile_money, virement">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label-settings">Autoriser remise manuelle</label>
                                        <select name="allow_manual_discount" class="form-select form-select-settings">
                                            <option value="1" <?= getSetting('allow_manual_discount', '1') === '1' ? 'selected' : '' ?>>Oui</option>
                                            <option value="0" <?= getSetting('allow_manual_discount') === '0' ? 'selected' : '' ?>>Non</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <div class="switch-row">
                                            <div><div class="label">Vente sans client (passage)</div><div class="hint">Autoriser vente sans associer un client.</div></div>
                                            <input type="hidden" name="sale_without_client" value="0">
                                            <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="sale_without_client" value="1" <?= getSetting('sale_without_client', '1') === '1' ? 'checked' : '' ?>></div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="switch-row">
                                            <div><div class="label">Vente via revendeur</div><div class="hint">Activer mode revendeur et commissions.</div></div>
                                            <input type="hidden" name="allow_reseller_sale" value="0">
                                            <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="allow_reseller_sale" value="1" <?= getSetting('allow_reseller_sale', '1') === '1' ? 'checked' : '' ?>></div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="switch-row">
                                            <div><div class="label">Commission automatique</div><div class="hint">Calcul auto selon taux revendeur ; sinon manuelle.</div></div>
                                            <input type="hidden" name="commission_auto" value="0">
                                            <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="commission_auto" value="1" <?= getSetting('commission_auto', '1') === '1' ? 'checked' : '' ?>></div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="switch-row">
                                            <div><div class="label">Impression auto de la facture</div><div class="hint">Proposer impression après validation de la vente.</div></div>
                                            <input type="hidden" name="auto_print_invoice" value="0">
                                            <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="auto_print_invoice" value="1" <?= getSetting('auto_print_invoice') === '1' ? 'checked' : '' ?>></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4 pt-3 border-top border-white border-opacity-10">
                                <button type="submit" class="btn btn-save-section"><i class="fa-solid fa-floppy-disk me-2"></i>Enregistrer</button>
                            </div>
                        </form>
                    </div>

                    <!-- 6. PROMOTIONS & PACKS -->
                    <div class="tab-pane fade" id="tab-promo">
                        <form class="param-form" data-category="promo">
                            <?= getCsrfInput() ?>
                            <div class="param-section">
                                <h6><i class="fa-solid fa-tags"></i> Promotions & packs</h6>
                                <div class="switch-row">
                                    <div><div class="label">Activer les promotions</div><div class="hint">Afficher et appliquer les promotions actives.</div></div>
                                    <input type="hidden" name="enable_promotions" value="0">
                                    <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="enable_promotions" value="1" <?= getSetting('enable_promotions', '1') === '1' ? 'checked' : '' ?>></div>
                                </div>
                                <div class="switch-row">
                                    <div><div class="label">Priorité promo sur pack</div><div class="hint">Si les deux s’appliquent, la promotion prime.</div></div>
                                    <input type="hidden" name="promo_priority_over_pack" value="0">
                                    <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="promo_priority_over_pack" value="1" <?= getSetting('promo_priority_over_pack', '1') === '1' ? 'checked' : '' ?>></div>
                                </div>
                                <div class="switch-row">
                                    <div><div class="label">Application automatique en caisse</div><div class="hint">Appliquer les promos éligibles au panier automatiquement.</div></div>
                                    <input type="hidden" name="auto_apply_promo_at_pos" value="0">
                                    <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="auto_apply_promo_at_pos" value="1" <?= getSetting('auto_apply_promo_at_pos', '1') === '1' ? 'checked' : '' ?>></div>
                                </div>
                            </div>
                            <div class="mt-4 pt-3 border-top border-white border-opacity-10">
                                <button type="submit" class="btn btn-save-section"><i class="fa-solid fa-floppy-disk me-2"></i>Enregistrer</button>
                            </div>
                        </form>
                    </div>

                    <!-- 7. SAV -->
                    <div class="tab-pane fade" id="tab-sav">
                        <form class="param-form" data-category="sav">
                            <?= getCsrfInput() ?>
                            <div class="param-section">
                                <h6><i class="fa-solid fa-screwdriver-wrench"></i> Service après-vente</h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label-settings">Garantie par défaut (mois)</label>
                                        <input type="number" name="default_warranty_months" class="form-control form-control-settings" value="<?= (int)getSetting('default_warranty_months', 12) ?>" min="0">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label-settings">Délai max réparation (jours)</label>
                                        <input type="number" name="repair_max_days" class="form-control form-control-settings" value="<?= (int)getSetting('repair_max_days', 7) ?>" min="1">
                                    </div>
                                    <div class="col-12">
                                        <div class="switch-row">
                                            <div><div class="label">Validation chef technique obligatoire</div><div class="hint">Diagnostic / sortie validés par un chef.</div></div>
                                            <input type="hidden" name="chef_validation_required" value="0">
                                            <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="chef_validation_required" value="1" <?= getSetting('chef_validation_required', '1') === '1' ? 'checked' : '' ?>></div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="switch-row">
                                            <div><div class="label">Constater l’état à l’entrée (obligatoire)</div><div class="hint">Fiche état entrée SAV obligatoire.</div></div>
                                            <input type="hidden" name="condition_report_required" value="0">
                                            <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="condition_report_required" value="1" <?= getSetting('condition_report_required', '1') === '1' ? 'checked' : '' ?>></div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="switch-row">
                                            <div><div class="label">Autoriser SAV externe</div><div class="hint">Enregistrer des dossiers sous-traités à l’externe.</div></div>
                                            <input type="hidden" name="allow_external_sav" value="0">
                                            <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="allow_external_sav" value="1" <?= getSetting('allow_external_sav') === '1' ? 'checked' : '' ?>></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4 pt-3 border-top border-white border-opacity-10">
                                <button type="submit" class="btn btn-save-section"><i class="fa-solid fa-floppy-disk me-2"></i>Enregistrer</button>
                            </div>
                        </form>
                    </div>

                    <!-- 8. RAPPORTS & DISCIPLINE -->
                    <div class="tab-pane fade" id="tab-reports">
                        <form class="param-form" data-category="reports">
                            <?= getCsrfInput() ?>
                            <div class="param-section">
                                <h6><i class="fa-solid fa-clipboard-list"></i> Rapports journaliers & discipline</h6>
                                <div class="switch-row">
                                    <div><div class="label">Rapport journalier obligatoire</div><div class="hint">Rappel pour les rôles concernés.</div></div>
                                    <input type="hidden" name="daily_report_required" value="0">
                                    <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="daily_report_required" value="1" <?= getSetting('daily_report_required', '1') === '1' ? 'checked' : '' ?>></div>
                                </div>
                                <div class="switch-row">
                                    <div><div class="label">Bloquer déconnexion sans rapport</div><div class="hint">Empêcher de se déconnecter si rapport du jour non soumis.</div></div>
                                    <input type="hidden" name="block_logout_without_report" value="0">
                                    <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="block_logout_without_report" value="1" <?= getSetting('block_logout_without_report') === '1' ? 'checked' : '' ?>></div>
                                </div>
                                <div class="switch-row">
                                    <div><div class="label">Validation Super Admin</div><div class="hint">Les rapports doivent être validés par le Super Admin.</div></div>
                                    <input type="hidden" name="super_admin_validate_reports" value="0">
                                    <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="super_admin_validate_reports" value="1" <?= getSetting('super_admin_validate_reports', '1') === '1' ? 'checked' : '' ?>></div>
                                </div>
                                <div class="switch-row">
                                    <div><div class="label">Génération PDF automatique</div><div class="hint">Exporter les rapports en PDF.</div></div>
                                    <input type="hidden" name="pdf_auto_generate" value="0">
                                    <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="pdf_auto_generate" value="1" <?= getSetting('pdf_auto_generate') === '1' ? 'checked' : '' ?>></div>
                                </div>
                            </div>
                            <div class="mt-4 pt-3 border-top border-white border-opacity-10">
                                <button type="submit" class="btn btn-save-section"><i class="fa-solid fa-floppy-disk me-2"></i>Enregistrer</button>
                            </div>
                        </form>
                    </div>

                    <!-- 9. LOGS & AUDIT -->
                    <div class="tab-pane fade" id="tab-audit">
                        <form class="param-form" data-category="audit">
                            <?= getCsrfInput() ?>
                            <div class="param-section">
                                <h6><i class="fa-solid fa-history"></i> Traçabilité & preuve légale</h6>
                                <div class="switch-row">
                                    <div><div class="label">Log des actions critiques</div><div class="hint">Modifications prix, stock, ventes, utilisateurs.</div></div>
                                    <input type="hidden" name="log_critical_actions" value="0">
                                    <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="log_critical_actions" value="1" <?= getSetting('log_critical_actions', '1') === '1' ? 'checked' : '' ?>></div>
                                </div>
                                <div class="switch-row">
                                    <div><div class="label">Historique des modifications</div><div class="hint">Qui a modifié quoi et quand.</div></div>
                                    <input type="hidden" name="log_modification_history" value="0">
                                    <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="log_modification_history" value="1" <?= getSetting('log_modification_history', '1') === '1' ? 'checked' : '' ?>></div>
                                </div>
                                <div class="switch-row">
                                    <div><div class="label">Enregistrer l’IP utilisateur</div><div class="hint">Pour audit et sécurité.</div></div>
                                    <input type="hidden" name="log_user_ip" value="0">
                                    <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" name="log_user_ip" value="1" <?= getSetting('log_user_ip', '1') === '1' ? 'checked' : '' ?>></div>
                                </div>
                            </div>
                            <div class="mt-4 pt-3 border-top border-white border-opacity-10">
                                <button type="submit" class="btn btn-save-section"><i class="fa-solid fa-floppy-disk me-2"></i>Enregistrer</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="../assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script src="../assets/vendor/sweetalert2/sweetalert2.all.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const Swal = window.Swal;
    var processSettingsUrl = <?= json_encode($process_settings_url) ?>;

    document.querySelectorAll('.param-form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            var category = form.getAttribute('data-category') || 'general';
            var formData = new FormData(form);
            var payload = new FormData();
            var csrfEl = form.querySelector('input[name="csrf_token"]');
            if (!csrfEl) { Swal.fire({ icon: 'error', title: 'Erreur', text: 'Session expirée. Rechargez la page.' }); return; }
            payload.append('action', 'save_settings');
            payload.append('csrf_token', csrfEl.value);
            formData.forEach(function(value, key) {
                if (key === 'csrf_token') return;
                payload.append('settings[' + key + '][value]', value);
                payload.append('settings[' + key + '][category]', category);
            });

            fetch(processSettingsUrl, { method: 'POST', body: payload })
                .then(function(r) { var ct = r.headers.get('Content-Type') || ''; if (ct.indexOf('json') !== -1) return r.json(); return r.text().then(function(t) { throw new Error(t || 'Réponse invalide'); }); })
                .then(function(data) {
                    if (data && data.success) {
                        Swal.fire({ icon: 'success', title: 'Enregistré', text: data.message || 'Paramètres enregistrés.', timer: 1800, showConfirmButton: false });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Erreur', text: data.message || 'Erreur lors de l’enregistrement.' });
                    }
                })
                .catch(function(err) {
                    Swal.fire({ icon: 'error', title: 'Erreur', text: (err && err.message) ? err.message : 'Requête impossible.' });
                });
        });
    });

    // Maintenance
    var mt = document.getElementById('maintenanceToggle');
    if (mt) {
        mt.addEventListener('change', function() {
            var fd = new FormData();
            fd.append('action', 'toggle_maintenance');
            fd.append('csrf_token', '<?= addslashes($_SESSION['csrf_token'] ?? '') ?>');
            fetch(window.location.href, { method: 'POST', body: fd }).then(function() { window.location.reload(); });
        });
    }

    var logoPreview = document.getElementById('logoPreview');
    var logoInput = document.getElementById('logoInput');
    if (logoPreview && logoInput) {
        logoPreview.addEventListener('click', function() { logoInput.click(); });
        logoInput.addEventListener('change', function() {
            if (!this.files || !this.files[0]) return;
            var fd = new FormData();
            fd.append('action', 'upload_logo');
            fd.append('logo', this.files[0]);
            var csrf = document.querySelector('.param-form input[name="csrf_token"]');
            if (csrf) fd.append('csrf_token', csrf.value);
            fetch(processSettingsUrl, { method: 'POST', body: fd })
                .then(function(r) { return r.json().catch(function() { return { success: false, message: 'Réponse invalide' }; }); })
                .then(function(data) {
                    if (data && data.success) {
                        var path = (data.path || '').replace(/^\/+/, '');
                        logoPreview.style.backgroundImage = path ? "url('../" + path + "')" : 'none';
                        logoPreview.innerHTML = path ? '' : '<i class="fa-solid fa-camera fa-2x text-muted"></i>';
                        Swal.fire({ icon: 'success', title: 'Logo mis à jour' });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Erreur', text: (data && data.message) || 'Échec upload.' });
                    }
                })
                .catch(function() { Swal.fire({ icon: 'error', title: 'Erreur', text: 'Upload impossible.' }); });
        });
    }

    // Onglets : garder .active en sync
    document.querySelectorAll('#paramNav .nav-link').forEach(function(link) {
        link.addEventListener('shown.bs.tab', function() {
            document.querySelectorAll('#paramNav .nav-link').forEach(function(l) { l.classList.remove('active'); });
            link.classList.add('active');
        });
    });
});
</script>
</body>
</html>
