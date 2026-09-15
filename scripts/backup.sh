#!/usr/bin/env bash
set -euo pipefail
umask 077
: "${MYSQL_CNF:?Path to a chmod 600 MySQL client option file is required}"
: "${BACKUP_PUBLIC_KEY:?Path to the backup recipient public certificate (PEM) is required}"
: "${BACKUP_DIR:?Set a dedicated backup directory}"
: "${DB_DATABASE:?Set the database name}"
[[ "$DB_DATABASE" =~ ^[a-zA-Z0-9_]+$ ]] || { echo 'Invalid database name.' >&2; exit 1; }
mkdir -p "$BACKUP_DIR"
backup_name="${DB_DATABASE}-$(date -u +%Y%m%dT%H%M%SZ).sql.gz.enc"
backup_partial="$(mktemp "$BACKUP_DIR/.partial.XXXXXXXX")"
trap 'rm -f -- "$backup_partial"' EXIT
mysqldump --defaults-extra-file="$MYSQL_CNF" --single-transaction --quick --no-tablespaces --set-gtid-purged=OFF --hex-blob --databases "$DB_DATABASE" \
  | gzip \
  | openssl cms -encrypt -binary -aes-256-cbc -stream -outform DER -out "$backup_partial" "$BACKUP_PUBLIC_KEY"
mv -- "$backup_partial" "$BACKUP_DIR/$backup_name"
sha256sum "$BACKUP_DIR/$backup_name" > "$BACKUP_DIR/$backup_name.sha256"
printf 'Encrypted backup created: %s\n' "$backup_name"
