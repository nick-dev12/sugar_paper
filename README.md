# Sugar Paper

Site e-commerce B2C pour produits naturels issus de la production propre de l'entreprise.

## Technologies

- PHP (pur, sans framework)
- MySQL
- Firebase (notifications push)

## Installation

1. Cloner le dépôt
2. Configurer la base de données dans `conn/conn.php` (copier depuis `conn/conn.example.php` si disponible)
3. Ajouter les fichiers de configuration sensibles (non versionnés) :
   - `sugar-paper-*.json` : clés Firebase (Console Firebase)
   - `config/emailjs.php` : configuration EmailJS
   - `config/firebase_config.php` : configuration Firebase frontend

## Structure

- `admin/` : Espace administrateur
- `user/` : Espace client
- `config/` : Fichiers de configuration
- `controllers/` : Logique métier
- `models/` : Accès aux données
