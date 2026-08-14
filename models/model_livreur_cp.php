<?php
/**
 * Suivi GPS / prise en charge des commandes personnalisées (même flux que commandes).
 */

function livreur_cp_livraison_columns_ok()
{
    global $db;
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    try {
        $db->query('SELECT livreur_id, tracking_active, delivery_latitude, delivery_longitude FROM commandes_personnalisees LIMIT 1');
        $ok = true;
    } catch (PDOException $e) {
        $ok = false;
    }
    return $ok;
}

function livreur_watch_token_has_cp_column()
{
    global $db;
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    try {
        $db->query('SELECT cp_id FROM tracking_watch_tokens LIMIT 1');
        $ok = true;
    } catch (PDOException $e) {
        $ok = false;
    }
    return $ok;
}

function livreur_positions_has_cp_column()
{
    global $db;
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    try {
        $db->query('SELECT cp_id FROM livreur_positions LIMIT 1');
        $ok = true;
    } catch (PDOException $e) {
        $ok = false;
    }
    return $ok;
}

function livreur_cp_numero($cp_id)
{
    return 'CP-' . (int) $cp_id;
}

function livreur_cp_adresse_affichage(array $cp)
{
    $adresse = trim((string) ($cp['adresse_livraison'] ?? ''));
    if ($adresse !== '') {
        return $adresse;
    }
    $parts = array_filter([
        trim((string) ($cp['zone_quartier'] ?? '')),
        trim((string) ($cp['zone_ville'] ?? '')),
    ]);
    return implode(', ', $parts);
}

function livreur_cp_search_blob(array $cp)
{
    $nom = trim((string) ($cp['prenom'] ?? '') . ' ' . (string) ($cp['nom'] ?? ''));
    $parts = [
        livreur_cp_numero((int) ($cp['id'] ?? 0)),
        $nom,
        (string) ($cp['telephone'] ?? ''),
        livreur_cp_adresse_affichage($cp),
        (string) ($cp['type_produit'] ?? ''),
    ];
    $parts = array_filter($parts);
    $blob = implode(' ', $parts);
    return function_exists('mb_strtolower') ? mb_strtolower($blob, 'UTF-8') : strtolower($blob);
}

function livreur_cp_statut_livraison(array $cp)
{
    if (livreur_livraison_est_terminee($cp, 'personnalisee')) {
        return 'Terminée';
    }
    $statut = (string) ($cp['statut'] ?? '');
    if ($statut === 'livraison_en_cours' || !empty($cp['tracking_active'])) {
        return 'En livraison';
    }
    if (!empty($cp['livreur_id'])) {
        return 'Prise en charge';
    }
    $labels = function_exists('get_statuts_commande_personnalisee')
        ? get_statuts_commande_personnalisee()
        : [];
    return $labels[$statut] ?? ($statut !== '' ? $statut : 'Disponible');
}

function livreur_get_cp_tracking($cp_id)
{
    global $db;
    if (!livreur_cp_livraison_columns_ok()) {
        return false;
    }
    require_once __DIR__ . '/model_commandes_personnalisees.php';
    try {
        $has_zone = function_exists('_cp_has_zone_livraison_column') && _cp_has_zone_livraison_column();
        $zone_join = $has_zone
            ? 'LEFT JOIN zones_livraison zl ON cp.zone_livraison_id = zl.id'
            : '';
        $zone_cols = $has_zone
            ? ', zl.ville AS zone_ville, zl.quartier AS zone_quartier, zl.prix_livraison AS zone_prix_livraison'
            : ', NULL AS zone_ville, NULL AS zone_quartier, NULL AS zone_prix_livraison';
        $stmt = $db->prepare("
            SELECT cp.*,
                   COALESCE(u.prenom, cp.prenom) AS client_prenom,
                   COALESCE(u.nom, cp.nom) AS client_nom,
                   COALESCE(u.telephone, cp.telephone) AS client_telephone,
                   a.nom AS livreur_nom, a.prenom AS livreur_prenom, a.email AS livreur_email
                   " . livreur_admin_photo_profil_sql_select('a') . "
                   $zone_cols
            FROM commandes_personnalisees cp
            LEFT JOIN users u ON u.id = cp.user_id
            LEFT JOIN admin a ON a.id = cp.livreur_id
            $zone_join
            WHERE cp.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => (int) $cp_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }
        $row['livraison_type'] = 'personnalisee';
        $row['numero_commande'] = livreur_cp_numero((int) $row['id']);
        if (trim((string) ($row['adresse_livraison'] ?? '')) === '') {
            $row['adresse_livraison'] = livreur_cp_adresse_affichage($row);
        }
        return $row;
    } catch (PDOException $e) {
        return false;
    }
}

