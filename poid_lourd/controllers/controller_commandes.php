<?php
/**
 * Contrôleur pour la gestion des commandes
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../models/model_commandes.php';
require_once __DIR__ . '/../models/model_panier.php';
require_once __DIR__ . '/../models/model_admin.php';
require_once __DIR__ . '/../includes/boutique_slug_redirect.php';
require_once __DIR__ . '/../includes/db_schema_helpers.php';
require_once __DIR__ . '/../includes/geo_location_service.php';
require_once __DIR__ . '/../includes/commande_mode_helpers.php';

/**
 * Construit la liste produits pour e-mail / notification (sous-ensemble panier).
 */
function commandes_controller_produits_email_for_items(array $panier_items, array $choix) {
    $produits_email = [];
    foreach ($panier_items as $item) {
        $prix_unitaire = (!empty($item['panier_prix_unitaire']) && $item['panier_prix_unitaire'] > 0)
            ? (float) $item['panier_prix_unitaire']
            : (!empty($item['prix_promotion']) && $item['prix_promotion'] < $item['prix'] ? $item['prix_promotion'] : $item['prix']);
        $prix_total_ligne = $prix_unitaire * $item['quantite'];
        $panier_id = isset($item['panier_id']) ? (int) $item['panier_id'] : 0;
        $c = isset($choix[$panier_id]) ? $choix[$panier_id] : [];
        $nom_affichage = $item['nom'];
        if (!empty($item['panier_variante_nom'])) {
            $nom_affichage .= ' → ' . $item['panier_variante_nom'];
        }
        $produits_email[] = [
            'nom' => $nom_affichage,
            'quantite' => $item['quantite'],
            'prix_unitaire' => $prix_unitaire,
            'prix_total' => $prix_total_ligne,
            'variante_nom' => $item['panier_variante_nom'] ?? '',
            'couleur' => isset($c['couleur']) ? $c['couleur'] : ($item['panier_couleur'] ?? ''),
            'poids' => isset($c['poids']) ? $c['poids'] : ($item['panier_poids'] ?? ''),
            'taille' => isset($c['taille']) ? $c['taille'] : ($item['panier_taille'] ?? ''),
            'surcout_poids' => isset($item['panier_surcout_poids']) ? (float) $item['panier_surcout_poids'] : 0,
            'surcout_taille' => isset($item['panier_surcout_taille']) ? (float) $item['panier_surcout_taille'] : 0,
            'boutique' => isset($item['vendeur_boutique_nom']) ? (string) $item['vendeur_boutique_nom'] : '',
        ];
    }
    return $produits_email;
}

function commandes_controller_totaux_for_items(array $panier_items) {
    $sous_total = 0;
    $nombre_articles = 0;
    foreach ($panier_items as $item) {
        $prix_unitaire = (!empty($item['panier_prix_unitaire']) && $item['panier_prix_unitaire'] > 0)
            ? (float) $item['panier_prix_unitaire']
            : (!empty($item['prix_promotion']) && $item['prix_promotion'] < $item['prix'] ? $item['prix_promotion'] : $item['prix']);
        $sous_total += $prix_unitaire * $item['quantite'];
        $nombre_articles += $item['quantite'];
    }
    return [$sous_total, $nombre_articles];
}

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

/**
 * Traite la création d'une commande
 * @return array Tableau avec 'success', 'message', et éventuellement 'commande_id' et 'numero_commande'
 */
