<?php
require_once __DIR__ . '/includes/session_user.php';
/**
 * Page de commande
 * Programmation procédurale uniquement
 */

session_start_persistent();

require_once __DIR__ . '/includes/guest_client.php';
require_once __DIR__ . '/includes/panier_invite.php';
require_once __DIR__ . '/includes/asset_version.php';

$is_guest_checkout = !isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0;

// Inclusion des modèles et contrôleurs
require_once __DIR__ . '/models/model_panier.php';
require_once __DIR__ . '/models/model_users.php';
require_once __DIR__ . '/models/model_zones_livraison.php';
require_once __DIR__ . '/includes/geo_location.php';
require_once __DIR__ . '/controllers/controller_commandes.php';

$zones_livraison = get_all_zones_livraison('actif');
$zone_retrait = zones_livraison_find_retrait($zones_livraison);
$zones_livraison_delivery = zones_livraison_filter_delivery($zones_livraison, $zone_retrait);
$commande_mode_selected = commande_mode_livraison_normalize($_POST['mode_livraison'] ?? 'livraison');

// Traitement du formulaire
$message = '';
$message_type = '';
$commande_id = null;
$numero_commande = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_commande') {
    $result = process_create_commande();

    if ($result['success']) {
        require_once __DIR__ . '/services/notifications_order_dispatch.php';
        notifications_dispatch_after_commande($result);

        if (!empty($result['is_guest'])) {
            header('Location: /commande.php?success=1&numero=' . urlencode($result['numero_commande']));
        } else {
            header('Location: /user/mes-commandes.php?success=1&numero=' . urlencode($result['numero_commande']));
        }
        exit;
    } else {
        $message = $result['message'];
        $message_type = 'error';
    }
}

// Page de confirmation invité
$commande_success = isset($_GET['success']) && $_GET['success'] === '1';
$commande_numero = isset($_GET['numero']) ? trim($_GET['numero']) : '';

