<?php
/**
 * Contrôleur pour la gestion des produits
 * Programmation procédurale uniquement
 */

require_once __DIR__ . '/../models/model_produits.php';
require_once __DIR__ . '/../includes/barcode_fpl.php';
require_once __DIR__ . '/../includes/upload_image_limits.php';
require_once __DIR__ . '/../includes/image_optimizer.php';
require_once __DIR__ . '/../includes/produit_image_moderation.php';

/**
 * @return void
 */
function produit_upload_reset_moderation_batch()
{
    $GLOBALS['produit_image_moderation_batch_hold'] = false;
    produit_image_moderation_set_last_error('');
}

/**
 * @return bool
 */
function produit_upload_batch_needs_hold()
{
    return !empty($GLOBALS['produit_image_moderation_batch_hold']);
}

/**
 * Génère et sauvegarde le QR code d'un produit (pointant vers stock-info.php)
 * @param int $produit_id ID du produit
 * @return bool True si succès
 */
function generer_qrcode_produit($produit_id) {
    if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
        return false;
    }
    require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/../includes/site_url.php';
    $base = get_site_base_url();
    $url = $base . '/stock-info.php?id=' . (int) $produit_id;
    $dir = __DIR__ . '/../upload/qrcodes/';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $file = $dir . 'produit_' . (int) $produit_id . '.png';
    try {
        $qro = new \chillerlan\QRCode\QROptions([
            'outputType'   => \chillerlan\QRCode\Output\QROutputInterface::GDIMAGE_PNG,
            'scale'        => 10,
            'outputBase64' => false,
        ]);
        $qr = new \chillerlan\QRCode\QRCode($qro);
        $qr->render($url, $file);
        return file_exists($file);
    } catch (Throwable $e) {
        return false;
    }
}
require_once __DIR__ . '/../models/model_categories.php';
require_once __DIR__ . '/../models/model_variantes.php';
require_once __DIR__ . '/../models/model_mouvements_stock.php';

/**
 * Upload une image de produit
 * @param array $file Le fichier $_FILES
 * @param string $field_name Le nom du champ
 * @return string|false Le nom du fichier ou False en cas d'erreur
 */
