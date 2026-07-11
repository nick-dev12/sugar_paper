# Suivi GPS livreurs — déploiement VPS (Nginx + Apache + Node.js)

Ce dossier héberge le serveur **Socket.io** qui diffuse les positions en temps réel.
La logique métier (auth, BDD, admin) reste en **PHP** à la racine du projet.

## Prérequis VPS

- Nginx en reverse proxy (déjà en place)
- Apache + PHP pour le site
- Node.js 18+ et npm
- PM2 (`npm install -g pm2`)

## Étape 1 — Base de données

Sur le VPS (ou en local pour test) :

```bash
cd /chemin/vers/site_gateau
php migrations/run_add_livreur_tracking.php
```

## Étape 2 — Configuration PHP

```bash
cp config/tracking.example.php config/tracking.php
```

Éditez `config/tracking.php` :

- `internal_secret` : clé longue aléatoire (ex. `openssl rand -hex 32`)
- `public_site_url` : `https://sugar-paper.com`
- `node_port` : `3001` (identique au `.env` Node)

## Étape 3 — Serveur Node.js

```bash
cd tracking-server
cp .env.example .env
# Éditez .env : même internal_secret que config/tracking.php
npm install
pm2 start ecosystem.config.cjs
pm2 save
pm2 startup
```

Vérification :

```bash
curl http://127.0.0.1:3001/health
# → {"ok":true,"service":"tracking-socket"}
```

## Étape 4 — Nginx (WebSocket)

Ajoutez dans le bloc `server` HTTPS de `sugar-paper.com` :

```nginx
location /socket.io/ {
    proxy_pass http://127.0.0.1:3001;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_read_timeout 86400s;
    proxy_send_timeout 86400s;
}
```

Puis :

```bash
nginx -t && systemctl reload nginx
```

## Étape 5 — Admin

1. Connectez-vous à l’admin : `/admin/livreurs/index.php`
2. Créez un livreur (email + mot de passe pour l’app)
3. Assignez une commande et ouvrez **Suivi GPS** : `/admin/livreurs/suivi.php?commande_id=X`

## API livreur (pour l’app Flutter — étape suivante)

| Endpoint | Méthode | Description |
|----------|---------|-------------|
| `/api/livreur/login.php` | POST | Connexion → token |
| `/api/livreur/me.php` | GET | Profil + livraison active |
| `/api/livreur/start-delivery.php` | POST | Démarrer suivi |
| `/api/livreur/stop-delivery.php` | POST | Arrêter suivi |
| `/api/livreur/logout.php` | POST | Déconnexion |

## Connexion Socket.io

**Livreur** (app) :

```javascript
io('https://sugar-paper.com', {
  path: '/socket.io',
  auth: { role: 'livreur', token: 'TOKEN_API' }
});
socket.emit('livreur:position', { commande_id: 1, latitude: 14.69, longitude: -17.44 });
```

**Admin / client** (page web) :

```javascript
io('https://sugar-paper.com', {
  path: '/socket.io',
  auth: { role: 'watch', token: WATCH_TOKEN, commande_id: 1 }
});
socket.on('position:update', (data) => { /* mettre à jour la carte */ });
```

## Dépannage

| Problème | Solution |
|----------|----------|
| `auth_failed` Socket.io | Vérifier que Node atteint PHP (`TRACKING_PHP_BASE`) |
| 403 verify-livreur | `internal_secret` différent entre PHP et `.env` |
| WebSocket coupe | Vérifier bloc Nginx `/socket.io/` et PM2 actif |
| Module non installé | Relancer `php migrations/run_add_livreur_tracking.php` |

## Fichiers ignorés par git

- `config/tracking.php`
- `tracking-server/.env`
- `tracking-server/node_modules/`