if ($commande_success && $commande_numero !== '') {
    include 'nav_bar.php';
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/includes/pwa_meta.php'; ?>
    <title>Commande confirmée - Sugar Paper</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/style.css<?php echo asset_version_query(); ?>">
    <style>
        body.commande-success-page {
            margin: 0;
            min-height: 100vh;
            background:
                radial-gradient(ellipse 80% 60% at 50% -10%, rgba(145, 138, 68, 0.22), transparent 60%),
                radial-gradient(ellipse 60% 50% at 100% 100%, rgba(194, 102, 56, 0.12), transparent 55%),
                linear-gradient(165deg, #fffaf7 0%, #ffffff 45%, #f9f6f0 100%);
            font-family: var(--font-corps, 'Segoe UI', system-ui, sans-serif);
            color: #1a1a1a;
        }

        .commande-success-wrap {
            max-width: 640px;
            margin: 0 auto;
            padding: 48px 20px 72px;
        }

        .commande-success-card {
            position: relative;
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(145, 138, 68, 0.25);
            border-radius: 24px;
            padding: 40px 32px 36px;
            box-shadow:
                0 24px 60px rgba(107, 47, 32, 0.08),
                0 8px 24px rgba(0, 0, 0, 0.04);
            text-align: center;
            overflow: hidden;
        }

        .commande-success-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #918a44, #c26638, #918a44);
        }

        .commande-success-icon {
            width: 88px;
            height: 88px;
            margin: 0 auto 24px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(145, 138, 68, 0.18), rgba(194, 102, 56, 0.12));
            display: flex;
            align-items: center;
            justify-content: center;
            animation: successPop 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) both;
        }

        .commande-success-icon i {
            font-size: 42px;
            color: #918a44;
        }

        @keyframes successPop {
            from { transform: scale(0.5); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .commande-success-eyebrow {
            display: inline-block;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #c26638;
            margin-bottom: 10px;
        }

        .commande-success-title {
            font-family: var(--font-titres, Georgia, serif);
            font-size: clamp(1.6rem, 4vw, 2rem);
            font-weight: 700;
            color: #6b2f20;
            margin: 0 0 12px;
            line-height: 1.25;
        }

        .commande-success-lead {
            font-size: 16px;
            color: #555;
            margin: 0 0 28px;
            line-height: 1.6;
        }

        .commande-success-numero {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            padding: 18px 28px;
            background: linear-gradient(135deg, rgba(145, 138, 68, 0.1), rgba(194, 102, 56, 0.06));
            border: 1px dashed rgba(145, 138, 68, 0.45);
            border-radius: 14px;
            margin-bottom: 28px;
        }

        .commande-success-numero__label {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #918a44;
        }

        .commande-success-numero__value {
            font-size: clamp(1.1rem, 3.5vw, 1.35rem);
            font-weight: 800;
            color: #000;
            letter-spacing: 0.04em;
            font-family: ui-monospace, 'Cascadia Code', monospace;
        }

        .commande-success-steps {
            list-style: none;
            margin: 0 0 32px;
            padding: 0;
            text-align: left;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .commande-success-steps li {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 14px 16px;
            background: #faf9f6;
            border-radius: 12px;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .commande-success-steps li.is-done .commande-success-step-icon {
            background: rgba(145, 138, 68, 0.2);
            color: #918a44;
        }

        .commande-success-step-icon {
            flex-shrink: 0;
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: rgba(194, 102, 56, 0.12);
            color: #c26638;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
        }

        .commande-success-step-text strong {
            display: block;
            font-size: 14px;
            color: #6b2f20;
            margin-bottom: 2px;
        }

        .commande-success-step-text span {
            font-size: 13px;
            color: #666;
            line-height: 1.45;
        }

        .commande-success-step-text a {
            color: #918a44;
            font-weight: 600;
            text-decoration: none;
        }

        .commande-success-step-text a:hover {
            color: #6b2f20;
            text-decoration: underline;
        }

        .commande-success-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: center;
        }

        .commande-success-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 14px 24px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .commande-success-btn--primary {
            background: linear-gradient(135deg, #918a44, #7a7340);
            color: #fff;
            box-shadow: 0 4px 16px rgba(145, 138, 68, 0.35);
        }

        .commande-success-btn--primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(145, 138, 68, 0.4);
            color: #fff;
        }

        .commande-success-btn--secondary {
            background: #fff;
            color: #6b2f20;
            border: 1px solid rgba(107, 47, 32, 0.2);
        }

        .commande-success-btn--secondary:hover {
            background: #faf9f6;
            color: #6b2f20;
        }

        .commande-success-contact {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            width: 100%;
            max-width: 320px;
            margin: 0 auto 26px;
            padding: 14px 18px;
            background: #fff;
            border: 1px solid rgba(145, 138, 68, 0.28);
            border-radius: 12px;
        }

        .commande-success-contact__label {
            font-size: 12px;
            font-weight: 600;
            color: #6b2f20;
            margin: 0;
        }

        .commande-success-contact__phone {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 1.05rem;
            font-weight: 700;
            color: #918a44;
            text-decoration: none;
            letter-spacing: 0.02em;
        }

        .commande-success-contact__phone i {
            font-size: 1rem;
            color: #c26638;
        }

        .commande-success-contact__phone:hover {
            color: #6b2f20;
        }

        @media (max-width: 768px) {
            .commande-success-wrap {
                padding: 32px 16px 56px;
            }

            .commande-success-card {
                padding: 28px 22px 24px;
                border-radius: 18px;
            }

            .commande-success-icon {
                width: 68px;
                height: 68px;
                margin-bottom: 18px;
            }

            .commande-success-icon i {
                font-size: 32px;
            }

            .commande-success-eyebrow {
                font-size: 10px;
                margin-bottom: 8px;
            }

            .commande-success-title {
                font-size: 1.35rem;
                margin-bottom: 10px;
            }

            .commande-success-lead {
                font-size: 14px;
                margin-bottom: 20px;
            }

            .commande-success-numero {
                padding: 14px 20px;
                margin-bottom: 20px;
            }

            .commande-success-numero__value {
                font-size: 1rem;
            }

            .commande-success-contact {
                max-width: 100%;
                padding: 12px 14px;
                margin-bottom: 20px;
            }

            .commande-success-contact__phone {
                font-size: 0.95rem;
            }

            .commande-success-steps {
                gap: 10px;
                margin-bottom: 24px;
            }

            .commande-success-steps li {
                gap: 10px;
                padding: 10px 12px;
                border-radius: 10px;
            }

            .commande-success-step-icon {
                width: 30px;
                height: 30px;
                font-size: 13px;
                border-radius: 8px;
            }

            .commande-success-step-text strong {
                font-size: 13px;
            }

            .commande-success-step-text span {
                font-size: 12px;
            }

            .commande-success-btn {
                padding: 12px 18px;
                font-size: 14px;
                border-radius: 10px;
            }
        }

        @media (max-width: 480px) {
            .commande-success-wrap {
                padding: 24px 12px 48px;
            }

            .commande-success-card {
                padding: 22px 16px 20px;
                border-radius: 14px;
            }

            .commande-success-icon {
                width: 56px;
                height: 56px;
                margin-bottom: 14px;
            }

            .commande-success-icon i {
                font-size: 26px;
            }

            .commande-success-title {
                font-size: 1.15rem;
            }

            .commande-success-lead {
                font-size: 13px;
                margin-bottom: 16px;
                line-height: 1.5;
            }

            .commande-success-numero {
                padding: 12px 16px;
                margin-bottom: 16px;
                border-radius: 10px;
            }

            .commande-success-numero__label {
                font-size: 10px;
            }

            .commande-success-numero__value {
                font-size: 0.88rem;
            }

            .commande-success-contact {
                padding: 10px 12px;
                margin-bottom: 16px;
                gap: 6px;
            }

            .commande-success-contact__label {
                font-size: 11px;
            }

            .commande-success-contact__phone {
                font-size: 0.88rem;
                gap: 6px;
            }

            .commande-success-steps {
                gap: 8px;
                margin-bottom: 20px;
            }

            .commande-success-steps li {
                padding: 8px 10px;
            }

            .commande-success-step-icon {
                width: 26px;
                height: 26px;
                font-size: 11px;
            }

            .commande-success-step-text strong {
                font-size: 12px;
            }

            .commande-success-step-text span {
                font-size: 11px;
            }

            .commande-success-actions {
                flex-direction: column;
                gap: 8px;
            }

            .commande-success-btn {
                width: 100%;
                padding: 11px 16px;
                font-size: 13px;
            }
        }
    </style>
</head>
<body class="commande-success-page">
    <div class="commande-success-wrap">
        <div class="commande-success-card">
            <div class="commande-success-icon" aria-hidden="true">
                <i class="fas fa-check-circle"></i>
            </div>
            <p class="commande-success-eyebrow">Merci pour votre confiance</p>
            <h1 class="commande-success-title">Votre commande est confirmée</h1>
            <p class="commande-success-lead">
                Nous avons bien enregistré votre commande. Notre équipe vous contactera très prochainement pour organiser la livraison.
            </p>

            <div class="commande-success-numero">
                <span class="commande-success-numero__label">Numéro de commande</span>
                <span class="commande-success-numero__value"><?php echo htmlspecialchars($commande_numero); ?></span>
            </div>

            <div class="commande-success-contact">
                <p class="commande-success-contact__label">Une question ? Contactez Sugar Paper</p>
                <a href="tel:+221773292123" class="commande-success-contact__phone">
                    <i class="fas fa-phone-alt" aria-hidden="true"></i>
                    +221 77 329 2123
                </a>
            </div>

            <ul class="commande-success-steps">
                <li class="is-done">
                    <span class="commande-success-step-icon"><i class="fas fa-clipboard-check"></i></span>
                    <div class="commande-success-step-text">
                        <strong>Commande reçue</strong>
                        <span>Votre demande a été transmise à notre équipe.</span>
                    </div>
                </li>
                <li>
                    <span class="commande-success-step-icon"><i class="fas fa-box-open"></i></span>
                    <div class="commande-success-step-text">
                        <strong>Préparation</strong>
                        <span>Nous préparons vos produits avec soin.</span>
                    </div>
                </li>
                <li>
                    <span class="commande-success-step-icon"><i class="fas fa-truck"></i></span>
                    <div class="commande-success-step-text">
                        <strong>Livraison</strong>
                        <span>Vous serez contacté(e) au numéro indiqué ou au <a href="tel:+221773292123">+221 77 329 2123</a>.</span>
                    </div>
                </li>
            </ul>

            <div class="commande-success-actions">
                <a href="/index.php" class="commande-success-btn commande-success-btn--primary">
                    <i class="fas fa-home"></i> Retour à l'accueil
                </a>
                <a href="/produits.php" class="commande-success-btn commande-success-btn--secondary">
                    <i class="fas fa-store"></i> Continuer mes achats
                </a>
            </div>
        </div>
    </div>
    <?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
    <?php
    exit;
}

if ($is_guest_checkout && !guest_client_has_info()) {
    header('Location: /panier.php');
    exit;
}

$user = null;
if (!$is_guest_checkout) {
    $user = get_user_by_id((int) $_SESSION['user_id']);
}

// Récupérer les produits du panier
$panier_items = panier_get_items_courant();

// Vérifier que le panier n'est pas vide
if (empty($panier_items)) {
    header('Location: /panier.php');
    exit;
}

// S'il n'y a aucune zone de livraison, le formulaire affichera un message et sera désactivé

// Calculer le total
$panier_total = panier_get_total_courant();
$nombre_total_articles = 0;
foreach ($panier_items as $item) {
    $nombre_total_articles += $item['quantite'];
}

// Inclusion de la barre de navigation
include 'nav_bar.php';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/includes/pwa_meta.php'; ?>
    <title>Passer la commande - Sugar Paper</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/a_style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <style>
    .commande-container {
        max-width: 1200px;
        margin: 40px auto;
        padding: 0 20px;
    }

    .commande-wrapper {
        display: grid;
        grid-template-columns: 1fr 400px;
        gap: 30px;
        margin-top: 30px;
    }

    @media (max-width: 968px) {
        .commande-wrapper {
            grid-template-columns: 1fr;
        }
    }

    .commande-form-section {
        background: var(--glass-bg);
        backdrop-filter: blur(15px);
        border: 1px solid var(--glass-border);
        border-radius: 12px;
        padding: 30px;
        box-shadow: var(--glass-shadow);
    }

    .commande-summary-section {
        background: var(--glass-bg);
        backdrop-filter: blur(15px);
        border: 1px solid var(--glass-border);
        border-radius: 12px;
        padding: 30px;
        box-shadow: var(--glass-shadow);
        height: fit-content;
        position: sticky;
        top: 20px;
    }

    .section-title {
        font-size: 24px;
        font-weight: 700;
        color: var(--titres);
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 1px solid rgba(229, 72, 138, 0.2);
        font-family: var(--font-titres);
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        font-weight: 600;
        color: var(--titres);
        margin-bottom: 8px;
        font-size: 14px;
    }

    .form-group select {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid rgba(229, 72, 138, 0.2);
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.3s;
        font-family: inherit;
        background: rgba(255, 255, 255, 0.8);
        cursor: pointer;
    }

    .form-group select:focus {
        outline: none;
        border-color: var(--couleur-dominante);
        box-shadow: 0 0 0 3px rgba(229, 72, 138, 0.15);
    }

    .form-group input,
    .form-group textarea {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid rgba(229, 72, 138, 0.2);
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.3s;
        font-family: inherit;
        background: rgba(255, 255, 255, 0.8);
    }

    .form-group input:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: var(--couleur-dominante);
        box-shadow: 0 0 0 3px rgba(229, 72, 138, 0.15);
    }

    .form-group textarea {
        resize: vertical;
        min-height: 100px;
    }

    .form-group small {
        display: block;
        color: #737373;
        font-size: 12px;
        margin-top: 5px;
    }

    .cmd-mode-switch {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin-bottom: 22px;
        padding: 6px;
        background: rgba(229, 72, 138, 0.08);
        border: 1px solid rgba(229, 72, 138, 0.22);
        border-radius: 12px;
    }

    .cmd-mode-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 12px 14px;
        border: 2px solid rgba(229, 72, 138, 0.25);
        border-radius: 10px;
        background: #ffffff;
        color: #6b2f20;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        font-family: inherit;
    }

    .cmd-mode-btn:hover {
        border-color: #e5488a;
        color: #e5488a;
    }

    .cmd-mode-btn.is-active {
        background: #e5488a;
        color: #ffffff;
        border-color: #e5488a;
        box-shadow: 0 4px 14px rgba(229, 72, 138, 0.28);
    }

    .cmd-mode-btn:disabled {
        opacity: 0.55;
        cursor: not-allowed;
    }

    .cmd-mode-panel {
        display: none;
    }

    .cmd-mode-panel.is-visible {
        display: block;
    }

    .cmd-retrait-info {
        padding: 14px 16px;
        border-radius: 10px;
        background: rgba(145, 138, 68, 0.1);
        border: 1px dashed rgba(145, 138, 68, 0.45);
        color: #6b2f20;
        font-size: 14px;
        line-height: 1.55;
        margin-bottom: 8px;
    }

    .commande-geo-box {
        margin-top: 8px;
        padding: 16px;
        border-radius: 12px;
        border: 1px solid rgba(145, 138, 68, 0.28);
        background: rgba(255, 255, 255, 0.72);
    }

    .commande-geo-map {
        width: 100%;
        height: 260px;
        min-height: 260px;
        border-radius: 10px;
        overflow: hidden;
        border: 1px solid rgba(0, 0, 0, 0.08);
        margin-bottom: 12px;
        display: block;
        background: #f3f0e8;
    }

    .commande-geo-map .leaflet-container {
        width: 100%;
        height: 100%;
        min-height: 260px;
        border-radius: 10px;
        font-family: inherit;
    }

    .btn-commande-geo-refresh {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 14px;
        border: none;
        border-radius: 8px;
        background: #918a44;
        color: #ffffff;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s ease;
        margin-bottom: 10px;
    }

    .btn-commande-geo-refresh:hover {
        background: #6b2f20;
    }

    .commande-geo-status {
        font-size: 13px;
        line-height: 1.5;
        padding: 10px 12px;
        border-radius: 8px;
        display: none;
    }

    .commande-geo-status[data-geo-state="pending"] {
        background: rgba(145, 138, 68, 0.12);
        color: #6b2f20;
    }

    .commande-geo-status[data-geo-state="ok"] {
        background: rgba(145, 138, 68, 0.15);
        color: #6b2f20;
    }

    .commande-geo-status[data-geo-state="error"] {
        background: rgba(194, 102, 56, 0.12);
        color: #6b2f20;
    }

    .summary-item {
        display: flex;
        justify-content: space-between;
        padding: 12px 0;
        border-bottom: 1px solid rgba(229, 72, 138, 0.15);
    }

    .summary-item:last-child {
        border-bottom: none;
    }

    .summary-item-label {
        color: var(--texte-fonce);
        font-size: 14px;
    }

    .summary-item-value {
        color: var(--titres);
        font-weight: 600;
        font-size: 14px;
    }

    .summary-total {
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid var(--couleur-dominante);
    }

    .summary-total .summary-item-label {
        font-size: 18px;
        font-weight: 700;
        color: var(--titres);
    }

    .summary-total .summary-item-value {
        font-size: 20px;
        color: var(--accent-promo);
    }

    .btn-submit-commande {
        width: 100%;
        padding: 15px;
        background: var(--couleur-dominante);
        color: var(--texte-clair);
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        margin-top: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }

    .btn-submit-commande:hover {
        background: rgba(229, 72, 138, 0.9);
        transform: translateY(-2px);
        box-shadow: var(--ombre-promo);
        color: var(--texte-clair);
    }

    .btn-submit-commande:disabled {
        background-color: #cccccc;
        cursor: not-allowed;
        transform: none;
    }

    .message {
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-weight: 500;
    }

    .message.error {
        background: rgba(229, 72, 138, 0.1);
        color: var(--titres);
        border: 1px solid rgba(229, 72, 138, 0.3);
    }

    .panier-item-summary {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 12px 0;
        border-bottom: 1px solid rgba(229, 72, 138, 0.15);
    }

    .panier-item-summary:last-child {
        border-bottom: none;
    }

    .panier-item-summary img {
        width: 60px;
        height: 60px;
        object-fit: cover;
        border-radius: 8px;
    }

    .panier-item-summary-info {
        flex: 1;
    }

    .panier-item-summary-info h4 {
        font-size: 14px;
        color: var(--titres);
        margin-bottom: 5px;
        font-weight: 600;
    }

    .panier-item-summary-info p {
        font-size: 12px;
        color: #737373;
        margin: 0;
    }

    .panier-item-summary-price {
        font-size: 14px;
        font-weight: 600;
        color: var(--titres);
    }

    .commande-page-title {
        font-size: 28px;
        color: var(--titres);
        margin-bottom: 10px;
        font-family: var(--font-titres);
    }

    .commande-page-subtitle {
        color: #737373;
        margin-bottom: 30px;
    }

    .summary-livraison {
        color: #737373;
    }

    .commande-link-retour {
        display: block;
        text-align: center;
        margin-top: 20px;
        color: var(--couleur-dominante);
        text-decoration: none;
        font-weight: 500;
    }

    .commande-link-retour:hover {
        text-decoration: underline;
    }

    /* Styles pour éviter que le footer s'incruste */
    .commande-container {
        margin-bottom: 100px;
        min-height: calc(100vh - 200px);
    }

    /* Footer - hérite du style global a_style.css */

    /* ——— Overlay loader confirmation commande ——— */
    .commande-loader-overlay {
        position: fixed;
        inset: 0;
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px;
        background: rgba(26, 18, 12, 0.52);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        animation: commandeLoaderFadeIn 0.35s ease-out both;
    }

    .commande-loader-overlay[hidden] {
        display: none !important;
    }

    @keyframes commandeLoaderFadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    .commande-loader-card {
        position: relative;
        width: 100%;
        max-width: 400px;
        padding: 40px 32px 36px;
        text-align: center;
        background: linear-gradient(165deg, #ffffff 0%, #fdfbf7 100%);
        border: 1px solid rgba(145, 138, 68, 0.35);
        border-radius: 24px;
        box-shadow:
            0 32px 80px rgba(107, 47, 32, 0.18),
            0 12px 32px rgba(0, 0, 0, 0.08);
        overflow: hidden;
        animation: commandeLoaderSlideUp 0.45s cubic-bezier(0.34, 1.2, 0.64, 1) both;
    }

    @keyframes commandeLoaderSlideUp {
        from {
            opacity: 0;
            transform: translateY(24px) scale(0.96);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    .commande-loader-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, #918a44, #c26638, #918a44);
        background-size: 200% 100%;
        animation: commandeLoaderBar 2s linear infinite;
    }

    @keyframes commandeLoaderBar {
        0% { background-position: 100% 0; }
        100% { background-position: -100% 0; }
    }

    .commande-loader-spinner {
        position: relative;
        width: 72px;
        height: 72px;
        margin: 0 auto 28px;
    }

    .commande-loader-spinner__ring {
        position: absolute;
        inset: 0;
        border-radius: 50%;
        border: 3px solid transparent;
    }

    .commande-loader-spinner__ring--outer {
        border-top-color: #918a44;
        border-right-color: rgba(145, 138, 68, 0.25);
        animation: commandeLoaderSpin 1.1s cubic-bezier(0.5, 0, 0.5, 1) infinite;
    }

    .commande-loader-spinner__ring--inner {
        inset: 10px;
        border-bottom-color: #c26638;
        border-left-color: rgba(194, 102, 56, 0.2);
        animation: commandeLoaderSpin 0.85s cubic-bezier(0.5, 0, 0.5, 1) infinite reverse;
    }

    .commande-loader-spinner__icon {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        color: #6b2f20;
        animation: commandeLoaderPulse 1.4s ease-in-out infinite;
    }

    @keyframes commandeLoaderSpin {
        to { transform: rotate(360deg); }
    }

    @keyframes commandeLoaderPulse {
        0%, 100% { opacity: 0.65; transform: scale(0.95); }
        50% { opacity: 1; transform: scale(1); }
    }

    .commande-loader-title {
        font-family: var(--font-titres, Georgia, serif);
        font-size: 1.35rem;
        font-weight: 700;
        color: #6b2f20;
        margin: 0 0 10px;
        line-height: 1.3;
    }

    .commande-loader-text {
        font-size: 15px;
        color: #555;
        margin: 0 0 22px;
        line-height: 1.55;
    }

    .commande-loader-dots {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .commande-loader-dots span {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #918a44;
        animation: commandeLoaderDot 1.2s ease-in-out infinite;
    }

    .commande-loader-dots span:nth-child(2) {
        background: #c26638;
        animation-delay: 0.15s;
    }

    .commande-loader-dots span:nth-child(3) {
        background: #6b2f20;
        animation-delay: 0.3s;
    }

    @keyframes commandeLoaderDot {
        0%, 80%, 100% {
            transform: scale(0.6);
            opacity: 0.4;
        }
        40% {
            transform: scale(1);
            opacity: 1;
        }
    }
    </style>
</head>

<body>

    <div class="commande-container">
        <h1 class="commande-page-title">
            <i class="fas fa-shopping-bag"></i> Passer la commande
        </h1>
        <p class="commande-page-subtitle">Veuillez remplir les informations de contact</p>

        <?php if ($message): ?>
        <div class="message <?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <div class="commande-wrapper">
            <!-- Formulaire de commande -->
            <div class="commande-form-section">
                <h2 class="section-title">
                    <i class="fas fa-phone"></i> Informations de livraison
                </h2>

                <form method="POST" action="" id="form-commande">
                    <input type="hidden" name="action" value="create_commande">
                    <input type="hidden" name="mode_livraison" id="mode_livraison"
                        value="<?php echo htmlspecialchars($commande_mode_selected, ENT_QUOTES, 'UTF-8'); ?>">

                    <?php if (empty($zones_livraison)): ?>
                    <div class="message error">
                        <i class="fas fa-exclamation-triangle"></i> Aucune zone de livraison n'est configurée. Veuillez
                        contacter l'administrateur.
                    </div>
                    <?php else: ?>

                    <div class="cmd-mode-switch" role="tablist" aria-label="Mode de réception">
                        <button type="button"
                            class="cmd-mode-btn<?php echo $commande_mode_selected === 'livraison' ? ' is-active' : ''; ?>"
                            data-mode="livraison" id="cmd-mode-btn-livraison" role="tab"
                            aria-selected="<?php echo $commande_mode_selected === 'livraison' ? 'true' : 'false'; ?>">
                            <i class="fas fa-truck" aria-hidden="true"></i> Livraison
                        </button>
                        <button type="button"
                            class="cmd-mode-btn<?php echo $commande_mode_selected === 'retrait' ? ' is-active' : ''; ?>"
                            data-mode="retrait" id="cmd-mode-btn-retrait" role="tab"
                            aria-selected="<?php echo $commande_mode_selected === 'retrait' ? 'true' : 'false'; ?>">
                            <i class="fas fa-store" aria-hidden="true"></i> Récupérer sur place
                        </button>
                    </div>

                    <?php if ($zone_retrait): ?>
                    <input type="hidden" name="zone_livraison_id" id="zone_retrait_id"
                        value="<?php echo (int) $zone_retrait['id']; ?>"
                        <?php echo $commande_mode_selected === 'retrait' ? '' : 'disabled'; ?>>
                    <?php endif; ?>

                    <div id="panel-livraison"
                        class="cmd-mode-panel<?php echo $commande_mode_selected === 'livraison' ? ' is-visible' : ''; ?>"
                        role="tabpanel">
                        <?php if (!empty($zones_livraison_delivery)): ?>
                        <div class="form-group">
                            <label for="zone_livraison_id">
                                <i class="fas fa-map-marker-alt"></i> Zone de livraison *
                            </label>
                            <select id="zone_livraison_id" name="zone_livraison_id"
                                <?php echo $commande_mode_selected === 'livraison' ? 'required' : 'disabled'; ?>>
                                <option value="">Sélectionnez votre zone de livraison</option>
                                <?php foreach ($zones_livraison_delivery as $zone): ?>
                                <option value="<?php echo (int) $zone['id']; ?>"
                                    data-prix="<?php echo (float) $zone['prix_livraison']; ?>"
                                    <?php echo (isset($_POST['zone_livraison_id']) && (int) $_POST['zone_livraison_id'] === (int) $zone['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($zone['ville'] . ' - ' . $zone['quartier']); ?>
                                    (<?php echo number_format($zone['prix_livraison'], 0, ',', ' '); ?> FCFA)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small>Choisissez la zone correspondant à votre secteur de livraison</small>
                        </div>
                        <?php else: ?>
                        <div class="message error">
                            <i class="fas fa-exclamation-triangle"></i> Aucune zone de livraison à domicile n'est configurée.
                        </div>
                        <?php endif; ?>

                        <div class="form-group">
                            <label>
                                <i class="fas fa-location-crosshairs"></i> Votre position exacte *
                            </label>
                            <div class="commande-geo-box">
                                <div id="commande-geo-map" class="commande-geo-map" aria-label="Carte de votre position"></div>
                                <button type="button" class="btn-commande-geo-refresh" id="btn-commande-geo-refresh">
                                    <i class="fas fa-crosshairs" aria-hidden="true"></i> Actualiser ma position
                                </button>
                                <div id="commande-geo-status" class="commande-geo-status" aria-live="polite"></div>
                                <small>Votre position est capturée en temps réel pour permettre au livreur de vous trouver.</small>
                            </div>
                            <input type="hidden" name="geo_lat" id="geo_lat"
                                value="<?php echo isset($_POST['geo_lat']) ? htmlspecialchars((string) $_POST['geo_lat'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                            <input type="hidden" name="geo_lng" id="geo_lng"
                                value="<?php echo isset($_POST['geo_lng']) ? htmlspecialchars((string) $_POST['geo_lng'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                            <input type="hidden" name="geo_precision" id="geo_precision"
                                value="<?php echo isset($_POST['geo_precision']) ? htmlspecialchars((string) $_POST['geo_precision'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                            <input type="hidden" name="geo_source" id="geo_source"
                                value="<?php echo isset($_POST['geo_source']) ? htmlspecialchars((string) $_POST['geo_source'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                            <input type="hidden" name="geo_address" id="geo_address"
                                value="<?php echo isset($_POST['geo_address']) ? htmlspecialchars((string) $_POST['geo_address'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                        </div>
                    </div>

                    <div id="panel-retrait"
                        class="cmd-mode-panel<?php echo $commande_mode_selected === 'retrait' ? ' is-visible' : ''; ?>"
                        role="tabpanel">
                        <div class="cmd-retrait-info">
                            <i class="fas fa-store" aria-hidden="true"></i>
                            Vous récupérez votre commande directement en boutique
                            <?php if ($zone_retrait): ?>
                            — <strong><?php echo htmlspecialchars($zone_retrait['ville'] . ' - ' . $zone_retrait['quartier']); ?></strong>
                            (gratuit)
                            <?php else: ?>
                            — <strong>Sugar Paper, Hann Mariste 2</strong> (gratuit)
                            <?php endif; ?>.
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="telephone_livraison">
                            <i class="fas fa-phone"></i> <span id="tel-label-text"><?php echo $commande_mode_selected === 'retrait' ? 'Téléphone de contact' : 'Téléphone de livraison'; ?></span> *
                        </label>
                        <input type="tel" id="telephone_livraison" name="telephone_livraison" required
                            placeholder="+221 XX XXX XX XX"
                            value="<?php
                                if (isset($_POST['telephone_livraison'])) {
                                    echo htmlspecialchars($_POST['telephone_livraison']);
                                } elseif ($user) {
                                    echo htmlspecialchars($user['telephone'] ?? '');
                                } elseif ($is_guest_checkout) {
                                    $gc = guest_client_get();
                                    echo htmlspecialchars($gc['telephone'] ?? '');
                                }
                            ?>">
                        <small>Numéro pour vous contacter au sujet de votre commande</small>
                    </div>

                    <button type="submit" class="btn-submit-commande"
                        <?php echo empty($zones_livraison) ? 'disabled' : ''; ?>>
                        <i class="fas fa-check-circle"></i> Confirmer la commande
                    </button>
                </form>
            </div>

            <!-- Résumé de la commande -->
            <div class="commande-summary-section">
                <h2 class="section-title">
                    <i class="fas fa-shopping-cart"></i> Résumé
                </h2>

                <div style="margin-bottom: 20px;">
                    <?php foreach ($panier_items as $item): ?>
                    <?php
                        $prix_unitaire = (!empty($item['panier_prix_unitaire']) && $item['panier_prix_unitaire'] > 0)
                            ? (float) $item['panier_prix_unitaire']
                            : (!empty($item['prix_promotion']) && $item['prix_promotion'] < $item['prix'] ? $item['prix_promotion'] : $item['prix']);
                        $prix_total_item = $prix_unitaire * $item['quantite'];
                        $item_img = !empty($item['panier_variante_image']) ? $item['panier_variante_image'] : $item['image_principale'];
                        ?>
                    <div class="panier-item-summary">
                        <img src="/upload/<?php echo htmlspecialchars($item_img); ?>"
                            alt="<?php echo htmlspecialchars($item['nom']); ?>"
                            onerror="this.src='/image/produit1.jpg'">
                        <div class="panier-item-summary-info">
                            <h4><?php echo htmlspecialchars(!empty($item['panier_variante_nom']) ? $item['nom'] . ' - ' . $item['panier_variante_nom'] : $item['nom']); ?></h4>
                            <?php
                            $opts = [];
                            if (!empty($item['panier_couleur'])) $opts[] = 'Couleur: ' . htmlspecialchars($item['panier_couleur']);
                            if (!empty($item['panier_poids'])) $opts[] = 'Poids: ' . htmlspecialchars($item['panier_poids']) . (!empty($item['panier_surcout_poids']) && $item['panier_surcout_poids'] > 0 ? ' (+' . number_format($item['panier_surcout_poids'], 0, ',', ' ') . ' FCFA)' : '');
                            if (!empty($item['panier_taille'])) $opts[] = 'Taille: ' . htmlspecialchars($item['panier_taille']) . (!empty($item['panier_surcout_taille']) && $item['panier_surcout_taille'] > 0 ? ' (+' . number_format($item['panier_surcout_taille'], 0, ',', ' ') . ' FCFA)' : '');
                            ?>
                            <?php if (!empty($opts)): ?>
                            <p style="font-size: 11px; color: #737373; margin-bottom: 4px;"><?php echo implode(' • ', $opts); ?></p>
                            <?php endif; ?>
                            <p>Quantité: <?php echo $item['quantite']; ?> × <?php echo number_format($prix_unitaire, 0, ',', ' '); ?> FCFA</p>
                        </div>
                        <div class="panier-item-summary-price">
                            <?php echo number_format($prix_total_item, 0, ',', ' '); ?> FCFA
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="summary-item">
                    <span class="summary-item-label">Nombre d'articles</span>
                    <span class="summary-item-value"><?php echo $nombre_total_articles; ?></span>
                </div>

                <div class="summary-item">
                    <span class="summary-item-label">Nombre de produits</span>
                    <span class="summary-item-value"><?php echo count($panier_items); ?></span>
                </div>

                <div class="summary-item">
                    <span class="summary-item-label">Sous-total</span>
                    <span class="summary-item-value"><?php echo number_format($panier_total, 0, ',', ' '); ?>
                        FCFA</span>
                </div>

                <div class="summary-item">
                    <span class="summary-item-label">Livraison</span>
                    <span class="summary-item-value" id="summary-livraison">0 FCFA</span>
                </div>

                <div class="summary-total">
                    <div class="summary-item">
                        <span class="summary-item-label">Total général</span>
                        <span class="summary-item-value"
                            id="summary-total"><?php echo number_format($panier_total, 0, ',', ' '); ?> FCFA</span>
                    </div>
                </div>

                <a href="/panier.php" class="commande-link-retour">
                    <i class="fas fa-arrow-left"></i> Retour au panier
                </a>
            </div>
        </div>
    </div>

    <div id="commande-loader-overlay" class="commande-loader-overlay" hidden aria-hidden="true" role="alertdialog" aria-modal="true" aria-labelledby="commande-loader-title" aria-describedby="commande-loader-text">
        <div class="commande-loader-card">
            <div class="commande-loader-spinner" aria-hidden="true">
                <span class="commande-loader-spinner__ring commande-loader-spinner__ring--outer"></span>
                <span class="commande-loader-spinner__ring commande-loader-spinner__ring--inner"></span>
                <span class="commande-loader-spinner__icon"><i class="fas fa-shopping-bag"></i></span>
            </div>
            <h2 class="commande-loader-title" id="commande-loader-title">Commande en cours</h2>
            <p class="commande-loader-text" id="commande-loader-text">Votre commande est en train d'être enregistrée.<br>Merci de patienter quelques instants…</p>
            <div class="commande-loader-dots" aria-hidden="true">
                <span></span><span></span><span></span>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="/js/commande-geo.js<?php echo asset_version_query(); ?>"></script>
    <script>
    (function() {
        var panierTotal = <?php echo $panier_total; ?>;
        var selectZone = document.getElementById('zone_livraison_id');
        var hiddenRetraitZone = document.getElementById('zone_retrait_id');
        var spanLivraison = document.getElementById('summary-livraison');
        var spanTotal = document.getElementById('summary-total');

        function formatNumber(n) {
            return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        }

        function getActiveZoneOption() {
            if (window.CommandeGeo && CommandeGeo.getMode() === 'retrait') {
                return { dataset: { prix: '0' } };
            }
            if (!selectZone || selectZone.disabled) {
                return null;
            }
            return selectZone.options[selectZone.selectedIndex];
        }

        function updateTotaux() {
            var opt = getActiveZoneOption();
            var frais = opt && opt.dataset && opt.dataset.prix ? parseFloat(opt.dataset.prix) : 0;
            if (isNaN(frais)) {
                frais = 0;
            }
            var total = panierTotal + frais;
            spanLivraison.textContent = formatNumber(Math.round(frais)) + ' FCFA';
            spanTotal.textContent = formatNumber(Math.round(total)) + ' FCFA';
        }

        window.CommandeTotaux = { refresh: updateTotaux };

        if (selectZone) {
            selectZone.addEventListener('change', updateTotaux);
        }
        updateTotaux();

        var formCommande = document.getElementById('form-commande');
        var loaderOverlay = document.getElementById('commande-loader-overlay');
        var commandeSubmitting = false;
        var MIN_LOADER_MS = 600;

        if (formCommande && loaderOverlay) {
            formCommande.addEventListener('submit', function (e) {
                if (commandeSubmitting) {
                    e.preventDefault();
                    return;
                }
                if (window.CommandeGeo && typeof CommandeGeo.validate === 'function' && !CommandeGeo.validate()) {
                    e.preventDefault();
                    return;
                }
                if (!formCommande.checkValidity()) {
                    return;
                }
                e.preventDefault();
                commandeSubmitting = true;

                if (window.CommandeGeo && typeof CommandeGeo.stopWatch === 'function') {
                    CommandeGeo.stopWatch();
                }

                loaderOverlay.hidden = false;
                loaderOverlay.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';

                var submitBtn = formCommande.querySelector('.btn-submit-commande');
                if (submitBtn) {
                    submitBtn.disabled = true;
                }

                setTimeout(function () {
                    formCommande.submit();
                }, MIN_LOADER_MS);
            });
        }
    })();
    </script>
</body>

</html>