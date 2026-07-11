<?php
/**
 * Détail d'une boutique vendeur — Super Admin
 */
require_once __DIR__ . '/../includes/require_login.php';
require_once dirname(__DIR__, 2) . '/models/model_super_admin.php';
require_once dirname(__DIR__, 2) . '/models/model_produits.php';
require_once dirname(__DIR__, 2) . '/models/model_vendeur_certification.php';
require_once dirname(__DIR__, 2) . '/includes/image_optimizer.php';
require_once dirname(__DIR__, 2) . '/includes/marketplace_helpers.php';

$msg_ok = $_SESSION['super_admin_flash_ok'] ?? '';
$msg_err = $_SESSION['super_admin_flash_err'] ?? '';
unset($_SESSION['super_admin_flash_ok'], $_SESSION['super_admin_flash_err']);

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$b = $id > 0 ? super_admin_get_boutique_stats($id) : false;
if (!$b) {
    header('Location: index.php');
    exit;
}

$vitrine = !empty($b['boutique_slug']) ? boutique_url('index.php', (string) $b['boutique_slug']) : '';
$titre = (string) ($b['boutique_nom'] ?: $b['nom']);
$dc = !empty($b['date_creation']) ? $b['date_creation'] : '';
$dc_fmt = $dc !== '' ? date('d/m/Y à H:i', strtotime((string) $dc)) : '—';
$est_actif = (($b['statut'] ?? '') === 'actif');

$n_cat = (int) $b['nb_produits_catalogue'];
$n_act = (int) $b['nb_produits_actifs'];
$n_rup = (int) $b['nb_produits_rupture'];
$n_ina = (int) $b['nb_produits_inactifs'];
$n_tot = (int) $b['nb_produits_total'];

$cert_niveau_actif = vendeur_certification_admin_column_exists()
    ? trim((string) ($b['certification_niveau'] ?? ''))
    : '';
$cert_date = !empty($b['certification_date']) ? date('d/m/Y', strtotime((string) $b['certification_date'])) : '';

$produits_boutique = super_admin_get_produits_boutique($id);
$moderation_ok = produit_moderation_plateforme_active();
$csrf = super_admin_csrf_token();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include dirname(__DIR__, 2) . '/includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($titre, ENT_QUOTES, 'UTF-8'); ?> — Boutique · Super Admin</title>
    <?php require_once dirname(__DIR__, 2) . '/includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/vendor-cert-ribbon.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-clients.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-boutique-detail.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/product-cards.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/mp-category-page.css<?php echo asset_version_query(); ?>">
</head>

