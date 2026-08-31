#!/usr/bin/env bash
#
# Déploiement sugar-paper.com + vérifications suivi GPS
#
# Usage :
#   cd /home/jomas/sugar-paper.com && bash scripts/deploy.sh
#
# Variables optionnelles :
#   DEPLOY_DIR, GIT_BRANCH, PM2_APP_NAME, SITE_URL, PM2_USER
#
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEPLOY_DIR="${DEPLOY_DIR:-$(cd "$SCRIPT_DIR/.." && pwd)}"
GIT_BRANCH="${GIT_BRANCH:-main}"
PM2_APP_NAME="${PM2_APP_NAME:-sugar-tracking}"
DEPLOY_LOG="${DEPLOY_LOG:-/home/jomas/logs/deploy-sugar-paper.log}"
SITE_URL="${SITE_URL:-https://sugar-paper.com}"
PM2_USER="${PM2_USER:-$(whoami)}"

DEPLOY_ERRORS=0
DEPLOY_WARNINGS=0

log() {
  local msg="[$(date '+%Y-%m-%d %H:%M:%S')] $*"
  echo "$msg"
  mkdir -p "$(dirname "$DEPLOY_LOG")"
  echo "$msg" >> "$DEPLOY_LOG"
}

log_ok() {
  log "OK: $*"
}

log_warn() {
  DEPLOY_WARNINGS=$((DEPLOY_WARNINGS + 1))
  log "AVERTISSEMENT: $*"
}

log_fail() {
  DEPLOY_ERRORS=$((DEPLOY_ERRORS + 1))
  log "ECHEC: $*"
}

ensure_git_safe_directory() {
  if ! git -C "$DEPLOY_DIR" status >/dev/null 2>&1; then
    log "Configuration git safe.directory pour ${DEPLOY_DIR}"
    git config --global --add safe.directory "$DEPLOY_DIR" 2>/dev/null || true
  fi
  if ! git -C "$DEPLOY_DIR" status >/dev/null 2>&1; then
    log_fail "Git refuse le dépôt (dubious ownership). Exécutez : git config --global --add safe.directory ${DEPLOY_DIR}"
    return 1
  fi
  log_ok "Git safe.directory OK"
  return 0
}

deploy_git_pull() {
  log "git pull origin ${GIT_BRANCH}"
  if git -C "$DEPLOY_DIR" pull origin "$GIT_BRANCH"; then
    log_ok "Code à jour (git pull)"
  else
    log_fail "git pull a échoué"
    return 1
  fi
}

deploy_migrations() {
  if [[ ! -f "${DEPLOY_DIR}/migrations/run_all_migrations.php" ]]; then
    log_warn "migrations/run_all_migrations.php absent — migrations ignorées"
    return 0
  fi

  log "Migrations BDD (run_all_migrations.php --continue)"
  if php "${DEPLOY_DIR}/migrations/run_all_migrations.php" --continue; then
    log_ok "Migrations BDD à jour"
  else
    log_fail "Migrations BDD — relancez : php migrations/run_all_migrations.php --continue"
  fi
}

deploy_composer() {
  if command -v composer >/dev/null 2>&1 && [[ -f "${DEPLOY_DIR}/composer.json" ]]; then
    log "composer install"
    if (cd "$DEPLOY_DIR" && composer install --no-dev --optimize-autoloader --no-interaction); then
      log_ok "Composer OK"
    else
      log_fail "composer install a échoué"
    fi
  else
    log_warn "Composer absent ou composer.json manquant — ignoré"
  fi
}

read_php_tracking_port() {
  php -r "
    require '${DEPLOY_DIR}/includes/tracking_config.php';
    echo (int) tracking_config_get('node_port', 3001);
  " 2>/dev/null || echo "3001"
}

