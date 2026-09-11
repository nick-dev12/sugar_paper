<?php
/**
 * Modèle pour la gestion de la configuration de la section trending
 * Programmation procédurale uniquement
 */

// Inclusion du fichier de connexion à la BDD
require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/../includes/image_optimizer.php';

/**
 * Récupère la configuration de la section trending
 * @return array|false Les données de configuration ou False si non trouvé
 */
function get_trending_config() {
    global $db;
    
    try {
        $stmt = $db->prepare("SELECT * FROM trending_config ORDER BY id DESC LIMIT 1");
        $stmt->execute();
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Si aucune configuration n'existe, retourner une configuration par défaut
        if (!$config) {
            return [
                'id' => 0,
                'label' => 'Nouveauté',
                'titre' => 'Sugar Paper|Kit impression comestible',
                'description' => 'Découvrez l\'impression, les cartouches encre comestible et le papier sucre.',
                'bouton_texte' => 'Découvrir',
                'bouton_lien' => 'section-produits.php?section=kit_impression',
                'image' => 'speaker.png',
                'date_modification' => date('Y-m-d H:i:s')
            ];
        }
        
        return $config;
    } catch (PDOException $e) {
        // En cas d'erreur, retourner une configuration par défaut
        return [
            'id' => 0,
            'label' => 'Nouveauté',
            'titre' => 'Sugar Paper|Kit impression comestible',
            'description' => 'Découvrez l\'impression, les cartouches encre comestible et le papier sucre.',
            'bouton_texte' => 'Découvrir',
            'bouton_lien' => 'section-produits.php?section=kit_impression',
            'image' => 'speaker.png',
            'date_modification' => date('Y-m-d H:i:s')
        ];
    }
}

/**
 * Met à jour la configuration de la section trending
 * @param array $data Les données de configuration
 * @return bool True en cas de succès, False sinon
 */
function update_trending_config($data) {
    global $db;
    
    try {
        // Vérifier si une configuration existe déjà
        $existing = get_trending_config();
        
        if ($existing && isset($existing['id']) && $existing['id'] > 0) {
            // Mettre à jour la configuration existante
            $stmt = $db->prepare("
                UPDATE trending_config 
                SET label = :label, 
                    titre = :titre, 
                    description = :description,
                    bouton_texte = :bouton_texte,
                    bouton_lien = :bouton_lien,
                    image = :image,
                    date_modification = NOW()
                WHERE id = :id
            ");
            
            return $stmt->execute([
                'id' => $existing['id'],
                'label' => $data['label'],
                'titre' => $data['titre'],
                'description' => $data['description'] ?? null,
                'bouton_texte' => $data['bouton_texte'],
                'bouton_lien' => $data['bouton_lien'] ?? '#',
                'image' => $data['image'] ?? null
            ]);
        } else {
            // Créer une nouvelle configuration
            $stmt = $db->prepare("
                INSERT INTO trending_config (label, titre, description, bouton_texte, bouton_lien, image, date_modification) 
                VALUES (:label, :titre, :description, :bouton_texte, :bouton_lien, :image, NOW())
            ");
            
            return $stmt->execute([
                'label' => $data['label'],
                'titre' => $data['titre'],
                'description' => $data['description'] ?? null,
                'bouton_texte' => $data['bouton_texte'],
                'bouton_lien' => $data['bouton_lien'] ?? '#',
                'image' => $data['image'] ?? null
            ]);
        }
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Supprime l'image de la section trending
 * @param string $image_name Le nom de l'image à supprimer
 * @return bool True en cas de succès, False sinon
 */
function delete_trending_image($image_name) {
    if (empty($image_name)) {
        return false;
    }

    $image_path = __DIR__ . '/../upload/trending/' . $image_name;
    $existed = is_file($image_path);
    image_optimizer_delete_with_variants('trending/' . $image_name);
    return $existed && !is_file($image_path);
}

/**
 * Vérifie si la table trending_images existe
 * @return bool
 */
function trending_has_images_table()
{
    static $has = null;
    if ($has !== null) {
        return $has;
    }

    global $db;
    try {
        $r = $db ? $db->query("SHOW TABLES LIKE 'trending_images'") : null;
        $has = $r && (bool) $r->fetchColumn();
    } catch (PDOException $e) {
        $has = false;
    }

    return $has;
}

/**
 * Récupère les images du carrousel spotlight
 * @return array<int, array<string, mixed>>
 */
function get_trending_spotlight_images()
{
    global $db;

    if (!trending_has_images_table()) {
        return [];
    }

    try {
        $stmt = $db->query('SELECT * FROM trending_images ORDER BY ordre ASC, id ASC');
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        return is_array($rows) ? $rows : [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Ajoute une image au carrousel spotlight
 * @param string $image_name
 * @return bool
 */
function add_trending_spotlight_image($image_name)
{
    global $db;

    $image_name = trim((string) $image_name);
    if ($image_name === '' || !trending_has_images_table()) {
        return false;
    }

    try {
        $ordre_stmt = $db->query('SELECT COALESCE(MAX(ordre), 0) + 1 FROM trending_images');
        $ordre = $ordre_stmt ? (int) $ordre_stmt->fetchColumn() : 1;
        $stmt = $db->prepare('INSERT INTO trending_images (image, ordre) VALUES (:image, :ordre)');
        return $stmt->execute([
            'image' => $image_name,
            'ordre' => $ordre,
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Supprime une image du carrousel spotlight par ID
 * @param int $image_id
 * @return bool
 */
function delete_trending_spotlight_image_by_id($image_id)
{
    global $db;

    $image_id = (int) $image_id;
    if ($image_id <= 0 || !trending_has_images_table()) {
        return false;
    }

    try {
        $stmt = $db->prepare('SELECT image FROM trending_images WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $image_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }

        $delete = $db->prepare('DELETE FROM trending_images WHERE id = :id');
        if (!$delete->execute(['id' => $image_id])) {
            return false;
        }

        delete_trending_image($row['image']);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère une image du carrousel par ID
 * @param int $image_id
 * @return array|false
 */
function get_trending_spotlight_image_by_id($image_id)
{
    global $db;

    $image_id = (int) $image_id;
    if ($image_id <= 0 || !trending_has_images_table()) {
        return false;
    }

    try {
        $stmt = $db->prepare('SELECT * FROM trending_images WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $image_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Remplace le fichier d'une image du carrousel
 * @param int $image_id
 * @param string $image_name
 * @return bool
 */
function update_trending_spotlight_image($image_id, $image_name)
{
    global $db;

    $image_id = (int) $image_id;
    $image_name = trim((string) $image_name);
    if ($image_id <= 0 || $image_name === '' || !trending_has_images_table()) {
        return false;
    }

    try {
        $stmt = $db->prepare('SELECT image FROM trending_images WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $image_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }

        $update = $db->prepare('UPDATE trending_images SET image = :image WHERE id = :id');
        if (!$update->execute(['image' => $image_name, 'id' => $image_id])) {
            return false;
        }

        $old_name = trim((string) ($row['image'] ?? ''));
        if ($old_name !== '' && $old_name !== $image_name) {
            delete_trending_image($old_name);
        }

        return true;
    } catch (PDOException $e) {
        return false;
    }
}