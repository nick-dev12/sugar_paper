<?php
/**
 * Synchronisation des chemins d'images en base après conversion WebP.
 */

require_once __DIR__ . '/image_optimizer.php';

function image_db_webp_equivalent_path($relative_path) {
    $relative_path = trim(str_replace('\\', '/', (string) $relative_path), '/');
    if ($relative_path === '') {
        return '';
    }
    $directory = dirname($relative_path);
    $stem = pathinfo($relative_path, PATHINFO_FILENAME);
    if ($stem === '') {
        return $relative_path;
    }
    return ($directory === '.' || $directory === '') ? $stem . '.webp' : $directory . '/' . $stem . '.webp';
}

function image_db_replace_paths_in_list(array $paths) {
    $changed = false;
    $out = [];
    foreach ($paths as $path) {
        $path = trim(str_replace('\\', '/', (string) $path));
        if ($path === '') {
            continue;
        }
        $normalized = image_optimizer_normalize_db_path($path);
        if ($normalized !== $path) {
            $changed = true;
        }
        $out[] = $normalized;
    }
    return $changed ? $out : null;
}

function image_db_table_exists($db, $table) {
    $table = trim(str_replace('`', '', (string) $table));
    if ($table === '') {
        return false;
    }
    try {
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
        );
        $stmt->execute(['table_name' => $table]);
        return (int) $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

function image_db_table_has_column($db, $table, $column) {
    $table = trim(str_replace('`', '', (string) $table));
    $column = trim(str_replace('`', '', (string) $column));
    if ($table === '' || $column === '' || !image_db_table_exists($db, $table)) {
        return false;
    }
    try {
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME = :column_name'
        );
        $stmt->execute(['table_name' => $table, 'column_name' => $column]);
        return (int) $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

function image_db_replace_column_exact($db, $table, $column, $old_value, $new_value) {
    if (!image_db_table_has_column($db, $table, $column)) {
        return 0;
    }
    $stmt = $db->prepare("UPDATE `{$table}` SET `{$column}` = :new_value WHERE `{$column}` = :old_value");
    $stmt->execute(['new_value' => $new_value, 'old_value' => $old_value]);
    return (int) $stmt->rowCount();
}

/**
 * Applique un renommage de chemin sur toutes les colonnes concernées.
 *
 * @return int Nombre total de lignes réellement mises à jour.
 */
function image_db_apply_path_mapping($db, $old_rel, $new_rel) {
    $old_rel = trim(str_replace('\\', '/', (string) $old_rel), '/');
    $new_rel = trim(str_replace('\\', '/', (string) $new_rel), '/');
    if ($old_rel === '' || $new_rel === '' || $old_rel === $new_rel) {
        return 0;
    }

    $affected = 0;
    $affected += image_db_replace_column_exact($db, 'produits', 'image_principale', $old_rel, $new_rel);
    $affected += image_db_replace_in_produits_images_json($db, $old_rel, $new_rel);
    $affected += image_db_replace_column_exact($db, 'categories', 'image', $old_rel, $new_rel);
    $affected += image_db_replace_column_exact($db, 'produits_variantes', 'image', $old_rel, $new_rel);
    $affected += image_db_replace_in_commandes_perso_images($db, $old_rel, $new_rel);

    $affected += image_db_replace_column_exact($db, 'slider', 'image', basename($old_rel), basename($new_rel));
    $affected += image_db_replace_column_exact($db, 'section4_config', 'image_fond', basename($old_rel), basename($new_rel));
    $affected += image_db_replace_column_exact($db, 'trending_config', 'image', basename($old_rel), basename($new_rel));

    $affected += image_db_replace_column_exact($db, 'admin', 'photo_profil', $old_rel, $new_rel);
    $affected += image_db_replace_column_exact($db, 'employes', 'photo_chemin', $old_rel, $new_rel);
    $affected += image_db_replace_column_exact($db, 'panier', 'image_personnalisation', $old_rel, $new_rel);
    $affected += image_db_replace_column_exact($db, 'commande_produits', 'image_personnalisation', $old_rel, $new_rel);
    $affected += image_db_replace_column_exact($db, 'cp_catalogue_produits', 'image', $old_rel, $new_rel);
    $affected += image_db_replace_column_exact($db, 'logos', 'image', $old_rel, $new_rel);
    $affected += image_db_replace_column_exact($db, 'employe_absence_justificatifs', 'fichier_chemin', $old_rel, $new_rel);
    $affected += image_db_replace_column_exact($db, 'employe_documents', 'fichier_chemin', $old_rel, $new_rel);
    $affected += image_db_replace_column_exact($db, 'videos', 'image_preview', basename($old_rel), basename($new_rel));

    return $affected;
}

/**
 * commandes_personnalisees.image_reference peut contenir un chemin unique
 * ou un tableau JSON de chemins. On gère les deux cas sans corrompre la valeur.
 */
function image_db_replace_in_commandes_perso_images($db, $old_rel, $new_rel) {
    if (!image_db_table_has_column($db, 'commandes_personnalisees', 'image_reference')) {
        return 0;
    }
    $stmt = $db->query("SELECT id, image_reference FROM commandes_personnalisees WHERE image_reference IS NOT NULL AND image_reference != ''");
    if (!$stmt) {
        return 0;
    }
    $updated = 0;
    $update = $db->prepare('UPDATE commandes_personnalisees SET image_reference = :value WHERE id = :id');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $raw = (string) ($row['image_reference'] ?? '');
        $decoded = json_decode($raw, true);

        if (is_array($decoded)) {
            $changed = false;
            foreach ($decoded as $index => $item) {
                if (trim(str_replace('\\', '/', (string) $item), '/') === $old_rel) {
                    $decoded[$index] = $new_rel;
                    $changed = true;
                }
            }
            if (!$changed) {
                continue;
            }
            $update->execute([
                'value' => json_encode(array_values($decoded), JSON_UNESCAPED_UNICODE),
                'id' => (int) $row['id'],
            ]);
            $updated++;
            continue;
        }

        if (trim(str_replace('\\', '/', $raw), '/') === $old_rel) {
            $update->execute(['value' => $new_rel, 'id' => (int) $row['id']]);
            $updated++;
        }
    }
    return $updated;
}

function image_db_replace_in_produits_images_json($db, $old_rel, $new_rel) {
    if (!image_db_table_has_column($db, 'produits', 'images')) {
        return 0;
    }
    $stmt = $db->query("SELECT id, images FROM produits WHERE images IS NOT NULL AND images != ''");
    if (!$stmt) {
        return 0;
    }
    $updated = 0;
    $update = $db->prepare('UPDATE produits SET images = :images WHERE id = :id');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $decoded = json_decode((string) ($row['images'] ?? ''), true);
        if (!is_array($decoded)) {
            continue;
        }
        $changed = false;
        foreach ($decoded as $index => $item) {
            if (trim(str_replace('\\', '/', (string) $item), '/') === $old_rel) {
                $decoded[$index] = $new_rel;
                $changed = true;
            }
        }
        if (!$changed) {
            continue;
        }
        $update->execute([
            'images' => json_encode(array_values($decoded), JSON_UNESCAPED_UNICODE),
            'id' => (int) $row['id'],
        ]);
        $updated++;
    }
    return $updated;
}

