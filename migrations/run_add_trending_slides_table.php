<?php
/**
 * Migration : slides trending (texte + image liés)
 * Exécuter: php migrations/run_add_trending_slides_table.php
 */

require_once __DIR__ . '/../conn/conn.php';

if (!$db) {
    echo "Connexion BDD indisponible.\n";
    exit(1);
}

/**
 * Extrait la clé section depuis un lien bouton legacy
 * @param string $lien
 * @return string
 */
function trending_migration_extract_section_key($lien)
{
    $lien = trim((string) $lien);
    if ($lien !== '' && preg_match('/section=([a-z0-9_]+)/i', $lien, $matches)) {
        return strtolower((string) $matches[1]);
    }
    return 'kit_impression';
}

try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS trending_slides (
            id INT(11) NOT NULL AUTO_INCREMENT,
            label VARCHAR(255) NOT NULL,
            titre VARCHAR(500) NOT NULL,
            description TEXT NULL,
            bouton_texte VARCHAR(255) NOT NULL DEFAULT 'Découvrir',
            section_key VARCHAR(100) NOT NULL DEFAULT 'kit_impression',
            image VARCHAR(255) NULL,
            ordre INT(11) NOT NULL DEFAULT 0,
            date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            date_modification DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_trending_slides_ordre (ordre)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Table trending_slides OK.\n";

    $count_stmt = $db->query('SELECT COUNT(*) FROM trending_slides');
    $count = $count_stmt ? (int) $count_stmt->fetchColumn() : 0;
    if ($count > 0) {
        echo "Slides déjà présents, migration ignorée.\n";
        exit(0);
    }

    $config_stmt = $db->query('SELECT * FROM trending_config ORDER BY id DESC LIMIT 1');
    $config = $config_stmt ? $config_stmt->fetch(PDO::FETCH_ASSOC) : false;

    $label = trim((string) ($config['label'] ?? 'Nouveauté'));
    $titre = trim((string) ($config['titre'] ?? 'Sugar Paper|Kit impression comestible'));
    $description = trim((string) ($config['description'] ?? ''));
    $bouton_texte = trim((string) ($config['bouton_texte'] ?? 'Découvrir'));
    $section_key = trending_migration_extract_section_key($config['bouton_lien'] ?? '');

    if ($label === '') {
        $label = 'Nouveauté';
    }
    if ($titre === '') {
        $titre = 'Sugar Paper|Kit impression comestible';
    }
    if ($bouton_texte === '') {
        $bouton_texte = 'Découvrir';
    }

    $images = [];
    $images_table = $db->query("SHOW TABLES LIKE 'trending_images'");
    if ($images_table && $images_table->fetchColumn()) {
        $img_stmt = $db->query('SELECT image FROM trending_images ORDER BY ordre ASC, id ASC');
        $rows = $img_stmt ? $img_stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($rows as $row) {
            $filename = trim((string) ($row['image'] ?? ''));
            if ($filename !== '') {
                $images[] = $filename;
            }
        }
    }

    if (empty($images)) {
        $legacy_image = trim((string) ($config['image'] ?? ''));
        if ($legacy_image !== '' && $legacy_image !== 'speaker.png') {
            $images[] = $legacy_image;
        }
    }

    $insert = $db->prepare("
        INSERT INTO trending_slides (label, titre, description, bouton_texte, section_key, image, ordre)
        VALUES (:label, :titre, :description, :bouton_texte, :section_key, :image, :ordre)
    ");

    if (empty($images)) {
        $insert->execute([
            'label' => $label,
            'titre' => $titre,
            'description' => $description !== '' ? $description : null,
            'bouton_texte' => $bouton_texte,
            'section_key' => $section_key,
            'image' => null,
            'ordre' => 1,
        ]);
        echo "1 slide texte importé depuis trending_config.\n";
        exit(0);
    }

    $ordre = 1;
    foreach ($images as $index => $image_name) {
        $slide_label = $label;
        $slide_titre = $titre;
        $slide_description = $description;
        $slide_bouton = $bouton_texte;
        $slide_section = $section_key;

        if ($index > 0) {
            $slide_label = $label;
            $slide_titre = $titre;
        }

        $insert->execute([
            'label' => $slide_label,
            'titre' => $slide_titre,
            'description' => $slide_description !== '' ? $slide_description : null,
            'bouton_texte' => $slide_bouton,
            'section_key' => $slide_section,
            'image' => $image_name,
            'ordre' => $ordre,
        ]);
        $ordre++;
    }

    echo count($images) . " slide(s) importé(s) depuis la configuration existante.\n";
    exit(0);
} catch (PDOException $e) {
    echo 'Erreur migration : ' . $e->getMessage() . "\n";
    exit(1);
}
