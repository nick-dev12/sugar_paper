#!/usr/bin/env bash
#
# Déploiement Sugar Paper — git pull + composer + tracking-server + migrations.
# Usage (sur le VPS) :
#   bash /home/jomas/samapiece.com/scripts/deploy.sh
#   DEPLOY_ENV=/home/jomas/deploy-samapiece.env bash scripts/deploy.sh
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

DEPLOY_ENV="${DEPLOY_ENV:-/home/jomas/deploy-samapiece.env}"
if [[ -f "$DEPLOY_ENV" ]]; then
  # shellcheck disable=SC1090
  source "$DEPLOY_ENV"
fi

DEPLOY_DIR="${DEPLOY_DIR:-$PROJECT_DIR}"
GIT_BRANCH="${GIT_BRANCH:-main}"
GIT_REMOTE="${GIT_REMOTE:-origin}"
GIT_REPO="${GIT_REPO:-https://github.com/nick-dev12/sugar_paper.git}"
RESTART_TRACKING="${RESTART_TRACKING:-1}"
TRACKING_DIR="${TRACKING_DIR:-tracking-server}"
PM2_APP_NAME="${PM2_APP_NAME:-sugar-tracking}"
RUN_MIGRATIONS="${RUN_MIGRATIONS:-0}"
WEB_USER="${WEB_USER:-jomas}"
WEB_GROUP="${WEB_GROUP:-jomas}"
DEPLOY_LOG="${DEPLOY_LOG:-/home/jomas/logs/deploy-samapiece.log}"

PROTECT_FILE="$SCRIPT_DIR/deploy-protect.txt"
MIGRATIONS_LIST="$SCRIPT_DIR/deploy-migrations.list"

log() {
  local msg="[$(date '+%Y-%m-%d %H:%M:%S')] $*"
  echo "$msg"
  mkdir -p "$(dirname "$DEPLOY_LOG")"
  echo "$msg" >> "$DEPLOY_LOG"
}

die() {
  log "ERREUR: $*"
  exit 1
}

command_exists() {
  command -v "$1" >/dev/null 2>&1
}

