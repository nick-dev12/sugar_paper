-- Email facultatif pour les comptes clients (connexion par téléphone + PIN possible)
-- Exécuter une fois sur la base concernée.

ALTER TABLE `users`
  MODIFY COLUMN `email` VARCHAR(255) NULL DEFAULT NULL;
