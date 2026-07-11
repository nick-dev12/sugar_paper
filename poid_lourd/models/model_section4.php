<?php
/**
 * Modèle pour la gestion de la configuration de la section4
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/../includes/db_schema_helpers.php';

/**
 * Configuration par défaut si aucune ligne (ou vendeur sans config).
 */
function section4_default_config_row() {
    return [
        'id' => 0,
        'titre' => '',
        'texte' => '',
        'image_fond' => '',
        'statut' => 'inactif',
        'date_modification' => date('Y-m-d H:i:s'),
    ];
}

/**
 * Récupère la configuration section4.
 * @param int|null $boutique_admin_id ID vendeur (vitrine) ; null = contenu plateforme (admin_id NULL)
 */
function get_section4_config($boutique_admin_id = null) {
    global $db;

    if (!$db) {
        return section4_default_config_row();
    }

    if (!db_table_has_column('section4_config', 'admin_id')) {
        try {
            $stmt = $db->prepare('SELECT * FROM section4_config ORDER BY id DESC LIMIT 1');
            $stmt->execute();
            $config = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$config) {
                return array_merge(section4_default_config_row(), [
                    'titre' => 'Bienvenue au Sugar Paper',
                    'texte' => 'Tous les produits a petit prix',
                    'image_fond' => 'market.png',
                    'statut' => 'actif',
                ]);
            }
            if (!isset($config['statut'])) {
                $config['statut'] = 'actif';
            }
            return $config;
        } catch (PDOException $e) {
            return section4_default_config_row();
        }
    }

    try {
        $aid = $boutique_admin_id !== null ? (int) $boutique_admin_id : 0;
        if ($aid > 0) {
            $stmt = $db->prepare('SELECT * FROM section4_config WHERE admin_id = :aid ORDER BY id DESC LIMIT 1');
            $stmt->execute(['aid' => $aid]);
        } else {
            $stmt = $db->prepare('SELECT * FROM section4_config WHERE admin_id IS NULL ORDER BY id DESC LIMIT 1');
            $stmt->execute();
        }
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$config) {
            return $aid > 0 ? section4_default_config_row() : array_merge(section4_default_config_row(), [
                'titre' => 'Bienvenue au Sugar Paper',
                'texte' => 'Tous les produits a petit prix',
                'image_fond' => 'market.png',
                'statut' => 'actif',
            ]);
        }
        if (!isset($config['statut'])) {
            $config['statut'] = 'actif';
        }
        return $config;
    } catch (PDOException $e) {
        return section4_default_config_row();
    }
}

function section4_has_column($column_name) {
    global $db;
    static $columns = null;
    if ($columns === null) {
        try {
            $stmt = $db->query('SHOW COLUMNS FROM section4_config');
            $columns = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $columns[] = $row['Field'];
            }
        } catch (PDOException $e) {
            return false;
        }
    }
    return in_array($column_name, $columns, true);
}

/**
 * Met à jour la configuration section4 pour le périmètre donné.
 * @param array $data
 * @param int|null $boutique_admin_id null = plateforme
 */
function update_section4_config($data, $boutique_admin_id = null) {
    global $db;

    try {
        $existing = get_section4_config($boutique_admin_id);
        $has_statut = section4_has_column('statut');
        $has_admin = db_table_has_column('section4_config', 'admin_id');

        $statut = isset($data['statut']) && in_array($data['statut'], ['actif', 'inactif'], true) ? $data['statut'] : 'actif';
        $titre = isset($data['titre']) ? trim($data['titre']) : '';
        $texte = isset($data['texte']) ? trim($data['texte']) : '';
        $image_fond = isset($data['image_fond']) ? $data['image_fond'] : null;

        $scope = $boutique_admin_id !== null ? (int) $boutique_admin_id : 0;

        if ($existing && isset($existing['id']) && (int) $existing['id'] > 0) {
            $set_parts = ['titre = :titre', 'texte = :texte', 'image_fond = :image_fond', 'date_modification = NOW()'];
            $params = [
                'id' => (int) $existing['id'],
                'titre' => $titre,
                'texte' => $texte,
                'image_fond' => $image_fond,
            ];
            if ($has_statut) {
                $set_parts[] = 'statut = :statut';
                $params['statut'] = $statut;
            }
            $sql = 'UPDATE section4_config SET ' . implode(', ', $set_parts) . ' WHERE id = :id';
            if ($has_admin && $scope > 0) {
                $sql .= ' AND admin_id = :scope';
                $params['scope'] = $scope;
            } elseif ($has_admin && $scope === 0) {
                $sql .= ' AND admin_id IS NULL';
            }
            $stmt = $db->prepare($sql);
            $ok = $stmt->execute($params);
            return $ok ? ['success' => true, 'message' => ''] : ['success' => false, 'message' => 'Échec de l\'exécution'];
        }

        $cols = ['titre', 'texte', 'image_fond', 'date_modification'];
        $placeholders = [':titre', ':texte', ':image_fond', 'NOW()'];
        $params = ['titre' => $titre, 'texte' => $texte, 'image_fond' => $image_fond];
        if ($has_statut) {
            $cols[] = 'statut';
            $placeholders[] = ':statut';
            $params['statut'] = $statut;
        }
        if ($has_admin) {
            $cols[] = 'admin_id';
            $placeholders[] = ':admin_id';
            $params['admin_id'] = $scope > 0 ? $scope : null;
        }
        $sql = 'INSERT INTO section4_config (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $db->prepare($sql);
        $ok = $stmt->execute($params);
        return $ok ? ['success' => true, 'message' => ''] : ['success' => false, 'message' => 'Échec de l\'exécution'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function delete_section4_image($image_name) {
    if (empty($image_name)) {
        return false;
    }

    $upload_dir = __DIR__ . '/../upload/section4/';
    $image_path = $upload_dir . $image_name;

    if (file_exists($image_path)) {
        return unlink($image_path);
    }

    return false;
}
