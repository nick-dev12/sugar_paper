<?php
/**
 * Modèle pour la gestion des contacts (manuels)
 */
require_once __DIR__ . '/../conn/conn.php';

/**
 * Récupère tous les contacts
 * @param string|null $recherche Recherche sur nom, prénom, téléphone
 * @return array
 */
function get_all_contacts($recherche = null) {
    global $db;
    try {
        $sql = "SELECT * FROM contacts WHERE 1=1";
        $params = [];
        if (!empty(trim($recherche ?? ''))) {
            $term = '%' . trim($recherche) . '%';
            $sql .= " AND (nom LIKE :term OR prenom LIKE :term2 OR telephone LIKE :term3 OR email LIKE :term4)";
            $params = ['term' => $term, 'term2' => $term, 'term3' => $term, 'term4' => $term];
        }
        $sql .= " ORDER BY nom ASC, prenom ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Récupère un contact par téléphone (normalisé)
 */
function get_contact_by_telephone($telephone) {
    global $db;
    $tel = preg_replace('/\D/', '', $telephone);
    if (empty($tel)) return false;
    try {
        $stmt = $db->prepare("SELECT * FROM contacts WHERE REPLACE(REPLACE(REPLACE(telephone, ' ', ''), '-', ''), '+', '') LIKE :tel");
        $stmt->execute(['tel' => '%' . $tel . '%']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Vérifie si un téléphone existe (users ou contacts)
 */
function telephone_exists_in_users_or_contacts($telephone) {
    global $db;
    $tel = preg_replace('/\D/', '', $telephone);
    if (empty($tel) || strlen($tel) < 8) return false;
    try {
        $stmt = $db->prepare("
            SELECT 1 FROM users WHERE REPLACE(REPLACE(REPLACE(COALESCE(telephone,''), ' ', ''), '-', ''), '+', '') LIKE :tel
            UNION ALL
            SELECT 1 FROM contacts WHERE REPLACE(REPLACE(REPLACE(COALESCE(telephone,''), ' ', ''), '-', ''), '+', '') LIKE :tel2
            LIMIT 1
        ");
        $stmt->execute(['tel' => '%' . $tel . '%', 'tel2' => '%' . $tel . '%']);
        return (bool) $stmt->fetch();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère un contact par ID
 */
function get_contact_by_id($id) {
    global $db;
    try {
        $stmt = $db->prepare("SELECT * FROM contacts WHERE id = :id");
        $stmt->execute(['id' => (int) $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Met à jour un contact
 */
function update_contact($id, $nom, $prenom, $telephone, $email = null) {
    global $db;
    try {
        $stmt = $db->prepare("UPDATE contacts SET nom = :nom, prenom = :prenom, telephone = :telephone, email = :email WHERE id = :id");
        return $stmt->execute([
            'id' => (int) $id,
            'nom' => trim($nom),
            'prenom' => trim($prenom),
            'telephone' => trim($telephone),
            'email' => $email && trim($email) !== '' ? trim($email) : null
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Crée un contact
 */
function create_contact($nom, $prenom, $telephone, $email = null) {
    global $db;
    try {
        $stmt = $db->prepare("INSERT INTO contacts (nom, prenom, telephone, email) VALUES (:nom, :prenom, :telephone, :email)");
        $stmt->execute([
            'nom' => trim($nom),
            'prenom' => trim($prenom),
            'telephone' => trim($telephone),
            'email' => $email && trim($email) !== '' ? trim($email) : null
        ]);
        return $db->lastInsertId();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Importe une liste de contacts (téléphone ou fichier).
 * Ignore les doublons de téléphone déjà présents.
 *
 * @param list<array<string, mixed>> $rows
 * @return array{imported:int,skipped:int,invalid:int}
 */
function import_contacts_from_array(array $rows)
{
    $imported = 0;
    $skipped = 0;
    $invalid = 0;

    foreach ($rows as $c) {
        if (!is_array($c)) {
            $invalid++;
            continue;
        }
        $nom = trim((string) ($c['nom'] ?? $c['name'] ?? ''));
        $prenom = trim((string) ($c['prenom'] ?? ''));
        $tel = trim((string) ($c['telephone'] ?? $c['tel'] ?? $c['phone'] ?? ''));
        $email_raw = trim((string) ($c['email'] ?? ''));
        $email = $email_raw !== '' ? $email_raw : null;

        if ($tel === '') {
            $invalid++;
            continue;
        }
        if (get_contact_by_telephone($tel)) {
            $skipped++;
            continue;
        }
        if ($nom === '') {
            $nom = $prenom !== '' ? $prenom : 'Sans nom';
            if ($prenom !== '' && $nom === $prenom) {
                $prenom = '';
            }
        }
        if (create_contact($nom, $prenom, $tel, $email)) {
            $imported++;
        } else {
            $invalid++;
        }
    }

    return [
        'imported' => $imported,
        'skipped' => $skipped,
        'invalid' => $invalid,
    ];
}

/**
 * Carnet contacts : crée le contact si le numéro n'existe pas (devis / BL).
 */
/**
 * Clé de correspondance téléphone (9 derniers chiffres si disponibles).
 */
function contacts_telephone_lookup_key($telephone)
{
    $digits = preg_replace('/\D+/', '', (string) $telephone);
    if ($digits === '') {
        return '';
    }
    if (strlen($digits) >= 9) {
        return substr($digits, -9);
    }
    return $digits;
}

/**
 * Index des stats factures BL par téléphone client B2B (correspondance souple).
 *
 * @return array<string, array{nb_factures:int, montant_paye:float}>
 */
function build_factures_stats_by_contact_telephone()
{
    require_once __DIR__ . '/model_bl.php';
    if (!bl_tables_available()) {
        return [];
    }
    global $db;
    $stats = [];
    try {
        $stmt = $db->query('
            SELECT b.*, c.telephone AS client_telephone
            FROM bons_livraison b
            INNER JOIN clients_b2b c ON c.id = b.client_b2b_id
        ');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('[build_factures_stats_by_contact_telephone] ' . $e->getMessage());
        return [];
    }
    foreach ($rows as $row) {
        $row = bl_row_apply_statut_bl($row);
        $key = contacts_telephone_lookup_key($row['client_telephone'] ?? '');
        if ($key === '') {
            continue;
        }
        if (!isset($stats[$key])) {
            $stats[$key] = ['nb_factures' => 0, 'montant_paye' => 0.0];
        }
        $stats[$key]['nb_factures']++;
        if (bl_est_facture_payee($row)) {
            $stats[$key]['montant_paye'] += bl_montant_facture_affichage($row);
        }
    }
    return $stats;
}

/**
 * Enrichit les contacts avec le nombre de factures et le total payé (appariement téléphone).
 *
 * @param list<array<string, mixed>> $contacts
 * @return list<array<string, mixed>>
 */
function enrich_contacts_with_factures_stats(array $contacts)
{
    $index = build_factures_stats_by_contact_telephone();
    foreach ($contacts as $i => $contact) {
        $key = contacts_telephone_lookup_key($contact['telephone'] ?? '');
        $s = ($key !== '' && isset($index[$key])) ? $index[$key] : ['nb_factures' => 0, 'montant_paye' => 0.0];
        $contacts[$i]['nb_factures'] = (int) $s['nb_factures'];
        $contacts[$i]['montant_paye'] = (float) $s['montant_paye'];
    }
    return $contacts;
}

function ensure_contact_from_bl($nom, $prenom, $telephone, $email = null) {
    $telephone = trim($telephone ?? '');
    if ($telephone === '') {
        return false;
    }
    $existing = get_contact_by_telephone($telephone);
    if ($existing) {
        return (int) $existing['id'];
    }
    $id = create_contact(
        trim($nom ?? ''),
        trim($prenom ?? ''),
        $telephone,
        $email && trim($email) !== '' ? trim($email) : null
    );
    return $id ? (int) $id : false;
}

function contacts_normalize_type_bl($code) {
    return (($code ?? '') === 'vip') ? 'vip' : 'standard';
}

/**
 * Recherche clients (users + contacts) pour commande manuelle
 */
function search_clients_for_commande($recherche, $limit = 20) {
    global $db;
    $term = '%' . trim($recherche) . '%';
    if (strlen(trim($recherche)) < 1) {
        return [];
    }
    try {
        $stmt = $db->prepare("
            (SELECT id, nom, prenom, telephone, email, 'user' AS source, 'standard' AS type_client_bl, 0 AS plafond_bl_cumul_ht FROM users WHERE statut = 'actif' AND (nom LIKE :t1 OR prenom LIKE :t2 OR email LIKE :t3 OR telephone LIKE :t4))
            UNION ALL
            (SELECT id, nom, prenom, telephone, email, 'contact' AS source,
                COALESCE(type_client_bl, 'standard') AS type_client_bl,
                COALESCE(plafond_bl_cumul_ht, 0) AS plafond_bl_cumul_ht
                FROM contacts WHERE nom LIKE :t5 OR prenom LIKE :t6 OR email LIKE :t7 OR telephone LIKE :t8)
            LIMIT :limit
        ");
        $stmt->bindValue('t1', $term, PDO::PARAM_STR);
        $stmt->bindValue('t2', $term, PDO::PARAM_STR);
        $stmt->bindValue('t3', $term, PDO::PARAM_STR);
        $stmt->bindValue('t4', $term, PDO::PARAM_STR);
        $stmt->bindValue('t5', $term, PDO::PARAM_STR);
        $stmt->bindValue('t6', $term, PDO::PARAM_STR);
        $stmt->bindValue('t7', $term, PDO::PARAM_STR);
        $stmt->bindValue('t8', $term, PDO::PARAM_STR);
        $stmt->bindValue('limit', (int) $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        try {
            $stmt = $db->prepare("
                (SELECT id, nom, prenom, telephone, email, 'user' AS source, 'standard' AS type_client_bl FROM users WHERE statut = 'actif' AND (nom LIKE :t1 OR prenom LIKE :t2 OR email LIKE :t3 OR telephone LIKE :t4))
                UNION ALL
                (SELECT id, nom, prenom, telephone, email, 'contact' AS source, 'standard' AS type_client_bl FROM contacts WHERE nom LIKE :t5 OR prenom LIKE :t6 OR email LIKE :t7 OR telephone LIKE :t8)
                LIMIT :limit
            ");
            $stmt->bindValue('t1', $term, PDO::PARAM_STR);
            $stmt->bindValue('t2', $term, PDO::PARAM_STR);
            $stmt->bindValue('t3', $term, PDO::PARAM_STR);
            $stmt->bindValue('t4', $term, PDO::PARAM_STR);
            $stmt->bindValue('t5', $term, PDO::PARAM_STR);
            $stmt->bindValue('t6', $term, PDO::PARAM_STR);
            $stmt->bindValue('t7', $term, PDO::PARAM_STR);
            $stmt->bindValue('t8', $term, PDO::PARAM_STR);
            $stmt->bindValue('limit', (int) $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e2) {
            return [];
        }
    }
}
