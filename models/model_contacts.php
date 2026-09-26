<?php
/**
 * Modèle pour la gestion des contacts (manuels)
 */
require_once __DIR__ . '/../conn/conn.php';

/**
 * Échappe % et _ pour LIKE (sinon « Chic _package » ne matche jamais le nom réel).
 */
function search_clients_like_escape($value) {
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $value);
}

/**
 * Conditions SQL commune users/contacts : nom, prénom, nom complet, email, téléphone (brut + chiffres).
 *
 * @param string $prefix Préfixe de paramètres PDO (ex. u / c)
 * @return array{0: string, 1: array<string, string>}
 */
function search_clients_build_match_sql($prefix, $recherche) {
    $q = trim((string) $recherche);
    $like = '%' . search_clients_like_escape($q) . '%';
    $digits = preg_replace('/\D/', '', $q);
    $params = [
        $prefix . '_t' => $like,
    ];

    $nameExpr = "CONCAT(COALESCE(prenom,''), ' ', COALESCE(nom,''))";
    $nameExprRev = "CONCAT(COALESCE(nom,''), ' ', COALESCE(prenom,''))";
    $sql = "(
        nom LIKE :{$prefix}_t ESCAPE '\\\\'
        OR prenom LIKE :{$prefix}_t ESCAPE '\\\\'
        OR email LIKE :{$prefix}_t ESCAPE '\\\\'
        OR telephone LIKE :{$prefix}_t ESCAPE '\\\\'
        OR TRIM($nameExpr) LIKE :{$prefix}_t ESCAPE '\\\\'
        OR TRIM($nameExprRev) LIKE :{$prefix}_t ESCAPE '\\\\'
    )";

    if (strlen($digits) >= 3) {
        $params[$prefix . '_d'] = '%' . $digits . '%';
        $sql = "(
            $sql
            OR REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(telephone,''), ' ', ''), '-', ''), '+', ''), '.', '') LIKE :{$prefix}_d
        )";
    }

    return [$sql, $params];
}

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
            list($matchSql, $matchParams) = search_clients_build_match_sql('g', $recherche);
            $sql .= " AND $matchSql";
            $params = $matchParams;
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
            WHERE 1=1' . bl_sql_archived_clause('b', 'active') . '
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
 * Score de pertinence client/contact (nom complet, téléphone, email) — fuzzy / accents / casse.
 * Plus strict que la recherche produit pour éviter les faux positifs sur le carnet (~4k contacts).
 */
function search_clients_row_score($query, array $row) {
    require_once __DIR__ . '/../includes/produit_recherche_fuzzy.php';

    $nom = trim((string) ($row['nom'] ?? ''));
    $prenom = trim((string) ($row['prenom'] ?? ''));
    $full = trim($prenom . ' ' . $nom);
    $fullAlt = trim($nom . ' ' . $prenom);
    $email = trim((string) ($row['email'] ?? ''));
    $tel = (string) ($row['telephone'] ?? '');

    $score = max(
        search_clients_text_score($query, $full),
        search_clients_text_score($query, $fullAlt),
        search_clients_text_score($query, $nom),
        search_clients_text_score($query, $prenom)
    );

    if ($email !== '') {
        $score = max($score, search_clients_text_score($query, $email));
    }

    $qDigits = preg_replace('/\D/', '', (string) $query);
    $telDigits = preg_replace('/\D/', '', $tel);
    if (strlen($qDigits) >= 3 && $telDigits !== '' && strpos($telDigits, $qDigits) !== false) {
        $score = max($score, 850);
    }

    return (int) $score;
}

/**
 * Score texte client : casse/accents ignorés, fautes légères, sans faux positifs sur tokens courts.
 */
