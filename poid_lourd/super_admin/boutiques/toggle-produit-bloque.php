<?php
/**
 * Bloquer / débloquer un produit — Super Admin
 */
require_once __DIR__ . '/../includes/require_login.php';
require_once dirname(__DIR__, 2) . '/models/model_super_admin.php';
require_once dirname(__DIR__, 2) . '/models/model_produits.php';
require_once dirname(__DIR__, 2) . '/controllers/controller_super_admin.php';

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

$produit_id = isset($_POST['produit_id']) ? (int) $_POST['produit_id'] : 0;
$vendeur_id = isset($_POST['vendeur_id']) ? (int) $_POST['vendeur_id'] : 0;
$action = isset($_POST['action']) ? trim((string) $_POST['action']) : '';

if ($vendeur_id <= 0 || $produit_id <= 0) {
    $_SESSION['super_admin_flash_err'] = 'Données invalides.';
    header('Location: detail.php?id=' . max(0, $vendeur_id));
    exit;
}

$p = get_produit_by_id($produit_id);
if (!$p || (int) ($p['admin_id'] ?? 0) !== $vendeur_id) {
    $_SESSION['super_admin_flash_err'] = 'Produit introuvable pour cette boutique.';
    header('Location: detail.php?id=' . $vendeur_id);
    exit;
}

if (!produit_moderation_plateforme_active()) {
    $_SESSION['super_admin_flash_err'] = 'Migration blocage produits non exécutée.';
    header('Location: detail.php?id=' . $vendeur_id);
    exit;
}

if ($action === 'debloquer') {
    if (super_admin_debloquer_produit($produit_id)) {
        $_SESSION['super_admin_flash_ok'] = 'Produit débloqué — visible à nouveau sur le site.';
    } else {
        $_SESSION['super_admin_flash_err'] = 'Impossible de débloquer ce produit.';
    }
} elseif ($action === 'bloquer') {
    $motif = trim((string) ($_POST['motif'] ?? ''));
    $champs = [];
    if (!empty($_POST['champ_nom'])) {
        $champs[] = 'nom';
    }
    if (!empty($_POST['champ_image'])) {
        $champs[] = 'image';
    }
    if ($motif === '' || empty($champs)) {
        $_SESSION['super_admin_flash_err'] = 'Indiquez le motif et au moins un élément à corriger (nom ou image).';
    } elseif (super_admin_bloquer_produit($produit_id, $motif, $champs)) {
        $_SESSION['super_admin_flash_ok'] = 'Produit bloqué — masqué du site. Le vendeur devra modifier les éléments indiqués.';
    } else {
        $_SESSION['super_admin_flash_err'] = 'Impossible de bloquer ce produit.';
    }
} else {
    $_SESSION['super_admin_flash_err'] = 'Action non reconnue.';
}

$return_to = isset($_POST['return_to']) ? trim((string) $_POST['return_to']) : 'detail';
if ($return_to === 'produit') {
    header('Location: produit.php?id=' . $produit_id . '&vendeur_id=' . $vendeur_id);
} else {
    header('Location: detail.php?id=' . $vendeur_id);
}
exit;
