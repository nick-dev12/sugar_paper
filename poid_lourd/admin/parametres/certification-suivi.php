<?php
/**
 * Suivi d'une demande de certification — vendeur
 */
require_once __DIR__ . '/../includes/require_admin_session.php';
require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../models/model_vendeur_certification.php';
require_once __DIR__ . '/../../includes/senegal_regions.php';

$role = admin_normalize_role_for_route($_SESSION['admin_role'] ?? '');
if ($role !== 'vendeur') {
    header('Location: ../parametres.php');
    exit;
}

$admin_id = (int) ($_SESSION['admin_id'] ?? 0);
$demande_id = (int) ($_GET['id'] ?? 0);
$d = vendeur_certification_get_demande_for_vendeur($demande_id, $admin_id);

if (!$d) {
    header('Location: parametres/certification.php');
    exit;
}

if (!empty($_GET['notif_lu'])) {
    vendeur_certification_marquer_notif_vendeur_lue($demande_id, $admin_id);
}

$st_dem = (string) ($d['statut'] ?? '');
$niveau_actif = vendeur_certification_get_niveau_actif($admin_id);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suivi certification #<?php echo $demande_id; ?></title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/vendor-cert-ribbon.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-certification.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/vendeur-cert-notif.css<?php echo asset_version_query(); ?>">
</head>
<body>
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <div class="cert-page cert-suivi-page">
        <a href="parametres/certification.php" class="cert-back"><i class="fas fa-arrow-left"></i> Retour à la certification</a>

        <header class="cert-hero cert-hero--compact">
            <p class="cert-hero__eyebrow"><i class="fas fa-clipboard-list"></i> Suivi de demande</p>
            <h1 class="cert-hero__title">Demande <?php echo htmlspecialchars(vendeur_certification_niveau_label((string) ($d['niveau'] ?? ''))); ?></h1>
            <div class="cert-status">
                <?php $cert_niveau = (string) ($d['niveau'] ?? 'standard'); $cert_size = 'md'; require __DIR__ . '/../../includes/partials/vendeur_certification_badge.php'; ?>
                <span class="cert-pill-none sa-cert-statut sa-cert-statut--<?php echo htmlspecialchars($st_dem, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars(vendeur_certification_statut_label($st_dem)); ?>
                </span>
            </div>
        </header>

        <?php if ($st_dem === 'approuvee'): ?>
            <div class="cert-alert cert-alert--success" role="status">
                <i class="fas fa-circle-check"></i>
                <span>Félicitations ! Votre certification est active. Votre badge est visible sur votre vitrine et votre espace vendeur.</span>
            </div>
        <?php elseif ($st_dem === 'refusee'): ?>
            <div class="cert-alert cert-alert--error" role="alert">
                <i class="fas fa-circle-xmark"></i>
                <span>Votre demande a été refusée. Consultez le motif ci-dessous et soumettez une nouvelle demande si besoin.</span>
            </div>
        <?php else: ?>
            <div class="cert-alert cert-alert--info" role="status">
                <i class="fas fa-hourglass-half"></i>
                <span>Votre demande est en cours d'examen par l'équipe plateforme.</span>
            </div>
        <?php endif; ?>

        <div class="cert-panel">
            <div class="cert-panel__head"><h2>Détails de la demande</h2></div>
            <div class="cert-panel__body cert-suivi-grid">
                <p><strong>Envoyée le</strong> <?php echo date('d/m/Y à H:i', strtotime((string) ($d['date_creation'] ?? 'now'))); ?></p>
                <?php if (!empty($d['date_traitement'])): ?>
                    <p><strong>Traitée le</strong> <?php echo date('d/m/Y à H:i', strtotime((string) $d['date_traitement'])); ?></p>
                <?php endif; ?>
                <p><strong>Identité</strong> <?php echo htmlspecialchars(trim(($d['prenom'] ?? '') . ' ' . ($d['nom'] ?? ''))); ?></p>
                <p><strong>Boutique</strong> <?php echo htmlspecialchars((string) ($d['boutique_nom'] ?? '')); ?></p>
                <p><strong>Téléphone</strong> <?php echo htmlspecialchars((string) ($d['telephone'] ?? '—')); ?></p>
                <p><strong>Email</strong> <?php echo htmlspecialchars((string) ($d['email'] !== '' ? $d['email'] : 'Non renseigné')); ?></p>
                <?php if (!empty($d['boutique_region'])): ?>
                    <p><strong>Région</strong> <?php echo htmlspecialchars(senegal_region_label((string) $d['boutique_region'])); ?></p>
                <?php endif; ?>
                <?php if (!empty($d['adresse_exacte'])): ?>
                    <p class="cert-suivi-full"><strong>Adresse du local</strong><br><?php echo nl2br(htmlspecialchars((string) $d['adresse_exacte'])); ?></p>
                <?php endif; ?>
                <?php if (!empty($d['numero_registre'])): ?>
                    <p><strong>Registre</strong> <?php echo htmlspecialchars((string) $d['numero_registre']); ?></p>
                <?php endif; ?>
                <?php if (!empty($d['description_activite'])): ?>
                    <p class="cert-suivi-full"><strong>Description</strong><br><?php echo nl2br(htmlspecialchars((string) $d['description_activite'])); ?></p>
                <?php endif; ?>
                <?php if (!empty($d['message_demande'])): ?>
                    <p class="cert-suivi-full"><strong>Votre message</strong><br><?php echo nl2br(htmlspecialchars((string) $d['message_demande'])); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($st_dem === 'refusee' && !empty($d['motif_refus'])): ?>
            <div class="sa-cert-refus-box cert-suivi-refus">
                <i class="fas fa-circle-xmark"></i>
                <div>
                    <strong>Motif du refus</strong>
                    <p><?php echo nl2br(htmlspecialchars((string) $d['motif_refus'])); ?></p>
                </div>
            </div>
        <?php endif; ?>

        <?php
        $nv = (string) ($d['niveau'] ?? 'standard');
        $photo_labels = [
            'photo_local_1' => 'Façade',
            'photo_local_2' => 'Intérieur',
            'photo_local_3' => 'Vue complémentaire',
            'photo_document' => 'Document',
            'photo_piece_identite' => 'Pièce d\'identité',
        ];
        $photos = [];
        foreach ($photo_labels as $pk => $plabel) {
            if (!empty($d[$pk])) {
                $photos[] = ['path' => $d[$pk], 'label' => $plabel];
            }
        }
        ?>
        <?php if (!empty($photos)): ?>
            <div class="cert-panel">
                <div class="cert-panel__head"><h2>Documents envoyés</h2></div>
                <div class="cert-panel__body">
                    <div class="sa-cert-photos">
                        <?php foreach ($photos as $ph): ?>
                            <a class="sa-cert-photo" href="/upload/<?php echo htmlspecialchars((string) $ph['path'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                                <img src="/upload/<?php echo htmlspecialchars((string) $ph['path'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($ph['label'], ENT_QUOTES, 'UTF-8'); ?>">
                                <span><?php echo htmlspecialchars($ph['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="cert-actions">
            <a href="parametres/certification.php" class="cert-btn cert-btn--primary"><i class="fas fa-certificate"></i> Retour certification</a>
            <?php if ($st_dem === 'refusee' && vendeur_certification_peut_demander($admin_id, $nv)['ok']): ?>
                <a href="parametres/certification-demande.php?niveau=<?php echo urlencode($nv); ?>" class="cert-btn cert-btn--ghost">Nouvelle demande</a>
            <?php endif; ?>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