function upload_produit_image($file, $field_name = 'image') {
    if (!isset($file[$field_name]) || $file[$field_name]['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    $upload_dir = __DIR__ . '/../upload/produits/';
    
    // Créer le dossier s'il n'existe pas
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file_info = $file[$field_name];
    $result = upload_optimize_image_file($file_info, $upload_dir, 'produits', 'produit_');
    if (!empty($result['success']) && !empty($result['relative_path'])) {
        $role = (string) ($_SESSION['admin_role'] ?? 'admin');
        $admin_id = (int) ($_SESSION['admin_id'] ?? 0);
        $mod = produit_image_moderation_after_upload((string) $result['relative_path'], $role, $admin_id);
        if (!$mod['ok']) {
            return false;
        }
        if (!empty($mod['needs_hold'])) {
            $GLOBALS['produit_image_moderation_batch_hold'] = true;
        }
        return (string) $result['relative_path'];
    }

    return false;
}

/**
 * Upload plusieurs images supplémentaires
 * @param array $files $_FILES avec name en tableau (ex: images_supplementaires[])
 * @param string $field_name Le nom du champ (ex: images_supplementaires)
 * @return array Tableau des chemins des images uploadées
 */
function upload_produit_images_multiples($files, $field_name = 'images_supplementaires') {
    $uploaded = [];
    if (!isset($files[$field_name]) || !is_array($files[$field_name]['name'])) {
        return $uploaded;
    }
    
    $count = count($files[$field_name]['name']);
    for ($i = 0; $i < $count; $i++) {
        $file = [
            'name' => $files[$field_name]['name'][$i],
            'type' => $files[$field_name]['type'][$i],
            'tmp_name' => $files[$field_name]['tmp_name'][$i],
            'error' => $files[$field_name]['error'][$i],
            'size' => $files[$field_name]['size'][$i]
        ];
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            continue;
        }
        $fake_files = [$field_name => $file];
        $path = upload_produit_image($fake_files, $field_name);
        if ($path) {
            $uploaded[] = $path;
        } elseif (produit_image_moderation_last_error() !== '') {
            break;
        }
    }
    return $uploaded;
}

/**
 * Traite l'ajout d'un nouveau produit
 * @return array Tableau avec 'success' (bool) et 'message' (string)
 */
function process_add_produit() {
    $errors = [];
    $success = false;
    $message = '';
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => ''];
    }
    
    // Récupération et validation des données (stock géré via produits.stock)
    $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $prix = isset($_POST['prix']) ? trim($_POST['prix']) : '';
    $prix_promotion = isset($_POST['prix_promotion']) && !empty($_POST['prix_promotion']) ? trim($_POST['prix_promotion']) : null;
    $stock = isset($_POST['stock']) ? intval($_POST['stock']) : 0;
    $categorie_id = isset($_POST['categorie_id']) ? intval($_POST['categorie_id']) : 0;
    $statut = isset($_POST['statut']) ? $_POST['statut'] : 'actif';
    $prix_negociable = produit_prix_negociable_from_post(1);
    $unite = isset($_POST['unite']) ? trim($_POST['unite']) : 'unité';
    $couleurs = null;
    if (isset($_POST['couleurs']) && trim($_POST['couleurs']) !== '') {
        $raw = trim($_POST['couleurs']);
        $decoded = json_decode($raw, true);
        if (is_array($decoded) && !empty($decoded)) {
            $valid = array_values(array_unique(array_filter($decoded, function($c) {
                return is_string($c) && preg_match('/^#[0-9A-Fa-f]{6}$/', $c);
            })));
            $couleurs = !empty($valid) ? json_encode($valid) : null;
        } else {
            $couleurs = $raw;
        }
    }
    $poids = null;
    $taille = null;
    if (isset($_POST['poids']) && trim($_POST['poids']) !== '') {
        $raw = trim($_POST['poids']);
        if ($raw !== '[]') {
            $dec = json_decode($raw, true);
            $poids = (is_array($dec) && !empty($dec)) ? $raw : null;
            if (!$poids && $raw) {
                $arr = array_map(function($x) { return ['v' => trim($x), 's' => 0]; }, array_filter(explode(',', $raw)));
                $arr = array_filter($arr, function($x) { return !empty($x['v']) && $x['v'] !== '[]'; });
                $poids = !empty($arr) ? json_encode(array_values($arr)) : null;
            }
        }
    }
    if (isset($_POST['taille']) && trim($_POST['taille']) !== '') {
        $raw = trim($_POST['taille']);
        if ($raw !== '[]') {
            $dec = json_decode($raw, true);
            $taille = (is_array($dec) && !empty($dec)) ? $raw : null;
            if (!$taille && $raw) {
                $arr = array_map(function($x) { return ['v' => trim($x), 's' => 0]; }, array_filter(explode(',', $raw)));
                $arr = array_filter($arr, function($x) { return !empty($x['v']) && $x['v'] !== '[]'; });
                $taille = !empty($arr) ? json_encode(array_values($arr)) : null;
            }
        }
    }
    $mesure = null;
    if (isset($_POST['mesure']) && trim((string) $_POST['mesure']) !== '') {
        $mesure = trim((string) $_POST['mesure']);
    }
    
    // Validation
    if (empty($nom)) {
        $errors[] = 'Le nom du produit est obligatoire.';
    }
    
    if (empty($description)) {
        $errors[] = 'La description est obligatoire.';
    }
    
    if (empty($prix) || !is_numeric($prix) || $prix <= 0) {
        $errors[] = 'Le prix doit être un nombre positif.';
    }
    
    if ($prix_promotion !== null && (!is_numeric($prix_promotion) || $prix_promotion <= 0 || $prix_promotion >= $prix)) {
        $errors[] = 'Le prix promotionnel doit être inférieur au prix normal.';
    }
    
    if ($stock < 0) {
        $errors[] = 'Le stock ne peut pas être négatif.';
    }

    require_once __DIR__ . '/../models/model_categories.php';
    require_once __DIR__ . '/../models/model_genres.php';
    require_once __DIR__ . '/../models/model_produits_sous_categories.php';
    $role_admin = $_SESSION['admin_role'] ?? 'admin';
    $admin_id_sess = (int) ($_SESSION['admin_id'] ?? 0);
    $categorie_generale_id_val = null;
    $genre_ids_for_save = [];
    $categorie_id_for_db = $categorie_id;
    $sous_categorie_ids_for_save = [];
    if (isset($_POST['sous_categorie_ids']) && is_array($_POST['sous_categorie_ids'])) {
        foreach ($_POST['sous_categorie_ids'] as $sc) {
            $isc = (int) $sc;
            if ($isc > 0) {
                $sous_categorie_ids_for_save[] = $isc;
            }
        }
        $sous_categorie_ids_for_save = array_values(array_unique($sous_categorie_ids_for_save, SORT_REGULAR));
        sort($sous_categorie_ids_for_save, SORT_NUMERIC);
    }

    if ($role_admin === 'vendeur' && function_exists('vendeur_genres_mode_actif') && vendeur_genres_mode_actif()) {
        $categorie_generale_id_val = isset($_POST['categorie_generale_id']) ? (int) $_POST['categorie_generale_id'] : 0;
        if ($categorie_generale_id_val <= 0 || !get_categorie_generale_by_id($categorie_generale_id_val)) {
            $errors[] = 'Choisissez une catégorie principale.';
            $categorie_generale_id_val = 0;
        }
        $genre_ids_for_save = [];
        if (isset($_POST['genre_ids']) && is_array($_POST['genre_ids'])) {
            foreach ($_POST['genre_ids'] as $g) {
                $genre_ids_for_save[] = (int) $g;
            }
        }
        $genre_ids_for_save = array_values(array_unique(array_filter($genre_ids_for_save, function ($x) {
            return (int) $x > 0;
        })));
        $rayon_a_des_genres = $categorie_generale_id_val > 0
            && function_exists('count_genres_linked_to_categorie_generale')
            && count_genres_linked_to_categorie_generale($categorie_generale_id_val) > 0;
        if ($rayon_a_des_genres) {
            if (empty($genre_ids_for_save)) {
                $errors[] = 'Cochez au moins un genre pour cette catégorie.';
            } else {
                foreach ($genre_ids_for_save as $gid) {
                    if (!get_genre_by_id($gid) || !genre_id_is_allowed_for_categorie_generale($gid, $categorie_generale_id_val)) {
                        $errors[] = 'Un ou plusieurs genres ne sont pas valides pour la catégorie choisie.';
                        $genre_ids_for_save = [];
                        break;
                    }
                }
            }
        } else {
            $genre_ids_for_save = [];
        }
        $rayon_sous_n = $categorie_generale_id_val > 0
            && function_exists('plateforme_sous_categorie_count_for_rayon')
            ? plateforme_sous_categorie_count_for_rayon($categorie_generale_id_val) : 0;
        if ($rayon_sous_n > 0) {
            if (empty($sous_categorie_ids_for_save)) {
                $errors[] = 'Cochez au moins une sous-catégorie pour ce rayon.';
            } else {
                foreach ($sous_categorie_ids_for_save as $scid) {
                    if (!categorie_est_utilisable_par_vendeur($scid, $admin_id_sess)
                        || !categorie_plateforme_liee_au_rayon($scid, $categorie_generale_id_val)) {
                        $errors[] = 'Une ou plusieurs sous-catégories ne sont pas valides pour le rayon choisi.';
                        $sous_categorie_ids_for_save = [];
                        break;
                    }
                }
            }
        } else {
            $sous_categorie_ids_for_save = [];
        }
        $categorie_id_for_db = null;
        if (!empty($sous_categorie_ids_for_save)) {
            $categorie_id_for_db = (int) $sous_categorie_ids_for_save[0];
        }
    } elseif ($role_admin === 'vendeur' && function_exists('categories_hierarchy_enabled') && categories_hierarchy_enabled()) {
        $generales_rayons = (function_exists('categories_generales_table_exists') && categories_generales_table_exists())
            ? get_general_categories_ordered() : [];
        if (!empty($generales_rayons)) {
            $categorie_generale_id_val = isset($_POST['categorie_generale_id']) ? (int) $_POST['categorie_generale_id'] : 0;
            if ($categorie_generale_id_val <= 0) {
                $errors[] = 'Choisissez une catégorie générale (rayon).';
            } elseif (!get_categorie_generale_by_id($categorie_generale_id_val)) {
                $errors[] = 'Catégorie générale invalide.';
                $categorie_generale_id_val = 0;
            }
        } else {
            $categorie_generale_id_val = isset($_POST['categorie_generale_id']) ? (int) $_POST['categorie_generale_id'] : 0;
        }
        $rayon_sous_n = $categorie_generale_id_val > 0
            && function_exists('plateforme_sous_categorie_count_for_rayon')
            ? plateforme_sous_categorie_count_for_rayon($categorie_generale_id_val) : 0;
        if ($rayon_sous_n > 0) {
            if (empty($sous_categorie_ids_for_save)) {
                $errors[] = 'Cochez au moins une sous-catégorie.';
            } else {
                foreach ($sous_categorie_ids_for_save as $scid) {
                    if (!categorie_est_utilisable_par_vendeur($scid, $admin_id_sess)
                        || !categorie_plateforme_liee_au_rayon($scid, (int) $categorie_generale_id_val)) {
                        $errors[] = 'Une ou plusieurs sous-catégories ne sont pas valides pour le rayon choisi.';
                        $sous_categorie_ids_for_save = [];
                        break;
                    }
                }
            }
        } else {
            $sous_categorie_ids_for_save = [];
        }
        $categorie_id_for_db = !empty($sous_categorie_ids_for_save) ? (int) $sous_categorie_ids_for_save[0] : null;
    } else {
        if ($categorie_id <= 0) {
            $errors[] = 'Veuillez sélectionner une catégorie.';
        }
        $categorie_id_for_db = $categorie_id;
    }

    if ($role_admin === 'vendeur' && (int) ($categorie_generale_id_val ?? 0) > 0 && function_exists('categorie_generale_parse_attributs_row')) {
        $cgr = get_categorie_generale_by_id((int) $categorie_generale_id_val);
        if ($cgr) {
            $attr = categorie_generale_parse_attributs_row($cgr);
            if (!$attr['poids']) {
                $poids = null;
            }
            if (!$attr['taille']) {
                $taille = null;
            }
            if (!$attr['couleur']) {
                $couleurs = null;
            }
            if (!$attr['mesure']) {
                $unite = 'unité';
                $mesure = null;
            }
        }
    }

    if ($categorie_id_for_db !== null && (int) $categorie_id_for_db > 0 && !get_categorie_by_id((int) $categorie_id_for_db)) {
        $errors[] = 'La catégorie sélectionnée n\'existe pas.';
    }
    
    // Upload des images : images_produit[] (1ère = principale, reste = galerie)
    // Si lié à un article en stock, on utilise son image si pas d'upload
    $image_principale = null;
    $images_supp = [];
    produit_upload_reset_moderation_batch();
    if (isset($_FILES['images_produit']) && is_array($_FILES['images_produit']['name'])) {
        $uploaded = upload_produit_images_multiples($_FILES, 'images_produit');
        if (!empty($uploaded)) {
            $image_principale = $uploaded[0];
            $images_supp = array_slice($uploaded, 1);
        } elseif (produit_image_moderation_last_error() !== '') {
            $errors[] = produit_image_moderation_last_error();
        }
    }
    if (!$image_principale) {
        $errors[] = 'Au moins une image est obligatoire.';
    }
    
    // Construire le tableau images (principale + supplémentaires) en JSON
    $images_json = null;
    if ($image_principale) {
        $all_images = array_merge([$image_principale], $images_supp);
        $images_json = json_encode($all_images);
    }
    
    // Si aucune erreur, créer le produit
    if (empty($errors)) {
        $etage = isset($_POST['etage']) ? trim($_POST['etage']) : '';
        $numero_rayon = isset($_POST['numero_rayon']) ? trim($_POST['numero_rayon']) : '';
        $owner_admin = (int) ($_SESSION['admin_id'] ?? 0);
        if (produits_has_column('admin_id') && $owner_admin <= 0) {
            $errors[] = 'Session administrateur invalide.';
        }

        if (empty($errors)) {
            $data = [
                'nom' => $nom,
                'description' => $description,
                'prix' => $prix,
                'prix_promotion' => $prix_promotion,
                'stock' => $stock,
                'categorie_id' => $categorie_id_for_db,
                'image_principale' => $image_principale,
                'images' => $images_json,
                'poids' => $poids,
                'unite' => $unite,
                'couleurs' => $couleurs,
                'taille' => $taille,
                'statut' => $stock > 0 ? $statut : 'rupture_stock',
                'etage' => $etage !== '' ? $etage : null,
                'numero_rayon' => $numero_rayon !== '' ? $numero_rayon : null,
                'admin_id' => $owner_admin,
            ];
            if (produits_has_column('categorie_generale_id')) {
                if ($categorie_generale_id_val !== null && $categorie_generale_id_val > 0) {
                    $data['categorie_generale_id'] = $categorie_generale_id_val;
                } else {
                    $data['categorie_generale_id'] = null;
                }
            }
            if (produits_has_column('mesure')) {
                $data['mesure'] = $mesure;
            }
            if (produits_has_column('prix_negociable')) {
                $data['prix_negociable'] = $prix_negociable;
            }

            $produit_id = create_produit($data);

            if ($produit_id) {
                $all_paths = array_merge([$image_principale], $images_supp);
                $message = 'Produit ajouté avec succès !';
                try {
                    if ($role_admin === 'vendeur' && function_exists('vendeur_genres_mode_actif') && vendeur_genres_mode_actif()) {
                        save_produits_genres_for_produit((int) $produit_id, $genre_ids_for_save);
                    } elseif ($categorie_generale_id_val !== null && $categorie_generale_id_val > 0 && function_exists('vendeur_align_subcategorie_generale') && $categorie_id_for_db !== null) {
                        vendeur_align_subcategorie_generale((int) $categorie_id_for_db, $categorie_generale_id_val, $owner_admin);
                    }
                    if ($role_admin === 'vendeur' && produits_sous_categories_table_exists()
                        && ((function_exists('vendeur_genres_mode_actif') && vendeur_genres_mode_actif())
                            || (function_exists('categories_hierarchy_enabled') && categories_hierarchy_enabled()))) {
                        save_produits_sous_categories_for_produit((int) $produit_id, $sous_categorie_ids_for_save);
                    }
                    $hold_info = ['held' => false];
                    if ($role_admin === 'vendeur') {
                        $hold_info = produit_image_moderation_finalize_vendor_product(
                            (int) $produit_id,
                            $owner_admin,
                            $all_paths,
                            produit_upload_batch_needs_hold(),
                            $statut
                        );
                        if (!empty($hold_info['held']) && !empty($hold_info['message'])) {
                            $message .= $hold_info['message'];
                        }
                    }
                    if ($owner_admin > 0 && empty($hold_info['held'])) {
                        // Notification push : voir produit_schedule_deferred_post_create().
                    }
                    $variantes_nom = isset($_POST['variantes_nom']) && is_array($_POST['variantes_nom']) ? array_values($_POST['variantes_nom']) : [];
                    $variantes_prix = isset($_POST['variantes_prix']) && is_array($_POST['variantes_prix']) ? array_values($_POST['variantes_prix']) : [];
                    $variantes_prix_promo = isset($_POST['variantes_prix_promo']) && is_array($_POST['variantes_prix_promo']) ? array_values($_POST['variantes_prix_promo']) : [];
                    $variantes_files = (isset($_FILES['variantes_image']) && is_array($_FILES['variantes_image']['name'])) ? $_FILES['variantes_image'] : null;
                    $nb_variantes = count($variantes_nom);
                    for ($i = 0; $i < $nb_variantes; $i++) {
                        $vn = trim($variantes_nom[$i] ?? '');
                        $vp = isset($variantes_prix[$i]) && is_numeric($variantes_prix[$i]) ? (float)$variantes_prix[$i] : 0;
                        if ($vn !== '' && $vp > 0) {
                            $vimg = null;
                            if ($variantes_files && isset($variantes_files['name'][$i]) && (int)($variantes_files['error'][$i] ?? 4) === UPLOAD_ERR_OK) {
                                $f = [
                                    'name' => $variantes_files['name'][$i],
                                    'type' => $variantes_files['type'][$i] ?? '',
                                    'tmp_name' => $variantes_files['tmp_name'][$i] ?? '',
                                    'error' => $variantes_files['error'][$i] ?? 4,
                                    'size' => $variantes_files['size'][$i] ?? 0
                                ];
                                $fake = ['image' => $f];
                                $vimg = upload_produit_image($fake, 'image');
                            }
                            $vpromo = isset($variantes_prix_promo[$i]) && is_numeric($variantes_prix_promo[$i]) && (float)$variantes_prix_promo[$i] > 0 ? (float)$variantes_prix_promo[$i] : null;
                            if ($vpromo !== null && $vpromo >= $vp) {
                                $vpromo = null;
                            }
                            create_variante([
                                'produit_id' => $produit_id,
                                'nom' => $vn,
                                'prix' => $vp,
                                'prix_promotion' => $vpromo,
                                'image' => $vimg ?: null,
                                'ordre' => $i
                            ]);
                        }
                    }
                    require_once __DIR__ . '/../includes/produit_post_create_deferred.php';
                    produit_schedule_deferred_post_create(
                        (int) $produit_id,
                        (int) $owner_admin,
                        (string) $nom,
                        (string) ($data['statut'] ?? 'actif')
                    );
                    $success = true;
                } catch (Throwable $e) {
                    error_log('[process_add_produit] post-create id=' . (int) $produit_id . ': ' . $e->getMessage());
                    $success = false;
                    $errors[] = 'Le produit a été enregistré mais une erreur est survenue lors de la finalisation (genres, variantes, codes…). Vérifiez la fiche produit.';
                }
            } else {
                $errors[] = 'Une erreur est survenue lors de l\'ajout du produit.';
            }
        }
    }
    
    if ($success) {
        return [
            'success' => true,
            'message' => $message,
            'produit_id' => isset($produit_id) ? (int) $produit_id : 0,
            'owner_admin' => isset($owner_admin) ? (int) $owner_admin : 0,
            'produit_nom' => isset($nom) ? (string) $nom : '',
            'produit_statut' => isset($data['statut']) ? (string) $data['statut'] : 'actif',
        ];
    } else {
        $message = !empty($errors) ? implode('<br>', $errors) : 'Une erreur est survenue.';
        return ['success' => false, 'message' => $message];
    }
}

