#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
umask 077
mkdir -p backups
target="backups/sga-$(date -u +%Y%m%dT%H%M%SZ).sql.gz"
partial="${target}.partial"
trap 'rm -f -- "$partial"' EXIT
docker compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysqldump -uroot --single-transaction --routines --triggers --events --set-gtid-purged=OFF --no-tablespaces "$MYSQL_DATABASE"' | gzip > "$partial"
gzip -t "$partial"
mv -- "$partial" "$target"
sha256sum "$target" > "${target}.sha256"
printf 'Backup concluído: %s\n' "$target"