function process_create_commande() {
    if (!isset($_SESSION['user_id'])) {
        return [
            'success' => false,
            'message' => 'Vous devez être connecté pour passer une commande.'
        ];
    }
    
    $user_id = $_SESSION['user_id'];

    $limit_vendeur_id = null;
    $boutique_slug_post = trim((string) ($_POST['boutique_slug'] ?? ''));
    if ($boutique_slug_post !== '') {
        $adm_b = resolve_vendeur_by_boutique_slug($boutique_slug_post);
        if ($adm_b) {
            $limit_vendeur_id = (int) $adm_b['id'];
        }
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return [
            'success' => false,
            'message' => 'Méthode non autorisée.'
        ];
    }
    
    $telephone_livraison = trim($_POST['telephone_livraison'] ?? '');

    if (empty($telephone_livraison)) {
        return [
            'success' => false,
            'message' => 'Le téléphone de livraison est obligatoire.'
        ];
    }

    $telephone_livraison = preg_replace('/\s+/', '', $telephone_livraison);
    if ($telephone_livraison !== '' && $telephone_livraison[0] !== '+') {
        $telephone_livraison = '+' . ltrim($telephone_livraison, '0');
    }

    if (!preg_match('/^\+[0-9]{7,15}$/', $telephone_livraison)) {
        return [
            'success' => false,
            'message' => 'Le format du téléphone n\'est pas valide.'
        ];
    }

    $adresse_livraison = trim($_POST['adresse_livraison'] ?? '');
    if ($adresse_livraison !== '') {
        require_once __DIR__ . '/../includes/geo_geocoder.php';
        $adresse_livraison = geo_address_concise_normalize($adresse_livraison);
    }
    $mode_livraison = commande_mode_livraison_normalize($_POST['mode_livraison'] ?? 'livraison');
    $zone_livraison_id = null;
    $frais_livraison = 0;
    $notes = null;

    /* Position exacte du client (champs cachés remplis par le navigateur, facultatif — livraison uniquement) */
    $geo_lat = geo_parse_coord($_POST['geo_lat'] ?? null);
    $geo_lng = geo_parse_coord($_POST['geo_lng'] ?? null);
    $geo_precision = geo_parse_precision($_POST['geo_precision'] ?? null);
    $geo_source = (string) ($_POST['geo_source'] ?? 'gps');
    if (!in_array($geo_source, GEO_SOURCES_COMMANDE, true)) {
        $geo_source = 'gps';
    }
    $geo_disponible = ($mode_livraison === 'livraison') && geo_coords_valid($geo_lat, $geo_lng);

    if ($mode_livraison === 'retrait') {
        $adresse_livraison = '';
    }
    
    $panier_items = get_panier_by_user($user_id);
    if ($limit_vendeur_id !== null) {
        $panier_items = filter_panier_items_by_vendeur($panier_items, $limit_vendeur_id);
    }
    
    if (empty($panier_items)) {
        return [
            'success' => false,
            'message' => 'Votre panier est vide pour cette boutique. Ajoutez des produits avant de passer une commande.'
        ];
    }
    
    // Récupérer les choix couleur, poids, taille par panier_id (priorité: POST, sinon options du panier)
    $choix = [];
    foreach ($panier_items as $item) {
        $panier_id = isset($item['panier_id']) ? (int) $item['panier_id'] : 0;
        if ($panier_id <= 0) continue;

        $couleur = '';
        $poids = '';
        $taille = '';

        if (isset($_POST['choix'][$panier_id]) && is_array($_POST['choix'][$panier_id])) {
            $c = $_POST['choix'][$panier_id];
            $couleur = isset($c['couleur']) ? trim($c['couleur']) : '';
            $poids = isset($c['poids']) ? trim($c['poids']) : '';
            $taille = isset($c['taille']) ? trim($c['taille']) : '';
        }
        // Fallback: utiliser les options du panier (sélectionnées sur la page produit)
        if ($couleur === '' && !empty(trim($item['panier_couleur'] ?? ''))) {
            $couleur = trim($item['panier_couleur']);
        }
        if ($poids === '' && !empty(trim($item['panier_poids'] ?? ''))) {
            $poids = trim($item['panier_poids']);
        }
        if ($taille === '' && !empty(trim($item['panier_taille'] ?? ''))) {
            $taille = trim($item['panier_taille']);
        }

        $choix[$panier_id] = ['couleur' => $couleur, 'poids' => $poids, 'taille' => $taille];
    }
    
    $result = create_marketplace_commandes_from_panier(
        $user_id,
        $panier_items,
        $adresse_livraison,
        $telephone_livraison,
        $notes,
        $zone_livraison_id,
        $frais_livraison,
        $choix,
        $mode_livraison
    );
    
    if ($result === false) {
        return [
            'success' => false,
            'message' => 'Une erreur est survenue lors de la création de la commande. Veuillez réessayer.'
        ];
    }
    
    if ($result['success']) {
        if ($limit_vendeur_id !== null) {
            delete_panier_lines_for_vendeur($user_id, $limit_vendeur_id);
        } else {
            clear_panier($user_id);
        }

        /* Attacher la position exacte du client à chaque commande créée */
        if ($geo_disponible) {
            $cmds_geo = isset($result['commandes']) && is_array($result['commandes']) ? $result['commandes'] : [];
            if (empty($cmds_geo) && !empty($result['commande_id'])) {
                $cmds_geo = [['commande_id' => $result['commande_id']]];
            }
            foreach ($cmds_geo as $cg) {
                if (!empty($cg['commande_id'])) {
                    geo_save_commande_location((int) $cg['commande_id'], $geo_lat, $geo_lng, $geo_precision, $geo_source);
                }
            }
            geo_save_user_last_location((int) $user_id, $geo_lat, $geo_lng, $geo_precision);
            geo_session_set_location($geo_lat, $geo_lng, $geo_precision);
        }

        /* Une notification par boutique (même ordre que create_marketplace_commandes_from_panier) */
        $notifications = [];
        $groups = group_panier_items_by_vendeur($panier_items);
        $idx_cr = 0;
        $commandes_creees = isset($result['commandes']) && is_array($result['commandes']) ? $result['commandes'] : [];
        if (empty($commandes_creees) && !empty($result['numero_commande'])) {
            $commandes_creees = [[
                'commande_id' => isset($result['commande_id']) ? $result['commande_id'] : null,
                'numero_commande' => $result['numero_commande'],
            ]];
        }
        foreach ($groups as $vid => $ginfo) {
            $cr = $commandes_creees[$idx_cr] ?? null;
            if (!$cr || empty($cr['numero_commande'])) {
                break;
            }
            $idx_cr++;
            $subset = $ginfo['items'];
            list($sous_cmd, $nb_art_cmd) = commandes_controller_totaux_for_items($subset);
            /* Les frais sont sur la 1re commande en base ; ici tout est 0 côté port */
            $notifications[] = [
                'vendeur_id' => (int) $vid,
                'commande_id' => isset($cr['commande_id']) ? (int) $cr['commande_id'] : 0,
                'numero_commande' => $cr['numero_commande'],
                'montant_total' => $sous_cmd,
                'nombre_articles' => $nb_art_cmd,
                'telephone_livraison' => $telephone_livraison,
                'adresse_livraison' => $adresse_livraison,
                'produits' => commandes_controller_produits_email_for_items($subset, $choix),
            ];
        }

        list($sous_total, $nombre_articles) = commandes_controller_totaux_for_items($panier_items);
        $montant_total = $sous_total + $frais_livraison;
        $produits_email = commandes_controller_produits_email_for_items($panier_items, $choix);
        $nums = !empty($result['numeros_commandes']) ? $result['numeros_commandes'] : [$result['numero_commande']];
        $numero_affichage = implode(', ', $nums);
        $msg_multi = count($nums) > 1
            ? 'Vos commandes ont été créées avec succès ! Numéros : ' . $numero_affichage
            : 'Votre commande a été créée avec succès ! Numéro de commande: ' . $result['numero_commande'];

        return [
            'success' => true,
            'message' => $msg_multi,
            'commande_id' => $result['commande_id'],
            'numero_commande' => $result['numero_commande'],
            'numeros_commandes' => $nums,
            'notifications' => $notifications,
            'email_data' => [
                'numero_commande' => $numero_affichage,
                'montant_total' => $montant_total,
                'nombre_articles' => $nombre_articles,
                'telephone_livraison' => $telephone_livraison,
                'adresse_livraison' => $adresse_livraison,
                'produits' => $produits_email
            ]
        ];
    }
    
    return [
        'success' => false,
        'message' => 'Une erreur est survenue lors de la création de la commande.'
    ];
}

