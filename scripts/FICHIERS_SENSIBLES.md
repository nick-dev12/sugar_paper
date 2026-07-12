# Fichiers sensibles à remettre à la main après un clone

Ces fichiers ne sont **pas** dans GitHub (`.gitignore`).  
Après `install-vps-fresh.sh`, copiez-les depuis votre sauvegarde locale ou recréez-les.

## Obligatoires

| Fichier | Description |
|---------|-------------|
| `conn/conn.php` | Connexion MySQL (copier depuis `conn/conn.example.php`) |
| `config/email.php` | SMTP / PHPMailer |
| `config/firebase_config.php` | Firebase côté front |
| `config/firebase_server.php` | Firebase Admin SDK |
| `config/tracking.php` | Secret suivi GPS livreurs |
| `tracking-server/.env` | Port Node, secret, URL PHP |
| `sugar-paper-*.json` | Clé service account Firebase |

## Souvent nécessaires

| Fichier | Description |
|---------|-------------|
| `config/emailjs.php` | EmailJS |
| `config/site.php` | Paramètres site |
| `AuthKey_*.p8` | Apple Sign In |

## Dossiers (données utilisateurs)

| Dossier | Description |
|---------|-------------|
| `upload/` | Images produits uploadées |
| `uploads/` | Autres uploads |

## Exemples rapides

```bash
cd /home/jomas/sugar-paper.com

cp conn/conn.example.php conn/conn.php
nano conn/conn.php

cp config/email.example.php config/email.php
nano config/email.php

# tracking-server : copier depuis votre sauvegarde ou recréer
nano tracking-server/.env
```

## Contenu type `tracking-server/.env`

```
PORT=3001
TRACKING_SECRET=votre_secret_identique_a_config/tracking.php
PHP_BASE_URL=http://127.0.0.1:8081
PHP_HOST_HEADER=sugar-paper.com
```

## Vérification

```bash
php scripts/tracking_diagnostic.php
pm2 restart sugar-tracking
curl -s https://sugar-paper.com/api/tracking/ping.php
```
