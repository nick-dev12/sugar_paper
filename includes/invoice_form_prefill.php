<?php
/**
 * Préremplissage des modales facture (BL) et devis en mode édition.
 */

require_once __DIR__ . '/../models/model_bl.php';
require_once __DIR__ . '/../models/model_devis.php';

/**
 * @return array{bl_id:int,post:array,lignes:list<array<string,mixed>>}|null
 */
function invoice_bl_prefill_for_modal($bl_id)
{
    $bl_id = (int) $bl_id;
    if ($bl_id <= 0 || !bl_tables_available()) {
        return null;
    }
    $bl = get_bl_by_id($bl_id);
    if (!$bl) {
        return null;
    }

    $lignes_db = get_lignes_bl($bl_id);
    $frais = 0.0;
    $lignes = [];
    foreach ($lignes_db as $l) {
        $designation = trim((string) ($l['designation'] ?? ''));
        if ($designation !== '' && stripos($designation, 'frais de livraison') !== false) {
            $frais = (float) ($l['prix_unitaire_ht'] ?? 0);
            continue;
        }
        $pid = (int) ($l['produit_id'] ?? 0);
        if ($pid <= 0 && $designation === '') {
            continue;
        }
        $lignes[] = [
            'produit_id' => $pid,
            'nom' => $designation !== '' ? $designation : 'Produit',
            'quantite' => (float) ($l['quantite'] ?? 1),
            'prix_unitaire' => (float) ($l['prix_unitaire_ht'] ?? 0),
            'prix_promotion' => '',
        ];
    }

    $adresse_livraison = trim((string) ($bl['client_adresse'] ?? ''));
    $zone_id = '';
    if ($adresse_livraison !== '') {
        $zone_id = 'custom';
    }

    $post = [
        'client_nom' => trim((string) ($bl['nom_contact'] ?? '')),
        'client_prenom' => trim((string) ($bl['prenom_contact'] ?? '')),
        'client_telephone' => trim((string) ($bl['client_telephone'] ?? '')),
        'client_email' => trim((string) ($bl['client_email'] ?? '')),
        'adresse_client' => trim((string) ($bl['adresse_client'] ?? '')),
        'adresse_livraison' => $adresse_livraison,
        'zone_livraison_id' => $zone_id,
        'frais_livraison' => $frais,
        'notes' => trim((string) ($bl['notes'] ?? '')),
        'date_bl' => !empty($bl['date_bl']) ? date('Y-m-d', strtotime($bl['date_bl'])) : date('Y-m-d', strtotime($bl['date_creation'] ?? 'now')),
        'statut' => in_array($bl['statut'] ?? '', ['brouillon', 'valide'], true) ? $bl['statut'] : 'brouillon',
        'remise_globale_pct' => (string) ((float) ($bl['remise_globale_pct'] ?? 0)),
        'inclure_tva' => !empty($bl['tva_incluse']) ? '1' : '0',
    ];

    return [
        'bl_id' => $bl_id,
        'numero_bl' => (string) ($bl['numero_bl'] ?? ''),
        'post' => $post,
        'lignes' => $lignes,
    ];
}

/**
 * @return array{devis_id:int,post:array,lignes:list<array<string,mixed>>}|null
 */
function invoice_devis_prefill_for_modal($devis_id)
{
    $devis_id = (int) $devis_id;
    if ($devis_id <= 0) {
        return null;
    }
    $devis = get_devis_by_id($devis_id);
    if (!$devis) {
        return null;
    }
    $produits = get_produits_by_devis($devis_id);
    $lignes = [];
    foreach ($produits as $p) {
        $pid = (int) ($p['produit_id'] ?? 0);
        if ($pid <= 0) {
            continue;
        }
        $lignes[] = [
            'produit_id' => $pid,
            'nom' => (string) ($p['produit_nom'] ?? $p['nom_produit'] ?? 'Produit'),
            'quantite' => (int) ($p['quantite'] ?? 1),
            'prix_unitaire' => (float) ($p['prix_unitaire'] ?? 0),
            'prix_promotion' => '',
        ];
    }

    $zone_id = $devis['zone_livraison_id'] ?? '';
    if ($zone_id !== null && $zone_id !== '' && (int) $zone_id > 0) {
        $zone_id = (int) $zone_id;
    } elseif (!empty($devis['adresse_livraison'])) {
        $zone_id = 'custom';
    } else {
        $zone_id = '';
    }

    $post = [
        'client_nom' => trim((string) ($devis['client_nom'] ?? '')),
        'client_prenom' => trim((string) ($devis['client_prenom'] ?? '')),
        'client_telephone' => trim((string) ($devis['client_telephone'] ?? '')),
        'client_email' => trim((string) ($devis['client_email'] ?? '')),
        'adresse_livraison' => trim((string) ($devis['adresse_livraison'] ?? '')),
        'zone_livraison_id' => $zone_id,
        'frais_livraison' => (float) ($devis['frais_livraison'] ?? 0),
        'notes' => trim((string) ($devis['notes'] ?? '')),
        'remise_globale_pct' => (string) ((float) ($devis['remise_globale_pct'] ?? 0)),
        'user_id' => !empty($devis['user_id']) ? (string) (int) $devis['user_id'] : '',
    ];

    return [
        'devis_id' => $devis_id,
        'numero_devis' => (string) ($devis['numero_devis'] ?? ''),
        'post' => $post,
        'lignes' => $lignes,
    ];
}