backup_protected_files() {
  local backup_dir
  backup_dir="$(mktemp -d /tmp/samapiece-deploy-backup.XXXXXX)"
  log "Sauvegarde fichiers protégés → $backup_dir"

  if [[ ! -f "$PROTECT_FILE" ]]; then
    echo "$backup_dir"
    return 0
  fi

  while IFS= read -r rel || [[ -n "$rel" ]]; do
    rel="${rel//$'\r'/}"
    [[ -z "$rel" || "$rel" =~ ^# ]] && continue
    if [[ -e "$DEPLOY_DIR/$rel" ]]; then
      mkdir -p "$backup_dir/$(dirname "$rel")"
      cp -a "$DEPLOY_DIR/$rel" "$backup_dir/$rel"
    fi
  done < "$PROTECT_FILE"

  echo "$backup_dir"
}

restore_protected_files() {
  local backup_dir="$1"
  [[ -z "$backup_dir" || ! -d "$backup_dir" ]] && return 0

  log "Restauration fichiers protégés"
  while IFS= read -r rel || [[ -n "$rel" ]]; do
    rel="${rel//$'\r'/}"
    [[ -z "$rel" || "$rel" =~ ^# ]] && continue
    if [[ -e "$backup_dir/$rel" ]]; then
      mkdir -p "$DEPLOY_DIR/$(dirname "$rel")"
      cp -a "$backup_dir/$rel" "$DEPLOY_DIR/$rel"
    fi
  done < "$PROTECT_FILE"

  rm -rf "$backup_dir"
}

ensure_git_repo() {
  cd "$DEPLOY_DIR"

  if [[ ! -d .git ]]; then
    die "Dépôt git absent dans $DEPLOY_DIR. Lancez d'abord : bash scripts/setup-vps-deploy.sh"
  fi

  if ! git remote get-url "$GIT_REMOTE" >/dev/null 2>&1; then
    git remote add "$GIT_REMOTE" "$GIT_REPO"
  fi

  log "git fetch $GIT_REMOTE"
  git fetch "$GIT_REMOTE" --prune

  log "git checkout $GIT_BRANCH"
  git checkout "$GIT_BRANCH" 2>/dev/null || git checkout -b "$GIT_BRANCH" "$GIT_REMOTE/$GIT_BRANCH"

  log "git pull $GIT_REMOTE $GIT_BRANCH"
  git pull --ff-only "$GIT_REMOTE" "$GIT_BRANCH"
}

run_composer() {
  cd "$DEPLOY_DIR"
  local composer_bin="${COMPOSER_BIN:-}"
  if [[ -z "$composer_bin" ]]; then
    if command_exists composer; then
      composer_bin=composer
    elif command_exists php && [[ -f /usr/local/bin/composer ]]; then
      composer_bin=/usr/local/bin/composer
    else
      composer_bin=""
    fi
  fi

  if ! command_exists "$composer_bin"; then
    log "Composer introuvable — étape ignorée"
    return 0
  fi

  if [[ ! -f composer.json ]]; then
    return 0
  fi

  log "composer install --no-dev --optimize-autoloader"
  "$composer_bin" install --no-dev --optimize-autoloader --no-interaction
}

run_tracking_server() {
  cd "$DEPLOY_DIR/$TRACKING_DIR"

  if [[ ! -f package.json ]]; then
    log "tracking-server absent — étape ignorée"
    return 0
  fi

  local npm_bin="${NPM_BIN:-npm}"
  if ! command_exists "$npm_bin"; then
    log "npm introuvable — tracking-server ignoré"
    return 0
  fi

  log "npm ci (tracking-server)"
  if [[ -f package-lock.json ]]; then
    "$npm_bin" ci --omit=dev
  else
    "$npm_bin" install --omit=dev
  fi

  if [[ "$RESTART_TRACKING" != "1" ]]; then
    return 0
  fi

  local pm2_bin="${PM2_BIN:-pm2}"
  if ! command_exists "$pm2_bin"; then
    log "pm2 introuvable — redémarrage tracking ignoré"
    return 0
  fi

  if "$pm2_bin" describe "$PM2_APP_NAME" >/dev/null 2>&1; then
    log "pm2 restart $PM2_APP_NAME"
    "$pm2_bin" restart "$PM2_APP_NAME"
  else
    log "pm2 start server.js --name $PM2_APP_NAME"
    "$pm2_bin" start server.js --name "$PM2_APP_NAME"
    "$pm2_bin" save
  fi
}

run_migrations() {
  if [[ "$RUN_MIGRATIONS" != "1" ]]; then
    return 0
  fi

  if [[ ! -f "$MIGRATIONS_LIST" ]]; then
    log "Aucune liste de migrations ($MIGRATIONS_LIST)"
    return 0
  fi

  local php_bin="php"
  command_exists php || die "php introuvable pour les migrations"

  cd "$DEPLOY_DIR"
  while IFS= read -r script || [[ -n "$script" ]]; do
    script="${script//$'\r'/}"
    [[ -z "$script" || "$script" =~ ^# ]] && continue
    if [[ ! -f "$DEPLOY_DIR/$script" ]]; then
      log "Migration ignorée (fichier absent) : $script"
      continue
    fi
    log "Migration : $script"
    "$php_bin" "$DEPLOY_DIR/$script" >> "$DEPLOY_LOG" 2>&1 || die "Échec migration : $script"
  done < "$MIGRATIONS_LIST"
}

fix_permissions() {
  cd "$DEPLOY_DIR"
  if id "$WEB_USER" >/dev/null 2>&1; then
    log "Permissions → $WEB_USER:$WEB_GROUP"
    chown -R "$WEB_USER:$WEB_GROUP" "$DEPLOY_DIR" 2>/dev/null || true
  fi
}

main() {
  log "========== Déploiement Sugar Paper =========="
  log "Répertoire : $DEPLOY_DIR"

  [[ -d "$DEPLOY_DIR" ]] || die "Répertoire introuvable : $DEPLOY_DIR"

  local backup_dir
  backup_dir="$(backup_protected_files)"

  ensure_git_repo
  restore_protected_files "$backup_dir"

  run_composer
  run_tracking_server
  run_migrations
  fix_permissions

  log "========== Déploiement terminé avec succès =========="
}

main "$@"
