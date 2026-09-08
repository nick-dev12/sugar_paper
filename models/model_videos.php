<?php
/**
 * Modèle pour la gestion des vidéos
 * Programmation procédurale uniquement
 */

// Inclusion du fichier de connexion à la BDD
require_once __DIR__ . '/../conn/conn.php';

/**
 * Récupère toutes les vidéos actives
 * @param string|null $statut Filtrer par statut ('actif', 'inactif' ou null pour tous)
 * @return array Tableau des vidéos (vide si aucun ou en cas d'erreur)
 */
function get_all_videos($statut = 'actif')
{
    global $db;

    try {
        if ($statut) {
            $stmt = $db->prepare("
                SELECT * FROM videos 
                WHERE statut = :statut 
                ORDER BY date_creation DESC
            ");
            $stmt->execute(['statut' => $statut]);
        } else {
            $stmt = $db->prepare("
                SELECT * FROM videos 
                ORDER BY date_creation DESC
            ");
            $stmt->execute();
        }

        $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $videos ? $videos : [];
    } catch (PDOException $e) {
        // En cas d'erreur (table n'existe pas encore), retourner un tableau vide
        return [];
    }
}

/**
 * Récupère une vidéo par son ID
 * @param int $id L'ID de la vidéo
 * @return array|false Les données de la vidéo ou False si non trouvée
 */
function get_video_by_id($id)
{
    global $db;

    try {
        $stmt = $db->prepare("SELECT * FROM videos WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $video = $stmt->fetch(PDO::FETCH_ASSOC);

        return $video ? $video : false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Vérifie si la colonne hero_banner existe
 * @return bool
 */
function videos_has_hero_banner_column()
{
    static $has = null;
    if ($has !== null) {
        return $has;
    }

    global $db;
    try {
        $r = $db ? $db->query("SHOW COLUMNS FROM videos LIKE 'hero_banner'") : null;
        $has = $r && (bool) $r->fetchColumn();
    } catch (PDOException $e) {
        $has = false;
    }

    return $has;
}

/**
 * Récupère la vidéo sélectionnée pour la bannière d'accueil
 * @return array|null
 */
function get_hero_banner_video()
{
    global $db;

    if (!videos_has_hero_banner_column()) {
        return null;
    }

    try {
        $stmt = $db->prepare("
            SELECT * FROM videos
            WHERE hero_banner = 1 AND statut = 'actif'
            ORDER BY date_modification DESC, date_creation DESC
            LIMIT 1
        ");
        $stmt->execute();
        $video = $stmt->fetch(PDO::FETCH_ASSOC);
        return $video ? $video : null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Désactive hero_banner sur toutes les vidéos (sauf éventuellement une)
 * @param int $except_id
 * @return void
 */
function clear_other_videos_hero_banner($except_id = 0)
{
    global $db;

    if (!videos_has_hero_banner_column()) {
        return;
    }

    try {
        $except_id = (int) $except_id;
        if ($except_id > 0) {
            $stmt = $db->prepare('UPDATE videos SET hero_banner = 0 WHERE id != :id AND hero_banner = 1');
            $stmt->execute(['id' => $except_id]);
        } else {
            $db->exec('UPDATE videos SET hero_banner = 0 WHERE hero_banner = 1');
        }
    } catch (PDOException $e) {
        // ignore
    }
}

/**
 * Crée une nouvelle vidéo
 * @param array $data Les données de la vidéo
 * @return int|false L'ID de la vidéo créée ou False en cas d'erreur
 */
function create_video($data)
{
    global $db;

    try {
        $hero_banner = !empty($data['hero_banner']) ? 1 : 0;
        $with_hero = videos_has_hero_banner_column();

        if ($with_hero) {
            $stmt = $db->prepare("
                INSERT INTO videos (titre, fichier_video, image_preview, statut, hero_banner, date_creation)
                VALUES (:titre, :fichier_video, :image_preview, :statut, :hero_banner, NOW())
            ");
            $result = $stmt->execute([
                'titre' => $data['titre'],
                'fichier_video' => $data['fichier_video'],
                'image_preview' => $data['image_preview'] ?? null,
                'statut' => $data['statut'] ?? 'actif',
                'hero_banner' => $hero_banner,
            ]);
        } else {
            $stmt = $db->prepare("
                INSERT INTO videos (titre, fichier_video, image_preview, statut, date_creation)
                VALUES (:titre, :fichier_video, :image_preview, :statut, NOW())
            ");
            $result = $stmt->execute([
                'titre' => $data['titre'],
                'fichier_video' => $data['fichier_video'],
                'image_preview' => $data['image_preview'] ?? null,
                'statut' => $data['statut'] ?? 'actif',
            ]);
        }

        if ($result) {
            $new_id = (int) $db->lastInsertId();
            if ($with_hero && $hero_banner === 1 && $new_id > 0) {
                clear_other_videos_hero_banner($new_id);
            }
            return $new_id;
        }

        return false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Met à jour une vidéo
 * @param int $id L'ID de la vidéo
 * @param array $data Les nouvelles données
 * @return bool True en cas de succès, False sinon
 */
function update_video($id, $data)
{
    global $db;

    try {
        $fields = ['titre = :titre', 'fichier_video = :fichier_video', 'statut = :statut', 'date_modification = NOW()'];
        $params = [
            'id' => $id,
            'titre' => $data['titre'],
            'fichier_video' => $data['fichier_video'],
            'statut' => $data['statut'] ?? 'actif',
        ];

        if (isset($data['image_preview'])) {
            $fields[] = 'image_preview = :image_preview';
            $params['image_preview'] = $data['image_preview'];
        }

        $hero_banner = null;
        if (videos_has_hero_banner_column() && array_key_exists('hero_banner', $data)) {
            $hero_banner = !empty($data['hero_banner']) ? 1 : 0;
            $fields[] = 'hero_banner = :hero_banner';
            $params['hero_banner'] = $hero_banner;
        }

        $sql = 'UPDATE videos SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $db->prepare($sql);
        $ok = $stmt->execute($params);

        if ($ok && $hero_banner === 1) {
            clear_other_videos_hero_banner((int) $id);
        }

        return $ok;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Supprime une vidéo
 * @param int $id L'ID de la vidéo
 * @return bool True en cas de succès, False sinon
 */
function delete_video($id)
{
    global $db;

    try {
        // Récupérer la vidéo pour supprimer le fichier vidéo et son thumbnail
        $video = get_video_by_id($id);
        if ($video) {
            // Supprimer le fichier vidéo uploadé
            if (!empty($video['fichier_video'])) {
                $video_path = __DIR__ . '/../upload/videos/' . $video['fichier_video'];
                if (file_exists($video_path)) {
                    unlink($video_path);
                }
            }
            // Supprimer le thumbnail s'il existe
            if (!empty($video['image_preview'])) {
                $thumbnail_path = __DIR__ . '/../upload/videos/thumbnails/' . $video['image_preview'];
                if (file_exists($thumbnail_path)) {
                    unlink($thumbnail_path);
                }
            }
        }

        $stmt = $db->prepare("DELETE FROM videos WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * URL publique de la miniature vidéo si le fichier existe sur disque
 * @param array $video
 * @return string
 */
function resolve_video_poster_url(array $video)
{
    if (empty($video['image_preview'])) {
        return '';
    }

    $thumb_disk = __DIR__ . '/../upload/videos/thumbnails/' . $video['image_preview'];
    if (is_file($thumb_disk) && filesize($thumb_disk) > 0) {
        return '/upload/videos/thumbnails/' . rawurlencode($video['image_preview']);
    }

    return '';
}

/**
 * Garantit une miniature pour l'affichage (génère si absente, position pseudo-aléatoire dans la vidéo).
 * @param array $video Ligne vidéo (id, fichier_video, image_preview)
 * @return string|null Nom du fichier thumbnail ou null
 */
function video_ensure_preview_image(array $video)
{
    global $db;

    if (!empty($video['image_preview'])) {
        $existing = __DIR__ . '/../upload/videos/thumbnails/' . $video['image_preview'];
        if (is_file($existing) && filesize($existing) > 0) {
            return $video['image_preview'];
        }
    }

    if (empty($video['fichier_video'])) {
        return null;
    }

    $video_path = __DIR__ . '/../upload/videos/' . $video['fichier_video'];
    if (!is_file($video_path)) {
        return null;
    }

    $thumbnails_dir = __DIR__ . '/../upload/videos/thumbnails/';
    if (!is_dir($thumbnails_dir)) {
        mkdir($thumbnails_dir, 0755, true);
    }

    require_once __DIR__ . '/../controllers/controller_videos.php';

    $thumb_name = 'thumb_' . pathinfo($video['fichier_video'], PATHINFO_FILENAME) . '.jpg';
    $thumb_path = $thumbnails_dir . $thumb_name;

    $video_id = (int) ($video['id'] ?? 0);
    $offset = $video_id > 0 ? (($video_id * 13 + 7) % 12) + 1 : 2;

    if (!generate_video_thumbnail($video_path, $thumb_path, $offset)) {
        for ($try = 1; $try <= 4; $try++) {
            if (generate_video_thumbnail($video_path, $thumb_path, $try)) {
                break;
            }
        }
    }

    if (!is_file($thumb_path) || filesize($thumb_path) <= 0) {
        return null;
    }

    require_once __DIR__ . '/../includes/image_optimizer.php';
    $optimized = image_optimizer_process_disk_thumbnail(
        $thumb_path,
        $thumbnails_dir,
        'videos/thumbnails',
        pathinfo($thumb_name, PATHINFO_FILENAME)
    );
    if ($optimized === null || $optimized === '') {
        return null;
    }

    $thumb_name = $optimized;

    if ($video_id > 0) {
        try {
            $stmt = $db->prepare('UPDATE videos SET image_preview = :preview WHERE id = :id');
            $stmt->execute(['preview' => $thumb_name, 'id' => $video_id]);
        } catch (PDOException $e) {
            // Affichage possible même si la BDD n'est pas mise à jour
        }
    }

    return $thumb_name;
}