<?php
/**
 * Liaison factures B2B (BL) ↔ comptes clients (users) par numéro de téléphone.
 * Gère les formats avec ou sans indicatif (221, +, 0…).
 */

function bl_find_user_id_by_telephone($telephone)
{
    require_once __DIR__ . '/model_users.php';
    $user = get_user_by_telephone($telephone);
    if (!$user) {
        return 0;
    }
    return (int) ($user['id'] ?? 0);
}

function bl_find_user_id_from_bl($bl_id)
{
    $bl = function_exists('get_bl_by_id') ? get_bl_by_id((int) $bl_id) : false;
    if (!$bl) {
        return 0;
    }
    return bl_find_user_id_by_telephone($bl['client_telephone'] ?? '');
}

function bl_user_telephone_matches($bl_telephone, $user_telephone)
{
    require_once __DIR__ . '/model_users.php';
    $bl_variants = users_phone_lookup_variants($bl_telephone);
    $user_variants = users_phone_lookup_variants($user_telephone);
    if (empty($bl_variants) || empty($user_variants)) {
        return false;
    }
    return count(array_intersect($bl_variants, $user_variants)) > 0;
}

function bl_user_can_access($bl_id, $user_id)
{
    require_once __DIR__ . '/model_users.php';
    $user_id = (int) $user_id;
    $bl_id = (int) $bl_id;
    if ($user_id < 1 || $bl_id < 1) {
        return false;
    }
    $user = get_user_by_id($user_id);
    $bl = function_exists('get_bl_by_id') ? get_bl_by_id($bl_id) : false;
    if (!$user || !$bl) {
        return false;
    }
    return bl_user_telephone_matches($bl['client_telephone'] ?? '', $user['telephone'] ?? '');
}

/**
 * Factures B2B dont le téléphone client B2B correspond au compte connecté.
 *
 * @return list<array<string, mixed>>
 */
function get_bls_for_registered_user($user_id)
{
    global $db;
    require_once __DIR__ . '/model_users.php';

    $user_id = (int) $user_id;
    if ($user_id < 1 || !bl_tables_available()) {
        return [];
    }

    $user = get_user_by_id($user_id);
    if (!$user) {
        return [];
    }

    $variants = users_phone_lookup_variants($user['telephone'] ?? '');
    if (empty($variants)) {
        return [];
    }

    $norm = "REPLACE(REPLACE(" . users_phone_normalized_sql('c.telephone') . ", '(', ''), ')', '')";
    $placeholders = [];
    $params = [];
    foreach ($variants as $i => $variant) {
        $key = 'p' . $i;
        $placeholders[] = ':' . $key;
        $params[$key] = $variant;
    }

    $archived_sql = '';
    if (function_exists('bl_archived_column_ok') && bl_archived_column_ok()) {
        $archived_sql = ' AND COALESCE(b.archived, 0) = 0';
    }

    try {
        $stmt = $db->prepare("
            SELECT b.*, c.raison_sociale, c.telephone AS client_telephone, c.email AS client_email,
                   c.adresse AS client_adresse, c.nom_contact, c.prenom_contact,
                   COALESCE(b.adresse_livraison, b.adresse_client, c.adresse) AS adresse_livraison_affichee
            FROM bons_livraison b
            INNER JOIN clients_b2b c ON c.id = b.client_b2b_id
            WHERE {$norm} IN (" . implode(', ', $placeholders) . ")
              {$archived_sql}
            ORDER BY COALESCE(b.date_bl, b.date_creation) DESC, b.id DESC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $i => $row) {
            if (function_exists('bl_row_apply_statut_bl')) {
                $rows[$i] = bl_row_apply_statut_bl($row);
            }
        }
        return $rows;
    } catch (PDOException $e) {
        return [];
    }
}

function bl_enqueue_client_event($event, $bl_id)
{
    $bl_id = (int) $bl_id;
    $event = trim((string) $event);
    if ($bl_id < 1 || $event === '') {
        return false;
    }
    if (bl_find_user_id_from_bl($bl_id) < 1) {
        return false;
    }
    require_once __DIR__ . '/../services/notify_queue.php';
    $queued = notify_queue_enqueue('bl_client', [
        'event' => $event,
        'bl_id' => $bl_id,
    ], true);
    return !empty($queued['success']);
}

function bl_public_facture_url($bl)
{
    require_once __DIR__ . '/../includes/site_url.php';
    $token = trim((string) ($bl['facture_token'] ?? ''));
    if ($token === '' && function_exists('ensure_bl_facture_token')) {
        $token = (string) ensure_bl_facture_token((int) ($bl['id'] ?? 0));
    }
    if ($token === '') {
        return '';
    }
    return rtrim(get_site_base_url(), '/') . '/facture-bl.php?token=' . rawurlencode($token);
}

/**
 * Statut affiché au client (livraison, pas comptabilité).
 *
 * @return array{key:string,label:string}
 */
function bl_client_statut_affichage(array $bl)
{
    if (function_exists('livreur_livraison_est_terminee') && livreur_livraison_est_terminee($bl, 'facture')) {
        return ['key' => 'livree', 'label' => 'Livrée'];
    }
    if ((int) ($bl['tracking_active'] ?? 0) === 1) {
        return ['key' => 'livraison_en_cours', 'label' => 'Livraison en cours'];
    }
    if (!empty($bl['livreur_id'])) {
        return ['key' => 'prise_en_charge', 'label' => 'Prise en charge'];
    }
    $st = strtolower(trim((string) ($bl['statut'] ?? '')));
    if ($st === 'valide' || $st === 'paye') {
        return ['key' => 'confirmee', 'label' => 'Confirmée'];
    }
    return ['key' => 'en_attente', 'label' => 'Enregistrée'];
}
