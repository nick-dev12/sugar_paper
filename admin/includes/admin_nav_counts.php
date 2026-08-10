<?php
/**
 * Compteurs badges navigation admin (sidebar + bottom nav).
 * Programmation procédurale uniquement.
 */

if (!function_exists('admin_nav_badge_label')) {
    function admin_nav_badge_label($count) {
        $count = (int) $count;
        return $count > 99 ? '99+' : (string) $count;
    }
}

if (!function_exists('admin_nav_load_counts')) {
    function admin_nav_load_counts() {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $cache = [
            'commandes_a_traiter' => 0,
            'invoice_impayees' => 0,
            'livreurs_actifs' => 0,
        ];

        if (!function_exists('admin_current_role')) {
            require_once __DIR__ . '/../../includes/admin_permissions.php';
        }

        $role = admin_current_role();
        if (in_array($role, ['contable', 'livreur'], true)) {
            return $cache;
        }

        if (!function_exists('count_commandes_a_traiter')) {
            require_once __DIR__ . '/../../models/model_commandes_admin.php';
        }
        $cache['commandes_a_traiter'] = count_commandes_a_traiter('active');

        if (function_exists('admin_can_invoice_hub') && admin_can_invoice_hub()) {
            if (!function_exists('count_bl_factures_impayees')) {
                require_once __DIR__ . '/../../models/model_bl.php';
            }
            if (function_exists('bl_tables_available') && bl_tables_available()) {
                $cache['invoice_impayees'] = count_bl_factures_impayees('active');
            }
        }

        if (function_exists('admin_can_view_livreurs_map') && admin_can_view_livreurs_map()) {
            if (!function_exists('livreur_get_actifs_sur_carte')) {
                require_once __DIR__ . '/../../models/model_livreur_tracking.php';
            }
            if (function_exists('livreur_tracking_tables_ready') && livreur_tracking_tables_ready()) {
                $cache['livreurs_actifs'] = count(livreur_get_actifs_sur_carte());
            }
        }

        return $cache;
    }
}
