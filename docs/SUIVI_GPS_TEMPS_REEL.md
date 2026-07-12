# Suivi GPS livreurs en temps réel — Documentation complète

Documentation de référence pour le module de **géolocalisation en temps réel** des livreurs, déployé sur **sugar-paper.com** (VPS Webuzo) et développé dans le projet PHP `site_gateau`.

**Date de validation en production :** juillet 2026  
**Statut :** fonctionnel — suivi GPS + Socket.io temps réel confirmé en prod

---

## Table des matières

1. [Objectif et périmètre](#1-objectif-et-périmètre)
2. [Architecture globale](#2-architecture-globale)
3. [Environnements](#3-environnements)
4. [Base de données](#4-base-de-données)
5. [Configuration PHP](#5-configuration-php)
6. [Serveur Node.js (Socket.io)](#6-serveur-nodejs-socketio)
7. [Reverse proxy Nginx / Apache (Webuzo)](#7-reverse-proxy-nginx--apache-webuzo)
8. [Flux fonctionnel détaillé](#8-flux-fonctionnel-détaillé)
9. [API PHP](#9-api-php)
10. [Événements Socket.io](#10-événements-socketio)
11. [Interface admin (page suivi)](#11-interface-admin-page-suivi)
12. [Itinéraires sans péage](#12-itinéraires-sans-péage)
13. [Statuts affichés à l'utilisateur](#13-statuts-affichés-à-lutilisateur)
14. [Déploiement pas à pas (VPS Webuzo)](#14-déploiement-pas-à-pas-vps-webuzo)
15. [Installation fraîche (`install-vps-fresh.sh`)](#15-installation-fraîche-install-vps-freshsh)
16. [Checklist de vérification (partie par partie)](#16-checklist-de-vérification-partie-par-partie)
17. [Vérifications et commandes utiles](#17-vérifications-et-commandes-utiles)
18. [Problèmes rencontrés et solutions](#18-problèmes-rencontrés-et-solutions)
19. [Fichiers du projet](#19-fichiers-du-projet)
20. [Sécurité](#20-sécurité)
21. [Évolutions prévues](#21-évolutions-prévues)

---

## 1. Objectif et périmètre

### Ce que fait le module

- Permet à un **livreur connecté en admin** de démarrer une livraison et de **partager sa position GPS** en continu.
- Affiche sur une **carte Leaflet** plein écran :
  - la position du livreur (marqueur mobile),
  - la destination client,
  - un **itinéraire sans autoroutes à péage** (Valhalla / OpenRouteService),
  - une estimation de temps de trajet (ETA).
- Diffuse les positions en **temps réel** via **Socket.io** (serveur Node.js) vers les observateurs (admin sur la page suivi, futur client / app mobile).
- Persiste les positions en **base MySQL** pour historique et reprise au rechargement.

### Ce qui est hors périmèche (pour l'instant)

- Application mobile Flutter (API livreur préparée, non finalisée).
- Upgrade WebSocket complet sur Webuzo (le **polling seul** suffit et est utilisé).
- Paiement, notifications email lors du suivi.

### Fonctionnalités actives (juillet 2026)

- Suivi admin livreur (`admin/livreurs/suivi.php`) avec autostart `&autostart=1`
- Mode observation admin (`&regarder=1`) depuis détails commande ou facture
- Partage lien public client : `suivi-livraison.php` + `api/tracking/share-link.php`
- Zoom dynamique carte selon vitesse GPS (mode navigation livreur)

### Principe architectural

| Couche | Technologie | Rôle |
|--------|-------------|------|
| Métier, auth, BDD | PHP pur (procédural) | CRUD, tokens, permissions, persistance |
| Temps réel | Node.js + Socket.io | Rooms, diffusion `position:update` |
| Carte / UX | Leaflet + JS | Affichage, GPS navigateur, popup erreurs |
| Proxy | Nginx → Node / Apache → PHP | HTTPS, `/socket.io/` |

---

## 2. Architecture globale

```
┌─────────────────────────────────────────────────────────────────────────┐
│  Navigateur admin (HTTPS sugar-paper.com)                                  │
│  ┌──────────────────┐    ┌──────────────────┐    ┌─────────────────┐ │
│  │ Geolocation API  │    │ Socket.io client │    │ Leaflet + route │ │
│  │ (GPS navigateur) │    │ (polling only)   │    │ API PHP         │ │
│  └────────┬─────────┘    └────────┬─────────┘    └────────┬────────┘ │
└───────────┼───────────────────────┼─────────────────────────┼──────────┘
            │ POST JSON             │ WSS/HTTPS polling       │ GET
            ▼                       ▼                         ▼
┌───────────────────────────────────────────────────────────────────────────┐
│  Nginx (443) — sugar-paper.com                                              │
│    /                    → Apache PHP (127.0.0.1:8081)                     │
│    /socket.io/          → Node.js (127.0.0.1:3001)                        │
└───────────────────────────────────────────────────────────────────────────┘
            │                       │
            ▼                       ▼
┌─────────────────────┐   ┌─────────────────────────────────────────────┐
│  Apache + PHP       │   │  Node.js PM2 « sugar-tracking »             │
│  - api/tracking/*   │◄──│  - Auth via PHP (verify-livreur/watch)      │
│  - livreur-web.php  │   │  - Rooms commande_{id} / bl_{id}            │
│  - models + MySQL   │   │  - persist-position → PHP                   │
└─────────────────────┘   └─────────────────────────────────────────────┘
            │
            ▼
      MySQL (MariaDB)
      commandes, livreur_positions, tracking_watch_tokens, …
```

### Secret partagé PHP ↔ Node

Un **`internal_secret`** identique dans :

- `config/tracking.php` (PHP)
- `tracking-server/.env` → `TRACKING_INTERNAL_SECRET`

Node appelle PHP en POST avec l'en-tête `X-Tracking-Secret` et le corps JSON `internal_secret`. PHP vérifie via `tracking_verify_internal_request()`.

### Particularité Webuzo (critique)

Sur le VPS **Webuzo**, Apache PHP n'écoute **pas** sur le port 80 public mais sur :

| Port | Usage |
|------|--------|
| **8081** | Apache HTTP interne → **utiliser pour Node → PHP** |
| **8082** | Apache HTTPS interne → **ne pas utiliser** pour les appels Node |
| **3001** | Node.js Socket.io (localhost uniquement) |

Node doit envoyer l'en-tête HTTP **`Host: sugar-paper.com`** pour que le virtual host Apache route correctement vers le bon site. **`fetch()` Node ignore cet en-tête** — d'où l'utilisation du module natif `http` dans `server.js`.

---

## 3. Environnements

### Production (sugar-paper.com)

| Élément | Valeur |
|---------|--------|
| Chemin projet | `/home/jomas/sugar-paper.com` |
| URL publique | `https://sugar-paper.com` |
| Node PM2 | `sugar-tracking` (mode **fork**, 1 instance) |
| Port Node | `3001` |
| PHP interne Node | `http://127.0.0.1:8081` + `Host: sugar-paper.com` |
| Config Nginx custom | `/var/webuzo-data/nginx/custom/domains/sugar-paper.com.conf` |
| Config Apache custom | `/var/webuzo-data/apache2/custom/domains/sugar-paper.com.conf` |

### Développement local (WAMP)

| Élément | Valeur typique |
|---------|----------------|
| PHP | `http://127.0.0.1` ou virtual host local |
| Node | `TRACKING_PHP_BASE=http://127.0.0.1`, `TRACKING_PHP_HOST=` (vide) |
| Pas de PM2 obligatoire | `cd tracking-server && node server.js` |

---

## 4. Base de données

### Migration principale

```bash
php migrations/run_add_livreur_tracking.php
```

Crée / met à jour :

#### Table `livreurs`

Comptes livreurs pour **l'application mobile** (email, mot de passe hashé).

#### Table `livreur_sessions`

Tokens API livreur (app mobile). FK vers `livreurs.id`.

#### Table `livreur_positions`

Historique des positions GPS.

| Colonne | Description |
|---------|-------------|
| `livreur_id` | **`admin.id`** (suivi web) **ou** `livreurs.id` (app mobile) |
| `commande_id` | Commande associée (nullable) |
| `bl_id` | Bon de livraison / facture B2B (nullable) |
| `latitude`, `longitude` | Coordonnées |
| `accuracy`, `speed`, `heading` | Métadonnées GPS |
| `recorded_at` | Horodatage |

> **Important :** le suivi **web admin** stocke `admin.id` dans `livreur_id`. Une ancienne FK `livreur_positions → livreurs.id` bloquait les INSERT. Migration corrective :

```bash
php migrations/run_fix_livreur_positions_fk.php
```

#### Table `tracking_watch_tokens`

Tokens temporaires pour rejoindre une room Socket.io en mode **watch** (admin ou client).

#### Colonnes ajoutées sur `commandes`

| Colonne | Type | Description |
|---------|------|-------------|
| `livreur_id` | INT NULL | **ID admin** assigné comme livreur |
| `delivery_latitude` | DECIMAL | Latitude destination |
| `delivery_longitude` | DECIMAL | Longitude destination |
| `tracking_active` | TINYINT(1) | 1 = suivi GPS actif |
| `tracking_started_at` | DATETIME | Début du suivi |

Colonnes équivalentes sur `bons_livraison` pour les factures B2B.

### Exemple d'état validé en prod

```sql
SELECT id, livreur_id, tracking_active FROM commandes WHERE id IN (73, 75);
-- id=73, livreur_id=1, tracking_active=1
-- id=75, livreur_id=1, tracking_active=1
```

**Condition métier :** l'admin connecté doit avoir `admin_id = commandes.livreur_id` pour gérer la livraison.

---

## 5. Configuration PHP

### Fichier à créer (non versionné)

```bash
cp config/tracking.example.php config/tracking.php
```

### Exemple production (`config/tracking.php`)

```php
return [
    'internal_secret' => 'CLE_GENEREE_avec_openssl_rand_hex_32',
    'node_host' => '127.0.0.1',
    'node_port' => 3001,
    'socket_path' => '/socket.io',
    'public_site_url' => 'https://sugar-paper.com',
    'socket_url' => 'https://sugar-paper.com',
    'livreur_token_ttl_hours' => 720,
    'watch_token_ttl_minutes' => 480,
    'cors_origins' => [
        'https://sugar-paper.com',
        'https://www.sugar-paper.com',
    ],
];
```

### Génération du secret

```bash
openssl rand -hex 32
```

### Fonctions clés (`includes/tracking_config.php`)

| Fonction | Rôle |
|----------|------|
| `tracking_load_config()` | Charge `config/tracking.php` |
| `tracking_internal_secret()` | Retourne le secret |
| `tracking_verify_internal_request()` | Valide les appels Node → PHP |
| `tracking_realtime_available()` | `true` si config présente et secret valide |

La page suivi n'affiche **« Suivi en temps réel actif »** que si Socket.io est **réellement connecté**, pas seulement si `tracking_active=1` en BDD.

---

## 6. Serveur Node.js (Socket.io)

### Emplacement

```
tracking-server/
├── server.js              # Serveur Socket.io
├── ecosystem.config.cjs   # Configuration PM2
├── package.json
├── .env.example
├── .env                   # Non versionné (prod)
└── nginx-snippet.conf     # Exemple bloc Nginx
```

### Dépendances

- Node.js **≥ 18**
- `socket.io` ^4.8.1
- `dotenv` ^16.4.7

### Installation

```bash
cd tracking-server
cp .env.example .env
# Éditer .env (voir ci-dessous)
npm install
```

### Fichier `.env` production (sugar-paper.com)

> **Important :** créez le fichier **en SSH** (`cat > .env`), pas uniquement via l’éditeur Webuzo.
> L’éditeur du panneau peut produire un fichier illisible par Node (encodage, BOM, placeholder non remplacé).

```bash
cd /home/jomas/sugar-paper.com/tracking-server

cat > .env << 'EOF'
TRACKING_PORT=3001
TRACKING_SOCKET_PATH=/socket.io

TRACKING_PHP_BASE=http://127.0.0.1:8081
TRACKING_PHP_HOST=sugar-paper.com

TRACKING_INTERNAL_SECRET=MEME_CLE_QUE_config_tracking_php

TRACKING_CORS_ORIGINS=https://sugar-paper.com,https://www.sugar-paper.com
EOF

chmod 600 .env
chown jomas:jomas .env
```

Contenu attendu :

```env
TRACKING_PORT=3001
TRACKING_SOCKET_PATH=/socket.io

TRACKING_PHP_BASE=http://127.0.0.1:8081
TRACKING_PHP_HOST=sugar-paper.com

TRACKING_INTERNAL_SECRET=MEME_CLE_QUE_config_tracking_php

TRACKING_CORS_ORIGINS=https://sugar-paper.com,https://www.sugar-paper.com
```

Vérifier que Node lit le secret :

```bash
node -e "require('dotenv').config({path:'.env'}); console.log(process.env.TRACKING_INTERNAL_SECRET ? 'SECRET OK' : 'SECRET MANQUANT');"
```

`server.js` charge explicitement `.env` depuis le dossier `tracking-server/` :

```javascript
require('dotenv').config({ path: path.join(__dirname, '.env') });
```

### PM2

```bash
cd /home/jomas/sugar-paper.com/tracking-server
npm install --omit=dev

# Premier démarrage ou après correction du .env : delete puis start (pas seulement restart)
pm2 delete sugar-tracking 2>/dev/null || true
pm2 start ecosystem.config.cjs
pm2 save
pm2 startup    # une seule fois — enregistre le service systemd
```

Nettoyer les anciens logs d’erreur (ex. secret manquant avant correction) :

```bash
pm2 flush sugar-tracking
```

Configuration PM2 (`ecosystem.config.cjs`) :

- Nom : **`sugar-tracking`**
- Mode : **`fork`** (obligatoire — pas cluster)
- Autorestart : oui
- Mémoire max : 200 Mo

### Endpoint health

```
GET http://127.0.0.1:3001/health
→ {"ok":true,"service":"tracking-socket"}
```

Au démarrage, Node teste PHP via `verify-livreur.php` et log :

```
[tracking] PHP joignable (http://127.0.0.1:8081, Host: sugar-paper.com)
```

### Appels PHP depuis Node

`server.js` utilise `http.request()` (pas `fetch`) avec :

- URL : `TRACKING_PHP_BASE` + chemin API
- Headers : `Content-Type`, `X-Tracking-Secret`, **`Host: TRACKING_PHP_HOST`**

Endpoints appelés :

| Chemin | Usage |
|--------|--------|
| `/api/tracking/verify-livreur.php` | Auth token app mobile |
| `/api/tracking/verify-watch.php` | Auth token page suivi admin |
| `/api/tracking/persist-position.php` | Sauvegarde position en BDD |

---

## 7. Reverse proxy Nginx / Apache (Webuzo)

### Bloc Nginx à ajouter

Fichier : `/var/webuzo-data/nginx/custom/domains/sugar-paper.com.conf`

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
    proxy_buffering off;
}
```

Recharger Nginx (Webuzo — `systemctl reload nginx` peut échouer) :

```bash
nginx -t
/usr/local/apps/nginx/sbin/nginx -s reload
# Alternative : service nginx reload
# Ou redémarrage Nginx depuis le panneau Webuzo
```

### WebSocket vs polling

Sur Webuzo, l'**upgrade WebSocket** peut échouer. Le client admin est configuré en **polling uniquement** :

```javascript
transports: ['polling'],
upgrade: false,
```

Le temps réel fonctionne correctement en polling. Test :

```bash
curl "https://sugar-paper.com/socket.io/?EIO=4&transport=polling"
# → HTTP 200 avec payload Engine.IO
```

### Option WebSocket (non requis)

Si upgrade WebSocket souhaité plus tard, ajouter dans la config Nginx globale (`http {}`) :

```nginx
map $http_upgrade $connection_upgrade {
    default upgrade;
    ''      close;
}
```

Puis remplacer `Connection "upgrade"` par `Connection $connection_upgrade`.

---

## 8. Flux fonctionnel détaillé

### 8.1 Préparation d'une livraison

1. Admin ouvre `/admin/livreurs/index.php`.
2. Une commande est assignée à un livreur (`commandes.livreur_id = admin.id`).
3. L'adresse client doit être **géolocalisée** (`delivery_latitude`, `delivery_longitude`).
4. Admin ouvre `/admin/livreurs/suivi.php?commande_id=X`.

### 8.2 Démarrage du suivi (bouton « Démarrer la livraison »)

```
Navigateur                          PHP                           Node
    │                                 │                             │
    │ POST livreur-web.php            │                             │
    │ action=start                    │                             │
    ├────────────────────────────────►│ UPDATE tracking_active=1    │
    │                                 │                             │
    │ Geolocation watchPosition       │                             │
    │                                 │                             │
    │ POST livreur-web.php            │                             │
    │ action=position (lat,lng)         │                             │
    ├────────────────────────────────►│ INSERT livreur_positions    │
    │                                 │                             │
    │ GET watch-token.php             │                             │
    ├────────────────────────────────►│ token watch généré          │
    │                                 │                             │
    │ Socket.io connect               │                             │
    │ auth: role=watch, token         │                             │
    ├─────────────────────────────────┼────────────────────────────►│
    │                                 │◄── verify-watch.php ────────│
    │◄── watch:ready ─────────────────┼─────────────────────────────│
    │                                 │                             │
    │ Statut UI : « Temps réel actif »│                             │
```

### 8.3 Pendant la livraison

- Le navigateur envoie périodiquement la position via `livreur-web.php` (`action=position`).
- En mode **app mobile** (futur), le livreur émet `livreur:position` sur Socket.io ; Node diffuse `position:update` à la room `commande_{id}` et appelle `persist-position.php`.

### 8.4 Reprise après rechargement

Si `tracking_active=1` en BDD au chargement de la page :

- `resumeActiveTracking()` relance le watch GPS.
- Reconnexion Socket.io automatique.
- Démarrage idempotent : `livreur_start_web_tracking()` retourne OK si déjà actif.

### 8.5 Arrêt (bouton « Terminer »)

- Visible uniquement si **temps réel connecté**.
- `POST livreur-web.php` `action=stop` → `tracking_active=0`.
- Déconnexion Socket.io, arrêt du watch GPS.

---

## 9. API PHP

### API interne (Node uniquement — secret requis)

| Endpoint | Méthode | Description |
|----------|---------|-------------|
| `/api/tracking/ping.php` | **POST** | Test interne Node → PHP (secret requis) |
| `/api/tracking/verify-livreur.php` | POST | Valide token session app mobile |
| `/api/tracking/verify-watch.php` | POST | Valide token watch admin/client |
| `/api/tracking/persist-position.php` | POST | Enregistre une position |
| `/api/tracking/share-link.php` | POST | Génère lien public `suivi-livraison.php` |

### API web livreur (session admin)

| Endpoint | Méthode | Body JSON | Description |
|----------|---------|-----------|-------------|
| `/api/tracking/livreur-web.php` | POST | `action: start` | Active `tracking_active` |
| `/api/tracking/livreur-web.php` | POST | `action: stop` | Désactive le suivi |
| `/api/tracking/livreur-web.php` | POST | `action: position` | Enregistre lat/lng/accuracy |

Paramètres communs : `commande_id` ou `bl_id`.

Réponses erreur fréquentes :

| Message | Cause |
|---------|--------|
| `Accès refusé` | `admin_id ≠ livreur_id` |
| `Suivi GPS inactif` | `tracking_active=0` |
| `Enregistrement position impossible` | Échec INSERT (ex. ancienne FK) |
| `Impossible d'activer le suivi GPS` | Commande non assignée au livreur |

### API watch token (session admin)

```
GET /api/tracking/watch-token.php?commande_id=73
→ { success, watch_token, expires_at, commande, last_position, socket_path }
```

### API livreur mobile (préparée — app Flutter)

| Endpoint | Description |
|----------|-------------|
| `/api/livreur/login.php` | Connexion → token |
| `/api/livreur/me.php` | Profil + livraison active |
| `/api/livreur/start-delivery.php` | Démarrer suivi |
| `/api/livreur/stop-delivery.php` | Arrêter suivi |
| `/api/livreur/logout.php` | Déconnexion |

### API itinéraire

```
GET /api/routing/directions.php?from_lat=…&from_lng=…&to_lat=…&to_lng=…
```

Session admin + permission `admin_can_livreur_gps()` requises.

---

## 10. Événements Socket.io

### Authentification à la connexion

**Livreur (app mobile) :**

```javascript
io('https://sugar-paper.com', {
  path: '/socket.io',
  transports: ['polling'],
  auth: { role: 'livreur', token: 'TOKEN_API' }
});
```

**Admin (page suivi) :**

```javascript
io('https://sugar-paper.com', {
  path: '/socket.io',
  transports: ['polling'],
  upgrade: false,
  auth: {
    role: 'watch',
    token: WATCH_TOKEN,
    commande_id: 73,
    bl_id: 0
  }
});
```

### Rooms

| Room | Membres |
|------|---------|
| `livreur_{id}` | Socket livreur |
| `commande_{id}` | Livreur + observateurs de la commande |
| `bl_{id}` | Livreur + observateurs facture B2B |

### Événements

| Événement | Direction | Payload |
|-----------|-----------|---------|
| `watch:ready` | Serveur → admin | coords destination, dernière position, tracking_active |
| `livreur:ready` | Serveur → livreur | commande_id, tracking_active |
| `livreur:position` | Livreur → serveur | lat, lng, commande_id, accuracy… |
| `position:update` | Serveur → room | position diffusée à tous les watchers |
| `livreur:join_commande` | Livreur → serveur | changement de commande active |
| `disconnect` | — | admin repasse en « temps réel inactif » si livraison en cours |

---

## 11. Interface admin (page suivi)

### URL

```
/admin/livreurs/suivi.php?commande_id=73
/admin/livreurs/suivi.php?bl_id=XX        # factures B2B
```

### Fichiers UI

| Fichier | Rôle |
|---------|------|
| `admin/livreurs/suivi.php` | Page PHP, config JS `LIVREUR_TRACKING_CONFIG` |
| `js/admin-livreur-suivi.js` | Carte, GPS, Socket.io, boutons, popup erreurs |
| `js/livreur-route-api.js` | Appels itinéraire |
| `css/admin-livreur-suivi.css` | Styles plein écran |

### Configuration JS injectée

```javascript
window.LIVREUR_TRACKING_CONFIG = {
  commandeId, blId, livraisonType,
  socketUrl,           // public_site_url
  socketPath,          // /socket.io
  watchTokenUrl,
  webApiUrl,           // /api/tracking/livreur-web.php
  canManage,           // admin_id === livreur_id
  geoReady,            // coords destination OK
  realtimeConfigured,  // tracking.php valide
  trackingActive,      // état BDD initial
  deliveryLat, deliveryLng,
  defaultCenter, defaultZoom
};
```

### Logique des boutons

| État | Bouton visible |
|------|----------------|
| Temps réel **non** connecté | **Démarrer la livraison** |
| Temps réel **connecté** | **Terminer** |
| Autostart + échec Socket.io | **Démarrer** (retry manuel) — pas Terminer |
| Adresse non géolocalisée | Démarrer désactivé |
| Admin ≠ livreur assigné | Pas de boutons de gestion |
| Mode `regarder=1` (admin observe) | Pas de démarrage ; bouton **Partager** le suivi public |

### Popup d'erreurs

Mini-modal au clic « Démarrer » en cas d'échec :

- GPS refusé / indisponible
- Config temps réel absente
- Socket.io injoignable (avec détails : URL, path, message erreur)
- Token watch refusé

### Cache navigateur

- `admin-livreur-suivi.js` : version forcée via `filemtime()` dans `suivi.php`
- Autres assets : `includes/asset_version.php` (scan `css/` + `js/`)

---

## 12. Itinéraires sans péage

### Configuration (`config/routing.php` optionnel)

Copier `config/routing.example.php` :

```php
return [
    'valhalla_url' => 'https://valhalla1.openstreetmap.de/route',
    'openrouteservice_api_key' => '',
    'avoid_tolls' => true,
];
```

### Algorithme (`includes/livreur_routing.php`)

1. Si clé OpenRouteService → routing avec évitement péages.
2. Sinon → **Valhalla** public avec `use_tolls: 0`.
3. Retour : polyline, distance, durée estimée.

---

## 13. Statuts affichés à l'utilisateur

Deux concepts **distincts** :

| Concept | Source | Libellé UI |
|---------|--------|------------|
| Suivi GPS BDD | `tracking_active=1` | Livraison en cours |
| Temps réel Socket.io | Connexion `io()` active | **« Suivi en temps réel actif »** |

Le statut « temps réel actif » n'est affiché **que** si Socket.io est connecté (`realtimeConnected=true`), même si le GPS navigateur fonctionne et envoie des positions à PHP.

---

## 14. Déploiement pas à pas (VPS Webuzo)

### Prérequis

| Outil | Version minimale | Vérification |
|-------|------------------|--------------|
| Node.js | ≥ 18 | `node -v` |
| npm | récent | `npm -v` |
| PM2 | installé global | `pm2 -v` (`npm install -g pm2`) |
| Nginx + Apache | Webuzo | `ss -tlnp \| grep httpd` → ports **8081** et **8082** |

### Ordre des opérations (résumé)

```
1. Code (git clone ou git pull)
2. conn/conn.php + fichiers sensibles
3. Migrations BDD (commandes + factures B2B si besoin)
4. config/tracking.php
5. tracking-server/.env (même secret)
6. npm install + PM2
7. Nginx location /socket.io/
8. Vérifications couche par couche (section 16)
9. Test navigateur
```

### Checklist commandes

```bash
# 1. Code
cd /home/jomas/sugar-paper.com
git pull   # ou install-vps-fresh.sh pour une install neuve

# 2. Migrations BDD — commandes
php migrations/run_add_livreur_tracking.php
php migrations/run_fix_livreur_positions_fk.php

# 2b. Migrations BDD — factures B2B (si module Invoice utilisé)
php migrations/run_migrate_invoice_bl.php    # si table bons_livraison absente
php migrations/run_add_livreur_tracking.php  # relancer pour colonnes GPS sur bons_livraison

# 3. Config PHP
cp config/tracking.example.php config/tracking.php
nano config/tracking.php
chmod 640 config/tracking.php
chown jomas:jomas config/tracking.php

# 4. Config Node — voir section 6 (.env en SSH)
cd tracking-server
# cat > .env … (voir section 6)
npm install --omit=dev
pm2 delete sugar-tracking 2>/dev/null || true
pm2 start ecosystem.config.cjs
pm2 save

# 5. Nginx — voir section 7
nginx -t
/usr/local/apps/nginx/sbin/nginx -s reload

# 6. Vérifications — voir section 16
curl http://127.0.0.1:3001/health
curl "https://sugar-paper.com/socket.io/?EIO=4&transport=polling"
php scripts/tracking_diagnostic.php {bl_id}
```

### Test fonctionnel navigateur

1. Connexion admin dont **`admin_id` = `livreur_id`** de la livraison.
2. Ouvrir :
   - Commande : `https://sugar-paper.com/admin/livreurs/suivi.php?commande_id={id}`
   - Facture : `https://sugar-paper.com/admin/livreurs/suivi.php?bl_id={id}`
   - Autostart : ajouter `&autostart=1`
3. Autoriser la géolocalisation du navigateur.
4. Cliquer **Démarrer la livraison** (ou autostart si Socket.io OK).
5. Vérifier : carte, itinéraire rose, **« Suivi en temps réel actif »**, bouton **Terminer**.

### Mises à jour quotidiennes (sans tout réinstaller)

```bash
cd /home/jomas/sugar-paper.com
bash scripts/deploy.sh
```

Ce script fait `git pull`, `composer`, `npm` dans `tracking-server/` et `pm2 restart sugar-tracking`.
Les fichiers sensibles (`config/tracking.php`, `.env`) ne sont **pas** écrasés.

---

## 15. Installation fraîche (`install-vps-fresh.sh`)

Après `bash scripts/install-vps-fresh.sh`, le code Git est propre mais **tous les fichiers sensibles et données utilisateur sont supprimés**.

### Fichiers à remettre manuellement (obligatoires tracking)

| Fichier | Rôle |
|---------|------|
| `conn/conn.php` | Connexion MySQL |
| `config/tracking.php` | Secret PHP, URL publique, CORS |
| `tracking-server/.env` | Secret Node, port Apache 8081, host domaine |

Liste complète : `scripts/FICHIERS_SENSIBLES.md`

### Génération du secret partagé

```bash
openssl rand -hex 32
```

Copier **exactement la même valeur** dans :
- `config/tracking.php` → `internal_secret`
- `tracking-server/.env` → `TRACKING_INTERNAL_SECRET`

### Domaine sugar-paper.com — où le configurer

| Élément | Où |
|---------|-----|
| Domaine du site (vhost, SSL, racine web) | **Panneau Webuzo** → domaine `sugar-paper.com` → `/home/jomas/sugar-paper.com` |
| URL publique / liens partage | `config/tracking.php` → `public_site_url`, `socket_url` |
| Node → PHP (en-tête Host) | `tracking-server/.env` → `TRACKING_PHP_HOST=sugar-paper.com` |
| Proxy Socket.io | Nginx : `/var/webuzo-data/nginx/custom/domains/sugar-paper.com.conf` |
| Secret partagé | `config/tracking.php` + `tracking-server/.env` |

> Le secret **n’est pas** dans Apache/Nginx — uniquement dans les deux fichiers ci-dessus.

### Erreur fréquente après install fraîche

| Symptôme | Cause |
|----------|--------|
| `TRACKING_INTERNAL_SECRET manquant` | `.env` absent ou créé via Webuzo avec placeholder |
| `curl :3001/health` échoue | Node crash → PM2 redémarre en boucle (↺ élevé) |
| Socket.io → 500 HTML | Node pas sur le port 3001 |
| `livreur_bl_livraison_columns_ok : non` | Table `bons_livraison` absente ou migration non relancée |
| BL #X introuvable | Données effacées — recréer factures/commandes en admin |

---

## 16. Checklist de vérification (partie par partie)

Cocher dans cet ordre. Chaque étape doit être **OK** avant la suivante.

### Partie 1 — Prérequis système

```bash
node -v          # ≥ 18
npm -v
pm2 -v
ss -tlnp | grep httpd   # 8081 (HTTP interne) et 8082 (HTTPS interne)
```

Test Apache port **8081** (bon virtual host) :

```bash
curl -X POST http://127.0.0.1:8081/api/tracking/verify-livreur.php \
  -H "Content-Type: application/json" \
  -H "Host: sugar-paper.com" \
  -d '{"token":"test"}'
```

**Attendu :** `{"valid":false,"error":"unauthorized"}` (JSON, pas HTML 404).

Port **8082** : ne pas utiliser pour Node → PHP (erreur SSL « plain HTTP to SSL port »).

---

### Partie 2 — Base de données

```bash
cd /home/jomas/sugar-paper.com
php -r "require 'conn/conn.php'; echo 'BDD OK';"
php migrations/run_add_livreur_tracking.php
php migrations/run_fix_livreur_positions_fk.php
```

Tables et colonnes commandes :

```bash
php -r "
require 'conn/conn.php';
foreach (['livreur_positions','tracking_watch_tokens'] as \$t) {
  echo \$t . ': ' . (\$db->query(\"SHOW TABLES LIKE '\$t'\")->fetch() ? 'OK' : 'MANQUANT') . PHP_EOL;
}
foreach (['livreur_id','delivery_latitude','delivery_longitude','tracking_active'] as \$c) {
  echo \$c . ': ' . (\$db->query(\"SHOW COLUMNS FROM commandes LIKE '\$c'\")->fetch() ? 'OK' : 'MANQUANT') . PHP_EOL;
}
"
```

Module factures B2B (si suivi `bl_id`) :

```bash
php -r "
require 'conn/conn.php';
require 'models/model_livreur_tracking.php';
echo 'bons_livraison: ' . (\$db->query(\"SHOW TABLES LIKE 'bons_livraison'\")->fetch() ? 'OK' : 'ABSENTE') . PHP_EOL;
echo 'livreur_bl_livraison_columns_ok: ' . (livreur_bl_livraison_columns_ok() ? 'oui' : 'non') . PHP_EOL;
"
```

Si `bons_livraison: ABSENTE` → `php migrations/run_migrate_invoice_bl.php` puis relancer `run_add_livreur_tracking.php`.

---

### Partie 3 — Fichiers sensibles

```bash
ls -la conn/conn.php
ls -la config/tracking.php
ls -la tracking-server/.env    # fichier caché — utiliser ls -la
```

---

### Partie 4 — Configuration PHP

```bash
php -r "
require 'includes/tracking_config.php';
\$s = tracking_internal_secret();
echo strlen(\$s) . ' chars — ';
echo (\$s !== '' && \$s !== 'REMPLACEZ_PAR_UNE_CLE_SECRETE_LONGUE_ET_ALEATOIRE') ? 'OK' : 'MANQUANT';
echo PHP_EOL;
echo 'realtime_available: ' . (tracking_realtime_available() ? 'oui' : 'non') . PHP_EOL;
"
```

**Attendu :** `64 chars — OK` et `realtime_available: oui`

Points de contrôle `config/tracking.php` :
- `public_site_url` = `https://sugar-paper.com` (sans slash final)
- `socket_url` = `https://sugar-paper.com` (proxy Nginx)
- `node_port` = `3001`
- `cors_origins` sans slash final

---

### Partie 5 — Configuration Node (`.env`)

```bash
cd /home/jomas/sugar-paper.com/tracking-server
node -e "require('dotenv').config({path:'.env'}); echo process.env.TRACKING_INTERNAL_SECRET ? 'SECRET OK' : 'SECRET MANQUANT';"

php -r "\$c=require '../config/tracking.php'; echo \$c['internal_secret'];"
echo ""
grep TRACKING_INTERNAL_SECRET .env
```

**Attendu :** même chaîne dans PHP et `.env`.

---

### Partie 6 — Node.js + PM2

```bash
cd tracking-server
npm install --omit=dev
pm2 delete sugar-tracking 2>/dev/null || true
pm2 start ecosystem.config.cjs
pm2 list
```

**Attendu PM2 :**

| Champ | Valeur |
|-------|--------|
| name | `sugar-tracking` |
| status | `online` |
| ↺ restarts | **0** (pas de boucle de crash) |
| mode | `fork` |

Logs :

```bash
pm2 logs sugar-tracking --lines 10 --nostream
```

**Attendu (out.log) :**
```
[tracking] Socket.io écoute sur 127.0.0.1:3001 path=/socket.io
[tracking] PHP joignable (http://127.0.0.1:8081, Host: sugar-paper.com)
```

**Pas attendu (error.log) :** `TRACKING_INTERNAL_SECRET manquant dans .env`

Health local :

```bash
curl http://127.0.0.1:3001/health
```

**Attendu :** `{"ok":true,"service":"tracking-socket"}`

---

### Partie 7 — Nginx (proxy Socket.io)

Fichier : `/var/webuzo-data/nginx/custom/domains/sugar-paper.com.conf`

```bash
nginx -t
/usr/local/apps/nginx/sbin/nginx -s reload
curl "https://sugar-paper.com/socket.io/?EIO=4&transport=polling"
```

**Attendu :** réponse commençant par `0{"sid":` (Engine.IO), **pas** page HTML 500.

---

### Partie 8 — API PHP interne

> `api/tracking/ping.php` exige **POST**, pas GET.

```bash
SECRET=$(php -r "require '/home/jomas/sugar-paper.com/includes/tracking_config.php'; echo tracking_internal_secret();")

curl -X POST http://127.0.0.1:8081/api/tracking/ping.php \
  -H "Content-Type: application/json" \
  -H "Host: sugar-paper.com" \
  -H "X-Tracking-Secret: $SECRET"
```

**Attendu :** `{"ok":true,"service":"tracking-php","realtime":true}`

Diagnostic facture (remplacer `{id}` par un BL existant) :

```bash
cd /home/jomas/sugar-paper.com
php scripts/tracking_diagnostic.php {bl_id}
```

Lister les livraisons disponibles :

```bash
php -r "
require 'conn/conn.php';
echo \"--- Commandes ---\n\";
print_r(\$db->query('SELECT id, numero_commande, livreur_id FROM commandes WHERE livreur_id IS NOT NULL ORDER BY id DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC));
echo \"--- Factures BL ---\n\";
\$t = \$db->query(\"SHOW TABLES LIKE 'bons_livraison'\")->fetch();
if (\$t) print_r(\$db->query('SELECT id, numero_bl, livreur_id FROM bons_livraison ORDER BY id DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC));
else echo 'Table bons_livraison absente\n';
"
```

---

### Partie 9 — Test navigateur (bout en bout)

| Étape | Attendu |
|-------|---------|
| Carte Leaflet + itinéraire rose | Visible |
| Autorisation GPS navigateur | Demandée |
| Clic « Démarrer la livraison » | Pas d’erreur Socket.io |
| Statut | **« Suivi en temps réel actif »** |
| Bouton | **Terminer** visible |

**Comportement autostart (`&autostart=1`) :** si Socket.io échoue à l’autostart, le bouton **Démarrer la livraison** reste affiché (pas **Terminer** directement). Le livreur peut relancer manuellement ; le GPS peut fonctionner même si le temps réel est inactif.

---

### Récapitulatif checklist

```
□ 1. Prérequis Node/PM2/ports Apache
□ 2. BDD + migrations (commandes + bons_livraison si factures)
□ 3. Fichiers sensibles présents
□ 4. config/tracking.php valide
□ 5. tracking-server/.env — secret identique
□ 6. PM2 online, ↺ 0, health OK
□ 7. Nginx /socket.io/ + curl public OK
□ 8. ping.php POST OK + diagnostic
□ 9. Test navigateur — temps réel actif
```

---

## 17. Vérifications et commandes utiles

### PM2

```bash
pm2 list
pm2 logs sugar-tracking
pm2 restart sugar-tracking
```

### BDD

```bash
php -r "
require 'conn/conn.php';
\$s = \$db->query('SELECT id, livreur_id, tracking_active FROM commandes WHERE id=73')->fetch(PDO::FETCH_ASSOC);
print_r(\$s);
"
```

### Test INSERT positions (après fix FK)

```bash
php -r "
require 'conn/conn.php';
\$db->exec('INSERT INTO livreur_positions (livreur_id, commande_id, latitude, longitude, recorded_at) VALUES (1, 73, 14.7167, -17.4677, NOW())');
echo 'INSERT OK';
"
```

### Vérifier FK restantes

```sql
SELECT CONSTRAINT_NAME
FROM information_schema.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'livreur_positions'
  AND CONSTRAINT_TYPE = 'FOREIGN KEY';
```

---

## 18. Problèmes rencontrés et solutions

| Problème | Symptôme | Solution appliquée |
|----------|----------|-------------------|
| Temps réel affiché sans Socket.io | Statut mensonger | `tracking_realtime_available()` + statuts séparés GPS / temps réel |
| Node → PHP 404 | Auth Socket.io échoue | Module `http` natif + header `Host: sugar-paper.com` (pas `fetch`) |
| Mauvais port Apache | PHP injoignable depuis Node | `TRACKING_PHP_BASE=http://127.0.0.1:8081` (pas 8082 SSL) |
| WebSocket échoue Webuzo | `connect_error` | Client en **polling seul** (`transports: ['polling']`, `upgrade: false`) |
| Abandon trop rapide connexion | Popup erreur au 1er échec | Timeout 15 s, ne pas `resolve(false)` au premier `connect_error` |
| Cache JS ancien | Comportement obsolète | `filemtime()` sur `admin-livreur-suivi.js` + `asset_version.php` |
| `headers already sent` | Warning PHP index | Suppression `?>` final dans `model_commandes_admin.php` |
| Redémarrage suivi 400 | `livreur-web.php` start | `livreur_start_web_tracking()` idempotent si déjà actif |
| INSERT position 400 | « Enregistrement position impossible » | FK `livreur_positions → livreurs` supprimée (`run_fix_livreur_positions_fk.php`) |
| PM2 cluster | Comportement instable | `exec_mode: 'fork'` dans `ecosystem.config.cjs` |
| `npm install` à la racine | node_modules incorrect | Toujours `cd tracking-server` avant install |
| `.env` absent après install fraîche | `TRACKING_INTERNAL_SECRET manquant`, PM2 ↺ élevé | Créer `.env` en SSH ; `pm2 delete` + `pm2 start` |
| Éditeur Webuzo pour `.env` | Secret lu par `node -e` mais pas par PM2 | Recréer via `cat > .env` en SSH ; `chmod 600` |
| `systemctl reload nginx` échoue Webuzo | Config Nginx non appliquée | `/usr/local/apps/nginx/sbin/nginx -s reload` |
| `ping.php` method_not_allowed | Test curl en GET | Utiliser `curl -X POST` + header `X-Tracking-Secret` |
| Socket.io public → 500 | Node pas sur 3001 | Corriger `.env` puis redémarrer PM2 |
| `livreur_bl_livraison_columns_ok : non` | `bons_livraison` sans colonnes GPS | `run_migrate_invoice_bl.php` puis relancer `run_add_livreur_tracking.php` |
| BL #X introuvable diagnostic | Données effacées (install fraîche) | Lister les IDs réels en BDD ; recréer livraisons en admin |
| Autostart + échec Socket.io | Bouton Terminer affiché trop tôt | Comportement corrigé : **Démarrer** reste visible ; retry manuel |
| Logs PM2 anciennes erreurs | Confusion après correction | `pm2 flush sugar-tracking` |
| `tracking_diagnostic.php` introuvable | Mauvais répertoire courant | Lancer depuis `/home/jomas/sugar-paper.com` |

---

## 19. Fichiers du projet

### Configuration (gitignore / secrets)

```
config/tracking.php          # Secret PHP (non versionné)
config/routing.php           # Optionnel
tracking-server/.env         # Secret Node (non versionné)
```

### Backend PHP

```
includes/tracking_config.php
includes/livreur_routing.php
includes/asset_version.php
models/model_livreur_tracking.php
api/tracking/livreur-web.php
api/tracking/watch-token.php
api/tracking/verify-watch.php
api/tracking/verify-livreur.php
api/tracking/persist-position.php
api/routing/directions.php
api/livreur/*.php
migrations/run_add_livreur_tracking.php
migrations/run_fix_livreur_positions_fk.php
migrations/run_migrate_invoice_bl.php
scripts/install-vps-fresh.sh
scripts/deploy.sh
scripts/FICHIERS_SENSIBLES.md
scripts/tracking_diagnostic.php
docs/SUIVI_GPS_TEMPS_REEL.md
suivi-livraison.php
```

### Node.js

```
tracking-server/server.js
tracking-server/ecosystem.config.cjs
tracking-server/package.json
tracking-server/nginx-snippet.conf
tracking-server/README.md
```

### Admin / Frontend

```
admin/livreurs/index.php
admin/livreurs/suivi.php
js/admin-livreur-suivi.js
js/livreur-route-api.js
css/admin-livreur-suivi.css
```

---

## 20. Sécurité

- **`internal_secret`** long et aléatoire ; jamais exposé au navigateur.
- Tokens **watch** : durée limitée (`watch_token_ttl_minutes`), liés à une commande/BL.
- API `livreur-web.php` : session admin + permission GPS + vérif `livreur_id`.
- API interne Node : refus si secret invalide (403).
- Positions : validation coordonnées (-90/90, -180/180).
- Node écoute **127.0.0.1 uniquement** — pas d'exposition directe du port 3001.
- CORS Socket.io limité aux origines du site.

---

## 21. Évolutions prévues

- [ ] Application **Flutter** livreur (Socket.io + API `/api/livreur/*`).
- [ ] Upgrade **WebSocket** Nginx si nécessaire (polling suffit actuellement).
- [ ] Page suivi **client** (token watch type `client`).
- [ ] Unification sémantique `livreur_id` (admin vs table `livreurs`) si app mobile déployée.
- [ ] Notifications push / email à la livraison.

---

## Références rapides

| Ressource | URL / chemin |
|-----------|--------------|
| Liste livraisons admin | `/admin/livreurs/index.php` |
| Suivi commande | `/admin/livreurs/suivi.php?commande_id={id}` |
| Health Node local | `http://127.0.0.1:3001/health` |
| Test Socket.io public | `https://sugar-paper.com/socket.io/?EIO=4&transport=polling` |
| Doc déploiement Node | `tracking-server/README.md` |
| Doc complète (ce fichier) | `docs/SUIVI_GPS_TEMPS_REEL.md` |
| Install fraîche VPS | `scripts/install-vps-fresh.sh` |
| Fichiers sensibles post-clone | `scripts/FICHIERS_SENSIBLES.md` |
| Diagnostic BL/commande | `php scripts/tracking_diagnostic.php {bl_id}` |
| Suivi public partagé | `/suivi-livraison.php?commande_id={id}&token=…` |

---

*Document mis à jour juillet 2026 — déploiement validé sur sugar-paper.com (Webuzo, PM2, Nginx, Apache 8081).*
*Inclut procédure post-`install-vps-fresh.sh`, checklist de vérification partie par partie, et dépannage production.*