ensure_pm2_autostart() {
  if ! command -v pm2 >/dev/null 2>&1; then
    log_warn "PM2 non installé — suivi temps réel Node indisponible"
    return 1
  fi

  # Démarrage automatique au boot (systemd)
  if command -v systemctl >/dev/null 2>&1; then
    local pm2_service=""
    for svc in "pm2-${PM2_USER}" "pm2-root" "pm2.service"; do
      if systemctl list-unit-files "${svc}.service" 2>/dev/null | grep -q "${svc}.service"; then
        pm2_service="${svc}.service"
        break
      fi
    done
    if [[ -n "$pm2_service" ]]; then
      if systemctl is-enabled "$pm2_service" >/dev/null 2>&1; then
        log_ok "PM2 autostart activé (${pm2_service})"
      else
        log "Activation PM2 au démarrage : systemctl enable ${pm2_service}"
        systemctl enable "$pm2_service" 2>/dev/null && log_ok "PM2 autostart enable OK" || log_warn "systemctl enable ${pm2_service} — à faire manuellement"
      fi
    else
      log "Configuration PM2 startup (première fois)…"
      local startup_cmd
      startup_cmd="$(pm2 startup systemd -u "$PM2_USER" --hp "$HOME" 2>/dev/null | grep -E '^sudo env' || true)"
      if [[ -n "$startup_cmd" ]]; then
        eval "$startup_cmd" 2>/dev/null && log_ok "PM2 startup systemd configuré" || log_warn "Exécutez manuellement : pm2 startup systemd -u ${PM2_USER}"
      else
        pm2 startup 2>/dev/null || log_warn "pm2 startup — vérifiez pm2 startup systemd -u ${PM2_USER}"
      fi
    fi
  fi

  pm2 save >/dev/null 2>&1 && log_ok "PM2 save (processus persistés)" || log_warn "pm2 save a échoué"
}

deploy_tracking_server() {
  if [[ ! -f "${DEPLOY_DIR}/tracking-server/package.json" ]]; then
    log_warn "tracking-server/package.json absent — ignoré"
    return 0
  fi

  if ! command -v npm >/dev/null 2>&1; then
    log_fail "npm absent — impossible d'installer tracking-server"
    return 1
  fi

  log "npm install (tracking-server)"
  if (cd "${DEPLOY_DIR}/tracking-server" && (npm ci --omit=dev 2>/dev/null || npm install --omit=dev)); then
    log_ok "npm tracking-server OK"
  else
    log_fail "npm install tracking-server a échoué"
    return 1
  fi

  if [[ ! -f "${DEPLOY_DIR}/tracking-server/.env" ]]; then
    log_fail "tracking-server/.env manquant — copiez .env.example et configurez TRACKING_INTERNAL_SECRET"
    return 1
  fi

  if ! command -v pm2 >/dev/null 2>&1; then
    log_fail "PM2 absent — installez : npm install -g pm2"
    return 1
  fi

  log "PM2 — (re)démarrage ${PM2_APP_NAME}"
  if [[ -f "${DEPLOY_DIR}/tracking-server/ecosystem.config.cjs" ]]; then
    if pm2 describe "$PM2_APP_NAME" >/dev/null 2>&1; then
      pm2 restart "$PM2_APP_NAME" --update-env && log_ok "PM2 restart ${PM2_APP_NAME}" || log_fail "PM2 restart échoué"
    else
      (cd "${DEPLOY_DIR}/tracking-server" && pm2 start ecosystem.config.cjs) && log_ok "PM2 start ${PM2_APP_NAME} (ecosystem)" || log_fail "PM2 start échoué"
    fi
  else
    (cd "${DEPLOY_DIR}/tracking-server" && pm2 start server.js --name "$PM2_APP_NAME") && log_ok "PM2 start ${PM2_APP_NAME}" || log_fail "PM2 start échoué"
  fi

  ensure_pm2_autostart

  sleep 2
  local port
  port="$(read_php_tracking_port)"
  local health_url="http://127.0.0.1:${port}/health"
  if curl -sf --max-time 8 "$health_url" | grep -q '"ok"'; then
    log_ok "Node tracking health (${health_url})"
  else
    log_fail "Node tracking ne répond pas sur ${health_url} — pm2 logs ${PM2_APP_NAME}"
  fi

  if pm2 describe "$PM2_APP_NAME" 2>/dev/null | grep -q "online"; then
    log_ok "PM2 process ${PM2_APP_NAME} online"
  else
    log_fail "PM2 process ${PM2_APP_NAME} pas online — pm2 status"
  fi
}

