#!/usr/bin/env bash
#
# Installation propre du site sur le VPS :
#   1. Vérifier / installer git
#   2. Configurer user.name et user.email
#   3. Supprimer le dossier sugar-paper.com
#   4. Cloner le dépôt GitHub
#   5. Afficher la liste des fichiers sensibles à remettre à la main
#
# Usage :
#   bash install-vps-fresh.sh
#
# Variables optionnelles (évite les questions) :
#   SITE_DIR=/home/jomas/sugar-paper.com
#   GIT_USER_NAME="Votre Nom"
#   GIT_USER_EMAIL="vous@email.com"
#   GIT_REPO=https://github.com/nick-dev12/sugar_paper.git
#   GIT_BRANCH=main
#   CONFIRM_OUI=1          # saute la confirmation (danger)
#
set -euo pipefail

SITE_DIR="${SITE_DIR:-/home/jomas/sugar-paper.com}"
PARENT_DIR="$(dirname "$SITE_DIR")"
SITE_NAME="$(basename "$SITE_DIR")"
GIT_REPO="${GIT_REPO:-https://github.com/nick-dev12/sugar_paper.git}"
GIT_BRANCH="${GIT_BRANCH:-main}"
GIT_USER_NAME="${GIT_USER_NAME:-}"
GIT_USER_EMAIL="${GIT_USER_EMAIL:-}"

echo ""
echo "=========================================="
echo "  Installation fraîche — Sugar Paper"
echo "=========================================="
echo "Dossier cible : $SITE_DIR"
echo "Dépôt         : $GIT_REPO"
echo "Branche       : $GIT_BRANCH"
echo ""

# --- 1. Git installé ? ---
if command -v git >/dev/null 2>&1; then
  echo "[OK] Git est installé : $(git --version)"
else
  echo "[!] Git n'est pas installé. Tentative d'installation…"
  if command -v apt-get >/dev/null 2>&1; then
    apt-get update -qq
    apt-get install -y git
  elif command -v yum >/dev/null 2>&1; then
    yum install -y git
  elif command -v dnf >/dev/null 2>&1; then
    dnf install -y git
  else
    echo "ERREUR : installez git manuellement puis relancez ce script."
    exit 1
  fi
  echo "[OK] Git installé : $(git --version)"
fi

# --- 2. user.name / user.email ---
if [[ -z "$GIT_USER_NAME" ]]; then
  read -r -p "Git user.name [Sugar Paper] : " GIT_USER_NAME
  GIT_USER_NAME="${GIT_USER_NAME:-Sugar Paper}"
fi
if [[ -z "$GIT_USER_EMAIL" ]]; then
  read -r -p "Git user.email [deploy@sugar-paper.com] : " GIT_USER_EMAIL
  GIT_USER_EMAIL="${GIT_USER_EMAIL:-deploy@sugar-paper.com}"
fi

git config --global user.name "$GIT_USER_NAME"
git config --global user.email "$GIT_USER_EMAIL"
echo "[OK] Git configuré : $GIT_USER_NAME <$GIT_USER_EMAIL>"

# --- 3. Confirmation suppression ---
echo ""
echo "ATTENTION : tout le contenu de $SITE_DIR sera SUPPRIMÉ."
echo "Les fichiers sensibles (BDD, Firebase, .env…) devront être remis à la main après le clone."
echo ""

if [[ "${CONFIRM_OUI:-}" != "1" ]]; then
  read -r -p "Tapez oui pour continuer : " CONFIRM
  if [[ "$CONFIRM" != "oui" ]]; then
    echo "Annulé."
    exit 0
  fi
fi

# --- 4. Suppression + clone ---
mkdir -p "$PARENT_DIR"

if [[ -d "$SITE_DIR" ]]; then
  echo "[…] Suppression de $SITE_DIR"
  rm -rf "$SITE_DIR"
fi

echo "[…] Clone en cours…"
git clone --branch "$GIT_BRANCH" --single-branch "$GIT_REPO" "$SITE_DIR"

cd "$SITE_DIR"
chmod +x scripts/deploy.sh 2>/dev/null || true

# --- 5. Dépendances de base (optionnel mais utile) ---
if command -v composer >/dev/null 2>&1 && [[ -f composer.json ]]; then
  echo "[…] composer install"
  composer install --no-dev --optimize-autoloader --no-interaction
fi

if [[ -f tracking-server/package.json ]] && command -v npm >/dev/null 2>&1; then
  echo "[…] npm install (tracking-server)"
  (cd tracking-server && npm ci --omit=dev 2>/dev/null || npm install --omit=dev)
fi

# Permissions Webuzo
if id jomas >/dev/null 2>&1; then
  chown -R jomas:jomas "$SITE_DIR" 2>/dev/null || true
fi

echo ""
echo "=========================================="
echo "  Clone terminé avec succès"
echo "=========================================="
echo ""
echo "Étape suivante : remettre MANUELLEMENT les fichiers sensibles."
echo "Liste complète : scripts/FICHIERS_SENSIBLES.md"
echo ""
echo "Minimum indispensable :"
echo "  conn/conn.php"
echo "  config/email.php"
echo "  config/firebase_config.php"
echo "  config/firebase_server.php"
echo "  config/tracking.php"
echo "  tracking-server/.env"
echo "  sugar-paper-*.json  (clé Firebase service account)"
echo ""
echo "Copier depuis les exemples si besoin :"
echo "  cp conn/conn.example.php conn/conn.php"
echo "  cp config/email.example.php config/email.php"
echo ""
echo "Mises à jour futures (sans tout supprimer) :"
echo "  cd $SITE_DIR && bash scripts/deploy.sh"
echo ""
