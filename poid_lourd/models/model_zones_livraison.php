<?php
/**
 * Modèle pour la gestion des zones de livraison
 */

require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/../includes/db_schema_helpers.php';

/**
 * @param string|null $statut
 * @param int|false|null $boutique_admin_id false = toutes zones (back-office admin) ; null = plateforme ; int = vendeur
 */
function get_all_zones_livraison($statut = 'actif', $boutique_admin_id = false) {
    global $db;
    try {
        $where = [];
        $params = [];
        if ($statut) {
            $where[] = 'statut = :statut';
            $params['statut'] = $statut;
        }
        if (db_table_has_column('zones_livraison', 'admin_id') && $boutique_admin_id !== false) {
            if ($boutique_admin_id === null || (int) $boutique_admin_id === 0) {
                $where[] = 'admin_id IS NULL';
            } else {
                $where[] = 'admin_id = :aid';
                $params['aid'] = (int) $boutique_admin_id;
            }
        }
        $sql = 'SELECT * FROM zones_livraison';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY ville ASC, quartier ASC';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $zones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $zones ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

function get_zone_livraison_by_id($id) {
    global $db;
    try {
        $stmt = $db->prepare('SELECT * FROM zones_livraison WHERE id = :id');
        $stmt->execute(['id' => (int) $id]);
        $zone = $stmt->fetch(PDO::FETCH_ASSOC);
        return $zone ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * @param int|false|null $owner_admin_id false = sans filtre propriétaire ; null = plateforme ; int = vendeur
 */
function zone_livraison_exists($ville, $quartier, $exclude_id = null, $owner_admin_id = false) {
    global $db;
    try {
        $sql = 'SELECT id FROM zones_livraison WHERE ville = :ville AND quartier = :quartier';
        $params = ['ville' => trim($ville), 'quartier' => trim($quartier)];
        if ($exclude_id !== null) {
            $sql .= ' AND id != :exclude_id';
            $params['exclude_id'] = (int) $exclude_id;
        }
        if (db_table_has_column('zones_livraison', 'admin_id') && $owner_admin_id !== false) {
            if ($owner_admin_id === null || (int) $owner_admin_id === 0) {
                $sql .= ' AND admin_id IS NULL';
            } else {
                $sql .= ' AND admin_id = :oid';
                $params['oid'] = (int) $owner_admin_id;
            }
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetch();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * @param int|null $admin_id Propriétaire (null = plateforme)
 */
function create_zone_livraison($ville, $quartier, $prix_livraison, $description = null, $admin_id = null) {
    global $db;
    $ville = trim($ville);
    $quartier = trim($quartier);
    $prix_livraison = (float) $prix_livraison;
    if (empty($ville) || empty($quartier)) {
        return ['success' => false, 'message' => 'La ville et le quartier sont obligatoires.'];
    }
    if ($prix_livraison < 0) {
        return ['success' => false, 'message' => 'Le prix de livraison doit être positif ou nul.'];
    }
    $scope = $admin_id !== null && (int) $admin_id > 0 ? (int) $admin_id : null;
    if (zone_livraison_exists($ville, $quartier, null, $scope === null ? null : $scope)) {
        return ['success' => false, 'message' => 'Cette zone (ville + quartier) existe déjà.'];
    }
    try {
        if (db_table_has_column('zones_livraison', 'admin_id')) {
            $stmt = $db->prepare('
                INSERT INTO zones_livraison (ville, quartier, prix_livraison, description, statut, admin_id)
                VALUES (:ville, :quartier, :prix_livraison, :description, \'actif\', :admin_id)
            ');
            $stmt->execute([
                'ville' => $ville,
                'quartier' => $quartier,
                'prix_livraison' => $prix_livraison,
                'description' => $description ? trim($description) : null,
                'admin_id' => $scope,
            ]);
        } else {
            $stmt = $db->prepare('
                INSERT INTO zones_livraison (ville, quartier, prix_livraison, description, statut)
                VALUES (:ville, :quartier, :prix_livraison, :description, \'actif\')
            ');
            $stmt->execute([
                'ville' => $ville,
                'quartier' => $quartier,
                'prix_livraison' => $prix_livraison,
                'description' => $description ? trim($description) : null,
            ]);
        }
        return ['success' => true, 'id' => (int) $db->lastInsertId(), 'message' => 'Zone ajoutée avec succès.'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur lors de l\'enregistrement.'];
    }
}

/**
 * @param int|false|null $scope_admin Pour WHERE : vendeur = id ; null = lignes plateforme ; false = super-admin (pas de filtre admin_id)
 */
function update_zone_livraison($id, $ville, $quartier, $prix_livraison, $description = null, $statut = 'actif', $scope_admin = false) {
    global $db;
    $ville = trim($ville);
    $quartier = trim($quartier);
    $prix_livraison = (float) $prix_livraison;
    $id = (int) $id;
    $zone = get_zone_livraison_by_id($id);
    if (!$zone) {
        return ['success' => false, 'message' => 'Zone introuvable.'];
    }
    if (empty($ville) || empty($quartier)) {
        return ['success' => false, 'message' => 'La ville et le quartier sont obligatoires.'];
    }
    if ($prix_livraison < 0) {
        return ['success' => false, 'message' => 'Le prix de livraison doit être positif ou nul.'];
    }
    $dup_scope = false;
    if (db_table_has_column('zones_livraison', 'admin_id')) {
        if ($scope_admin !== false && (int) $scope_admin > 0) {
            $dup_scope = (int) $scope_admin;
        } elseif ($scope_admin === null) {
            $dup_scope = null;
        } else {
            $aid = $zone['admin_id'] ?? null;
            $dup_scope = ($aid !== null && $aid !== '') ? (int) $aid : null;
        }
    }
    if (zone_livraison_exists($ville, $quartier, $id, $dup_scope)) {
        return ['success' => false, 'message' => 'Cette zone (ville + quartier) existe déjà.'];
    }
    try {
        $sql = '
            UPDATE zones_livraison
            SET ville = :ville, quartier = :quartier, prix_livraison = :prix_livraison,
                description = :description, statut = :statut, date_modification = NOW()
            WHERE id = :id';
        $params = [
            'id' => $id,
            'ville' => $ville,
            'quartier' => $quartier,
            'prix_livraison' => $prix_livraison,
            'description' => $description ? trim($description) : null,
            'statut' => in_array($statut, ['actif', 'inactif'], true) ? $statut : 'actif',
        ];
        if (db_table_has_column('zones_livraison', 'admin_id') && $scope_admin !== false && (int) $scope_admin > 0) {
            $sql .= ' AND admin_id = :scope';
            $params['scope'] = (int) $scope_admin;
        } elseif (db_table_has_column('zones_livraison', 'admin_id') && $scope_admin === null) {
            $sql .= ' AND admin_id IS NULL';
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Zone modifiée avec succès.'];
        }
        return ['success' => false, 'message' => 'Zone introuvable ou non autorisée.'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur lors de la modification.'];
    }
}

function delete_zone_livraison($id, $scope_admin = false) {
    global $db;
    $id = (int) $id;
    try {
        $sql = 'DELETE FROM zones_livraison WHERE id = :id';
        $params = ['id' => $id];
        if (db_table_has_column('zones_livraison', 'admin_id') && $scope_admin !== false && $scope_admin !== null && (int) $scope_admin > 0) {
            $sql .= ' AND admin_id = :scope';
            $params['scope'] = (int) $scope_admin;
        } elseif (db_table_has_column('zones_livraison', 'admin_id') && $scope_admin !== false && $scope_admin === null) {
            $sql .= ' AND admin_id IS NULL';
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Zone supprimée.'];
        }
        return ['success' => false, 'message' => 'Zone introuvable ou non autorisée.'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur lors de la suppression.'];
    }
}