/**
 * Traite la modification d'un produit
 * @param int $produit_id L'ID du produit à modifier
 * @return array Tableau avec 'success' (bool) et 'message' (string)
 */
function process_update_produit($produit_id) {
    $errors = [];
    $success = false;
    $message = '';
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['success' => false, 'message' => ''];
    }
    
    // Vérifier que le produit existe
    $produit = get_produit_by_id($produit_id);
    if (!$produit) {
        return ['success' => false, 'message' => 'Produit introuvable.'];
    }

    if (($_SESSION['admin_role'] ?? '') === 'vendeur') {
        $aid = (int) ($produit['admin_id'] ?? 0);
        if ($aid !== (int) ($_SESSION['admin_id'] ?? 0)) {
            return ['success' => false, 'message' => 'Accès non autorisé à ce produit.'];
        }
    }

    // Récupération et validation des données (stock géré via produits.stock)
    $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $prix = isset($_POST['prix']) ? trim($_POST['prix']) : '';
    $prix_promotion = isset($_POST['prix_promotion']) && !empty($_POST['prix_promotion']) ? trim($_POST['prix_promotion']) : null;
    $stock = isset($_POST['stock']) ? intval($_POST['stock']) : 0;
    $categorie_id = isset($_POST['categorie_id']) ? intval($_POST['categorie_id']) : 0;
    $unite = isset($_POST['unite']) ? trim($_POST['unite']) : 'unité';
    $statut = isset($_POST['statut']) ? $_POST['statut'] : 'actif';
    $prix_negociable = produit_prix_negociable_from_post(1);
    $couleurs = null;
    if (isset($_POST['couleurs']) && trim($_POST['couleurs']) !== '') {
        $raw = trim($_POST['couleurs']);
        $decoded = json_decode($raw, true);
        if (is_array($decoded) && !empty($decoded)) {
            $valid = array_values(array_unique(array_filter($decoded, function($c) {
                return is_string($c) && preg_match('/^#[0-9A-Fa-f]{6}$/', $c);
            })));
            $couleurs = !empty($valid) ? json_encode($valid) : null;
        } else {
            $couleurs = $raw;
        }
    }
    $poids = null;
    $taille = null;
    if (isset($_POST['poids']) && trim($_POST['poids']) !== '') {
        $raw = trim($_POST['poids']);
        if ($raw !== '[]') {
            $dec = json_decode($raw, true);
            $poids = (is_array($dec) && !empty($dec)) ? $raw : null;
            if (!$poids && $raw) {
                $arr = array_map(function($x) { return ['v' => trim($x), 's' => 0]; }, array_filter(explode(',', $raw)));
                $arr = array_filter($arr, function($x) { return !empty($x['v']) && $x['v'] !== '[]'; });
                $poids = !empty($arr) ? json_encode(array_values($arr)) : null;
            }
        }
    }
    if (isset($_POST['taille']) && trim($_POST['taille']) !== '') {
        $raw = trim($_POST['taille']);
        if ($raw !== '[]') {
            $dec = json_decode($raw, true);
            $taille = (is_array($dec) && !empty($dec)) ? $raw : null;
            if (!$taille && $raw) {
                $arr = array_map(function($x) { return ['v' => trim($x), 's' => 0]; }, array_filter(explode(',', $raw)));
                $arr = array_filter($arr, function($x) { return !empty($x['v']) && $x['v'] !== '[]'; });
                $taille = !empty($arr) ? json_encode(array_values($arr)) : null;
            }
        }
    }
    $mesure = null;
    if (isset($_POST['mesure']) && trim((string) $_POST['mesure']) !== '') {
        $mesure = trim((string) $_POST['mesure']);
    }
    
    // Validation (identique à l'ajout)
    if (empty($nom)) {
        $errors[] = 'Le nom du produit est obligatoire.';
    }
    
    if (empty($description)) {
        $errors[] = 'La description est obligatoire.';
    }
    
    if (empty($prix) || !is_numeric($prix) || $prix <= 0) {
        $errors[] = 'Le prix doit être un nombre positif.';
    }
    
    if ($prix_promotion !== null && (!is_numeric($prix_promotion) || $prix_promotion <= 0 || $prix_promotion >= $prix)) {
        $errors[] = 'Le prix promotionnel doit être inférieur au prix normal.';
    }
    
    if ($stock < 0) {
        $errors[] = 'Le stock ne peut pas être négatif.';
    }

    require_once __DIR__ . '/../models/model_categories.php';
    require_once __DIR__ . '/../models/model_genres.php';
    require_once __DIR__ . '/../models/model_produits_sous_categories.php';
    $role_admin = $_SESSION['admin_role'] ?? 'admin';
    $admin_id_sess = (int) ($_SESSION['admin_id'] ?? 0);
    $categorie_generale_id_val = null;
    $genre_ids_for_save = [];
    $categorie_id_for_db = $categorie_id;
    $sous_categorie_ids_for_save = [];
    if (isset($_POST['sous_categorie_ids']) && is_array($_POST['sous_categorie_ids'])) {
        foreach ($_POST['sous_categorie_ids'] as $sc) {
            $isc = (int) $sc;
            if ($isc > 0) {
                $sous_categorie_ids_for_save[] = $isc;
            }
        }
        $sous_categorie_ids_for_save = array_values(array_unique($sous_categorie_ids_for_save, SORT_REGULAR));
        sort($sous_categorie_ids_for_save, SORT_NUMERIC);
    }

    if ($role_admin === 'vendeur' && function_exists('vendeur_genres_mode_actif') && vendeur_genres_mode_actif()) {
        $categorie_generale_id_val = isset($_POST['categorie_generale_id']) ? (int) $_POST['categorie_generale_id'] : 0;
        if ($categorie_generale_id_val <= 0 || !get_categorie_generale_by_id($categorie_generale_id_val)) {
            $errors[] = 'Choisissez une catégorie principale.';
            $categorie_generale_id_val = 0;
        }
        $genre_ids_for_save = [];
        if (isset($_POST['genre_ids']) && is_array($_POST['genre_ids'])) {
            foreach ($_POST['genre_ids'] as $g) {
                $genre_ids_for_save[] = (int) $g;
            }
        }
        $genre_ids_for_save = array_values(array_unique(array_filter($genre_ids_for_save, function ($x) {
            return (int) $x > 0;
        })));
        $rayon_a_des_genres = $categorie_generale_id_val > 0
            && function_exists('count_genres_linked_to_categorie_generale')
            && count_genres_linked_to_categorie_generale($categorie_generale_id_val) > 0;
        if ($rayon_a_des_genres) {
            if (empty($genre_ids_for_save)) {
                $errors[] = 'Cochez au moins un genre pour cette catégorie.';
            } else {
                foreach ($genre_ids_for_save as $gid) {
                    if (!get_genre_by_id($gid) || !genre_id_is_allowed_for_categorie_generale($gid, $categorie_generale_id_val)) {
                        $errors[] = 'Un ou plusieurs genres ne sont pas valides pour la catégorie choisie.';
                        $genre_ids_for_save = [];
                        break;
                    }
                }
            }
        } else {
            $genre_ids_for_save = [];
        }
        $rayon_sous_n = $categorie_generale_id_val > 0
            && function_exists('plateforme_sous_categorie_count_for_rayon')
            ? plateforme_sous_categorie_count_for_rayon($categorie_generale_id_val) : 0;
        if ($rayon_sous_n > 0) {
            if (empty($sous_categorie_ids_for_save)) {
                $errors[] = 'Cochez au moins une sous-catégorie pour ce rayon.';
            } else {
                foreach ($sous_categorie_ids_for_save as $scid) {
                    if (!categorie_est_utilisable_par_vendeur($scid, $admin_id_sess)
                        || !categorie_plateforme_liee_au_rayon($scid, $categorie_generale_id_val)) {
                        $errors[] = 'Une ou plusieurs sous-catégories ne sont pas valides pour le rayon choisi.';
                        $sous_categorie_ids_for_save = [];
                        break;
                    }
                }
            }
        } else {
            $sous_categorie_ids_for_save = [];
        }
        $categorie_id_for_db = null;
        if (!empty($sous_categorie_ids_for_save)) {
            $categorie_id_for_db = (int) $sous_categorie_ids_for_save[0];
        }
    } elseif ($role_admin === 'vendeur' && function_exists('categories_hierarchy_enabled') && categories_hierarchy_enabled()) {
        $generales_rayons = (function_exists('categories_generales_table_exists') && categories_generales_table_exists())
            ? get_general_categories_ordered() : [];
        if (!empty($generales_rayons)) {
            $categorie_generale_id_val = isset($_POST['categorie_generale_id']) ? (int) $_POST['categorie_generale_id'] : 0;
            if ($categorie_generale_id_val <= 0) {
                $errors[] = 'Choisissez une catégorie générale (rayon).';
            } elseif (!get_categorie_generale_by_id($categorie_generale_id_val)) {
                $errors[] = 'Catégorie générale invalide.';
                $categorie_generale_id_val = 0;
            }
        } else {
            $categorie_generale_id_val = isset($_POST['categorie_generale_id']) ? (int) $_POST['categorie_generale_id'] : 0;
        }
        $rayon_sous_n = $categorie_generale_id_val > 0
            && function_exists('plateforme_sous_categorie_count_for_rayon')
            ? plateforme_sous_categorie_count_for_rayon($categorie_generale_id_val) : 0;
        if ($rayon_sous_n > 0) {
            if (empty($sous_categorie_ids_for_save)) {
                $errors[] = 'Cochez au moins une sous-catégorie.';
            } else {
                foreach ($sous_categorie_ids_for_save as $scid) {
                    if (!categorie_est_utilisable_par_vendeur($scid, $admin_id_sess)
                        || !categorie_plateforme_liee_au_rayon($scid, (int) $categorie_generale_id_val)) {
                        $errors[] = 'Une ou plusieurs sous-catégories ne sont pas valides pour le rayon choisi.';
                        $sous_categorie_ids_for_save = [];
                        break;
                    }
                }
            }
        } else {
            $sous_categorie_ids_for_save = [];
        }
        $categorie_id_for_db = !empty($sous_categorie_ids_for_save) ? (int) $sous_categorie_ids_for_save[0] : null;
    } else {
        if ($categorie_id <= 0) {
            $errors[] = 'Veuillez sélectionner une catégorie.';
        }
        $categorie_id_for_db = $categorie_id;
    }

    if ($role_admin === 'vendeur' && (int) ($categorie_generale_id_val ?? 0) > 0 && function_exists('categorie_generale_parse_attributs_row')) {
        $cgr = get_categorie_generale_by_id((int) $categorie_generale_id_val);
        if ($cgr) {
            $attr = categorie_generale_parse_attributs_row($cgr);
            if (!$attr['poids']) {
                $poids = null;
            }
            if (!$attr['taille']) {
                $taille = null;
            }
            if (!$attr['couleur']) {
                $couleurs = null;
            }
            if (!$attr['mesure']) {
                $unite = 'unité';
                $mesure = null;
            }
        }
    }

    if ($categorie_id_for_db !== null && (int) $categorie_id_for_db > 0 && !get_categorie_by_id((int) $categorie_id_for_db)) {
        $errors[] = 'La catégorie sélectionnée n\'existe pas.';
    }
    
    // Récupérer les images existantes
    $all_images = [];
    if (!empty($produit['images'])) {
        $decoded = json_decode($produit['images'], true);
        if (is_array($decoded)) {
            $all_images = $decoded;
        }
    }
    if (empty($all_images) && !empty($produit['image_principale'])) {
        $all_images = [$produit['image_principale']];
    }
    
    // Images à conserver (envoyées par le formulaire - celles non supprimées par l'utilisateur)
    $images_to_keep = [];
    if (isset($_POST['images_to_keep']) && is_array($_POST['images_to_keep'])) {
        $images_to_keep = array_values(array_filter(array_map('trim', $_POST['images_to_keep'])));
    }
    
    // Upload des images supplémentaires (nouvelles)
    $images_supp = [];
    produit_upload_reset_moderation_batch();
    if (isset($_FILES['images_supplementaires']) && is_array($_FILES['images_supplementaires']['name'])) {
        $images_supp = upload_produit_images_multiples($_FILES, 'images_supplementaires');
        if (empty($images_supp) && produit_image_moderation_last_error() !== '') {
            $errors[] = produit_image_moderation_last_error();
        }
    }
    
    // Construire le tableau final : images conservées + nouvelles
    $final_images = array_merge($images_to_keep, $images_supp);
    $final_images = array_values(array_unique($final_images));
    
    // Validation : au moins une image obligatoire
    if (empty($final_images)) {
        $errors[] = 'Au moins une image est obligatoire. Veuillez conserver ou ajouter au moins une image.';
    }
    
    $image_principale = !empty($final_images) ? $final_images[0] : $produit['image_principale'];
    $images_json = !empty($final_images) ? json_encode($final_images) : null;
    $removed_images = array_diff($all_images, $images_to_keep);
    
    // Si aucune erreur, mettre à jour le produit
    if (empty($errors)) {
        /* Étage / rayon : absents du formulaire admin — conserver les valeurs en base */
        $etage = isset($_POST['etage']) ? trim((string) $_POST['etage']) : trim((string) ($produit['etage'] ?? ''));
        $numero_rayon = isset($_POST['numero_rayon']) ? trim((string) $_POST['numero_rayon']) : trim((string) ($produit['numero_rayon'] ?? ''));
        $data = [
            'nom' => $nom,
            'description' => $description,
            'prix' => $prix,
            'prix_promotion' => $prix_promotion,
            'stock' => $stock,
            'categorie_id' => $categorie_id_for_db,
            'image_principale' => $image_principale,
            'images' => $images_json,
            'poids' => $poids,
            'unite' => $unite,
            'couleurs' => $couleurs,
            'taille' => $taille,
            'statut' => (($produit['statut'] ?? '') === 'bloque')
                ? 'bloque'
                : ($stock > 0 ? $statut : 'rupture_stock'),
            'stock_article_id' => null,
            'etage' => $etage !== '' ? $etage : null,
            'numero_rayon' => $numero_rayon !== '' ? $numero_rayon : null
        ];
        if (produits_has_column('categorie_generale_id')) {
            if ($categorie_generale_id_val !== null && $categorie_generale_id_val > 0) {
                $data['categorie_generale_id'] = $categorie_generale_id_val;
            } else {
                $data['categorie_generale_id'] = null;
            }
        }
        if (produits_has_column('mesure')) {
            $data['mesure'] = $mesure;
        }
        if (produits_has_column('prix_negociable')) {
            $data['prix_negociable'] = $prix_negociable;
        }

        if (update_produit($produit_id, $data)) {
            if ($role_admin === 'vendeur' && function_exists('vendeur_genres_mode_actif') && vendeur_genres_mode_actif()) {
                save_produits_genres_for_produit((int) $produit_id, $genre_ids_for_save);
            } elseif ($categorie_generale_id_val !== null && $categorie_generale_id_val > 0 && function_exists('vendeur_align_subcategorie_generale') && $categorie_id_for_db !== null) {
                vendeur_align_subcategorie_generale((int) $categorie_id_for_db, $categorie_generale_id_val, $admin_id_sess);
            }
            if ($role_admin === 'vendeur' && function_exists('produits_sous_categories_table_exists') && produits_sous_categories_table_exists()
                && ((function_exists('vendeur_genres_mode_actif') && vendeur_genres_mode_actif())
                    || (function_exists('categories_hierarchy_enabled') && categories_hierarchy_enabled()))) {
                save_produits_sous_categories_for_produit((int) $produit_id, $sous_categorie_ids_for_save);
            }
            $message = 'Produit modifié avec succès !';
            if ($role_admin === 'vendeur' && !empty($images_supp)) {
                $hold_info = produit_image_moderation_finalize_vendor_product(
                    (int) $produit_id,
                    $admin_id_sess,
                    $images_supp,
                    produit_upload_batch_needs_hold(),
                    $statut
                );
                if (!empty($hold_info['held']) && !empty($hold_info['message'])) {
                    $message .= $hold_info['message'];
                }
            }
            $success = true;

            $notify_admin_id = (int) ($produit['admin_id'] ?? 0);
            if ($notify_admin_id <= 0 && $role_admin === 'vendeur') {
                $notify_admin_id = (int) ($admin_id_sess ?? 0);
            }
            if ($notify_admin_id > 0 && ($produit['statut'] ?? '') !== 'bloque') {
                require_once __DIR__ . '/../services/send_boutique_abonnement_notification.php';
                if (boutique_abonnement_produit_visible_catalogue($data['statut'] ?? '')) {
                    $new_promo_val = ($prix_promotion !== null && (float) $prix_promotion > 0) ? (float) $prix_promotion : null;
                    $notify_event = null;
                    if (boutique_abonnement_promo_a_change($produit, $new_promo_val)) {
                        $notify_event = 'promotion';
                    } elseif (boutique_abonnement_modification_significative($produit, $data)) {
                        $notify_event = 'modification';
                    }
                    if ($notify_event !== null) {
                        boutique_abonnement_try_notify($notify_admin_id, $notify_event, $nom, (int) $produit_id);
                    }
                }
            }

            // Supprimer du disque les images retirées par l'utilisateur
            foreach ($removed_images as $old_path) {
                $full_path = __DIR__ . '/../upload/' . $old_path;
                if ($old_path && file_exists($full_path)) {
                    @unlink($full_path);
                }
            }
            // Gestion des variantes
            $variantes_nom = isset($_POST['variantes_nom']) && is_array($_POST['variantes_nom']) ? $_POST['variantes_nom'] : [];
            $variantes_prix = isset($_POST['variantes_prix']) && is_array($_POST['variantes_prix']) ? $_POST['variantes_prix'] : [];
            $variantes_prix_promo = isset($_POST['variantes_prix_promo']) && is_array($_POST['variantes_prix_promo']) ? $_POST['variantes_prix_promo'] : [];
            $variantes_id = isset($_POST['variantes_id']) && is_array($_POST['variantes_id']) ? $_POST['variantes_id'] : [];
            $existing_ids = [];
            foreach (get_variantes_by_produit($produit_id) as $v) {
                $existing_ids[] = (int) $v['id'];
            }
            $kept_ids = [];
            for ($i = 0; $i < count($variantes_nom); $i++) {
                $vn = isset($variantes_nom[$i]) ? trim($variantes_nom[$i]) : '';
                $vp = isset($variantes_prix[$i]) && is_numeric($variantes_prix[$i]) ? (float) $variantes_prix[$i] : 0;
                if (empty($vn) || $vp <= 0) continue;
                $vid = isset($variantes_id[$i]) ? (int) $variantes_id[$i] : 0;
                $vpromo = isset($variantes_prix_promo[$i]) && is_numeric($variantes_prix_promo[$i]) && $variantes_prix_promo[$i] > 0 ? (float) $variantes_prix_promo[$i] : null;
                if ($vpromo && $vp > 0 && $vpromo >= $vp) $vpromo = null;
                $vimg = null;
                if (isset($_FILES['variantes_image']) && is_array($_FILES['variantes_image']['name']) && isset($_FILES['variantes_image']['name'][$i]) && $_FILES['variantes_image']['error'][$i] === UPLOAD_ERR_OK) {
                    $fake = [
                        'image' => [
                            'name' => $_FILES['variantes_image']['name'][$i],
                            'type' => $_FILES['variantes_image']['type'][$i],
                            'tmp_name' => $_FILES['variantes_image']['tmp_name'][$i],
                            'error' => $_FILES['variantes_image']['error'][$i],
                            'size' => $_FILES['variantes_image']['size'][$i]
                        ]
                    ];
                    $vimg = upload_produit_image($fake, 'image');
                }
                if ($vid > 0 && in_array($vid, $existing_ids)) {
                    $old = get_variante_by_id($vid);
                    $img_final = $vimg ?: ($old ? $old['image'] : null);
                    update_variante($vid, [
                        'nom' => $vn,
                        'prix' => $vp,
                        'prix_promotion' => $vpromo,
                        'image' => $img_final,
                        'ordre' => $i
                    ]);
                    $kept_ids[] = $vid;
                } else {
                    create_variante([
                        'produit_id' => $produit_id,
                        'nom' => $vn,
                        'prix' => $vp,
                        'prix_promotion' => $vpromo,
                        'image' => $vimg,
                        'ordre' => $i
                    ]);
                }
            }
            foreach ($existing_ids as $eid) {
                if (!in_array($eid, $kept_ids)) {
                    delete_variante($eid);
                }
            }
            if (($produit['statut'] ?? '') === 'bloque' && function_exists('produit_tenter_debloquer_apres_modification')) {
                if (produit_tenter_debloquer_apres_modification($produit_id)) {
                    $message = 'Produit modifié — le blocage plateforme est levé, votre produit est à nouveau visible sur le site.';
                }
            }
        } else {
            $errors[] = 'Une erreur est survenue lors de la modification du produit.';
        }
    }
    
    if ($success) {
        return ['success' => true, 'message' => $message];
    } else {
        $message = !empty($errors) ? implode('<br>', $errors) : 'Une erreur est survenue.';
        return ['success' => false, 'message' => $message];
    }
}

