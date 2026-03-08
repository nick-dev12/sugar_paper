<?php
/**
 * Modèle pour les mouvements de stock (entrées, sorties, inventaires)
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../conn/conn.php';

/**
 * Enregistre un mouvement de stock
 * @param array $data ['type', 'stock_article_id'?, 'produit_id'?, 'quantite', 'quantite_avant'?, 'quantite_apres'?, 'reference_type'?, 'reference_id'?, 'reference_numero'?, 'notes'?]
 * @return int|false ID du mouvement ou False
 */
function create_stock_mouvement($data)
{
    global $db;

    try {
        $stmt = $db->prepare("
            INSERT INTO stock_mouvements (
                type, stock_article_id, produit_id, quantite, quantite_avant, quantite_apres,
                reference_type, reference_id, reference_numero, date_mouvement, notes
            ) VALUES (
                :type, :stock_article_id, :produit_id, :quantite, :quantite_avant, :quantite_apres,
                :reference_type, :reference_id, :reference_numero, NOW(), :notes
            )
        ");
        $stmt->execute([
            'type' => $data['type'],
            'stock_article_id' => $data['stock_article_id'] ?? null,
            'produit_id' => $data['produit_id'] ?? null,
            'quantite' => (int) $data['quantite'],
            'quantite_avant' => isset($data['quantite_avant']) ? (int) $data['quantite_avant'] : null,
            'quantite_apres' => isset($data['quantite_apres']) ? (int) $data['quantite_apres'] : null,
            'reference_type' => $data['reference_type'] ?? null,
            'reference_id' => $data['reference_id'] ?? null,
            'reference_numero' => $data['reference_numero'] ?? null,
            'notes' => $data['notes'] ?? null
        ]);
        return $db->lastInsertId();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère les mouvements avec filtres
 * @param int|null $stock_article_id Filtrer par article
 * @param int|null $produit_id Filtrer par produit
 * @param string|null $type Filtrer par type (entree, sortie, inventaire)
 * @param int $limit Nombre max
 * @return array
 */
function get_stock_mouvements($stock_article_id = null, $produit_id = null, $type = null, $limit = 100)
{
    global $db;

    try {
        $sql = "SELECT m.*, s.nom as article_nom, p.nom as produit_nom
                FROM stock_mouvements m
                LEFT JOIN stock_articles s ON m.stock_article_id = s.id
                LEFT JOIN produits p ON m.produit_id = p.id
                WHERE 1=1";
        $params = ['limit' => (int) $limit];

        if ($stock_article_id !== null && $stock_article_id > 0) {
            $sql .= " AND m.stock_article_id = :stock_article_id";
            $params['stock_article_id'] = (int) $stock_article_id;
        }
        if ($produit_id !== null && $produit_id > 0) {
            $sql .= " AND m.produit_id = :produit_id";
            $params['produit_id'] = (int) $produit_id;
        }
        if ($type !== null && in_array($type, ['entree', 'sortie', 'inventaire'])) {
            $sql .= " AND m.type = :type";
            $params['type'] = $type;
        }

        $sql .= " ORDER BY m.date_mouvement DESC LIMIT :limit";
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