<body class="page-users admin-clients-page sa-users-page sa-boutique-detail">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <div class="sa-users-shell">
        <header class="sa-users-hero" aria-labelledby="sa-bd-title">
            <div class="sa-users-hero__inner">
                <div>
                    <p class="sa-users-hero__eyebrow"><i class="fas fa-clipboard-list" aria-hidden="true"></i> Fiche boutique vendeur</p>
                    <h1 class="sa-users-hero__title" id="sa-bd-title"><?php echo htmlspecialchars($titre, ENT_QUOTES, 'UTF-8'); ?></h1>
                    <?php if (!empty($b['boutique_slug'])): ?>
                        <p class="sa-bd-slug" title="Slug URL"><i class="fas fa-link" aria-hidden="true"></i> <?php echo htmlspecialchars((string) $b['boutique_slug'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>
                    <div class="sa-bd-hero-actions">
                        <a class="sa-bd-btn sa-bd-btn--ghost" href="index.php"><i class="fas fa-arrow-left" aria-hidden="true"></i> Retour à la liste</a>
                        <?php if ($vitrine !== ''): ?>
                            <a class="sa-bd-btn sa-bd-btn--accent" href="<?php echo htmlspecialchars($vitrine, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                                <i class="fas fa-external-link-alt" aria-hidden="true"></i> Ouvrir la vitrine
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="sa-users-kpis" role="group" aria-label="Synthèse boutique">
                    <div class="sa-users-kpi">
                        <span class="sa-users-kpi__label">Accès vendeur</span>
                        <?php if ($est_actif): ?>
                            <span class="sa-badge-hero sa-badge-hero--ok" style="display:block;margin-top:6px;">Actif</span>
                        <?php else: ?>
                            <span class="sa-badge-hero sa-badge-hero--off" style="display:block;margin-top:6px;">Désactivé</span>
                        <?php endif; ?>
                    </div>
                    <div class="sa-users-kpi">
                        <span class="sa-users-kpi__label">Produits visibles</span>
                        <span class="sa-users-kpi__value"><?php echo $n_cat; ?></span>
                    </div>
                    <div class="sa-users-kpi">
                        <span class="sa-users-kpi__label">Références totales</span>
                        <span class="sa-users-kpi__value"><?php echo $n_tot; ?></span>
                    </div>
                </div>
            </div>
        </header>

        <?php if ($msg_ok !== ''): ?>
            <div class="sa-alert sa-alert--ok" role="status"><i class="fas fa-check-circle"></i><span><?php echo htmlspecialchars($msg_ok, ENT_QUOTES, 'UTF-8'); ?></span></div>
        <?php endif; ?>
        <?php if ($msg_err !== ''): ?>
            <div class="sa-alert sa-alert--err" role="alert"><i class="fas fa-exclamation-circle"></i><span><?php echo htmlspecialchars($msg_err, ENT_QUOTES, 'UTF-8'); ?></span></div>
        <?php endif; ?>

        <div class="sa-bd-panels">
            <section class="sa-bd-card" aria-labelledby="sa-bd-coord">
                <div class="sa-bd-card__head">
                    <i class="fas fa-user-tie" aria-hidden="true"></i>
                    <h2 id="sa-bd-coord">Identité &amp; contact</h2>
                </div>
                <div class="sa-bd-card__body">
                    <div class="sa-bd-row">
                        <div class="sa-bd-label">Titulaire du compte</div>
                        <div class="sa-bd-value"><?php echo htmlspecialchars(trim($b['nom'] . ' ' . ($b['prenom'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <div class="sa-bd-row">
                        <div class="sa-bd-label">E-mail</div>
                        <div class="sa-bd-value"><?php echo htmlspecialchars((string) ($b['email'] ?: '—'), ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <div class="sa-bd-row">
                        <div class="sa-bd-label">Téléphone</div>
                        <div class="sa-bd-value"><?php echo htmlspecialchars((string) ($b['telephone'] ?: '—'), ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <div class="sa-bd-row">
                        <div class="sa-bd-label">Compte créé le</div>
                        <div class="sa-bd-value"><?php echo htmlspecialchars($dc_fmt, ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                </div>
            </section>

            <section class="sa-bd-card" aria-labelledby="sa-bd-cert">
                <div class="sa-bd-card__head">
                    <i class="fas fa-certificate" aria-hidden="true"></i>
                    <h2 id="sa-bd-cert">Certification</h2>
                </div>
                <div class="sa-bd-card__body">
                    <?php if ($cert_niveau_actif !== ''): ?>
                        <div class="sa-bd-cert-active">
                            <?php $cert_niveau = $cert_niveau_actif; $cert_size = 'md'; require dirname(__DIR__, 2) . '/includes/partials/vendeur_certification_badge.php'; ?>
                            <p class="sa-bd-cert-meta">
                                Boutique certifiée
                                <?php if ($cert_date !== ''): ?>
                                    depuis le <strong><?php echo htmlspecialchars($cert_date, ENT_QUOTES, 'UTF-8'); ?></strong>
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <p class="sa-bd-cert-none"><i class="fas fa-circle-info"></i> Cette boutique n’est pas encore certifiée.</p>
                        <a class="sa-bd-btn sa-bd-btn--ghost sa-bd-btn--inline" href="../certifications/index.php?tab=en_cours">Voir les demandes de certification</a>
                    <?php endif; ?>
                </div>
            </section>

            <section class="sa-bd-card sa-bd-card--wide" aria-labelledby="sa-bd-cat">
                <div class="sa-bd-card__head">
                    <i class="fas fa-boxes-stacked" aria-hidden="true"></i>
                    <h2 id="sa-bd-cat">Catalogue produits</h2>
                </div>
                <div class="sa-bd-card__body">
                    <div class="sa-bd-metrics" role="list">
                        <div class="sa-bd-metric" role="listitem">
                            <div class="sa-bd-metric__num"><?php echo $n_act; ?></div>
                            <div class="sa-bd-metric__label">Actifs</div>
                        </div>
                        <div class="sa-bd-metric" role="listitem">
                            <div class="sa-bd-metric__num"><?php echo $n_rup; ?></div>
                            <div class="sa-bd-metric__label">Rupture</div>
                        </div>
                        <div class="sa-bd-metric" role="listitem">
                            <div class="sa-bd-metric__num"><?php echo $n_ina; ?></div>
                            <div class="sa-bd-metric__label">Hors catalogue</div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <section class="sa-bd-produits" aria-labelledby="sa-bd-produits-title">
            <div class="sa-bd-produits__head">
                <h2 id="sa-bd-produits-title"><i class="fas fa-images"></i> Produits publiés</h2>
                <p><?php echo count($produits_boutique); ?> produit<?php echo count($produits_boutique) > 1 ? 's' : ''; ?> (actifs, rupture ou bloqués)</p>
            </div>

            <?php if (!$moderation_ok): ?>
                <div class="sa-alert sa-alert--err" role="alert">
                    <i class="fas fa-database"></i>
                    <span>Exécutez la migration <code>php migrations/run_migrate_produit_bloque_plateforme.php</code> pour activer le blocage produits.</span>
                </div>
            <?php endif; ?>

            <?php if (empty($produits_boutique)): ?>
                <div class="sa-users-empty sa-bd-produits-empty">
                    <i class="fas fa-box-open"></i>
                    <p>Aucun produit publié pour cette boutique.</p>
                </div>
            <?php else: ?>
                <div class="mp-grid sa-bd-mp-grid">
                    <?php foreach ($produits_boutique as $pr):
                        $produit = $pr;
                        $vendeur_id = $id;
                        require dirname(__DIR__, 2) . '/includes/partials/super_admin_mp_product_card.php';
                    endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
