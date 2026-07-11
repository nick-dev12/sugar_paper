<?php
/**
 * Répare la table employes si elle existe sans PK (migration partielle antérieure)
 */
require_once __DIR__ . '/../conn/conn.php';

if (!$db) {
    exit(1);
}

try {
    $st = $db->query("SHOW TABLES LIKE 'employes'");
    if ($st->rowCount() === 0) {
        echo "— table employes absente, rien à réparer\n";
        exit(0);
    }

    $cols = $db->query("SHOW COLUMNS FROM employes WHERE Field = 'id'")->fetch(PDO::FETCH_ASSOC);
    $extra = strtolower((string) ($cols['Extra'] ?? ''));
    if (strpos($extra, 'auto_increment') === false) {
        $db->exec('ALTER TABLE `employes` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY');
        echo "+ employes.id : AUTO_INCREMENT + PRIMARY KEY ajoutés\n";
    } else {
        echo "— employes.id déjà AUTO_INCREMENT\n";
    }
} catch (PDOException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
