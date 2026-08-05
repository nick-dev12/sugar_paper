<?php
require_once __DIR__ . '/../../includes/session_user.php';
/**
 * Page de détails d'une commande (Admin)
 * Programmation procédurale uniquement
 */

session_start_persistent();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

// Récupérer l'ID de la commande
$commande_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($commande_id <= 0) {
    header('Location: index.php');
    exit;
}

// Récupérer la commande et ses produits
require_once __DIR__ . '/../../models/model_commandes_admin.php';
require_once __DIR__ . '/../../models/model_produits.php';
require_once __DIR__ . '/../../includes/image_optimizer.php';
require_once __DIR__ . '/../../models/model_factures.php';
require_once __DIR__ . '/../../models/model_livreur_tracking.php';
require_once __DIR__ . '/../../includes/format_commande_options.php';
require_once __DIR__ . '/../../includes/commande_mode_helpers.php';
require_once __DIR__ . '/../../includes/geo_location.php';
require_once __DIR__ . '/../../includes/admin_permissions.php';
$commande = get_commande_by_id($commande_id);
$produits = get_produits_by_commande($commande_id);
$produits = is_array($produits) ? $produits : [];
$facture = get_facture_by_commande($commande_id);
$cmd_tracking = livreur_tracking_tables_ready() ? livreur_get_commande_tracking($commande_id) : false;
$cmd_livraison_suivable = $cmd_tracking && !empty($cmd_tracking['livreur_id']);
$cmd_is_retrait = commande_is_retrait($commande);
$cmd_mode_label = commande_mode_livraison_label($commande['mode_livraison'] ?? ($cmd_is_retrait ? 'retrait' : 'livraison'));
$cmd_geo_lat = livreur_parse_coord($commande['delivery_latitude'] ?? null);
$cmd_geo_lng = livreur_parse_coord($commande['delivery_longitude'] ?? null);
$cmd_has_geo = $cmd_geo_lat !== null && $cmd_geo_lng !== null;

if (!$commande) {
    header('Location: index.php');
    exit;
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}
$admin_csrf = (string) $_SESSION['admin_csrf'];

// Suppression définitive (admin uniquement)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supprimer_commande'])) {
    $token = $_POST['csrf_token'] ?? '';
    if ($token === '' || !hash_equals($admin_csrf, (string) $token)) {
        $_SESSION['error_message'] = 'Jeton de sécurité invalide. Rechargez la page et réessayez.';
        header('Location: details.php?id=' . $commande_id);
        exit;
    }
    if (!admin_is_full_admin()) {
        $_SESSION['error_message'] = 'Seul un administrateur peut supprimer une commande.';
        header('Location: details.php?id=' . $commande_id);
        exit;
    }
    $result = delete_commande($commande_id);
    if (!empty($result['success'])) {
        $_SESSION['success_message'] = $result['message'];
        header('Location: index.php');
        exit;
    }
    $_SESSION['error_message'] = $result['message'] ?? 'Impossible de supprimer la commande.';
    header('Location: details.php?id=' . $commande_id);
    exit;
}

// Archivage / désarchivage de la commande
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['archiver_commande']) || isset($_POST['desarchiver_commande']))) {
    $token = $_POST['csrf_token'] ?? '';
    if ($token === '' || !hash_equals($admin_csrf, (string) $token)) {
        $_SESSION['error_message'] = 'Jeton de sécurité invalide. Rechargez la page et réessayez.';
        header('Location: details.php?id=' . $commande_id);
        exit;
    }

    if (isset($_POST['desarchiver_commande'])) {
        if (!admin_is_full_admin()) {
            $_SESSION['error_message'] = 'Seul un administrateur peut désarchiver une commande.';
            header('Location: details.php?id=' . $commande_id);
            exit;
        }
        $result = unarchive_commande($commande_id);
        if (!empty($result['success'])) {
            $_SESSION['success_message'] = $result['message'];
            header('Location: details.php?id=' . $commande_id);
            exit;
        }
        $_SESSION['error_message'] = $result['message'] ?? 'Impossible de désarchiver la commande.';
        header('Location: details.php?id=' . $commande_id);
        exit;
    }

    $result = archive_commande($commande_id, (int) ($_SESSION['admin_id'] ?? 0));
    if (!empty($result['success'])) {
        $_SESSION['success_message'] = $result['message'];
        header('Location: index.php');
        exit;
    }

    $_SESSION['error_message'] = $result['message'] ?? 'Impossible d\'archiver la commande.';
    header('Location: details.php?id=' . $commande_id);
    exit;
}

