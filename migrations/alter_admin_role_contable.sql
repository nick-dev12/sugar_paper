-- Rôle « contable » — accès Comptes, Paramètres, Mon profil.
ALTER TABLE `admin`
  MODIFY COLUMN `role` ENUM(
    'admin',
    'gestion_stock',
    'commercial',
    'commercial_general',
    'informaticien',
    'developpeur',
    'comptabilite',
    'contable',
    'rh',
    'caissier'
  ) NOT NULL DEFAULT 'admin';
