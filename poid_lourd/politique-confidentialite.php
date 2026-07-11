<?php
require_once __DIR__ . '/includes/session_user.php';
session_start_persistent();

// Meta SEO
require_once __DIR__ . '/includes/site_url.php';
require_once __DIR__ . '/includes/site_brand.php';
$base = get_site_base_url();
$seo_title = 'Politique de Confidentialité - COLObanes';
$seo_description = 'Politique de confidentialité COLObanes : protection des données, sécurité, intégrité, non-commercialisation, droits des personnes et sous-traitants.';
$seo_canonical = $base . '/politique-confidentialite.php';
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
        .legal-page h4 {
            color: var(--gris-fonce);
            font-size: 0.98rem;
            margin-top: 16px;
            margin-bottom: 8px;
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
        .legal-toc {
            background: var(--fond-secondaire);
            border: 1px solid var(--glass-border);
            border-radius: 10px;
            padding: 20px 22px;
            margin-bottom: 32px;
        }
        .legal-toc strong {
            display: block;
            margin-bottom: 12px;
            color: var(--titres);
        }
        .legal-toc ol {
            margin: 0;
            padding-left: 22px;
        }
        .legal-toc li {
            margin-bottom: 6px;
            font-size: 14px;
        }
        .legal-table-wrap {
            overflow-x: auto;
            margin: 18px 0;
            border-radius: 8px;
            border: 1px solid var(--glass-border);
        }
        .legal-table-wrap table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        .legal-table-wrap th,
        .legal-table-wrap td {
            padding: 12px 14px;
            text-align: left;
            border-bottom: 1px solid var(--glass-border);
            vertical-align: top;
        }
        .legal-table-wrap th {
            background: var(--bleu-pale);
            color: var(--titres);
            font-weight: 600;
        }
        .legal-table-wrap tr:last-child td {
            border-bottom: none;
        }
        .legal-note {
            font-size: 13px;
            color: var(--gris-moyen);
            font-style: italic;
            margin-top: 20px;
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
    <?php include('nav_bar.php'); ?>

    <div class="legal-page">
        <h1><i class="fas fa-shield-alt" aria-hidden="true"></i> Politique de confidentialité et de protection des données</h1>
        <p class="legal-updated"><strong>Dernière mise à jour :</strong> <?php echo htmlspecialchars(date('d/m/Y'), ENT_QUOTES, 'UTF-8'); ?></p>

        <p>
            La présente politique décrit comment <strong>COLObanes</strong> (« nous », « notre », « la Plateforme »), dans le cadre de son activité de marketplace en ligne au Sénégal,
            <strong>collecte</strong>, <strong>utilise</strong>, <strong>conserve</strong>, <strong>partage</strong> et <strong>protège</strong> les informations relatives aux personnes physiques qui utilisent le site, l'application mobile ou les services associés
            (visiteurs, clients, vendeurs partenaires et, le cas échéant, leurs représentants).
            Nous nous engageons à traiter les données de manière <strong>loyale</strong>, <strong>transparente</strong> et <strong>sécurisée</strong>, et à limiter la collecte au strict nécessaire aux finalités décrites ci-dessous.
            <strong>Nous ne commercialisons pas vos données personnelles</strong> et ne les vendons pas à des tiers (voir section <a href="#priv-2b">2 bis</a> et <a href="#priv-6-nocom">6.5</a>).
        </p>
        <p>
            L'utilisation du Service implique la prise de connaissance de cette politique ainsi que des
            <a href="/conditions-utilisation.php">Conditions générales d'utilisation</a>.
            Pour la <strong>suppression de votre compte utilisateur</strong> (procédure, données supprimées ou conservées, délais), consultez la
            <a href="/politique-suppression-compte.php">Politique de suppression de compte</a>.
        </p>

        <div class="legal-toc">
            <strong>Sommaire</strong>
            <ol>
                <li><a href="#priv-1">Responsable du traitement et contact</a></li>
                <li><a href="#priv-2">Principes et engagements</a></li>
                <li><a href="#priv-2b">Priorité aux données, intégrité et non-commercialisation</a></li>
                <li><a href="#priv-3">Données collectées et origine</a></li>
                <li><a href="#priv-4">Finalités et bases légales du traitement</a></li>
                <li><a href="#priv-5">Décisions automatisées et profilage</a></li>
                <li><a href="#priv-6">Destinataires, sous-traitants et transferts</a></li>
                <li><a href="#priv-7">Durées de conservation</a></li>
                <li><a href="#priv-8">Mesures de sécurité des données et des systèmes</a></li>
                <li><a href="#priv-9">Appareils, journaux techniques et application mobile</a></li>
                <li><a href="#priv-10">Cookies et technologies similaires</a></li>
                <li><a href="#priv-11">Communications électroniques et notifications</a></li>
                <li><a href="#priv-12">Mineurs</a></li>
                <li><a href="#priv-13">Vos droits</a></li>
                <li><a href="#priv-suppression">Suppression du compte utilisateur</a></li>
                <li><a href="#priv-14">Réclamations auprès d'une autorité</a></li>
                <li><a href="#priv-15">Évolution de cette politique</a></li>
            </ol>
        </div>

        <h2 id="priv-1">1. Responsable du traitement et contact</h2>
        <p>
            Pour les traitements réalisés dans le cadre de l'exploitation de la Plateforme COLObanes, le <strong>responsable du traitement</strong> est l'entité opérant le service e-marketplace sous la marque COLObanes,
            joignable aux coordonnées suivantes :
        </p>
        <ul>
            <li><strong>Adresse</strong> : Rond Point Colobane, Dakar, Sénégal ;</li>
            <li><strong>Courriel dédié vie privée / support</strong> : <a href="mailto:contact@colobanes.com">contact@colobanes.com</a> (merci de préciser « Données personnelles » dans l'objet pour un traitement prioritaire).</li>
        </ul>
        <p>
            Selon l'organisation interne et la taille des opérations, COLObanes peut désigner une personne référente « correspondant vie privée » ou un service interne chargé de centraliser les demandes relatives aux droits des personnes (voir section 13).
            L'absence de titre « DPD » ou « DPO » officiel n'empêche pas l'exercice de vos droits aux adresses ci-dessus.
        </p>

        <h2 id="priv-2">2. Principes et engagements</h2>
        <p>Nous appliquons notamment les principes suivants :</p>
        <ul>
            <li><strong>Licéité, loyauté et transparence</strong> : chaque traitement repose sur une base légale identifiée (consentement, exécution d'un contrat, obligation légale, intérêt légitime équilibré) et vous est expliqué dans la mesure du possible sans compromettre la sécurité du site.</li>
            <li><strong>Minimisation</strong> : nous ne collectons que les données adéquates, pertinentes et limitées à ce qui est nécessaire.</li>
            <li><strong>Exactitude</strong> : nous vous invitons à mettre à jour vos informations ; nous pouvons corriger des erreurs manifestes signalées.</li>
            <li><strong>Limitation de la conservation</strong> : les données ne sont pas gardées au-delà des durées indiquées à la section 7, sauf obligation légale contraire.</li>
            <li><strong>Intégrité et confidentialité</strong> : mesures techniques et organisationnelles appropriées contre tout accès non autorisé, perte ou altération (section 8).</li>
            <li><strong>Responsabilisation</strong> : documentation interne, contrôles d'accès, clauses de confidentialité avec les prestataires et sensibilisation des équipes habilitées.</li>
        </ul>

        <h2 id="priv-2b">2 bis. Priorité aux données personnelles, intégrité et non-commercialisation</h2>
        <p>
            La protection de vos données personnelles est une <strong>priorité opérationnelle</strong> pour COLObanes, et non un simple accessoire technique.
            Cette section complète les principes généraux et répond aux exigences de transparence des plateformes <strong>Apple</strong> et <strong>Google</strong> en matière de confidentialité et de traitement des informations utilisateur.
        </p>

        <h3>2 bis.1 Aucune vente ni commercialisation de vos données</h3>
        <p>
            COLObanes <strong>ne vend pas</strong>, <strong>ne loue pas</strong> et <strong>ne cède pas</strong> vos données personnelles à des tiers à des fins de marketing, de profilage publicitaire ou de monétisation de dossiers utilisateurs.
            Nous <strong>ne partageons pas</strong> vos coordonnées, votre historique d'achat ou vos données de localisation avec des courtiers en données, des réseaux publicitaires tiers ou des plateformes d'enrichissement de bases de données à des fins commerciales indépendantes de COLObanes.
        </p>
        <p>
            Les seuls partages autorisés sont ceux strictement nécessaires à l'exécution du Service (vendeur, livreur, prestataire technique contractuel) ou imposés par la loi, comme détaillé à la section 6.
        </p>

        <h3>2 bis.2 Finalité limitée et usage loyal</h3>
        <p>
            Chaque donnée collectée sert une <strong>finalité déterminée, explicite et légitime</strong> (compte, commande, livraison, sécurité, support, obligations légales).
            Nous ne réutilisons pas vos données à des fins incompatibles avec celles pour lesquelles elles ont été collectées, sauf avec votre consentement lorsque la loi l'exige ou dans les cas prévus par la loi (par exemple statistiques agrégées et anonymisées ne permettant plus de vous identifier).
        </p>

        <h3>2 bis.3 Intégrité, exactitude et sécurité des données</h3>
        <p>
            Nous nous engageons à préserver l'<strong>intégrité</strong> et la <strong>confidentialité</strong> des données que vous nous confiez :
        </p>
        <ul>
            <li><strong>Exactitude</strong> : vous pouvez mettre à jour vos informations depuis votre espace compte ; nous corrigeons les erreurs manifestes signalées ;</li>
            <li><strong>Protection contre l'altération</strong> : contrôles d'accès, journaux d'administration, sauvegardes régulières et procédures de restauration ;</li>
            <li><strong>Protection contre la perte</strong> : copies de secours sur environnements distincts, avec accès restreint ;</li>
            <li><strong>Protection contre l'accès non autorisé</strong> : chiffrement en transit (HTTPS/TLS), mots de passe hachés, moindre privilège pour les comptes internes ;</li>
            <li><strong>Traçabilité</strong> : journalisation des accès sensibles aux back-offices lorsque la stack technique le permet ;</li>
            <li><strong>Notification</strong> : en cas de violation de données susceptible d'affecter vos droits, COLObanes s'efforce d'informer les personnes concernées et, le cas échéant, l'autorité compétente, conformément au droit applicable.</li>
        </ul>

        <h3>2 bis.4 Données des mineurs</h3>
        <p>
            COLObanes ne collecte pas sciemment de données personnelles auprès d'enfants de moins de <strong>13 ans</strong> à des fins commerciales directes.
            Si nous apprenons qu'un enfant a fourni des données sans consentement parental valable, nous prendrons des mesures pour <strong>supprimer</strong> ces informations dans les meilleurs délais.
            Les mineurs doivent utiliser le Service sous la supervision d'un adulte responsable (voir aussi section 12).
        </p>

        <h3>2 bis.5 Vos données restent sous votre contrôle</h3>
        <p>
            Vous conservez le contrôle sur les informations que vous choisissez de fournir (profil, adresse, préférences de notification).
            Vous pouvez à tout moment : modifier vos données, refuser certaines autorisations (caméra, localisation, notifications) dans les réglages de votre appareil, vous opposer au marketing direct, demander la suppression de votre compte conformément à notre
            <a href="/politique-suppression-compte.php">Politique de suppression de compte</a>, et exercer l'ensemble de vos droits (section 13).
        </p>
        <p>
            COLObanes ne conditionne pas l'accès au catalogue à l'acceptation de traitements non essentiels (publicité ciblée tierce, revente de données) — de tels traitements ne sont pas pratiqués sur la Plateforme.
        </p>

        <h2 id="priv-3">3. Données collectées et origine</h2>
        <h3>3.1 Données d'identification et de compte</h3>
        <ul>
            <li>Nom, prénom ou dénomination sociale pour les professionnels ;</li>
            <li>Identifiant de connexion (adresse électronique ou identifiant attribué) ;</li>
            <li>Mot de passe : stocké sous forme <strong>cryptographiquement dérivée</strong> (hachage sécurisé) — nous ne conservons pas le mot de passe en clair ;</li>
            <li>Numéro de téléphone mobile ou fixe ;</li>
            <li>Éventuellement date de naissance ou pièce d'identité <strong>uniquement si la réglementation ou la vérification « vendeur » l'exige</strong>, avec information préalable sur la finalité.</li>
        </ul>

        <h3>3.2 Données de commande, livraison et relation commerciale</h3>
        <ul>
            <li>Adresses postales complètes (livraison et, le cas échéant, facturation) ;</li>
            <li>Coordonnées géographiques (<strong>latitude / longitude</strong>) et, le cas échéant, <strong>précision estimée</strong> (en mètres), lorsque vous utilisez la fonction «&nbsp;Localiser&nbsp;» ou «&nbsp;Mettre à jour ma position&nbsp;» sur le site ou l'application mobile ;</li>
            <li>Adresse textuelle dérivée du géocodage inverse (lieu, quartier, arrondissement, ville, pays) associée à ces coordonnées ;</li>
            <li>Zone ou commune de livraison sélectionnée ;</li>
            <li>Détails des commandes : références produits, quantités, prix, personnalisations, messages associés au panier ou à la commande ;</li>
            <li>Historique des statuts de commande, échanges avec le support ou avec la boutique ;</li>
            <li>Éventuellement instructions de livraison sensibles (code porte, étage) : à fournir avec discernement ; elles sont traitées pour la seule exécution de la livraison.</li>
        </ul>

        <h3>3.3 Données de paiement</h3>
        <p>
            Selon le moyen de paiement, nous pouvons traiter des <strong>métadonnées de transaction</strong> (montant, devise FCFA, statut, identifiant de transaction fourni par le prestataire, moyen générique utilisé : carte, mobile money, espèces à la livraison).
            Les <strong>numéros de carte complets</strong> sont en principe traités par des prestataires certifiés (redirection, page hébergée ou tokenisation) conformément au standard PCI-DSS applicable.
            Nous n'imprimons pas les codes cryptogrammes sur les reçus et ne vous demandons jamais par e-mail non sécurisé de communiquer votre code de carte ou votre mot de passe.
        </p>

        <h3>3.4 Données relatives aux vendeurs et boutiques partenaires</h3>
        <ul>
            <li>Informations d'immatriculation, représentants légaux, RIB ou coordonnées bancaires pour les virements, pièces justificatives ;</li>
            <li>Contenus publics : description de la boutique, produits, visuels ;</li>
            <li>Données de performance agrégées (chiffre d'affaires, nombre de commandes) nécessaires aux commissions ou à la comptabilité interne.</li>
        </ul>

        <h3>3.5 Données techniques, réseau et « appareil »</h3>
        <p>
            À chaque requête vers nos serveurs, certaines informations sont enregistrées automatiquement dans des <strong>fichiers journaux serveur</strong> ou équivalents :
        </p>
        <ul>
            <li>Adresse IP (IPv4 ou IPv6), parfois approximativement géolocalisée au niveau région/pays ;</li>
            <li>Horodatage de la requête ;</li>
            <li>Type et version du navigateur ou de l'application (User-Agent) ;</li>
            <li>URL demandée, code de réponse HTTP, volume de données transféré ;</li>
            <li>Identifiant de session technique ou jeton d'authentification côté navigateur / application ;</li>
            <li>Sur <strong>application mobile</strong> (par exemple application Flutter sous la marque COLObanes) : identifiant d'installation ou jetons de notification push (Firebase Cloud Messaging ou équivalent), modèle d'appareil, version du système d'exploitation (Android / iOS), éventuellement identifiant publicitaire si vous l'avez autorisé au niveau du système.</li>
        </ul>
        <p>
            Ces données servent principalement à la <strong>sécurité</strong> (détection d'abus, blocage d'IPs malveillantes), au <strong>dépannage</strong>, aux <strong>statistiques d'usage agrégées</strong> et à l'<strong>amélioration des performances</strong>.
        </p>

        <h3>3.6 Cookies et stockage local côté navigateur</h3>
        <p>
            Voir section 10 pour le détail des finalités (session, préférences, panier, mesure d'audience, etc.).
        </p>

        <h3>3.7 Données reçues de tiers</h3>
        <p>
            Si vous vous connectez ou interagissez via un compte tiers (réseau social, opérateur mobile), nous pouvons recevoir un identifiant technique ou des informations de profil que <strong>vous avez autorisées</strong> côté ce tiers.
            Nous ne fusionnons pas ces données avec des dossiers tiers achetés sur le marché.
        </p>

        <h2 id="priv-4">4. Finalités et bases légales du traitement</h2>
        <div class="legal-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Finalité</th>
                        <th>Exemples de données</th>
                        <th>Base légale principale (synthèse)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Création et gestion du compte utilisateur</td>
                        <td>Identité, e-mail, mot de passe dérivé, préférences de compte</td>
                        <td>Exécution des mesures précontractuelles / contrat ; intérêt légitime en matière de lutte contre la fraude à l'inscription</td>
                    </tr>
                    <tr>
                        <td>Passation, suivi et livraison des commandes</td>
                        <td>Coordonnées, détail du panier, statut, échanges logistiques</td>
                        <td>Exécution du contrat de vente / de prestation marketplace</td>
                    </tr>
                    <tr>
                        <td>Localisation GPS (site et application mobile)</td>
                        <td>Latitude, longitude, précision, adresse dérivée ; position boutique vendeur</td>
                        <td>Exécution du contrat (livraison) ; consentement via l'autorisation système lors de l'action «&nbsp;Localiser&nbsp;» ; intérêt légitime pour afficher les boutiques à proximité lorsque vous sollicitez cette fonction</td>
                    </tr>
                    <tr>
                        <td>Paiement et prévention de la fraude</td>
                        <td>Métadonnées de transaction, logs de sécurité, score de risque interne</td>
                        <td>Exécution du contrat ; obligation légale (compta, lutte contre le blanchiment le cas échéant) ; intérêt légitime à sécuriser les paiements</td>
                    </tr>
                    <tr>
                        <td>Support client et médiation</td>
                        <td>Historique des tickets, enregistrements d'appel si vous y consentez</td>
                        <td>Exécution du contrat ; intérêt légitime à améliorer le service</td>
                    </tr>
                    <tr>
                        <td>Obligations comptables et fiscales</td>
                        <td>Factures, justificatifs, données de transaction conservées légalement</td>
                        <td>Obligation légale</td>
                    </tr>
                    <tr>
                        <td>Sécurité du site, détection d'abus</td>
                        <td>Adresse IP, journaux, empreintes techniques</td>
                        <td>Intérêt légitime ; obligation légale en cas de réquisition</td>
                    </tr>
                    <tr>
                        <td>Amélioration du produit et statistiques d'usage</td>
                        <td>Données agrégées ou pseudonymisées, parcours de navigation</td>
                        <td>Intérêt légitime ; consentement pour certaines couches analytiques non essentielles</td>
                    </tr>
                    <tr>
                        <td>Prospection commerciale par voie électronique</td>
                        <td>E-mail, historique d'opt-in, préférences</td>
                        <td>Consentement préalable lorsque requis par la loi ; intérêt légitime pour clients professionnels sous réserve de droit d'opposition</td>
                    </tr>
                    <tr>
                        <td>Modération des contenus (images, signalements)</td>
                        <td>Empreintes d'images, scores d'analyse, décisions de validation, journaux de signalement</td>
                        <td>Intérêt légitime (sécurité des utilisateurs, conformité stores) ; obligation légale le cas échéant</td>
                    </tr>
                    <tr>
                        <td>Notifications push (application mobile)</td>
                        <td>Jeton d'appareil FCM/APNs, préférences de notification</td>
                        <td>Consentement explicite dans l'application ou les réglages système</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p>
            Lorsque le traitement est fondé sur l'<strong>intérêt légitime</strong>, nous avons procédé à une balance d'intérêts : par exemple, la journalisation courte des adresses IP pour bloquer des attaques par déni de service est nécessaire et proportionnée à la sécurité de l'ensemble des utilisateurs.
        </p>

        <h2 id="priv-5">5. Décisions automatisées et profilage</h2>
        <p>
            COLObanes peut mettre en œuvre des règles automatisées à finalité <strong>non juridiquement contraignante</strong> pour l'utilisateur : détection de paniers anormaux, scoring interne simple de risque fraude, classement de résultats de recherche en fonction de pertinence commerciale ou de disponibilité.
        </p>
        <p>
            Aucune décision produisant des effets juridiques significatifs vis-à-vis de vous (par exemple refus systématique de tout moyen de paiement sans explication humaine) n'est prise <strong>sans possibilité d'intervention humaine</strong>, sauf obligation légale contraire.
            Vous pouvez contester une décision vous concernant en écrivant à <a href="mailto:contact@colobanes.com">contact@colobanes.com</a>.
        </p>

        <h2 id="priv-6">6. Destinataires, sous-traitants et transferts</h2>
        <h3>6.1 Accès internes</h3>
        <p>
            Seules les personnes autorisées au sein de COLObanes (support client, équipes techniques, comptabilité, opérations logistiques) accèdent aux données, dans la limite du besoin d'en connaissance (<em>need-to-know</em>), avec authentification individuelle lorsque possible.
        </p>

        <h3>6.2 Vendeurs et partenaires logistiques</h3>
        <p>
            Pour exécuter une commande, nous transmettons au <strong>Vendeur</strong> concerné et, si besoin, à un <strong>transporteur ou livreur</strong>, les nom, téléphone, adresse et instructions strictement nécessaires.
            Ces partenaires sont contractuellement tenus à une utilisation limitée aux fins de livraison ou de service après-vente, mais nous vous recommandons de consulter également leurs propres politiques lorsque vous interagissez directement avec eux hors Plateforme.
        </p>

        <h3>6.3 Prestataires techniques (hébergement, e-mail, analytics, Paiement)</h3>
                 <p>
            Nous faisons appel à des sous-traitants pour l'hébergement web, l'envoi d'e-mails transactionnels, la sauvegarde, l'analyse d'audience ou le traitement des paiements.
            Ces acteurs sont sélectionnés pour leurs garanties de sécurité et liés par des clauses de confidentialité et de traitement des données conformes au droit applicable.
        </p>
        <p>
            Certains prestataires peuvent être situés <strong>en dehors du Sénégal</strong> (par exemple infrastructure cloud en Europe ou aux États-Unis). Le cas échéant, nous veillons à mettre en place des garanties appropriées (clauses contractuelles types, localisation dans des pays reconnus adéquats, chiffrement en transit) selon les instruments disponibles.
        </p>

        <h3>6.4 Autorités publiques</h3>
        <p>
            Nous pouvons communiquer des données lorsque la loi l'exige (réquisition judiciaire, réponse à une administration compétente) ou pour protéger nos droits et la sécurité des utilisateurs, dans le strict cadre légal.
        </p>

        <h3 id="priv-6-nocom">6.5 Ce que nous ne faisons pas avec vos données</h3>
        <p>Pour éviter toute ambiguïté, COLObanes s'engage à <strong>ne pas</strong> :</p>
        <ul>
            <li>vendre, louer ou échanger vos données personnelles à des annonceurs ou data brokers ;</li>
            <li>créer de profils publicitaires comportementaux vendus à des tiers ;</li>
            <li>fusionner vos données COLObanes avec des bases de données acquises sur le marché sans base légale et information préalable ;</li>
            <li>utiliser vos coordonnées GPS ou vos photos à des fins publicitaires tierces ;</li>
            <li>transmettre vos données de paiement complètes (numéro de carte, cryptogramme) à des partenaires non habilités au traitement sécurisé.</li>
        </ul>
        <p>
            Les prestataires techniques (hébergement, envoi d'e-mails, notifications push Firebase, analyse d'images pour modération le cas échéant) agissent en qualité de <strong>sous-traitants</strong> ou de responsables conjoints limités à leur mission, liés par des obligations contractuelles de confidentialité et de sécurité.
        </p>

        <h2 id="priv-7">7. Durées de conservation</h2>
        <div class="legal-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Catégorie</th>
                        <th>Durée indicative</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Données du compte client actif</td>
                        <td>Pendant toute la durée de vie du compte + courtes périodes techniques de réplication sauvegarde</td>
                    </tr>
                    <tr>
                        <td>Données de commande et facturation</td>
                        <td>Durée imposée par la réglementation comptable et fiscale applicable (en pratique souvent jusqu'à <strong>dix (10) ans</strong> pour les pièces justificatives de facturation, sous réserve des textes en vigueur et de l'avis de votre conseil)</td>
                    </tr>
                    <tr>
                        <td>Journaux serveur et sécurité</td>
                        <td>De quelques jours à <strong>12 mois</strong> selon la criticité (trafic HTTP vs journaux d'authentification admin)</td>
                    </tr>
                    <tr>
                        <td>Cookies non essentiels / analytics</td>
                        <td>Conformément à la durée indiquée dans le bandeau ou les paramètres (souvent <strong>6 à 13 mois</strong> maximum pour un cookie d'audience)</td>
                    </tr>
                    <tr>
                        <td>Prospection : données marketing</td>
                        <td>Jusqu'au <strong>retrait du consentement</strong> ou <strong>3 ans</strong> d'inactivité depuis le dernier contact commercial émanant du client (usage courant), selon la politique interne affinée</td>
                    </tr>
                    <tr>
                        <td>Données des comptes vendeurs</td>
                        <td>Durée du contrat partenaire + obligations légales ; certains justificatifs peuvent être archivés au-delà</td>
                    </tr>
                    <tr>
                        <td>Coordonnées GPS enregistrées sur le compte (client ou boutique vendeur)</td>
                        <td>Tant que le compte est actif et que la position reste pertinente pour la livraison ou l'affichage de la boutique ; mise à jour ou suppression via votre espace compte / paramètres vendeur</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p>
            À l'issue des durées, les données sont <strong>supprimées</strong> ou <strong>anonymisées irréversiblement</strong> lorsque leur conservation n'est plus justifiée.
        </p>

        <h2 id="priv-8">8. Mesures de sécurité des données et des systèmes</h2>
        <p>Nous mettons en œuvre une approche de sécurité en profondeur, incluant notamment :</p>
        <ul>
            <li><strong>Chiffrement en transit</strong> : utilisation de TLS (HTTPS) pour les échanges entre votre navigateur ou application et nos API lorsque la stack technique le permet ;</li>
            <li><strong>Gestion des mots de passe</strong> : algorithms de hachage adaptatifs (type bcrypt / argon2 selon implémentation), politique de réinitialisation sécurisée par lien à durée limitée ;</li>
            <li><strong>Contrôle d'accès</strong> : comptes administrateur avec moindre privilège, journaux d'accès aux back-offices ;</li>
            <li><strong>Protection des injections</strong> : requêtes paramétrées côté base de données, échappement des sorties pour limiter les failles XSS sur les zones dynamiques ;</li>
            <li><strong>Sauvegardes</strong> : copies régulières pour la continuité d'activité, stockées sur des environnements distincts avec accès restreint ;</li>
            <li><strong>Mises à jour</strong> : application de correctifs de sécurité sur les composants serveurs et dépendances dans des délais raisonnables ;</li>
            <li><strong>Sensibilisation</strong> : consignes internes sur la lutte contre l'hameçonnage et la fraude au président (BEC).</li>
        </ul>
        <p>
            Malgré ces mesures, <strong>aucune transmission sur Internet ne peut être garantie à 100&nbsp;% inviolable</strong>. Nous vous invitons à protéger vos terminaux (code de déverrouillage, antivirus à jour, méfiance vis-à-vis des liens suspects prétendant provenir de COLObanes).
        </p>
        <h3>8.1 Intégrité et disponibilité</h3>
        <p>
            Au-delà de la confidentialité, nous veillons à l'<strong>intégrité</strong> des données (qu'elles ne soient ni altérées ni corrompues de manière non autorisée) et à leur <strong>disponibilité</strong> raisonnable pour l'exécution des commandes et la continuité du Service.
            Les opérations sensibles (modification de mot de passe, changement d'adresse de livraison, validation de commande) transitent par des canaux sécurisés ; les sessions administratives font l'objet de contrôles d'accès renforcés.
        </p>
        <h3>8.2 Modération et données associées aux contenus</h3>
        <p>
            Les images et contenus soumis à modération (voir <a href="/conditions-utilisation.php#cgu-5b">CGU — section modération</a>) peuvent faire l'objet de traitements techniques (empreinte de fichier, scores d'analyse automatique, décisions d'approbation ou de refus).
            Ces données de modération sont conservées le temps nécessaire à la sécurité de la Plateforme et à la preuve en cas de litige ou de signalement, puis supprimées ou anonymisées selon les durées internes.
        </p>

        <h2 id="priv-9">9. Appareils, journaux techniques et application mobile</h2>
        <h3>9.1 Application mobile COLObanes (iOS et Android)</h3>
        <p>
            L'application mobile officielle COLObanes (identifiant de bundle iOS&nbsp;: <strong>com.colobanes.app</strong>, package Android&nbsp;: <strong>com.colobanes.app</strong>)
            charge le site marketplace dans une interface sécurisée et expose, sur demande explicite de l'utilisateur, des fonctions natives (prise de photo, localisation GPS pour la livraison ou la boutique vendeur, notifications de commande).
            Elle ne collecte pas de données via la caméra, la galerie ou le GPS sans action de votre part (bouton «&nbsp;Localiser&nbsp;», «&nbsp;Prendre une photo&nbsp;», etc.) ni sans l'autorisation affichée par iOS ou Android.
        </p>
        <h3>9.2 Identifiants d'appareil et notifications push</h3>
        <p>
            Lorsque vous installez l'application et acceptez les notifications, un <strong>jeton technique</strong> est attribué par Firebase Cloud Messaging (Google) et, sur iOS, relayé via Apple Push Notification service (APNs).
            Ce jeton sert uniquement à vous adresser des alertes liées au Service (ex.&nbsp;: confirmation ou expédition de commande, message du support si vous l'avez sollicité).
            Il est associé à votre compte ou à votre session selon l'implémentation technique ; vous pouvez le neutraliser en refusant les notifications dans Réglages &gt; COLObanes ou en désinstallant l'application.
        </p>
        <h3>9.3 Permissions matérielles (caméra, photos, localisation)</h3>
        <p>
            Conformément aux exigences d'Apple (App Store, ligne directrice 5.1.1) et de Google Play, chaque accès sensible est demandé <strong>au moment de l'usage</strong>, avec une explication affichée par le système d'exploitation.
            Le tableau ci-dessous reprend les finalités réelles ; les textes affichés sur iPhone/iPad reprennent les mêmes informations.
        </p>
        <div class="legal-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Permission</th>
                        <th>Finalité</th>
                        <th>Exemple concret</th>
                        <th>Obligatoire&nbsp;?</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Caméra</strong></td>
                        <td>Prendre une photo à joindre au profil, à une fiche produit vendeur ou à un message</td>
                        <td>Photographier un article que vous mettez en vente sur la marketplace</td>
                        <td>Non — refus possible ; fonction «&nbsp;prendre une photo&nbsp;» indisponible</td>
                    </tr>
                    <tr>
                        <td><strong>Photothèque (lecture)</strong></td>
                        <td>Choisir une image déjà enregistrée sur l'appareil</td>
                        <td>Sélectionner une photo de profil depuis la galerie</td>
                        <td>Non</td>
                    </tr>
                    <tr>
                        <td><strong>Photothèque (ajout)</strong></td>
                        <td>Enregistrer une image téléchargée depuis COLObanes, si vous utilisez la fonction d'enregistrement</td>
                        <td>Sauvegarder une preuve de commande en image sur votre appareil</td>
                        <td>Non</td>
                    </tr>
                    <tr>
                        <td><strong>Localisation (pendant l'utilisation)</strong></td>
                        <td>Obtenir vos coordonnées GPS pour préremplir ou confirmer une adresse sur la carte (livraison, inscription, boutiques proches, emplacement boutique vendeur)</td>
                        <td>Positionner votre point de livraison à Dakar lors d'une commande ; localiser votre boutique à l'inscription vendeur</td>
                        <td>Non — saisie manuelle de l'adresse possible</td>
                    </tr>
                    <tr>
                        <td><strong>Notifications</strong></td>
                        <td>Alertes de suivi de commande et informations de compte</td>
                        <td>Notification «&nbsp;Votre commande est en livraison&nbsp;»</td>
                        <td>Non</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p>
            <strong>Microphone&nbsp;:</strong> l'application COLObanes <strong>ne demande pas</strong> l'accès au microphone et n'enregistre pas d'audio.
            <strong>Localisation en arrière-plan&nbsp;:</strong> non utilisée ; seule la localisation «&nbsp;pendant l'utilisation de l'app&nbsp;» (<em>When In Use</em>) peut être sollicitée. L'application <strong>ne demande pas</strong> l'accès «&nbsp;Toujours&nbsp;» / en arrière-plan.
        </p>
        <h3 id="priv-9-gps">9.4 Traitement des coordonnées GPS</h3>
        <p>
            Lorsque vous autorisez la localisation, l'application ou le site peut&nbsp;:
        </p>
        <ul>
            <li>lire la position GPS de votre appareil (via le GPS natif de l'application mobile ou le navigateur) ;</li>
            <li>convertir ces coordonnées en adresse lisible par géocodage inverse (service COLObanes et, en secours, prestataire cartographique) ;</li>
            <li>enregistrer latitude, longitude, précision et adresse associée sur votre compte client, votre commande ou votre fiche boutique vendeur, <strong>lorsque vous validez le formulaire concerné</strong>.</li>
        </ul>
        <p>
            Les coordonnées ne sont pas vendues à des tiers publicitaires. Elles peuvent être transmises au vendeur ou au livreur dans la limite nécessaire à l'exécution de la commande ou à l'affichage de votre boutique sur la marketplace.
        </p>
        <p>
            Les coordonnées GPS ou les images capturées sont transmises à nos serveurs uniquement lorsque vous validez l'action dans l'interface (envoi du formulaire, enregistrement du profil, etc.) et sont traitées selon les sections 3 et 4 de la présente politique.
            Vous pouvez révoquer toute autorisation dans les réglages iOS (Réglages &gt; Confidentialité &gt; Service de localisation &gt; COLObanes) ou Android (Paramètres &gt; Applications &gt; COLObanes &gt; Autorisations &gt; Localisation).
        </p>
        <h3>9.5 Mode Web (PWA)</h3>
        <p>
            Si vous utilisez COLObanes via un navigateur en mode installable (PWA), des technologies similaires aux cookies et au cache local peuvent stocker des ressources pour le fonctionnement hors ligne partiel ; aucune donnée bancaire n'y est conservée en clair.
        </p>

        <h2 id="priv-10">10. Cookies et technologies similaires</h2>
        <p>Nous pouvons utiliser :</p>
        <ul>
            <li><strong>Cookies strictement nécessaires</strong> : maintien de session, panier, équilibrage de charge, prévention CSRF ;</li>
            <li><strong>Cookies de préférences</strong> : langue, choix d'affichage ;</li>
            <li><strong>Cookies de mesure d'audience</strong> (si activés et consentis) : statistiques de fréquentation anonymisées ou pseudonymisées ;</li>
            <li><strong>Stockage local / sessionStorage</strong> pour optimiser les performances côté client.</li>
        </ul>
        <p>
            Vous pouvez configurer votre navigateur pour refuser certains cookies non essentiels ; le site peut alors perdre des fonctionnalités (ex. : panier persistant entre sessions).
            Pour l'Union européenne ou d'autres juridictions imposant un consentement préalable aux traceurs non essentiels, COLObanes s'efforce d'afficher un mécanisme de choix conforme lorsque le Service cible ces utilisateurs.
        </p>

        <h2 id="priv-11">11. Communications électroniques et notifications</h2>
        <ul>
            <li><strong>E-mails transactionnels</strong> (confirmation de commande, réinitialisation de mot de passe) : envoyés sur la base de l'exécution du contrat ou de mesures précontractuelles ;</li>
            <li><strong>Newsletters ou offres</strong> : uniquement avec votre accord préalable lorsque la loi l'exige, avec lien de désinscription clair dans chaque message ;</li>
            <li><strong>SMS ou WhatsApp</strong> (si utilisés) : dans le cadre logistique ou avec consentement marketing distinct.</li>
        </ul>

        <h2 id="priv-12">12. Mineurs</h2>
        <p>
            Le Service est destiné aux personnes capables juridiquement de contracter. Les mineurs doivent utiliser la Plateforme sous la <strong>supervision et avec l'accord</strong> de leurs titulaires de l'autorité parentale.
            Nous ne collectons pas sciemment de données à des fins commerciales directes auprès d'enfants sans base légale et autorisation appropriées.
        </p>

        <h2 id="priv-13">13. Vos droits</h2>
        <p>
            Conformément aux principes de protection des données reconnus au Sénégal (notamment la <strong>Loi n° 2008-12 du 25 janvier 2008</strong> d'orientation relative aux données à caractère personnel, dans les conditions et exceptions prévues par les textes d'application)
            et, le cas échéant, au RGPD pour les personnes concernées situées dans l'Union européenne, vous pouvez exercer les droits suivants, sous réserve des exceptions légales :
        </p>
        <ul>
            <li><strong>Droit d'information</strong> : la présente politique et les notices courtes au moment de la collecte ;</li>
            <li><strong>Droit d'accès</strong> : obtenir une copie de vos données traitées ;</li>
            <li><strong>Droit de rectification</strong> : corriger des inexactitudes via votre compte ou par e-mail ;</li>
            <li><strong>Droit à l'effacement (« droit à l'oubli »)</strong> : lorsque le traitement n'est plus nécessaire au regard des finalités, sous réserve des motifs de conservation légale (ex. facturation) ;</li>
            <li><strong>Droit à la limitation</strong> : gel temporaire du traitement en cas de contestation ;</li>
            <li><strong>Droit à la portabilité</strong> : lorsque le traitement est fondé sur le consentement ou le contrat et réalisé par des moyens automatisés — nous fournissons les données dans un format structuré couramment utilisé lorsque c'est techniquement possible ;</li>
            <li><strong>Droit d'opposition</strong> : notamment pour le marketing direct ;</li>
            <li><strong>Retrait du consentement</strong> : lorsque le traitement est fondé sur le consentement, sans affecter la licéité des traitements antérieurs ;</li>
            <li><strong>Directives post-mortem</strong> : selon le droit applicable local quant à la transmission ou la suppression de certaines données.</li>
        </ul>
        <h3 id="priv-suppression">Suppression du compte utilisateur</h3>
        <p>
            Une <strong>procédure détaillée</strong> explique comment demander la fermeture et la suppression des données liées à votre compte client, quelles informations sont effacées ou conservées, et quelles
            <strong>durées de conservation supplémentaires</strong> peuvent s’appliquer (notamment pour la comptabilité et les commandes). Elle précise que
            <strong>la demande doit être formulée par courriel</strong> à
            <a href="mailto:<?php echo htmlspecialchars(SITE_BRAND_CONTACT_EMAIL, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(SITE_BRAND_CONTACT_EMAIL, ENT_QUOTES, 'UTF-8'); ?></a> :
            <a href="/politique-suppression-compte.php"><strong>Politique de suppression de compte</strong></a>.
        </p>
        <p>
            Pour exercer vos droits : écrivez à <a href="mailto:contact@colobanes.com">contact@colobanes.com</a> en joignant une preuve d'identité proportionnée (scan d'identité avec masquage des données non nécessaires si vous le souhaitez).
            Nous nous efforçons de répondre sous <strong>30 jours</strong> ; ce délai peut être prolongé en cas de complexité, avec information préalable.
        </p>

        <h2 id="priv-14">14. Réclamations auprès d'une autorité</h2>
        <p>
            Si vous estimez que vos droits ne sont pas respectés, vous pouvez introduire une réclamation auprès de l'autorité de protection des données compétente.

            Au Sénégal, l'autorité historiquement désignée pour accompagner la mise en œuvre du cadre des données personnelles est la <strong>Commission de protection des données personnelles (CDP)</strong>
            (vérifiez l'adresse et les modalités de saisine à jour sur les sites officiels de l'État du Sénégal).
        </p>

        <h2 id="priv-15">15. Évolution de cette politique</h2>
        <p>
            Nous pouvons modifier cette politique pour refléter l'évolution du Service, des outils technologiques (nouvelle application, nouveau prestataire de paiement) ou du cadre juridique.
            La date de « dernière mise à jour » en tête de page sera ajustée ; pour les changements majeurs, une notification sur le site ou par e-mail pourra être utilisée lorsque cela est possible.
        </p>

        <p class="legal-note">
            Ce document vise une transparence maximale sur nos pratiques. Il ne remplace pas un audit juridique ou une analyse sectorielle spécifique (santé, finance, enfants). Pour toute activité de traitement à haut risque, COLObanes peut réaliser ou faire réaliser une analyse d'impact (PIA / AIPD) conformément aux bonnes pratiques.
        </p>

        <div class="legal-cross">
            <strong>Documents associés :</strong>
            <a href="/politique-suppression-compte.php">Politique de suppression de compte</a>
            ·
            <a href="/conditions-utilisation.php">Conditions générales d'utilisation</a>
            ·
            <a href="/conditions-utilisation.php#cgu-5b">Modération des contenus (CGU)</a>
        </div>

        <a href="javascript:history.back()" class="back-link">
            <i class="fas fa-arrow-left" aria-hidden="true"></i> Retour
        </a>
    </div>

    <?php include('footer.php'); ?>
</body>
</html>
