<?php
/**
 * Modèle — négociations de prix produit (client ↔ vendeur)
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../conn/conn.php';

/**
 * @return bool
 */
function prix_negociations_table_exists()
{
    global $db;

    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $stmt = $db->query("SHOW TABLES LIKE 'prix_negociations'");
        $exists = (bool) $stmt->fetch(PDO::FETCH_NUM);
    } catch (PDOException $e) {
        $exists = false;
    }

    return $exists;
}

/**
 * @param array<string, mixed> $options
 * @return string
 */
function prix_negociation_options_hash($options)
{
    $payload = [
        'variante_id' => (int) ($options['variante_id'] ?? 0),
        'couleur' => trim((string) ($options['couleur'] ?? '')),
        'poids' => trim((string) ($options['poids'] ?? '')),
        'taille' => trim((string) ($options['taille'] ?? '')),
    ];

    return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE));
}

/**
 * @param array<string, mixed> $options
 * @return string
 */
function prix_negociation_options_json_encode($options)
{
    return json_encode([
        'variante_id' => (int) ($options['variante_id'] ?? 0) ?: null,
        'couleur' => trim((string) ($options['couleur'] ?? '')),
        'poids' => trim((string) ($options['poids'] ?? '')),
        'taille' => trim((string) ($options['taille'] ?? '')),
        'variante_nom' => trim((string) ($options['variante_nom'] ?? '')),
        'variante_image' => trim((string) ($options['variante_image'] ?? '')),
        'surcout_poids' => (float) ($options['surcout_poids'] ?? 0),
        'surcout_taille' => (float) ($options['surcout_taille'] ?? 0),
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * @param string|null $json
 * @return array<string, mixed>
 */
function prix_negociation_options_json_decode($json)
{
    if ($json === null || trim((string) $json) === '') {
        return [];
    }
    $dec = json_decode((string) $json, true);
    return is_array($dec) ? $dec : [];
}

/**
 * @param array<string, mixed> $row
 * @return float|null
 */
function prix_negociation_prix_convenu_effectif($row)
{
    if (!is_array($row)) {
        return null;
    }
    $statut = (string) ($row['statut'] ?? '');
    if ($statut === 'acceptee' && isset($row['prix_convenu']) && $row['prix_convenu'] !== null && (float) $row['prix_convenu'] > 0) {
        return (float) $row['prix_convenu'];
    }
    if ($statut === 'contre_proposee' && isset($row['prix_contre_vendeur']) && $row['prix_contre_vendeur'] !== null && (float) $row['prix_contre_vendeur'] > 0) {
        return (float) $row['prix_contre_vendeur'];
    }
    if (isset($row['prix_convenu']) && $row['prix_convenu'] !== null && (float) $row['prix_convenu'] > 0) {
        return (float) $row['prix_convenu'];
    }
    return null;
}

/**
 * @param array<string, mixed> $row
 * @return bool
 */
function prix_negociation_peut_commander($row)
{
    if (!is_array($row)) {
        return false;
    }
    $statut = (string) ($row['statut'] ?? '');
    if (!in_array($statut, ['acceptee', 'contre_proposee'], true)) {
        return false;
    }
    return prix_negociation_prix_convenu_effectif($row) !== null;
}

/**
 * @param int $id
 * @return array<string, mixed>|null
 */
function prix_negociation_get_by_id($id)
{
    global $db;

    $id = (int) $id;
    if ($id <= 0 || !prix_negociations_table_exists()) {
        return null;
    }

    try {
        $stmt = $db->prepare('
            SELECT pn.*,
                   p.nom AS produit_nom,
                   p.image_principale AS produit_image,
                   u.nom AS user_nom,
                   u.prenom AS user_prenom,
                   a.boutique_nom AS vendeur_boutique_nom
            FROM prix_negociations pn
            INNER JOIN produits p ON p.id = pn.produit_id
            INNER JOIN users u ON u.id = pn.user_id
            INNER JOIN admin a ON a.id = pn.admin_id
            WHERE pn.id = :id
            LIMIT 1
        ');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * @param int $user_id
 * @param int $produit_id
 * @param string $options_hash
 * @return array<string, mixed>|null
 */
function prix_negociation_find_by_user_produit_hash($user_id, $produit_id, $options_hash)
{
    global $db;

    $user_id = (int) $user_id;
    $produit_id = (int) $produit_id;
    if ($user_id <= 0 || $produit_id <= 0 || !prix_negociations_table_exists()) {
        return null;
    }

    try {
        $stmt = $db->prepare('
            SELECT * FROM prix_negociations
            WHERE user_id = :user_id AND produit_id = :produit_id AND options_hash = :options_hash
            ORDER BY date_maj DESC
            LIMIT 1
        ');
        $stmt->execute([
            'user_id' => $user_id,
            'produit_id' => $produit_id,
            'options_hash' => (string) $options_hash,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Crée ou met à jour une offre client
 *
 * @param int $user_id
 * @param int $admin_id
 * @param int $produit_id
 * @param array<string, mixed> $options
 * @param float $prix_reference
 * @param float $prix_propose
 * @return array{success:bool, id?:int, message?:string}
 */
function prix_negociation_submit_offer($user_id, $admin_id, $produit_id, $options, $prix_reference, $prix_propose)
{
    global $db;

    $user_id = (int) $user_id;
    $admin_id = (int) $admin_id;
    $produit_id = (int) $produit_id;
    $prix_reference = (float) $prix_reference;
    $prix_propose = (float) $prix_propose;

    if ($user_id <= 0 || $admin_id <= 0 || $produit_id <= 0 || !prix_negociations_table_exists()) {
        return ['success' => false, 'message' => 'Données invalides.'];
    }
    if ($prix_propose <= 0) {
        return ['success' => false, 'message' => 'Indiquez un prix supérieur à 0.'];
    }
    if ($prix_propose >= $prix_reference) {
        return ['success' => false, 'message' => 'Votre offre doit être inférieure au prix affiché.'];
    }

    $variante_id = (int) ($options['variante_id'] ?? 0);
    $options_hash = prix_negociation_options_hash($options);
    $options_json = prix_negociation_options_json_encode($options);

    $existing = prix_negociation_find_by_user_produit_hash($user_id, $produit_id, $options_hash);
    $updatable = $existing && in_array($existing['statut'] ?? '', ['en_attente', 'contre_proposee', 'refusee_finale'], true);

    try {
        if ($updatable) {
            $stmt = $db->prepare('
                UPDATE prix_negociations SET
                    prix_reference = :prix_reference,
                    prix_propose_client = :prix_propose,
                    prix_contre_vendeur = NULL,
                    prix_convenu = NULL,
                    variante_id = :variante_id,
                    options_json = :options_json,
                    statut = \'en_attente\',
                    date_maj = NOW()
                WHERE id = :id AND user_id = :user_id
            ');
            $stmt->execute([
                'prix_reference' => $prix_reference,
                'prix_propose' => $prix_propose,
                'variante_id' => $variante_id > 0 ? $variante_id : null,
                'options_json' => $options_json,
                'id' => (int) $existing['id'],
                'user_id' => $user_id,
            ]);
            return ['success' => true, 'id' => (int) $existing['id']];
        }

        $stmt = $db->prepare('
            INSERT INTO prix_negociations (
                user_id, admin_id, produit_id, variante_id, options_json, options_hash,
                prix_reference, prix_propose_client, statut, date_creation, date_maj
            ) VALUES (
                :user_id, :admin_id, :produit_id, :variante_id, :options_json, :options_hash,
                :prix_reference, :prix_propose, \'en_attente\', NOW(), NOW()
            )
        ');
        $stmt->execute([
            'user_id' => $user_id,
            'admin_id' => $admin_id,
            'produit_id' => $produit_id,
            'variante_id' => $variante_id > 0 ? $variante_id : null,
            'options_json' => $options_json,
            'options_hash' => $options_hash,
            'prix_reference' => $prix_reference,
            'prix_propose' => $prix_propose,
        ]);

        return ['success' => true, 'id' => (int) $db->lastInsertId()];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Impossible d\'enregistrer votre offre.'];
    }
}

/**
 * Regroupe les négociations par produit (dashboard vendeur).
 *
 * @param array<int, array<string, mixed>> $rows
 * @return array<int, array<string, mixed>>
 */
function prix_negociation_group_by_produit($rows)
{
    $groups = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $pid = (int) ($row['produit_id'] ?? 0);
        if ($pid <= 0) {
            continue;
        }
        if (!isset($groups[$pid])) {
            $groups[$pid] = [
                'produit_id' => $pid,
                'produit_nom' => (string) ($row['produit_nom'] ?? 'Produit'),
                'produit_image' => (string) ($row['produit_image'] ?? ''),
                'offres' => [],
                'pending_count' => 0,
            ];
        }
        $groups[$pid]['offres'][] = $row;
        if (($row['statut'] ?? '') === 'en_attente') {
            $groups[$pid]['pending_count']++;
        }
    }

    usort($groups, function ($a, $b) {
        $pa = (int) ($a['pending_count'] ?? 0);
        $pb = (int) ($b['pending_count'] ?? 0);
        if ($pa !== $pb) {
            return $pb <=> $pa;
        }
        return strcmp((string) ($b['produit_nom'] ?? ''), (string) ($a['produit_nom'] ?? ''));
    });

    return array_values($groups);
}

/**
 * @param int $admin_id
 * @param int|null $limit
 * @param int $offset
 * @param array<int, string>|null $statuts
 * @return array<int, array<string, mixed>>
 */
function prix_negociation_list_by_admin($admin_id, $limit = null, $offset = 0, $statuts = null)
{
    global $db;

    $admin_id = (int) $admin_id;
    if ($admin_id <= 0 || !prix_negociations_table_exists()) {
        return [];
    }

    $sql = '
        SELECT pn.*,
               p.nom AS produit_nom,
               p.image_principale AS produit_image,
               u.nom AS user_nom,
               u.prenom AS user_prenom
        FROM prix_negociations pn
        INNER JOIN produits p ON p.id = pn.produit_id
        INNER JOIN users u ON u.id = pn.user_id
        WHERE pn.admin_id = :admin_id
    ';
    $params = ['admin_id' => $admin_id];

    if (is_array($statuts) && !empty($statuts)) {
        $placeholders = [];
        foreach (array_values($statuts) as $i => $st) {
            $key = 'st' . $i;
            $placeholders[] = ':' . $key;
            $params[$key] = $st;
        }
        $sql .= ' AND pn.statut IN (' . implode(', ', $placeholders) . ')';
    }

    $sql .= ' ORDER BY pn.date_maj DESC';

    if ($limit !== null) {
        $sql .= ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
    }

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * @param int $user_id
 * @param int|null $limit
 * @param int $offset
 * @return array<int, array<string, mixed>>
 */
function prix_negociation_list_by_user($user_id, $limit = null, $offset = 0)
{
    global $db;

    $user_id = (int) $user_id;
    if ($user_id <= 0 || !prix_negociations_table_exists()) {
        return [];
    }

    $sql = '
        SELECT pn.*,
               p.nom AS produit_nom,
               p.image_principale AS produit_image,
               a.boutique_nom AS vendeur_boutique_nom
        FROM prix_negociations pn
        INNER JOIN produits p ON p.id = pn.produit_id
        INNER JOIN admin a ON a.id = pn.admin_id
        WHERE pn.user_id = :user_id
        ORDER BY pn.date_maj DESC
    ';

    if ($limit !== null) {
        $sql .= ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
    }

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute(['user_id' => $user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * @param int $id
 * @param int $admin_id
 * @return array{success:bool, message?:string}
 */
function prix_negociation_vendor_accept($id, $admin_id)
{
    global $db;

    $row = prix_negociation_get_by_id($id);
    if (!$row || (int) ($row['admin_id'] ?? 0) !== (int) $admin_id) {
        return ['success' => false, 'message' => 'Offre introuvable.'];
    }
    if (($row['statut'] ?? '') !== 'en_attente') {
        return ['success' => false, 'message' => 'Cette offre n\'est plus en attente.'];
    }

    try {
        $stmt = $db->prepare('
            UPDATE prix_negociations SET
                statut = \'acceptee\',
                prix_convenu = prix_propose_client,
                prix_contre_vendeur = NULL,
                date_maj = NOW()
            WHERE id = :id AND admin_id = :admin_id AND statut = \'en_attente\'
        ');
        $ok = $stmt->execute(['id' => (int) $id, 'admin_id' => (int) $admin_id]);
        return $ok ? ['success' => true] : ['success' => false, 'message' => 'Échec de la validation.'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur serveur.'];
    }
}

/**
 * @param int $id
 * @param int $admin_id
 * @param float $prix_contre
 * @return array{success:bool, message?:string}
 */
function prix_negociation_vendor_counter($id, $admin_id, $prix_contre)
{
    global $db;

    $row = prix_negociation_get_by_id($id);
    if (!$row || (int) ($row['admin_id'] ?? 0) !== (int) $admin_id) {
        return ['success' => false, 'message' => 'Offre introuvable.'];
    }
    if (($row['statut'] ?? '') !== 'en_attente') {
        return ['success' => false, 'message' => 'Cette offre n\'est plus en attente.'];
    }

    $prix_contre = (float) $prix_contre;
    if ($prix_contre <= 0) {
        return ['success' => false, 'message' => 'Indiquez un prix valide.'];
    }

    try {
        $stmt = $db->prepare('
            UPDATE prix_negociations SET
                statut = \'contre_proposee\',
                prix_contre_vendeur = :prix_contre,
                prix_convenu = :prix_contre,
                date_maj = NOW()
            WHERE id = :id AND admin_id = :admin_id AND statut = \'en_attente\'
        ');
        $ok = $stmt->execute([
            'prix_contre' => $prix_contre,
            'id' => (int) $id,
            'admin_id' => (int) $admin_id,
        ]);
        return $ok ? ['success' => true] : ['success' => false, 'message' => 'Échec de la contre-proposition.'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur serveur.'];
    }
}

/**
 * @param int $id
 * @param int $admin_id
 * @return array{success:bool, message?:string}
 */
function prix_negociation_vendor_reject_final($id, $admin_id)
{
    global $db;

    $row = prix_negociation_get_by_id($id);
    if (!$row || (int) ($row['admin_id'] ?? 0) !== (int) $admin_id) {
        return ['success' => false, 'message' => 'Offre introuvable.'];
    }
    if (($row['statut'] ?? '') !== 'en_attente') {
        return ['success' => false, 'message' => 'Cette offre n\'est plus en attente.'];
    }

    try {
        $stmt = $db->prepare('
            UPDATE prix_negociations SET
                statut = \'refusee_finale\',
                prix_contre_vendeur = NULL,
                prix_convenu = NULL,
                date_maj = NOW()
            WHERE id = :id AND admin_id = :admin_id AND statut = \'en_attente\'
        ');
        $ok = $stmt->execute(['id' => (int) $id, 'admin_id' => (int) $admin_id]);
        return $ok ? ['success' => true] : ['success' => false, 'message' => 'Échec du rejet.'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur serveur.'];
    }
}

/**
 * @param int $id
 * @param int $user_id
 * @return array{success:bool, message?:string}
 */
function prix_negociation_client_accept_counter($id, $user_id)
{
    global $db;

    $row = prix_negociation_get_by_id($id);
    if (!$row || (int) ($row['user_id'] ?? 0) !== (int) $user_id) {
        return ['success' => false, 'message' => 'Négociation introuvable.'];
    }
    if (($row['statut'] ?? '') !== 'contre_proposee') {
        return ['success' => false, 'message' => 'Aucune contre-proposition en cours.'];
    }

    try {
        $stmt = $db->prepare('
            UPDATE prix_negociations SET
                statut = \'acceptee\',
                prix_convenu = prix_contre_vendeur,
                date_maj = NOW()
            WHERE id = :id AND user_id = :user_id AND statut = \'contre_proposee\'
        ');
        $ok = $stmt->execute(['id' => (int) $id, 'user_id' => (int) $user_id]);
        return $ok ? ['success' => true] : ['success' => false, 'message' => 'Échec de l\'acceptation.'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur serveur.'];
    }
}

/**
 * @param int $id
 * @param int $user_id
 * @return array{success:bool, message?:string}
 */
function prix_negociation_mark_commandee($id, $user_id)
{
    global $db;

    try {
        $stmt = $db->prepare('
            UPDATE prix_negociations SET statut = \'commandee\', date_maj = NOW()
            WHERE id = :id AND user_id = :user_id
            AND statut IN (\'acceptee\', \'contre_proposee\')
        ');
        $ok = $stmt->execute(['id' => (int) $id, 'user_id' => (int) $user_id]);
        return $ok ? ['success' => true] : ['success' => false, 'message' => 'Impossible de finaliser la négociation.'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur serveur.'];
    }
}

/**
 * @param string $statut
 * @return string
 */
function prix_negociation_statut_label($statut)
{
    $map = [
        'en_attente' => 'En attente',
        'acceptee' => 'Acceptée',
        'contre_proposee' => 'Contre-proposition',
        'refusee_finale' => 'Refusée',
        'commandee' => 'Commandée',
    ];
    return $map[$statut] ?? $statut;
}

/**
 * @param string $statut
 * @return string
 */
function prix_negociation_statut_css_class($statut)
{
    $map = [
        'en_attente' => 'prix-neg-statut--attente',
        'acceptee' => 'prix-neg-statut--acceptee',
        'contre_proposee' => 'prix-neg-statut--contre',
        'refusee_finale' => 'prix-neg-statut--refusee',
        'commandee' => 'prix-neg-statut--commandee',
    ];
    return $map[$statut] ?? 'prix-neg-statut--attente';
}
