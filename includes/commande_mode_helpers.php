<?php
/**
 * Mode commande : livraison ou retrait sur site.
 */

require_once __DIR__ . '/geo_location.php';
require_once __DIR__ . '/../models/model_zones_livraison.php';

function commande_retrait_fallback_adresse() {
    return 'Récupérer sur place - ( Sugar Paper )';
}

function commande_mode_livraison_label($mode) {
    return commande_mode_livraison_normalize($mode) === 'retrait'
        ? 'Récupération sur site'
        : 'Livraison';
}

/**
 * @param array<string, mixed> $commande
 */
function commande_is_retrait(array $commande) {
    if (!empty($commande['mode_livraison'])) {
        return commande_mode_livraison_normalize($commande['mode_livraison']) === 'retrait';
    }

    $adresse = mb_strtolower(trim((string) ($commande['adresse_livraison'] ?? '')));
    if (strpos($adresse, 'récupérer') !== false || strpos($adresse, 'recuperer') !== false) {
        return true;
    }

    if (!empty($commande['zone_livraison_id'])) {
        $zone = get_zone_livraison_by_id((int) $commande['zone_livraison_id']);
        if ($zone && zones_livraison_find_retrait([$zone])) {
            return true;
        }
    }

    return false;
}

function _commandes_has_mode_livraison_column() {
    static $has = null;
    if ($has !== null) {
        return $has;
    }
    global $db;
    try {
        $r = $db->query("SHOW COLUMNS FROM commandes LIKE 'mode_livraison'");
        $has = $r && $r->rowCount() > 0;
    } catch (PDOException $e) {
        $has = false;
    }
    return $has;
}

function geo_save_commande_mode($commande_id, $mode) {
    global $db;
    $commande_id = (int) $commande_id;
    if ($commande_id <= 0 || !_commandes_has_mode_livraison_column()) {
        return false;
    }
    $mode = commande_mode_livraison_normalize($mode);
    try {
        $stmt = $db->prepare('UPDATE commandes SET mode_livraison = :mode WHERE id = :id');
        return $stmt->execute(['id' => $commande_id, 'mode' => $mode]);
    } catch (PDOException $e) {
        error_log('[geo_save_commande_mode] ' . $e->getMessage());
        return false;
    }
}
