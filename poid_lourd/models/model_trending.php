<?php
/**
 * Modèle pour la gestion de la configuration de la section trending
 */

require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/../includes/db_schema_helpers.php';

function trending_default_config_row() {
    return [
        'id' => 0,
        'label' => 'categories',
        'titre' => '',
        'bouton_texte' => '',
        'bouton_lien' => '#',
        'image' => '',
        'date_modification' => date('Y-m-d H:i:s'),
    ];
}

/**
 * @param int|null $boutique_admin_id null = plateforme
 */
function get_trending_config($boutique_admin_id = null) {
    global $db;

    if (!$db) {
        return array_merge(trending_default_config_row(), [
            'titre' => 'Enhance Your Music Experience',
            'bouton_texte' => 'Buy Now!',
            'image' => 'speaker.png',
        ]);
    }

    if (!db_table_has_column('trending_config', 'admin_id')) {
        try {
            $stmt = $db->prepare('SELECT * FROM trending_config ORDER BY id DESC LIMIT 1');
            $stmt->execute();
            $config = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$config) {
                return array_merge(trending_default_config_row(), [
                    'titre' => 'Enhance Your Music Experience',
                    'bouton_texte' => 'Buy Now!',
                    'image' => 'speaker.png',
                ]);
            }
            return $config;
        } catch (PDOException $e) {
            return trending_default_config_row();
        }
    }

    try {
        $aid = $boutique_admin_id !== null ? (int) $boutique_admin_id : 0;
        if ($aid > 0) {
            $stmt = $db->prepare('SELECT * FROM trending_config WHERE admin_id = :aid ORDER BY id DESC LIMIT 1');
            $stmt->execute(['aid' => $aid]);
        } else {
            $stmt = $db->prepare('SELECT * FROM trending_config WHERE admin_id IS NULL ORDER BY id DESC LIMIT 1');
            $stmt->execute();
        }
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$config) {
            return $aid > 0 ? trending_default_config_row() : array_merge(trending_default_config_row(), [
                'titre' => 'Enhance Your Music Experience',
                'bouton_texte' => 'Buy Now!',
                'image' => 'speaker.png',
            ]);
        }
        return $config;
    } catch (PDOException $e) {
        return trending_default_config_row();
    }
}

/**
 * @param int|null $boutique_admin_id
 */
function update_trending_config($data, $boutique_admin_id = null) {
    global $db;

    try {
        $existing = get_trending_config($boutique_admin_id);
        $has_admin = db_table_has_column('trending_config', 'admin_id');
        $scope = $boutique_admin_id !== null ? (int) $boutique_admin_id : 0;

        if ($existing && isset($existing['id']) && (int) $existing['id'] > 0) {
            $sql = '
                UPDATE trending_config
                SET label = :label, titre = :titre, bouton_texte = :bouton_texte,
                    bouton_lien = :bouton_lien, image = :image, date_modification = NOW()
                WHERE id = :id';
            $params = [
                'id' => (int) $existing['id'],
                'label' => $data['label'],
                'titre' => $data['titre'],
                'bouton_texte' => $data['bouton_texte'],
                'bouton_lien' => $data['bouton_lien'] ?? '#',
                'image' => $data['image'] ?? null,
            ];
            if ($has_admin && $scope > 0) {
                $sql .= ' AND admin_id = :scope';
                $params['scope'] = $scope;
            } elseif ($has_admin && $scope === 0) {
                $sql .= ' AND admin_id IS NULL';
            }
            $stmt = $db->prepare($sql);
            return $stmt->execute($params);
        }

        $cols = ['label', 'titre', 'bouton_texte', 'bouton_lien', 'image', 'date_modification'];
        $ph = [':label', ':titre', ':bouton_texte', ':bouton_lien', ':image', 'NOW()'];
        $params = [
            'label' => $data['label'],
            'titre' => $data['titre'],
            'bouton_texte' => $data['bouton_texte'],
            'bouton_lien' => $data['bouton_lien'] ?? '#',
            'image' => $data['image'] ?? null,
        ];
        if ($has_admin) {
            $cols[] = 'admin_id';
            $ph[] = ':admin_id';
            $params['admin_id'] = $scope > 0 ? $scope : null;
        }
        $stmt = $db->prepare('INSERT INTO trending_config (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $ph) . ')');
        return $stmt->execute($params);
    } catch (PDOException $e) {
        return false;
    }
}

function delete_trending_image($image_name) {
    if (empty($image_name)) {
        return false;
    }

    $upload_dir = __DIR__ . '/../upload/trending/';
    $image_path = $upload_dir . $image_name;

    if (file_exists($image_path)) {
        return unlink($image_path);
    }

    return false;
}
