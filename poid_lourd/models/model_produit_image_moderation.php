<?php
/**
 * Journal et file d'attente — modération images produits vendeurs.
 */
require_once __DIR__ . '/../conn/conn.php';

function produit_image_moderation_table_exists()
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    global $db;
    $cached = false;
    if (!$db) {
        return false;
    }
    try {
        $st = $db->query("
            SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produit_image_moderation'
        ");
        $cached = ((int) $st->fetchColumn()) > 0;
    } catch (PDOException $e) {
        $cached = false;
    }
    return $cached;
}

function produit_image_moderation_ensure_table()
{
    static $ok = null;
    if ($ok === true) {
        return true;
    }
    global $db;
    if (!$db) {
        $ok = false;
        return false;
    }
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS `produit_image_moderation` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `produit_id` int(11) NULL DEFAULT NULL,
            `admin_id` int(11) NOT NULL DEFAULT 0,
            `image_path` varchar(500) NOT NULL DEFAULT '',
            `image_hash` char(64) NOT NULL DEFAULT '',
            `statut` enum('en_attente','approuve','refuse') NOT NULL DEFAULT 'en_attente',
            `motif` text NULL,
            `scores_json` text NULL,
            `provider` varchar(32) NULL DEFAULT NULL,
            `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `date_traitement` datetime NULL DEFAULT NULL,
            `super_admin_id` int(11) NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_pim_statut` (`statut`),
            KEY `idx_pim_admin` (`admin_id`),
            KEY `idx_pim_produit` (`produit_id`),
            KEY `idx_pim_hash` (`image_hash`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $ok = true;
        return true;
    } catch (PDOException $e) {
        $ok = false;
        return false;
    }
}

/**
 * @param array<string, mixed> $scan
 */