// Vérifier si la commande est annulée ou livrée (pas de modification possible)
$is_annulee = $commande['statut'] === 'annulee';
$is_livree = $commande['statut'] === 'livree';
$is_paye = $commande['statut'] === 'paye';
$is_archivee = commande_est_archivee($commande);
$can_desarchiver = admin_is_full_admin() && $is_archivee;
$can_supprimer_commande = admin_is_full_admin();

// Traiter les actions de statut (uniquement si la commande n'est pas annulée ni archivée)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_annulee && !$is_archivee) {
    $statut_mis_a_jour = null;

    if (isset($_POST['prendre_en_charge'])) {
        if (update_commande_statut($commande_id, 'prise_en_charge')) {
            $statut_mis_a_jour = 'prise_en_charge';
        }
    } elseif (isset($_POST['expedier'])) {
        if (update_commande_statut($commande_id, 'livraison_en_cours')) {
            $statut_mis_a_jour = 'livraison_en_cours';
        }
    } elseif (isset($_POST['changer_statut'])) {
        $nouveau_statut = $_POST['statut'] ?? '';
        if (in_array($nouveau_statut, ['en_attente', 'prise_en_charge', 'en_preparation', 'livraison_en_cours', 'paye', 'annulee'])) {
            if (update_commande_statut($commande_id, $nouveau_statut)) {
                $statut_mis_a_jour = $nouveau_statut;
            } else {
                $_SESSION['error_message'] = 'Impossible de mettre à jour le statut. Vérifiez que la migration "add_statut_paye_commandes" a été exécutée et que la commande contient des produits.';
            }
        }
    }

    if ($statut_mis_a_jour !== null) {
        $_SESSION['success_message'] = 'Statut de la commande mis à jour avec succès. Le client sera notifié s\'il est connecté et a activé les notifications.';
        header('Location: details.php?id=' . $commande_id);
        exit;
    }
}
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
    <link rel="stylesheet" href="/css/platform-share-modal.css<?php echo asset_version_query(); ?>">
    <style>
        .detail-mode-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 999px;
            font-weight: 600;
            font-size: 13px;
        }
        .detail-mode-badge--retrait {
            background: rgba(145, 138, 68, 0.15);
            color: #6b2f20;
        }
        .detail-mode-badge--livraison {
            background: rgba(229, 72, 138, 0.12);
            color: #b8326a;
        }
        .geo-nav-apps {
            margin-top: 10px;
        }
        .geo-nav-app {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            border: none;
            border-radius: 8px;
            background: #e5488a;
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
        }
        .geo-nav-app:hover {
            background: #c93d76;
        }
    </style>
</head>