/**
 * Traite la suppression d'un produit
 * @param int $produit_id L'ID du produit à supprimer
 * @return array Tableau avec 'success' (bool) et 'message' (string)
 */
function process_delete_produit($produit_id) {
    // Vérifier que le produit existe
    $produit = get_produit_by_id($produit_id);
    if (!$produit) {
        return ['success' => false, 'message' => 'Produit introuvable.'];
    }

    if (($_SESSION['admin_role'] ?? '') === 'vendeur') {
        $aid = (int) ($produit['admin_id'] ?? 0);
        if ($aid !== (int) ($_SESSION['admin_id'] ?? 0)) {
            return ['success' => false, 'message' => 'Accès non autorisé à ce produit.'];
        }
    }

    // Supprimer l'image si elle existe
    if ($produit['image_principale'] && file_exists(__DIR__ . '/../upload/' . $produit['image_principale'])) {
        @unlink(__DIR__ . '/../upload/' . $produit['image_principale']);
    }
    
    // Supprimer le produit
    if (delete_produit($produit_id)) {
        return ['success' => true, 'message' => 'Produit supprimé avec succès !'];
    } else {
        return ['success' => false, 'message' => 'Une erreur est survenue lors de la suppression.'];
    }
}

/**
 * Traite l'ajustement du stock d'un produit
 * @param int $produit_id ID du produit
 * @return array ['success' => bool, 'message' => string]
 */