function image_db_sync_table_column($db, $table, $column, &$details, $basename_only = false, $subdir = '') {
    $key = $table . '.' . $column;
    $details[$key] = 0;
    if (!image_db_table_has_column($db, $table, $column)) {
        return 0;
    }

    $stmt = $db->query("SELECT id, `{$column}` AS img FROM `{$table}` WHERE `{$column}` IS NOT NULL AND `{$column}` != ''");
    if (!$stmt) {
        return 0;
    }

    $count = 0;
    $update = $db->prepare("UPDATE `{$table}` SET `{$column}` = :new_value WHERE id = :id");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $old = trim(str_replace('\\', '/', (string) ($row['img'] ?? '')));
        if ($old === '') {
            continue;
        }

        if ($basename_only) {
            $old_base = basename($old);
            $probe = ($subdir !== '' ? $subdir . '/' : '') . $old_base;
            $new_base = basename(image_optimizer_normalize_db_path($probe));
            if ($new_base === '' || $new_base === $old_base) {
                continue;
            }
            $update->execute(['new_value' => $new_base, 'id' => (int) $row['id']]);
        } else {
            $new = image_optimizer_normalize_db_path($old);
            if ($new === $old) {
                continue;
            }
            $update->execute(['new_value' => $new, 'id' => (int) $row['id']]);
        }
        $count++;
    }
    $details[$key] = $count;
    return $count;
}

function image_db_sync_produits_images_json($db, &$details) {
    $key = 'produits.images';
    $details[$key] = 0;
    if (!image_db_table_has_column($db, 'produits', 'images')) {
        return 0;
    }

    $stmt = $db->query("SELECT id, images FROM produits WHERE images IS NOT NULL AND images != ''");
    if (!$stmt) {
        return 0;
    }

    $count = 0;
    $update = $db->prepare('UPDATE produits SET images = :images WHERE id = :id');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $decoded = json_decode((string) ($row['images'] ?? ''), true);
        if (!is_array($decoded)) {
            continue;
        }
        $new_list = image_db_replace_paths_in_list($decoded);
        if ($new_list === null) {
            continue;
        }
        $update->execute([
            'images' => json_encode($new_list, JSON_UNESCAPED_UNICODE),
            'id' => (int) $row['id'],
        ]);
        $count++;
    }
    $details[$key] = $count;
    return $count;
}

