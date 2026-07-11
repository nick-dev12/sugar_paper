<?php
/**
 * Page de liste des commandes utilisateur — redesign v2
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../includes/session_user.php';
session_start_persistent();


if (!function_exists('auth_redirect_to_site_home')) {
    require_once __DIR__ . '/../includes/auth_redirect.php';
}
if (empty($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    auth_redirect_to_site_home();
    exit;
}

require_once __DIR__ . '/../models/model_commandes.php';
require_once __DIR__ . '/../models/model_admin.php';
require_once __DIR__ . '/../includes/flash_toast.php';
require_once __DIR__ . '/../includes/boutique_vendeur_display.php';
require_once __DIR__ . '/../includes/commande_card_helpers.php';

$success_message = '';
$error_message = '';

// Toast commande créée : une seule fois, puis URL propre (évite réaffichage à chaque F5)
if (!empty($_GET['success']) && (string) $_GET['success'] === '1') {
    $cmd_success_msg = '';
    if (!empty($_GET['numeros'])) {
        $nums = array_filter(array_map('trim', explode(',', (string) $_GET['numeros'])));
        $cmd_success_msg = count($nums) > 1
            ? 'Vos commandes ont été créées : ' . implode(', ', $nums) . '.'
            : 'Votre commande #' . ($nums[0] ?? '') . ' a été créée avec succès !';
    } elseif (!empty($_GET['numero'])) {
        $cmd_success_msg = 'Votre commande #' . trim((string) $_GET['numero']) . ' a été créée avec succès !';
    }
    if ($cmd_success_msg !== '') {
        flash_toast_push('success', $cmd_success_msg);
        $tab_keep = isset($_GET['tab']) ? trim((string) $_GET['tab']) : '';
        $redir = '/user/mes-commandes.php';
        if ($tab_keep !== '' && $tab_keep !== 'actives') {
            $redir .= '?tab=' . rawurlencode($tab_keep);
        }
        http_redirect_safe($redir);
    }
}

// ---- Confirmation livraison ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmer_livraison'])) {
    $commande_id = (int) ($_POST['commande_id'] ?? 0);
    if ($commande_id > 0) {
        $commande = get_commande_by_id($commande_id, $_SESSION['user_id']);
        if ($commande && $commande['statut'] === 'livraison_en_cours') {
            require_once __DIR__ . '/../models/model_commandes_admin.php';
            if (update_commande_statut($commande_id, 'paye')) {
                require_once __DIR__ . '/../services/send_commande_notification.php';
                send_commande_status_notification((int) $_SESSION['user_id'], $commande['numero_commande'], 'paye', trim($_SESSION['user_email'] ?? ''));
                $vendeur_id = (int) ($commande['vendeur_id'] ?? 0);
                if ($vendeur_id > 0)
                    send_commande_vendeur_action_notification($vendeur_id, $commande_id, $commande['numero_commande'], 'R&eacute;ception confirm&eacute;e par le client (pay&eacute;e)');
                header('Location: mes-commandes.php?livraison_confirmee=1&noter=' . $commande_id);
                exit;
            }
        }
        $error_message = 'Une erreur est survenue lors de la confirmation.';
    }
}

// ---- Annulation commande ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['annuler_commande'])) {
    $commande_id = (int) ($_POST['commande_id'] ?? 0);
    if ($commande_id > 0) {
        $commande = get_commande_by_id($commande_id, $_SESSION['user_id']);
        if ($commande && $commande['statut'] !== 'livree' && $commande['statut'] !== 'annulee') {
            if (update_commande_statut_user($commande_id, $_SESSION['user_id'], 'annulee')) {
                require_once __DIR__ . '/../services/send_commande_notification.php';
                send_commande_status_notification((int) $_SESSION['user_id'], $commande['numero_commande'], 'annulee', trim($_SESSION['user_email'] ?? ''));
                $vendeur_id = (int) ($commande['vendeur_id'] ?? 0);
                if ($vendeur_id > 0)
                    send_commande_vendeur_action_notification($vendeur_id, $commande_id, $commande['numero_commande'], 'Annul&eacute;e par le client');
                header('Location: mes-commandes.php?commande_annulee=1');
                exit;
            }
        }
        $error_message = empty($error_message) ? 'Cette commande ne peut pas &ecirc;tre annul&eacute;e.' : $error_message;
    }
}

// ---- Recommander (re-commander) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recommander'])) {
    $commande_id = (int) ($_POST['commande_id'] ?? 0);
    if ($commande_id > 0) {
        $commande = get_commande_by_id($commande_id, $_SESSION['user_id']);
        if ($commande && $commande['statut'] === 'annulee') {
            require_once __DIR__ . '/../models/model_panier.php';
            $produits_commande = get_commande_produits($commande_id);
            if (!empty($produits_commande)) {
                require_once __DIR__ . '/../models/model_variantes.php';
                require_once __DIR__ . '/../models/model_produits.php';
                $added_count = 0;
                foreach ($produits_commande as $produit) {
                    $produit_info = get_produit_by_id($produit['produit_id']);
                    if ($produit_info && $produit_info['statut'] === 'actif' && $produit_info['stock'] > 0) {
                        $quantite = min($produit['quantite'], $produit_info['stock']);
                        $variante_id = !empty($produit['variante_id']) ? (int) $produit['variante_id'] : null;
                        $variante_nom = !empty($produit['variante_nom']) ? trim($produit['variante_nom']) : null;
                        $variante_img = null;
                        if ($variante_id) {
                            $var = get_variante_by_id($variante_id);
                            $variante_img = $var && !empty($var['image']) ? $var['image'] : null;
                        }
                        if (add_to_panier($_SESSION['user_id'], $produit['produit_id'], $quantite, $produit['couleur'] ?? null, $produit['poids'] ?? null, $produit['taille'] ?? null, $variante_id, $variante_nom, $variante_img, (float) ($produit['surcout_poids'] ?? 0), (float) ($produit['surcout_taille'] ?? 0), isset($produit['prix_unitaire']) ? (float) $produit['prix_unitaire'] : null)) {
                            $added_count++;
                        }
                    }
                }
                if ($added_count > 0) {
                    header('Location: /panier.php?recommande=1&count=' . $added_count);
                    exit;
                }
                $error_message = 'Aucun produit disponible &agrave; recommander.';
            } else {
                $error_message = 'Aucun produit trouv&eacute; dans cette commande.';
            }
        } else {
            $error_message = 'Cette commande ne peut pas &ecirc;tre recommand&eacute;e.';
        }
    }
}

// commande_annulee, livraison_confirmee : toast via flash_toast_from_query()

// ---- Données ----
$toutes_commandes = get_commandes_by_user($_SESSION['user_id']);
$toutes_commandes = is_array($toutes_commandes) ? $toutes_commandes : [];

$commandes_actives = array_values(array_filter($toutes_commandes, fn($c) => !in_array($c['statut'], ['livree', 'paye', 'annulee'])));
$commandes_livrees = array_values(array_filter($toutes_commandes, fn($c) => in_array($c['statut'], ['livree', 'paye'])));
$commandes_annulees = array_values(array_filter($toutes_commandes, fn($c) => $c['statut'] === 'annulee'));

$nb_actives = count($commandes_actives);
$nb_livrees = count($commandes_livrees);
$nb_annulees = count($commandes_annulees);
$nb_total = count($toutes_commandes);

$tab = $_GET['tab'] ?? 'actives';

/**
 * Infos boutique associée à une commande (multi-boutique).
 *
 * @return array{nom:string, telephone:string}
 */
