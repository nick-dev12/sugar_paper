#!/usr/bin/env bash
set -euo pipefail

SITE_DIR="/home/jomas/sugar-paper.com"
STAMP=$(date +%Y%m%d_%H%M%S)
BDIR="/home/jomas/backups/sugar-paper_${STAMP}"
mkdir -p "$BDIR"

cd "$SITE_DIR"
DB_NAME=$(grep 'db_name' conn/conn.php | head -1 | sed 's/.*= "\([^"]*\)";.*/\1/')
DB_USER=$(grep 'db_user' conn/conn.php | head -1 | sed 's/.*= "\([^"]*\)";.*/\1/')
DB_PASS=$(grep 'db_pass' conn/conn.php | head -1 | sed 's/.*= "\([^"]*\)";.*/\1/')

echo "=== Dump SQL : $DB_NAME ==="
mysqldump -u "$DB_USER" -p"$DB_PASS" \
  --single-transaction --routines --triggers --events \
  "$DB_NAME" > "$BDIR/jomas_paper_full.sql"

echo "=== Archive site ==="
cd /home/jomas
zip -r -q "$BDIR/sugar-paper-site.zip" sugar-paper.com \
  -x 'sugar-paper.com/.git/*' \
     'sugar-paper.com/tracking-server/node_modules/*' \
     'sugar-paper.com/storage/email_queue/*' \
     'sugar-paper.com/storage/notify_queue/*'

echo "=== Terminé ==="
ls -lah "$BDIR"
echo "BACKUP_DIR=$BDIR"
