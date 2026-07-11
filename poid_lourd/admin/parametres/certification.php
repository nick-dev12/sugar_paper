<?php
/**
 * Certification vendeur — choix du niveau (Standard / VIP / Premium)
 */
require_once __DIR__ . '/../includes/require_admin_session.php';
require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../models/model_admin.php';
require_once __DIR__ . '/../../models/model_vendeur_certification.php';

$role = admin_normalize_role_for_route($_SESSION['admin_role'] ?? '');
if ($role !== 'vendeur') {
    header('Location: ../parametres.php');
    exit;
}

$admin_id = (int) ($_SESSION['admin_id'] ?? 0);
$admin = get_admin_by_id($admin_id);
if (!$admin || ($admin['role'] ?? '') !== 'vendeur') {
    header('Location: ../parametres.php');
    exit;
}

$error_message = '';
$success_message = '';
$niveaux = vendeur_certification_niveaux();
$niveau_actif = vendeur_certification_get_niveau_actif($admin_id);
$demande_en_cours = vendeur_certification_get_demande_en_cours($admin_id);
$derniere_demande = vendeur_certification_get_derniere_demande($admin_id);

if (!empty($_GET['envoye'])) {
    $success_message = 'Votre demande de certification a été transmise avec succès.';
    $demande_en_cours = vendeur_certification_get_demande_en_cours($admin_id);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certification boutique &mdash; Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/vendor-cert-ribbon.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-certification.css<?php echo asset_version_query(); ?>">
</head>
<body>
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <div class="cert-page">
        <a href="../parametres.php" class="cert-back"><i class="fas fa-arrow-left"></i> Retour aux paramètres</a>

        <header class="cert-hero">
            <p class="cert-hero__eyebrow"><i class="fas fa-certificate"></i> Confiance &amp; visibilité</p>
            <h1 class="cert-hero__title">Certification de votre boutique</h1>
            <div class="cert-status">
                <?php if ($niveau_actif): ?>
                    <?php $cert_niveau = $niveau_actif; $cert_size = 'md'; require __DIR__ . '/../../includes/partials/vendeur_certification_badge.php'; ?>
                    <span class="cert-pill-none"><i class="fas fa-check"></i> Certification active</span>
                <?php else: ?>
                    <span class="cert-pill-none"><i class="fas fa-store"></i> Non certifié</span>
                <?php endif; ?>
                <?php if ($demande_en_cours): ?>
                    <span class="cert-pill-wait"><i class="fas fa-hourglass-half"></i> Demande en cours — <?php echo htmlspecialchars(vendeur_certification_niveau_label($demande_en_cours['niveau'])); ?></span>
                <?php endif; ?>
            </div>
        </header>

        <?php if ($success_message !== ''): ?>
            <div class="cert-alert cert-alert--success" role="status"><i class="fas fa-circle-check"></i><span><?php echo htmlspecialchars($success_message); ?></span></div>
        <?php endif; ?>

        <?php if ($demande_en_cours): ?>
            <div class="cert-alert cert-alert--info" role="status">
                <i class="fas fa-info-circle"></i>
                <span>Votre demande <strong><?php echo htmlspecialchars(vendeur_certification_niveau_label($demande_en_cours['niveau'])); ?></strong>
                du <?php echo date('d/m/Y à H:i', strtotime((string) $demande_en_cours['date_creation'])); ?> est en cours d'examen. Vous serez notifié après validation.</span>
            </div>
        <?php elseif ($derniere_demande && ($derniere_demande['statut'] ?? '') === 'refusee'): ?>
            <div class="cert-alert cert-alert--error" role="alert">
                <i class="fas fa-times-circle"></i>
                <span>Dernière demande refusée<?php if (!empty($derniere_demande['motif_refus'])): ?> : <?php echo htmlspecialchars((string) $derniere_demande['motif_refus']); ?><?php endif; ?>. Vous pouvez soumettre une nouvelle demande.</span>
            </div>
        <?php endif; ?>

        <div class="cert-tiers" role="list" aria-label="Niveaux de certification">
            <?php foreach ($niveaux as $key => $meta):
                $is_active = ($niveau_actif === $key);
                $is_attained = ($niveau_actif !== null && vendeur_certification_niveau_ordre($key) <= vendeur_certification_niveau_ordre($niveau_actif));
                $is_pending = $demande_en_cours && (($demande_en_cours['niveau'] ?? '') === $key);
                $can_request = !$demande_en_cours && vendeur_certification_peut_demander($admin_id, $key)['ok'];
                $req_txt = $key === 'standard' ? '+ Pièce d\'identité' : ($key === 'vip' ? '+ Local & pièce ID' : '+ Justificatifs');

                $tier_classes = 'cert-tier cert-tier--' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
                if ($is_active) {
                    $tier_classes .= ' is-active';
                } elseif ($is_pending) {
                    $tier_classes .= ' is-pending';
                } elseif (!$can_request && $is_attained) {
                    $tier_classes .= ' is-disabled';
                }
            ?>
            <?php if ($can_request): ?>
            <a href="certification-demande.php?niveau=<?php echo urlencode($key); ?>" class="<?php echo $tier_classes; ?> cert-tier--link" role="listitem">
            <?php else: ?>
            <div class="<?php echo $tier_classes; ?>" role="listitem">
            <?php endif; ?>
                <div class="cert-tier__ribbon-wrap">
                    <?php
                    $cert_niveau = $key;
                    $cert_size = 'tier';
                    require __DIR__ . '/../../includes/partials/vendeur_certification_badge.php';
                    ?>
                </div>
                <p class="cert-tier__name"><?php echo htmlspecialchars($meta['label']); ?></p>
                <p class="cert-tier__desc"><?php echo htmlspecialchars($meta['desc']); ?></p>
                <p class="cert-tier__req"><?php echo htmlspecialchars($req_txt); ?></p>

                <?php if ($is_active): ?>
                <div class="cert-tier__status cert-tier__status--active">
                    <span class="cert-tier__status-label"><i class="fas fa-check-circle"></i> Badge activé</span>
                </div>
                <?php elseif ($is_pending): ?>
                <div class="cert-tier__status cert-tier__status--pending">
                    <span class="cert-pill-wait"><i class="fas fa-hourglass-half"></i> En cours</span>
                    <a href="certification-suivi.php?id=<?php echo (int) ($demande_en_cours['id'] ?? 0); ?>" class="cert-tier__follow-link cert-tier__follow">
                        <i class="fas fa-eye"></i> Suivre la demande
                    </a>
                </div>
                <?php elseif (!$demande_en_cours && $derniere_demande && ($derniere_demande['statut'] ?? '') === 'refusee' && ($derniere_demande['niveau'] ?? '') === $key): ?>
                <div class="cert-tier__status cert-tier__status--pending">
                    <span class="cert-pill-wait" style="background:rgba(220,38,38,0.1);color:#b91c1c;"><i class="fas fa-times-circle"></i> Refusée</span>
                    <a href="certification-suivi.php?id=<?php echo (int) ($derniere_demande['id'] ?? 0); ?>" class="cert-tier__follow-link cert-tier__follow">
                        <i class="fas fa-eye"></i> Voir le détail
                    </a>
                </div>
                <?php elseif ($can_request): ?>
                <p class="cert-tier__cta">Demander <i class="fas fa-arrow-right"></i></p>
                <?php elseif ($is_attained && !$is_active): ?>
                <p class="cert-tier__sub">Niveau déjà obtenu</p>
                <?php endif; ?>
            <?php if ($can_request): ?>
            </a>
            <?php else: ?>
            </div>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
