<?php
/**
 * Modèle pour la gestion des articles en stock
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../conn/conn.php';

/**
 * Récupère tous les articles en stock
 * @param string|null $recherche Terme de recherche sur le nom (optionnel)
 * @param int|null $categorie_id Filtrer par catégorie (optionnel)
 * @return array Tableau des articles
 */
function get_all_stock_articles($recherche = null, $categorie_id = null)
{
    global $db;

    try {
        $sql = "SELECT s.*, c.nom as categorie_nom FROM stock_articles s LEFT JOIN categories c ON s.categorie_id = c.id WHERE 1=1";
        $params = [];

        if (!empty(trim($recherche ?? ''))) {
            $sql .= " AND s.nom LIKE :term";
            $params['term'] = '%' . trim($recherche) . '%';
        }
        if ($categorie_id !== null && $categorie_id > 0) {
            $sql .= " AND s.categorie_id = :categorie_id";
            $params['categorie_id'] = (int) $categorie_id;
        }

        $sql .= " ORDER BY s.date_creation DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Récupère un article en stock par son ID
 * @param int $id L'ID de l'article
 * @return array|false Les données de l'article ou False
 */
function get_stock_article_by_id($id)
{
    global $db;

    try {
        $stmt = $db->prepare("
            SELECT s.*, c.nom as categorie_nom 
            FROM stock_articles s 
            LEFT JOIN categories c ON s.categorie_id = c.id 
            WHERE s.id = :id
        ");
        $stmt->execute(['id' => (int) $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Recherche des articles en stock (pour sélection produit)
 * @param string $recherche Terme de recherche
 * @param int|null $categorie_id Filtrer par catégorie (optionnel)
 * @param int $limit Nombre max de résultats
 * @return array Tableau des articles
 */
function search_stock_articles($recherche = '', $categorie_id = null, $limit = 50)
{
    global $db;

    try {
        $sql = "SELECT s.*, c.nom as categorie_nom FROM stock_articles s LEFT JOIN categories c ON s.categorie_id = c.id WHERE 1=1";
        $params = ['limit' => (int) $limit];

        if (!empty(trim($recherche))) {
            $sql .= " AND (s.nom LIKE :term OR c.nom LIKE :term2)";
            $params['term'] = '%' . trim($recherche) . '%';
            $params['term2'] = '%' . trim($recherche) . '%';
        }
        if ($categorie_id !== null && $categorie_id > 0) {
            $sql .= " AND s.categorie_id = :categorie_id";
            $params['categorie_id'] = (int) $categorie_id;
        }

        $sql .= " ORDER BY s.nom ASC LIMIT :limit";
        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Crée un nouvel article en stock
 * @param array $data ['nom', 'image_principale', 'quantite', 'categorie_id']
 * @return int|false L'ID créé ou False
 */
function create_stock_article($data)
{
    global $db;

    try {
        $stmt = $db->prepare("
            INSERT INTO stock_articles (nom, image_principale, quantite, categorie_id, date_creation)
            VALUES (:nom, :image_principale, :quantite, :categorie_id, NOW())
        ");
        $stmt->execute([
            'nom' => $data['nom'],
            'image_principale' => $data['image_principale'] ?? null,
            'quantite' => (int) ($data['quantite'] ?? 0),
            'categorie_id' => (int) $data['categorie_id']
        ]);
        return $db->lastInsertId();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Met à jour un article en stock
 * @param int $id L'ID de l'article
 * @param array $data Les nouvelles données
 * @return bool
 */
function update_stock_article($id, $data)
{
    global $db;

    try {
        $sets = "nom = :nom, quantite = :quantite, categorie_id = :categorie_id, date_modification = NOW()";
        $params = [
            'id' => (int) $id,
            'nom' => $data['nom'],
            'quantite' => (int) ($data['quantite'] ?? 0),
            'categorie_id' => (int) $data['categorie_id']
        ];
        if (isset($data['image_principale'])) {
            $sets .= ", image_principale = :image_principale";
            $params['image_principale'] = $data['image_principale'];
        }
        $stmt = $db->prepare("UPDATE stock_articles SET $sets WHERE id = :id");
        return $stmt->execute($params);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Met à jour la quantité d'un article en stock
 * @param int $id L'ID de l'article
 * @param int $quantite Nouvelle quantité
 * @return bool
 */
function update_stock_article_quantite($id, $quantite)
{
    global $db;

    try {
        $stmt = $db->prepare("UPDATE stock_articles SET quantite = :quantite, date_modification = NOW() WHERE id = :id");
        return $stmt->execute(['id' => (int) $id, 'quantite' => (int) $quantite]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Décrémente la quantité d'un article en stock
 * @param int $id L'ID de l'article
 * @param int $quantite Quantité à soustraire
 * @return bool True si succès
 */
function decrement_stock_article($id, $quantite)
{
    global $db;

    try {
        $stmt = $db->prepare("UPDATE stock_articles SET quantite = GREATEST(0, quantite - :qty), date_modification = NOW() WHERE id = :id");
        return $stmt->execute(['id' => (int) $id, 'qty' => (int) $quantite]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Supprime un article en stock (vérifier qu'aucun produit n'est lié)
 * @param int $id L'ID de l'article
 * @return bool
 */
function delete_stock_article($id)
{
    global $db;

    try {
        $stmt = $db->prepare("DELETE FROM stock_articles WHERE id = :id");
        return $stmt->execute(['id' => (int) $id]);
    } catch (PDOException $e) {
        return false;
    }
}