function search_clients_text_score($query, $text) {
    require_once __DIR__ . '/../includes/produit_recherche_fuzzy.php';

    $q = produit_recherche_normalize($query);
    $l = produit_recherche_normalize($text);
    if ($q === '' || $l === '') {
        return 0;
    }
    if ($l === $q) {
        return 1000;
    }
    if (strpos($l, $q) === 0) {
        return 850;
    }
    if (strpos($l, $q) !== false) {
        return 700;
    }

    $words = array_values(array_filter(explode(' ', $q), static function ($w) {
        return mb_strlen($w) >= 2 && !ctype_digit($w);
    }));
    if (empty($words)) {
        return 0;
    }

    $tokens = array_values(array_filter(explode(' ', $l), static function ($t) {
        return mb_strlen($t) >= 2 && !ctype_digit($t);
    }));

    $score = 0;
    $matchedWords = 0;
    foreach ($words as $word) {
        $best = 0;
        if (strpos($l, $word) !== false) {
            $best = 120;
        } else {
            foreach ($tokens as $token) {
                if ($token === $word) {
                    $best = max($best, 120);
                    continue;
                }
                if (strlen($word) >= 3 && strpos($token, $word) === 0) {
                    $best = max($best, 110);
                    continue;
                }
                if (strlen($word) >= 4 && strlen($token) >= 4) {
                    if (strpos($token, $word) !== false || strpos($word, $token) !== false) {
                        $best = max($best, 100);
                        continue;
                    }
                    similar_text($word, $token, $pct);
                    if ($pct >= 82) {
                        $best = max($best, (int) round($pct));
                    }
                    if (produit_recherche_levenshtein_ok($word, $token)) {
                        $best = max($best, 90);
                    }
                }
            }
        }
        if ($best > 0) {
            $matchedWords++;
            $score += $best;
        }
    }

    // Exiger que tous les mots significatifs matchent (ex. « jp magnifcat »)
    if ($matchedWords < count($words)) {
        return 0;
    }

    return $score;
}

/**
 * Recherche clients (users + contacts) pour commande manuelle / devis / BL.
 * Fuzzy : casse, accents, fautes légères ; téléphone normalisé ; contacts prioritaires.
 */
function search_clients_for_commande($recherche, $limit = 20) {
    global $db;
    $recherche = trim((string) $recherche);
    if ($recherche === '') {
        return [];
    }

    require_once __DIR__ . '/../includes/produit_recherche_fuzzy.php';
    $limit = max(1, (int) $limit);
    $minScore = 90;

    $users = [];
    $contacts = [];

    try {
        $stmt = $db->query("
            SELECT id, nom, prenom, telephone, email, 'user' AS source,
                   'standard' AS type_client_bl, 0 AS plafond_bl_cumul_ht
            FROM users
            WHERE statut = 'actif'
        ");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        $users = [];
    }

    try {
        $stmt = $db->query("
            SELECT id, nom, prenom, telephone, email, 'contact' AS source,
                   COALESCE(type_client_bl, 'standard') AS type_client_bl,
                   COALESCE(plafond_bl_cumul_ht, 0) AS plafond_bl_cumul_ht
            FROM contacts
        ");
        $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        try {
            $stmt = $db->query("
                SELECT id, nom, prenom, telephone, email, 'contact' AS source,
                       'standard' AS type_client_bl, 0 AS plafond_bl_cumul_ht
                FROM contacts
            ");
            $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e2) {
            $contacts = [];
        }
    }

    $scored = [];
    foreach (array_merge($contacts, $users) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $score = search_clients_row_score($recherche, $row);
        if ($score < $minScore) {
            continue;
        }
        $row['_search_score'] = $score;
        $row['_is_contact'] = (($row['source'] ?? '') === 'contact') ? 1 : 0;
        $scored[] = $row;
    }

    usort($scored, static function ($a, $b) {
        $sa = (int) ($a['_search_score'] ?? 0);
        $sb = (int) ($b['_search_score'] ?? 0);
        if ($sa !== $sb) {
            return $sb <=> $sa;
        }
        $ca = (int) ($a['_is_contact'] ?? 0);
        $cb = (int) ($b['_is_contact'] ?? 0);
        if ($ca !== $cb) {
            return $cb <=> $ca;
        }
        $na = mb_strtolower(trim(($a['prenom'] ?? '') . ' ' . ($a['nom'] ?? '')), 'UTF-8');
        $nb = mb_strtolower(trim(($b['prenom'] ?? '') . ' ' . ($b['nom'] ?? '')), 'UTF-8');
        return strcmp($na, $nb);
    });

    $merged = [];
    $seenPhones = [];
    foreach ($scored as $row) {
        $phoneKey = preg_replace('/\D/', '', (string) ($row['telephone'] ?? ''));
        if ($phoneKey !== '' && isset($seenPhones[$phoneKey])) {
            continue;
        }
        if ($phoneKey !== '') {
            $seenPhones[$phoneKey] = true;
        }
        unset($row['_search_score'], $row['_is_contact']);
        $merged[] = $row;
        if (count($merged) >= $limit) {
            break;
        }
    }

    return $merged;
}
