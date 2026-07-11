<?php
/**
 * Synchronisation des chemins d'images en base après conversion WebP.
 */

require_once __DIR__ . '/image_optimizer.php';

/**
 * @return string|null
 */
/**
 * Chemin .webp conservant le même nom de base (foo.jpg → foo.webp).
 */
function image_db_webp_equivalent_path($relative_path) {
    $relative_path = trim(str_replace('\\', '/', (string) $relative_path), '/');
    if ($relative_path === '') {
        return '';
    }
    $dir = dirname($relative_path);
    $stem = pathinfo($relative_path, PATHINFO_FILENAME);
    if ($stem === '') {
        return $relative_path;
    }
    return ($dir === '.' || $dir === '') ? $stem . '.webp' : $dir . '/' . $stem . '.webp';
}

function image_db_replace_path_in_value($path) {
    $path = trim(str_replace('\\', '/', (string) $path));
    if ($path === '') {
        return null;
    }
    $normalized = image_optimizer_normalize_db_path($path);
    return ($normalized !== $path) ? $normalized : null;
}

/**
 * @param array<int,string> $paths
 * @return array<int,string>|null
 */
function image_db_replace_paths_in_list(array $paths) {
    $changed = false;
    $out = [];
    foreach ($paths as $p) {
        $p = trim(str_replace('\\', '/', (string) $p));
        if ($p === '') {
            continue;
        }
        $n = image_optimizer_normalize_db_path($p);
        if ($n !== $p) {
            $changed = true;
        }
        $out[] = $n;
    }
    return $changed ? $out : null;
}

/**
 * @param PDO $db
 * @param string $old_rel
 * @param string $new_rel
 */
function image_db_apply_path_mapping($db, $old_rel, $new_rel) {
    $old_rel = trim(str_replace('\\', '/', $old_rel), '/');
    $new_rel = trim(str_replace('\\', '/', $new_rel), '/');
    if ($old_rel === '' || $new_rel === '' || $old_rel === $new_rel) {
        return;
    }

    image_db_replace_column_exact($db, 'produits', 'image_principale', $old_rel, $new_rel);
    image_db_replace_in_produits_images_json($db, $old_rel, $new_rel);
    image_db_replace_column_exact($db, 'categories', 'image', $old_rel, $new_rel);
    image_db_replace_column_exact($db, 'categories_generales', 'image', $old_rel, $new_rel);
    image_db_replace_column_exact($db, 'genres', 'image', $old_rel, $new_rel);
    image_db_replace_column_exact($db, 'produit_variantes', 'image', $old_rel, $new_rel);
    image_db_replace_column_exact($db, 'logos', 'image', $old_rel, $new_rel);

    if (image_db_table_exists($db, 'commandes_personnalisees')) {
        image_db_replace_column_exact($db, 'commandes_personnalisees', 'image_reference', $old_rel, $new_rel);
    }

    if (image_db_table_exists($db, 'marketplace_hero_affiches')) {
        image_db_replace_column_exact($db, 'marketplace_hero_affiches', 'image', basename($old_rel), basename($new_rel));
    }
}

/**
 * @param PDO $db
 */
function image_db_replace_column_exact($db, $table, $column, $old_val, $new_val) {
    if (!image_db_table_has_column($db, $table, $column)) {
        return 0;
    }
    $sql = "UPDATE `{$table}` SET `{$column}` = :new WHERE `{$column}` = :old";
    $stmt = $db->prepare($sql);
    $stmt->execute(['new' => $new_val, 'old' => $old_val]);
    return (int) $stmt->rowCount();
}

/**
 * @param PDO $db
 */
function image_db_replace_in_produits_images_json($db, $old_rel, $new_rel) {
    if (!image_db_table_has_column($db, 'produits', 'images')) {
        return 0;
    }
    $stmt = $db->query("SELECT id, images FROM produits WHERE images IS NOT NULL AND images != ''");
    if (!$stmt) {
        return 0;
    }
    $updated = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $decoded = json_decode((string) ($row['images'] ?? ''), true);
        if (!is_array($decoded)) {
            continue;
        }
        $changed = false;
        foreach ($decoded as $i => $item) {
            if (trim((string) $item) === $old_rel) {
                $decoded[$i] = $new_rel;
                $changed = true;
            }
        }
        if (!$changed) {
            continue;
        }
        $up = $db->prepare('UPDATE produits SET images = :images WHERE id = :id');
        $up->execute([
            'images' => json_encode(array_values($decoded), JSON_UNESCAPED_UNICODE),
            'id' => (int) $row['id'],
        ]);
        $updated++;
    }
    return $updated;
}

/**
 * @param PDO $db
 * @return array{updated:int, details:array<string,int>}
 */
