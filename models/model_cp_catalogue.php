<?php
/**
 * Modèle catalogue commandes personnalisées (dossiers + produits)
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../conn/conn.php';

function cp_catalogue_tables_available() {
    static $ok = null;
    if ($ok === null) {
        global $db;
        try {
            $r = $db ? $db->query("SHOW TABLES LIKE 'cp_catalogue_dossiers'") : null;
            $ok = $r && $r->fetchColumn();
        } catch (PDOException $e) {
            $ok = false;
        }
    }
    return (bool) $ok;
}

function get_all_cp_dossiers($statut = null) {
    global $db;
    if (!$db || !cp_catalogue_tables_available()) {
        return [];
    }
    try {
        $sql = "
            SELECT d.*,
                (SELECT COUNT(*) FROM cp_catalogue_produits p WHERE p.dossier_id = d.id) AS nb_produits
            FROM cp_catalogue_dossiers d
        ";
        $params = [];
        if ($statut !== null) {
            $sql .= " WHERE d.statut = :statut";
            $params['statut'] = $statut;
        }
        $sql .= " ORDER BY d.position ASC, d.nom ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

function get_cp_dossier_by_id($id) {
    global $db;
    if (!$db || !cp_catalogue_tables_available() || (int) $id <= 0) {
        return false;
    }
    try {
        $stmt = $db->prepare("
            SELECT d.*,
                (SELECT COUNT(*) FROM cp_catalogue_produits p WHERE p.dossier_id = d.id) AS nb_produits
            FROM cp_catalogue_dossiers d
            WHERE d.id = :id
        ");
        $stmt->execute(['id' => (int) $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

function count_cp_produits_in_dossier($dossier_id) {
    global $db;
    if (!$db || !cp_catalogue_tables_available()) {
        return 0;
    }
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM cp_catalogue_produits WHERE dossier_id = :id");
        $stmt->execute(['id' => (int) $dossier_id]);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

function create_cp_dossier($nom, $position, $statut = 'actif') {
    global $db;
    if (!$db || !cp_catalogue_tables_available()) {
        return false;
    }
    try {
        $stmt = $db->prepare("
            INSERT INTO cp_catalogue_dossiers (nom, position, statut, date_creation)
            VALUES (:nom, :position, :statut, NOW())
        ");
        $stmt->execute([
            'nom' => $nom,
            'position' => (int) $position,
            'statut' => $statut === 'inactif' ? 'inactif' : 'actif',
        ]);
        return (int) $db->lastInsertId();
    } catch (PDOException $e) {
        return false;
    }
}

function update_cp_dossier($id, $nom, $position, $statut = null) {
    global $db;
    if (!$db || !cp_catalogue_tables_available() || (int) $id <= 0) {
        return false;
    }
    $sets = 'nom = :nom, position = :position';
    $params = [
        'id' => (int) $id,
        'nom' => $nom,
        'position' => (int) $position,
    ];
    if ($statut !== null) {
        $sets .= ', statut = :statut';
        $params['statut'] = $statut === 'inactif' ? 'inactif' : 'actif';
    }
    try {
        $stmt = $db->prepare("UPDATE cp_catalogue_dossiers SET {$sets} WHERE id = :id");
        return $stmt->execute($params);
    } catch (PDOException $e) {
        return false;
    }
}

function delete_cp_dossier($id) {
    global $db;
    if (!$db || !cp_catalogue_tables_available() || (int) $id <= 0) {
        return ['success' => false, 'message' => 'Dossier introuvable.'];
    }
    if (count_cp_produits_in_dossier($id) > 0) {
        return ['success' => false, 'message' => 'Impossible de supprimer un dossier contenant des produits.'];
    }
    try {
        $stmt = $db->prepare("DELETE FROM cp_catalogue_dossiers WHERE id = :id");
        $ok = $stmt->execute(['id' => (int) $id]);
        return $ok
            ? ['success' => true, 'message' => 'Dossier supprimé.']
            : ['success' => false, 'message' => 'Erreur lors de la suppression.'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur lors de la suppression.'];
    }
}

function get_cp_produits_by_dossier($dossier_id, $statut = null) {
    global $db;
    if (!$db || !cp_catalogue_tables_available() || (int) $dossier_id <= 0) {
        return [];
    }
    try {
        $sql = "SELECT * FROM cp_catalogue_produits WHERE dossier_id = :dossier_id";
        $params = ['dossier_id' => (int) $dossier_id];
        if ($statut !== null) {
            $sql .= " AND statut = :statut";
            $params['statut'] = $statut;
        }
        $sql .= " ORDER BY position ASC, nom ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

function get_cp_produit_by_id($id, $actif_only = false) {
    global $db;
    if (!$db || !cp_catalogue_tables_available() || (int) $id <= 0) {
        return false;
    }
    try {
        $sql = "
            SELECT p.*, d.nom AS dossier_nom
            FROM cp_catalogue_produits p
            INNER JOIN cp_catalogue_dossiers d ON d.id = p.dossier_id
            WHERE p.id = :id
        ";
        if ($actif_only) {
            $sql .= " AND p.statut = 'actif' AND d.statut = 'actif'";
        }
        $stmt = $db->prepare($sql);
        $stmt->execute(['id' => (int) $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

function get_next_cp_produit_position($dossier_id) {
    global $db;
    if (!$db || !cp_catalogue_tables_available()) {
        return 1;
    }
    try {
        $stmt = $db->prepare("SELECT COALESCE(MAX(position), 0) + 1 FROM cp_catalogue_produits WHERE dossier_id = :id");
        $stmt->execute(['id' => (int) $dossier_id]);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 1;
    }
}

function create_cp_produit($data) {
    global $db;
    if (!$db || !cp_catalogue_tables_available()) {
        return false;
    }
    $dossier_id = (int) ($data['dossier_id'] ?? 0);
    if ($dossier_id <= 0 || empty($data['nom']) || empty($data['image'])) {
        return false;
    }
    $position = isset($data['position']) ? (int) $data['position'] : get_next_cp_produit_position($dossier_id);
    try {
        $stmt = $db->prepare("
            INSERT INTO cp_catalogue_produits
            (dossier_id, nom, image, prix_min, prix_max, position, statut, date_creation)
            VALUES (:dossier_id, :nom, :image, :prix_min, :prix_max, :position, :statut, NOW())
        ");
        $stmt->execute([
            'dossier_id' => $dossier_id,
            'nom' => $data['nom'],
            'image' => $data['image'],
            'prix_min' => (float) ($data['prix_min'] ?? 0),
            'prix_max' => (float) ($data['prix_max'] ?? 0),
            'position' => $position,
            'statut' => ($data['statut'] ?? 'actif') === 'inactif' ? 'inactif' : 'actif',
        ]);
        return (int) $db->lastInsertId();
    } catch (PDOException $e) {
        return false;
    }
}

function update_cp_produit($id, $data) {
    global $db;
    if (!$db || !cp_catalogue_tables_available() || (int) $id <= 0) {
        return false;
    }
    $sets = ['nom = :nom', 'prix_min = :prix_min', 'prix_max = :prix_max'];
    $params = [
        'id' => (int) $id,
        'nom' => $data['nom'],
        'prix_min' => (float) ($data['prix_min'] ?? 0),
        'prix_max' => (float) ($data['prix_max'] ?? 0),
    ];
    if (!empty($data['image'])) {
        $sets[] = 'image = :image';
        $params['image'] = $data['image'];
    }
    if (isset($data['statut'])) {
        $sets[] = 'statut = :statut';
        $params['statut'] = $data['statut'] === 'inactif' ? 'inactif' : 'actif';
    }
    try {
        $stmt = $db->prepare("UPDATE cp_catalogue_produits SET " . implode(', ', $sets) . " WHERE id = :id");
        return $stmt->execute($params);
    } catch (PDOException $e) {
        return false;
    }
}

function delete_cp_produit($id) {
    global $db;
    if (!$db || !cp_catalogue_tables_available() || (int) $id <= 0) {
        return false;
    }
    try {
        $stmt = $db->prepare("DELETE FROM cp_catalogue_produits WHERE id = :id");
        return $stmt->execute(['id' => (int) $id]);
    } catch (PDOException $e) {
        return false;
    }
}

function get_cp_catalogue_grouped($statut = 'actif') {
    $dossiers = get_all_cp_dossiers($statut);
    $grouped = [];
    foreach ($dossiers as $dossier) {
        $produits = get_cp_produits_by_dossier((int) $dossier['id'], $statut);
        if (empty($produits)) {
            continue;
        }
        $dossier['produits'] = $produits;
        $grouped[] = $dossier;
    }
    return $grouped;
}

function get_cp_catalogue_flat_products($statut = 'actif') {
    $grouped = get_cp_catalogue_grouped($statut);
    $flat = [];
    foreach ($grouped as $dossier) {
        foreach ($dossier['produits'] as $produit) {
            $produit['dossier_id'] = (int) $dossier['id'];
            $produit['dossier_nom'] = $dossier['nom'];
            $flat[] = $produit;
        }
    }
    return $flat;
}

function get_cp_catalogue_price_bounds() {
    global $db;
    if (!$db || !cp_catalogue_tables_available()) {
        return ['min' => 0, 'max' => 0];
    }
    try {
        $stmt = $db->query("
            SELECT MIN(prix_min) AS pmin, MAX(prix_max) AS pmax
            FROM cp_catalogue_produits
            WHERE statut = 'actif'
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'min' => (float) ($row['pmin'] ?? 0),
            'max' => (float) ($row['pmax'] ?? 0),
        ];
    } catch (PDOException $e) {
        return ['min' => 0, 'max' => 0];
    }
}
