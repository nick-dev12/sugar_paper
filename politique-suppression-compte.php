<?php
require_once __DIR__ . '/includes/session_user.php';
session_start_persistent();

require_once __DIR__ . '/includes/site_url.php';
require_once __DIR__ . '/includes/asset_version.php';

$base = get_site_base_url();
$seo_title = 'Politique de suppression de compte — Sugar Paper';
$seo_description = 'Procédure de suppression de compte Sugar Paper : droit à l\'effacement, données supprimées ou conservées, formulaire sécurisé pour clients connectés.';
$seo_canonical = $base . '/politique-suppression-compte.php';

$privacy_email = 'sugarpaper26@gmail.com';
$last_update = '14/07/2026';

$is_logged_in = !empty($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0;
$login_redirect = '/user/connexion.php?redirect=' . rawurlencode('/user/supprimer-compte.php');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/includes/pwa_meta.php'; ?>
    <?php include __DIR__ . '/includes/seo_meta.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/a_style.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/legal-page.css<?php echo asset_version_query(); ?>">
</head>
<body>
    <?php include __DIR__ . '/nav_bar.php'; ?>

    <article class="legal-page" id="top">
        <h1><i class="fas fa-user-slash" aria-hidden="true"></i> Politique de suppression de compte</h1>
        <p class="legal-updated"><strong>Dernière mise à jour :</strong> <?php echo htmlspecialchars($last_update, ENT_QUOTES, 'UTF-8'); ?></p>

        <p>
            Cette page décrit votre <strong>droit de demander la suppression de votre compte client Sugar Paper</strong>,
            les données effacées ou conservées, ainsi que la procédure en ligne réservée aux utilisateurs <strong>connectés</strong>.
            Elle complète notre <a href="/politique-confidentialite.php">Politique de confidentialité</a>.
        </p>

        <nav class="legal-toc" aria-label="Sommaire">
            <strong>Sommaire</strong>
            <ol>
                <li><a href="#suppr-1">Champ d'application</a></li>
                <li><a href="#suppr-2">Comment supprimer votre compte</a></li>
                <li><a href="#suppr-3">Conséquences de la suppression</a></li>
                <li><a href="#suppr-4">Données supprimées</a></li>
                <li><a href="#suppr-5">Données conservées</a></li>
                <li><a href="#suppr-6">Commandes en cours</a></li>
                <li><a href="#suppr-7">Demande par e-mail</a></li>
                <li><a href="#suppr-action">Supprimer mon compte</a></li>
            </ol>
        </nav>

        <h2 id="suppr-1">1. Champ d'application</h2>
        <p>
            Cette politique concerne les <strong>comptes clients</strong> créés sur le site Sugar Paper ou via l'application mobile officielle
            (identifiant iOS / Android&nbsp;: <strong>com.sugarpaper.app</strong>).
            Elle ne s'applique pas aux comptes administrateurs internes, gérés séparément par l'entreprise.
        </p>

        <h2 id="suppr-2">2. Comment supprimer votre compte</h2>
        <p>Vous disposez de deux moyens pour demander la suppression&nbsp;:</p>
        <ol>
            <li>
                <strong>Formulaire en ligne (recommandé)</strong>&nbsp;: connectez-vous à votre compte, puis utilisez le bouton
                «&nbsp;Supprimer mon compte&nbsp;» en bas de cette page. Vous devrez confirmer votre mot de passe.
            </li>
            <li>
                <strong>Demande par e-mail</strong>&nbsp;: écrivez à
                <a href="mailto:<?php echo htmlspecialchars($privacy_email, ENT_QUOTES, 'UTF-8'); ?>?subject=<?php echo rawurlencode('Suppression de compte — Sugar Paper'); ?>"><?php echo htmlspecialchars($privacy_email, ENT_QUOTES, 'UTF-8'); ?></a>
                depuis l'adresse liée à votre compte, avec l'objet «&nbsp;Suppression de compte&nbsp;».
            </li>
        </ol>
        <p>
            Pour des raisons de sécurité, <strong>la suppression via le formulaire en ligne nécessite une connexion préalable</strong>.
            Si vous n'êtes pas connecté, vous serez invité à vous identifier avant toute action de suppression.
        </p>

        <h2 id="suppr-3">3. Conséquences de la suppression</h2>
        <ul>
            <li>Vous ne pourrez plus vous connecter avec vos identifiants actuels ;</li>
            <li>Votre profil client (nom, prénom, e-mail, téléphone) sera effacé ;</li>
            <li>Votre panier et vos préférences de compte seront supprimés ;</li>
            <li>Les jetons de notification push (FCM) liés à votre compte seront invalidés ;</li>
            <li>L'historique de commandes peut être conservé sous forme anonymisée lorsque la loi l'exige (voir section&nbsp;5).</li>
        </ul>

        <h2 id="suppr-4">4. Données supprimées</h2>
        <p>Sont en principe <strong>supprimés</strong>&nbsp;:</p>
        <ul>
            <li>Identifiants de connexion (e-mail, téléphone, mot de passe haché) ;</li>
            <li>Informations du profil non requises légalement ;</li>
            <li>Contenu du panier et favoris ;</li>
            <li>Historique de navigation produits enregistré côté serveur pour le compte ;</li>
            <li>Jetons FCM et préférences de notification liés au compte.</li>
        </ul>

        <h2 id="suppr-5">5. Données conservées</h2>
        <div class="legal-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Type de données</th>
                        <th>Raison</th>
                        <th>Durée indicative</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Commandes, factures, paiements</td>
                        <td>Obligations comptables, fiscales et commerciales</td>
                        <td>Durée légale applicable au Sénégal</td>
                    </tr>
                    <tr>
                        <td>Preuves de litige ou réclamation SAV</td>
                        <td>Défense de nos droits</td>
                        <td>Durée du litige + prescription</td>
                    </tr>
                    <tr>
                        <td>Journaux de sécurité (IP, horodatage)</td>
                        <td>Prévention de la fraude</td>
                        <td>Plusieurs mois selon politique interne</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <h2 id="suppr-6">6. Commandes en cours</h2>
        <p>
            Si des commandes sont <strong>en cours de traitement ou de livraison</strong>, la suppression immédiate du compte peut être refusée
            jusqu'à leur finalisation. Vous pouvez nous contacter pour une anonymisation partielle ou attendre la clôture des commandes concernées.
        </p>

        <h2 id="suppr-7">7. Demande par e-mail et délais</h2>
        <p>
            Nous accuserons réception de votre demande dans un délai raisonnable et nous efforçons de traiter les suppressions sous <strong>30 jours</strong>,
            sauf complexité ou vérification d'identité supplémentaire. Pour exercer vos autres droits (accès, rectification, opposition),
            consultez la <a href="/politique-confidentialite.php#priv-13">section&nbsp;13 de la Politique de confidentialité</a>.
        </p>

        <div class="legal-danger-zone" id="suppr-action">
            <h2><i class="fas fa-exclamation-triangle" aria-hidden="true"></i> Supprimer mon compte</h2>

            <?php if ($is_logged_in): ?>
                <p>
                    Vous êtes connecté en tant que
                    <strong><?php echo htmlspecialchars(trim(($_SESSION['user_prenom'] ?? '') . ' ' . ($_SESSION['user_nom'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></strong>.
                    La suppression est <strong>définitive</strong> et ne peut pas être annulée.
                </p>
                <p>
                    <a href="/user/supprimer-compte.php" class="legal-btn-delete">
                        <i class="fas fa-user-slash" aria-hidden="true"></i> Supprimer mon compte
                    </a>
                </p>
            <?php else: ?>
                <div class="legal-alert-login" role="alert">
                    <p><strong>Connexion requise</strong></p>
                    <p>
                        Pour supprimer votre compte, vous devez d'abord vous connecter à votre espace client.
                        Cette mesure protège votre compte contre toute suppression non autorisée.
                    </p>
                    <p>
                        <a href="<?php echo htmlspecialchars($login_redirect, ENT_QUOTES, 'UTF-8'); ?>" class="legal-btn-login">
                            <i class="fas fa-sign-in-alt" aria-hidden="true"></i> Se connecter pour supprimer mon compte
                        </a>
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <div class="legal-cross">
            <strong>Documents associés :</strong>
            <a href="/politique-confidentialite.php">Politique de confidentialité</a>
            ·
            <a href="/conditions-utilisation.php">Conditions générales d'utilisation</a>
        </div>

        <a href="javascript:history.back()" class="back-link">
            <i class="fas fa-arrow-left" aria-hidden="true"></i> Retour
        </a>
    </article>

    <?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
