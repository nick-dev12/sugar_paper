# Fichiers sensibles à remettre à la main après un clone

Ces fichiers ne sont **pas** dans GitHub (`.gitignore`).  
Après `install-vps-fresh.sh`, copiez-les depuis votre sauvegarde locale ou recréez-les.

Documentation complète suivi temps réel : **`docs/SUIVI_GPS_TEMPS_REEL.md`** (sections 15 et 16).

## Obligatoires

| Fichier | Description |
|---------|-------------|
| `conn/conn.php` | Connexion MySQL (copier depuis `conn/conn.example.php`) |
| `config/email.php` | SMTP / PHPMailer |
| `config/firebase_config.php` | Firebase côté front |
| `config/firebase_server.php` | Firebase Admin SDK |
| `config/tracking.php` | Secret suivi GPS livreurs (PHP) |
| `tracking-server/.env` | Secret suivi GPS livreurs (Node.js) |
| `sugar-paper-*.json` | Clé service account Firebase |

## Souvent nécessaires

| Fichier | Description |
|---------|-------------|
| `config/emailjs.php` | EmailJS |
| `config/site.php` | Paramètres site (`site_url` pour emails) |
| `AuthKey_*.p8` | Apple Sign In |

## Dossiers (données utilisateurs)

| Dossier | Description |
|---------|-------------|
| `upload/` | Images produits uploadées |
| `uploads/` | Autres uploads |

## Secret partagé PHP ↔ Node

Générer une fois :

```bash
openssl rand -hex 32
```

**Même valeur** dans les deux fichiers :

| Fichier | Clé |
|---------|-----|
| `config/tracking.php` | `internal_secret` |
| `tracking-server/.env` | `TRACKING_INTERNAL_SECRET` |

## Exemples rapides

```bash
cd /home/jomas/sugar-paper.com

cp conn/conn.example.php conn/conn.php
nano conn/conn.php

cp config/tracking.example.php config/tracking.php
nano config/tracking.php

# .env — préférer SSH (voir ci-dessous)
cd tracking-server
nano .env
```

## Contenu type `config/tracking.php`

```php
return [
    'internal_secret' => 'VOTRE_CLE_64_CHARS',
    'node_host' => '127.0.0.1',
    'node_port' => 3001,
    'socket_path' => '/socket.io',
    'public_site_url' => 'https://sugar-paper.com',
    'socket_url' => 'https://sugar-paper.com',
    'cors_origins' => [
        'https://sugar-paper.com',
        'https://www.sugar-paper.com',
    ],
];
```

## Contenu type `tracking-server/.env`

> Créer en SSH : `cat > .env` (voir `docs/SUIVI_GPS_TEMPS_REEL.md` section 6).

```env
TRACKING_PORT=3001
TRACKING_SOCKET_PATH=/socket.io

TRACKING_PHP_BASE=http://127.0.0.1:8081
TRACKING_PHP_HOST=sugar-paper.com

TRACKING_INTERNAL_SECRET=VOTRE_CLE_64_CHARS

TRACKING_CORS_ORIGINS=https://sugar-paper.com,https://www.sugar-paper.com
```

## Vérification après remise en place

```bash
cd /home/jomas/sugar-paper.com

# PHP
php -r "require 'includes/tracking_config.php'; echo tracking_realtime_available() ? 'PHP OK' : 'PHP KO';"

# Node lit le .env
cd tracking-server
node -e "require('dotenv').config({path:'.env'}); console.log(process.env.TRACKING_INTERNAL_SECRET?'Node OK':'Node KO');"

# PM2
pm2 delete sugar-tracking 2>/dev/null || true
pm2 start ecosystem.config.cjs
curl http://127.0.0.1:3001/health

# Socket.io public
curl "https://sugar-paper.com/socket.io/?EIO=4&transport=polling"

# Diagnostic (depuis la racine du site, avec un bl_id existant)
cd /home/jomas/sugar-paper.com
php scripts/tracking_diagnostic.php 1
```