function produit_image_moderation_log_entry($admin_id, $image_path, array $scan, $produit_id = null)
{
    if (!produit_image_moderation_ensure_table()) {
        return 0;
    }
    global $db;
    $admin_id = (int) $admin_id;
    $produit_id = $produit_id !== null ? (int) $produit_id : null;
    $image_path = trim(str_replace('\\', '/', (string) $image_path), '/');
    if ($image_path === '') {
        return 0;
    }
    $hash = (string) ($scan['hash'] ?? '');
    if ($hash === '') {
        $abs = dirname(__DIR__) . '/upload/' . $image_path;
        $hash = is_file($abs) ? hash_file('sha256', $abs) : '';
    }
    $statut = 'en_attente';
    if (($scan['action'] ?? '') === 'allow') {
        $statut = 'approuve';
    } elseif (($scan['action'] ?? '') === 'reject') {
        $statut = 'refuse';
    }
    try {
        $stmt = $db->prepare('
            INSERT INTO produit_image_moderation
                (produit_id, admin_id, image_path, image_hash, statut, motif, scores_json, provider, date_creation)
            VALUES
                (:pid, :aid, :path, :hash, :st, :motif, :scores, :prov, NOW())
        ');
        $stmt->execute([
            'pid' => $produit_id > 0 ? $produit_id : null,
            'aid' => $admin_id,
            'path' => $image_path,
            'hash' => $hash,
            'st' => $statut,
            'motif' => (string) ($scan['reason'] ?? ''),
            'scores' => !empty($scan['scores']) ? json_encode($scan['scores'], JSON_UNESCAPED_UNICODE) : null,
            'prov' => (string) ($scan['provider'] ?? ''),
        ]);
        return (int) $db->lastInsertId();
    } catch (PDOException $e) {
        return 0;
    }
}

function produit_image_moderation_attach_produit($produit_id, $admin_id, array $image_paths)
{
    $produit_id = (int) $produit_id;
    $admin_id = (int) $admin_id;
    if ($produit_id <= 0 || !produit_image_moderation_ensure_table()) {
        return;
    }
    global $db;
    $paths = [];
    foreach ($image_paths as $p) {
        $p = trim(str_replace('\\', '/', (string) $p), '/');
        if ($p !== '') {
            $paths[] = $p;
        }
    }
    if (empty($paths)) {
        return;
    }
    try {
        $stmt = $db->prepare('
            UPDATE produit_image_moderation
            SET produit_id = :pid
            WHERE admin_id = :aid AND produit_id IS NULL AND image_path = :path
        ');
        foreach ($paths as $path) {
            $stmt->execute(['pid' => $produit_id, 'aid' => $admin_id, 'path' => $path]);
        }
    } catch (PDOException $e) {
    }
}

function produit_image_moderation_count_pending($admin_id = 0)
{
    if (!produit_image_moderation_ensure_table()) {
        return 0;
    }
    global $db;
    try {
        if ((int) $admin_id > 0) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM produit_image_moderation WHERE statut = 'en_attente' AND admin_id = :aid");
            $stmt->execute(['aid' => (int) $admin_id]);
        } else {
            $stmt = $db->query("SELECT COUNT(*) FROM produit_image_moderation WHERE statut = 'en_attente'");
        }
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * @return array<int, array>
 */
function produit_image_moderation_list_pending($limit = 50, $admin_id = 0)
{
    if (!produit_image_moderation_ensure_table()) {
        return [];
    }
    global $db;
    $limit = max(1, min(200, (int) $limit));
    try {
        if ((int) $admin_id > 0) {
            $stmt = $db->prepare("
                SELECT m.*, p.nom AS produit_nom, a.boutique_nom, a.nom AS vendeur_nom
                FROM produit_image_moderation m
                LEFT JOIN produits p ON p.id = m.produit_id
                LEFT JOIN admin a ON a.id = m.admin_id
                WHERE m.statut = 'en_attente' AND m.admin_id = :aid
                ORDER BY m.date_creation ASC
                LIMIT $limit
            ");
            $stmt->execute(['aid' => (int) $admin_id]);
        } else {
            $stmt = $db->query("
                SELECT m.*, p.nom AS produit_nom, a.boutique_nom, a.nom AS vendeur_nom
                FROM produit_image_moderation m
                LEFT JOIN produits p ON p.id = m.produit_id
                LEFT JOIN admin a ON a.id = m.admin_id
                WHERE m.statut = 'en_attente'
                ORDER BY m.date_creation ASC
                LIMIT $limit
            ");
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    } catch (PDOException $e) {
        return [];
    }
}

function produit_image_moderation_produit_has_pending($produit_id)
{
    $produit_id = (int) $produit_id;
    if ($produit_id <= 0 || !produit_image_moderation_ensure_table()) {
        return false;
    }
    global $db;
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM produit_image_moderation WHERE produit_id = :pid AND statut = 'en_attente'");
        $stmt->execute(['pid' => $produit_id]);
        return ((int) $stmt->fetchColumn()) > 0;
    } catch (PDOException $e) {
        return false;
    }
}

function produit_image_moderation_set_statut($entry_id, $statut, $super_admin_id, $motif = '')
{
    if (!produit_image_moderation_ensure_table()) {
        return false;
    }
    global $db;
    $entry_id = (int) $entry_id;
    $super_admin_id = (int) $super_admin_id;
    if ($entry_id <= 0 || !in_array($statut, ['approuve', 'refuse'], true)) {
        return false;
    }
    try {
        $stmt = $db->prepare("
            UPDATE produit_image_moderation
            SET statut = :st, motif = :motif, super_admin_id = :said, date_traitement = NOW()
            WHERE id = :id AND statut = 'en_attente'
        ");
        return $stmt->execute([
            'st' => $statut,
            'motif' => trim((string) $motif),
            'said' => $super_admin_id > 0 ? $super_admin_id : null,
            'id' => $entry_id,
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

function produit_image_moderation_maybe_publish_produit($produit_id)
{
    $produit_id = (int) $produit_id;
    if ($produit_id <= 0 || produit_image_moderation_produit_has_pending($produit_id)) {
        return false;
    }
    require_once __DIR__ . '/model_produits.php';
    $p = get_produit_by_id($produit_id);
    if (!$p || ($p['statut'] ?? '') !== 'inactif') {
        return false;
    }
    global $db;
    try {
        $stmt = $db->prepare("
            UPDATE produits SET statut = 'actif', date_modification = NOW()
            WHERE id = :id AND statut = 'inactif' AND stock > 0
        ");
        $stmt->execute(['id' => $produit_id]);
        if ($stmt->rowCount() > 0) {
            return true;
        }
        $stmt = $db->prepare("
            UPDATE produits SET statut = 'rupture_stock', date_modification = NOW()
            WHERE id = :id AND statut = 'inactif' AND stock <= 0
        ");
        $stmt->execute(['id' => $produit_id]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

function produit_image_moderation_hash_is_blocked($hash)
{
    $hash = strtolower(trim((string) $hash));
    if ($hash === '' || strlen($hash) !== 64) {
        return false;
    }
    if (!produit_image_moderation_ensure_table()) {
        return false;
    }
    global $db;
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM produit_image_moderation WHERE image_hash = :h AND statut = 'refuse'");
        $stmt->execute(['h' => $hash]);
        return ((int) $stmt->fetchColumn()) > 0;
    } catch (PDOException $e) {
        return false;
    }
}