verify_secrets_quick() {
  log "Vérification rapide secrets PHP ↔ Node"
  local php_secret node_secret
  php_secret="$(php -r "require '${DEPLOY_DIR}/includes/tracking_config.php'; echo tracking_internal_secret();" 2>/dev/null || echo "")"
  node_secret="$(grep -E '^TRACKING_INTERNAL_SECRET=' "${DEPLOY_DIR}/tracking-server/.env" 2>/dev/null | head -1 | cut -d= -f2- || true)"
  node_secret="${node_secret//\"/}"
  node_secret="${node_secret//\'/}"
  node_secret="${node_secret//[[:space:]]/}"

  if [[ -z "$php_secret" || "$php_secret" == *REMPLACEZ* ]]; then
    log_fail "config/tracking.php — internal_secret invalide"
  else
    log_ok "Secret PHP présent"
  fi

  if [[ -z "$node_secret" || "$node_secret" == *REMPLACEZ* ]]; then
    log_fail "tracking-server/.env — TRACKING_INTERNAL_SECRET invalide"
  else
    log_ok "Secret Node présent"
  fi

  if [[ -n "$php_secret" && -n "$node_secret" && "$php_secret" == "$node_secret" ]]; then
    log_ok "Secrets PHP et Node identiques"
  elif [[ -n "$php_secret" && -n "$node_secret" ]]; then
    log_fail "Secrets PHP ≠ Node — alignez config/tracking.php et tracking-server/.env"
  fi
}

verify_site_http() {
  log "Vérification site public ${SITE_URL}"
  local code
  code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 "${SITE_URL}/" || echo "000")"
  if [[ "$code" =~ ^(200|301|302)$ ]]; then
    log_ok "Site répond HTTP ${code} (${SITE_URL})"
  else
    log_warn "Site HTTP ${code} pour ${SITE_URL}"
  fi
}

verify_socket_public() {
  local port socket_path probe
  port="$(read_php_tracking_port)"
  socket_path="$(php -r "require '${DEPLOY_DIR}/includes/tracking_config.php'; echo tracking_config_get('socket_path','/socket.io');" 2>/dev/null || echo '/socket.io')"
  probe="${SITE_URL}${socket_path}/?EIO=4&transport=polling"
  log "Vérification Socket.io public"
  if curl -sf --max-time 12 "$probe" | head -c 80 | grep -qE "sid|0\\{"; then
    log_ok "Socket.io public OK"
  else
    log_warn "Socket.io public non confirmé (${probe}) — proxy Nginx/Apache /socket.io"
  fi
}

verify_android_assetlinks() {
  if [[ ! -f "${DEPLOY_DIR}/scripts/verify_assetlinks.php" ]]; then
    log_warn "scripts/verify_assetlinks.php absent — vérification App Links ignorée"
    return 0
  fi
  log "Vérification Android App Links (assetlinks.json)"
  if php "${DEPLOY_DIR}/scripts/verify_assetlinks.php" "${SITE_URL}"; then
    log_ok "assetlinks.json OK"
  else
    log_warn "assetlinks.json — configurez config/assetlinks.php (SHA-256 Play Console) puis Recontrôler dans Play Console"
  fi
}

run_php_tracking_verify() {
  if [[ ! -f "${DEPLOY_DIR}/scripts/deploy-tracking-verify.php" ]]; then
    log_warn "scripts/deploy-tracking-verify.php absent — vérification PHP ignorée"
    return 0
  fi
  log "Vérification complète suivi GPS (PHP)"
  echo ""
  if php "${DEPLOY_DIR}/scripts/deploy-tracking-verify.php"; then
    log_ok "deploy-tracking-verify.php OK"
  else
    log_fail "deploy-tracking-verify.php — erreurs ci-dessus"
  fi
  echo ""
}

fix_permissions() {
  if id jomas >/dev/null 2>&1; then
    chown -R jomas:jomas "$DEPLOY_DIR" 2>/dev/null && log_ok "Permissions jomas:jomas" || log_warn "chown jomas — ignoré"
  fi
}

print_pm2_status() {
  if command -v pm2 >/dev/null 2>&1; then
    log "État PM2 :"
    pm2 status 2>/dev/null | head -20 || true
  fi
}

# ─── Main ────────────────────────────────────────────────────────────────────

log "========== Déploiement sugar-paper =========="
log "Répertoire : ${DEPLOY_DIR}"

[[ -d "${DEPLOY_DIR}/.git" ]] || { log_fail "Pas de dépôt git. Lancez install-vps-fresh.sh"; exit 1; }

ensure_git_safe_directory || true
deploy_git_pull || true
deploy_migrations
deploy_composer
verify_secrets_quick
deploy_tracking_server
fix_permissions
verify_site_http
verify_socket_public
verify_android_assetlinks
run_php_tracking_verify
print_pm2_status

log "========== Fin déploiement =========="
log "Erreurs : ${DEPLOY_ERRORS} | Avertissements : ${DEPLOY_WARNINGS}"

if [[ "$DEPLOY_ERRORS" -gt 0 ]]; then
  log "Déploiement terminé AVEC ERREURS — corrigez les points FAIL ci-dessus."
  exit 1
fi

log "Déploiement terminé avec succès."
exit 0
