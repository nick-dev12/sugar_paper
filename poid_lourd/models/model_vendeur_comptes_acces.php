<?php
/**
 * Comptes d'accès collaborateur (vendeur → personne autorisée sur l'espace boutique)
 */
require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/model_admin.php';

if (!function_exists('vendeur_compte_acces_normalize_tel')) {
    function vendeur_compte_acces_normalize_tel($telephone) {
        return preg_replace('/\s+/', '', trim((string) $telephone));
    }
}

if (!function_exists('vendeur_compte_acces_telephone_libre')) {
    /**
     * Téléphone utilisable (pas déjà admin ni autre collaborateur)
     */
    function vendeur_compte_acces_telephone_libre($telephone, $exclude_collaborateur_id = null) {
        global $db;
        $t = vendeur_compte_acces_normalize_tel($telephone);
        if ($t === '') {
            return false;
        }
        if (get_admin_by_telephone($t)) {
            return false;
        }
        try {
            $sql = 'SELECT id FROM vendeur_comptes_acces WHERE telephone = :t';
            $params = ['t' => $t];
            if ($exclude_collaborateur_id !== null && (int) $exclude_collaborateur_id > 0) {
                $sql .= ' AND id != :id';
                $params['id'] = (int) $exclude_collaborateur_id;
            }
            $stmt = $db->prepare($sql . ' LIMIT 1');
            $stmt->execute($params);
            return $stmt->fetch(PDO::FETCH_ASSOC) === false;
        } catch (PDOException $e) {
            return false;
        }
    }
}

if (!function_exists('get_vendeur_compte_acces_by_telephone')) {
    function get_vendeur_compte_acces_by_telephone($telephone) {
        global $db;
        $t = vendeur_compte_acces_normalize_tel($telephone);
        if ($t === '') {
            return false;
        }
        try {
            $stmt = $db->prepare('SELECT * FROM vendeur_comptes_acces WHERE telephone = :t LIMIT 1');
            $stmt->execute(['t' => $t]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: false;
        } catch (PDOException $e) {
            return false;
        }
    }
}

if (!function_exists('get_vendeur_compte_acces_by_telephone_for_unified_login')) {
    /**
     * Même logique que les autres connexions par téléphone : espaces puis chiffres uniquement.
     */
    function get_vendeur_compte_acces_by_telephone_for_unified_login($telephone) {
        $row = get_vendeur_compte_acces_by_telephone($telephone);
        if ($row) {
            return $row;
        }
        global $db;
        $digits = preg_replace('/\D/', '', (string) $telephone);
        if ($digits === '') {
            return false;
        }
        try {
            $stmt = $db->prepare("
                SELECT * FROM vendeur_comptes_acces
                WHERE REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(telephone, ''), ' ', ''), '-', ''), '+', ''), '.', '') = :d
                LIMIT 1
            ");
            $stmt->execute(['d' => $digits]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: false;
        } catch (PDOException $e) {
            return false;
        }
    }
}

if (!function_exists('get_vendeur_comptes_acces_by_vendeur_id')) {
    /**
     * @return array<int, array>
     */
    function get_vendeur_comptes_acces_by_vendeur_id($vendeur_admin_id) {
        global $db;
        $vid = (int) $vendeur_admin_id;
        if ($vid <= 0) {
            return [];
        }
        try {
            $stmt = $db->prepare('
                SELECT id, vendeur_admin_id, nom, telephone, statut, date_creation, derniere_connexion
                FROM vendeur_comptes_acces
                WHERE vendeur_admin_id = :vid
                ORDER BY date_creation DESC
            ');
            $stmt->execute(['vid' => $vid]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            return [];
        }
    }
}

if (!function_exists('get_vendeur_compte_acces_by_id')) {
    function get_vendeur_compte_acces_by_id($id) {
        global $db;
        try {
            $stmt = $db->prepare('SELECT * FROM vendeur_comptes_acces WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => (int) $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: false;
        } catch (PDOException $e) {
            return false;
        }
    }
}

if (!function_exists('create_vendeur_compte_acces')) {
    /**
     * @return int|false id créé
     */
    function create_vendeur_compte_acces($vendeur_admin_id, $nom, $telephone, $password_hash) {
        global $db;
        $t = vendeur_compte_acces_normalize_tel($telephone);
        if ($t === '' || !vendeur_compte_acces_telephone_libre($t)) {
            return false;
        }
        try {
            $stmt = $db->prepare('
                INSERT INTO vendeur_comptes_acces (vendeur_admin_id, nom, telephone, password, statut, date_creation)
                VALUES (:vid, :nom, :tel, :pw, \'actif\', NOW())
            ');
            $ok = $stmt->execute([
                'vid' => (int) $vendeur_admin_id,
                'nom' => trim((string) $nom),
                'tel' => $t,
                'pw' => (string) $password_hash,
            ]);
            return $ok ? (int) $db->lastInsertId() : false;
        } catch (PDOException $e) {
            return false;
        }
    }
}

if (!function_exists('update_vendeur_compte_acces_statut')) {
    function update_vendeur_compte_acces_statut($id, $vendeur_admin_id, $statut) {
        global $db;
        if (!in_array($statut, ['actif', 'inactif'], true)) {
            return false;
        }
        try {
            $stmt = $db->prepare('
                UPDATE vendeur_comptes_acces SET statut = :s
                WHERE id = :id AND vendeur_admin_id = :vid
            ');
            return $stmt->execute([
                's' => $statut,
                'id' => (int) $id,
                'vid' => (int) $vendeur_admin_id,
            ]) && $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }
}

if (!function_exists('update_vendeur_compte_acces_last_login')) {
    function update_vendeur_compte_acces_last_login($id) {
        global $db;
        try {
            $stmt = $db->prepare('UPDATE vendeur_comptes_acces SET derniere_connexion = NOW() WHERE id = :id');
            return $stmt->execute(['id' => (int) $id]);
        } catch (PDOException $e) {
            return false;
        }
    }
}
