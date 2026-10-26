<?php
/**
 * SEO : libellés « outils de pâtisserie » → « matériel de pâtisserie » (catégories catalogue).
 * Exécuter : php migrations/run_seo_materiel_patisserie_libelles.php
 */

require_once __DIR__ . '/../conn/conn.php';

global $db;

try {
    $updated_nom = 0;
    $updated_desc = 0;

    $stmt = $db->query('SELECT id, nom, description FROM categories');
    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

    $update = $db->prepare('UPDATE categories SET nom = :nom, description = :description WHERE id = :id');

    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $nom = (string) ($row['nom'] ?? '');
        $desc = (string) ($row['description'] ?? '');
        $new_nom = $nom;
        $new_desc = $desc;

        $nom_lower = mb_strtolower($nom, 'UTF-8');
        if (
            strpos($nom_lower, 'outil') !== false
            && (strpos($nom_lower, 'pâtiss') !== false || strpos($nom_lower, 'patiss') !== false)
        ) {
            $new_nom = 'Matériel de pâtisserie';
        }

        $replacements = [
            'Outils de pâtisserie' => 'Matériel de pâtisserie',
            'outils de pâtisserie' => 'matériel de pâtisserie',
            'Outils de patisserie' => 'Matériel de pâtisserie',
            'outils de patisserie' => 'matériel de pâtisserie',
            'Des outils de pâtisserie' => 'Du matériel de pâtisserie',
        ];
        foreach ($replacements as $from => $to) {
            if ($new_desc !== '' && strpos($new_desc, $from) !== false) {
                $new_desc = str_replace($from, $to, $new_desc);
            }
        }

        if ($new_nom !== $nom || $new_desc !== $desc) {
            $update->execute([
                'id' => $id,
                'nom' => $new_nom,
                'description' => $new_desc,
            ]);
            if ($new_nom !== $nom) {
                $updated_nom++;
            }
            if ($new_desc !== $desc) {
                $updated_desc++;
            }
        }
    }

    echo "Migration libellés matériel de pâtisserie terminée.\n";
    echo "- Catégories renommées : {$updated_nom}\n";
    echo "- Descriptions mises à jour : {$updated_desc}\n";
} catch (Throwable $e) {
    echo 'Erreur : ' . $e->getMessage() . "\n";
    exit(1);
}
