<?php
require_once __DIR__ . '/includes/session_user.php';
session_start_persistent();

require_once __DIR__ . '/includes/site_url.php';
require_once __DIR__ . '/includes/site_brand.php';

$base = get_site_base_url();
$contact_email = SITE_BRAND_CONTACT_EMAIL;
$seo_title = 'Politique de suppression de compte - COLObanes';
$seo_description = 'Procédure de demande de suppression de compte COLObanes : étapes par e-mail, données supprimées ou conservées, durées de conservation.';
$seo_canonical = $base . '/politique-suppression-compte.php';
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
    <style>
        .legal-page {
            max-width: 920px;
            margin: 40px auto;
            padding: 40px 24px 80px;
            background: var(--blanc);
            border-radius: 10px;
            box-shadow: var(--ombre-douce);
        }
        .legal-page h1 {
            color: var(--titres);
            font-size: 32px;
            margin-bottom: 16px;
            text-align: center;
            border-bottom: 3px solid var(--couleur-dominante);
            padding-bottom: 15px;
        }
        .legal-page .legal-updated {
            text-align: center;
            color: var(--gris-moyen);
            font-size: 14px;
            margin-bottom: 28px;
        }
        .legal-page h2 {
            color: var(--titres);
            font-size: 1.25rem;
            margin-top: 36px;
            margin-bottom: 14px;
            padding-top: 8px;
            border-top: 1px solid var(--noir-pale);
        }
        .legal-page h2:first-of-type {
            border-top: none;
            padding-top: 0;
        }
        .legal-page h3 {
            color: var(--gris-fonce);
            font-size: 1.05rem;
            margin-top: 22px;
            margin-bottom: 10px;
        }
        .legal-page p, .legal-page li {
            color: var(--texte-fonce);
            font-size: 15px;
            line-height: 1.85;
            margin-bottom: 12px;
        }
        .legal-page p {
            text-align: justify;
        }
        .legal-page ul, .legal-page ol {
            margin: 12px 0 18px;
            padding-left: 28px;
        }
        .legal-page li {
            margin-bottom: 8px;
        }
        .legal-page a {
            color: var(--couleur-dominante);
            text-decoration: none;
        }
        .legal-page a:hover {
            color: var(--orange);
            text-decoration: underline;
        }
        .legal-procedure-box {
            border: 2px solid var(--couleur-dominante);
            border-radius: 14px;
            padding: 24px 26px;
            margin: 28px 0 32px;
            background: linear-gradient(165deg, var(--bleu-pale), #fff 55%);
            box-shadow: var(--ombre-douce);
        }
        .legal-procedure-box > h2 {
            margin-top: 0;
            padding-top: 0;
            border-top: none;
            color: var(--couleur-dominante);
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .legal-procedure-box > h2 i {
            font-size: 1.15rem;
        }
        .legal-steps {
            counter-reset: legalstep;
            list-style: none;
            padding-left: 0;
            margin: 18px 0 0;
        }
        .legal-steps > li {
            position: relative;
            padding: 14px 18px 14px 58px;
            margin-bottom: 14px;
            min-height: 28px;
            background: rgba(255, 255, 255, 0.92);
            border-radius: 10px;
            border: 1px solid var(--glass-border);
        }
        .legal-steps > li::before {
            counter-increment: legalstep;
            content: counter(legalstep);
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: var(--couleur-dominante);
            color: #fff;
            font-weight: 700;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .legal-mail-highlight {
            display: inline-block;
            margin-top: 6px;
            padding: 10px 16px;
            background: var(--fond-secondaire);
            border-radius: 8px;
            font-weight: 600;
            border: 1px solid var(--border-input);
        }
        .legal-two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 24px 0;
        }
        @media (max-width: 720px) {
            .legal-two-col {
                grid-template-columns: 1fr;
            }
        }
        .legal-data-section {
            border-radius: 12px;
            padding: 20px 22px;
            border: 1px solid var(--glass-border);
        }
        .legal-data-section h3 {
            margin-top: 0;
            font-size: 1.08rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .legal-data-section--delete {
            background: rgba(53, 100, 166, 0.1);
            border-left: 5px solid var(--couleur-dominante);
        }
        .legal-data-section--delete h3 {
            color: var(--couleur-dominante);
        }
        .legal-data-section--keep {
            background: rgba(255, 107, 53, 0.1);
            border-left: 5px solid var(--orange, #FF6B35);
        }
        .legal-data-section--keep h3 {
            color: var(--orange-fonce, #e85a2a);
        }
        .legal-retention-box {
            margin: 24px 0;
            padding: 20px 22px;
            border-radius: 12px;
            background: var(--fond-secondaire);
            border: 1px solid var(--glass-border);
            border-left: 5px solid var(--gris-moyen);
        }
        .legal-retention-box h3 {
            margin-top: 0;
        }
        .legal-note {
            font-size: 13px;
            color: var(--gris-moyen);
            font-style: italic;
            margin-top: 24px;
            margin-bottom: 16px;
            padding: 14px;
            background: var(--bleu-pale);
            border-radius: 8px;
            border-left: 4px solid var(--couleur-dominante);
            text-align: left;
        }
        .legal-cross {
            margin-top: 28px;
            padding: 16px;
            border-radius: 8px;
            background: var(--fond-secondaire);
            font-size: 14px;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 30px;
            padding: 12px 24px;
            background: var(--couleur-dominante);
            color: var(--texte-clair) !important;
            text-decoration: none !important;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .back-link:hover {
            background: var(--orange);
            color: var(--texte-clair) !important;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/nav_bar.php'; ?>

    <div class="legal-page">
        <h1><i class="fas fa-user-slash" aria-hidden="true"></i> Politique de suppression de compte</h1>
        <p class="legal-updated"><strong>Dernière mise à jour :</strong> <?php echo htmlspecialchars(date('d/m/Y'), ENT_QUOTES, 'UTF-8'); ?></p>

        <p>
            La présente page décrit la procédure à suivre pour <strong>demander la suppression de votre compte utilisateur COLObanes</strong> (compte client),
            ainsi que les catégories de données concernées par la suppression, celles que nous pouvons être amenés à <strong>conserver</strong> pour des motifs légaux ou contractuels,
            et les <strong>délais de conservation</strong> qui s’y appliquent. Elle complète notre
            <a href="/politique-confidentialite.php">Politique de confidentialité</a>.
        </p>

        <div class="legal-procedure-box">
            <h2 id="procedure"><i class="fas fa-list-ol" aria-hidden="true"></i> Procédure à suivre (demande par courriel)</h2>
            <p>
                <strong>La demande de suppression de compte ne peut pas être faite uniquement depuis l’application ou le site sans contact :</strong>
                vous devez l’envoyer explicitement à notre adresse de contact ci-dessous. Nous traitons les demandes ainsi pour vérifier votre identité et limiter les abus.
            </p>
            <p class="legal-mail-highlight">
                <i class="fas fa-envelope" aria-hidden="true"></i>
                Écrivez à&nbsp;:
                <a href="mailto:<?php echo htmlspecialchars($contact_email, ENT_QUOTES, 'UTF-8'); ?>?subject=<?php echo rawurlencode('Demande de suppression de compte COLObanes'); ?>"><?php echo htmlspecialchars($contact_email, ENT_QUOTES, 'UTF-8'); ?></a>
            </p>
            <ol class="legal-steps">
                <li>
                    Utilisez une <strong>messagerie à laquelle vous avez accès</strong>. Idéalement, envoyez la demande depuis l’<strong>adresse e-mail associée à votre compte</strong> COLObanes, si vous vous êtes inscrit avec un e-mail.
                    Si votre compte repose uniquement sur un <strong>numéro de téléphone</strong>, indiquez ce numéro au format international utilisé sur la Plateforme.
                </li>
                <li>
                    Indiquez clairement dans l’objet ou le corps du message qu’il s’agit d’une <strong>« Demande de suppression de compte »</strong>.
                </li>
                <li>
                    Précisez vos <strong>nom et prénom</strong> tels qu’enregistrés sur le compte, et les <strong>identifiants de connexion</strong> connus (e-mail et/ou téléphone).
                </li>
                <li>
                    Nous pouvons vous demander une <strong>preuve d’identité raisonnable</strong> (par exemple une copie de pièce d’identité avec masquage des données non nécessaires) afin d’éviter qu’un tiers ne supprime votre compte à votre place.
                </li>
                <li>
                    Après vérification, nous procédons à la suppression du compte et aux traitements associés décrits ci-dessous. Nous vous confirmons habituellement par retour de courriel ; en l’absence de réponse sous <strong>30 jours</strong>,
                    vous pouvez nous relancer à la même adresse (délai pouvant être prolongé en cas de demande complexe, conformément à notre
                    <a href="/politique-confidentialite.php#priv-13">Politique de confidentialité</a>).
                </li>
            </ol>
        </div>

        <h2 id="donnees-traitement">Données supprimées et données conservées</h2>
        <p>
            La suppression du compte entraîne la <strong>fermeture de l’accès</strong> à celui-ci. Les effets sur les données personnelles sont résumés dans les deux blocs suivants.
            Les détails peuvent varier selon les fonctionnalités effectivement utilisées (commandes passées, messages, etc.).
        </p>

        <div class="legal-two-col">
            <div class="legal-data-section legal-data-section--delete">
                <h3><i class="fas fa-trash-alt" aria-hidden="true"></i> Données en principe supprimées ou anonymisées</h3>
                <ul>
                    <li>Profil du compte : <strong>nom, prénom</strong> lorsque stockés pour le compte client ;</li>
                    <li><strong>Adresse e-mail</strong> et <strong>numéro de téléphone</strong> liés au compte, dès lors qu’ils ne sont plus nécessaires aux finalités autorisées de conservation ;</li>
                    <li><strong>Mot de passe</strong> (déjà stocké sous forme dérivée / hachée) — suppression de l’enregistrement du compte ;</li>
                    <li>Contenus strictement liés au compte et sans obligation légale de conservation (par exemple <strong>panier</strong>, préférences de compte non nécessaires aux commandes passées) ;</li>
                    <li>Autres données traitées uniquement sur la base du <strong>consentement</strong> ou de l’<strong>exécution du contrat « compte »</strong>, lorsque leur conservation n’est plus requise.</li>
                </ul>
            </div>
            <div class="legal-data-section legal-data-section--keep">
                <h3><i class="fas fa-archive" aria-hidden="true"></i> Données susceptibles d’être conservées</h3>
                <ul>
                    <li>Éléments nécessaires à la <strong>preuve des commandes</strong>, de la <strong>facturation</strong>, de la <strong>comptabilité</strong> et des <strong>obligations fiscales</strong> : montants, références de commande, livraison, traces de paiement selon les outils utilisés ;</li>
                    <li>Informations devant rester disponibles pour la <strong>gestion des litiges</strong>, réclamations, garanties ou <strong>obligation légale</strong> (notamment tant qu’un litige n’est pas prescrit) ;</li>
                    <li>Journaux techniques <strong>anonymisés</strong> ou <strong>pseudonymisés</strong> à des fins de sécurité et de stabilité du service ;</li>
                    <li>Données dont la conservation a été <strong>imposée par une autorité</strong> ou une décision judiciaire.</li>
                </ul>
            </div>
        </div>

        <div class="legal-retention-box">
            <h3><i class="fas fa-clock" aria-hidden="true"></i> Durées de conservation supplémentaires</h3>
            <p>
                Lorsque nous conservons des données après suppression du compte, ce n’est <strong>pas pour réactiver le profil commercial du compte</strong>, mais pour respecter nos obligations.
            </p>
            <ul>
                <li>
                    <strong>Données comptables et facturation</strong> : conservation pendant les délais imposés par la loi applicable (souvent de <strong>plusieurs années</strong> après la dernière opération concernée, selon la réglementation sénégalaise et les pratiques comptables en vigueur) ;
                </li>
                <li>
                    <strong>Prouver l’exécution d’un contrat de vente</strong> (commande marketplace) : conservation au moins pendant le temps nécessaire aux garanties, litiges et réclamations clients, et au-delà si la loi l’exige ;
                </li>
                <li>
                    <strong>Journaux de sécurité</strong> : durées courtes à moyennes, adaptées aux besoins de détection d’incidents et aux obligations légales éventuelles ;
                </li>
                <li>
                    Lorsque la loi le permet et qu’il n’y a pas d’obligation de conserver des données nominatives, nous privilégions l’<strong>anonymisation</strong> ou l’<strong>agrégation</strong> (statistiques sans identification des personnes).
                </li>
            </ul>
        </div>

        <p class="legal-note">
            Cette politique reflète une pratique standard de marketplace. Elle ne remplace pas un conseil juridique personnalisé. Pour le détail des traitements et de vos droits (accès, rectification, opposition, etc.), voir la
            <a href="/politique-confidentialite.php">Politique de confidentialité</a>.
        </p>

        <div class="legal-cross">
            <strong>Documents associés :</strong>
            <a href="/politique-confidentialite.php">Politique de confidentialité</a>
            ·
            <a href="/conditions-utilisation.php">Conditions générales d'utilisation</a>
        </div>

        <a href="javascript:history.back()" class="back-link">
            <i class="fas fa-arrow-left" aria-hidden="true"></i> Retour
        </a>
    </div>

    <?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
