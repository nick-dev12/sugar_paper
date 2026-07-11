<?php
/**
 * Périmètre « paramètres vitrine » : vendeur = sa boutique ; sinon = contenu plateforme (admin_id NULL).
 */
function admin_param_boutique_scope_id() {
    if (!isset($_SESSION['admin_id'])) {
        return null;
    }
    if (isset($_SESSION['admin_role']) && (string) ($_SESSION['admin_role'] ?? '') === 'vendeur') {
        return (int) $_SESSION['admin_id'];
    }
    return null;
}