function image_db_sync_all_image_paths($db) {
    $details = [];
    $total = 0;

    $total += image_db_sync_table_column($db, 'produits', 'image_principale', $details);
    $total += image_db_sync_produits_images_json($db, $details);
    $total += image_db_sync_table_column($db, 'categories', 'image', $details);
    $total += image_db_sync_table_column($db, 'categories_generales', 'image', $details);
    $total += image_db_sync_table_column($db, 'genres', 'image', $details);
    $total += image_db_sync_table_column($db, 'produit_variantes', 'image', $details);
    $total += image_db_sync_table_column($db, 'logos', 'image', $details);

    if (image_db_table_exists($db, 'commandes_personnalisees')
        && image_db_table_has_column($db, 'commandes_personnalisees', 'image_reference')) {
        $total += image_db_sync_table_column($db, 'commandes_personnalisees', 'image_reference', $details);
    }

    $total += image_db_sync_marketplace_hero_images($db, $details);

    return ['updated' => $total, 'details' => $details];
}

/**
 * Bannières hero marketplace : la BDD stocke le nom de fichier seul (hero_xxx.webp).
 *
 * @param PDO $db
 */
function image_db_sync_marketplace_hero_images($db, &$details) {
    $key = 'marketplace_hero_affiches.image';
    $details[$key] = 0;
    if (!image_db_table_exists($db, 'marketplace_hero_affiches')
        || !image_db_table_has_column($db, 'marketplace_hero_affiches', 'image')) {
        return 0;
    }

    $stmt = $db->query("SELECT id, image FROM marketplace_hero_affiches WHERE image IS NOT NULL AND image != ''");
    if (!$stmt) {
        return 0;
    }

    $count = 0;
    $update = $db->prepare('UPDATE marketplace_hero_affiches SET image = :new WHERE id = :id');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $old = basename(trim(str_replace('\\', '/', (string) ($row['image'] ?? ''))));
        if ($old === '') {
            continue;
        }
        $new = basename(image_optimizer_normalize_db_path('marketplace_hero/' . $old));
        if ($new === '' || $new === $old) {
            continue;
        }
        $update->execute(['new' => $new, 'id' => (int) $row['id']]);
        $count++;
    }
    $details[$key] = $count;
    return $count;
}

/**
 * @param PDO $db
 */
function image_db_sync_table_column($db, $table, $column, &$details) {
    if (!image_db_table_has_column($db, $table, $column)) {
        return 0;
    }
    $key = $table . '.' . $column;
    $details[$key] = 0;

    $stmt = $db->query("SELECT id, `{$column}` AS img FROM `{$table}` WHERE `{$column}` IS NOT NULL AND `{$column}` != ''");
    if (!$stmt) {
        return 0;
    }

    $count = 0;
    $update = $db->prepare("UPDATE `{$table}` SET `{$column}` = :new WHERE id = :id");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $old = trim(str_replace('\\', '/', (string) ($row['img'] ?? '')));
        if ($old === '') {
            continue;
        }
        $new = image_optimizer_normalize_db_path($old);
        if ($new === $old) {
            continue;
        }
        $update->execute(['new' => $new, 'id' => (int) $row['id']]);
        $count++;
    }
    $details[$key] = $count;
    return $count;
}

/**
 * @param PDO $db
 */
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

/**
 * Nom de la base courante (pour diagnostic CLI).
 *
 * @param PDO $db
 */
function image_db_current_database($db) {
    try {
        $name = $db->query('SELECT DATABASE()')->fetchColumn();
        return is_string($name) ? $name : '';
    } catch (PDOException $e) {
        return '';
    }
}

/**
 * @param PDO $db
 */
function image_db_table_exists($db, $table) {
    $table = trim(str_replace('`', '', (string) $table));
    if ($table === '') {
        return false;
    }
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t
        ");
        $stmt->execute(['t' => $table]);
        return ((int) $stmt->fetchColumn()) > 0;
    } catch (PDOException $e) {
        try {
            $stmt = $db->query('SHOW TABLES LIKE ' . $db->quote($table));
            return $stmt && $stmt->fetchColumn() !== false;
        } catch (PDOException $e2) {
            return false;
        }
    }
}

/**
 * @param PDO $db
 */
function image_db_table_has_column($db, $table, $column) {
    $table = trim(str_replace('`', '', (string) $table));
    $column = trim(str_replace('`', '', (string) $column));
    if ($table === '' || $column === '') {
        return false;
    }
    if (!image_db_table_exists($db, $table)) {
        return false;
    }
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :t
              AND COLUMN_NAME = :c
        ");
        $stmt->execute(['t' => $table, 'c' => $column]);
        return ((int) $stmt->fetchColumn()) > 0;
    } catch (PDOException $e) {
        try {
            $stmt = $db->query(
                'SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '` LIKE ' . $db->quote($column)
            );
            return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e2) {
            return false;
        }
    }
}