function image_db_sync_commandes_perso_images($db, &$details) {
    $key = 'commandes_personnalisees.image_reference';
    $details[$key] = 0;
    if (!image_db_table_has_column($db, 'commandes_personnalisees', 'image_reference')) {
        return 0;
    }
    $stmt = $db->query("SELECT id, image_reference FROM commandes_personnalisees WHERE image_reference IS NOT NULL AND image_reference != ''");
    if (!$stmt) {
        return 0;
    }
    $count = 0;
    $update = $db->prepare('UPDATE commandes_personnalisees SET image_reference = :value WHERE id = :id');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $raw = (string) ($row['image_reference'] ?? '');
        $decoded = json_decode($raw, true);

        if (is_array($decoded)) {
            $new_list = image_db_replace_paths_in_list($decoded);
            if ($new_list === null) {
                continue;
            }
            $update->execute([
                'value' => json_encode($new_list, JSON_UNESCAPED_UNICODE),
                'id' => (int) $row['id'],
            ]);
            $count++;
            continue;
        }

        $normalized = image_optimizer_normalize_db_path($raw);
        if ($normalized === trim(str_replace('\\', '/', $raw))) {
            continue;
        }
        $update->execute(['value' => $normalized, 'id' => (int) $row['id']]);
        $count++;
    }
    $details[$key] = $count;
    return $count;
}

/**
 * @return array{updated:int, details:array<string,int>}
 */
function image_db_sync_all_image_paths($db) {
    $details = [];
    $total = 0;

    $total += image_db_sync_table_column($db, 'produits', 'image_principale', $details);
    $total += image_db_sync_produits_images_json($db, $details);
    $total += image_db_sync_table_column($db, 'categories', 'image', $details);
    $total += image_db_sync_table_column($db, 'produits_variantes', 'image', $details);
    $total += image_db_sync_commandes_perso_images($db, $details);
    $total += image_db_sync_table_column($db, 'slider', 'image', $details, true, 'slider');
    $total += image_db_sync_table_column($db, 'section4_config', 'image_fond', $details, true, 'section4');
    $total += image_db_sync_table_column($db, 'trending_config', 'image', $details, true, 'trending');

    $total += image_db_sync_table_column($db, 'admin', 'photo_profil', $details);
    $total += image_db_sync_table_column($db, 'employes', 'photo_chemin', $details);
    $total += image_db_sync_table_column($db, 'panier', 'image_personnalisation', $details);
    $total += image_db_sync_table_column($db, 'commande_produits', 'image_personnalisation', $details);
    $total += image_db_sync_table_column($db, 'cp_catalogue_produits', 'image', $details);
    $total += image_db_sync_table_column($db, 'logos', 'image', $details);
    $total += image_db_sync_table_column($db, 'employe_absence_justificatifs', 'fichier_chemin', $details);
    $total += image_db_sync_table_column($db, 'employe_documents', 'fichier_chemin', $details);
    $total += image_db_sync_video_thumbnails($db, $details);

    return ['updated' => $total, 'details' => $details];
}

/**
 * Miniatures vidéo : nom de fichier seul dans upload/videos/thumbnails/.
 */
function image_db_sync_video_thumbnails($db, &$details) {
    $key = 'videos.image_preview';
    $details[$key] = 0;
    if (!image_db_table_has_column($db, 'videos', 'image_preview')) {
        return 0;
    }

    $stmt = $db->query("SELECT id, image_preview FROM videos WHERE image_preview IS NOT NULL AND image_preview != ''");
    if (!$stmt) {
        return 0;
    }

    $count = 0;
    $update = $db->prepare('UPDATE videos SET image_preview = :new_value WHERE id = :id');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $old = basename(trim(str_replace('\\', '/', (string) ($row['image_preview'] ?? ''))));
        if ($old === '') {
            continue;
        }
        $new = basename(image_optimizer_normalize_db_path('videos/thumbnails/' . $old));
        if ($new === '' || $new === $old) {
            continue;
        }
        $update->execute(['new_value' => $new, 'id' => (int) $row['id']]);
        $count++;
    }
    $details[$key] = $count;
    return $count;
}

/**
 * Nom de la base courante (diagnostic CLI).
 */
function image_db_current_database($db) {
    try {
        $name = $db->query('SELECT DATABASE()')->fetchColumn();
        return is_string($name) ? $name : '';
    } catch (PDOException $e) {
        return '';
    }
}
