<?php
/**
 * Page de détails d'une commande (Admin)
 * Programmation procédurale uniquement
 */

session_start();

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
$commande = get_commande_by_id($commande_id);
$produits = get_produits_by_commande($commande_id);

if (!$commande) {
    header('Location: index.php');
    exit;
}

// Vérifier si la commande est annulée ou livrée (pas de modification possible)
$is_annulee = $commande['statut'] === 'annulee';
$is_livree = $commande['statut'] === 'livree';

// Traiter les actions de statut (uniquement si la commande n'est pas annulée)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_annulee) {
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
        if (in_array($nouveau_statut, ['en_attente', 'confirmee', 'prise_en_charge', 'en_preparation', 'livraison_en_cours', 'expediee', 'livree', 'annulee'])) {
            if (update_commande_statut($commande_id, $nouveau_statut)) {
                $statut_mis_a_jour = $nouveau_statut;
            }
        }
    }

    if ($statut_mis_a_jour !== null) {
        require_once __DIR__ . '/../../services/send_commande_notification.php';
        send_commande_status_notification(
            (int) $commande['user_id'],
            $commande['numero_commande'],
            $statut_mis_a_jour,
            $commande['user_email'] ?? ''
        );
        $_SESSION['success_message'] = 'Statut de la commande mis à jour avec succès. Une notification et un email ont été envoyés au client.';
        header('Location: details.php?id=' . $commande_id);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails Commande #<?php echo htmlspecialchars($commande['numero_commande']); ?> - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="content-header">
        <h1>
            <i class="fas fa-shopping-bag"></i> Commande #<?php echo htmlspecialchars($commande['numero_commande']); ?>
        </h1>
        <div class="header-actions">
            <a href="index.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="message success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo htmlspecialchars($_SESSION['success_message']);
            unset($_SESSION['success_message']); ?></span>
        </div>
    <?php endif; ?>

    <!-- Détails de la commande -->
    <div class="commande-details-grid">
        <div class="detail-box">
            <h3><i class="fas fa-user"></i> Informations Client</h3>
            <div class="detail-item">
                <label>Nom complet</label>
                <div class="value">
                    <?php echo htmlspecialchars($commande['user_prenom'] . ' ' . $commande['user_nom']); ?>
                </div>
            </div>
            <div class="detail-item">
                <label>Email</label>
                <div class="value"><?php echo htmlspecialchars($commande['user_email']); ?></div>
            </div>
            <div class="detail-item">
                <label>Téléphone</label>
                <div class="value"><?php echo htmlspecialchars($commande['user_telephone']); ?></div>
            </div>
        </div>

        <div class="detail-box">
            <h3><i class="fas fa-map-marker-alt"></i> Livraison</h3>
            <div class="detail-item">
                <label>Adresse</label>
                <div class="value"><?php echo nl2br(htmlspecialchars($commande['adresse_livraison'])); ?></div>
            </div>
            <div class="detail-item">
                <label>Téléphone livraison</label>
                <div class="value"><?php echo htmlspecialchars($commande['telephone_livraison']); ?></div>
            </div>
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
                <div class="produit-item">
                    <img src="/upload/<?php echo htmlspecialchars($produit['image_principale']); ?>"
                        alt="<?php echo htmlspecialchars($produit['produit_nom']); ?>"
                        onerror="this.src='/image/produit1.jpg'">
                    <div class="produit-info">
                        <h4><?php echo htmlspecialchars($produit['produit_nom']); ?></h4>
                        <p>Quantité: <?php echo $produit['quantite']; ?> | Prix unitaire:
                            <?php echo number_format($produit['prix_unitaire'], 0, ',', ' '); ?> FCFA
                        </p>
                        <?php if (!empty($produit['couleur']) || !empty($produit['poids']) || !empty($produit['taille'])): ?>
                        <div class="produit-options-detail">
                            <?php if (!empty($produit['couleur'])): ?>
                            <?php
                            $hex = trim($produit['couleur']);
                            $is_hex = preg_match('/^#[0-9A-Fa-f]{6}$/', $hex);
                            ?>
                            <div class="option-detail option-couleur">
                                <span class="option-label">Couleur:</span>
                                <?php if ($is_hex): ?>
                                <span class="couleur-swatch-large" style="background-color:<?php echo htmlspecialchars($hex); ?>;" title="<?php echo htmlspecialchars($hex); ?>"></span>
                                <span class="option-value"><?php echo htmlspecialchars($hex); ?></span>
                                <?php else: ?>
                                <span class="option-value"><?php echo htmlspecialchars($produit['couleur']); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($produit['poids'])): ?>
                            <div class="option-detail option-poids">
                                <span class="option-label">Poids:</span>
                                <span class="option-value"><?php echo htmlspecialchars($produit['poids']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($produit['taille'])): ?>
                            <div class="option-detail option-taille">
                                <span class="option-label">Taille:</span>
                                <span class="option-value"><?php echo htmlspecialchars($produit['taille']); ?></span>
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

        <?php if ($is_annulee): ?>
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
                            <p class="sub">Vous pouvez changer le statut manuellement ci-dessous pour la marquer comme
                                "Expédiée" ou "Livrée"</p>
                        </div>
                    <?php elseif ($commande['statut'] == 'expediee'): ?>
                        <div class="alert-livree">
                            <p><i class="fas fa-check-circle"></i> Commande expédiée</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Formulaire de changement manuel de statut (masqué si livrée) -->
                <div class="actions-divider">
                    <h3>Changer le statut manuellement</h3>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="statut">Nouveau statut</label>
                            <select id="statut" name="statut" required>
                                <option value="en_attente" <?php echo $commande['statut'] == 'en_attente' ? 'selected' : ''; ?>>En Attente</option>
                                <option value="confirmee" <?php echo $commande['statut'] == 'confirmee' ? 'selected' : ''; ?>>
                                    Confirmée</option>
                                <option value="prise_en_charge" <?php echo $commande['statut'] == 'prise_en_charge' ? 'selected' : ''; ?>>Prise en
                                    charge</option>
                                <option value="en_preparation" <?php echo $commande['statut'] == 'en_preparation' ? 'selected' : ''; ?>>En Préparation
                                </option>
                                <option value="livraison_en_cours" <?php echo $commande['statut'] == 'livraison_en_cours' ? 'selected' : ''; ?>>Livraison
                                    en cours</option>
                                <option value="expediee" <?php echo $commande['statut'] == 'expediee' ? 'selected' : ''; ?>>
                                    Expédiée</option>
                                <option value="livree" <?php echo $commande['statut'] == 'livree' ? 'selected' : ''; ?>>
                                    Livrée</option>
                                <option value="annulee" <?php echo $commande['statut'] == 'annulee' ? 'selected' : ''; ?>>
                                    Annulée</option>
                            </select>
                        </div>
                        <?php if ($commande['notes']): ?>
                            <div class="form-group">
                                <label>Notes</label>
                                <div class="notes-box">
                                    <?php echo nl2br(htmlspecialchars($commande['notes'])); ?>
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

    <?php include '../includes/footer.php'; ?>