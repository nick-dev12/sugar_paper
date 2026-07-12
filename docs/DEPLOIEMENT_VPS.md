# Déploiement — sugar-paper.com

**Chemin VPS :** `/home/jomas/sugar-paper.com`  
**GitHub :** https://github.com/nick-dev12/sugar_paper.git

---

## Première installation (une fois)

Connectez-vous en SSH :

```bash
ssh root@votre-vps
```

Copiez le script sur le VPS (ou clonez d’abord dans un dossier temporaire), puis :

```bash
# Télécharger le script si le dossier n'existe pas encore :
curl -fsSL -o /tmp/install-vps-fresh.sh \
  https://raw.githubusercontent.com/nick-dev12/sugar_paper/main/scripts/install-vps-fresh.sh

bash /tmp/install-vps-fresh.sh
```

Le script :

1. Vérifie si **git** est installé (sinon l’installe)
2. Demande **user.name** et **user.email** git
3. **Supprime** tout `/home/jomas/sugar-paper.com`
4. **Clone** le dépôt GitHub
5. Lance `composer install` et `npm` si disponibles

### Remettre les fichiers sensibles à la main

Voir la liste : `scripts/FICHIERS_SENSIBLES.md`

Minimum :

```bash
cd /home/jomas/sugar-paper.com
cp conn/conn.example.php conn/conn.php
nano conn/conn.php

# Remettre depuis votre PC ou une sauvegarde :
# - config/*.php
# - tracking-server/.env
# - sugar-paper-*.json
```

---

## Mises à jour (au quotidien)

Sur le VPS :

```bash
cd /home/jomas/sugar-paper.com
bash scripts/deploy.sh
```

Ou depuis votre PC : `git push origin main` (si GitHub Actions est configuré).

---

## GitHub Actions (optionnel)

Secrets GitHub : `VPS_HOST`, `VPS_USER`, `VPS_SSH_KEY`

Le workflow exécute `scripts/deploy.sh` à chaque push sur `main`.

---

## Sans confirmation interactive

```bash
GIT_USER_NAME="Jomas" \
GIT_USER_EMAIL="vous@email.com" \
CONFIRM_OUI=1 \
bash install-vps-fresh.sh
```
