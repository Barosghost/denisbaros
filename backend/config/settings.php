<?php
/**
 * Paramètres système - chargement centralisé
 * getSystemSetting($key, $default, $pdo = null) - si $pdo non fourni, utilise $GLOBALS['pdo']
 */
if (!function_exists('getSystemSetting')) {
    function getSystemSetting($key, $default = '', $pdo = null)
    {
        static $cache = null;
        if ($cache === null) {
            $cache = [];
            $pdo = $pdo ?? ($GLOBALS['pdo'] ?? null);
            if ($pdo instanceof PDO) {
                try {
                    $stmt = $pdo->query("SELECT `key`, `value` FROM system_settings");
                    if ($stmt) {
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            $cache[$row['key']] = $row['value'];
                        }
                    }
                } catch (PDOException $e) {
                    // Table peut ne pas exister
                }
            }
        }
        return $cache[$key] ?? $default;
    }
}