function uc_boutique_info_commande(array $commande)
{
    static $cache = [];
    $vid = (int) ($commande['vendeur_id'] ?? 0);
    if ($vid <= 0) {
        return ['nom' => 'Boutique', 'telephone' => ''];
    }
    if (!isset($cache[$vid])) {
        $admin = get_admin_by_id($vid);
        if (!is_array($admin)) {
            $cache[$vid] = ['nom' => 'Boutique', 'telephone' => ''];
        } else {
            $nom = trim((string) ($admin['boutique_nom'] ?? ''));
            $cache[$vid] = [
                'nom' => $nom !== '' ? $nom : 'Boutique',
                'telephone' => trim((string) ($admin['telephone'] ?? '')),
            ];
        }
    }
    return $cache[$vid];
}

function uc_boutique_nom_commande(array $commande)
{
    return uc_boutique_info_commande($commande)['nom'];
}

$commandes_affichees = match ($tab) {
    'livrees' => $commandes_livrees,
    'annulees' => $commandes_annulees,
    default => $commandes_actives,
};

$commandes_avis_stats = [];
$commandes_noter_pending = [];
if (file_exists(__DIR__ . '/../models/model_produits_avis.php')) {
    require_once __DIR__ . '/../models/model_produits_avis.php';
    if (!empty($commandes_affichees)) {
        $ids_cmd = [];
        foreach ($commandes_affichees as $c) {
            $ids_cmd[] = (int) ($c['id'] ?? 0);
        }
        if (function_exists('produits_avis_moyennes_commandes')) {
            $commandes_avis_stats = produits_avis_moyennes_commandes($ids_cmd);
        }
        if (function_exists('produits_avis_commandes_notation_en_attente')) {
            $commandes_noter_pending = produits_avis_commandes_notation_en_attente((int) $_SESSION['user_id'], $ids_cmd);
        }
    }
}

$pr_open_noter_commande = isset($_GET['noter']) ? (int) $_GET['noter'] : 0;

if (!empty($success_message)) {
    flash_toast_queue_page('success', $success_message);
}
if (!empty($error_message)) {
    flash_toast_queue_page('error', $error_message);
}

// ---- Helpers statut ----
function cmd_user_label($s)
{
    return match ($s) {
        'en_attente' => 'En attente',
        'prise_en_charge' => 'Confirm&eacute;e',
        'livraison_en_cours' => 'En livraison',
        'livree' => 'Livr&eacute;e',
        'paye' => 'Re&ccedil;ue',
        'annulee' => 'Annul&eacute;e',
        default => ucfirst(str_replace('_', ' ', $s)),
    };
}

function cmd_user_badge($s)
{
    return match ($s) {
        'en_attente' => 'ub--wait',
        'prise_en_charge' => 'ub--confirm',
        'livraison_en_cours' => 'ub--delivery',
        'livree', 'paye' => 'ub--done',
        'annulee' => 'ub--cancel',
        default => 'ub--wait',
    };
}

function cmd_user_icon($s)
{
    return match ($s) {
        'en_attente' => 'fa-clock',
        'prise_en_charge' => 'fa-box-open',
        'livraison_en_cours' => 'fa-truck',
        'livree', 'paye' => 'fa-circle-check',
        'annulee' => 'fa-ban',
        default => 'fa-clock',
    };
}

