<?php
/**
 * Modèle pour la gestion du slider
 * Programmation procédurale uniquement
 */

// Inclusion du fichier de connexion à la BDD
require_once __DIR__ . '/../conn/conn.php';

/**
 * La table slider possède une colonne admin_id (vendeur propriétaire du slide).
 */
function slider_table_has_admin_id_column() {
    static $has = null;
    if ($has !== null) {
        return $has;
    }
    global $db;
    if (!$db) {
        $has = false;
        return false;
    }
    try {
        $r = $db->query("SHOW COLUMNS FROM slider LIKE 'admin_id'");
        $has = $r && $r->rowCount() > 0;
    } catch (PDOException $e) {
        $has = false;
    }
    return $has;
}

/**
 * Slides actifs pour une vitrine boutique (admin_id = vendeur).
 * Si la colonne admin_id n'existe pas encore, retourne [] (aucun hero dédié).
 */
function get_slides_for_boutique($admin_id) {
    global $db;
    $aid = (int) $admin_id;
    if ($aid <= 0 || !$db || !slider_table_has_admin_id_column()) {
        return [];
    }
    try {
        $stmt = $db->prepare("
            SELECT * FROM slider
            WHERE statut = 'actif' AND admin_id = :aid
            ORDER BY ordre ASC, date_creation DESC
        ");
        $stmt->execute(['aid' => $aid]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ? filter_slides_with_existing_upload_files($rows) : [];
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Ne garde que les slides dont le fichier image existe sous upload/slider/.
 */
function filter_slides_with_existing_upload_files(array $slides) {
    $base = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR . 'slider' . DIRECTORY_SEPARATOR;
    $out = [];
    foreach ($slides as $s) {
        $img = trim((string) ($s['image'] ?? ''));
        if ($img === '') {
            continue;
        }
        $path = $base . str_replace(['/', '\\'], '', $img);
        if (is_file($path)) {
            $out[] = $s;
        }
    }
    return $out;
}

/**
 * Récupère tous les slides actifs
 * @param string|null $statut Filtrer par statut ('actif', 'inactif' ou null pour tous)
 * @return array Tableau des slides (vide si aucun ou en cas d'erreur)
 */
function get_all_slides($statut = 'actif') {
    global $db;
    
    try {
        if ($statut) {
            $stmt = $db->prepare("
                SELECT * FROM slider 
                WHERE statut = :statut 
                ORDER BY ordre ASC, date_creation DESC
            ");
            $stmt->execute(['statut' => $statut]);
        } else {
            $stmt = $db->prepare("
                SELECT * FROM slider 
                ORDER BY ordre ASC, date_creation DESC
            ");
            $stmt->execute();
        }
        
        $slides = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $slides ? $slides : [];
    } catch (PDOException $e) {
        // En cas d'erreur (table n'existe pas encore), retourner un tableau vide
        return [];
    }
}

/**
 * Récupère un slide par son ID
 * @param int $id L'ID du slide
 * @return array|false Les données du slide ou False si non trouvé
 */
function get_slide_by_id($id) {
    global $db;
    
    try {
        $stmt = $db->prepare("SELECT * FROM slider WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $slide = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $slide ? $slide : false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Prochain ordre d'affichage pour un nouveau slide.
 *
 * @param int|null $admin_id Propriétaire vendeur ou null (tous / plateforme)
 */
function slider_get_next_ordre($admin_id = null)
{
    global $db;

    if (!$db) {
        return 0;
    }

    try {
        if (slider_table_has_admin_id_column() && $admin_id !== null && (int) $admin_id > 0) {
            $stmt = $db->prepare('
                SELECT COALESCE(MAX(ordre), -1) + 1 AS next_ordre
                FROM slider
                WHERE admin_id = :admin_id
            ');
            $stmt->execute(['admin_id' => (int) $admin_id]);
        } else {
            $stmt = $db->query('
                SELECT COALESCE(MAX(ordre), -1) + 1 AS next_ordre
                FROM slider
            ');
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return isset($row['next_ordre']) ? (int) $row['next_ordre'] : 0;
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Crée un nouveau slide
 * @param string $titre Le titre du slide
 * @param string $paragraphe Le paragraphe du slide
 * @param string $image Le nom de l'image
 * @param string $bouton_texte Le texte du bouton (optionnel)
 * @param string $bouton_lien Le lien du bouton (optionnel)
 * @param int $ordre L'ordre d'affichage
 * @param string $statut Le statut (actif/inactif)
 * @param int|null $admin_id Propriétaire vendeur (vitrine) ; null = slide plateforme / général
 * @return int|false L'ID du slide créé ou False en cas d'erreur
 */
function add_slide($titre, $paragraphe, $image, $bouton_texte = null, $bouton_lien = null, $ordre = 0, $statut = 'actif', $admin_id = null) {
    global $db;
    
    try {
        $params = [
            'titre' => $titre,
            'paragraphe' => $paragraphe,
            'image' => $image,
            'bouton_texte' => $bouton_texte,
            'bouton_lien' => $bouton_lien,
            'ordre' => $ordre,
            'statut' => $statut
        ];
        if (slider_table_has_admin_id_column()) {
            $aid = ($admin_id !== null && (int) $admin_id > 0) ? (int) $admin_id : null;
            $params['admin_id'] = $aid;
            $stmt = $db->prepare("
                INSERT INTO slider (titre, paragraphe, image, bouton_texte, bouton_lien, ordre, statut, date_creation, admin_id)
                VALUES (:titre, :paragraphe, :image, :bouton_texte, :bouton_lien, :ordre, :statut, NOW(), :admin_id)
            ");
        } else {
            $stmt = $db->prepare("
                INSERT INTO slider (titre, paragraphe, image, bouton_texte, bouton_lien, ordre, statut, date_creation)
                VALUES (:titre, :paragraphe, :image, :bouton_texte, :bouton_lien, :ordre, :statut, NOW())
            ");
        }
        
        $result = $stmt->execute($params);
        
        if ($result) {
            return $db->lastInsertId();
        }
        
        return false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Met à jour un slide
 * @param int $id L'ID du slide
 * @param string $titre Le titre du slide
 * @param string $paragraphe Le paragraphe du slide
 * @param string $image Le nom de l'image
 * @param string $bouton_texte Le texte du bouton (optionnel)
 * @param string $bouton_lien Le lien du bouton (optionnel)
 * @param int $ordre L'ordre d'affichage
 * @param string $statut Le statut (actif/inactif)
 * @return bool True en cas de succès, False sinon
 */
function update_slide($id, $titre, $paragraphe, $image, $bouton_texte = null, $bouton_lien = null, $ordre = 0, $statut = 'actif') {
    global $db;
    
    try {
        $stmt = $db->prepare("
            UPDATE slider SET
                titre = :titre,
                paragraphe = :paragraphe,
                image = :image,
                bouton_texte = :bouton_texte,
                bouton_lien = :bouton_lien,
                ordre = :ordre,
                statut = :statut,
                date_modification = NOW()
            WHERE id = :id
        ");
        
        return $stmt->execute([
            'id' => $id,
            'titre' => $titre,
            'paragraphe' => $paragraphe,
            'image' => $image,
            'bouton_texte' => $bouton_texte,
            'bouton_lien' => $bouton_lien,
            'ordre' => $ordre,
            'statut' => $statut
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Supprime un slide
 * @param int $id L'ID du slide
 * @return bool True en cas de succès, False sinon
 */
function delete_slide($id) {
    global $db;
    
    try {
        $stmt = $db->prepare("DELETE FROM slider WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupère le chemin de l'image d'un slide
 * @param int $slide_id L'ID du slide
 * @return string|false Le chemin de l'image ou False
 */
function get_slide_image_path($slide_id) {
    $slide = get_slide_by_id($slide_id);
    return $slide ? $slide['image'] : false;
}

?>

