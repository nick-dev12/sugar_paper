<?php
/**
 * Personnalisation photo / impression comestible sur fiche produit
 */

require_once __DIR__ . '/../models/model_produits.php';

/**
 * Personnalisation produit activée (désactivée temporairement)
 * @return bool
 */
function produit_personnalisation_enabled()
{
    return false;
}

/**
 * Produit éligible à la prévisualisation sur gâteau (section photo_impression)
 * @param array|string|null $produit
 * @return bool
 */
function produit_supports_photo_personnalisation($produit)
{
    if (!is_array($produit)) {
        return false;
    }

    $section = normalize_produit_section_accueil($produit['section_accueil'] ?? '');
    if ($section === 'photo_impression') {
        return true;
    }

    $cat = mb_strtolower(trim((string) ($produit['categorie_nom'] ?? '')));
    if ($cat === '') {
        return false;
    }

    return (strpos($cat, 'photo') !== false && strpos($cat, 'impression') !== false)
        || strpos($cat, 'impression comestible') !== false;
}

/**
 * Chemin relatif du modèle de gâteau (depuis la racine web)
 * @return string
 */
function produit_personnalisation_modele_url()
{
    return '/image/gateau-personnalisation-modele.jpg';
}

/**
 * Dossier d'upload des visuels personnalisés
 * @return string
 */
function produit_personnalisation_upload_dir()
{
    return __DIR__ . '/../upload/produits-personnalises/';
}

/**
 * Sous-dossier relatif pour image_optimizer
 * @return string
 */
function produit_personnalisation_upload_subdir()
{
    return 'produits-personnalises';
}

/**
 * Valide qu'un chemin relatif appartient bien au dossier personnalisation
 * @param string|null $relative_path
 * @return bool
 */
function produit_personnalisation_path_is_valid($relative_path)
{
    $relative_path = trim((string) $relative_path);
    if ($relative_path === '' || strpos($relative_path, '..') !== false) {
        return false;
    }

    $prefix = produit_personnalisation_upload_subdir() . '/';
    if (strpos($relative_path, $prefix) !== 0) {
        return false;
    }

    $disk = __DIR__ . '/../upload/' . str_replace('/', DIRECTORY_SEPARATOR, $relative_path);
    return is_file($disk);
}

/**
 * URL publique d'une image personnalisée enregistrée
 * @param string|null $relative_path
 * @return string
 */
function produit_personnalisation_public_url($relative_path)
{
    if (!produit_personnalisation_path_is_valid($relative_path)) {
        return '';
    }

    require_once __DIR__ . '/image_optimizer.php';
    return upload_image_url($relative_path, 'md');
}
