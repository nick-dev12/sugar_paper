<?php
/**
 * Contrôleur pour la gestion des commandes
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../models/model_commandes.php';
require_once __DIR__ . '/../models/model_panier.php';
require_once __DIR__ . '/../models/model_zones_livraison.php';
require_once __DIR__ . '/../includes/panier_invite.php';
require_once __DIR__ . '/../includes/guest_client.php';
require_once __DIR__ . '/../includes/geo_location.php';

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

/**
 * Traite la création d'une commande
 * @return array Tableau avec 'success', 'message', et éventuellement 'commande_id' et 'numero_commande'
 */
function process_create_commande() {
    $user_connecte = isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0;

    if (!$user_connecte) {
        return [
            'success' => false,
            'message' => 'Connectez-vous avec votre numéro et votre code PIN pour finaliser la commande.',
        ];
    }

    $user_id = (int) $_SESSION['user_id'];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return [
            'success' => false,
            'message' => 'Méthode non autorisée.'
        ];
    }

    $zone_livraison_id = isset($_POST['zone_livraison_id']) ? (int) $_POST['zone_livraison_id'] : 0;
    $telephone_livraison = trim($_POST['telephone_livraison'] ?? '');
    $mode_livraison = commande_mode_livraison_normalize($_POST['mode_livraison'] ?? 'livraison');

    $geo_lat = geo_parse_coord($_POST['geo_lat'] ?? null);
    $geo_lng = geo_parse_coord($_POST['geo_lng'] ?? null);
    $geo_precision = geo_parse_precision($_POST['geo_precision'] ?? null);
    $geo_source = trim((string) ($_POST['geo_source'] ?? 'gps'));
    if (!in_array($geo_source, GEO_SOURCES_COMMANDE, true)) {
        $geo_source = 'gps';
    }
    $geo_address = trim((string) ($_POST['geo_address'] ?? ''));

    if (empty($telephone_livraison) && !empty($_SESSION['user_telephone'])) {
        $telephone_livraison = trim((string) $_SESSION['user_telephone']);
    }

    if (empty($telephone_livraison)) {
        return [
            'success' => false,
            'message' => 'Le téléphone de livraison est obligatoire.'
        ];
    }

    if (!preg_match('/^[0-9+\s\-()]+$/', $telephone_livraison)) {
        return [
            'success' => false,
            'message' => 'Le format du téléphone n\'est pas valide.'
        ];
    }

    $adresse_livraison = 'À définir';
    $frais_livraison = 0;

    if ($mode_livraison === 'retrait') {
        $zones_actives = get_all_zones_livraison('actif');
        $zone_retrait = zones_livraison_find_retrait($zones_actives);
        if ($zone_retrait) {
            $zone_livraison_id = (int) $zone_retrait['id'];
            $adresse_livraison = trim($zone_retrait['ville'] . ' - ' . $zone_retrait['quartier']);
            $frais_livraison = (float) $zone_retrait['prix_livraison'];
        } else {
            require_once __DIR__ . '/../includes/commande_mode_helpers.php';
            $zone_livraison_id = null;
            $adresse_livraison = commande_retrait_fallback_adresse();
            $frais_livraison = 0;
        }
    } elseif ($zone_livraison_id > 0) {
        $zone = get_zone_livraison_by_id($zone_livraison_id);
        if (!$zone || $zone['statut'] !== 'actif') {
            return [
                'success' => false,
                'message' => 'La zone de livraison sélectionnée n\'est pas valide.'
            ];
        }
        $zone_retrait_check = zones_livraison_find_retrait(get_all_zones_livraison('actif'));
        if ($zone_retrait_check && (int) $zone['id'] === (int) $zone_retrait_check['id']) {
            return [
                'success' => false,
                'message' => 'Veuillez choisir une zone de livraison à domicile ou l\'option retrait sur place.'
            ];
        }
        if (!geo_coords_valid($geo_lat, $geo_lng)) {
            return [
                'success' => false,
                'message' => 'Votre position GPS est obligatoire pour une livraison à domicile. Autorisez la géolocalisation ou actualisez votre position.'
            ];
        }
        $zone_label = trim($zone['ville'] . ' - ' . $zone['quartier']);
        if ($geo_address !== '') {
            $adresse_livraison = $geo_address . ' (' . $zone_label . ')';
        } else {
            $adresse_livraison = geo_reverse_geocode_label($geo_lat, $geo_lng);
            if ($adresse_livraison === '' || strpos($adresse_livraison, 'Position GPS') === 0) {
                $adresse_livraison = sprintf('Position GPS : %.6f, %.6f', $geo_lat, $geo_lng);
            }
            $adresse_livraison .= ' (' . $zone_label . ')';
        }
        $frais_livraison = (float) $zone['prix_livraison'];
    } else {
        return [
            'success' => false,
            'message' => 'Veuillez sélectionner une zone de livraison.'
        ];
    }

    $panier_items = panier_get_items_courant();

    if (empty($panier_items)) {
        return [
            'success' => false,
            'message' => 'Votre panier est vide. Ajoutez des produits avant de passer une commande.'
        ];
    }

    foreach ($panier_items as $item) {
        if ($item['stock'] < $item['quantite']) {
            return [
                'success' => false,
                'message' => 'Le stock disponible pour "' . htmlspecialchars($item['nom']) . '" est insuffisant. Stock disponible: ' . $item['stock']
            ];
        }
    }

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

    $client_nom = null;
    $client_telephone = null;

    $result = create_commande(
        $user_id,
        $panier_items,
        $adresse_livraison,
        $telephone_livraison,
        null,
        $zone_livraison_id,
        $frais_livraison,
        $choix,
        $client_nom,
        '-',
        $client_telephone
    );

    if ($result !== false && !empty($result['success'])) {
        require_once __DIR__ . '/../includes/commande_mode_helpers.php';
        geo_save_commande_mode((int) $result['commande_id'], $mode_livraison);
        if ($mode_livraison === 'livraison' && geo_coords_valid($geo_lat, $geo_lng)) {
            geo_save_commande_location((int) $result['commande_id'], $geo_lat, $geo_lng, $geo_precision, $geo_source);
        }
    }

    if ($result === false) {
        return [
            'success' => false,
            'message' => 'Une erreur est survenue lors de la création de la commande. Veuillez réessayer.'
        ];
    }

    if ($result['success']) {
        clear_panier($user_id);
        panier_invite_clear();

        $sous_total = 0;
        $nombre_articles = 0;
        $produits_email = [];
        foreach ($panier_items as $item) {
            $prix_unitaire = (!empty($item['panier_prix_unitaire']) && $item['panier_prix_unitaire'] > 0)
                ? (float) $item['panier_prix_unitaire']
                : (!empty($item['prix_promotion']) && $item['prix_promotion'] < $item['prix'] ? $item['prix_promotion'] : $item['prix']);
            $prix_total_ligne = $prix_unitaire * $item['quantite'];
            $sous_total += $prix_total_ligne;
            $nombre_articles += $item['quantite'];
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
                'surcout_taille' => isset($item['panier_surcout_taille']) ? (float) $item['panier_surcout_taille'] : 0
            ];
        }
        $montant_total = $sous_total + $frais_livraison;

        return [
            'success' => true,
            'message' => 'Votre commande a été créée avec succès ! Numéro de commande: ' . $result['numero_commande'],
            'commande_id' => $result['commande_id'],
            'numero_commande' => $result['numero_commande'],
            'user_id' => $user_id,
            'is_guest' => false,
            'email_data' => [
                'numero_commande' => $result['numero_commande'],
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
