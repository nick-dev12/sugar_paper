<?php
/**
 * Page de profil utilisateur — redesign v2
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

require_once __DIR__ . '/../models/model_users.php';
$user = get_user_by_id($_SESSION['user_id']);

if (!$user) {
    session_destroy();
    auth_redirect_to_site_home();
    exit;
}

$prenom_trim    = trim((string)($user['prenom'] ?? ''));
$avatar_initial = $prenom_trim !== '' ? mb_strtoupper(mb_substr($prenom_trim, 0, 1, 'UTF-8'), 'UTF-8') : '?';

$error_message   = '';

// Formulaire infos personnelles
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier_profil'])) {
    $nom       = isset($_POST['nom'])       ? trim($_POST['nom'])       : '';
    $prenom    = isset($_POST['prenom'])    ? trim($_POST['prenom'])    : '';
    $email     = isset($_POST['email'])     ? trim($_POST['email'])     : '';
    $telephone = isset($_POST['telephone']) ? trim($_POST['telephone']) : '';

    $errors = [];

    if (empty($nom)) {
        $errors[] = 'Le nom est obligatoire.';
    } elseif (strlen($nom) < 2) {
        $errors[] = 'Le nom doit contenir au moins 2 caractères.';
    }

    if ($prenom !== '' && strlen($prenom) < 2) {
        $errors[] = 'Le prénom doit contenir au moins 2 caractères.';
    }

    $email_db = null;
    if ($email !== '') {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "L'email n'est pas valide.";
        } else {
            $existing_user = get_user_by_email($email);
            if ($existing_user && (int)$existing_user['id'] !== (int)$_SESSION['user_id']) {
                $errors[] = 'Cet email est déjà utilisé par un autre compte.';
            } else {
                $email_db = $email;
            }
        }
    }

    if (empty($telephone)) {
        $errors[] = 'Le téléphone est obligatoire.';
    } elseif (!preg_match('/^[0-9+\-\s()]+$/', $telephone)) {
        $errors[] = "Le format du téléphone n'est pas valide.";
    }

    if (empty($errors)) {
        $data = ['nom' => $nom, 'prenom' => $prenom, 'email' => $email_db, 'telephone' => $telephone];
        if (update_user($_SESSION['user_id'], $data)) {
            $_SESSION['user_email'] = $email_db !== null ? $email_db : '';
            $_SESSION['success_message'] = 'Vos informations personnelles ont été mises à jour avec succès !';
            header('Location: profil.php');
            exit;
        } else {
            $error_message = 'Une erreur est survenue lors de la mise à jour. Veuillez réessayer.';
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
}

// Formulaire mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier_mot_de_passe'])) {
    $current_password  = isset($_POST['current_password'])  ? $_POST['current_password']  : '';
    $password          = isset($_POST['password'])          ? $_POST['password']          : '';
    $password_confirm  = isset($_POST['password_confirm'])  ? $_POST['password_confirm']  : '';

    $errors = [];

    if (empty($current_password)) {
        $errors[] = 'Le mot de passe actuel est obligatoire.';
    } elseif (!password_verify($current_password, $user['password'])) {
        $errors[] = 'Le mot de passe actuel est incorrect.';
    }

    if (empty($password)) {
        $errors[] = 'Le nouveau mot de passe est obligatoire.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Le nouveau mot de passe doit contenir au moins 6 caractères.';
    } elseif ($password === $current_password) {
        $errors[] = "Le nouveau mot de passe doit être différent de l'ancien.";
    } elseif ($password !== $password_confirm) {
        $errors[] = 'Les nouveaux mots de passe ne correspondent pas.';
    }

    if (empty($errors)) {
        require_once __DIR__ . '/../conn/conn.php';
        global $db;
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $db->prepare("UPDATE users SET password = :password WHERE id = :id");
        if ($stmt->execute(['id' => $_SESSION['user_id'], 'password' => $password_hash])) {
            $_SESSION['success_message'] = 'Votre mot de passe a été modifié avec succès !';
            header('Location: profil.php');
            exit;
        } else {
            $error_message = 'Une erreur est survenue lors de la modification du mot de passe. Veuillez réessayer.';
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
}

$compte_actif = ($user['statut'] ?? '') === 'actif';
$nom_affiche  = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''));
$nom_affiche  = $nom_affiche !== '' ? $nom_affiche : 'Client';
$member_since = isset($user['date_creation']) ? date('M Y', strtotime($user['date_creation'])) : '';

require_once __DIR__ . '/../includes/flash_toast.php';
if (!empty($error_message)) {
    flash_toast_queue_page('error', str_replace('<br>', ' — ', $error_message));
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
    <?php include __DIR__ . '/../includes/pwa_meta.php'; ?>
    <title>Mon profil &mdash; COLObanes</title>
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/user-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/user-mes-commandes.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/user-profil.css<?php echo asset_version_query(); ?>">
    <?php include __DIR__ . '/../includes/auth_intl_tel_head.php'; ?>
    <style>
        /* ===== PROFIL v2 ===== */

        .prf-page {
            max-width: 860px;
            margin: 0 auto;
            padding: clamp(16px, 4vw, 36px) clamp(14px, 4vw, 24px) 90px;
            display: flex;
            flex-direction: column;
            gap: 22px;
            font-family: var(--font-corps);
        }

        /* ---- Header ---- */
        .prf-page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .prf-page-header__left { display: flex; flex-direction: column; gap: 3px; }

        .prf-page-header__eyebrow {
            font-size: 0.73rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.12em;
            color: var(--couleur-dominante, #3564a6);
            display: flex; align-items: center; gap: 5px;
        }

        .prf-page-header__title {
            font-size: clamp(1.3rem, 3vw, 1.75rem);
            font-weight: 800; color: var(--titres, #0d0d0d);
            font-family: var(--font-titres);
            line-height: 1.15; letter-spacing: -0.025em;
        }

        .prf-page-header__actions { display: flex; gap: 9px; align-items: center; }

        /* ---- Fil d'Ariane ---- */
        .prf-breadcrumb {
            display: flex; align-items: center; gap: 8px;
            font-size: 0.8rem; color: var(--gris-moyen, #737373); flex-wrap: wrap;
        }

        .prf-breadcrumb a {
            text-decoration: none; color: var(--couleur-dominante, #3564a6);
            font-weight: 600; display: flex; align-items: center; gap: 5px;
        }

        .prf-breadcrumb a:hover { text-decoration: underline; }
        .prf-breadcrumb i { font-size: 0.65rem; color: var(--gris-clair, #a3a3a3); }

        /* ---- Boutons ---- */
        .prf-btn {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 9px 18px; border-radius: 11px;
            font-size: 0.81rem; font-weight: 700;
            cursor: pointer; border: none;
            text-decoration: none; font-family: var(--font-corps);
            transition: all 0.2s; white-space: nowrap;
        }

        .prf-btn--primary { background: var(--couleur-dominante, #3564a6); color: #fff; box-shadow: 0 4px 14px rgba(53,100,166,0.25); }
        .prf-btn--primary:hover { background: var(--bleu-fonce, #2d5690); transform: translateY(-1px); }
        .prf-btn--outline { background: #fff; color: var(--couleur-dominante, #3564a6); border: 1.5px solid rgba(53,100,166,0.22); }
        .prf-btn--outline:hover { background: rgba(53,100,166,0.05); }

        /* ---- Hero identité ---- */
        .prf-hero {
            background: linear-gradient(135deg, var(--bleu-fonce, #2d5690) 0%, var(--couleur-dominante, #3564a6) 60%, var(--bleu-clair, #4a7ab8) 100%);
            border-radius: 20px;
            padding: clamp(20px, 3.5vw, 34px);
            position: relative; overflow: hidden;
            box-shadow: 0 16px 44px rgba(53,100,166,0.28);
        }

        .prf-hero::before {
            content: '';
            position: absolute; top: -50px; right: -30px;
            width: 200px; height: 200px;
            background: rgba(255,255,255,0.06);
            border-radius: 50%; pointer-events: none;
        }

        .prf-hero::after {
            content: '';
            position: absolute; bottom: -60px; right: 100px;
            width: 160px; height: 160px;
            background: rgba(255,255,255,0.04);
            border-radius: 50%; pointer-events: none;
        }

        .prf-hero__inner {
            display: flex; align-items: center;
            gap: 20px; flex-wrap: wrap; position: relative;
        }

        /* Avatar grand */
        .prf-hero__avatar {
            width: 74px; height: 74px;
            border-radius: 50%;
            background: rgba(255,255,255,0.18);
            border: 3px solid rgba(255,255,255,0.35);
            display: flex; align-items: center; justify-content: center;
            font-size: 2rem; font-weight: 900; color: #fff;
            font-family: var(--font-titres); flex-shrink: 0;
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
        }

        .prf-hero__body { flex: 1; min-width: 0; }

        .prf-hero__name {
            font-size: clamp(1.15rem, 3vw, 1.55rem);
            font-weight: 900; color: #fff;
            font-family: var(--font-titres);
            line-height: 1.1; margin-bottom: 4px;
        }

        .prf-hero__email {
            font-size: 0.82rem; color: rgba(255,255,255,0.68);
            display: flex; align-items: center; gap: 5px;
        }

        .prf-hero__since {
            font-size: 0.75rem; color: rgba(255,255,255,0.5);
            margin-top: 3px; display: flex; align-items: center; gap: 5px;
        }

        .prf-hero__status {
            margin-top: 14px; display: flex; gap: 10px; flex-wrap: wrap; align-items: center;
        }

        .prf-hero__badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 5px 14px; border-radius: 50px;
            font-size: 0.75rem; font-weight: 700;
        }

        .prf-hero__badge--actif {
            background: rgba(134,239,172,0.2); border: 1px solid rgba(134,239,172,0.35);
            color: #86efac;
        }

        .prf-hero__badge--inactif {
            background: rgba(255,107,53,0.2); border: 1px solid rgba(255,107,53,0.3);
            color: #fca28a;
        }

        /* Quick links dans hero */
        .prf-hero__links {
            display: flex; gap: 8px; flex-wrap: wrap; margin-left: auto;
        }

        .prf-hero__link {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 16px;
            background: rgba(255,255,255,0.12);
            border: 1.5px solid rgba(255,255,255,0.2);
            border-radius: 10px; color: #fff;
            font-size: 0.78rem; font-weight: 700;
            text-decoration: none; transition: background 0.2s;
            white-space: nowrap;
        }

        .prf-hero__link:hover { background: rgba(255,255,255,0.22); }

        /* ---- Alert messages ---- */
        .prf-alert {
            display: flex; align-items: flex-start; gap: 11px;
            padding: 14px 18px; border-radius: 14px;
            font-size: 0.84rem; font-weight: 500;
            border: 1px solid transparent;
        }

        .prf-alert i { margin-top: 2px; font-size: 1rem; flex-shrink: 0; }

        .prf-alert--success {
            background: rgba(34,197,94,0.09);
            border-color: rgba(34,197,94,0.22); color: #15803d;
        }

        .prf-alert--error {
            background: rgba(239,68,68,0.07);
            border-color: rgba(239,68,68,0.2); color: #b91c1c;
        }

        /* ---- Grille 2 cols (info + sécurité) ---- */
        .prf-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        @media (max-width: 680px) { .prf-grid { grid-template-columns: 1fr; } }

        /* ---- Cards de formulaire ---- */
        .prf-card {
            background: #fff;
            border-radius: 18px;
            border: 1px solid rgba(53,100,166,0.08);
            box-shadow: 0 2px 14px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .prf-card__head {
            padding: 18px 22px 14px;
            border-bottom: 1px solid rgba(53,100,166,0.07);
            display: flex; align-items: center; gap: 12px;
        }

        .prf-card__head-icon {
            width: 40px; height: 40px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem; flex-shrink: 0;
        }

        .prf-card--info .prf-card__head-icon   { background: rgba(53,100,166,0.1); color: var(--couleur-dominante, #3564a6); }
        .prf-card--secure .prf-card__head-icon  { background: rgba(255,107,53,0.1); color: var(--orange, #FF6B35); }

        .prf-card__head-text h3 {
            font-size: 0.95rem; font-weight: 800;
            color: var(--titres); margin: 0; font-family: var(--font-titres);
        }

        .prf-card__head-text p {
            font-size: 0.73rem; color: var(--gris-moyen, #737373); margin: 2px 0 0;
        }

        .prf-card__body { padding: 20px 22px 22px; display: flex; flex-direction: column; gap: 15px; }

        /* ---- Infos statiques ---- */
        .prf-info-item {
            display: flex; align-items: center; gap: 11px;
            padding: 11px 16px; border-radius: 11px;
            background: rgba(53,100,166,0.04);
            border: 1px solid rgba(53,100,166,0.08);
        }

        .prf-info-item__icon {
            width: 34px; height: 34px; border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.8rem; color: var(--couleur-dominante); flex-shrink: 0;
            background: rgba(53,100,166,0.1);
        }

        .prf-info-item__body { display: flex; flex-direction: column; gap: 1px; }
        .prf-info-item__label { font-size: 0.67rem; font-weight: 700; color: var(--gris-moyen); text-transform: uppercase; letter-spacing: 0.06em; }
        .prf-info-item__val   { font-size: 0.85rem; font-weight: 600; color: var(--titres); }

        .prf-status-dot {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 0.82rem; font-weight: 700;
        }

        .prf-status-dot::before {
            content: ''; width: 8px; height: 8px; border-radius: 50%;
        }

        .prf-status-dot--actif   { color: #15803d; }
        .prf-status-dot--actif::before   { background: #16a34a; }
        .prf-status-dot--inactif { color: #b91c1c; }
        .prf-status-dot--inactif::before { background: #ef4444; }

        /* ---- Champs de formulaire ---- */
        .prf-form-row {
            display: grid; grid-template-columns: 1fr 1fr; gap: 13px;
        }

        @media (max-width: 480px) { .prf-form-row { grid-template-columns: 1fr; } }

        .prf-form-group { display: flex; flex-direction: column; gap: 5px; }

        .prf-form-label {
            font-size: 0.75rem; font-weight: 700;
            color: var(--gris-fonce, #4a4a4a);
            text-transform: uppercase; letter-spacing: 0.05em;
            display: flex; align-items: center; gap: 5px;
        }

        .prf-form-label .req  { color: var(--orange, #FF6B35); }
        .prf-form-label .opt  { font-weight: 500; color: var(--gris-clair, #a3a3a3); text-transform: none; font-size: 0.72rem; }

        .prf-form-input {
            width: 100%; padding: 10px 14px;
            border-radius: 10px;
            border: 1.5px solid rgba(53,100,166,0.18);
            background: #f9fbff;
            font-size: 0.88rem; color: var(--titres);
            font-family: var(--font-corps);
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none; box-sizing: border-box;
        }

        .prf-form-input:focus {
            border-color: var(--couleur-dominante, #3564a6);
            box-shadow: 0 0 0 3px rgba(53,100,166,0.1);
            background: #fff;
        }

        .prf-card--secure .prf-form-input:focus {
            border-color: var(--orange, #FF6B35);
            box-shadow: 0 0 0 3px rgba(255,107,53,0.1);
        }

        .prf-form-hint {
            font-size: 0.71rem; color: var(--gris-clair, #a3a3a3);
            display: flex; align-items: center; gap: 4px;
        }

        /* ---- Password wrapper ---- */
        .prf-pw-wrapper { position: relative; }

        .prf-pw-wrapper .prf-form-input { padding-right: 42px; }

        .prf-pw-toggle {
            position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer;
            color: var(--gris-clair, #a3a3a3); font-size: 0.85rem;
            padding: 4px; transition: color 0.2s;
        }

        .prf-pw-toggle:hover { color: var(--gris-fonce, #4a4a4a); }

        /* Indicateur de force */
        .prf-pw-strength {
            display: flex; gap: 4px; margin-top: 5px;
        }

        .prf-pw-strength__bar {
            flex: 1; height: 3px; border-radius: 3px;
            background: rgba(53,100,166,0.1);
            transition: background 0.3s;
        }

        /* ---- Actions de formulaire ---- */
        .prf-form-actions {
            display: flex; gap: 10px; align-items: center;
            padding-top: 4px;
        }

        .prf-submit {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 10px 22px; border-radius: 11px;
            font-size: 0.83rem; font-weight: 700;
            border: none; cursor: pointer;
            font-family: var(--font-corps); transition: all 0.2s;
        }

        .prf-card--info .prf-submit   { background: var(--couleur-dominante, #3564a6); color: #fff; box-shadow: 0 4px 14px rgba(53,100,166,0.25); }
        .prf-card--info .prf-submit:hover   { background: var(--bleu-fonce, #2d5690); transform: translateY(-1px); }
        .prf-card--secure .prf-submit  { background: var(--orange, #FF6B35); color: #fff; box-shadow: 0 4px 14px rgba(255,107,53,0.25); }
        .prf-card--secure .prf-submit:hover  { background: var(--orange-fonce, #E85A2A); transform: translateY(-1px); }

        .prf-cancel {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 10px 18px; border-radius: 11px;
            font-size: 0.81rem; font-weight: 700;
            text-decoration: none; color: var(--gris-moyen, #737373);
            background: rgba(0,0,0,0.04); transition: background 0.2s;
            font-family: var(--font-corps); border: none;
        }

        .prf-cancel:hover { background: rgba(0,0,0,0.08); }

        /* ---- Card compte (infos non modifiables) ---- */
        .prf-account-card {
            background: #fff;
            border-radius: 18px;
            border: 1px solid rgba(53,100,166,0.08);
            box-shadow: 0 2px 14px rgba(0,0,0,0.05);
            padding: 20px 22px;
            display: flex; align-items: center;
            gap: 16px; flex-wrap: wrap;
        }

        .prf-account-card__icon {
            width: 46px; height: 46px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; flex-shrink: 0;
            background: rgba(53,100,166,0.1); color: var(--couleur-dominante, #3564a6);
        }

        .prf-account-card__body { flex: 1; min-width: 0; }
        .prf-account-card__title { font-size: 0.82rem; font-weight: 700; color: var(--titres); margin-bottom: 4px; }
        .prf-account-card__items { display: flex; gap: 20px; flex-wrap: wrap; }
        .prf-account-card__item { display: flex; flex-direction: column; gap: 1px; }
        .prf-account-card__item-label { font-size: 0.67rem; font-weight: 700; color: var(--gris-moyen); text-transform: uppercase; letter-spacing: 0.06em; }
        .prf-account-card__item-val   { font-size: 0.84rem; font-weight: 600; color: var(--titres); }

        /* Responsive */
        @media (max-width: 600px) {
            .prf-hero__inner { flex-direction: column; align-items: flex-start; }
            .prf-hero__links { margin-left: 0; }
        }
    </style>
</head>

<body class="user-page-profil">
    <?php include 'includes/user_nav.php'; ?>

    <div class="prf-page">

        <!-- ===== HEADER ===== -->
        <header class="prf-page-header">
            <div class="prf-page-header__left">
                <p class="prf-page-header__eyebrow">
                    <i class="fas fa-id-card"></i> Param&egrave;tres du compte
                </p>
                <h1 class="prf-page-header__title">Mon profil</h1>
            </div>
        </header>

        <!-- Fil d'Ariane -->
        <nav class="prf-breadcrumb" aria-label="Fil d'Ariane">
            <a href="mon-compte.php"><i class="fas fa-house"></i> Mon compte</a>
            <i class="fas fa-chevron-right"></i>
            <span>Mon profil</span>
        </nav>

        <!-- ===== HERO IDENTITÉ ===== -->
        <div class="prf-hero">
            <div class="prf-hero__inner">
                <div class="prf-hero__avatar" aria-hidden="true">
                    <?php echo $avatar_initial !== '?' ? htmlspecialchars($avatar_initial) : '<i class="fas fa-user" style="font-size:1.5rem;"></i>'; ?>
                </div>
                <div class="prf-hero__body">
                    <div class="prf-hero__name"><?php echo htmlspecialchars($nom_affiche); ?></div>
                    <?php if (!empty($user['email'])): ?>
                        <div class="prf-hero__email">
                            <i class="fas fa-envelope" style="font-size:.7rem;"></i>
                            <?php echo htmlspecialchars($user['email']); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($user['telephone'])): ?>
                        <div class="prf-hero__email" style="margin-top:2px;">
                            <i class="fas fa-phone" style="font-size:.7rem;"></i>
                            <?php echo htmlspecialchars($user['telephone']); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($member_since): ?>
                        <div class="prf-hero__since">
                            <i class="fas fa-calendar" style="font-size:.65rem;"></i>
                            Membre depuis <?php echo $member_since; ?>
                        </div>
                    <?php endif; ?>
                    <div class="prf-hero__status">
                        <span class="prf-hero__badge <?php echo $compte_actif ? 'prf-hero__badge--actif' : 'prf-hero__badge--inactif'; ?>">
                            <i class="fas <?php echo $compte_actif ? 'fa-circle-check' : 'fa-circle-pause'; ?>"></i>
                            Compte <?php echo $compte_actif ? 'actif' : 'inactif'; ?>
                        </span>
                    </div>
                </div>
                <div class="prf-hero__links">
                    <a href="mes-commandes.php" class="prf-hero__link">
                        <i class="fas fa-shopping-bag"></i> Commandes
                    </a>
                    <a href="/produits.php" class="prf-hero__link">
                        <i class="fas fa-store"></i> Boutique
                    </a>
                </div>
            </div>
        </div>

        <!-- ===== INFOS COMPTE (non modifiables) ===== -->
        <div class="prf-account-card">
            <div class="prf-account-card__icon"><i class="fas fa-circle-info"></i></div>
            <div class="prf-account-card__body">
                <div class="prf-account-card__title">Informations du compte</div>
                <div class="prf-account-card__items">
                    <div class="prf-account-card__item">
                        <span class="prf-account-card__item-label">Inscription</span>
                        <span class="prf-account-card__item-val">
                            <?php echo isset($user['date_creation']) ? date('d/m/Y', strtotime($user['date_creation'])) : '&mdash;'; ?>
                        </span>
                    </div>
                    <div class="prf-account-card__item">
                        <span class="prf-account-card__item-label">Statut</span>
                        <span class="prf-account-card__item-val">
                            <span class="prf-status-dot prf-status-dot--<?php echo $compte_actif ? 'actif' : 'inactif'; ?>">
                                <?php echo ucfirst($user['statut'] ?? 'inactif'); ?>
                            </span>
                        </span>
                    </div>
                    <div class="prf-account-card__item">
                        <span class="prf-account-card__item-label">ID</span>
                        <span class="prf-account-card__item-val">#<?php echo (int)$_SESSION['user_id']; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== GRILLE 2 CARTES ===== -->
        <div class="prf-grid">

            <!-- CARTE 1 : Infos personnelles -->
            <div class="prf-card prf-card--info">
                <div class="prf-card__head">
                    <div class="prf-card__head-icon"><i class="fas fa-user-pen"></i></div>
                    <div class="prf-card__head-text">
                        <h3>Informations personnelles</h3>
                        <p>Mettez &agrave; jour vos donn&eacute;es de contact</p>
                    </div>
                </div>
                <div class="prf-card__body">
                    <form method="post" action="" id="form-infos">
                        <!-- Nom / Prénom -->
                        <div class="prf-form-row">
                            <div class="prf-form-group">
                                <label class="prf-form-label" for="nom">
                                    Nom <span class="req">*</span>
                                </label>
                                <input type="text" id="nom" name="nom"
                                    class="prf-form-input"
                                    value="<?php echo htmlspecialchars($user['nom'] ?? ''); ?>"
                                    required autocomplete="family-name"
                                    placeholder="Votre nom">
                            </div>
                            <div class="prf-form-group">
                                <label class="prf-form-label" for="prenom">
                                    Pr&eacute;nom <span class="opt">(optionnel)</span>
                                </label>
                                <input type="text" id="prenom" name="prenom"
                                    class="prf-form-input"
                                    value="<?php echo htmlspecialchars((string)($user['prenom'] ?? '')); ?>"
                                    autocomplete="given-name"
                                    placeholder="Votre pr&eacute;nom">
                            </div>
                        </div>

                        <!-- Email -->
                        <div class="prf-form-group">
                            <label class="prf-form-label" for="email">
                                E-mail <span class="opt">(optionnel)</span>
                            </label>
                            <input type="email" id="email" name="email"
                                class="prf-form-input"
                                value="<?php echo htmlspecialchars((string)($user['email'] ?? '')); ?>"
                                autocomplete="email"
                                placeholder="votre@email.com">
                        </div>

                        <!-- Téléphone -->
                        <div class="prf-form-group">
                            <label class="prf-form-label" for="telephone">
                                T&eacute;l&eacute;phone <span class="req">*</span>
                            </label>
                            <div class="input-wrapper input-wrapper--intl-tel input-wrapper--intl-tel--profil">
                                <input type="tel" id="telephone" name="telephone"
                                    class="prf-form-input"
                                    value="<?php echo htmlspecialchars((string)($user['telephone'] ?? '')); ?>"
                                    required placeholder="77 123 45 67"
                                    autocomplete="tel">
                            </div>
                            <span class="prf-form-hint">
                                <i class="fas fa-circle-info"></i>
                                Indicatif pays au choix ; format international si possible.
                            </span>
                        </div>

                        <div class="prf-form-actions">
                            <button type="submit" name="modifier_profil" class="prf-submit">
                                <i class="fas fa-floppy-disk"></i> Enregistrer
                            </button>
                            <a href="mon-compte.php" class="prf-cancel">
                                <i class="fas fa-xmark"></i> Annuler
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- CARTE 2 : Sécurité -->
            <div class="prf-card prf-card--secure">
                <div class="prf-card__head">
                    <div class="prf-card__head-icon"><i class="fas fa-shield-halved"></i></div>
                    <div class="prf-card__head-text">
                        <h3>S&eacute;curit&eacute;</h3>
                        <p>Changez votre mot de passe</p>
                    </div>
                </div>
                <div class="prf-card__body">
                    <form method="post" action="" id="form-mdp">

                        <!-- MDP actuel -->
                        <div class="prf-form-group">
                            <label class="prf-form-label" for="current_password">
                                Mot de passe actuel <span class="req">*</span>
                            </label>
                            <div class="prf-pw-wrapper">
                                <input type="password" id="current_password" name="current_password"
                                    class="prf-form-input"
                                    placeholder="Mot de passe actuel"
                                    required autocomplete="current-password">
                                <button type="button" class="prf-pw-toggle" data-target="current_password" aria-label="Afficher/masquer">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <span class="prf-form-hint">
                                <i class="fas fa-lock"></i> Obligatoire pour modifier votre mot de passe.
                            </span>
                        </div>

                        <!-- Nouveau MDP -->
                        <div class="prf-form-group">
                            <label class="prf-form-label" for="password">
                                Nouveau mot de passe <span class="req">*</span>
                            </label>
                            <div class="prf-pw-wrapper">
                                <input type="password" id="password" name="password"
                                    class="prf-form-input"
                                    placeholder="Nouveau mot de passe"
                                    required autocomplete="new-password"
                                    id="new-pw-input">
                                <button type="button" class="prf-pw-toggle" data-target="password" aria-label="Afficher/masquer">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="prf-pw-strength" id="pw-strength-bars" aria-hidden="true">
                                <div class="prf-pw-strength__bar" id="bar1"></div>
                                <div class="prf-pw-strength__bar" id="bar2"></div>
                                <div class="prf-pw-strength__bar" id="bar3"></div>
                                <div class="prf-pw-strength__bar" id="bar4"></div>
                            </div>
                            <span class="prf-form-hint">
                                <i class="fas fa-circle-info"></i> Au moins 6 caract&egrave;res.
                            </span>
                        </div>

                        <!-- Confirmer MDP -->
                        <div class="prf-form-group">
                            <label class="prf-form-label" for="password_confirm">
                                Confirmer le mot de passe <span class="req">*</span>
                            </label>
                            <div class="prf-pw-wrapper">
                                <input type="password" id="password_confirm" name="password_confirm"
                                    class="prf-form-input"
                                    placeholder="Confirmez le mot de passe"
                                    required autocomplete="new-password">
                                <button type="button" class="prf-pw-toggle" data-target="password_confirm" aria-label="Afficher/masquer">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="prf-form-actions">
                            <button type="submit" name="modifier_mot_de_passe" class="prf-submit">
                                <i class="fas fa-key"></i> Changer le mot de passe
                            </button>
                            <a href="mon-compte.php" class="prf-cancel">
                                <i class="fas fa-xmark"></i> Annuler
                            </a>
                        </div>
                    </form>
                </div>
            </div>

        </div><!-- /.prf-grid -->

    </div><!-- /.prf-page -->

    <?php include __DIR__ . '/../includes/auth_intl_tel_scripts.php'; ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Initialisation intl-tel-input
            if (typeof window.initAuthIntlTel === 'function') {
                window.initAuthIntlTel('telephone');
            }

            // Toggle affichage mot de passe
            document.querySelectorAll('.prf-pw-toggle').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var targetId = btn.getAttribute('data-target');
                    var input = document.getElementById(targetId);
                    var icon  = btn.querySelector('i');
                    if (!input) return;
                    if (input.type === 'password') {
                        input.type = 'text';
                        icon.classList.replace('fa-eye', 'fa-eye-slash');
                    } else {
                        input.type = 'password';
                        icon.classList.replace('fa-eye-slash', 'fa-eye');
                    }
                });
            });

            // Indicateur de force du mot de passe
            var pwInput = document.getElementById('password');
            var bars    = [
                document.getElementById('bar1'),
                document.getElementById('bar2'),
                document.getElementById('bar3'),
                document.getElementById('bar4')
            ];
            var colors  = ['#ef4444', '#f97316', '#eab308', '#16a34a'];

            if (pwInput) {
                pwInput.addEventListener('input', function () {
                    var val   = pwInput.value;
                    var score = 0;
                    if (val.length >= 6)  score++;
                    if (val.length >= 10) score++;
                    if (/[A-Z]/.test(val) && /[0-9]/.test(val)) score++;
                    if (/[^a-zA-Z0-9]/.test(val)) score++;

                    bars.forEach(function (bar, idx) {
                        bar.style.background = idx < score ? colors[Math.max(0, score - 1)] : 'rgba(53,100,166,0.1)';
                    });
                });
            }
        });
    </script>

    <?php include 'includes/user_footer.php'; ?>
</body>
</html>
