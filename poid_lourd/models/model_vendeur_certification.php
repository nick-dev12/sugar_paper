<?php
/**
 * Certification vendeurs — Standard / VIP / Premium.
 * Programmation procédurale uniquement.
 */

require_once __DIR__ . '/../conn/conn.php';
require_once __DIR__ . '/model_admin.php';

function vendeur_certification_table_exists() {
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
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vendeur_certification_demandes'
        ");
        $cached = ((int) $st->fetchColumn()) > 0;
    } catch (PDOException $e) {
        $cached = false;
    }
    return $cached;
}

function vendeur_certification_admin_column_exists() {
    return admin_has_column('certification_niveau');
}

function vendeur_certification_niveaux() {
    return [
        'standard' => [
            'label' => 'Standard',
            'badge' => 'cert-badge--standard',
            'icon' => 'fa-shield',
            'color' => '#737373',
            'desc' => 'Gagnez la confiance des clients et augmentez vos ventes.',
        ],
        'vip' => [
            'label' => 'VIP',
            'badge' => 'cert-badge--vip',
            'icon' => 'fa-gem',
            'color' => '#3564a6',
            'desc' => 'Augmentez votre visibilité et touchez plus de clients.',
        ],
        'premium' => [
            'label' => 'Premium',
            'badge' => 'cert-badge--premium',
            'icon' => 'fa-crown',
            'color' => '#d97706',
            'desc' => 'Placez-vous au top des ventes — visibilité x10, bénéfices garantis.',
        ],
    ];
}

function vendeur_certification_niveau_ordre($niveau) {
    $map = ['standard' => 1, 'vip' => 2, 'premium' => 3];
    return $map[(string) $niveau] ?? 0;
}

function vendeur_certification_niveau_label($niveau) {
    $n = vendeur_certification_niveaux();
    return $n[$niveau]['label'] ?? ucfirst((string) $niveau);
}

/**
 * Chemin public de l'image badge certification.
 */
function vendeur_certification_badge_image_src($niveau) {
    $map = [
        'standard' => '/image/badge standar.png',
        'vip' => '/image/badge vip.png',
        'premium' => '/image/badge premium.png',
    ];
    $niveau = (string) $niveau;
    if (!isset($map[$niveau])) {
        return '';
    }
    $parts = explode('/', $map[$niveau]);
    $file = array_pop($parts);
    $parts[] = rawurlencode($file);
    return implode('/', $parts);
}

