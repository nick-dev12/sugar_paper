#!/usr/bin/env bash
# Alias : même script que install-vps-fresh.sh
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
exec bash "$SCRIPT_DIR/install-vps-fresh.sh" "$@"
