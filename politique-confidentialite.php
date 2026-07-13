<?php
require_once __DIR__ . '/includes/session_user.php';
session_start_persistent();

require_once __DIR__ . '/includes/site_url.php';
require_once __DIR__ . '/includes/asset_version.php';

$base = get_site_base_url();
$seo_title = 'Politique de confidentialité — Sugar Paper';
$seo_description = 'Politique de confidentialité Sugar Paper : protection des données, application mobile, suivi de livraison, localisation GPS, import contacts, droits des utilisateurs et conformité App Store / Google Play.';
$seo_canonical = $base . '/politique-confidentialite.php';

$privacy_email = 'sugarpaper26@gmail.com';
$privacy_email_subject = rawurlencode('Données personnelles — Sugar Paper');
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
        <h1><i class="fas fa-shield-alt" aria-hidden="true"></i> Politique de confidentialité et de protection des données</h1>
        <p class="legal-updated"><strong>Dernière mise à jour :</strong> <?php echo htmlspecialchars($last_update, ENT_QUOTES, 'UTF-8'); ?></p>

        <div class="legal-badge-row" aria-hidden="true">
            <span class="legal-badge"><i class="fas fa-store"></i> Site e-commerce</span>
            <span class="legal-badge"><i class="fas fa-mobile-alt"></i> Application mobile</span>
            <span class="legal-badge"><i class="fas fa-truck"></i> Suivi livraison</span>
        </div>

        <p>
            La présente politique décrit comment <strong>Sugar Paper</strong> («&nbsp;nous&nbsp;», «&nbsp;notre&nbsp;», «&nbsp;la Plateforme&nbsp;»), boutique en ligne de produits naturels et de décoration pour gâteaux,
            <strong>collecte</strong>, <strong>utilise</strong>, <strong>conserve</strong>, <strong>partage</strong> et <strong>protège</strong> les informations relatives aux personnes qui utilisent notre site web,
            notre application mobile officielle ou nos services associés (clients, visiteurs, personnel livreur habilité et administrateurs internes).
        </p>
        <p>
            Nous nous engageons à traiter les données de manière <strong>loyale</strong>, <strong>transparente</strong> et <strong>sécurisée</strong>, en limitant la collecte au strict nécessaire.
            <strong>Nous ne vendons pas vos données personnelles</strong> à des tiers (voir sections <a href="#priv-2b">2 bis</a> et <a href="#priv-6-nocom">6.4</a>).
        </p>
        <p>
            L'utilisation de nos services implique la prise de connaissance de cette politique et de nos
            <a href="/conditions-utilisation.php">Conditions générales d'utilisation</a>.
        </p>

        <nav class="legal-toc" aria-label="Sommaire">
            <strong>Sommaire</strong>
            <ol>
                <li><a href="#priv-1">Responsable du traitement et contact</a></li>
                <li><a href="#priv-2">Principes et engagements</a></li>
                <li><a href="#priv-2b">Données, intégrité et non-commercialisation</a></li>
                <li><a href="#priv-3">Données collectées</a></li>
                <li><a href="#priv-4">Finalités et bases légales</a></li>
                <li><a href="#priv-5">Décisions automatisées</a></li>
                <li><a href="#priv-6">Destinataires et sous-traitants</a></li>
                <li><a href="#priv-7">Durées de conservation</a></li>
                <li><a href="#priv-8">Sécurité des données</a></li>
                <li><a href="#priv-9">Application mobile et permissions</a></li>
                <li><a href="#priv-9-gps">Localisation GPS et suivi livraison</a></li>
                <li><a href="#priv-9-contacts">Import contacts (carnet clients)</a></li>
                <li><a href="#priv-10">Cookies et stockage local</a></li>
                <li><a href="#priv-11">Communications et notifications</a></li>
                <li><a href="#priv-12">Mineurs</a></li>
                <li><a href="#priv-13">Vos droits</a></li>
                <li><a href="#priv-suppression">Suppression de compte</a></li>
                <li><a href="#priv-14">Réclamations auprès d'une autorité</a></li>
                <li><a href="#priv-15">Évolution de la politique</a></li>
            </ol>
        </nav>

        <h2 id="priv-1">1. Responsable du traitement et contact</h2>
        <p>
            Le <strong>responsable du traitement</strong> des données personnelles collectées via la Plateforme Sugar Paper est l'entité exploitant le service sous la marque <strong>Sugar Paper</strong>.
        </p>
        <ul>
            <li><strong>Marque commerciale</strong> : Sugar Paper ;</li>
            <li><strong>Adresse</strong> : <?php echo htmlspecialchars($company_address, ENT_QUOTES, 'UTF-8'); ?> ;</li>
            <li><strong>Courriel vie privée / support</strong> : <a href="mailto:<?php echo htmlspecialchars($privacy_email, ENT_QUOTES, 'UTF-8'); ?>?subject=<?php echo $privacy_email_subject; ?>"><?php echo htmlspecialchars($privacy_email, ENT_QUOTES, 'UTF-8'); ?></a> (objet recommandé&nbsp;: «&nbsp;Données personnelles&nbsp;») ;</li>
            <li><strong>Téléphone</strong> : <a href="tel:+221774161212">+221 77 416 12 12</a>, <a href="tel:+221773292123">+221 77 32 92 123</a>, <a href="tel:+221338233514">+221 33 823 35 14</a>.</li>
        </ul>
        <p>
            Pour toute question relative à vos données ou à l'exercice de vos droits, contactez-nous aux coordonnées ci-dessus.
            Nous centralisons les demandes et nous efforçons d'y répondre dans un délai raisonnable (voir section&nbsp;13).
        </p>

        <h2 id="priv-2">2. Principes et engagements</h2>
        <p>Nous appliquons notamment les principes suivants&nbsp;:</p>
        <ul>
            <li><strong>Licéité, loyauté et transparence</strong> — chaque traitement repose sur une base légale identifiée et vous est expliqué ;</li>
            <li><strong>Minimisation</strong> — nous ne collectons que les données adéquates et nécessaires aux finalités décrites ;</li>
            <li><strong>Exactitude</strong> — vous pouvez mettre à jour vos informations depuis votre compte ou sur demande ;</li>
            <li><strong>Limitation de la conservation</strong> — les données ne sont pas conservées au-delà des durées indiquées (section&nbsp;7), sauf obligation légale ;</li>
            <li><strong>Intégrité et confidentialité</strong> — mesures techniques et organisationnelles contre l'accès non autorisé (section&nbsp;8) ;</li>
            <li><strong>Responsabilisation</strong> — contrôle des accès internes, clauses de confidentialité avec les prestataires et sensibilisation des équipes habilitées.</li>
        </ul>

        <h2 id="priv-2b">2 bis. Priorité aux données, intégrité et non-commercialisation</h2>
        <p>
            Cette section répond aux exigences de transparence des plateformes <strong>Apple App Store</strong> et <strong>Google Play</strong> en matière de confidentialité.
        </p>

        <h3>2 bis.1 Aucune vente de vos données</h3>
        <p>
            Sugar Paper <strong>ne vend pas</strong>, <strong>ne loue pas</strong> et <strong>ne cède pas</strong> vos données personnelles à des tiers à des fins de marketing, de profilage publicitaire ou de monétisation de bases de données.
            Nous ne partageons pas vos coordonnées, votre historique d'achat ou vos données de localisation avec des courtiers en données ou des réseaux publicitaires tiers.
        </p>

        <h3>2 bis.2 Finalité limitée</h3>
        <p>
            Chaque donnée sert une finalité déterminée (commande, livraison, compte, sécurité, support, obligations légales).
            Nous ne réutilisons pas vos données à des fins incompatibles, sauf avec votre consentement lorsque la loi l'exige.
        </p>

        <h3>2 bis.3 Vos choix et contrôle</h3>
        <p>
            Vous pouvez modifier vos données, refuser certaines autorisations (caméra, localisation, contacts, notifications) dans les réglages de votre appareil,
            vous opposer au marketing direct et demander la suppression de votre compte (sections <a href="#priv-13">13</a> et <a href="#priv-suppression">14</a>).
            L'accès au catalogue ne dépend pas de l'acceptation de traitements non essentiels.
        </p>

        <h2 id="priv-3">3. Données collectées</h2>

        <h3>3.1 Compte client et identification</h3>
        <ul>
            <li>Nom, prénom ;</li>
            <li>Adresse e-mail (identifiant de connexion) ;</li>
            <li>Numéro de téléphone ;</li>
            <li>Mot de passe stocké sous forme <strong>hachée</strong> (nous ne conservons pas le mot de passe en clair) ;</li>
            <li>Éventuellement photo de profil si vous en téléversez une.</li>
        </ul>

        <h3>3.2 Commandes, livraison et suivi</h3>
        <ul>
            <li>Adresse de livraison, zone ou commune ;</li>
            <li>Coordonnées GPS (latitude, longitude, précision) lorsque vous utilisez «&nbsp;Localiser&nbsp;» ou «&nbsp;Mettre à jour ma position&nbsp;» ;</li>
            <li>Détails des commandes (produits, quantités, personnalisations, montants) ;</li>
            <li>Statut de commande et historique ;</li>
            <li>Instructions de livraison (étage, code d'accès, etc.) ;</li>
            <li>Pour le <strong>suivi de livraison en temps réel</strong>&nbsp;: position du livreur pendant une course active, transmise au client via la page de suivi ou un lien partagé (section&nbsp;9.4).</li>
        </ul>

        <h3>3.3 Paiement</h3>
        <p>
            Selon le moyen de paiement, nous traitons des <strong>métadonnées de transaction</strong> (montant en FCFA, statut, identifiant de transaction, moyen générique).
            Les données bancaires sensibles sont en principe traitées par des prestataires sécurisés ; nous ne vous demandons jamais par e-mail non sécurisé votre code de carte ou votre mot de passe.
        </p>

        <h3>3.4 Données techniques et journaux</h3>
        <ul>
            <li>Adresse IP, horodatage, type de navigateur ou d'application (User-Agent) ;</li>
            <li>Identifiant de session, jetons d'authentification ;</li>
            <li>Sur l'application mobile&nbsp;: modèle d'appareil, version du système (Android / iOS), jeton de notification push (Firebase Cloud Messaging).</li>
        </ul>

        <h3>3.5 Connexion via comptes tiers</h3>
        <p>
            Si vous utilisez la connexion Google ou Apple, nous recevons les informations que <strong>vous autorisez</strong> via ce service (identifiant technique, e-mail, nom) pour créer ou associer votre compte Sugar Paper.
        </p>

        <h3>3.6 Contacts importés (espace commercial)</h3>
        <p>
            Lorsque un utilisateur habilité (administrateur / commercial) utilise la fonction «&nbsp;Importer&nbsp;» dans l'application mobile ou via un fichier,
            nous pouvons enregistrer dans le <strong>carnet clients</strong> de l'entreprise&nbsp;:
            nom, prénom, numéro de téléphone et, le cas échéant, adresse e-mail des contacts <strong>explicitement sélectionnés</strong>.
            Le répertoire du téléphone n'est pas lu en continu ni en arrière-plan&nbsp;; seuls les contacts choisis lors de l'import sont transmis au serveur.
            Voir section&nbsp;<a href="#priv-9-contacts">9.5</a>.
        </p>

        <h2 id="priv-4">4. Finalités et bases légales du traitement</h2>
        <div class="legal-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Finalité</th>
                        <th>Exemples de données</th>
                        <th>Base légale (synthèse)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Création et gestion du compte</td>
                        <td>Identité, e-mail, mot de passe haché</td>
                        <td>Exécution du contrat / mesures précontractuelles</td>
                    </tr>
                    <tr>
                        <td>Commande et livraison</td>
                        <td>Adresse, panier, statut, échanges logistiques</td>
                        <td>Exécution du contrat de vente</td>
                    </tr>
                    <tr>
                        <td>Localisation client (adresse)</td>
                        <td>Latitude, longitude, adresse dérivée</td>
                        <td>Exécution du contrat ; consentement via l'autorisation système lors de l'action «&nbsp;Localiser&nbsp;»</td>
                    </tr>
                    <tr>
                        <td>Suivi GPS livreur en course</td>
                        <td>Position du livreur, horodatage, précision</td>
                        <td>Exécution du contrat (livraison) ; intérêt légitime du client à suivre sa commande ; consentement du livreur via autorisation «&nbsp;Toujours&nbsp;» / arrière-plan lors du démarrage explicite d'une livraison</td>
                    </tr>
                    <tr>
                        <td>Paiement et prévention fraude</td>
                        <td>Métadonnées de transaction, journaux de sécurité</td>
                        <td>Exécution du contrat ; obligation légale ; intérêt légitime</td>
                    </tr>
                    <tr>
                        <td>Support client</td>
                        <td>Historique des échanges, commandes concernées</td>
                        <td>Exécution du contrat ; intérêt légitime</td>
                    </tr>
                    <tr>
                        <td>Obligations comptables et fiscales</td>
                        <td>Factures, justificatifs</td>
                        <td>Obligation légale</td>
                    </tr>
                    <tr>
                        <td>Sécurité du site et détection d'abus</td>
                        <td>Adresse IP, journaux serveur</td>
                        <td>Intérêt légitime ; obligation légale le cas échéant</td>
                    </tr>
                    <tr>
                        <td>Notifications push (application)</td>
                        <td>Jeton FCM/APNs, préférences</td>
                        <td>Consentement (autorisation système et/ou in-app)</td>
                    </tr>
                    <tr>
                        <td>Import contacts (carnet clients)</td>
                        <td>Nom, prénom, téléphone, e-mail des contacts sélectionnés</td>
                        <td>Consentement via écran explicatif in-app puis autorisation système ; intérêt légitime de l'entreprise à gérer son carnet clients B2B / commercial</td>
                    </tr>
                    <tr>
                        <td>Prospection commerciale</td>
                        <td>E-mail, historique d'opt-in</td>
                        <td>Consentement préalable lorsque requis ; droit d'opposition</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <h2 id="priv-5">5. Décisions automatisées et profilage</h2>
        <p>
            Sugar Paper peut appliquer des règles automatisées à finalité non contraignante (détection de commandes anormales, classement de produits).
            Aucune décision produisant des effets juridiques significatifs ne vous est imposée sans possibilité d'intervention humaine, sauf obligation légale.
            Pour contester une décision, contactez <a href="mailto:<?php echo htmlspecialchars($privacy_email, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($privacy_email, ENT_QUOTES, 'UTF-8'); ?></a>.
        </p>

        <h2 id="priv-6">6. Destinataires, sous-traitants et transferts</h2>

        <h3>6.1 Accès internes</h3>
        <p>
            Seul le personnel autorisé (support, logistique, comptabilité, technique) accède aux données, dans la limite du besoin d'en connaissance, avec authentification individuelle lorsque possible.
        </p>

        <h3>6.2 Prestataires et sous-traitants</h3>
        <p>Vos données peuvent être traitées par des prestataires agissant pour notre compte, notamment&nbsp;:</p>
        <ul>
            <li><strong>Hébergement web</strong> et infrastructure serveur ;</li>
            <li><strong>Firebase (Google)</strong> — notifications push, authentification Google ;</li>
            <li><strong>Apple</strong> — notifications push (APNs), connexion Sign in with Apple ;</li>
            <li><strong>Services cartographiques / géocodage</strong> — conversion coordonnées ↔ adresse ;</li>
            <li><strong>Prestataires de livraison</strong> ou livreurs habilités — exécution de la livraison ;</li>
            <li><strong>Prestataires de paiement</strong> — traitement sécurisé des transactions.</li>
        </ul>
        <p>
            Ces prestataires sont contractuellement tenus de protéger vos données et de ne les utiliser que selon nos instructions, dans la limite de leurs propres politiques de confidentialité lorsque vous interagissez directement avec eux (Google, Apple).
        </p>

        <h3>6.3 Transferts hors du Sénégal</h3>
        <p>
            Certains sous-traitants (Google Firebase, Apple) peuvent traiter des données sur des serveurs situés hors du Sénégal, notamment aux États-Unis ou en Union européenne.
            Nous veillons à ce que des garanties appropriées soient en place (clauses contractuelles types, certifications le cas échéant).
        </p>

        <h3 id="priv-6-nocom">6.4 Pas de revente à des tiers</h3>
        <p>
            Nous ne vendons, ne louons et ne cédons pas vos données personnelles à des annonceurs, courtiers en données ou plateformes d'enrichissement de bases à des fins commerciales indépendantes de Sugar Paper.
        </p>

        <h3>6.5 Autorités</h3>
        <p>
            Nous pouvons communiquer des données aux autorités compétentes lorsque la loi l'exige ou en réponse à une demande légale valide.
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
                        <td>Compte client actif</td>
                        <td>Tant que le compte existe, puis suppression ou anonymisation après demande ou inactivité prolongée (sous réserve des obligations légales)</td>
                    </tr>
                    <tr>
                        <td>Commandes et facturation</td>
                        <td>Durée légale de conservation comptable et fiscale applicable au Sénégal</td>
                    </tr>
                    <tr>
                        <td>Positions GPS livreur (suivi temps réel)</td>
                        <td>Durée de la livraison active et période technique de purge ultérieure (historique limité aux besoins de preuve et support)</td>
                    </tr>
                    <tr>
                        <td>Jetons de suivi public (lien partagé client)</td>
                        <td>Durée limitée à la livraison concernée, puis expiration ou invalidation</td>
                    </tr>
                    <tr>
                        <td>Journaux techniques / sécurité</td>
                        <td>Durée proportionnée (généralement quelques mois), sauf incident en cours d'investigation</td>
                    </tr>
                    <tr>
                        <td>Jetons notification push</td>
                        <td>Tant que l'application est installée et les notifications activées, ou jusqu'à désinstallation / révocation</td>
                    </tr>
                    <tr>
                        <td>Contacts importés (carnet clients)</td>
                        <td>Tant que le compte administrateur / l'entreprise conserve le carnet clients, ou jusqu'à suppression manuelle du contact ; les doublons de numéro ne sont pas réimportés</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <h2 id="priv-8">8. Sécurité des données</h2>
        <p>Nous mettons en œuvre des mesures appropriées, notamment&nbsp;:</p>
        <ul>
            <li>Chiffrement des communications (HTTPS/TLS) ;</li>
            <li>Hachage sécurisé des mots de passe ;</li>
            <li>Contrôle d'accès et principe du moindre privilège pour les comptes internes ;</li>
            <li>Requêtes préparées et protections contre les injections SQL ;</li>
            <li>Protection CSRF sur les formulaires sensibles ;</li>
            <li>Sauvegardes et procédures de restauration ;</li>
            <li>Surveillance des accès et journalisation des opérations sensibles lorsque la technique le permet.</li>
        </ul>
        <p>
            En cas de violation de données susceptible d'affecter vos droits, nous nous efforcerons de vous informer et, le cas échéant, l'autorité compétente, conformément au droit applicable.
        </p>

        <h2 id="priv-9">9. Application mobile Sugar Paper (iOS et Android)</h2>

        <h3>9.1 Description de l'application</h3>
        <p>
            L'application mobile officielle <strong>Sugar Paper</strong> (identifiant iOS&nbsp;: <strong>com.sugarpaper.app</strong>, package Android&nbsp;: <strong>com.sugarpaper.app</strong>)
            charge notre site e-commerce dans une interface sécurisée (WebView) et expose, sur demande explicite ou dans des cas décrits ci-dessous, des fonctions natives&nbsp;:
            prise de photo, localisation GPS, notifications push, partage système, connexion Google / Apple et, pour l'espace commercial, <strong>import de contacts</strong>.
        </p>
        <p>
            L'application <strong>ne collecte pas</strong> de données via la caméra, la galerie, le GPS ou le répertoire de contacts sans action de votre part
            (bouton «&nbsp;Localiser&nbsp;», «&nbsp;Prendre une photo&nbsp;», «&nbsp;Importer&nbsp;» dans l'espace commercial, démarrage d'une livraison par un livreur habilité, etc.)
            ni sans l'autorisation affichée par iOS ou Android. Avant la demande système, un <strong>écran explicatif</strong> rappelle la finalité de l'autorisation.
        </p>

        <h3>9.2 Notifications push (Firebase Cloud Messaging)</h3>
        <p>
            Si vous acceptez les notifications, un <strong>jeton technique</strong> est attribué par Firebase (Google) et, sur iOS, relayé via Apple Push Notification service (APNs).
            Ce jeton sert à vous adresser des alertes liées au Service (confirmation de commande, statut de livraison, messages support si sollicité).
            Vous pouvez désactiver les notifications dans les réglages de l'appareil ou en désinstallant l'application.
        </p>

        <h3>9.3 Tableau des permissions</h3>
        <p>Conformément aux exigences Apple (ligne directrice 5.1.1) et Google Play&nbsp;:</p>
        <div class="legal-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Permission</th>
                        <th>Finalité</th>
                        <th>Exemple</th>
                        <th>Obligatoire&nbsp;?</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Caméra</strong></td>
                        <td>Photographier un produit ou une image de profil</td>
                        <td>Joindre une photo à une commande personnalisée</td>
                        <td>Non</td>
                    </tr>
                    <tr>
                        <td><strong>Photothèque (lecture)</strong></td>
                        <td>Importer une image existante</td>
                        <td>Choisir une photo depuis la galerie</td>
                        <td>Non</td>
                    </tr>
                    <tr>
                        <td><strong>Localisation (pendant l'utilisation)</strong></td>
                        <td>Confirmer une adresse de livraison ou d'inscription</td>
                        <td>Préremplir votre adresse sur la carte lors d'une commande</td>
                        <td>Non — saisie manuelle possible</td>
                    </tr>
                    <tr>
                        <td><strong>Localisation (arrière-plan / Toujours)</strong></td>
                        <td><strong>Uniquement pour les livreurs habilités</strong>, pendant une livraison active démarrée explicitement</td>
                        <td>Transmettre la position au client qui suit sa commande en direct</td>
                        <td>Non — réservé au personnel livreur ; refus possible (suivi livraison indisponible)</td>
                    </tr>
                    <tr>
                        <td><strong>Notifications</strong></td>
                        <td>Alertes de commande et livraison</td>
                        <td>«&nbsp;Votre commande est en route&nbsp;»</td>
                        <td>Non</td>
                    </tr>
                    <tr>
                        <td><strong>Contacts (répertoire)</strong></td>
                        <td><strong>Espace commercial / admin uniquement</strong>&nbsp;: importer des clients dans le carnet (nom, téléphone, e-mail)</td>
                        <td>Appuyer sur «&nbsp;Importer&nbsp;» puis sélectionner les contacts à enregistrer</td>
                        <td>Non — import fichier .vcf / .csv possible ; refus limite uniquement l'import depuis le répertoire</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p>
            <strong>Microphone&nbsp;:</strong> l'application <strong>ne demande pas</strong> l'accès au microphone et n'enregistre pas d'audio.
        </p>

        <h2 id="priv-9-gps">9.4 Localisation GPS et suivi de livraison en temps réel</h2>

        <h3>9.4.1 Clients — confirmation d'adresse</h3>
        <p>
            Lorsque vous appuyez sur «&nbsp;Localiser&nbsp;» ou «&nbsp;Mettre à jour ma position&nbsp;», l'application ou le site peut lire votre position GPS,
            la convertir en adresse lisible (géocodage inverse) et l'enregistrer <strong>lorsque vous validez le formulaire</strong>.
            Ces coordonnées ne sont pas vendues à des tiers publicitaires.
        </p>

        <h3>9.4.2 Livreurs — suivi pendant une course active</h3>
        <p>
            Les membres du personnel habilités (comptes livreur / administrateurs avec rôle livreur) peuvent activer le <strong>suivi GPS en temps réel</strong> lorsqu'ils démarrent explicitement une livraison depuis l'interface dédiée.
            Dans ce cas&nbsp;:
        </p>
        <ul>
            <li>La position est collectée périodiquement (latitude, longitude, précision, horodatage) ;</li>
            <li>Elle est transmise à nos serveurs et diffusée en temps réel au client via la page de suivi ou un <strong>lien sécurisé partagé</strong> ;</li>
            <li>Sur Android, une <strong>notification persistante</strong> indique que la livraison est en cours (exigence système pour la localisation en arrière-plan) ;</li>
            <li>Sur iOS, un indicateur système signale l'utilisation de la localisation en arrière-plan ;</li>
            <li>Le suivi <strong>s'arrête automatiquement</strong> lorsque le livreur termine la livraison ou en démarre une autre ;</li>
            <li>Le suivi <strong>ne continue pas</strong> en dehors d'une livraison active démarrée par le livreur.</li>
        </ul>
        <p>
            Les clients qui reçoivent un lien de suivi voient la position du livreur en direct uniquement pour la commande concernée et pendant la durée de validité du lien.
            Ils ne reçoivent pas d'accès à d'autres données du livreur (nom complet, téléphone personnel) au-delà de ce qui est affiché dans l'interface de suivi.
        </p>

        <h3>9.4.3 Révocation</h3>
        <p>
            Vous pouvez révoquer les autorisations de localisation à tout moment&nbsp;:
            <strong>iOS</strong> — Réglages &gt; Confidentialité et sécurité &gt; Service de localisation &gt; Sugar Paper ;
            <strong>Android</strong> — Paramètres &gt; Applications &gt; Sugar Paper &gt; Autorisations &gt; Localisation.
            Pour les livreurs, la révocation empêche le suivi en direct mais n'affecte pas les autres fonctions du site accessibles sans GPS.
        </p>

        <h2 id="priv-9-contacts">9.5 Import de contacts (carnet clients)</h2>
        <p>
            La fonction d'import de contacts est destinée aux <strong>utilisateurs habilités de l'espace commercial / administrateur</strong>
            (gestion de devis, factures et carnet clients). Elle n'est pas utilisée pour la navigation catalogue client grand public.
        </p>
        <h3>9.5.1 Quand l'accès est demandé</h3>
        <p>
            Uniquement lorsque vous appuyez sur «&nbsp;Importer&nbsp;» puis «&nbsp;Depuis le téléphone&nbsp;».
            Avant la boîte de dialogue système iOS / Android, l'application affiche un <strong>écran explicatif</strong> précisant la finalité
            (comme pour la localisation ou la caméra). Vous pouvez refuser («&nbsp;Plus tard&nbsp;») et utiliser un fichier <strong>.vcf</strong> ou <strong>.csv</strong> à la place.
        </p>
        <h3>9.5.2 Données concernées</h3>
        <ul>
            <li>Nom et prénom (ou nom d'affichage du contact) ;</li>
            <li>Numéro de téléphone (obligatoire pour l'enregistrement) ;</li>
            <li>Adresse e-mail si présente sur le contact sélectionné.</li>
        </ul>
        <p>
            Les contacts <strong>non sélectionnés</strong> ne sont pas transmis au serveur.
            Les numéros déjà présents dans le carnet clients sont ignorés (pas de doublon).
            Aucune synchronisation continue du répertoire n'est effectuée.
        </p>
        <h3>9.5.3 Révocation</h3>
        <p>
            Vous pouvez révoquer l'accès aux contacts à tout moment&nbsp;:
            <strong>iOS</strong> — Réglages &gt; Confidentialité et sécurité &gt; Contacts &gt; Sugar Paper ;
            <strong>Android</strong> — Paramètres &gt; Applications &gt; Sugar Paper &gt; Autorisations &gt; Contacts.
            La révocation n'efface pas les contacts déjà importés dans le carnet clients (suppression manuelle possible dans l'interface).
        </p>

        <h3 id="priv-9-pwa">9.6 Site web et PWA</h3>
        <p>
            Le site peut être utilisé via navigateur ou en mode installable (PWA). Des cookies et stockages locaux assurent le panier, la session et les préférences.
            Aucune donnée bancaire complète n'est stockée en clair côté navigateur.
            Sur navigateur mobile (hors application), l'import depuis le répertoire peut reposer sur l'API Contact Picker (souvent limitée à Android Chrome en HTTPS)
            ou sur un fichier exporté.
        </p>

        <h2 id="priv-10">10. Cookies et technologies similaires</h2>
        <ul>
            <li><strong>Cookies strictement nécessaires</strong> — session, panier, sécurité (CSRF) ;</li>
            <li><strong>Cookies de préférences</strong> — choix d'affichage, langue ;</li>
            <li><strong>Stockage local / sessionStorage</strong> — performance et fonctionnement de l'interface ;</li>
            <li><strong>Cookies de mesure d'audience</strong> — le cas échéant, avec consentement lorsque requis par la loi.</li>
        </ul>
        <p>
            Vous pouvez configurer votre navigateur pour refuser certains cookies non essentiels ; certaines fonctionnalités (panier persistant) peuvent alors être limitées.
        </p>

        <h2 id="priv-11">11. Communications électroniques et notifications</h2>
        <ul>
            <li><strong>E-mails transactionnels</strong> (confirmation de commande, réinitialisation de mot de passe) — exécution du contrat ;</li>
            <li><strong>Notifications push</strong> — avec votre consentement via l'application ou les réglages système ;</li>
            <li><strong>Offres promotionnelles</strong> — uniquement avec votre accord préalable lorsque la loi l'exige, avec lien de désinscription ;</li>
            <li><strong>SMS / WhatsApp</strong> — le cas échéant, pour la logistique ou avec consentement marketing distinct.</li>
        </ul>

        <h2 id="priv-12">12. Mineurs</h2>
        <p>
            Le Service s'adresse aux personnes capables juridiquement de contracter.
            Nous ne collectons pas sciemment de données auprès d'enfants de moins de <strong>13 ans</strong> à des fins commerciales directes.
            Si nous apprenons qu'un enfant a fourni des données sans consentement parental valable, nous prendrons des mesures pour les supprimer dans les meilleurs délais.
            Les mineurs doivent utiliser le Service sous la supervision d'un adulte responsable.
        </p>

        <h2 id="priv-13">13. Vos droits</h2>
        <p>
            Conformément aux principes reconnus au Sénégal (notamment la <strong>Loi n°&nbsp;2008-12 du 25 janvier 2008</strong> relative aux données à caractère personnel)
            et, le cas échéant, au RGPD pour les personnes situées dans l'Union européenne, vous pouvez exercer les droits suivants, sous réserve des exceptions légales&nbsp;:
        </p>
        <ul>
            <li><strong>Droit d'information</strong> — la présente politique et les notices au moment de la collecte ;</li>
            <li><strong>Droit d'accès</strong> — obtenir une copie de vos données ;</li>
            <li><strong>Droit de rectification</strong> — corriger des inexactitudes ;</li>
            <li><strong>Droit à l'effacement</strong> — lorsque le traitement n'est plus nécessaire, sous réserve des obligations légales ;</li>
            <li><strong>Droit à la limitation</strong> — gel temporaire en cas de contestation ;</li>
            <li><strong>Droit à la portabilité</strong> — format structuré couramment utilisé, lorsque techniquement possible ;</li>
            <li><strong>Droit d'opposition</strong> — notamment au marketing direct ;</li>
            <li><strong>Retrait du consentement</strong> — sans affecter la licéité des traitements antérieurs.</li>
        </ul>
        <p>
            Pour exercer vos droits, écrivez à
            <a href="mailto:<?php echo htmlspecialchars($privacy_email, ENT_QUOTES, 'UTF-8'); ?>?subject=<?php echo $privacy_email_subject; ?>"><?php echo htmlspecialchars($privacy_email, ENT_QUOTES, 'UTF-8'); ?></a>
            en précisant votre demande et, si nécessaire, une preuve d'identité proportionnée.
            Nous nous efforçons de répondre sous <strong>30 jours</strong> ; ce délai peut être prolongé en cas de complexité, avec information préalable.
        </p>

        <h2 id="priv-suppression">14. Suppression de compte</h2>
        <p>
            Vous pouvez demander la <strong>fermeture de votre compte client</strong> et la suppression des données associées en envoyant un e-mail à
            <a href="mailto:<?php echo htmlspecialchars($privacy_email, ENT_QUOTES, 'UTF-8'); ?>?subject=<?php echo rawurlencode('Suppression de compte — Sugar Paper'); ?>"><?php echo htmlspecialchars($privacy_email, ENT_QUOTES, 'UTF-8'); ?></a>
            depuis l'adresse e-mail liée à votre compte, avec l'objet «&nbsp;Suppression de compte&nbsp;».
        </p>
        <p>Nous vous informerons des données supprimées et de celles que nous devons conserver pour des obligations légales (facturation, comptabilité, litiges), ainsi que de leur durée de conservation.</p>
        <p>
            La suppression du compte n'annule pas les commandes en cours ni les obligations contractuelles déjà nées.
            Les données de suivi GPS liées à une livraison terminée peuvent être conservées temporairement à des fins de preuve et support, puis purgées selon la section&nbsp;7.
        </p>

        <h2 id="priv-14">15. Réclamations auprès d'une autorité</h2>
        <p>
            Si vous estimez que vos droits ne sont pas respectés, vous pouvez introduire une réclamation auprès de l'autorité compétente.
            Au Sénégal, il s'agit notamment de la <strong>Commission de protection des données personnelles (CDP)</strong>
            — consultez les coordonnées et modalités de saisine à jour sur les sites officiels de l'État du Sénégal.
        </p>
        <p>
            Nous vous invitons toutefois à nous contacter préalablement afin que nous puissions traiter votre demande directement.
        </p>

        <h2 id="priv-15">16. Évolution de cette politique</h2>
        <p>
            Nous pouvons modifier cette politique pour refléter l'évolution du Service (nouvelles fonctionnalités, prestataires, application mobile) ou du cadre juridique.
            La date de «&nbsp;dernière mise à jour&nbsp;» en tête de page sera ajustée.
            Pour les changements majeurs, une notification sur le site ou par e-mail pourra être utilisée lorsque cela est possible.
        </p>

        <p class="legal-note">
            Ce document vise une transparence maximale conforme aux exigences des stores Apple et Google.
            Il ne remplace pas un audit juridique personnalisé. Pour toute question, contactez-nous à
            <a href="mailto:<?php echo htmlspecialchars($privacy_email, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($privacy_email, ENT_QUOTES, 'UTF-8'); ?></a>.
        </p>

        <div class="legal-cross">
            <strong>Documents associés :</strong>
            <a href="/conditions-utilisation.php">Conditions générales d'utilisation</a>
            ·
            <a href="#priv-suppression">Suppression de compte</a>
            ·
            <a href="#priv-9-gps">Suivi GPS livraison</a>
            ·
            <a href="#priv-9-contacts">Import contacts</a>
        </div>

        <a href="javascript:history.back()" class="back-link">
            <i class="fas fa-arrow-left" aria-hidden="true"></i> Retour
        </a>
    </article>

    <?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