function livreur_get_cp_livraison_list($only_today = false)
{
    global $db;
    if (!livreur_cp_livraison_columns_ok()) {
        return [];
    }
    try {
        $sql = "
            SELECT cp.id, cp.statut, cp.nom, cp.prenom, cp.telephone, cp.user_id,
                   cp.livreur_id, cp.tracking_active, cp.date_creation, cp.prix,
                   cp.delivery_latitude, cp.delivery_longitude, cp.adresse_livraison,
                   cp.livraison_terminee_at, cp.type_produit,
                   COALESCE(u.prenom, cp.prenom) AS user_prenom,
                   COALESCE(u.nom, cp.nom) AS user_nom,
                   COALESCE(u.telephone, cp.telephone) AS user_telephone,
                   a.nom AS livreur_nom, a.prenom AS livreur_prenom
            FROM commandes_personnalisees cp
            LEFT JOIN users u ON u.id = cp.user_id
            LEFT JOIN admin a ON a.id = cp.livreur_id AND a.role IN ('livreur', 'admin')
            WHERE cp.statut NOT IN ('en_attente', 'devis_envoye', 'refusee', 'annulee', 'terminee')
              AND (
                  cp.statut NOT IN ('livree')
                  OR cp.livreur_id IS NOT NULL
              )
        ";
        if ($only_today) {
            $sql .= " AND DATE(cp.date_creation) = CURDATE()";
        }
        $sql .= " ORDER BY cp.date_creation DESC";
        $stmt = $db->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $i => $row) {
            $rows[$i]['numero_commande'] = livreur_cp_numero((int) $row['id']);
            $rows[$i]['client_prenom'] = $row['user_prenom'] ?? $row['prenom'] ?? '';
            $rows[$i]['client_nom'] = $row['user_nom'] ?? $row['nom'] ?? '';
            $rows[$i]['client_telephone'] = $row['user_telephone'] ?? $row['telephone'] ?? '';
        }
        return $rows;
    } catch (PDOException $e) {
        return [];
    }
}

