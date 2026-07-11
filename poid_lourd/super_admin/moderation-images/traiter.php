<?php
/**
 * Traitement modération image — Super Admin
 */
require_once __DIR__ . '/../includes/require_login.php';
require_once dirname(__DIR__, 2) . '/models/model_super_admin.php';
require_once dirname(__DIR__, 2) . '/models/model_produit_image_moderation.php';
require_once dirname(__DIR__, 2) . '/models/model_produits.php';
require_once dirname(__DIR__, 2) . '/includes/image_optimizer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$tok = $_POST['csrf_token'] ?? '';
if (!super_admin_csrf_valid($tok)) {
    $_SESSION['super_admin_flash_err'] = 'Jeton de sécurité invalide.';
    header('Location: index.php');
    exit;
}

$entry_id = isset($_POST['entry_id']) ? (int) $_POST['entry_id'] : 0;
$action = isset($_POST['action']) ? trim((string) $_POST['action']) : '';
$motif = isset($_POST['motif']) ? trim((string) $_POST['motif']) : '';
$sa_id = (int) ($_SESSION['super_admin_id'] ?? 0);

if ($entry_id <= 0 || !in_array($action, ['approuver', 'refuser'], true)) {
    $_SESSION['super_admin_flash_err'] = 'Données invalides.';
    header('Location: index.php');
    exit;
}

if (!produit_image_moderation_ensure_table()) {
    $_SESSION['super_admin_flash_err'] = 'Table de modération indisponible.';
    header('Location: index.php');
    exit;
}

global $db;
$row = null;
try {
    $stmt = $db->prepare('SELECT * FROM produit_image_moderation WHERE id = :id AND statut = \'en_attente\' LIMIT 1');
    $stmt->execute(['id' => $entry_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $row = null;
}

if (!is_array($row)) {
    $_SESSION['super_admin_flash_err'] = 'Entrée introuvable ou déjà traitée.';
    header('Location: index.php');
    exit;
}

$produit_id = (int) ($row['produit_id'] ?? 0);
$image_path = trim((string) ($row['image_path'] ?? ''));

if ($action === 'approuver') {
    if (!produit_image_moderation_set_statut($entry_id, 'approuve', $sa_id)) {
        $_SESSION['super_admin_flash_err'] = 'Impossible d\'approuver cette image.';
    } else {
        if ($produit_id > 0) {
            produit_image_moderation_maybe_publish_produit($produit_id);
        }
        $_SESSION['super_admin_flash_ok'] = 'Image approuvée.';
    }
} else {
    $refus_motif = $motif !== '' ? $motif : 'Image refusée — contenu non conforme aux règles de la plateforme.';
    if (!produit_image_moderation_set_statut($entry_id, 'refuse', $sa_id, $refus_motif)) {
        $_SESSION['super_admin_flash_err'] = 'Impossible de refuser cette image.';
    } else {
        if ($image_path !== '') {
            image_optimizer_delete_with_variants($image_path);
        }
        if ($produit_id > 0 && produit_moderation_plateforme_active()) {
            super_admin_bloquer_produit($produit_id, $refus_motif, ['image']);
        } elseif ($produit_id > 0) {
            try {
                $stmt = $db->prepare("UPDATE produits SET statut = 'inactif', date_modification = NOW() WHERE id = :id");
                $stmt->execute(['id' => $produit_id]);
            } catch (PDOException $e) {
            }
        }
        $_SESSION['super_admin_flash_ok'] = 'Image refusée et retirée.';
    }
}

header('Location: index.php');
exit;
