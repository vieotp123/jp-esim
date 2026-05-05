#!/bin/bash
# scripts/db_backup_daily.sh
# Daily mysqldump → gzip into /home/levanrin2404/db_backups/, 7-day retention.
# Run as root via systemd (so it can read db_config.php directly).

set -euo pipefail

CONFIG=/home/foamljf4kvet/db_config.php
BACKUP_DIR=/home/levanrin2404/db_backups
LOG=/var/log/jpesim/db_backup.log
RETAIN_DAYS=7

mkdir -p "$BACKUP_DIR"
mkdir -p "$(dirname "$LOG")"

ts() { date '+%Y-%m-%d %H:%M:%S'; }
log() { echo "[$(ts)] $*" >> "$LOG"; }

if [ ! -r "$CONFIG" ]; then
    log "FAIL: cannot read $CONFIG"
    exit 1
fi

DB_HOST=$(grep -E "'DB_HOST'" "$CONFIG" | sed -E "s/.*=> '([^']+)'.*/\1/")
DB_USER=$(grep -E "'DB_USER'" "$CONFIG" | sed -E "s/.*=> '([^']+)'.*/\1/")
DB_PASS=$(grep -E "'DB_PASS'" "$CONFIG" | sed -E "s/.*=> '([^']+)'.*/\1/")
DB_NAME=$(grep -E "'DB_NAME'" "$CONFIG" | sed -E "s/.*=> '([^']+)'.*/\1/")
DB_PORT=$(grep -E "'DB_PORT'" "$CONFIG" | sed -E "s/.*=> '([^']+)'.*/\1/" || echo "3306")

if [ -z "$DB_USER" ] || [ -z "$DB_PASS" ] || [ -z "$DB_NAME" ]; then
    log "FAIL: missing DB credentials"
    exit 1
fi

STAMP=$(date +%Y%m%d_%H%M%S)
OUT="$BACKUP_DIR/jpesim_${DB_NAME}_${STAMP}.sql.gz"
TMP="$OUT.partial"

# Use defaults-file approach to avoid leaking pass on cmdline
DEFAULTS=$(mktemp)
chmod 600 "$DEFAULTS"
trap 'rm -f "$DEFAULTS"' EXIT
cat > "$DEFAULTS" <<EOF
[client]
host=$DB_HOST
port=$DB_PORT
user=$DB_USER
password=$DB_PASS
EOF

START=$(date +%s)
if mysqldump --defaults-extra-file="$DEFAULTS" --single-transaction --quick --routines --triggers --events --skip-lock-tables "$DB_NAME" 2>>"$LOG" | gzip -c > "$TMP"; then
    SIZE=$(stat -c %s "$TMP")
    if [ "$SIZE" -lt 1024 ]; then
        log "FAIL: backup tiny ($SIZE bytes); not promoting"
        rm -f "$TMP"
        exit 2
    fi
    # Sanity check: gzip file should decompress and start with -- MySQL or /*!
    if ! gzip -t "$TMP" 2>>"$LOG"; then
        log "FAIL: gzip integrity check failed"
        rm -f "$TMP"
        exit 2
    fi
    HEAD=$(zcat "$TMP" 2>/dev/null | dd bs=1 count=200 2>/dev/null | tr -d '\0' || true)
    if [[ "$HEAD" != *"MySQL"* && "$HEAD" != *"MariaDB"* && "$HEAD" != *"-- "* ]]; then
        log "FAIL: backup head looks wrong: $(echo "$HEAD" | head -c 80)"
        rm -f "$TMP"
        exit 2
    fi
    chmod 600 "$TMP"
    chown levanrin2404:levanrin2404 "$TMP" || true
    mv "$TMP" "$OUT"
    DUR=$(( $(date +%s) - START ))
    log "OK $OUT size=${SIZE}B dur=${DUR}s"
else
    log "FAIL: mysqldump pipeline error"
    rm -f "$TMP"
    exit 3
fi

# Retention: delete backups older than RETAIN_DAYS days
find "$BACKUP_DIR" -maxdepth 1 -name 'jpesim_*.sql.gz' -mtime +$RETAIN_DAYS -print -delete 2>>"$LOG" | while read -r f; do
    log "PURGED $f"
done

exit 0