function livreur_commencer_livraison_cp($cp_id, $admin_livreur_id, array $coords, $require_today = true)
{
    global $db;

    if (!livreur_cp_livraison_columns_ok()) {
        return ['ok' => false, 'error' => 'Module livraison personnalisée non installé. Exécutez php migrations/run_add_cp_livreur_tracking.php'];
    }

    $cp_id = (int) $cp_id;
    $admin_livreur_id = (int) $admin_livreur_id;
    if ($cp_id < 1 || $admin_livreur_id < 1) {
        return ['ok' => false, 'error' => 'Paramètres invalides.'];
    }

    $driver_lat = livreur_parse_coord($coords['driver_lat'] ?? null);
    $driver_lng = livreur_parse_coord($coords['driver_lng'] ?? null);
    $delivery_lat = livreur_parse_coord($coords['delivery_lat'] ?? null);
    $delivery_lng = livreur_parse_coord($coords['delivery_lng'] ?? null);
    $adresse = trim((string) ($coords['adresse_livraison'] ?? ''));

    if ($driver_lat === null || $driver_lng === null) {
        return ['ok' => false, 'error' => 'Position du livreur requise. Autorisez la géolocalisation.'];
    }
    if ($delivery_lat === null || $delivery_lng === null) {
        return ['ok' => false, 'error' => 'Adresse client introuvable sur la carte. Vérifiez l\'adresse.'];
    }
    if ($adresse === '') {
        return ['ok' => false, 'error' => 'L\'adresse de livraison est obligatoire.'];
    }

    try {
        $sql = "
            SELECT id, livreur_id, statut, user_id, email, nom, prenom
            FROM commandes_personnalisees
            WHERE id = :id
        ";
        if ($require_today) {
            $sql .= " AND DATE(date_creation) = CURDATE()";
        }
        $sql .= " LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute(['id' => $cp_id]);
        $cp = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cp) {
            return ['ok' => false, 'error' => 'Commande personnalisée introuvable' . ($require_today ? ' ou pas du jour' : '') . '.'];
        }

        if (in_array($cp['statut'] ?? '', ['refusee', 'annulee', 'livree'], true)) {
            return ['ok' => false, 'error' => 'Cette demande n\'est plus disponible.'];
        }
        if (livreur_livraison_est_terminee($cp, 'personnalisee')) {
            return ['ok' => false, 'error' => 'Cette commande personnalisée a déjà été livrée.'];
        }

        $current_livreur = $cp['livreur_id'] !== null ? (int) $cp['livreur_id'] : null;
        if ($current_livreur !== null && $current_livreur !== $admin_livreur_id) {
            return ['ok' => false, 'error' => 'Cette commande a déjà été prise par un autre livreur.'];
        }

        $stmt_admin = $db->prepare("SELECT id, role, statut FROM admin WHERE id = :id LIMIT 1");
        $stmt_admin->execute(['id' => $admin_livreur_id]);
        $admin_row = $stmt_admin->fetch(PDO::FETCH_ASSOC);
        if (!$admin_row || !in_array($admin_row['role'] ?? '', ['livreur', 'admin'], true) || ($admin_row['statut'] ?? '') !== 'actif') {
            return ['ok' => false, 'error' => 'Compte livreur invalide.'];
        }

        $db->beginTransaction();
        $upd = $db->prepare("
            UPDATE commandes_personnalisees
            SET livreur_id = :livreur_id,
                delivery_latitude = :delivery_lat,
                delivery_longitude = :delivery_lng,
                adresse_livraison = :adresse,
                statut = 'livraison_en_cours',
                tracking_active = 0,
                tracking_started_at = NULL,
                date_modification = NOW()
            WHERE id = :id
              AND (livreur_id IS NULL OR livreur_id = :livreur_id2)
        ");
        $upd->execute([
            'livreur_id' => $admin_livreur_id,
            'delivery_lat' => $delivery_lat,
            'delivery_lng' => $delivery_lng,
            'adresse' => $adresse,
            'id' => $cp_id,
            'livreur_id2' => $admin_livreur_id,
        ]);
        if ($upd->rowCount() < 1) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'Impossible de démarrer cette livraison.'];
        }
        $db->commit();

        $numero = livreur_cp_numero($cp_id);
        if ($current_livreur === null) {
            require_once __DIR__ . '/../services/livreur_push_notifications.php';
            livreur_enqueue_prise_notifications('personnalisee', $cp_id, $admin_livreur_id, $numero);
        }

        $uid = (int) ($cp['user_id'] ?? 0);
        if ($uid > 0) {
            require_once __DIR__ . '/../services/send_commande_personnalisee_notification.php';
            $email = trim((string) ($cp['email'] ?? ''));
            send_commande_personnalisee_status_notification($uid, $cp_id, 'livraison_en_cours', $email);
        }

        return [
            'ok' => true,
            'message' => 'Livraison démarrée pour ' . $numero . '.',
            'cp_id' => $cp_id,
            'driver_lat' => $driver_lat,
            'driver_lng' => $driver_lng,
            'delivery_lat' => $delivery_lat,
            'delivery_lng' => $delivery_lng,
        ];
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return ['ok' => false, 'error' => 'Erreur lors du démarrage de la livraison personnalisée.'];
    }
}