function vendeur_certification_get_niveau_actif($admin_id) {
    global $db;
    $admin_id = (int) $admin_id;
    if ($admin_id <= 0 || !vendeur_certification_admin_column_exists() || !$db) {
        return null;
    }
    try {
        $st = $db->prepare('SELECT certification_niveau FROM admin WHERE id = :id LIMIT 1');
        $st->execute(['id' => $admin_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        $n = trim((string) ($row['certification_niveau'] ?? ''));
        return in_array($n, ['standard', 'vip', 'premium'], true) ? $n : null;
    } catch (PDOException $e) {
        return null;
    }
}

function vendeur_certification_get_demande_en_cours($admin_id) {
    global $db;
    $admin_id = (int) $admin_id;
    if ($admin_id <= 0 || !vendeur_certification_table_exists() || !$db) {
        return null;
    }
    try {
        $st = $db->prepare("
            SELECT * FROM vendeur_certification_demandes
            WHERE admin_id = :a AND statut = 'en_attente'
            ORDER BY date_creation DESC
            LIMIT 1
        ");
        $st->execute(['a' => $admin_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

function vendeur_certification_get_derniere_demande($admin_id) {
    global $db;
    $admin_id = (int) $admin_id;
    if ($admin_id <= 0 || !vendeur_certification_table_exists() || !$db) {
        return null;
    }
    try {
        $st = $db->prepare("
            SELECT * FROM vendeur_certification_demandes
            WHERE admin_id = :a
            ORDER BY date_creation DESC
            LIMIT 1
        ");
        $st->execute(['a' => $admin_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

function vendeur_certification_peut_demander($admin_id, $niveau) {
    $niveau = (string) $niveau;
    if (!in_array($niveau, ['standard', 'vip', 'premium'], true)) {
        return ['ok' => false, 'message' => 'Niveau de certification invalide.'];
    }
    if (vendeur_certification_get_demande_en_cours($admin_id)) {
        return ['ok' => false, 'message' => 'Une demande est déjà en cours d\'examen.'];
    }
    $actuel = vendeur_certification_get_niveau_actif($admin_id);
    if ($actuel !== null && vendeur_certification_niveau_ordre($niveau) <= vendeur_certification_niveau_ordre($actuel)) {
        return ['ok' => false, 'message' => 'Vous possédez déjà ce niveau ou un niveau supérieur.'];
    }
    return ['ok' => true, 'message' => ''];
}

function vendeur_certification_upload_photo($file, $admin_id, $prefix = 'local') {
    $admin_id = (int) $admin_id;
    if ($admin_id <= 0 || !is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'path' => '', 'message' => 'Fichier image requis.'];
    }
    require_once __DIR__ . '/../includes/upload_image_limits.php';
    $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    $mime = (string) ($file['type'] ?? '');
    if (!in_array($mime, $allowed, true)) {
        return ['ok' => false, 'path' => '', 'message' => 'Format accepté : JPEG, PNG ou WebP.'];
    }
    if ((int) ($file['size'] ?? 0) > UPLOAD_MAX_IMAGE_BYTES) {
        return ['ok' => false, 'path' => '', 'message' => 'Image trop volumineuse (max. 20 Mo).'];
    }
    $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        $ext = 'jpg';
    }
    $dir = __DIR__ . '/../upload/certifications/' . $admin_id;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return ['ok' => false, 'path' => '', 'message' => 'Impossible de créer le dossier d\'upload.'];
    }
    $name = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file((string) $file['tmp_name'], $dest)) {
        return ['ok' => false, 'path' => '', 'message' => 'Échec de l\'envoi de l\'image.'];
    }
    return ['ok' => true, 'path' => 'certifications/' . $admin_id . '/' . $name, 'message' => ''];
}

function vendeur_certification_piece_identite_column_exists() {
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
            SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'vendeur_certification_demandes'
              AND COLUMN_NAME = 'photo_piece_identite'
        ");
        $cached = ((int) $st->fetchColumn()) > 0;
    } catch (PDOException $e) {
        $cached = false;
    }
    return $cached;
}

function vendeur_certification_creer_demande($admin_id, array $data) {
    global $db;
    $admin_id = (int) $admin_id;
    $niveau = (string) ($data['niveau'] ?? '');
    $check = vendeur_certification_peut_demander($admin_id, $niveau);
    if (!$check['ok']) {
        return ['ok' => false, 'message' => $check['message']];
    }
    if (!vendeur_certification_table_exists() || !$db) {
        return ['ok' => false, 'message' => 'Migration certification requise.'];
    }
    try {
        $has_piece = vendeur_certification_piece_identite_column_exists();
        $cols = 'admin_id, niveau, statut, nom, prenom, email, telephone,
                boutique_nom, boutique_region, adresse_exacte, description_activite,
                numero_registre, photo_local_1, photo_local_2, photo_local_3,
                photo_document, message_demande, date_creation';
        $vals = ':admin_id, :niveau, \'en_attente\', :nom, :prenom, :email, :telephone,
                :boutique_nom, :boutique_region, :adresse_exacte, :description_activite,
                :numero_registre, :photo_local_1, :photo_local_2, :photo_local_3,
                :photo_document, :message_demande, NOW()';
        if ($has_piece) {
            $cols = str_replace('photo_document, message_demande', 'photo_document, photo_piece_identite, message_demande', $cols);
            $vals = str_replace(':photo_document, :message_demande', ':photo_document, :photo_piece_identite, :message_demande', $vals);
        }
        $st = $db->prepare("INSERT INTO vendeur_certification_demandes ($cols) VALUES ($vals)");
        $params = [
            'admin_id' => $admin_id,
            'niveau' => $niveau,
            'nom' => (string) ($data['nom'] ?? ''),
            'prenom' => (string) ($data['prenom'] ?? ''),
            'email' => (string) ($data['email'] ?? ''),
            'telephone' => (string) ($data['telephone'] ?? ''),
            'boutique_nom' => (string) ($data['boutique_nom'] ?? ''),
            'boutique_region' => (string) ($data['boutique_region'] ?? ''),
            'adresse_exacte' => (string) ($data['adresse_exacte'] ?? ''),
            'description_activite' => (string) ($data['description_activite'] ?? ''),
            'numero_registre' => (string) ($data['numero_registre'] ?? ''),
            'photo_local_1' => (string) ($data['photo_local_1'] ?? ''),
            'photo_local_2' => (string) ($data['photo_local_2'] ?? ''),
            'photo_local_3' => (string) ($data['photo_local_3'] ?? ''),
            'photo_document' => (string) ($data['photo_document'] ?? ''),
            'message_demande' => (string) ($data['message_demande'] ?? ''),
        ];
        if ($has_piece) {
            $params['photo_piece_identite'] = (string) ($data['photo_piece_identite'] ?? '');
        }
        $st->execute($params);
        return ['ok' => true, 'message' => 'Votre demande a été envoyée. Notre équipe l\'examinera sous peu.', 'id' => (int) $db->lastInsertId()];
    } catch (PDOException $e) {
        return ['ok' => false, 'message' => 'Erreur lors de l\'enregistrement de la demande.'];
    }
}

function vendeur_certification_list_en_attente($limit = 50) {
    return vendeur_certification_list_par_statut('en_attente', $limit);
}

/**
 * Liste des demandes par statut (en_attente | approuvee | refusee).
 *
 * @return array<int, array>
 */
function vendeur_certification_list_par_statut($statut, $limit = 100) {
    global $db;
    if (!vendeur_certification_table_exists() || !$db) {
        return [];
    }
    $statut = (string) $statut;
    if (!in_array($statut, ['en_attente', 'approuvee', 'refusee'], true)) {
        return [];
    }
    $limit = max(1, min(200, (int) $limit));
    $order = $statut === 'en_attente' ? 'd.date_creation ASC' : 'd.date_traitement DESC, d.date_creation DESC';
    try {
        $st = $db->prepare("
            SELECT d.*, a.boutique_slug, a.certification_niveau AS niveau_actif_admin
            FROM vendeur_certification_demandes d
            INNER JOIN admin a ON a.id = d.admin_id
            WHERE d.statut = :st
            ORDER BY $order
            LIMIT $limit
        ");
        $st->execute(['st' => $statut]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    } catch (PDOException $e) {
        return [];
    }
}

function vendeur_certification_counts_par_statut() {
    global $db;
    $out = ['en_attente' => 0, 'approuvee' => 0, 'refusee' => 0];
    if (!vendeur_certification_table_exists() || !$db) {
        return $out;
    }
    try {
        $st = $db->query("
            SELECT statut, COUNT(*) AS nb
            FROM vendeur_certification_demandes
            WHERE statut IN ('en_attente', 'approuvee', 'refusee')
            GROUP BY statut
        ");
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $s = (string) ($row['statut'] ?? '');
            if (isset($out[$s])) {
                $out[$s] = (int) ($row['nb'] ?? 0);
            }
        }
    } catch (PDOException $e) {
    }
    return $out;
}

function vendeur_certification_statut_label($statut) {
    return match ((string) $statut) {
        'en_attente' => 'En cours',
        'approuvee'  => 'Validée',
        'refusee'    => 'Refusée',
        'annulee'    => 'Annulée',
        default      => ucfirst(str_replace('_', ' ', (string) $statut)),
    };
}

function vendeur_certification_traiter_demande($demande_id, $action, $motif_refus = '', $traite_par = null) {
    global $db;
    $demande_id = (int) $demande_id;
    $action = (string) $action;
    if ($demande_id <= 0 || !vendeur_certification_table_exists() || !$db) {
        return false;
    }
    if (!in_array($action, ['approuvee', 'refusee'], true)) {
        return false;
    }
    try {
        $st = $db->prepare('SELECT * FROM vendeur_certification_demandes WHERE id = :id LIMIT 1');
        $st->execute(['id' => $demande_id]);
        $dem = $st->fetch(PDO::FETCH_ASSOC);
        if (!$dem || ($dem['statut'] ?? '') !== 'en_attente') {
            return false;
        }
        $db->beginTransaction();
        $st2 = $db->prepare("
            UPDATE vendeur_certification_demandes
            SET statut = :st, motif_refus = :motif, date_traitement = NOW(), traite_par = :tp"
            . (vendeur_certification_notif_lue_column_exists() ? ', vendeur_notif_lue = 0' : '')
            . "
            WHERE id = :id
        ");
        $st2->execute([
            'st' => $action,
            'motif' => $action === 'refusee' ? trim((string) $motif_refus) : null,
            'tp' => $traite_par !== null ? (int) $traite_par : null,
            'id' => $demande_id,
        ]);
        if ($action === 'approuvee' && vendeur_certification_admin_column_exists()) {
            $st3 = $db->prepare("
                UPDATE admin SET certification_niveau = :n, certification_date = NOW()
                WHERE id = :aid
            ");
            $st3->execute(['n' => $dem['niveau'], 'aid' => (int) $dem['admin_id']]);
        }
        $db->commit();
        return true;
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return false;
    }
}

function vendeur_certification_count_en_attente() {
    global $db;
    if (!vendeur_certification_table_exists() || !$db) {
        return 0;
    }
    try {
        return (int) $db->query("SELECT COUNT(*) FROM vendeur_certification_demandes WHERE statut = 'en_attente'")->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

function vendeur_certification_notif_lue_column_exists() {
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
            SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'vendeur_certification_demandes'
              AND COLUMN_NAME = 'vendeur_notif_lue'
        ");
        $cached = ((int) $st->fetchColumn()) > 0;
    } catch (PDOException $e) {
        $cached = false;
    }
    return $cached;
}

function vendeur_certification_get_demande_by_id($demande_id) {
    global $db;
    $demande_id = (int) $demande_id;
    if ($demande_id <= 0 || !vendeur_certification_table_exists() || !$db) {
        return null;
    }
    try {
        $st = $db->prepare("
            SELECT d.*, a.boutique_slug, a.certification_niveau AS niveau_actif_admin
            FROM vendeur_certification_demandes d
            INNER JOIN admin a ON a.id = d.admin_id
            WHERE d.id = :id
            LIMIT 1
        ");
        $st->execute(['id' => $demande_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

function vendeur_certification_get_demande_for_vendeur($demande_id, $admin_id) {
    $demande_id = (int) $demande_id;
    $admin_id = (int) $admin_id;
    if ($demande_id <= 0 || $admin_id <= 0) {
        return null;
    }
    $row = vendeur_certification_get_demande_by_id($demande_id);
    if (!$row || (int) ($row['admin_id'] ?? 0) !== $admin_id) {
        return null;
    }
    return $row;
}

function vendeur_certification_get_notif_vendeur_pending($admin_id) {
    global $db;
    $admin_id = (int) $admin_id;
    if ($admin_id <= 0 || !vendeur_certification_table_exists() || !$db) {
        return null;
    }
    try {
        $notif_sql = vendeur_certification_notif_lue_column_exists()
            ? ' AND (d.vendeur_notif_lue = 0 OR d.vendeur_notif_lue IS NULL)'
            : '';
        $st = $db->prepare("
            SELECT d.*, a.boutique_slug
            FROM vendeur_certification_demandes d
            INNER JOIN admin a ON a.id = d.admin_id
            WHERE d.admin_id = :aid
              AND d.statut IN ('approuvee', 'refusee')
              AND d.date_traitement IS NOT NULL
              $notif_sql
            ORDER BY d.date_traitement DESC
            LIMIT 1
        ");
        $st->execute(['aid' => $admin_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

function vendeur_certification_marquer_notif_vendeur_lue($demande_id, $admin_id) {
    global $db;
    $demande_id = (int) $demande_id;
    $admin_id = (int) $admin_id;
    if ($demande_id <= 0 || $admin_id <= 0 || !vendeur_certification_table_exists() || !$db) {
        return false;
    }
    if (!vendeur_certification_notif_lue_column_exists()) {
        return true;
    }
    try {
        $st = $db->prepare("
            UPDATE vendeur_certification_demandes
            SET vendeur_notif_lue = 1
            WHERE id = :id AND admin_id = :aid
        ");
        $st->execute(['id' => $demande_id, 'aid' => $admin_id]);
        return $st->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}
