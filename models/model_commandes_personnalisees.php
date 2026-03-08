<?php
/**
 * Modèle pour les commandes personnalisées
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../conn/conn.php';

/**
 * Vérifie si la colonne image_reference existe dans commandes_personnalisees
 * @return bool
 */
function _cp_has_image_reference_column() {
    static $has = null;
    if ($has === null) {
        global $db;
        try {
            $r = $db ? $db->query("SHOW COLUMNS FROM commandes_personnalisees LIKE 'image_reference'") : null;
            $has = $r && $r->rowCount() > 0;
        } catch (PDOException $e) {
            $has = false;
        }
    }
    return $has;
}

/**
 * Crée une commande personnalisée
 * @param array $data Les données de la commande
 * @return int|false L'ID créé ou False
 */
function create_commande_personnalisee($data) {
    global $db;
    if (!$db) {
        return false;
    }

    $has_img = _cp_has_image_reference_column();
    $image_ref = $data['image_reference'] ?? null;

    try {
        if ($has_img) {
            $stmt = $db->prepare("
                INSERT INTO commandes_personnalisees 
                (user_id, nom, prenom, email, telephone, description, image_reference, type_produit, quantite, date_souhaitee) 
                VALUES (:user_id, :nom, :prenom, :email, :telephone, :description, :image_reference, :type_produit, :quantite, :date_souhaitee)
            ");
            $stmt->execute([
                'user_id' => $data['user_id'],
                'nom' => $data['nom'],
                'prenom' => $data['prenom'],
                'email' => $data['email'],
                'telephone' => $data['telephone'],
                'description' => $data['description'],
                'image_reference' => $image_ref,
                'type_produit' => $data['type_produit'] ?? null,
                'quantite' => $data['quantite'] ?? null,
                'date_souhaitee' => !empty($data['date_souhaitee']) ? $data['date_souhaitee'] : null
            ]);
        } else {
            $stmt = $db->prepare("
                INSERT INTO commandes_personnalisees 
                (user_id, nom, prenom, email, telephone, description, type_produit, quantite, date_souhaitee) 
                VALUES (:user_id, :nom, :prenom, :email, :telephone, :description, :type_produit, :quantite, :date_souhaitee)
            ");
            $stmt->execute([
                'user_id' => $data['user_id'],
                'nom' => $data['nom'],
                'prenom' => $data['prenom'],
                'email' => $data['email'],
                'telephone' => $data['telephone'],
                'description' => $data['description'],
                'type_produit' => $data['type_produit'] ?? null,
                'quantite' => $data['quantite'] ?? null,
                'date_souhaitee' => !empty($data['date_souhaitee']) ? $data['date_souhaitee'] : null
            ]);
        }
        return $db->lastInsertId();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère toutes les commandes personnalisées
 * @param string|null $statut Filtrer par statut
 * @return array
 */
function get_all_commandes_personnalisees($statut = null) {
    global $db;

    try {
        $sql = "
            SELECT cp.*, u.nom as user_nom, u.prenom as user_prenom, u.email as user_email
            FROM commandes_personnalisees cp
            LEFT JOIN users u ON cp.user_id = u.id
            WHERE 1=1
        ";
        $params = [];
        if ($statut) {
            $sql .= " AND cp.statut = :statut";
            $params['statut'] = $statut;
        }
        $sql .= " ORDER BY cp.date_creation DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Récupère une commande personnalisée par ID
 * @param int $id
 * @return array|false
 */
function get_commande_personnalisee_by_id($id) {
    global $db;

    try {
        $stmt = $db->prepare("
            SELECT cp.*, u.nom as user_nom, u.prenom as user_prenom, u.email as user_email, u.telephone as user_telephone
            FROM commandes_personnalisees cp
            LEFT JOIN users u ON cp.user_id = u.id
            WHERE cp.id = :id
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Met à jour le statut d'une commande personnalisée
 * @param int $id
 * @param string $statut
 * @param string|null $notes_admin
 * @return bool
 */
function update_commande_personnalisee_statut($id, $statut, $notes_admin = null) {
    global $db;

    try {
        $stmt = $db->prepare("
            UPDATE commandes_personnalisees 
            SET statut = :statut, 
                notes_admin = COALESCE(:notes_admin, notes_admin),
                date_modification = NOW()
            WHERE id = :id
        ");
        return $stmt->execute([
            'id' => $id,
            'statut' => $statut,
            'notes_admin' => $notes_admin
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Met à jour les notes admin
 * @param int $id
 * @param string $notes_admin
 * @return bool
 */
function update_commande_personnalisee_notes($id, $notes_admin) {
    global $db;

    try {
        $stmt = $db->prepare("
            UPDATE commandes_personnalisees 
            SET notes_admin = :notes_admin, date_modification = NOW()
            WHERE id = :id
        ");
        return $stmt->execute(['id' => $id, 'notes_admin' => $notes_admin]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Compte les commandes personnalisées par statut
 * @param string|null $statut
 * @return int
 */
function count_commandes_personnalisees_by_statut($statut = null) {
    global $db;

    try {
        if ($statut) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM commandes_personnalisees WHERE statut = :statut");
            $stmt->execute(['statut' => $statut]);
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) FROM commandes_personnalisees");
            $stmt->execute();
        }
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Récupère les commandes personnalisées d'un utilisateur
 * @param int $user_id L'ID de l'utilisateur
 * @param string|null $statut Filtrer par statut (optionnel)
 * @return array
 */
function get_commandes_personnalisees_by_user($user_id, $statut = null) {
    global $db;

    try {
        $sql = "SELECT * FROM commandes_personnalisees WHERE user_id = :user_id";
        $params = ['user_id' => $user_id];
        if ($statut) {
            $sql .= " AND statut = :statut";
            $params['statut'] = $statut;
        }
        $sql .= " ORDER BY date_creation DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Retourne les libellés des statuts
 * @return array
 */
function get_statuts_commande_personnalisee() {
    return [
        'en_attente' => 'En attente',
        'confirmee' => 'Confirmée',
        'en_preparation' => 'En préparation',
        'devis_envoye' => 'Devis envoyé',
        'acceptee' => 'Acceptée',
        'refusee' => 'Refusée',
        'terminee' => 'Terminée',
        'annulee' => 'Annulée'
    ];
}