// Timeline steps
function cmd_timeline_steps($statut)
{
    $steps = [
        ['key' => 'en_attente', 'label' => 'Re&ccedil;ue', 'icon' => 'fa-circle-dot'],
        ['key' => 'prise_en_charge', 'label' => 'Confirm&eacute;e', 'icon' => 'fa-box-open'],
        ['key' => 'livraison_en_cours', 'label' => 'En livraison', 'icon' => 'fa-truck'],
        ['key' => 'livree', 'label' => 'Livr&eacute;e', 'icon' => 'fa-circle-check'],
    ];
    if ($statut === 'annulee') {
        return null;
    }
    if (in_array($statut, ['livree', 'paye'], true)) {
        $result = [];
        foreach ($steps as $s) {
            $result[] = $s + ['state' => 'done'];
        }
        return $result;
    }
    $order = ['en_attente' => 0, 'prise_en_charge' => 1, 'livraison_en_cours' => 2, 'livree' => 3, 'paye' => 3];
    $cur = $order[$statut] ?? -1;
    $result = [];
    foreach ($steps as $i => $s) {
        if ($i < $cur) {
            $result[] = $s + ['state' => 'done'];
        } elseif ($i === $cur) {
            $result[] = $s + ['state' => 'current'];
        } else {
            $result[] = $s + ['state' => 'pending'];
        }
    }
    return $result;
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
    <?php include __DIR__ . '/../includes/pwa_meta.php'; ?>
    <title>Mes commandes &mdash; COLObanes</title>
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/user-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/user-mes-commandes.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/commande-card-uc.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/platform-share-modal.css<?php echo asset_version_query(); ?>">
    <style>
        /* ===== MES COMMANDES v2 ===== */

        .uc-v2-page {
            width: 100%;
            max-width: min(900px, 100%);
            min-width: 0;
            margin: 0 auto;
            padding: clamp(10px, 2.5vw, 36px) clamp(8px, 2.5vw, 24px) 80px;
            display: flex;
            flex-direction: column;
            gap: clamp(10px, 2.5vw, 22px);
            font-family: var(--font-corps);
            box-sizing: border-box;
        }

        body.user-page-mes-commandes .user-content {
            padding: clamp(0.5rem, 2vw, 1.5rem);
            min-width: 0;
            overflow-x: hidden;
        }

        body.user-page-mes-commandes .user-container,
        body.user-page-mes-commandes main#userContent {
            min-width: 0;
            max-width: 100%;
        }

        /* ---- Header ---- */
        .uc-v2-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .uc-v2-header__left {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .uc-v2-header__eyebrow {
            font-size: 0.73rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: var(--bleu-clair, #4a7ab8);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .uc-v2-header__title {
            font-size: clamp(1.3rem, 3vw, 1.75rem);
            font-weight: 800;
            color: var(--titres, #0d0d0d);
            font-family: var(--font-titres);
            line-height: 1.15;
            letter-spacing: -0.025em;
        }

        .uc-v2-header__actions {
            display: flex;
            gap: 9px;
            align-items: center;
            flex-wrap: wrap;
        }

        /* ---- Boutons ---- */
        .uc-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 18px;
            border-radius: 11px;
            font-size: 0.81rem;
            font-weight: 700;
            cursor: pointer;
            border: none;
            text-decoration: none;
            font-family: var(--font-corps);
            transition: all 0.2s;
            white-space: nowrap;
        }

        .uc-btn--primary {
            background: var(--couleur-dominante, #3564a6);
            color: #fff;
            box-shadow: 0 4px 14px rgba(53, 100, 166, 0.25);
        }

        .uc-btn--primary:hover {
            background: var(--bleu-fonce, #2d5690);
            transform: translateY(-1px);
        }

        .uc-btn--outline {
            background: #fff;
            color: var(--couleur-dominante, #3564a6);
            border: 1.5px solid rgba(53, 100, 166, 0.22);
        }

        .uc-btn--outline:hover {
            background: rgba(53, 100, 166, 0.05);
        }

        /* ---- Notifications ---- */
        .uc-v2-notif {
            padding: 13px 18px;
            border-radius: 13px;
            display: flex;
            align-items: center;
            gap: 11px;
            font-size: 0.86rem;
            font-weight: 600;
        }

        .uc-v2-notif--success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.25);
            color: #15803d;
        }

        .uc-v2-notif--error {
            background: rgba(239, 68, 68, 0.08);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #b91c1c;
        }

        /* ---- Hero banner ---- */
        .uc-v2-hero {
            background: linear-gradient(135deg, var(--couleur-dominante, #3564a6) 0%, #1e3f7a 55%, #0f2550 100%);
            border-radius: 20px;
            padding: clamp(20px, 3.5vw, 34px);
            position: relative;
            overflow: hidden;
            box-shadow: 0 16px 44px rgba(53, 100, 166, 0.28);
        }

        .uc-v2-hero::before {
            content: '';
            position: absolute;
            top: -60px;
            right: -40px;
            width: 240px;
            height: 240px;
            background: rgba(255, 255, 255, 0.06);
            border-radius: 50%;
            pointer-events: none;
        }

        .uc-v2-hero__top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }

        .uc-v2-hero__label {
            font-size: 0.73rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: rgba(255, 255, 255, 0.6);
            margin-bottom: 5px;
        }

        .uc-v2-hero__title {
            font-size: clamp(1.5rem, 4vw, 2.2rem);
            font-weight: 900;
            color: #fff;
            font-family: var(--font-titres);
            line-height: 1.1;
            letter-spacing: -0.03em;
        }

        .uc-v2-hero__cta {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.15);
            border: 1.5px solid rgba(255, 255, 255, 0.25);
            border-radius: 11px;
            color: #fff;
            font-size: 0.81rem;
            font-weight: 700;
            text-decoration: none;
            transition: background 0.2s;
            white-space: nowrap;
        }

        .uc-v2-hero__cta:hover {
            background: rgba(255, 255, 255, 0.24);
        }

        /* ---- Onglets ---- */
        .uc-v2-tabs-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 11px;
        }

        .uc-v2-tabs {
            display: flex;
            gap: 5px;
            background: rgba(53, 100, 166, 0.05);
            border-radius: 13px;
            padding: 5px;
            flex-wrap: wrap;
        }

        .uc-v2-tab {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 9px;
            font-size: 0.8rem;
            font-weight: 700;
            text-decoration: none;
            color: var(--gris-moyen, #737373);
            transition: all 0.17s;
            white-space: nowrap;
        }

        .uc-v2-tab:hover {
            color: var(--titres);
            background: rgba(255, 255, 255, 0.8);
        }

        .uc-v2-tab.active {
            background: #fff;
            color: var(--couleur-dominante, #3564a6);
            box-shadow: 0 2px 8px rgba(53, 100, 166, 0.12);
        }

        .uc-v2-tab__count {
            background: rgba(53, 100, 166, 0.09);
            color: var(--couleur-dominante, #3564a6);
            font-size: 0.67rem;
            font-weight: 700;
            padding: 1px 7px;
            border-radius: 50px;
        }

        .uc-v2-tab.active .uc-v2-tab__count {
            background: var(--couleur-dominante, #3564a6);
            color: #fff;
        }

        .uc-v2-tab--warn .uc-v2-tab__count {
            background: rgba(255, 193, 7, 0.15);
            color: #9a6800;
        }

        .uc-v2-tab--warn.active {
            color: #c8960f;
        }

        .uc-v2-tab--warn.active .uc-v2-tab__count {
            background: #c8960f;
        }

        .uc-v2-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
            width: 100%;
            max-width: 100%;
            min-width: 0;
        }

        .uc-v2-card {
            background: #fff;
            border-radius: 18px;
            border: 1px solid rgba(53, 100, 166, 0.09);
            box-shadow: 0 2px 14px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            transition: box-shadow 0.2s;
        }

        .uc-v2-card:hover {
            box-shadow: 0 6px 24px rgba(53, 100, 166, 0.12);
        }

        /* Top bar */
        .uc-v2-card__top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 20px 12px;
            border-bottom: 1px solid rgba(53, 100, 166, 0.06);
            flex-wrap: wrap;
            gap: 10px;
        }

        .uc-v2-card__ref {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 4px;
            min-width: 0;
            flex: 1;
        }

        .uc-v2-card__ref-head {
            display: flex;
            align-items: center;
            gap: 7px;
            min-width: 0;
            width: 100%;
        }

        .uc-v2-card__boutique {
            font-size: 0.92rem;
            font-weight: 800;
            color: var(--titres);
            font-family: var(--font-titres);
            line-height: 1.25;
            word-break: break-word;
        }

        .uc-v2-card__meta-line {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px;
            font-size: 0.72rem;
            color: var(--gris-moyen, #737373);
        }

        .uc-v2-card__ref-num {
            font-weight: 700;
            color: var(--couleur-dominante, #3564a6);
            font-family: var(--font-titres);
        }

        .uc-v2-card__sep {
            opacity: 0.45;
            font-weight: 700;
        }

        .uc-v2-card__date {
            font-size: inherit;
            color: inherit;
            display: inline;
        }

        /* Badges */
        .uc-badge {
            font-size: 0.7rem;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 50px;
            white-space: nowrap;
        }

        .ub--wait {
            background: rgba(255, 193, 7, 0.14);
            color: #9a6800;
        }

        .ub--confirm {
            background: rgba(255, 107, 53, 0.14);
            color: #c04a10;
        }

        .ub--delivery {
            background: rgba(53, 100, 166, 0.12);
            color: var(--bleu-fonce, #2d5690);
        }

        .ub--done {
            background: rgba(34, 197, 94, 0.12);
            color: #15803d;
        }

        .ub--cancel {
            background: rgba(239, 68, 68, 0.1);
            color: #b91c1c;
        }

        /* Body */
        .uc-v2-card__body {
            padding: 16px 20px 10px;
            display: flex;
            align-items: flex-start;
            gap: clamp(10px, 2.5vw, 16px);
            flex-wrap: nowrap;
        }

        .uc-v2-card__body--track {
            cursor: pointer;
            transition: background 0.15s ease;
        }

        .uc-v2-card__body--track:hover {
            background: rgba(53, 100, 166, 0.04);
        }

        .uc-v2-card__body--track:focus-visible {
            outline: 2px solid var(--couleur-dominante, #3564a6);
            outline-offset: -2px;
        }

        .uc-v2-card__body-inner {
            flex: 1;
            min-width: 0;
            display: flex;
            align-items: flex-start;
            gap: clamp(10px, 2.5vw, 20px);
            flex-wrap: wrap;
        }

        .uc-v2-card__thumb {
            flex-shrink: 0;
            position: relative;
            width: clamp(72px, 20vw, 92px);
            height: clamp(72px, 20vw, 92px);
            padding: 0;
            border: 1px solid rgba(53, 100, 166, 0.14);
            border-radius: 14px;
            overflow: hidden;
            background: var(--blanc-neige, #f5f5f5);
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(53, 100, 166, 0.1);
            transition: transform 0.18s ease, box-shadow 0.18s ease;
        }

        .uc-v2-card__thumb:hover {
            transform: scale(1.03);
            box-shadow: 0 6px 18px rgba(53, 100, 166, 0.16);
        }

        .uc-v2-card__thumb:focus-visible {
            outline: none;
            box-shadow: 0 0 0 3px var(--focus-ring, rgba(53, 100, 166, 0.2));
        }

        .uc-v2-card__thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .uc-v2-card__thumb-zoom {
            position: absolute;
            right: 5px;
            bottom: 5px;
            width: 22px;
            height: 22px;
            border-radius: 7px;
            background: rgba(255, 255, 255, 0.92);
            color: var(--couleur-dominante, #3564a6);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.62rem;
            pointer-events: none;
            box-shadow: 0 1px 6px rgba(0, 0, 0, 0.12);
        }

        .uc-v2-card__thumb-count {
            position: absolute;
            left: 5px;
            top: 5px;
            padding: 2px 6px;
            border-radius: 50px;
            background: rgba(13, 13, 13, 0.72);
            color: #fff;
            font-size: 0.58rem;
            font-weight: 700;
            pointer-events: none;
        }

        /* Lightbox galerie produit */
        .uc-gallery-lb {
            position: fixed;
            inset: 0;
            z-index: 7000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: clamp(8px, 3vw, 20px);
        }

        .uc-gallery-lb[hidden] {
            display: none !important;
        }

        .uc-gallery-lb__backdrop {
            position: absolute;
            inset: 0;
            background: rgba(13, 13, 13, 0.88);
            backdrop-filter: blur(4px);
        }

        .uc-gallery-lb__panel {
            position: relative;
            z-index: 1;
            width: min(100%, 720px);
            max-height: min(92vh, 820px);
            display: flex;
            flex-direction: column;
            align-items: stretch;
            gap: 10px;
        }

        .uc-gallery-lb__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            color: #fff;
            padding: 0 4px;
        }

        .uc-gallery-lb__title {
            margin: 0;
            font-size: clamp(0.78rem, 2.5vw, 0.92rem);
            font-weight: 700;
            line-height: 1.3;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .uc-gallery-lb__close {
            flex-shrink: 0;
            width: 36px;
            height: 36px;
            border: none;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.14);
            color: #fff;
            font-size: 1.1rem;
            cursor: pointer;
        }

        .uc-gallery-lb__close:hover {
            background: rgba(255, 255, 255, 0.22);
        }

        .uc-gallery-lb__stage {
            position: relative;
            flex: 1;
            min-height: min(52vh, 420px);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 16px;
            overflow: hidden;
            background: rgba(0, 0, 0, 0.35);
        }

        .uc-gallery-lb__img {
            max-width: 100%;
            max-height: min(72vh, 640px);
            width: auto;
            height: auto;
            object-fit: contain;
            display: block;
        }

        .uc-gallery-lb__nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 38px;
            height: 38px;
            border: none;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.92);
            color: var(--titres, #0d0d0d);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.2);
        }

        .uc-gallery-lb__nav--prev {
            left: 10px;
        }

        .uc-gallery-lb__nav--next {
            right: 10px;
        }

        .uc-gallery-lb__nav[disabled] {
            opacity: 0.35;
            cursor: default;
        }

        .uc-gallery-lb__thumbs {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding: 4px 2px;
            -webkit-overflow-scrolling: touch;
        }

        .uc-gallery-lb__thumbs button {
            flex-shrink: 0;
            width: 52px;
            height: 52px;
            padding: 0;
            border: 2px solid transparent;
            border-radius: 10px;
            overflow: hidden;
            cursor: pointer;
            background: rgba(255, 255, 255, 0.08);
        }

        .uc-gallery-lb__thumbs button.is-active {
            border-color: #fff;
        }

        .uc-gallery-lb__thumbs img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .uc-v2-card__meta-bar {
            padding: 0 20px 14px;
            border-bottom: 1px solid rgba(53, 100, 166, 0.06);
        }

        /* Infos montant + adresse */
        .uc-v2-card__info {
            flex: 1;
            min-width: 0;
        }

        .uc-v2-card__amount {
            font-size: 1.45rem;
            font-weight: 900;
            color: var(--titres);
            font-family: var(--font-titres);
            line-height: 1.05;
            letter-spacing: -0.02em;
        }

        .uc-v2-card__amount small {
            font-size: 0.48em;
            font-weight: 600;
            color: var(--gris-moyen, #737373);
            margin-left: 3px;
        }

        .uc-v2-card__addr {
            display: flex;
            align-items: flex-start;
            gap: 6px;
            font-size: 0.78rem;
            color: var(--gris-moyen, #737373);
            margin-top: 6px;
        }

        .uc-v2-card__addr i {
            color: var(--couleur-dominante, #3564a6);
            font-size: 0.72rem;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .uc-v2-card__tel {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.78rem;
            color: var(--gris-moyen, #737373);
            margin-top: 4px;
            text-decoration: none;
        }

        .uc-v2-card__tel i {
            color: var(--couleur-dominante, #3564a6);
            font-size: 0.72rem;
        }

        .uc-v2-card__tel:hover {
            color: var(--couleur-dominante);
        }

        /* Modal annulation */
        .uc-cancel-modal {
            position: fixed;
            inset: 0;
            z-index: 6000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }

        .uc-cancel-modal[hidden] {
            display: none !important;
        }

        .uc-cancel-modal__backdrop {
            position: absolute;
            inset: 0;
            background: rgba(13, 13, 13, 0.45);
            backdrop-filter: blur(3px);
        }

        .uc-cancel-modal__sheet {
            position: relative;
            z-index: 1;
            width: min(100%, 360px);
            background: #fff;
            border-radius: 18px;
            padding: 22px 20px 18px;
            box-shadow: 0 20px 50px rgba(53, 100, 166, 0.22);
            border: 1px solid rgba(53, 100, 166, 0.1);
        }

        .uc-cancel-modal__title {
            font-size: 1rem;
            font-weight: 800;
            color: var(--titres);
            margin: 0 0 8px;
            font-family: var(--font-titres);
        }

        .uc-cancel-modal__text {
            font-size: 0.86rem;
            color: var(--gris-fonce, #4a4a4a);
            line-height: 1.45;
            margin: 0 0 18px;
        }

        .uc-cancel-modal__actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .uc-cancel-modal__btn {
            border: none;
            border-radius: 12px;
            padding: 11px 14px;
            font-size: 0.84rem;
            font-weight: 700;
            font-family: var(--font-corps);
            cursor: pointer;
            transition: background 0.18s ease, transform 0.15s ease;
        }

        .uc-cancel-modal__btn--no {
            background: rgba(53, 100, 166, 0.08);
            color: var(--gris-fonce, #4a4a4a);
        }

        .uc-cancel-modal__btn--no:hover {
            background: rgba(53, 100, 166, 0.14);
        }

        .uc-cancel-modal__btn--yes {
            background: #b91c1c;
            color: #fff;
        }

        .uc-cancel-modal__btn--yes:hover {
            background: #991b1b;
        }

        /* Timeline */
        .uc-v2-timeline {
            display: flex;
            align-items: center;
            gap: 0;
            min-width: 0;
            flex: 1;
        }

        .uc-tl-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
            flex: 1;
            position: relative;
        }

        .uc-tl-step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 14px;
            left: calc(50% + 14px);
            right: calc(-50% + 14px);
            height: 2px;
            background: rgba(53, 100, 166, 0.12);
            z-index: 0;
        }

        .uc-tl-step--done:not(:last-child)::after {
            background: rgba(34, 197, 94, 0.4);
        }

        .uc-tl-step--current:not(:last-child)::after {
            background: rgba(53, 100, 166, 0.2);
        }

        .uc-tl-dot {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.65rem;
            position: relative;
            z-index: 1;
            flex-shrink: 0;
            transition: transform 0.2s;
        }

        .uc-tl-step--done .uc-tl-dot {
            background: rgba(34, 197, 94, 0.15);
            color: #16a34a;
        }

        .uc-tl-step--current .uc-tl-dot {
            background: var(--couleur-dominante, #3564a6);
            color: #fff;
            box-shadow: 0 3px 10px rgba(53, 100, 166, 0.35);
            animation: pulse-tl 2s ease-in-out infinite;
        }

        .uc-tl-step--pending .uc-tl-dot {
            background: rgba(0, 0, 0, 0.05);
            color: #ccc;
        }

        @keyframes pulse-tl {

            0%,
            100% {
                box-shadow: 0 3px 10px rgba(53, 100, 166, 0.35);
            }

            50% {
                box-shadow: 0 3px 18px rgba(53, 100, 166, 0.55);
            }
        }

        .uc-tl-label {
            font-size: 0.62rem;
            font-weight: 600;
            text-align: center;
            line-height: 1.2;
        }

        .uc-tl-step--done .uc-tl-label {
            color: #16a34a;
        }

        .uc-tl-step--current .uc-tl-label {
            color: var(--couleur-dominante, #3564a6);
            font-weight: 700;
        }

        .uc-tl-step--pending .uc-tl-label {
            color: var(--gris-clair, #a3a3a3);
        }

        /* Footer actions */
        .uc-v2-card__footer {
            display: flex;
            align-items: stretch;
            gap: 8px;
            padding: 12px 20px;
            background: rgba(53, 100, 166, 0.025);
            border-top: 1px solid rgba(53, 100, 166, 0.06);
            flex-wrap: wrap;
        }

        .uc-v2-card__footer form {
            display: inline-flex;
            margin: 0;
        }

        .uc-card-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 9px;
            font-size: clamp(0.72rem, 2.2vw, 0.79rem);
            font-weight: 700;
            border: none;
            cursor: pointer;
            text-decoration: none;
            font-family: var(--font-corps);
            transition: all 0.18s;
            line-height: 1.25;
            text-align: center;
            box-sizing: border-box;
        }

        .uc-card-btn--track {
            background: rgba(53, 100, 166, 0.1);
            color: var(--couleur-dominante, #3564a6);
        }

        .uc-card-btn--track:hover {
            background: rgba(53, 100, 166, 0.18);
        }

        .uc-card-btn--confirm {
            background: rgba(34, 197, 94, 0.12);
            color: #15803d;
        }

        .uc-card-btn--confirm:hover {
            background: rgba(34, 197, 94, 0.2);
        }

        .uc-card-btn--cancel {
            background: rgba(239, 68, 68, 0.08);
            color: #b91c1c;
        }

        .uc-card-btn--cancel:hover {
            background: rgba(239, 68, 68, 0.16);
        }

        .uc-card-btn--reorder {
            background: rgba(255, 107, 53, 0.1);
            color: var(--orange, #FF6B35);
        }

        .uc-card-btn--gmaps {
            background: #fff;
            color: #4285F4;
            border: 1px solid rgba(66, 133, 244, 0.28);
            box-shadow: 0 1px 4px rgba(66, 133, 244, 0.12);
        }

        .uc-card-btn--gmaps:hover {
            background: rgba(66, 133, 244, 0.08);
            border-color: rgba(66, 133, 244, 0.4);
        }

        .uc-card-btn--gmaps .fab.fa-google {
            font-size: 1em;
        }

        .uc-card-btn--wa-share {
            background: #25D366;
            color: #fff;
            box-shadow: 0 2px 8px rgba(37, 211, 102, 0.28);
        }

        .uc-card-btn--wa-share:hover {
            background: #1da851;
            color: #fff;
        }

        .uc-card-btn--reorder:hover {
            background: rgba(255, 107, 53, 0.18);
        }

        .uc-card-btn--rate {
            background: linear-gradient(135deg, rgba(240, 180, 41, 0.22) 0%, rgba(224, 149, 0, 0.14) 100%);
            color: #9a6b00;
            border: 1px solid rgba(240, 180, 41, 0.35);
            box-shadow: 0 2px 8px rgba(240, 180, 41, 0.15);
        }

        .uc-card-btn--rate:hover {
            background: linear-gradient(135deg, rgba(240, 180, 41, 0.32) 0%, rgba(224, 149, 0, 0.22) 100%);
            transform: translateY(-1px);
        }

        .uc-card-btn--detail {
            background: rgba(53, 100, 166, 0.07);
            color: var(--gris-fonce, #4a4a4a);
        }

        .uc-card-btn--detail:hover {
            background: rgba(53, 100, 166, 0.13);
        }

        /* Badge urgence */
        .uc-urgence {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #ef4444;
            display: inline-block;
            animation: pulse-dot 1.5s ease-in-out infinite;
        }

        @keyframes pulse-dot {

            0%,
            100% {
                opacity: 1;
                transform: scale(1);
            }

            50% {
                opacity: 0.6;
                transform: scale(0.8);
            }
        }

        /* Empty state */
        .uc-v2-empty {
            background: #fff;
            border-radius: 18px;
            border: 1px solid rgba(53, 100, 166, 0.08);
            padding: 56px 24px;
            text-align: center;
            color: var(--gris-moyen, #737373);
        }

        .uc-v2-empty__icon {
            width: 68px;
            height: 68px;
            border-radius: 18px;
            background: rgba(53, 100, 166, 0.07);
            color: var(--couleur-dominante, #3564a6);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.7rem;
            margin: 0 auto 16px;
        }

        .uc-v2-empty h3 {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--titres);
            margin-bottom: 6px;
        }

        .uc-v2-empty p {
            font-size: 0.86rem;
            max-width: 340px;
            margin: 0 auto 20px;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .uc-v2-page {
                gap: 18px;
            }

            .uc-v2-card {
                border-radius: 16px;
            }
        }

        @media (max-width: 768px) {
            .uc-v2-page {
                padding: 8px 6px 72px;
                gap: 10px;
            }

            .uc-v2-header__eyebrow {
                font-size: 0.58rem;
            }

            .uc-v2-header__title {
                font-size: 1.05rem;
            }

            .uc-v2-hero {
                padding: 10px 12px;
                border-radius: 14px;
            }

            .uc-v2-hero__title {
                font-size: 1.1rem;
            }

            .uc-v2-hero__label {
                font-size: 0.56rem;
            }

            .uc-v2-hero__cta {
                font-size: 0.66rem;
                padding: 6px 10px;
            }

            .uc-v2-tabs-row {
                overflow: visible;
                width: 100%;
            }

            .uc-v2-tabs {
                width: 100%;
                min-width: 0;
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 4px;
                padding: 4px;
            }

            .uc-v2-tab {
                justify-content: center;
                padding: 6px 4px;
                font-size: 0.62rem;
                white-space: normal;
                text-align: center;
                line-height: 1.2;
            }

            .uc-v2-tab__count {
                font-size: 0.58rem;
                padding: 0 5px;
            }

            .uc-v2-card {
                border-radius: 14px;
            }

            .uc-v2-card__top {
                padding: 8px 10px 6px;
            }

            .uc-v2-card__body {
                padding: 8px 10px 6px;
            }

            .uc-v2-card__meta-bar {
                padding: 0 10px 8px;
            }

            .uc-v2-card__footer {
                padding: 8px 10px;
            }

            .uc-v2-card__boutique {
                font-size: 0.76rem;
            }

            .uc-v2-card__amount {
                font-size: 1.05rem;
            }

            .uc-v2-card__tel {
                font-size: 0.64rem;
            }

            .uc-badge {
                font-size: 0.58rem;
                padding: 2px 7px;
            }

            .uc-v2-empty {
                padding: 36px 16px;
                border-radius: 14px;
            }
        }

        @media (max-width: 380px) {
            .uc-v2-hero__top {
                flex-direction: column;
                align-items: stretch;
                gap: 8px;
            }

            .uc-v2-hero__cta {
                width: 100%;
                justify-content: center;
            }

            .uc-v2-header__title {
                font-size: 0.98rem;
            }

            .uc-v2-tab {
                font-size: 0.58rem;
                padding: 5px 3px;
            }
        }
    </style>
</head>

<body class="user-page-mes-commandes">
    <?php include 'includes/user_nav.php'; ?>

    <div class="uc-v2-page">

        <!-- ===== HEADER ===== -->
        <header class="uc-v2-header">
            <div class="uc-v2-header__left">
                <p class="uc-v2-header__eyebrow"><i class="fas fa-route"></i> Suivi en temps r&eacute;el</p>
                <h1 class="uc-v2-header__title">Mes commandes</h1>
            </div>
        </header>

        <!-- ===== HERO ===== -->
        <div class="uc-v2-hero">
            <div class="uc-v2-hero__top">
                <div>
                    <p class="uc-v2-hero__label">Mes commandes &mdash; Vue d'ensemble</p>
                    <div class="uc-v2-hero__title"><?php echo $nb_total; ?>
                        commande<?php echo $nb_total > 1 ? 's' : ''; ?></div>
                </div>
                <a href="/produits.php" class="uc-v2-hero__cta">
                    <i class="fas fa-shopping-basket"></i> Commander &agrave; nouveau
                </a>
            </div>
        </div>

        <!-- ===== ONGLETS ===== -->
        <div class="uc-v2-tabs-row">
            <div class="uc-v2-tabs" role="tablist">
                <a href="?tab=actives"
                    class="uc-v2-tab uc-v2-tab--warn <?php echo $tab === 'actives' ? 'active' : ''; ?>" role="tab">
                    <i class="fas fa-clock"></i>
                    En cours
                    <span class="uc-v2-tab__count"><?php echo $nb_actives; ?></span>
                </a>
                <a href="?tab=livrees" class="uc-v2-tab <?php echo $tab === 'livrees' ? 'active' : ''; ?>" role="tab">
                    <i class="fas fa-circle-check"></i>
                    Livr&eacute;es
                    <span class="uc-v2-tab__count"><?php echo $nb_livrees; ?></span>
                </a>
                <a href="?tab=annulees" class="uc-v2-tab <?php echo $tab === 'annulees' ? 'active' : ''; ?>" role="tab">
                    <i class="fas fa-ban"></i>
                    Annul&eacute;es
                    <span class="uc-v2-tab__count"><?php echo $nb_annulees; ?></span>
                </a>
            </div>
        </div>

        <!-- ===== LISTE COMMANDES ===== -->
        <?php if (empty($commandes_affichees)): ?>
            <div class="uc-v2-empty">
                <div class="uc-v2-empty__icon">
                    <?php if ($tab === 'livrees'): ?>
                        <i class="fas fa-circle-check"></i>
                    <?php elseif ($tab === 'annulees'): ?>
                        <i class="fas fa-ban"></i>
                    <?php else: ?>
                        <i class="fas fa-bag-shopping"></i>
                    <?php endif; ?>
                </div>
                <?php if ($tab === 'livrees'): ?>
                    <h3>Aucune commande livr&eacute;e</h3>
                    <p>Vos commandes livr&eacute;es apparaîtront ici.</p>
                <?php elseif ($tab === 'annulees'): ?>
                    <h3>Aucune commande annul&eacute;e</h3>
                    <p>Vous n'avez annul&eacute; aucune commande.</p>
                <?php else: ?>
                    <h3>Aucune commande en cours</h3>
                    <p>Vous n'avez pas de commande active. Passez votre premi&egrave;re commande !</p>
                <?php endif; ?>
                <a href="/produits.php" class="uc-btn uc-btn--primary">
                    <i class="fas fa-store"></i> D&eacute;couvrir les produits
                </a>
            </div>
        <?php else: ?>
            <div class="uc-v2-list">
                <?php foreach ($commandes_affichees as $commande):
                    $st = $commande['statut'] ?? 'en_attente';
                    $is_urgent = $st === 'en_attente';
                    $timeline = cmd_timeline_steps($st);
                    $boutique_info = uc_boutique_info_commande($commande);
                    $boutique_nom = $boutique_info['nom'];
                    $boutique_tel = $boutique_info['telephone'];
                    $date_cmd = isset($commande['date_commande'])
                        ? date('d/m/Y', strtotime($commande['date_commande']))
                        : '&mdash;';
                    $can_cancel = in_array($st, ['en_attente', 'confirmee', 'prise_en_charge', 'en_preparation']);
                    $can_confirm = $st === 'livraison_en_cours';
                    $can_reorder = $st === 'annulee';
                    $cmd_id = (int) ($commande['id'] ?? 0);
                    $cmd_avis = ($cmd_id > 0 && isset($commandes_avis_stats[$cmd_id]))
                        ? $commandes_avis_stats[$cmd_id]
                        : ['moyenne' => 0.0, 'count' => 0];
                    $can_noter = in_array($st, ['livree', 'paye'], true)
                        && $cmd_id > 0
                        && !empty($commandes_noter_pending[$cmd_id]);
                    $boutique_maps_url = '';
                    $boutique_wa_url = '';
                    $boutique_geo_lat = null;
                    $boutique_geo_lng = null;
                    $boutique_geo_label = '';
                    $boutique_geo_share_url = '';
                    $vendeur_boutique_id = (int) ($commande['vendeur_id'] ?? 0);
                    if ($vendeur_boutique_id > 0) {
                        $adm_boutique = get_admin_by_id($vendeur_boutique_id);
                        $boutique_geo = boutique_pickup_info_from_admin(
                            $adm_boutique && is_array($adm_boutique) ? $adm_boutique : null,
                            $boutique_nom
                        );
                        $boutique_maps_url = trim((string) ($boutique_geo['maps_url'] ?? ''));
                        $boutique_wa_url = trim((string) ($boutique_geo['whatsapp_url'] ?? ''));
                        $boutique_geo_lat = $boutique_geo['lat'] ?? null;
                        $boutique_geo_lng = $boutique_geo['lng'] ?? null;
                        $boutique_geo_label = 'Point de retrait — ' . $boutique_nom;
                        if ($boutique_geo_lat !== null && $boutique_geo_lng !== null) {
                            $boutique_geo_share_url = 'https://maps.google.com/?q=' . $boutique_geo_lat . ',' . $boutique_geo_lng;
                        } elseif ($boutique_maps_url !== '') {
                            $boutique_geo_share_url = $boutique_maps_url;
                        }
                    }
                    $galerie_pack = commande_carte_galerie_urls($cmd_id, $boutique_nom);
                    $cmd_galerie_urls = $galerie_pack['urls'];
                    $cmd_galerie_nom = $galerie_pack['nom'];
                    $cmd_thumb_src = $galerie_pack['thumb_url'];
                    ?>
                    <article class="uc-v2-card">

                        <!-- Top bar -->
                        <div class="uc-v2-card__top">
                            <div class="uc-v2-card__ref">
                                <div class="uc-v2-card__ref-head">
                                    <?php if ($is_urgent): ?><span class="uc-urgence"
                                            title="Action possible"></span><?php endif; ?>
                                    <span class="uc-v2-card__boutique"><?php echo htmlspecialchars($boutique_nom); ?></span>
                                </div>
                            </div>
                            <span class="uc-badge <?php echo cmd_user_badge($st); ?>">
                                <i class="fas <?php echo cmd_user_icon($st); ?>" style="font-size:.7em;margin-right:3px;"></i>
                                <?php echo cmd_user_label($st); ?>
                            </span>
                        </div>

                        <!-- Body -->
                        <?php $cmd_track_url = 'commande-categorie.php?commande_id=' . (int) $commande['id']; ?>
                        <div class="uc-v2-card__body uc-v2-card__body--track"
                            data-track-url="<?php echo htmlspecialchars($cmd_track_url, ENT_QUOTES, 'UTF-8'); ?>"
                            role="link" tabindex="0"
                            aria-label="Voir le suivi de la commande #<?php echo htmlspecialchars($commande['numero_commande'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php if (!empty($cmd_galerie_urls)): ?>
                                <button type="button" class="uc-v2-card__thumb uc-btn-open-gallery"
                                    data-gallery="<?php echo htmlspecialchars(json_encode($cmd_galerie_urls, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-gallery-title="<?php echo htmlspecialchars($cmd_galerie_nom, ENT_QUOTES, 'UTF-8'); ?>"
                                    aria-label="Voir les photos du produit <?php echo htmlspecialchars($cmd_galerie_nom, ENT_QUOTES, 'UTF-8'); ?>">
                                    <img src="<?php echo htmlspecialchars($cmd_thumb_src, ENT_QUOTES, 'UTF-8'); ?>"
                                        alt="<?php echo htmlspecialchars($cmd_galerie_nom, ENT_QUOTES, 'UTF-8'); ?>" loading="lazy"
                                        onerror="this.src='/image/produit1.jpg'">
                                    <?php if (count($cmd_galerie_urls) > 1): ?>
                                        <span class="uc-v2-card__thumb-count">+<?php echo count($cmd_galerie_urls) - 1; ?></span>
                                    <?php endif; ?>
                                    <span class="uc-v2-card__thumb-zoom" aria-hidden="true"><i class="fas fa-expand"></i></span>
                                </button>
                            <?php endif; ?>
                            <div class="uc-v2-card__body-inner">
                                <!-- Montant + infos -->
                                <div class="uc-v2-card__info">
                                    <div class="uc-v2-card__amount">
                                        <?php echo number_format((float) $commande['montant_total'], 0, ',', ' '); ?><small>FCFA</small>
                                    </div>
                                    <?php if ($boutique_tel !== ''): ?>
                                        <a href="tel:<?php echo htmlspecialchars(preg_replace('/\s+/', '', $boutique_tel)); ?>"
                                            class="uc-v2-card__tel uc-v2-card__tel--boutique">
                                            <i class="fas fa-store"></i>
                                            <?php echo htmlspecialchars($boutique_tel); ?>
                                        </a>
                                    <?php endif; ?>
                                </div>

                                <!-- Timeline -->
                                <?php if ($timeline !== null): ?>
                                    <div class="uc-v2-timeline" aria-label="Avancement de la commande">
                                        <?php foreach ($timeline as $step): ?>
                                            <div class="uc-tl-step uc-tl-step--<?php echo $step['state']; ?>">
                                                <div class="uc-tl-dot">
                                                    <i class="fas <?php echo $step['icon']; ?>"></i>
                                                </div>
                                                <span class="uc-tl-label"><?php echo $step['label']; ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="uc-v2-card__meta-bar">
                            <span class="uc-v2-card__meta-line">
                                <span
                                    class="uc-v2-card__ref-num">#<?php echo htmlspecialchars($commande['numero_commande']); ?></span>
                                <span class="uc-v2-card__sep" aria-hidden="true">&middot;</span>
                                <span class="uc-v2-card__date"><?php echo $date_cmd; ?></span>
                            </span>
                        </div>

                        <!-- Footer actions -->
                        <div class="uc-v2-card__footer">
                            <?php if ($boutique_maps_url !== '' || ($boutique_geo_lat !== null && $boutique_geo_lng !== null)): ?>
                                <button type="button" class="uc-card-btn uc-card-btn--gmaps js-geo-open-maps"
                                    title="Ouvrir avec une application de navigation"
                                    data-lat="<?php echo $boutique_geo_lat !== null ? htmlspecialchars((string) $boutique_geo_lat, ENT_QUOTES, 'UTF-8') : ''; ?>"
                                    data-lng="<?php echo $boutique_geo_lng !== null ? htmlspecialchars((string) $boutique_geo_lng, ENT_QUOTES, 'UTF-8') : ''; ?>"
                                    data-label="<?php echo htmlspecialchars($boutique_geo_label, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-maps-url="<?php echo htmlspecialchars($boutique_maps_url, ENT_QUOTES, 'UTF-8'); ?>">
                                    <i class="fas fa-location-dot" aria-hidden="true"></i> Ouvrir avec une application de navigation
                                </button>
                            <?php endif; ?>
                            <?php if ($boutique_geo_share_url !== ''): ?>
                                <button type="button" class="uc-card-btn uc-card-btn--wa-share js-geo-share-location"
                                    title="Partager la position de la boutique"
                                    data-lat="<?php echo $boutique_geo_lat !== null ? htmlspecialchars((string) $boutique_geo_lat, ENT_QUOTES, 'UTF-8') : ''; ?>"
                                    data-lng="<?php echo $boutique_geo_lng !== null ? htmlspecialchars((string) $boutique_geo_lng, ENT_QUOTES, 'UTF-8') : ''; ?>"
                                    data-label="<?php echo htmlspecialchars($boutique_geo_label, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-share-title="<?php echo htmlspecialchars($boutique_geo_label, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-share-url="<?php echo htmlspecialchars($boutique_geo_share_url, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-share-text="<?php echo htmlspecialchars($boutique_geo_label, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-share-modal-title="Partager la position de la boutique"
                                    data-share-hint="Partagez le point de retrait de la boutique avec vos proches.">
                                    <i class="fab fa-whatsapp" aria-hidden="true"></i> Partager la position de la boutique
                                </button>
                            <?php endif; ?>

                            <a href="commande-categorie.php?commande_id=<?php echo (int) $commande['id']; ?>"
                                class="uc-card-btn uc-card-btn--track">
                                <i class="fas fa-route"></i> Suivre ma commande
                            </a>

                            <?php if ($can_noter): ?>
                                <button type="button" class="uc-card-btn uc-card-btn--rate uc-btn-open-rating"
                                    data-commande-id="<?php echo $cmd_id; ?>" aria-label="Noter les produits de cette commande">
                                    <i class="fas fa-star"></i> Noter
                                </button>
                            <?php endif; ?>

                            <?php if ($can_confirm): ?>
                                <form method="post" action="" style="display:inline;">
                                    <input type="hidden" name="commande_id" value="<?php echo (int) $commande['id']; ?>">
                                    <button type="submit" name="confirmer_livraison" class="uc-card-btn uc-card-btn--confirm"
                                        onclick="return confirm('Confirmez-vous la r&eacute;ception de votre colis ?');">
                                        <i class="fas fa-check-circle"></i> Colis re&ccedil;u
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if ($can_reorder): ?>
                                <form method="post" action="" style="display:inline;">
                                    <input type="hidden" name="commande_id" value="<?php echo (int) $commande['id']; ?>">
                                    <button type="submit" name="recommander" class="uc-card-btn uc-card-btn--reorder">
                                        <i class="fas fa-rotate-right"></i> Recommander
                                    </button>
                                </form>
                            <?php endif; ?>

                        </div>

                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div><!-- /.uc-v2-page -->

    <?php require __DIR__ . '/../includes/partials/uc_gallery_lightbox.php'; ?>

    <script>
        (function () {
            document.querySelectorAll('.uc-v2-card__body--track').forEach(function (body) {
                var url = body.getAttribute('data-track-url') || '';
                if (!url) {
                    return;
                }
                function goToTrack(e) {
                    if (e.target.closest('a, button, input, select, textarea, label')) {
                        return;
                    }
                    window.location.href = url;
                }
                body.addEventListener('click', goToTrack);
                body.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        goToTrack(e);
                    }
                });
            });
        })();
    </script>
    <?php require __DIR__ . '/../includes/partials/platform_share_modal.php'; ?>
    <script src="/js/platform-share-modal.js<?php echo asset_version_query(); ?>"></script>
    <script src="/js/geo-nav-apps.js<?php echo asset_version_query(); ?>"></script>
    <script src="/js/uc-gallery-lightbox.js<?php echo asset_version_query(); ?>"></script>

    <?php include 'includes/user_footer.php'; ?>