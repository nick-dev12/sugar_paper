<?php
/**
 * Migration : textes français section mise en avant (kit impression comestible)
 * Exécuter: php migrations/run_update_trending_spotlight_content.php
 */

require_once __DIR__ . '/../conn/conn.php';

if (!$db) {
    echo "Connexion BDD indisponible.\n";
    exit(1);
}

try {
    $stmt = $db->query('SELECT id, label, titre, description, bouton_texte FROM trending_config ORDER BY id DESC LIMIT 1');
    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;

    if (!$row) {
        $insert = $db->prepare("
            INSERT INTO trending_config (label, titre, description, bouton_texte, bouton_lien, image, date_modification)
            VALUES (:label, :titre, :description, :bouton_texte, :bouton_lien, :image, NOW())
        ");
        $insert->execute([
            'label' => 'Nouveauté',
            'titre' => 'Sugar Paper|Kit impression comestible',
            'description' => 'Découvrez l\'impression, les cartouches encre comestible et le papier sucre.',
            'bouton_texte' => 'Découvrir',
            'bouton_lien' => 'section-produits.php?section=kit_impression',
            'image' => 'speaker.png',
        ]);
        echo "Configuration trending créée avec les textes kit impression comestible.\n";
        exit(0);
    }

    $update = $db->prepare("
        UPDATE trending_config
        SET label = :label,
            titre = :titre,
            description = :description,
            bouton_texte = :bouton_texte,
            date_modification = NOW()
        WHERE id = :id
    ");
    $update->execute([
        'id' => $row['id'],
        'label' => 'Nouveauté',
        'titre' => 'Sugar Paper|Kit impression comestible',
        'description' => 'Découvrez l\'impression, les cartouches encre comestible et le papier sucre.',
        'bouton_texte' => 'Découvrir',
    ]);

    echo "Textes trending mis à jour (id={$row['id']}).\n";
    exit(0);
} catch (PDOException $e) {
    echo 'Erreur migration : ' . $e->getMessage() . "\n";
    exit(1);
}
