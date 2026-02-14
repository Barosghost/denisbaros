<?php
/**
 * DENIS FBI STORE - Contrôle d'accès par rôle
 * Définit les pages et actions autorisées pour chaque rôle.
 */

if (!defined('ROLES_LOADED')) {
    define('ROLES_LOADED', true);
}

// Pages accessibles par rôle (nom du fichier sans .php)
$GLOBALS['ROLES_PAGES'] = [
    'super_admin' => ['*'], // Toutes les pages
    'admin' => [
        'dashboard', 'daily_reports', 'products', 'categories', 'stock', 'sales', 'clients',
        'resellers', 'commissions', 'packs', 'repairs', 'repair_reception', 'repair_details', 'technicians',
        'stock_movements', 'loyalty', 'reports', 'global_report', 'audit_logs', 'users', 'user_management',
        'help', 'invoice', 'inventory_detail', 'service_actions', 'devices'
    ],
    'chef_technique' => [
        'dashboard', 'daily_reports', 'products', 'stock', 'repairs', 'repair_reception', 'repair_details',
        'technicians', 'reports', 'help', 'inventory_detail', 'service_actions'
    ],
    'technicien' => [
        'dashboard', 'repairs', 'repair_details', 'help', 'service_actions'
    ],
    'vendeur' => [
        'dashboard', 'daily_reports', 'products', 'stock', 'sales', 'clients', 'resellers', 'commissions',
        'loyalty', 'repair_reception', 'reports', 'help', 'invoice'
    ],
];

// Rôles autorisés pour les actions sensibles (à utiliser dans les scripts backend)
$GLOBALS['ROLES_ACTIONS'] = [
    'manage_users'       => ['super_admin'],
    'manage_settings'    => ['super_admin'],
    'approve_daily_report' => ['super_admin'],
    'manage_products'   => ['super_admin', 'admin'],
    'manage_stock'       => ['super_admin', 'admin'],       // Ajustement manuel, entrées
    'view_stock_movements'=> ['super_admin', 'admin'],
    'do_sale'            => ['super_admin', 'admin', 'vendeur'],
    'manage_clients'     => ['super_admin', 'admin', 'vendeur'],
    'manage_resellers'    => ['super_admin', 'admin'],
    'pay_commission'     => ['super_admin', 'admin'],
    'add_reward'          => ['super_admin', 'admin'],
    'sav_full'           => ['super_admin', 'admin', 'chef_technique'], // Réception, assignation, validation
    'sav_technician'     => ['super_admin', 'admin', 'chef_technique', 'technicien'], // Consulter, diagnostiquer, réparer (technicien = ses dossiers)
    'send_to_sav'        => ['super_admin', 'admin', 'vendeur'], // Envoyer un produit au SAV (réception)
    'view_audit_logs'    => ['super_admin', 'admin'],
    'view_supervision'   => ['super_admin'],
    'view_global_report' => ['super_admin', 'admin'],
];

/**
 * Retourne le rôle de la session normalisé (slug).
 */
function getSessionRole() {
    $r = $_SESSION['role'] ?? '';
    return str_replace(' ', '_', strtolower($r));
}

/**
 * Vérifie si le rôle actuel peut accéder à la page.
 * @param string $pageName Nom de la page (ex: dashboard, sales)
 * @return bool
 */
function canAccessPage($pageName) {
    $role = getSessionRole();
    $pages = $GLOBALS['ROLES_PAGES'][$role] ?? [];
    if (empty($pages)) return false;
    if (in_array('*', $pages)) return true;
    return in_array($pageName, $pages);
}

/**
 * Inclure après auth_required. Redirige vers dashboard avec erreur si accès refusé.
 * @param string $pageName Nom de la page (ex: basename sans .php)
 */
function requirePageAccess($pageName) {
    if (!canAccessPage($pageName)) {
        header('Location: dashboard.php?error=forbidden');
        exit;
    }
}

/**
 * Vérifie si le rôle actuel peut effectuer une action.
 * @param string $actionKey Clé dans ROLES_ACTIONS (ex: do_sale, manage_users)
 * @return bool
 */
function canDoAction($actionKey) {
    $role = getSessionRole();
    $allowed = $GLOBALS['ROLES_ACTIONS'][$actionKey] ?? [];
    return in_array($role, $allowed);
}

/**
 * À appeler dans les scripts backend. Envoie JSON erreur et exit si non autorisé.
 * @param string $actionKey Clé dans ROLES_ACTIONS
 */
function requireAction($actionKey) {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Non authentifié']);
        exit;
    }
    if (!canDoAction($actionKey)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Accès non autorisé pour votre rôle']);
        exit;
    }
}
