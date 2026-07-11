<?php
session_start();

require_once __DIR__ . '/includes/site_url.php';
require_once __DIR__ . '/includes/asset_version.php';

$base = get_site_base_url();
$seo_title = "Conditions générales d'utilisation — Sugar Paper";
$seo_description = "CGU Sugar Paper : boutique en ligne, produits naturels et décoration pâtissière, commandes, livraison, suivi GPS, application mobile iOS/Android.";
$seo_canonical = $base . '/conditions-utilisation.php';

$contact_email = 'sugarpaper26@gmail.com';
$company_address = 'Hann Mariste 2 LOT R/01, Dakar, Sénégal';
$last_update = '11/07/2026';
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
        <h1><i class="fas fa-file-contract" aria-hidden="true"></i> Conditions générales d'utilisation</h1>
        <p class="legal-updated"><strong>Dernière mise à jour :</strong> <?php echo htmlspecialchars($last_update, ENT_QUOTES, 'UTF-8'); ?></p>

        <div class="legal-badge-row" aria-hidden="true">
            <span class="legal-badge"><i class="fas fa-shopping-bag"></i> E-commerce B2C</span>
            <span class="legal-badge"><i class="fas fa-mobile-alt"></i> Application mobile</span>
            <span class="legal-badge"><i class="fas fa-truck"></i> Livraison &amp; suivi</span>
        </div>

        <p>
            Les présentes conditions générales d'utilisation («&nbsp;<strong>CGU</strong>&nbsp;») régissent l'accès et l'usage du site web, de l'application mobile et des services proposés par
            <strong>Sugar Paper</strong>, boutique en ligne de produits naturels, de décoration pour gâteaux et de créations pâtissières personnalisées.
        </p>
        <p>
            En créant un compte, en parcourant le site, en passant commande ou en utilisant l'application mobile officielle Sugar Paper
            (identifiant iOS&nbsp;: <strong>com.sugarpaper.app</strong>, package Android&nbsp;: <strong>com.sugarpaper.app</strong>),
            vous reconnaissez avoir lu, compris et accepté sans réserve les présentes CGU, ainsi que notre
            <a href="/politique-confidentialite.php">Politique de confidentialité</a>, qui en fait partie intégrante.
        </p>
        <p>
            Si vous n'acceptez pas ces conditions, veuillez ne pas utiliser nos services.
        </p>

        <nav class="legal-toc" aria-label="Sommaire">
            <strong>Sommaire</strong>
            <ol>
                <li><a href="#cgu-1">Objet, définitions et champ d'application</a></li>
                <li><a href="#cgu-2">Éditeur et contact</a></li>
                <li><a href="#cgu-3">Compte utilisateur et sécurité</a></li>
                <li><a href="#cgu-4">Description des services</a></li>
                <li><a href="#cgu-4b">Application mobile et autorisations</a></li>
                <li><a href="#cgu-5">Règles d'utilisation acceptables</a></li>
                <li><a href="#cgu-6">Produits, prix et disponibilité</a></li>
                <li><a href="#cgu-7">Commande et validation</a></li>
                <li><a href="#cgu-8">Paiement</a></li>
                <li><a href="#cgu-9">Livraison et suivi en temps réel</a></li>
                <li><a href="#cgu-10">Annulation, retours et garanties</a></li>
                <li><a href="#cgu-11">Propriété intellectuelle</a></li>
                <li><a href="#cgu-12">Services tiers et liens</a></li>
                <li><a href="#cgu-13">Données personnelles</a></li>
                <li><a href="#cgu-14">Force majeure</a></li>
                <li><a href="#cgu-15">Limitation de responsabilité</a></li>
                <li><a href="#cgu-16">Modification et résiliation</a></li>
                <li><a href="#cgu-17">Droit applicable et litiges</a></li>
            </ol>
        </nav>

        <h2 id="cgu-1">1. Objet, définitions et champ d'application</h2>
        <p>Les CGU définissent les droits et obligations des utilisateurs des services Sugar Paper.</p>

        <h3>1.1 Définitions</h3>
        <ul>
            <li><strong>Service ou Plateforme</strong> : le site web Sugar Paper, l'application mobile, les espaces client et administrateur associés, ainsi que les fonctionnalités connexes (panier, commande, suivi de livraison, etc.).</li>
            <li><strong>Utilisateur</strong> : toute personne accédant au Service.</li>
            <li><strong>Client</strong> : Utilisateur disposant d'un compte et/ou passant une commande.</li>
            <li><strong>Livreur habilité</strong> : membre du personnel ou prestataire autorisé par Sugar Paper à effectuer des livraisons et, le cas échéant, à activer le suivi GPS pendant une course.</li>
            <li><strong>Contenu utilisateur</strong> : textes, images ou instructions que vous transmettez (profil, commande personnalisée, message au support).</li>
        </ul>

        <h3>1.2 Champ d'application</h3>
        <p>
            Les CGU s'appliquent à chaque visite ou connexion au Service, quelle que soit l'origine géographique de l'Utilisateur,
            dès lors que la commande ou la livraison relève du périmètre d'intervention de Sugar Paper (principalement le Sénégal).
            Sugar Paper se réserve le droit de refuser l'accès à toute personne ne respectant pas les CGU ou la réglementation applicable.
        </p>

        <h2 id="cgu-2">2. Éditeur du service et contact</h2>
        <p>Le Service est exploité sous la marque <strong>Sugar Paper</strong>. Coordonnées&nbsp;:</p>
        <ul>
            <li><strong>Adresse</strong> : <?php echo htmlspecialchars($company_address, ENT_QUOTES, 'UTF-8'); ?> ;</li>
            <li><strong>E-mail</strong> : <a href="mailto:<?php echo htmlspecialchars($contact_email, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($contact_email, ENT_QUOTES, 'UTF-8'); ?></a> ;</li>
            <li><strong>Téléphone</strong> : <a href="tel:+221774161212">+221 77 416 12 12</a>, <a href="tel:+221773292123">+221 77 32 92 123</a>, <a href="tel:+221338233514">+221 33 823 35 14</a>.</li>
        </ul>
        <p>Les coordonnées affichées sur le site au moment de votre demande prévalent en cas de mise à jour ultérieure.</p>

        <h2 id="cgu-3">3. Création de compte, identifiants et sécurité</h2>

        <h3>3.1 Exactitude des informations</h3>
        <p>
            Vous vous engagez à fournir des informations sincères, complètes et à jour (identité, e-mail, téléphone joignable, adresse de livraison).
            Toute fausse déclaration ou usurpation d'identité pourra entraîner la suspension du compte et, le cas échéant, des poursuites.
        </p>

        <h3>3.2 Confidentialité des identifiants</h3>
        <p>
            Vos identifiants de connexion sont strictement personnels. Vous êtes responsable des actions effectuées depuis votre compte,
            sauf preuve d'une faille de sécurité imputable à Sugar Paper. En cas de suspicion d'utilisation frauduleuse, modifiez votre mot de passe et contactez-nous sans délai.
        </p>

        <h3>3.3 Connexion via Google ou Apple</h3>
        <p>
            Si vous utilisez la connexion Google ou Apple, vous acceptez également les conditions de ces services pour l'authentification.
            Sugar Paper reçoit uniquement les informations que vous autorisez via ce tiers.
        </p>

        <h3>3.4 Suspension et clôture</h3>
        <p>
            Sugar Paper peut suspendre ou clôturer un compte en cas de manquement aux CGU, fraude, impayé ou injonction légale.
            La clôture n'efface pas les obligations nées avant la clôture (commandes en cours, factures, litiges).
            Vous pouvez demander la suppression de votre compte conformément à la
            <a href="/politique-confidentialite.php#priv-suppression">Politique de confidentialité</a>.
        </p>

        <h2 id="cgu-4">4. Description des services</h2>
        <p>Sugar Paper propose notamment&nbsp;:</p>
        <ul>
            <li>La vente en ligne de produits naturels (noix, feuilles, fruits, huiles, céréales, racines, cosmétiques naturels, etc.) ;</li>
            <li>La vente d'accessoires et produits de décoration pour gâteaux ;</li>
            <li>Des commandes personnalisées (gâteaux, créations sur mesure) ;</li>
            <li>La gestion de commandes, du panier et du suivi de livraison ;</li>
            <li>Un espace «&nbsp;Mon compte&nbsp;» (historique, profil, adresses) ;</li>
            <li>Des fonctionnalités réservées au personnel habilité (administration, livraison, suivi GPS livreur).</li>
        </ul>

        <h3>4.1 Contrat de vente</h3>
        <p>
            Sauf mention contraire explicite, le <strong>contrat de vente est conclu directement entre le Client et Sugar Paper</strong>,
            éditeur du Service et vendeur des produits proposés sur la Plateforme.
        </p>

        <h3>4.2 Stocks et disponibilité</h3>
        <p>
            Les stocks affichés sont mis à jour en temps réel dans la mesure du possible. Un produit peut exceptionnellement devenir indisponible
            après validation de commande (rupture simultanée). Vous serez informé dans les meilleurs délais et pourrez opter pour un remboursement, un avoir ou un produit de remplacement.
        </p>

        <h3>4.3 Absence de conseil médical ou diététique</h3>
        <p>
            Les descriptions de produits naturels ou alimentaires n'ont pas vocation à se substituer à un avis médical, nutritionnel ou professionnel.
            Le Client reste seul responsable de l'usage des produits et de la vérification de leur compatibilité avec sa situation (allergies, régime, etc.).
        </p>

        <h2 id="cgu-4b">4 bis. Application mobile et autorisations système (iOS / Android)</h2>
        <p>
            L'application mobile Sugar Paper donne accès au site e-commerce dans une interface sécurisée et peut solliciter,
            <strong>uniquement lorsque vous utilisez la fonction concernée</strong>, les autorisations suivantes&nbsp;:
        </p>
        <ul>
            <li><strong>Caméra et photothèque</strong> — pour prendre ou choisir une photo (profil, commande personnalisée). Exemple&nbsp;: joindre une image d'inspiration à une commande sur mesure.</li>
            <li><strong>Localisation (pendant l'utilisation)</strong> — lorsque vous appuyez sur «&nbsp;Localiser&nbsp;» ou «&nbsp;Mettre à jour ma position&nbsp;» pour confirmer une adresse de livraison ou d'inscription. Vous pouvez refuser et saisir l'adresse manuellement.</li>
            <li><strong>Localisation (arrière-plan / Toujours)</strong> — <strong>réservée aux livreurs habilités</strong>, uniquement pendant une livraison active qu'ils démarrent explicitement, afin de permettre au client de suivre la course en direct. Voir section&nbsp;9.2.</li>
            <li><strong>Notifications</strong> — pour vous informer du statut de vos commandes ou de messages liés à votre compte (avec votre consentement via l'application ou les réglages système).</li>
        </ul>
        <p>
            Avant la demande système, l'application peut afficher un <strong>écran explicatif</strong> rappelant la finalité de l'autorisation.
            L'application <strong>ne demande pas l'accès au microphone</strong>.
            Le refus d'une autorisation limite la fonction concernée sans empêcher la navigation générale sur le catalogue.
        </p>
        <p>
            Le détail des traitements de données figure dans la
            <a href="/politique-confidentialite.php#priv-9">Politique de confidentialité (section&nbsp;9)</a> et la
            <a href="/politique-confidentialite.php#priv-9-gps">section suivi GPS livraison</a>.
        </p>
        <p>
            En installant l'application depuis l'App Store ou Google Play, vous acceptez également les conditions propres à ces plateformes (Apple, Google) pour les téléchargements et mises à jour.
        </p>

        <h2 id="cgu-5">5. Règles d'utilisation acceptables</h2>
        <p>Il est notamment interdit de&nbsp;:</p>
        <ul>
            <li>contourner ou attaquer les mesures de sécurité du site, des comptes ou des infrastructures ;</li>
            <li>extraire massivement des données (scraping non autorisé), surcharger les serveurs ou utiliser des robots de manière abusive ;</li>
            <li>publier ou transmettre des contenus illicites, diffamatoires, discriminatoires, violents ou portant atteinte aux droits de tiers ;</li>
            <li>usurper l'identité d'un tiers ;</li>
            <li>utiliser le Service à des fins frauduleuses (fausses commandes, paiements contestés de mauvaise foi, etc.) ;</li>
            <li>accéder ou tenter d'accéder à des espaces administrateur ou livreur sans autorisation ;</li>
            <li>manipuler le système de suivi GPS ou transmettre de fausses positions.</li>
        </ul>
        <p>Toute violation pourra entraîner la suspension du compte et, le cas échéant, des poursuites civiles ou pénales.</p>

        <h3>5.1 Protection des mineurs</h3>
        <p>
            Le Service n'est pas destiné aux enfants de moins de <strong>13 ans</strong> en tant que public principal.
            Les mineurs doivent utiliser le Service sous la supervision d'un titulaire de l'autorité parentale.
        </p>

        <h2 id="cgu-6">6. Produits, prix, disponibilité et erreurs manifestes</h2>
        <p>
            Les prix sont affichés en <strong>franc CFA (FCFA)</strong>. Le montant total (produits + frais de livraison selon la zone) est présenté avant validation de la commande.
            Les photographies et descriptions sont fournies de bonne foi ; de légères variations (couleur à l'écran, conditionnement) peuvent exister sans engager une non-conformité au-delà des obligations légales.
        </p>
        <p>
            En cas d'<strong>erreur manifeste</strong> de prix (bug d'affichage, prix dérisoire), Sugar Paper pourra refuser ou annuler la commande après vous en avoir informé et, le cas échéant, rembourser les sommes encaissées.
        </p>
        <p>
            Les produits alimentaires périssables et les créations personnalisées peuvent être soumis à des conditions particulières indiquées sur la fiche produit ou lors de la commande sur mesure.
        </p>

        <h2 id="cgu-7">7. Commande, validation et preuve</h2>
        <p>
            La commande est formée par les étapes du parcours d'achat&nbsp;: panier, identification ou connexion, choix de livraison,
            acceptation des CGU et de la Politique de confidentialité, confirmation et paiement le cas échéant.
            L'enregistrement électronique sur nos serveurs, sous réserve de preuve contraire, fait foi du contenu et de la date de la commande.
        </p>

        <h3>7.1 Commandes personnalisées</h3>
        <p>
            Pour les gâteaux ou produits sur mesure, vous devez vérifier orthographe, couleurs, dimensions et options avant validation.
            Une commande confirmée avec des instructions erronées peut ne pas donner lieu à un échange si la fabrication a débuté conformément à vos indications.
            Les délais de préparation peuvent être plus longs ; ils vous sont communiqués lors de la commande ou par notre équipe.
        </p>

        <h3>7.2 Refus de commande</h3>
        <p>
            Sugar Paper se réserve le droit de refuser ou d'annuler toute commande en cas de stock insuffisant, d'impossibilité de livraison dans la zone demandée,
            de suspicion de fraude ou pour toute autre raison légitime, avec information du Client dans les meilleurs délais.
        </p>

        <h2 id="cgu-8">8. Paiement</h2>
        <p>
            Les moyens de paiement acceptés (espèces à la livraison, mobile money, carte bancaire ou tout autre mode) sont indiqués au moment du passage de commande.
            Les données de paiement sont traitées conformément aux normes de sécurité en vigueur ; les coordonnées complètes de carte ne sont en principe pas conservées sur nos serveurs au-delà du nécessaire (tokenisation ou redirection sécurisée).
        </p>
        <p>
            Tout impayé, rétrofacturation frauduleuse ou contestation abusive pourra entraîner la suspension du compte et le recouvrement des sommes dues.
        </p>

        <h2 id="cgu-9">9. Livraison, réception et suivi en temps réel</h2>

        <h3>9.1 Zones, délais et réception</h3>
        <p>
            Les zones desservies, délais indicatifs et tarifs de livraison sont précisés avant validation de la commande.
            Les délais sont fournis à titre indicatif sauf engagement ferme express sur une offre donnée.
            Vous devez être joignable au numéro indiqué et faciliter l'accès au lieu de livraison (codes, étage, consignes).
            En cas d'absence répétée ou d'adresse incomplète, des frais de nouvelle livraison pourront s'appliquer.
        </p>
        <p>
            Les risques de perte ou de détérioration des biens sont transférés au Client au moment de la remise physique, sauf disposition impérative contraire.
        </p>

        <h3>9.2 Adresse et localisation GPS (client)</h3>
        <p>
            Vous pouvez indiquer votre adresse manuellement ou utiliser «&nbsp;Localiser&nbsp;» pour préremplir votre position sur la carte.
            Vous restez responsable de l'exactitude de l'adresse finale validée. Le refus de la localisation n'empêche pas de commander si l'adresse est complète et vérifiable.
        </p>

        <h3 id="cgu-9-suivi">9.3 Suivi de livraison en temps réel</h3>
        <p>
            Pour certaines livraisons, Sugar Paper peut proposer un <strong>suivi en temps réel</strong> de la position du livreur sur une carte,
            accessible depuis votre espace commande ou via un <strong>lien sécurisé partagé</strong>.
        </p>
        <ul>
            <li>Le suivi n'est actif que pendant une <strong>livraison en cours</strong>, démarrée explicitement par un livreur habilité ;</li>
            <li>Le suivi s'arrête à la fin de la livraison ou si le livreur prend en charge une autre course ;</li>
            <li>Le lien de suivi public est limité dans le temps et ne doit pas être diffusé publiquement au-delà de ce qui est nécessaire pour informer la personne concernée par la réception ;</li>
            <li>Le suivi est un outil d'information ; des interruptions techniques (réseau, GPS, batterie) peuvent survenir sans engager une responsabilité au-delà de nos obligations contractuelles ;</li>
            <li>Les livreurs habilités acceptent, en activant une livraison, que leur position soit transmise au client concerné pendant la durée de la course, conformément à la Politique de confidentialité.</li>
        </ul>

        <h3>9.4 Retards et force majeure logistique</h3>
        <p>
            Sugar Paper n'est pas responsable des retards dus à des circonstances indépendantes de sa volonté raisonnable (conditions météo, trafic, événements exceptionnels).
            En cas de retard significatif, nous nous efforçons de vous informer.
        </p>

        <h2 id="cgu-10">10. Annulation, retours, échanges et garanties</h2>

        <h3>10.1 Annulation avant préparation</h3>
        <p>
            Une demande d'annulation peut être acceptée tant que la préparation ou l'expédition n'a pas commencé,
            notamment via l'espace «&nbsp;Mes commandes&nbsp;» ou en contactant le support.
            Les produits personnalisés ou déjà en fabrication peuvent ne plus être annulables.
        </p>

        <h3>10.2 Produits alimentaires et hygiène</h3>
        <p>
            Les denrées alimentaires périssables (gâteaux, produits frais) et les produits ouverts ne peuvent en principe pas être repris pour des raisons d'hygiène,
            sauf non-conformité avérée ou vice caché dans les conditions prévues par la loi.
        </p>

        <h3>10.3 Accessoires et produits non alimentaires</h3>
        <p>
            Pour les accessoires de décoration et produits non alimentaires, un retour ou échange peut être envisagé sous conditions
            (produit non utilisé, emballage d'origine, délai limité). Contactez-nous avant toute démarche de retour.
        </p>

        <h3>10.4 Garanties légales</h3>
        <p>
            Les garanties légales applicables (conformité, vices cachés) s'exercent dans les conditions et délais prévus par le droit sénégalais applicable.
            Toute réclamation doit être formulée dans les délais légaux avec les justificatifs nécessaires (photos, numéro de commande).
        </p>

        <h2 id="cgu-11">11. Propriété intellectuelle</h2>
        <p>
            L'ensemble des éléments du Service (structure, charte graphique, marques, logos, textes, images, recettes, créations Sugar Paper, code logiciel)
            est protégé par le droit de la propriété intellectuelle et reste la propriété de Sugar Paper ou de ses concédants.
        </p>
        <p>
            Toute reproduction, représentation, modification ou exploitation non autorisée est interdite.
            Les contenus que vous transmettez (photos de commande personnalisée) restent votre propriété ; vous nous accordez une licence limitée pour les traiter aux fins d'exécution de votre commande.
        </p>

        <h2 id="cgu-12">12. Liens hypertextes et services tiers</h2>
        <p>
            Le Service peut contenir des liens vers des sites ou services tiers (réseaux sociaux, cartes, paiement).
            Sugar Paper n'exerce aucun contrôle sur ces ressources externes et décline toute responsabilité quant à leur contenu ou leurs pratiques.
            L'utilisation de Google, Apple, Firebase ou d'autres prestataires est également soumise à leurs conditions propres.
        </p>

        <h2 id="cgu-13">13. Données personnelles</h2>
        <p>
            Le traitement de vos données personnelles est décrit dans notre
            <a href="/politique-confidentialite.php">Politique de confidentialité</a>, incluant&nbsp;:
            collecte, finalités, durées de conservation, droits des personnes, cookies, application mobile, localisation GPS et suivi livraison.
        </p>
        <p>
            En utilisant le Service, vous reconnaissez en avoir pris connaissance. Pour exercer vos droits ou demander la suppression de compte, contactez
            <a href="mailto:<?php echo htmlspecialchars($contact_email, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($contact_email, ENT_QUOTES, 'UTF-8'); ?></a>.
        </p>

        <h2 id="cgu-14">14. Force majeure</h2>
        <p>
            Sugar Paper ne pourra être tenue responsable de l'inexécution ou du retard dans l'exécution de ses obligations résultant de circonstances indépendantes de sa volonté
            (catastrophe naturelle, guerre, émeute, grève générale, panne réseau majeure, décision gouvernementale, pandémie, etc.), sous réserve des dispositions légales impératives.
        </p>

        <h2 id="cgu-15">15. Limitation de responsabilité</h2>
        <p>
            Dans les limites autorisées par la loi applicable, Sugar Paper ne sera pas responsable des dommages indirects, pertes de profit, perte de données ou préjudices immatériels
            résultant de l'utilisation ou de l'impossibilité d'utiliser le Service.
        </p>
        <p>
            La responsabilité de Sugar Paper, toutes causes confondues, est limitée au montant effectivement payé par le Client pour la commande concernée,
            sauf en cas de faute lourde, de dol ou de manquement à une obligation essentielle du contrat.
        </p>
        <p>
            Le Service est fourni «&nbsp;en l'état&nbsp;» ; nous nous efforçons d'assurer sa disponibilité et sa sécurité, sans garantie d'absence totale d'interruption ou d'erreur.
        </p>

        <h2 id="cgu-16">16. Modification des CGU, durée et résiliation</h2>
        <p>
            Sugar Paper peut modifier les présentes CGU pour refléter l'évolution du Service, de la réglementation ou des pratiques commerciales.
            La date de «&nbsp;dernière mise à jour&nbsp;» en tête de page sera ajustée. Pour les modifications substantielles, une information sur le site ou par e-mail pourra être utilisée.
        </p>
        <p>
            Les CGU s'appliquent pendant toute la durée d'utilisation du Service. Vous pouvez cesser d'utiliser le Service à tout moment ;
            Sugar Paper peut suspendre l'accès en cas de manquement, conformément à la section&nbsp;3.
        </p>

        <h2 id="cgu-17">17. Droit applicable et règlement des litiges</h2>
        <p>
            Les présentes CGU sont régies par le <strong>droit sénégalais</strong>, sous réserve des dispositions impératives applicables dans votre pays de résidence le cas échéant.
        </p>
        <p>
            En cas de litige, nous vous invitons à contacter d'abord notre service client à
            <a href="mailto:<?php echo htmlspecialchars($contact_email, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($contact_email, ENT_QUOTES, 'UTF-8'); ?></a>
            afin de rechercher une solution amiable.
        </p>
        <p>
            À défaut de résolution amiable dans un délai raisonnable, le litige sera porté devant les <strong>tribunaux compétents de Dakar, Sénégal</strong>,
            sauf disposition légale impérative contraire (notamment pour les consommateurs).
        </p>

        <p class="legal-note">
            Ce document constitue le contrat cadre entre vous et Sugar Paper pour l'utilisation du Service.
            Pour toute question juridique spécifique, consultez un professionnel du droit. Version en vigueur au <?php echo htmlspecialchars($last_update, ENT_QUOTES, 'UTF-8'); ?>.
        </p>

        <div class="legal-cross">
            <strong>Documents associés :</strong>
            <a href="/politique-confidentialite.php">Politique de confidentialité</a>
            ·
            <a href="/politique-confidentialite.php#priv-9-gps">Suivi GPS livraison</a>
            ·
            <a href="/politique-confidentialite.php#priv-suppression">Suppression de compte</a>
        </div>

        <a href="javascript:history.back()" class="back-link">
            <i class="fas fa-arrow-left" aria-hidden="true"></i> Retour
        </a>
    </article>

    <?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
