# Déploiement dynamique — samapiece.com (Webuzo)

Déploiement automatique à chaque `git push` sur `main`, ou manuel via script SSH.

## Architecture

```
GitHub (main)  →  GitHub Actions  →  SSH VPS  →  scripts/deploy.sh
                                              →  git pull
                                              →  composer install
                                              →  npm ci (tracking-server)
                                              →  pm2 restart
```

**Chemin production :** `/home/jomas/samapiece.com`  
**Dépôt :** https://github.com/nick-dev12/sugar_paper.git

Les fichiers sensibles (BDD, Firebase, `.env` tracking) restent **sur le VPS** et ne sont jamais écrasés par git (voir `scripts/deploy-protect.txt`).

---

## 1. Configuration initiale VPS (une seule fois)

Connectez-vous en SSH :

```bash
ssh root@votre-vps
# ou
ssh jomas@votre-vps
```

### A. Lancer le setup git

```bash
cd /home/jomas/samapiece.com
git pull   # si le dépôt est déjà cloné, sinon :
bash scripts/setup-vps-deploy.sh
```

### B. Accès GitHub depuis le VPS

**Option recommandée — Deploy key (lecture seule) :**

```bash
ssh-keygen -t ed25519 -C "deploy-samapiece" -f /home/jomas/.ssh/deploy_samapiece -N ""
cat /home/jomas/.ssh/deploy_samapiece.pub
```

Sur GitHub → **sugar_paper** → Settings → Deploy keys → Add deploy key → coller la clé publique.

Configurer git pour utiliser cette clé :

```bash
cat >> /home/jomas/.ssh/config << 'EOF'
Host github.com-sugar
  HostName github.com
  User git
  IdentityFile /home/jomas/.ssh/deploy_samapiece
  IdentitiesOnly yes
EOF
chmod 600 /home/jomas/.ssh/config

cd /home/jomas/samapiece.com
git remote set-url origin git@github.com-sugar:nick-dev12/sugar_paper.git
```

### C. Fichier de config déploiement

```bash
cp /home/jomas/samapiece.com/scripts/deploy.env.example /home/jomas/deploy-samapiece.env
nano /home/jomas/deploy-samapiece.env
```

Vérifiez notamment :

| Variable | Valeur typique |
|----------|----------------|
| `DEPLOY_DIR` | `/home/jomas/samapiece.com` |
| `PM2_APP_NAME` | nom de votre app PM2 (`pm2 list`) |
| `WEB_USER` | `jomas` |
| `RUN_MIGRATIONS` | `0` sauf si vous listez des scripts dans `deploy-migrations.list` |

### D. Test manuel

```bash
DEPLOY_ENV=/home/jomas/deploy-samapiece.env bash /home/jomas/samapiece.com/scripts/deploy.sh
tail -20 /home/jomas/logs/deploy-samapiece.log
```

---

## 2. GitHub Actions (déploiement auto)

Dans GitHub → **sugar_paper** → Settings → Secrets and variables → Actions :

| Secret | Description |
|--------|-------------|
| `VPS_HOST` | IP ou domaine du VPS |
| `VPS_USER` | `jomas` ou `root` |
| `VPS_SSH_KEY` | Clé privée SSH (celle qui peut se connecter au VPS) |
| `VPS_SSH_PORT` | `22` (optionnel) |
| `VPS_DEPLOY_ENV` | `/home/jomas/deploy-samapiece.env` (optionnel) |

Créez une clé SSH **depuis votre PC ou CI** pour se connecter au VPS :

```bash
ssh-keygen -t ed25519 -C "github-actions-deploy" -f github_deploy -N ""
```

- Coller `github_deploy.pub` dans `/home/jomas/.ssh/authorized_keys` sur le VPS
- Coller le contenu de `github_deploy` (privée) dans le secret `VPS_SSH_KEY`

Ensuite, chaque push sur `main` déclenche le déploiement.

Déploiement manuel depuis GitHub : **Actions** → **Deploy production** → **Run workflow**.

---

## 3. Workflow développeur

```bash
# En local (WAMP)
git add .
git commit -m "fix: ..."
git push origin main
# → GitHub Actions déploie automatiquement (~30–90 s)
```

Ou déploiement manuel sur le VPS :

```bash
ssh jomas@vps
bash /home/jomas/samapiece.com/scripts/deploy.sh
```

---

## 4. Migrations base de données

Par défaut les migrations **ne sont pas** exécutées automatiquement.

Pour activer :

1. Ajouter les scripts PHP idempotents dans `scripts/deploy-migrations.list`
2. Mettre `RUN_MIGRATIONS=1` dans `/home/jomas/deploy-samapiece.env`

Exemple :

```
migrations/run_add_livreur_tracking.php
```

---

## 5. Fichiers jamais écrasés

Liste dans `scripts/deploy-protect.txt` :

- `conn/conn.php`
- `config/*.php` (secrets)
- `tracking-server/.env`
- clés Firebase / Apple
- `upload/`, `uploads/`

---

## 6. Dépannage

**git pull échoue (conflits locaux) :**

```bash
cd /home/jomas/samapiece.com
git status
git stash push -m "prod-local" -- conn/ config/ tracking-server/.env
bash scripts/deploy.sh
git stash pop
```

**PM2 :**

```bash
pm2 list
pm2 logs sugar-tracking --lines 50
```

**Log déploiement :**

```bash
tail -f /home/jomas/logs/deploy-samapiece.log
```

**Permissions Webuzo :**

```bash
chown -R jomas:jomas /home/jomas/samapiece.com
```