<body class="page-admin-doc-detail">
    <?php include '../includes/nav.php'; ?>

    <div class="content-header">
        <h1>
            <i class="fas fa-shopping-bag"></i> Commande #<?php echo htmlspecialchars($commande['numero_commande'] ?? ''); ?>
        </h1>
        <div class="header-actions header-actions--primary-row">
            <?php if ($facture): ?>
                <a href="facture.php?id=<?php echo (int) $facture['id']; ?>" class="btn-primary">
                    <i class="fas fa-file-invoice"></i> Voir la facture
                </a>
            <?php else: ?>
                <a href="generer_facture.php?id=<?php echo $commande_id; ?>" class="btn-primary">
                    <i class="fas fa-file-invoice"></i> Générer une facture
                </a>
            <?php endif; ?>
            <?php if ($cmd_livraison_suivable): ?>
            <a href="../livreurs/suivi.php?commande_id=<?php echo (int) $commande_id; ?>&amp;regarder=1" class="btn-secondary">
                <i class="fas fa-map-location-dot"></i> Suivre la livraison
            </a>
            <?php endif; ?>
            <a href="<?php echo $is_archivee && admin_is_full_admin() ? 'archives.php' : 'index.php'; ?>" class="btn-back">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
            <?php if ($can_desarchiver): ?>
            <button type="button" class="btn-secondary" id="btn-open-archive-commande"
                title="Désarchiver cette commande">
                <i class="fas fa-box-open"></i> Désarchiver
            </button>
            <?php elseif (!$is_archivee): ?>
            <button type="button" class="btn-secondary" id="btn-open-archive-commande"
                title="Archiver cette commande">
                <i class="fas fa-box-archive"></i> Archiver
            </button>
            <?php endif; ?>
            <?php if ($can_supprimer_commande): ?>
            <button type="button" class="btn-delete" id="btn-open-supprimer-commande"
                title="Supprimer définitivement cette commande">
                <i class="fas fa-trash-alt"></i> Supprimer
            </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="message success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo htmlspecialchars($_SESSION['success_message'] ?? '');
            unset($_SESSION['success_message']); ?></span>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="message error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars($_SESSION['error_message'] ?? '');
            unset($_SESSION['error_message']); ?></span>
        </div>
    <?php endif; ?>

    <!-- Détails de la commande -->
    <div class="commande-details-grid">
        <div class="detail-box">
            <h3><i class="fas fa-user"></i> Informations Client</h3>
            <div class="detail-item">
                <label>Nom complet</label>
                <div class="value">
                    <?php echo htmlspecialchars(trim(($commande['user_prenom'] ?? '') . ' ' . ($commande['user_nom'] ?? ''))); ?>
                </div>
            </div>
            <div class="detail-item">
                <label>Email</label>
                <div class="value"><?php echo htmlspecialchars($commande['user_email'] ?? ''); ?></div>
            </div>
            <div class="detail-item">
                <label>Téléphone</label>
                <div class="value"><?php echo htmlspecialchars($commande['user_telephone'] ?? ''); ?></div>
            </div>
        </div>

        <div class="detail-box">
            <h3><i class="fas fa-map-marker-alt"></i> Livraison</h3>
            <div class="detail-item">
                <label>Option</label>
                <div class="value">
                    <span class="detail-mode-badge <?php echo $cmd_is_retrait ? 'detail-mode-badge--retrait' : 'detail-mode-badge--livraison'; ?>">
                        <i class="fas fa-<?php echo $cmd_is_retrait ? 'store' : 'truck'; ?>" aria-hidden="true"></i>
                        <?php echo htmlspecialchars($cmd_mode_label); ?>
                    </span>
                </div>
            </div>
            <div class="detail-item">
                <label>Adresse</label>
                <div class="value"><?php echo nl2br(htmlspecialchars($commande['adresse_livraison'] ?? '')); ?></div>
            </div>
            <?php if (!$cmd_is_retrait && $cmd_has_geo): ?>
            <div class="detail-item">
                <label>Localisation client</label>
                <div class="value">
                    <?php
                    $geo_nav_lat = $cmd_geo_lat;
                    $geo_nav_lng = $cmd_geo_lng;
                    $geo_nav_label = 'Commande #' . (string) ($commande['numero_commande'] ?? '');
                    include __DIR__ . '/../../includes/partials/geo_nav_apps_buttons.php';
                    ?>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($commande['frais_livraison'])): ?>
                <div class="detail-item">
                    <label>Frais de livraison</label>
                    <div class="value"><?php echo number_format($commande['frais_livraison'], 0, ',', ' '); ?> FCFA</div>
                </div>
            <?php endif; ?>
            <div class="detail-item">
                <label>Date commande</label>
                <div class="value"><?php echo date('d/m/Y à H:i', strtotime($commande['date_commande'])); ?></div>
            </div>
            <?php if ($commande['date_livraison']): ?>
                <div class="detail-item">
                    <label>Date livraison</label>
                    <div class="value"><?php echo date('d/m/Y à H:i', strtotime($commande['date_livraison'])); ?></div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Produits de la commande -->
    <section class="content-section">
        <div class="section-title">
            <h2><i class="fas fa-box"></i> Produits Commandés</h2>
        </div>

        <div class="produits-list">
            <?php foreach ($produits as $produit): ?>
                <?php $img_src = !empty($produit['image_afficher']) ? $produit['image_afficher'] : ($produit['image_principale'] ?? ''); ?>
                <?php $nom_affichage = !empty($produit['variante_nom']) ? $produit['produit_nom'] . ' → ' . $produit['variante_nom'] : ($produit['produit_nom'] ?? ''); ?>
                <div class="produit-item">
                    <img src="<?php echo htmlspecialchars(upload_image_url($img_src ?? '', 'sm')); ?>"
                        alt="<?php echo htmlspecialchars($nom_affichage ?? ''); ?>"
                        onerror="this.src='/image/produit1.jpg'">
                    <div class="produit-info">
                        <h4><?php echo htmlspecialchars($nom_affichage ?? ''); ?></h4>
                        <div class="produit-info-lignes">
                            <div class="info-ligne">Quantité: <?php echo $produit['quantite']; ?></div>
                            <div class="info-ligne">Prix unitaire: <?php echo number_format($produit['prix_unitaire'], 0, ',', ' '); ?> FCFA</div>
                        </div>
                        <?php if (!empty($produit['couleur']) || !empty($produit['poids']) || !empty($produit['taille']) || !empty($produit['variante_nom']) || (!empty($produit['surcout_poids']) && $produit['surcout_poids'] > 0) || (!empty($produit['surcout_taille']) && $produit['surcout_taille'] > 0)): ?>
                        <div class="produit-options-detail">
                            <?php if (!empty($produit['variante_nom'])): ?>
                            <div class="option-detail option-variante">
                                <span class="option-label">Variante:</span>
                                <span class="option-value"><?php echo htmlspecialchars($produit['variante_nom'] ?? ''); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($produit['couleur'])): ?>
                            <?php
                            $hex = trim($produit['couleur']);
                            $is_hex = preg_match('/^#[0-9A-Fa-f]{6}$/', $hex);
                            $nom_couleur = format_couleur_commande($hex);
                            ?>
                            <div class="option-detail option-couleur">
                                <span class="option-label">Couleur:</span>
                                <?php if ($is_hex): ?>
                                <span class="couleur-swatch-large" style="background-color:<?php echo htmlspecialchars($hex ?? ''); ?>;" title="<?php echo htmlspecialchars($hex ?? ''); ?>"></span>
                                <?php endif; ?>
                                <span class="option-value"><?php echo htmlspecialchars($nom_couleur ?? ''); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php 
                            $poids_raw = $produit['poids'] ?? '';
                            $taille_raw = $produit['taille'] ?? '';
                            $surcout_p = isset($produit['surcout_poids']) ? (float)$produit['surcout_poids'] : 0;
                            $surcout_t = isset($produit['surcout_taille']) ? (float)$produit['surcout_taille'] : 0;
                            $poids_lignes = parse_poids_taille_commande($poids_raw, $surcout_p);
                            $taille_lignes = parse_poids_taille_commande($taille_raw, $surcout_t);
                            $afficher_poids = !empty($poids_lignes);
                            $afficher_taille = !empty($taille_lignes);
                            ?>
                            <?php if ($afficher_poids): ?>
                            <div class="option-detail option-poids">
                                <span class="option-label">Poids:</span>
                                <div class="option-value options-lignes">
                                    <?php foreach ($poids_lignes as $opt): ?>
                                    <div class="option-ligne"><?php 
                                    echo htmlspecialchars($opt['v'] ?? ''); 
                                    if (($opt['s'] ?? 0) > 0) echo ' (poids +' . number_format($opt['s'], 0, ',', ' ') . ' FCFA)';
                                    ?></div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php if ($afficher_taille): ?>
                            <div class="option-detail option-taille">
                                <span class="option-label">Taille:</span>
                                <div class="option-value options-lignes">
                                    <?php foreach ($taille_lignes as $opt): ?>
                                    <div class="option-ligne"><?php 
                                    echo htmlspecialchars($opt['v'] ?? ''); 
                                    if (($opt['s'] ?? 0) > 0) echo ' (taille +' . number_format($opt['s'], 0, ',', ' ') . ' FCFA)';
                                    ?></div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="produit-total">
                        <?php echo number_format($produit['prix_total'], 0, ',', ' '); ?> FCFA
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="produits-list-total">
                <?php
                $sous_total = array_sum(array_column($produits, 'prix_total'));
                $frais = isset($commande['frais_livraison']) ? (float) $commande['frais_livraison'] : 0;
                ?>
                <?php if ($frais > 0): ?>
                    <p style="margin-bottom: 8px;">Sous-total produits:
                        <?php echo number_format($sous_total, 0, ',', ' '); ?> FCFA
                    </p>
                    <p style="margin-bottom: 8px;">Frais de livraison: <?php echo number_format($frais, 0, ',', ' '); ?>
                        FCFA</p>
                <?php endif; ?>
                <h3>Total: <span
                        class="total-value"><?php echo number_format($commande['montant_total'], 0, ',', ' '); ?>
                        FCFA</span></h3>
            </div>
        </div>
    </section>

    <!-- Actions rapides -->
    <section class="content-section">
        <div class="section-title">
            <h2><i class="fas fa-tasks"></i> Statut de la commande</h2>
        </div>

        <?php if ($is_archivee): ?>
            <div class="alert-annulee">
                <h3><i class="fas fa-box-archive"></i> Commande archivée</h3>
                <p>Cette commande est archivée. Les modifications de statut sont désactivées.
                    <?php if ($can_desarchiver): ?>Vous pouvez la désarchiver via le bouton en haut de page.<?php endif; ?>
                </p>
            </div>
        <?php elseif ($is_annulee): ?>
            <div class="alert-annulee">
                <h3><i class="fas fa-ban"></i> Commande Annulée</h3>
                <p>Cette commande a été annulée. Les actions de modification ne sont pas disponibles. Vous pouvez uniquement
                    consulter les détails.</p>
            </div>
        <?php elseif ($is_livree): ?>
            <div class="alert-livree">
                <h3><i class="fas fa-check-circle"></i> Commande livrée</h3>
                <p>Le client a confirmé la réception du colis. La commande est terminée. Aucune modification n'est possible.
                </p>
            </div>
        <?php elseif ($is_paye): ?>
            <div class="alert-livree">
                <h3><i class="fas fa-money-bill-wave"></i> Commande payée</h3>
                <p>La commande a été marquée comme payée. Le stock a été décrémenté. La commande est terminée.
                </p>
            </div>
        <?php else: ?>
            <div class="statut-form">
                <div class="form-group">
                    <label>Statut actuel</label>
                    <div class="statut-current-wrap">
                        <span class="commande-statut statut-<?php echo $commande['statut']; ?>">
                            <?php
                            $statut_display = ucfirst(str_replace('_', ' ', $commande['statut']));
                            if ($commande['statut'] == 'annulee') {
                                $statut_display = 'Annulée';
                            } elseif ($commande['statut'] == 'paye') {
                                $statut_display = 'Payée';
                            }
                            echo $statut_display;
                            ?>
                        </span>
                    </div>
                </div>

                <div class="form-group">
                    <?php if (in_array($commande['statut'], ['en_attente', 'confirmee'])): ?>
                        <form method="POST" action="">
                            <button type="submit" name="prendre_en_charge" class="btn-primary btn-prise-charge">
                                <i class="fas fa-hand-paper"></i> Prendre en charge la commande
                            </button>
                        </form>

                    <?php elseif ($commande['statut'] == 'prise_en_charge'): ?>
                        <form method="POST" action="">
                            <button type="submit" name="expedier" class="btn-primary btn-expedier">
                                <i class="fas fa-shipping-fast"></i> Mettre en livraison
                            </button>
                        </form>

                    <?php elseif ($commande['statut'] == 'livraison_en_cours'): ?>
                        <div class="alert-livraison">
                            <p><i class="fas fa-truck"></i> Commande en cours de livraison</p>
                            <p class="sub">Vous pouvez changer le statut manuellement ci-dessous pour la marquer comme "Payée" (décrémente le stock)</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Formulaire de changement manuel de statut (masqué si livrée ou payée) -->
                <div class="actions-divider">
                    <h3>Changer le statut manuellement</h3>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="statut">Nouveau statut</label>
                            <select id="statut" name="statut" required>
                                <option value="en_attente" <?php echo $commande['statut'] == 'en_attente' ? 'selected' : ''; ?>>En Attente</option>
                                <option value="prise_en_charge" <?php echo $commande['statut'] == 'prise_en_charge' ? 'selected' : ''; ?>>Prise en charge</option>
                                <option value="en_preparation" <?php echo $commande['statut'] == 'en_preparation' ? 'selected' : ''; ?>>En Préparation</option>
                                <option value="livraison_en_cours" <?php echo $commande['statut'] == 'livraison_en_cours' ? 'selected' : ''; ?>>Livraison en cours</option>
                                <option value="paye" <?php echo $commande['statut'] == 'paye' ? 'selected' : ''; ?>>Payée (décrémente le stock)</option>
                                <option value="annulee" <?php echo $commande['statut'] == 'annulee' ? 'selected' : ''; ?>>Annulée</option>
                            </select>
                        </div>
                        <?php if ($commande['notes']): ?>
                            <div class="form-group">
                                <label>Notes</label>
                                <div class="notes-box">
                                    <?php echo nl2br(htmlspecialchars($commande['notes'] ?? '')); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <button type="submit" name="changer_statut" class="btn-primary">
                            <i class="fas fa-save"></i> Mettre à jour le statut
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <div id="modal-archive-commande" class="modal-overlay commande-delete-modal-overlay" role="dialog"
        aria-modal="true" aria-labelledby="modal-archive-commande-title" aria-hidden="true">
        <div class="modal-content commande-delete-modal">
            <div class="modal-header">
                <h2 class="modal-title" id="modal-archive-commande-title">
                    <i class="fas <?php echo $can_desarchiver ? 'fa-box-open' : 'fa-box-archive'; ?>"></i>
                    <?php echo $can_desarchiver ? 'Désarchiver la commande' : 'Archiver la commande'; ?>
                </h2>
                <button type="button" class="modal-close" id="btn-close-archive-commande" aria-label="Fermer">
                    &times;
                </button>
            </div>
            <?php if ($can_desarchiver): ?>
            <p class="commande-delete-modal__lead">
                Désarchiver la commande
                <strong>#<?php echo htmlspecialchars($commande['numero_commande'] ?? ''); ?></strong> ?
                Elle réapparaîtra dans la liste des commandes actives.
            </p>
            <form method="POST" action="" class="commande-delete-modal__form" id="form-archive-commande">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($admin_csrf); ?>">
                <div class="commande-delete-modal__actions">
                    <button type="button" class="btn-secondary" id="btn-cancel-archive-commande">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                    <button type="submit" name="desarchiver_commande" class="btn-primary">
                        <i class="fas fa-box-open"></i> Désarchiver
                    </button>
                </div>
            </form>
            <?php else: ?>
            <p class="commande-delete-modal__lead">
                Archiver la commande
                <strong>#<?php echo htmlspecialchars($commande['numero_commande'] ?? ''); ?></strong> ?
            </p>
            <div class="commande-delete-modal__warning">
                <p><i class="fas fa-info-circle"></i> La commande disparaîtra de la liste active mais ne sera pas supprimée.</p>
                <p>Seul un administrateur pourra la consulter et la désarchiver depuis les Archives.</p>
            </div>
            <form method="POST" action="" class="commande-delete-modal__form" id="form-archive-commande">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($admin_csrf); ?>">
                <div class="commande-delete-modal__actions">
                    <button type="button" class="btn-secondary" id="btn-cancel-archive-commande">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                    <button type="submit" name="archiver_commande" class="btn-primary">
                        <i class="fas fa-box-archive"></i> Archiver
                    </button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($can_supprimer_commande): ?>
    <div id="modal-supprimer-commande" class="modal-overlay commande-delete-modal-overlay" role="dialog"
        aria-modal="true" aria-labelledby="modal-supprimer-commande-title" aria-hidden="true">
        <div class="modal-content commande-delete-modal">
            <div class="modal-header">
                <h2 class="modal-title" id="modal-supprimer-commande-title">
                    <i class="fas fa-trash-alt"></i> Supprimer la commande
                </h2>
                <button type="button" class="modal-close" id="btn-close-supprimer-commande" aria-label="Fermer">
                    &times;
                </button>
            </div>
            <p class="commande-delete-modal__lead">
                Supprimer définitivement la commande
                <strong>#<?php echo htmlspecialchars($commande['numero_commande'] ?? ''); ?></strong> ?
            </p>
            <div class="commande-delete-modal__warning">
                <p><i class="fas fa-exclamation-triangle"></i> Cette action est irréversible.</p>
                <p>La commande, ses produits associés et la facture liée seront définitivement effacés.</p>
            </div>
            <form method="POST" action="" class="commande-delete-modal__form" id="form-supprimer-commande">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($admin_csrf); ?>">
                <div class="commande-delete-modal__actions">
                    <button type="button" class="btn-secondary" id="btn-cancel-supprimer-commande">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                    <button type="submit" name="supprimer_commande" class="btn-delete">
                        <i class="fas fa-trash-alt"></i> Supprimer définitivement
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        (function () {
            function bindConfirmModal(modalId, openBtnId, closeBtnId, cancelBtnId) {
                var modal = document.getElementById(modalId);
                var openBtn = document.getElementById(openBtnId);
                var closeBtn = document.getElementById(closeBtnId);
                var cancelBtn = document.getElementById(cancelBtnId);
                if (!modal || !openBtn) return;

                function openModal() {
                    modal.classList.add('active');
                    modal.setAttribute('aria-hidden', 'false');
                    document.body.style.overflow = 'hidden';
                }

                function closeModal() {
                    modal.classList.remove('active');
                    modal.setAttribute('aria-hidden', 'true');
                    document.body.style.overflow = '';
                }

                openBtn.addEventListener('click', openModal);
                if (closeBtn) closeBtn.addEventListener('click', closeModal);
                if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

                modal.addEventListener('click', function (event) {
                    if (event.target === modal) {
                        closeModal();
                    }
                });

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && modal.classList.contains('active')) {
                        closeModal();
                    }
                });
            }

            bindConfirmModal(
                'modal-archive-commande',
                'btn-open-archive-commande',
                'btn-close-archive-commande',
                'btn-cancel-archive-commande'
            );
            bindConfirmModal(
                'modal-supprimer-commande',
                'btn-open-supprimer-commande',
                'btn-close-supprimer-commande',
                'btn-cancel-supprimer-commande'
            );
        })();
    </script>

    <?php require __DIR__ . '/../../includes/partials/platform_share_modal.php'; ?>
    <script src="/js/platform-share-modal.js<?php echo asset_version_query(); ?>"></script>
    <script src="/js/geo-nav-apps.js<?php echo asset_version_query(); ?>"></script>

    <?php include '../includes/footer.php'; ?>