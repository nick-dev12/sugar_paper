<?php
require_once __DIR__ . '/../../includes/session_user.php';
/**
 * Détail des notes clients pour un livreur
 */
session_start_persistent();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../../includes/admin_route_access.php';
admin_route_enforce();

require_once __DIR__ . '/../../includes/admin_permissions.php';
require_once __DIR__ . '/../../models/model_livreur_notes.php';
require_once __DIR__ . '/../../models/model_admin.php';

if (!admin_can_view_livreur_notes()) {
    header('Location: ' . (admin_can_livreur_gps() ? 'index.php' : '../dashboard.php'));
    exit;
}

$livreur_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$livreur = $livreur_id > 0 ? get_admin_by_id($livreur_id) : false;

if (!$livreur) {
    header('Location: notes.php');
    exit;
}

$tables_ready = livreur_notes_tables_ready();
$notes = $tables_ready ? livreur_notes_detail_livreur($livreur_id) : [];
$nom_complet = trim((string) ($livreur['prenom'] ?? '') . ' ' . (string) ($livreur['nom'] ?? ''));
$photo_url = !empty($livreur['photo_profil']) ? admin_photo_profil_url($livreur['photo_profil']) : '';
$initials = strtoupper(
    mb_substr(trim((string) ($livreur['prenom'] ?? '')), 0, 1)
    . mb_substr(trim((string) ($livreur['nom'] ?? '')), 0, 1)
);
if ($initials === '') {
    $initials = 'L';
}
$moyenne = isset($livreur['livreur_note_moyenne']) ? $livreur['livreur_note_moyenne'] : null;
$nb = (int) ($livreur['livreur_nb_notes'] ?? count($notes));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notes — <?php echo htmlspecialchars($nom_complet); ?></title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-livreur-suivi.css<?php echo asset_version_query(); ?>">
</head>
<body class="page-livreurs-notes page-livreurs-notes-detail">
<?php include __DIR__ . '/../includes/nav.php'; ?>

<div class="content-header content-header--livreurs">
    <h1><i class="fas fa-star" aria-hidden="true"></i> Notes — <?php echo htmlspecialchars($nom_complet); ?></h1>
    <div class="header-actions">
        <a href="notes.php" class="btn-secondary"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>
</div>

<?php if (!$tables_ready): ?>
<div class="message error">
    <i class="fas fa-database"></i>
    <span>Module non installé.</span>
</div>
<?php else: ?>
<section class="livreur-card livreur-card--wide">
    <div class="livreur-notes-summary">
        <?php if ($photo_url !== ''): ?>
        <img src="<?php echo htmlspecialchars($photo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="" class="livreur-notes-card__avatar" style="width:56px;height:56px;" onerror="this.style.display='none'">
        <?php else: ?>
        <span class="livreur-notes-card__avatar" style="width:56px;height:56px;font-size:1rem;"><?php echo htmlspecialchars($initials); ?></span>
        <?php endif; ?>
        <div>
            <p class="livreur-notes-card__name" style="margin:0 0 0.25rem;"><?php echo htmlspecialchars($nom_complet); ?></p>
            <p class="livreur-notes-card__meta" style="margin:0;"><?php echo (int) $nb; ?> avis — moyenne <?php echo htmlspecialchars(livreur_note_format_moyenne_label($moyenne, $nb)); ?></p>
            <div style="margin-top:0.35rem;"><?php echo livreur_note_format_stars_html($moyenne !== null ? round((float) $moyenne) : 0); ?></div>
        </div>
    </div>

    <?php if (empty($notes)): ?>
    <div class="empty-state">
        <i class="fas fa-comment-slash"></i>
        <p>Aucune note client pour ce livreur.</p>
    </div>
    <?php else: ?>
    <div class="livreur-notes-list">
        <?php foreach ($notes as $note_row): ?>
            <?php
            $type_label = ($note_row['livraison_type'] ?? '') === 'facture' ? 'Facture / BL' : 'Commande';
            $ref = trim((string) ($note_row['numero_reference'] ?? ''));
            $date_aff = !empty($note_row['date_creation'])
                ? date('d/m/Y H:i', strtotime($note_row['date_creation']))
                : '';
            ?>
            <article class="livreur-notes-item">
                <div class="livreur-notes-item__top">
                    <span class="livreur-notes-item__client">
                        <?php echo htmlspecialchars($note_row['client_nom'] ?? 'Client'); ?>
                        <?php if (!empty($note_row['client_telephone'])): ?>
                        <span style="font-weight:400;color:#888;"> — <?php echo htmlspecialchars($note_row['client_telephone']); ?></span>
                        <?php endif; ?>
                    </span>
                    <span class="livreur-notes-item__date"><?php echo htmlspecialchars($date_aff); ?></span>
                </div>
                <div><?php echo livreur_note_format_stars_html((int) ($note_row['note'] ?? 0)); ?>
                    <strong style="margin-left:0.35rem;color:#918a44;"><?php echo (int) ($note_row['note'] ?? 0); ?>/5</strong>
                </div>
                <p class="livreur-notes-item__ref">
                    <?php echo htmlspecialchars($type_label); ?>
                    <?php if ($ref !== ''): ?> — <?php echo htmlspecialchars($ref); ?><?php endif; ?>
                </p>
            </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
