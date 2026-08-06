<?php
/**
 * Page de liste des commandes utilisateur
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../includes/session_user.php';
session_start_persistent();
require_once __DIR__ . '/../includes/image_optimizer.php';

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    header('Location: connexion.php');
    exit;
}

require_once __DIR__ . '/../models/model_commandes.php';
require_once __DIR__ . '/../models/model_commandes_personnalisees.php';
require_once __DIR__ . '/../models/model_livreur_tracking.php';

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmer_livraison'])) {
    $commande_id = isset($_POST['commande_id']) ? (int) $_POST['commande_id'] : 0;

    if ($commande_id > 0) {
        $commande = get_commande_by_id($commande_id, $_SESSION['user_id']);
        if ($commande && $commande['statut'] === 'livraison_en_cours') {
            require_once __DIR__ . '/../models/model_commandes_admin.php';
            if (update_commande_statut($commande_id, 'paye')) {
                $success_message = 'Colis reçu confirmé avec succès !';
                header('Location: mes-commandes.php?onglet=recues&livraison_confirmee=1');
                exit;
            }
        }
        if (empty($success_message)) {
            $error_message = 'Une erreur est survenue lors de la confirmation de la réception du colis.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['annuler_commande'])) {
    $commande_id = isset($_POST['commande_id']) ? (int) $_POST['commande_id'] : 0;

    if ($commande_id > 0) {
        $commande = get_commande_by_id($commande_id, $_SESSION['user_id']);

        if ($commande && $commande['statut'] !== 'livree' && $commande['statut'] !== 'annulee') {
            if (update_commande_statut_user($commande_id, $_SESSION['user_id'], 'annulee')) {
                $success_message = 'Commande annulée avec succès !';
                header('Location: mes-commandes.php?commande_annulee=1');
                exit;
            } else {
                $error_message = 'Une erreur est survenue lors de l\'annulation de la commande.';
            }
        } else {
            $error_message = 'Cette commande ne peut pas être annulée.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recommander'])) {
    $commande_id = isset($_POST['commande_id']) ? (int) $_POST['commande_id'] : 0;

    if ($commande_id > 0) {
        $commande = get_commande_by_id($commande_id, $_SESSION['user_id']);

        if ($commande && $commande['statut'] === 'annulee') {
            require_once __DIR__ . '/../models/model_panier.php';
            $produits_commande = get_commande_produits($commande_id);

            if (!empty($produits_commande)) {
                require_once __DIR__ . '/../models/model_variantes.php';
                $added_count = 0;
                foreach ($produits_commande as $produit) {
                    require_once __DIR__ . '/../models/model_produits.php';
                    $produit_info = get_produit_by_id($produit['produit_id']);

                    if ($produit_info && $produit_info['statut'] === 'actif' && $produit_info['stock'] > 0) {
                        $quantite = min($produit['quantite'], $produit_info['stock']);
                        $variante_id = !empty($produit['variante_id']) ? (int) $produit['variante_id'] : null;
                        $variante_nom = !empty($produit['variante_nom']) ? trim($produit['variante_nom']) : null;
                        $variante_image = null;
                        if ($variante_id) {
                            $var = get_variante_by_id($variante_id);
                            $variante_image = $var && !empty($var['image']) ? $var['image'] : null;
                        }
                        $surcout_poids = isset($produit['surcout_poids']) ? (float) $produit['surcout_poids'] : 0;
                        $surcout_taille = isset($produit['surcout_taille']) ? (float) $produit['surcout_taille'] : 0;
                        $prix_unitaire = isset($produit['prix_unitaire']) ? (float) $produit['prix_unitaire'] : null;

                        if (add_to_panier(
                            $_SESSION['user_id'],
                            $produit['produit_id'],
                            $quantite,
                            $produit['couleur'] ?? null,
                            $produit['poids'] ?? null,
                            $produit['taille'] ?? null,
                            $variante_id,
                            $variante_nom,
                            $variante_image,
                            $surcout_poids,
                            $surcout_taille,
                            $prix_unitaire
                        )) {
                            $added_count++;
                        }
                    }
                }

                if ($added_count > 0) {
                    header('Location: /panier.php?recommande=1&count=' . $added_count);
                    exit;
                } else {
                    $error_message = 'Aucun produit disponible à recommander.';
                }
            } else {
                $error_message = 'Aucun produit trouvé dans cette commande.';
            }
        } else {
            $error_message = 'Cette commande ne peut pas être recommandée.';
        }
    }
}

if (isset($_GET['success']) && $_GET['success'] == '1' && isset($_GET['numero'])) {
    $success_message = 'Votre commande #' . htmlspecialchars($_GET['numero']) . ' a été créée avec succès !';
}

if (isset($_GET['livraison_confirmee']) && $_GET['livraison_confirmee'] == '1') {
    $success_message = 'Colis reçu confirmé avec succès !';
}

if (isset($_GET['commande_annulee']) && $_GET['commande_annulee'] == '1') {
    $success_message = 'Commande annulée avec succès !';
}

$commandes = get_commandes_by_user($_SESSION['user_id']);

$commandes_actives = array_values(array_filter($commandes, function ($commande) {
    return $commande['statut'] !== 'livree' && $commande['statut'] !== 'paye' && $commande['statut'] !== 'annulee';
}));

$commandes_recues = array_values(array_filter($commandes, function ($commande) {
    return in_array($commande['statut'], ['livree', 'paye'], true);
}));

$enrich_commandes = static function (array &$list) {
    foreach ($list as &$cmd_row) {
        $cmd_row['produits'] = get_commande_produits((int) $cmd_row['id']);
        $cmd_row['nb_articles'] = 0;
        foreach ($cmd_row['produits'] as $p) {
            $cmd_row['nb_articles'] += (int) ($p['quantite'] ?? 0);
        }
    }
    unset($cmd_row);
};
$enrich_commandes($commandes_actives);
$enrich_commandes($commandes_recues);

$commandes_perso = get_commandes_personnalisees_by_user($_SESSION['user_id']);
$commandes_perso_actives = array_values(array_filter($commandes_perso, function ($cp) {
    return !in_array($cp['statut'], ['terminee', 'refusee', 'annulee']);
}));
$commandes_perso_terminees = array_values(array_filter($commandes_perso, function ($cp) {
    return ($cp['statut'] ?? '') === 'terminee';
}));
$statuts_labels = get_statuts_commande_personnalisee();

$statut_labels_cmd = [
    'en_attente' => 'En attente',
    'confirmee' => 'Confirmée',
    'prise_en_charge' => 'Prise en charge',
    'en_preparation' => 'En préparation',
    'expediee' => 'Expédiée',
    'livraison_en_cours' => 'Livraison en cours',
    'livree' => 'Livrée',
    'paye' => 'Reçue',
    'annulee' => 'Annulée',
];

$total_actives = count($commandes_actives) + count($commandes_perso_actives);
$total_recues = count($commandes_recues) + count($commandes_perso_terminees);

$onglet = isset($_GET['onglet']) ? trim((string) $_GET['onglet']) : 'en_cours';
if (!in_array($onglet, ['en_cours', 'recues'], true)) {
    $onglet = 'en_cours';
}
if (isset($_GET['livraison_confirmee']) && $_GET['livraison_confirmee'] == '1') {
    $onglet = 'recues';
}

$enable_firebase_notifications = true;
$firebase_notify_type = 'user';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
    <?php include __DIR__ . '/../includes/pwa_meta.php'; ?>
    <title>Mes Commandes - Sugar Paper</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/user-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/user-mes-commandes.css<?php echo asset_version_query(); ?>">
</head>

<body class="user-page-mes-commandes">
    <?php include 'includes/user_nav.php'; ?>

    <div class="mc-page">
        <header class="mc-hero">
            <div class="mc-hero__identity">
                <div class="mc-hero__avatar" aria-hidden="true"><i class="fas fa-shopping-bag"></i></div>
                <div class="mc-hero__text">
                    <p class="mc-hero__eyebrow">Sugar Paper</p>
                    <h1 class="mc-hero__title">Mes commandes</h1>
                    <p class="mc-hero__subtitle">Suivez vos commandes en cours et vos commandes reçues.</p>
                </div>
            </div>
            <div class="mc-hero__actions">
                <a href="/index.php" class="mc-btn mc-btn--primary">
                    <i class="fas fa-store"></i>
                    <span>Continuer mes achats</span>
                </a>
                <a href="mon-compte.php" class="mc-btn mc-btn--ghost">
                    <i class="fas fa-home"></i>
                    <span>Mon compte</span>
                </a>
            </div>
        </header>

        <section class="mc-stats mc-stats--2" aria-label="Résumé des commandes">
            <a href="mes-commandes.php?onglet=en_cours" class="mc-stat mc-stat--commandes<?php echo $onglet === 'en_cours' ? ' is-active' : ''; ?>">
                <span class="mc-stat__icon"><i class="fas fa-shopping-bag"></i></span>
                <span class="mc-stat__value"><?php echo (int) $total_actives; ?></span>
                <span class="mc-stat__label">En cours</span>
            </a>
            <a href="mes-commandes.php?onglet=recues" class="mc-stat mc-stat--livrees<?php echo $onglet === 'recues' ? ' is-active' : ''; ?>">
                <span class="mc-stat__icon"><i class="fas fa-check-circle"></i></span>
                <span class="mc-stat__value"><?php echo (int) $total_recues; ?></span>
                <span class="mc-stat__label">Reçues</span>
            </a>
        </section>

        <section class="mc-section">
            <?php if ($success_message): ?>
                <div class="mc-alert mc-alert--success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo $success_message; ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="mc-alert mc-alert--error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            <?php endif; ?>

            <div class="mc-tabs" role="tablist" aria-label="Filtrer les commandes">
                <a href="mes-commandes.php?onglet=en_cours"
                    class="mc-tabs__btn<?php echo $onglet === 'en_cours' ? ' is-active' : ''; ?>"
                    role="tab"
                    aria-selected="<?php echo $onglet === 'en_cours' ? 'true' : 'false'; ?>">
                    <i class="fas fa-clock"></i>
                    <span>En cours</span>
                    <em><?php echo (int) $total_actives; ?></em>
                </a>
                <a href="mes-commandes.php?onglet=recues"
                    class="mc-tabs__btn<?php echo $onglet === 'recues' ? ' is-active' : ''; ?>"
                    role="tab"
                    aria-selected="<?php echo $onglet === 'recues' ? 'true' : 'false'; ?>">
                    <i class="fas fa-check-circle"></i>
                    <span>Reçues</span>
                    <em><?php echo (int) $total_recues; ?></em>
                </a>
            </div>

            <?php if ($onglet === 'en_cours'): ?>
                <div class="mc-section__head">
                    <h2><i class="fas fa-list"></i> Commandes en cours</h2>
                    <a href="commande-categorie.php" class="mc-section__link">
                        Par catégorie <i class="fas fa-arrow-right"></i>
                    </a>
                </div>

                <?php if (empty($commandes_actives) && empty($commandes_perso_actives)): ?>
                    <div class="mc-empty">
                        <i class="fas fa-box-open"></i>
                        <p>Aucune commande active pour le moment.</p>
                        <div class="mc-empty__actions">
                            <a href="/produits.php" class="mc-btn mc-btn--primary">
                                <i class="fas fa-shopping-cart"></i> Découvrir les produits
                            </a>
                            <a href="/commande-personnalisee.php" class="mc-btn mc-btn--ghost">
                                <i class="fas fa-palette"></i> Demande personnalisée
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="mc-orders">
                        <?php foreach ($commandes_actives as $commande): ?>
                            <?php
                            $statut = (string) ($commande['statut'] ?? 'en_attente');
                            $statut_label = $statut_labels_cmd[$statut] ?? ucfirst(str_replace('_', ' ', $statut));
                            $date_cmd = !empty($commande['date_commande'])
                                ? date('d/m/Y à H:i', strtotime($commande['date_commande']))
                                : '—';
                            $thumbs = array_slice($commande['produits'] ?? [], 0, 4);
                            $can_cancel = in_array($statut, ['en_attente', 'confirmee', 'prise_en_charge', 'en_preparation'], true);
                            ?>
                            <article class="mc-order">
                                <div class="mc-order__top">
                                    <div class="mc-order__meta">
                                        <span class="mc-order__numero">#<?php echo htmlspecialchars($commande['numero_commande']); ?></span>
                                        <span class="mc-order__date"><i class="far fa-calendar"></i> <?php echo htmlspecialchars($date_cmd); ?></span>
                                    </div>
                                    <span class="mc-order__statut statut-<?php echo htmlspecialchars($statut); ?>">
                                        <?php echo htmlspecialchars($statut_label); ?>
                                    </span>
                                </div>

                                <?php if (!empty($thumbs)): ?>
                                    <div class="mc-order__thumbs">
                                        <?php foreach ($thumbs as $thumb): ?>
                                            <img
                                                src="<?php echo htmlspecialchars(upload_image_url($thumb['image_afficher'] ?? $thumb['image_principale'] ?? '', 'xs')); ?>"
                                                alt="<?php echo htmlspecialchars($thumb['nom'] ?? 'Produit'); ?>"
                                                loading="lazy"
                                                onerror="this.src='/image/produit1.jpg'">
                                        <?php endforeach; ?>
                                        <?php if (count($commande['produits']) > 4): ?>
                                            <span class="mc-order__more">+<?php echo count($commande['produits']) - 4; ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="mc-order__details">
                                    <div class="mc-order__detail">
                                        <span>Articles</span>
                                        <strong><?php echo (int) $commande['nb_articles']; ?></strong>
                                    </div>
                                    <div class="mc-order__detail">
                                        <span>Montant</span>
                                        <strong><?php echo number_format((float) $commande['montant_total'], 0, ',', ' '); ?> FCFA</strong>
                                    </div>
                                    <div class="mc-order__detail">
                                        <span>Téléphone</span>
                                        <strong><?php echo htmlspecialchars($commande['telephone_livraison']); ?></strong>
                                    </div>
                                    <?php if (!empty($commande['date_livraison'])): ?>
                                        <div class="mc-order__detail">
                                            <span>Livraison</span>
                                            <strong><?php echo date('d/m/Y', strtotime($commande['date_livraison'])); ?></strong>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="mc-order__actions">
                                    <a href="commande-categorie.php?commande_id=<?php echo (int) $commande['id']; ?>"
                                        class="mc-btn mc-btn--sm mc-btn--primary">
                                        <i class="fas fa-eye"></i> Produits
                                    </a>

                                    <?php if (livreur_client_peut_suivre_gps($commande)): ?>
                                        <a href="suivi-commande.php?commande_id=<?php echo (int) $commande['id']; ?>"
                                            class="mc-btn mc-btn--sm mc-btn--track">
                                            <i class="fas fa-location-dot"></i> Suivre
                                        </a>
                                    <?php elseif (livreur_client_livraison_en_cours($commande)): ?>
                                        <span class="mc-btn mc-btn--sm mc-btn--pending" aria-disabled="true">
                                            <i class="fas fa-truck"></i> GPS bientôt
                                        </span>
                                    <?php endif; ?>

                                    <?php if ($statut === 'livraison_en_cours'): ?>
                                        <form method="POST" action="" class="mc-order__form">
                                            <input type="hidden" name="commande_id" value="<?php echo (int) $commande['id']; ?>">
                                            <button type="submit" name="confirmer_livraison" class="mc-btn mc-btn--sm mc-btn--success"
                                                onclick="return confirm('Avez-vous bien reçu votre colis ?');">
                                                <i class="fas fa-check-circle"></i> Colis reçu
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($can_cancel): ?>
                                        <form method="POST" action="" class="mc-order__form">
                                            <input type="hidden" name="commande_id" value="<?php echo (int) $commande['id']; ?>">
                                            <button type="submit" name="annuler_commande" class="mc-btn mc-btn--sm mc-btn--danger"
                                                onclick="return confirm('Êtes-vous sûr de vouloir annuler cette commande ? Cette action est irréversible.');">
                                                <i class="fas fa-times-circle"></i> Annuler
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>

                        <?php foreach ($commandes_perso_actives as $cp): ?>
                            <?php
                            $desc = (string) ($cp['description'] ?? '');
                            $desc_short = mb_strlen($desc) > 110 ? mb_substr($desc, 0, 110) . '…' : $desc;
                            ?>
                            <article class="mc-order mc-order--perso">
                                <div class="mc-order__top">
                                    <div class="mc-order__meta">
                                        <span class="mc-order__badge"><i class="fas fa-palette"></i> Personnalisée</span>
                                        <span class="mc-order__numero">Demande #<?php echo (int) $cp['id']; ?></span>
                                        <span class="mc-order__date">
                                            <i class="far fa-calendar"></i>
                                            <?php echo date('d/m/Y à H:i', strtotime($cp['date_creation'])); ?>
                                        </span>
                                    </div>
                                    <span class="mc-order__statut statut-<?php echo htmlspecialchars($cp['statut']); ?>">
                                        <?php echo htmlspecialchars($statuts_labels[$cp['statut']] ?? $cp['statut']); ?>
                                    </span>
                                </div>

                                <div class="mc-order__perso-body">
                                    <p><?php echo nl2br(htmlspecialchars($desc_short)); ?></p>
                                    <?php if (!empty($cp['type_produit'])): ?>
                                        <span class="mc-order__type"><?php echo htmlspecialchars($cp['type_produit']); ?></span>
                                    <?php endif; ?>
                                </div>

                                <div class="mc-order__actions">
                                    <a href="commande-personnalisee-details.php?id=<?php echo (int) $cp['id']; ?>"
                                        class="mc-btn mc-btn--sm mc-btn--primary">
                                        <i class="fas fa-eye"></i> Détails
                                    </a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="mc-section__head">
                    <h2><i class="fas fa-box"></i> Commandes reçues</h2>
                    <a href="/produits.php" class="mc-section__link">
                        Commander à nouveau <i class="fas fa-arrow-right"></i>
                    </a>
                </div>

                <?php if (empty($commandes_recues) && empty($commandes_perso_terminees)): ?>
                    <div class="mc-empty">
                        <i class="fas fa-box-open"></i>
                        <p>Aucune commande livrée pour le moment.</p>
                        <div class="mc-empty__actions">
                            <a href="mes-commandes.php?onglet=en_cours" class="mc-btn mc-btn--primary">
                                <i class="fas fa-shopping-bag"></i> Voir mes commandes
                            </a>
                            <a href="/produits.php" class="mc-btn mc-btn--ghost">
                                <i class="fas fa-store"></i> Découvrir les produits
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="mc-orders">
                        <?php foreach ($commandes_recues as $commande): ?>
                            <?php
                            $date_cmd = !empty($commande['date_commande'])
                                ? date('d/m/Y à H:i', strtotime($commande['date_commande']))
                                : '—';
                            $thumbs = array_slice($commande['produits'] ?? [], 0, 4);
                            $adresse = trim((string) ($commande['adresse_livraison'] ?? ''));
                            $adresse_short = mb_strlen($adresse) > 42 ? mb_substr($adresse, 0, 42) . '…' : $adresse;
                            ?>
                            <article class="mc-order mc-order--livree">
                                <div class="mc-order__top">
                                    <div class="mc-order__meta">
                                        <span class="mc-order__numero">#<?php echo htmlspecialchars($commande['numero_commande']); ?></span>
                                        <span class="mc-order__date"><i class="far fa-calendar"></i> <?php echo htmlspecialchars($date_cmd); ?></span>
                                    </div>
                                    <span class="mc-order__statut statut-livree">
                                        <i class="fas fa-check-circle"></i> Reçu
                                    </span>
                                </div>

                                <?php if (!empty($thumbs)): ?>
                                    <div class="mc-order__thumbs">
                                        <?php foreach ($thumbs as $thumb): ?>
                                            <img
                                                src="<?php echo htmlspecialchars(upload_image_url($thumb['image_afficher'] ?? $thumb['image_principale'] ?? '', 'xs')); ?>"
                                                alt="<?php echo htmlspecialchars($thumb['nom'] ?? 'Produit'); ?>"
                                                loading="lazy"
                                                onerror="this.src='/image/produit1.jpg'">
                                        <?php endforeach; ?>
                                        <?php if (count($commande['produits']) > 4): ?>
                                            <span class="mc-order__more">+<?php echo count($commande['produits']) - 4; ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="mc-order__details">
                                    <div class="mc-order__detail">
                                        <span>Articles</span>
                                        <strong><?php echo (int) $commande['nb_articles']; ?></strong>
                                    </div>
                                    <div class="mc-order__detail">
                                        <span>Montant</span>
                                        <strong><?php echo number_format((float) $commande['montant_total'], 0, ',', ' '); ?> FCFA</strong>
                                    </div>
                                    <div class="mc-order__detail">
                                        <span>Téléphone</span>
                                        <strong><?php echo htmlspecialchars($commande['telephone_livraison']); ?></strong>
                                    </div>
                                    <?php if ($adresse_short !== ''): ?>
                                        <div class="mc-order__detail">
                                            <span>Adresse</span>
                                            <strong><?php echo htmlspecialchars($adresse_short); ?></strong>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($commande['date_livraison'])): ?>
                                        <div class="mc-order__detail">
                                            <span>Livré le</span>
                                            <strong><?php echo date('d/m/Y', strtotime($commande['date_livraison'])); ?></strong>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="mc-order__actions">
                                    <a href="commande-categorie.php?commande_id=<?php echo (int) $commande['id']; ?>"
                                        class="mc-btn mc-btn--sm mc-btn--primary">
                                        <i class="fas fa-eye"></i> Voir les produits
                                    </a>
                                </div>
                            </article>
                        <?php endforeach; ?>

                        <?php foreach ($commandes_perso_terminees as $cp): ?>
                            <?php
                            $desc = (string) ($cp['description'] ?? '');
                            $desc_short = mb_strlen($desc) > 110 ? mb_substr($desc, 0, 110) . '…' : $desc;
                            ?>
                            <article class="mc-order mc-order--perso">
                                <div class="mc-order__top">
                                    <div class="mc-order__meta">
                                        <span class="mc-order__badge"><i class="fas fa-palette"></i> Personnalisée</span>
                                        <span class="mc-order__numero">Demande #<?php echo (int) $cp['id']; ?></span>
                                        <span class="mc-order__date">
                                            <i class="far fa-calendar"></i>
                                            <?php echo date('d/m/Y à H:i', strtotime($cp['date_creation'])); ?>
                                        </span>
                                    </div>
                                    <span class="mc-order__statut statut-terminee">
                                        <i class="fas fa-check-circle"></i> Terminée
                                    </span>
                                </div>

                                <div class="mc-order__perso-body">
                                    <p><?php echo nl2br(htmlspecialchars($desc_short)); ?></p>
                                    <?php if (!empty($cp['type_produit'])): ?>
                                        <span class="mc-order__type"><?php echo htmlspecialchars($cp['type_produit']); ?></span>
                                    <?php endif; ?>
                                </div>

                                <div class="mc-order__actions">
                                    <a href="commande-personnalisee-details.php?id=<?php echo (int) $cp['id']; ?>"
                                        class="mc-btn mc-btn--sm mc-btn--primary">
                                        <i class="fas fa-eye"></i> Détails
                                    </a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </div>

    <?php include 'includes/user_footer.php'; ?>
