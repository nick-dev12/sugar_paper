<?php

/**
 * Configuration de connexion à la base de données
 * Copiez ce fichier en conn.php et modifiez les valeurs selon votre environnement
 */

// Charger l'autoload Composer (PHPMailer, Firebase, etc.)
$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

// Paramètres de connexion
$db_host = "localhost";
$db_name = "tresor_afri";
$db_user = "root";
$db_pass = "";

try {
  // Connexion à la base avec PDO (charset utf8mb4 obligatoire pour les accents/emojis)
  $db = new PDO(
    "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
    $db_user,
    $db_pass,
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      // Vraies requêtes préparées côté serveur (plus sûr, meilleur cache)
      PDO::ATTR_EMULATE_PREPARES => false,
      // Connexion persistante : réutilise les connexions => moins de latence
      PDO::ATTR_PERSISTENT => true,
    ]
  );
} catch (PDOException $e) {
  // En production on n'affiche jamais le détail : on journalise et on renvoie une page propre.
  error_log('Erreur de connexion BDD : ' . $e->getMessage());
  http_response_code(503);
  exit('Service temporairement indisponible. Veuillez réessayer dans quelques instants.');
}

?>
