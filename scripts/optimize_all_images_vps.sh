#!/usr/bin/env bash
#
# Conversion batch des images existantes en WebP (+ variantes _md/_sm)
# et synchronisation des chemins en base de données.
#
# Usage sur le VPS (depuis la racine du projet déployé) :
#   bash scripts/optimize_all_images_vps.sh
#   bash scripts/optimize_all_images_vps.sh --dry-run
#   bash scripts/optimize_all_images_vps.sh produits
#   bash scripts/optimize_all_images_vps.sh --force
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

PHP_BIN="${PHP_BIN:-php}"

if ! command -v "$PHP_BIN" >/dev/null 2>&1; then
  echo "Erreur : PHP introuvable (PHP_BIN=$PHP_BIN)." >&2
  exit 1
fi

if [[ ! -f conn/conn.php ]]; then
  echo "Erreur : conn/conn.php introuvable. Exécutez ce script depuis le projet déployé." >&2
  exit 1
fi

echo "=== Sugar Paper — optimisation images existantes ==="
echo "Répertoire : $ROOT"
echo ""

# optimize_existing_images.php inclut déjà la sync BDD finale (sauf --skip-sync)
"$PHP_BIN" scripts/optimize_existing_images.php "$@"

echo ""
echo "=== Terminé ==="