function livreur_merge_watch_token_with_cp($token_hash, $cp_id)
{
    $cp_id = (int) $cp_id;
    if ($cp_id < 1) {
        return false;
    }
    $cp = livreur_get_cp_tracking($cp_id);
    if (!$cp) {
        return false;
    }
    global $db;
    try {
        if (livreur_watch_token_has_cp_column()) {
            $stmt = $db->prepare("
                SELECT *
                FROM tracking_watch_tokens
                WHERE token_hash = :hash
                  AND expires_at > NOW()
                  AND (cp_id IS NULL OR cp_id = :cp_id)
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmt->execute(['hash' => $token_hash, 'cp_id' => $cp_id]);
        } else {
            $stmt = $db->prepare("
                SELECT *
                FROM tracking_watch_tokens
                WHERE token_hash = :hash AND expires_at > NOW()
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmt->execute(['hash' => $token_hash]);
        }
        $token_row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$token_row) {
            return false;
        }
        return array_merge($token_row, [
            'numero_commande' => livreur_cp_numero($cp_id),
            'livreur_id' => $cp['livreur_id'] ?? null,
            'tracking_active' => $cp['tracking_active'] ?? 0,
            'delivery_latitude' => $cp['delivery_latitude'] ?? null,
            'delivery_longitude' => $cp['delivery_longitude'] ?? null,
            'adresse_livraison' => $cp['adresse_livraison'] ?? '',
            'livreur_nom' => $cp['livreur_nom'] ?? '',
            'livreur_prenom' => $cp['livreur_prenom'] ?? '',
            'delivery_countdown_initial_sec' => $cp['delivery_countdown_initial_sec'] ?? null,
            'delivery_countdown_remaining_sec' => $cp['delivery_countdown_remaining_sec'] ?? null,
            'delivery_countdown_running_at' => $cp['delivery_countdown_running_at'] ?? null,
            'livraison_type' => 'personnalisee',
            'cp_id' => $cp_id,
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

function livreur_client_public_suivi_url_cp($cp_id, $user_id = null, $require_tracking = false)
{
    require_once __DIR__ . '/../includes/site_url.php';
    $cp_id = (int) $cp_id;
    if ($cp_id < 1) {
        return false;
    }
    $cp = livreur_get_cp_tracking($cp_id);
    if (!$cp || empty($cp['livreur_id'])) {
        return false;
    }
    if ($user_id !== null && (int) $user_id > 0 && (int) ($cp['user_id'] ?? 0) !== (int) $user_id) {
        return false;
    }
    if (livreur_livraison_est_terminee($cp, 'personnalisee')) {
        return false;
    }
    if ($require_tracking && (int) ($cp['tracking_active'] ?? 0) !== 1) {
        return false;
    }
    $token_user_id = (int) ($cp['user_id'] ?? 0);
    $watch = livreur_create_watch_token(null, 'client', null, $token_user_id > 0 ? $token_user_id : null, null, $cp_id);
    if (!$watch || empty($watch['token'])) {
        return false;
    }
    $base = rtrim(get_site_base_url(), '/');
    return $base . '/suivi-livraison.php?cp_id=' . $cp_id . '&token=' . rawurlencode($watch['token']);
}

function livreur_client_peut_suivre_gps_cp(array $cp)
{
    if (empty($cp['livreur_id'])) {
        return false;
    }
    if (livreur_livraison_est_terminee($cp, 'personnalisee')) {
        return false;
    }
    return (int) ($cp['tracking_active'] ?? 0) === 1;
}

function livreur_client_livraison_en_cours_cp(array $cp)
{
    if (empty($cp['livreur_id'])) {
        return false;
    }
    if (livreur_livraison_est_terminee($cp, 'personnalisee')) {
        return false;
    }
    $statut = strtolower(trim((string) ($cp['statut'] ?? '')));
    return in_array($statut, ['livraison_en_cours', 'en_preparation', 'confirmee', 'acceptee'], true);
}

function livreur_client_suivi_map_url_cp($cp_id, $user_id)
{
    return livreur_client_public_suivi_url_cp($cp_id, $user_id, true);
}
