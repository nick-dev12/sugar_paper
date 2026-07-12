#!/usr/bin/env bash
#
# Mise à jour rapide (après install-vps-fresh.sh) :
#   git pull + composer + tracking-server + pm2
#
# Usage :
#   cd /home/jomas/samapiece.com && bash scripts/deploy.sh
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEPLOY_DIR="${DEPLOY_DIR:-$(cd "$SCRIPT_DIR/.." && pwd)}"
GIT_BRANCH="${GIT_BRANCH:-main}"
PM2_APP_NAME="${PM2_APP_NAME:-sugar-tracking}"
DEPLOY_LOG="${DEPLOY_LOG:-/home/jomas/logs/deploy-samapiece.log}"

log() {
  local msg="[$(date '+%Y-%m-%d %H:%M:%S')] $*"
  echo "$msg"
  mkdir -p "$(dirname "$DEPLOY_LOG")"
  echo "$msg" >> "$DEPLOY_LOG"
}

cd "$DEPLOY_DIR"

[[ -d .git ]] || { log "ERREUR: pas de dépôt git. Lancez install-vps-fresh.sh"; exit 1; }

log "git pull origin $GIT_BRANCH"
git pull origin "$GIT_BRANCH"

if command -v composer >/dev/null 2>&1 && [[ -f composer.json ]]; then
  log "composer install"
  composer install --no-dev --optimize-autoloader --no-interaction
fi

if [[ -f tracking-server/package.json ]] && command -v npm >/dev/null 2>&1; then
  log "npm (tracking-server)"
  (cd tracking-server && (npm ci --omit=dev 2>/dev/null || npm install --omit=dev))
  if command -v pm2 >/dev/null 2>&1; then
    if pm2 describe "$PM2_APP_NAME" >/dev/null 2>&1; then
      pm2 restart "$PM2_APP_NAME"
    else
      (cd tracking-server && pm2 start server.js --name "$PM2_APP_NAME")
      pm2 save
    fi
  fi
fi

if id jomas >/dev/null 2>&1; then
  chown -R jomas:jomas "$DEPLOY_DIR" 2>/dev/null || true
fi

log "Déploiement terminé."
