#!/usr/bin/env bash
#
# Configuration initiale du VPS pour le déploiement git.
# À lancer UNE FOIS en root ou jomas :
#   bash /home/jomas/samapiece.com/scripts/setup-vps-deploy.sh
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEPLOY_DIR="${DEPLOY_DIR:-$(cd "$SCRIPT_DIR/.." && pwd)}"
GIT_REPO="${GIT_REPO:-https://github.com/nick-dev12/sugar_paper.git}"
GIT_BRANCH="${GIT_BRANCH:-main}"
DEPLOY_ENV="${DEPLOY_ENV:-/home/jomas/deploy-samapiece.env}"

echo "=== Setup déploiement Sugar Paper ==="
echo "Répertoire : $DEPLOY_DIR"

if [[ ! -d "$DEPLOY_DIR" ]]; then
  echo "ERREUR: répertoire absent : $DEPLOY_DIR"
  exit 1
fi

cd "$DEPLOY_DIR"

if [[ -d .git ]]; then
  echo "Dépôt git déjà présent."
  git remote -v || true
else
  echo "Initialisation git dans le dossier existant (fichiers protégés sauvegardés)…"

  PROTECT_FILE="$SCRIPT_DIR/deploy-protect.txt"
  BACKUP_DIR="$(mktemp -d /tmp/samapiece-setup-backup.XXXXXX)"

  if [[ -f "$PROTECT_FILE" ]]; then
    while IFS= read -r rel || [[ -n "$rel" ]]; do
      rel="${rel//$'\r'/}"
      [[ -z "$rel" || "$rel" =~ ^# ]] && continue
      if [[ -e "$DEPLOY_DIR/$rel" ]]; then
        mkdir -p "$BACKUP_DIR/$(dirname "$rel")"
        cp -a "$DEPLOY_DIR/$rel" "$BACKUP_DIR/$rel"
      fi
    done < "$PROTECT_FILE"
  fi

  git init
  git remote add origin "$GIT_REPO" 2>/dev/null || git remote set-url origin "$GIT_REPO"
  git fetch origin "$GIT_BRANCH"
  git checkout -B "$GIT_BRANCH" "origin/$GIT_BRANCH"

  if [[ -f "$PROTECT_FILE" ]]; then
    while IFS= read -r rel || [[ -n "$rel" ]]; do
      rel="${rel//$'\r'/}"
      [[ -z "$rel" || "$rel" =~ ^# ]] && continue
      if [[ -e "$BACKUP_DIR/$rel" ]]; then
        mkdir -p "$DEPLOY_DIR/$(dirname "$rel")"
        cp -a "$BACKUP_DIR/$rel" "$DEPLOY_DIR/$rel"
      fi
    done < "$PROTECT_FILE"
  fi

  rm -rf "$BACKUP_DIR"
  echo "Git initialisé (conn.php, config/, .env tracking préservés)."
fi

mkdir -p /home/jomas/logs
chmod +x "$SCRIPT_DIR/deploy.sh" 2>/dev/null || true

if [[ ! -f "$DEPLOY_ENV" ]]; then
  cp "$SCRIPT_DIR/deploy.env.example" "$DEPLOY_ENV"
  echo "Fichier créé : $DEPLOY_ENV (à personnaliser)"
else
  echo "Config déjà présente : $DEPLOY_ENV"
fi

echo ""
echo "=== Prochaines étapes ==="
echo "1. Éditer $DEPLOY_ENV (PM2_APP_NAME, WEB_USER, etc.)"
echo "2. Configurer l'accès GitHub (SSH deploy key ou token HTTPS)"
echo "3. Tester : DEPLOY_ENV=$DEPLOY_ENV bash $SCRIPT_DIR/deploy.sh"
echo "4. Configurer GitHub Actions (secrets SSH) pour déploiement auto"
echo ""
echo "Clé SSH dédiée (recommandé) :"
echo "  ssh-keygen -t ed25519 -C deploy-samapiece -f /home/jomas/.ssh/deploy_samapiece -N ''"
echo "  cat /home/jomas/.ssh/deploy_samapiece.pub"
echo "  → Ajouter comme Deploy key (read-only) sur GitHub"
echo ""
