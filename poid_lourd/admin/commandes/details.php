<?php
/**
 * Page de détails d'une commande (Admin)
 * Programmation procédurale uniquement
 */

ob_start();

require_once __DIR__ . '/../includes/require_admin_session.php';

// Récupérer l'ID de la commande (GET ou POST — formulaires de statut)
$commande_id = isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;

if ($commande_id <= 0) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';

// Récupérer la commande et ses produits
require_once __DIR__ . '/../../models/model_commandes_admin.php';
require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../models/model_factures.php';
require_once __DIR__ . '/../../includes/format_commande_options.php';
require_once __DIR__ . '/../../includes/commande_suivi_ui.php';
require_once __DIR__ . '/../../includes/image_optimizer.php';
$commande = get_commande_by_id($commande_id);
$produits = get_produits_by_commande($commande_id);
$produits = is_array($produits) ? $produits : [];
$facture = get_facture_by_commande($commande_id);

if (!$commande) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../includes/admin_route_access.php';
$vf_ck = admin_vendeur_filter_id();
if ($vf_ck !== null && (int) ($commande['vendeur_id'] ?? 0) !== $vf_ck) {
    header('Location: index.php');
    exit;
}

$statut_raw = commande_statut_ui_normalize((string) ($commande['statut'] ?? ''));
$statut_safe_class = htmlspecialchars($statut_raw, ENT_QUOTES, 'UTF-8');
$statut_display = ucfirst(str_replace('_', ' ', $statut_raw));
if ($statut_raw === 'annulee') {
    $statut_display = 'Annulée';
} elseif ($statut_raw === 'paye') {
    $statut_display = 'Payée';
}

// Vérifier si la commande est annulée ou livrée (pas de modification possible)
$is_annulee = $commande['statut'] === 'annulee';
$is_livree = $commande['statut'] === 'livree';
$is_paye = $commande['statut'] === 'paye';

// Traiter les actions de statut (uniquement si la commande n'est pas annulée)
$statut_flash_err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_annulee && $commande_id > 0) {
    $statut_mis_a_jour = null;

    $admin_traitant = (int) ($_SESSION['admin_id'] ?? 0);

    if (isset($_POST['prendre_en_charge'])) {
        if (update_commande_statut($commande_id, 'prise_en_charge', $admin_traitant)) {
            $statut_mis_a_jour = 'prise_en_charge';
        }
    } elseif (isset($_POST['expedier'])) {
        if (update_commande_statut($commande_id, 'livraison_en_cours', $admin_traitant)) {
            $statut_mis_a_jour = 'livraison_en_cours';
        }
    } elseif (isset($_POST['confirmer_livraison'])) {
        if (update_commande_statut($commande_id, 'livree', $admin_traitant)) {
            $statut_mis_a_jour = 'livree';
        }
    }

    if ($statut_mis_a_jour !== null) {
        try {
            require_once __DIR__ . '/../../services/send_commande_notification.php';
            send_commande_status_notification(
                (int) ($commande['user_id'] ?? 0),
                $commande['numero_commande'],
                $statut_mis_a_jour,
                trim($commande['user_email'] ?? '')
            );
        } catch (Throwable $e) {
            error_log('[commandes/details] notification : ' . $e->getMessage());
        }

        $_SESSION['success_message'] = 'Statut de la commande mis à jour avec succès. Le client a été notifié.';
        header('Location: /admin/commandes/details.php?id=' . (int) $commande_id);
        exit;
    }

    $statut_flash_err = 'Impossible de mettre à jour le statut. Vérifiez que la commande est dans un état compatible.';
    $err_sql = function_exists('commande_statut_update_last_error') ? commande_statut_update_last_error() : '';
    if ($err_sql !== '') {
        $statut_flash_err .= ' Détail : ' . $err_sql;
    }
    $commande = get_commande_by_id($commande_id);
    if ($commande) {
        $statut_raw = commande_statut_ui_normalize((string) ($commande['statut'] ?? ''));
        $statut_safe_class = htmlspecialchars($statut_raw, ENT_QUOTES, 'UTF-8');
        $statut_display = ucfirst(str_replace('_', ' ', $statut_raw));
        if ($statut_raw === 'annulee') {
            $statut_display = 'Annulée';
        } elseif ($statut_raw === 'paye') {
            $statut_display = 'Payée';
        }
        $is_annulee = $commande['statut'] === 'annulee';
        $is_livree = $commande['statut'] === 'livree';
        $is_paye = $commande['statut'] === 'paye';
    }
}

