<?php
/**
 * Permissions et rôles — espace administration
 * Programmation procédurale uniquement
 */

if (!function_exists('normalize_admin_role')) {
    require_once __DIR__ . '/../models/model_admin.php';
}

if (!function_exists('admin_current_role')) {

    function admin_current_role() {
        return normalize_admin_role($_SESSION['admin_role'] ?? 'admin');
    }

    function admin_is_full_admin() {
        return admin_current_role() === 'admin';
    }

    function admin_is_restricted_admin_account() {
        return false;
    }

    function admin_is_utilisateur() {
        return admin_current_role() === 'utilisateur';
    }

    function admin_can_zones_livraison() {
        $r = admin_current_role();
        return $r === 'admin' || $r === 'livreur' || $r === 'utilisateur';
    }

    /**
     * Carte GPS livreurs — admin et comptes utilisateur boutique.
     */
    function admin_can_view_livreurs_map() {
        $r = admin_current_role();
        return $r === 'admin' || $r === 'utilisateur';
    }

    /**
     * Hub Invoice (contacts, rapports) — utilisateur boutique inclus.
     */
    function admin_can_invoice_hub() {
        $r = admin_current_role();
        return admin_can_devis() || admin_can_bl_retours_b2b() || $r === 'utilisateur';
    }

    /**
     * Accès hub livreurs : GPS, zones, prise de commandes.
     */
    function admin_can_livreur_gps() {
        $r = admin_current_role();
        return $r === 'admin' || $r === 'livreur';
    }

    /**
     * Gestion des comptes et suivi global (admin uniquement).
     */
    function admin_can_manage_livreurs() {
        return admin_current_role() === 'admin';
    }

    function admin_can_commercial() {
        $r = admin_current_role();
        return in_array($r, ['admin', 'commercial', 'commercial_general', 'informaticien', 'developpeur'], true);
    }

    /**
     * Devis — admin, commercial, commercial général, informaticien / développeur.
     */
    function admin_can_devis() {
        $r = admin_current_role();
        return in_array($r, ['admin', 'commercial', 'commercial_general', 'informaticien', 'developpeur'], true);
    }

    /**
     * Bons de livraison B2B — admin, commercial général, informaticien / développeur.
     */
    function admin_can_bl_retours_b2b() {
        $r = admin_current_role();
        return in_array($r, ['admin', 'commercial_general', 'informaticien', 'developpeur'], true);
    }

    function admin_can_comptabilite() {
        $r = admin_current_role();
        return $r === 'admin' || $r === 'comptabilite' || $r === 'informaticien' || $r === 'developpeur';
    }

    function admin_can_consulter_bl_b2b_compta() {
        return admin_can_bl_retours_b2b() || admin_can_comptabilite();
    }

    function admin_can_consulter_devis_compta() {
        return admin_can_devis() || admin_can_comptabilite();
    }

    function admin_can_rh() {
        $r = admin_current_role();
        return $r === 'rh' || $r === 'informaticien' || $r === 'developpeur';
    }

    function admin_can_caisse() {
        $r = admin_current_role();
        return in_array($r, ['commercial', 'commercial_general', 'caissier', 'informaticien', 'developpeur'], true);
    }

    function admin_can_caisse_vendeur() {
        $r = admin_current_role();
        return $r === 'commercial' || $r === 'commercial_general' || $r === 'informaticien' || $r === 'developpeur';
    }

    function admin_can_encaisser_ticket() {
        $r = admin_current_role();
        return $r === 'caissier' || $r === 'informaticien' || $r === 'developpeur';
    }

    function admin_can_saisir_depenses_caisse() {
        $r = admin_current_role();
        return $r === 'caissier' || $r === 'informaticien' || $r === 'developpeur';
    }

    function admin_can_commandes() {
        $r = admin_current_role();
        return $r === 'admin' || $r === 'utilisateur';
    }

    function admin_can_gestion_boutique() {
        $r = admin_current_role();
        return $r === 'utilisateur' || $r === 'admin';
    }

    function admin_can_receive_stock_alerte_popup() {
        $r = admin_current_role();
        return in_array($r, ['admin', 'utilisateur'], true);
    }

    function admin_is_contable() {
        return admin_current_role() === 'contable';
    }

    function admin_require_roles($allowed_roles, $redirect = 'dashboard.php') {
        $r = admin_current_role();
        if (in_array($r, $allowed_roles, true)) {
            return;
        }
        header('Location: ' . $redirect);
        exit;
    }
}
