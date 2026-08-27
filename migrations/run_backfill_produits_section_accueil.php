<?php
/**
 * Migration: assignation automatique section_accueil selon catégories / mots-clés
 * Exécuter: php migrations/run_backfill_produits_section_accueil.php
 */

require_once __DIR__ . '/../conn/conn.php';

global $db;

/**
 * @return string|null
 */
function backfill_resolve_section_from_category($categorie_nom)
{
    $nom = mb_strtolower(trim((string) $categorie_nom), 'UTF-8');

    if ($nom === '') {
        return null;
    }

    if (strpos($nom, 'cake topper') !== false || $nom === 'cake topper') {
        return 'cake_topper';
    }

    if (
        strpos($nom, 'impression') !== false
        || strpos($nom, 'imprimante') !== false
        || strpos($nom, 'azyme') !== false
    ) {
        return 'photo_impression';
    }

    if (
        strpos($nom, 'outil') !== false
        || strpos($nom, 'pâtiss') !== false
        || strpos($nom, 'patiss') !== false
        || strpos($nom, 'moule') !== false
        || strpos($nom, 'matériel') !== false
        || strpos($nom, 'materiel') !== false
        || strpos($nom, 'cake design') !== false
        || strpos($nom, 'boite') !== false
        || strpos($nom, 'boîte') !== false
        || strpos($nom, 'colorant') !== false
        || strpos($nom, 'sprinkle') !== false
        || strpos($nom, 'comestible') !== false
        || strpos($nom, 'gros') !== false
        || strpos($nom, 'halal') !== false
    ) {
        return 'outils_patisserie';
    }

    return null;
}

/**
 * @return string|null
 */
function backfill_resolve_section_from_product_name($produit_nom)
{
    $nom = mb_strtolower(trim((string) $produit_nom), 'UTF-8');

    if ($nom === '') {
        return null;
    }

    if (strpos($nom, 'topper') !== false) {
        return 'cake_topper';
    }

    if (
        strpos($nom, 'impression') !== false
        || strpos($nom, 'imprimante') !== false
        || strpos($nom, 'azyme') !== false
        || strpos($nom, 'papier sucre') !== false
        || strpos($nom, 'choco transfert') !== false
        || strpos($nom, 'cartouche') !== false
        || strpos($nom, 'encre alimentaire') !== false
    ) {
        return 'photo_impression';
    }

    return null;
}

try {
    if (!$db instanceof PDO) {
        echo "Connexion a la base indisponible depuis le PHP CLI.\n";
        exit(1);
    }

    $column_stmt = $db->query("SHOW COLUMNS FROM produits LIKE 'section_accueil'");
    if (!$column_stmt || !$column_stmt->fetchColumn()) {
        echo "La colonne section_accueil n'existe pas. Executez d'abord run_add_produits_section_accueil.php\n";
        exit(1);
    }

    $select = $db->query("
        SELECT p.id, p.nom, c.nom AS categorie_nom
        FROM produits p
        LEFT JOIN categories c ON c.id = p.categorie_id
        WHERE p.statut = 'actif'
          AND (p.section_accueil IS NULL OR p.section_accueil = '')
    ");

    $rows = $select ? $select->fetchAll(PDO::FETCH_ASSOC) : [];
    if (empty($rows)) {
        echo "Aucun produit actif sans section_accueil a traiter.\n";
        exit(0);
    }

    $update = $db->prepare("
        UPDATE produits
        SET section_accueil = :section_accueil
        WHERE id = :id
    ");

    $counts = [
        'cake_topper' => 0,
        'photo_impression' => 0,
        'outils_patisserie' => 0,
        'skipped' => 0,
    ];

    foreach ($rows as $row) {
        $section = backfill_resolve_section_from_category($row['categorie_nom'] ?? '');

        if ($section === null) {
            $section = backfill_resolve_section_from_product_name($row['nom'] ?? '');
        }

        if ($section === null) {
            $counts['skipped']++;
            continue;
        }

        $update->execute([
            'section_accueil' => $section,
            'id' => (int) $row['id'],
        ]);
        $counts[$section]++;
    }

    echo "Assignation terminee.\n";
    echo "- Cake toppers: {$counts['cake_topper']}\n";
    echo "- Photo et impression comestible: {$counts['photo_impression']}\n";
    echo "- Outils de patisserie: {$counts['outils_patisserie']}\n";
    echo "- Non assignes: {$counts['skipped']}\n";
} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
    exit(1);
}