$detail_form_action = 'details.php?id=' . (int) $commande_id;
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails Commande #<?php echo htmlspecialchars($commande['numero_commande'] ?? ''); ?> - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/commande-suivi-page.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-commandes-details.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/platform-share-modal.css<?php echo asset_version_query(); ?>">
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="contents-container cmd-detail-page">
        <header class="cmd-detail-hero" aria-labelledby="cmd-detail-title">
            <div class="cmd-detail-hero__top">
                <a href="index.php" class="cmd-detail-back"><i class="fas fa-arrow-left" aria-hidden="true"></i> Liste des commandes</a>
                <span class="commande-statut statut-<?php echo $statut_safe_class; ?>"><?php echo htmlspecialchars($statut_display); ?></span>
            </div>
            <div class="cmd-detail-hero__main">
                <div>
                    <h1 class="cmd-detail-hero__title" id="cmd-detail-title">Commande <span>#<?php echo htmlspecialchars($commande['numero_commande'] ?? ''); ?></span></h1>
                    <p class="cmd-detail-meta">
                        <span><i class="far fa-calendar-alt" aria-hidden="true"></i> <?php echo date('d/m/Y à H:i', strtotime($commande['date_commande'])); ?></span>
                        <?php
                        require_once __DIR__ . '/../../includes/commande_mode_helpers.php';
                        if (commande_is_retrait($commande)):
                        ?>
                        <span class="cmd-detail-mode cmd-detail-mode--retrait"><i class="fas fa-store" aria-hidden="true"></i> Retrait sur site</span>
                        <?php endif; ?>
                        <?php
                        $frais_liv = isset($commande['frais_livraison']) ? (float) $commande['frais_livraison'] : 0;
                        if ($frais_liv > 0):
                        ?>
                        <span><i class="fas fa-truck" aria-hidden="true"></i> Livraison <?php echo number_format($frais_liv, 0, ',', ' '); ?> FCFA</span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="cmd-detail-hero__actions">
                    <?php if ($facture): ?>
                    <a href="facture.php?id=<?php echo (int) $facture['id']; ?>" class="btn-primary" target="_blank" rel="noopener noreferrer">
                        <i class="fas fa-file-invoice"></i> Voir la facture
                    </a>
                    <?php else: ?>
                    <a href="generer_facture.php?id=<?php echo (int) $commande_id; ?>" class="btn-primary">
                        <i class="fas fa-file-invoice"></i> Générer une facture
                    </a>
                    <?php endif; ?>
                    <a href="index.php" class="btn-back">
                        <i class="fas fa-list" aria-hidden="true"></i> Retour liste
                    </a>
                </div>
            </div>
        </header>

    <?php if ($statut_flash_err !== ''): ?>
        <div class="cmd-alert cmd-alert--danger" role="alert" style="margin-bottom:1rem;">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($statut_flash_err, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php /* success / error affichés via flash toast — voir footer.php */ ?>

    <?php
    ob_start();
        ?>
        <div class="cmd-suivi-status-slot">
            <div class="cmd-status-embed-shell" aria-labelledby="cmd-status-embed-title">
                <div class="cmd-status-embed-hd">
                    <h3 id="cmd-status-embed-title"><i class="fas fa-sliders-h" aria-hidden="true"></i> Statut &amp; actions</h3>
                </div>
                <div class="cmd-status-embed-bd">
        <?php if ($is_annulee): ?>
            <div class="cmd-alert cmd-alert--danger" role="status">
                <h3><i class="fas fa-ban" aria-hidden="true"></i> Commande annulée</h3>
                <p>Cette commande a été annulée. Aucune modification n’est possible ; vous pouvez consulter les informations ci-dessus.</p>
            </div>
        <?php elseif ($is_livree): ?>
            <div class="cmd-alert cmd-alert--success" role="status">
                <h3><i class="fas fa-check-circle" aria-hidden="true"></i> Commande livrée</h3>
                <p>Le client a confirmé la réception. La commande est terminée.</p>
            </div>
        <?php elseif ($is_paye): ?>
            <div class="cmd-alert cmd-alert--success" role="status">
                <h3><i class="fas fa-money-bill-wave" aria-hidden="true"></i> Commande payée</h3>
                <p>Le paiement est enregistré et le stock a été mis à jour. La commande est terminée.</p>
            </div>
        <?php else: ?>
            <?php
            $csf_steps = [
                ['key' => 'en_attente',        'label' => 'En attente',      'sub' => 'Commande reçue',        'icon' => 'fa-clock'],
                ['key' => 'prise_en_charge',   'label' => 'Prise en charge', 'sub' => 'Traitement en cours',   'icon' => 'fa-hand-paper'],
                ['key' => 'livraison_en_cours','label' => 'En livraison',    'sub' => 'Expédiée au client',    'icon' => 'fa-truck'],
                ['key' => 'livree',            'label' => 'Livrée',          'sub' => 'Réception confirmée',   'icon' => 'fa-circle-check'],
            ];
            $csf_order = [
                'en_attente'         => 0,
                'confirmee'          => 0,
                'prise_en_charge'    => 1,
                'en_preparation'     => 1,
                'livraison_en_cours' => 2,
                'expediee'           => 2,
                'livree'             => 3,
                'paye'               => 3,
            ];
            $csf_current_idx = $csf_order[$statut_raw] ?? 0;
            ?>
            <div class="csf-stepper">
                <div class="csf-track">
                    <?php foreach ($csf_steps as $si => $step):
                        $s_done   = $si < $csf_current_idx;
                        $s_active = $si === $csf_current_idx;
                        $s_cls    = $s_done ? 'done' : ($s_active ? 'active' : 'pending');
                    ?>
                    <div class="csf-node csf-node--<?php echo $s_cls; ?>">
                        <div class="csf-node__mark" aria-hidden="true">
                            <?php if ($s_done): ?><i class="fas fa-check"></i><?php else: ?><i class="fas <?php echo $step['icon']; ?>"></i><?php endif; ?>
                        </div>
                        <div class="csf-node__text">
                            <span class="csf-node__label"><?php echo $step['label']; ?></span>
                            <span class="csf-node__sub"><?php echo $step['sub']; ?></span>
                        </div>
                    </div>
                    <?php if ($si < count($csf_steps) - 1): ?>
                        <div class="csf-line csf-line--<?php echo $si < $csf_current_idx ? 'done' : 'pending'; ?>"></div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <div class="csf-action-zone">
                    <?php if (in_array($statut_raw, ['en_attente', 'confirmee'], true)): ?>
                    <form method="POST" action="<?php echo htmlspecialchars($detail_form_action, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="id" value="<?php echo (int) $commande_id; ?>">
                        <button type="submit" name="prendre_en_charge" value="1" class="csf-action-btn csf-action-btn--charge">
                            <span class="csf-action-btn__icon"><i class="fas fa-hand-paper"></i></span>
                            <span class="csf-action-btn__body"><strong>Prendre en charge</strong><small>Le client sera notifié immédiatement</small></span>
                            <i class="fas fa-chevron-right csf-action-btn__arrow" aria-hidden="true"></i>
                        </button>
                    </form>
                    <?php elseif (in_array($statut_raw, ['prise_en_charge', 'en_preparation'], true)): ?>
                    <form method="POST" action="<?php echo htmlspecialchars($detail_form_action, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="id" value="<?php echo (int) $commande_id; ?>">
                        <button type="submit" name="expedier" value="1" class="csf-action-btn csf-action-btn--ship">
                            <span class="csf-action-btn__icon"><i class="fas fa-truck"></i></span>
                            <span class="csf-action-btn__body"><strong>Mettre en livraison</strong><small>Notifie le client que sa commande est en route</small></span>
                            <i class="fas fa-chevron-right csf-action-btn__arrow" aria-hidden="true"></i>
                        </button>
                    </form>
                    <?php elseif (in_array($statut_raw, ['livraison_en_cours', 'expediee'], true)): ?>
                    <form method="POST" action="<?php echo htmlspecialchars($detail_form_action, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="id" value="<?php echo (int) $commande_id; ?>">
                        <button type="submit" name="confirmer_livraison" value="1" class="csf-action-btn csf-action-btn--confirm">
                            <span class="csf-action-btn__icon"><i class="fas fa-circle-check"></i></span>
                            <span class="csf-action-btn__body"><strong>Confirmer la réception</strong><small>Marque la commande comme livrée au client</small></span>
                            <i class="fas fa-check csf-action-btn__arrow" aria-hidden="true"></i>
                        </button>
                    </form>
                    <?php endif; ?>
                    <?php if ($commande['notes']): ?>
                    <div class="csf-notes">
                        <p class="csf-notes__label"><i class="fas fa-comment-alt"></i> Notes</p>
                        <div class="csf-notes__body"><?php echo nl2br(htmlspecialchars($commande['notes'])); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    $cmd_suivi_status_slot_html = ob_get_clean();
    ?>

    <section class="cmd-suivi-espace-client" aria-label="Suivi commande">
        <?php
        commande_suivi_render_dashboard($commande, [
            'show_client_actions_bar' => false,
            'admin_hint' => false,
            'admin_contact_in_live' => true,
            'admin_compact_meta_row' => true,
            'wrap_class' => 'cc-products-anchor commande-suivi-detail cmd-suivi-admin-embed',
            'slot_replace_progress_html' => $cmd_suivi_status_slot_html,
        ]);
        ?>
    </section>

    <?php
    require_once __DIR__ . '/../../includes/commande_mode_helpers.php';
    $cmd_is_retrait = commande_is_retrait($commande);

    if ($cmd_is_retrait):
    ?>
    <section class="cmd-geo-section cmd-retrait-section" aria-labelledby="cmd-retrait-heading">
        <h2 id="cmd-retrait-heading">
            <i class="fas fa-store" aria-hidden="true"></i>
            Retrait sur site
        </h2>
        <p class="cmd-retrait-badge">
            <i class="fas fa-bag-shopping" aria-hidden="true"></i>
            Le client viendra récupérer sa commande en boutique
        </p>
    </section>
    <?php else:
    /* ---- Position GPS exacte du client (livraison) ---- */
    require_once __DIR__ . '/../../includes/geo_location_service.php';
    $geo_cmd_lat = geo_parse_coord($commande['delivery_latitude'] ?? null);
    $geo_cmd_lng = geo_parse_coord($commande['delivery_longitude'] ?? null);
    if (geo_coords_valid($geo_cmd_lat, $geo_cmd_lng)):
        $geo_distance_txt = '';
        if (!empty($commande['vendeur_id']) && geo_boutiques_ready()) {
            try {
                $stmt_geo_b = $db->prepare("SELECT boutique_latitude, boutique_longitude FROM admin WHERE id = :id");
                $stmt_geo_b->execute(['id' => (int) $commande['vendeur_id']]);
                $geo_b = $stmt_geo_b->fetch(PDO::FETCH_ASSOC);
                $geo_b_lat = $geo_b ? geo_parse_coord($geo_b['boutique_latitude']) : null;
                $geo_b_lng = $geo_b ? geo_parse_coord($geo_b['boutique_longitude']) : null;
                if (geo_coords_valid($geo_b_lat, $geo_b_lng)) {
                    $geo_distance_txt = geo_format_distance(geo_distance_km($geo_b_lat, $geo_b_lng, $geo_cmd_lat, $geo_cmd_lng));
                }
            } catch (PDOException $e) { /* distance facultative */ }
        }
    ?>
    <section class="cmd-geo-section" aria-labelledby="cmd-geo-heading">
        <h2 id="cmd-geo-heading">
            <i class="fas fa-map-location-dot" aria-hidden="true"></i>
            Position exacte du client
        </h2>
        <?php if ($geo_distance_txt !== ''): ?>
        <p class="cmd-geo-distance">
            <i class="fas fa-route" aria-hidden="true"></i>
            À <?php echo htmlspecialchars($geo_distance_txt); ?> de votre boutique
        </p>
        <?php endif; ?>
        <?php
        $geo_nav_lat = $geo_cmd_lat;
        $geo_nav_lng = $geo_cmd_lng;
        $geo_nav_label = 'Commande #' . (string) ($commande['numero_commande'] ?? '');
        include __DIR__ . '/../../includes/partials/geo_nav_apps_buttons.php';
        ?>
    </section>
    <?php endif; ?>
    <?php endif; ?>

    <section class="cmd-products-section" aria-labelledby="cmd-products-heading">
        <div class="cmd-products-section__head">
            <div class="cmd-products-section__head-left">
                <div class="cmd-products-section__icon" aria-hidden="true"><i class="fas fa-receipt"></i></div>
                <div>
                    <h2 id="cmd-products-heading">Lignes de commande</h2>
                    <p class="cmd-products-section__sub"><?php echo count($produits); ?> article<?php echo count($produits) > 1 ? 's' : ''; ?></p>
                </div>
            </div>
        </div>

        <div class="cmd-products-list">
            <?php if (empty($produits)): ?>
            <div class="cmd-products-empty">
                <i class="fas fa-box-open" aria-hidden="true"></i>
                <p>Aucune ligne produit enregistrée pour cette commande.</p>
            </div>
            <?php else: ?>
            <?php foreach ($produits as $idx => $produit): ?>
                <?php
                $img_src = !empty($produit['image_afficher']) ? $produit['image_afficher'] : ($produit['image_principale'] ?? '');
                $nom_affichage = !empty($produit['variante_nom']) ? $produit['produit_nom'] . ' → ' . $produit['variante_nom'] : ($produit['produit_nom'] ?? '');
                ?>
                <div class="cpd-row">
                    <div class="cpd-row__num" aria-hidden="true"><?php echo $idx + 1; ?></div>
                    <div class="cpd-row__img-wrap">
                        <img src="<?php echo htmlspecialchars(upload_image_url($img_src ?? '', 'sm')); ?>"
                            alt="<?php echo htmlspecialchars($nom_affichage ?? ''); ?>"
                            class="cpd-row__img"
                            onerror="this.src='/image/produit1.jpg'">
                    </div>
                    <div class="cpd-row__body">
                        <p class="cpd-row__name"><?php echo htmlspecialchars($nom_affichage ?? ''); ?></p>
                        <div class="cpd-row__chips">
                            <span class="cpd-chip cpd-chip--qty">
                                <i class="fas fa-cubes"></i> <?php echo (int)$produit['quantite']; ?> pièce<?php echo $produit['quantite'] > 1 ? 's' : ''; ?>
                            </span>
                            <span class="cpd-chip cpd-chip--unit">
                                <?php echo number_format((float)$produit['prix_unitaire'], 0, ',', ' '); ?> FCFA / u
                            </span>
                            <?php if (!empty($produit['couleur'])): ?>
                            <?php
                            $hex = trim($produit['couleur']);
                            $is_hex = preg_match('/^#[0-9A-Fa-f]{6}$/', $hex);
                            $nom_couleur = format_couleur_commande($hex);
                            ?>
                            <span class="cpd-chip cpd-chip--color">
                                <?php if ($is_hex): ?>
                                <span class="cpd-chip__swatch" style="background:<?php echo htmlspecialchars($hex); ?>;"></span>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($nom_couleur); ?>
                            </span>
                            <?php endif; ?>
                            <?php if (!empty($produit['variante_nom'])): ?>
                            <span class="cpd-chip cpd-chip--variant"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($produit['variante_nom']); ?></span>
                            <?php endif; ?>
                            <?php
                            $poids_raw = $produit['poids'] ?? '';
                            $taille_raw = $produit['taille'] ?? '';
                            $surcout_p = isset($produit['surcout_poids']) ? (float)$produit['surcout_poids'] : 0;
                            $surcout_t = isset($produit['surcout_taille']) ? (float)$produit['surcout_taille'] : 0;
                            $poids_lignes = parse_poids_taille_commande($poids_raw, $surcout_p);
                            $taille_lignes = parse_poids_taille_commande($taille_raw, $surcout_t);
                            foreach ($poids_lignes as $opt): ?>
                            <span class="cpd-chip cpd-chip--poids"><i class="fas fa-weight-hanging"></i> <?php echo htmlspecialchars($opt['v'] ?? ''); ?><?php if (($opt['s'] ?? 0) > 0) echo ' (+' . number_format($opt['s'], 0, ',', ' ') . ' FCFA)'; ?></span>
                            <?php endforeach; ?>
                            <?php foreach ($taille_lignes as $opt): ?>
                            <span class="cpd-chip cpd-chip--taille"><i class="fas fa-ruler"></i> <?php echo htmlspecialchars($opt['v'] ?? ''); ?><?php if (($opt['s'] ?? 0) > 0) echo ' (+' . number_format($opt['s'], 0, ',', ' ') . ' FCFA)'; ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="cpd-row__total">
                        <span><?php echo number_format((float)$produit['prix_total'], 0, ',', ' '); ?></span>
                        <small>FCFA</small>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="cpd-totals">
                <?php
                $sous_total = array_sum(array_column($produits, 'prix_total'));
                $frais = isset($commande['frais_livraison']) ? (float) $commande['frais_livraison'] : 0;
                ?>
                <?php if ($frais > 0): ?>
                <div class="cpd-totals__line">
                    <span><i class="fas fa-box-open"></i> Sous-total produits</span>
                    <strong><?php echo number_format($sous_total, 0, ',', ' '); ?> FCFA</strong>
                </div>
                <div class="cpd-totals__line">
                    <span><i class="fas fa-truck"></i> Frais de livraison</span>
                    <strong><?php echo number_format($frais, 0, ',', ' '); ?> FCFA</strong>
                </div>
                <div class="cpd-totals__divider"></div>
                <?php endif; ?>
                <div class="cpd-totals__grand">
                    <span>Total commande</span>
                    <div class="cpd-totals__grand-value">
                        <?php echo number_format((float)$commande['montant_total'], 0, ',', ' '); ?>
                        <small>FCFA</small>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>

    </div>

    <?php require __DIR__ . '/../../includes/partials/platform_share_modal.php'; ?>
    <script src="/js/platform-share-modal.js<?php echo asset_version_query(); ?>"></script>
    <script src="/js/geo-nav-apps.js<?php echo asset_version_query(); ?>"></script>

    <?php include '../includes/footer.php'; ?>