function process_ajuster_stock_produit($produit_id) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['ajuster_stock'])) {
        return ['success' => false, 'message' => ''];
    }

    $produit = get_produit_by_id($produit_id);
    if (!$produit) {
        return ['success' => false, 'message' => 'Produit introuvable.'];
    }

    if (($_SESSION['admin_role'] ?? '') === 'vendeur') {
        $aid = (int) ($produit['admin_id'] ?? 0);
        if ($aid !== (int) ($_SESSION['admin_id'] ?? 0)) {
            return ['success' => false, 'message' => 'Accès non autorisé à ce produit.'];
        }
    }

    $nouveau_stock = isset($_POST['nouveau_stock']) ? (int) $_POST['nouveau_stock'] : -1;
    if ($nouveau_stock < 0) {
        return ['success' => false, 'message' => 'La quantité doit être un nombre positif ou zéro.'];
    }

    $quantite_avant = (int) ($produit['stock'] ?? 0);
    if ($nouveau_stock === $quantite_avant) {
        return ['success' => false, 'message' => 'La nouvelle quantité est identique au stock actuel.'];
    }

    $statut = $produit['statut'];
    if ($nouveau_stock <= 0) {
        $statut = 'rupture_stock';
    } elseif ($produit['statut'] === 'rupture_stock') {
        $statut = 'actif';
    }
    $data_update = array_merge($produit, ['stock' => $nouveau_stock, 'statut' => $statut]);
    if (update_produit($produit_id, $data_update)) {
        $quantite_diff = $nouveau_stock - $quantite_avant;
        create_stock_mouvement([
            'type' => 'inventaire',
            'stock_article_id' => null,
            'produit_id' => $produit_id,
            'quantite' => abs($quantite_diff),
            'quantite_avant' => $quantite_avant,
            'quantite_apres' => $nouveau_stock,
            'reference_type' => 'ajustement',
            'reference_id' => null,
            'reference_numero' => null,
            'notes' => 'Ajustement manuel : ' . ($quantite_diff >= 0 ? '+' : '') . $quantite_diff
        ]);
        return ['success' => true, 'message' => 'Stock ajusté avec succès.'];
    }

    return ['success' => false, 'message' => 'Erreur lors de la mise à jour du stock.'];
}

?>

