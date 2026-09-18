#!/usr/bin/env bash
# ============================================================
# Guarded "migrate prod from dev".
# localhost runs against the LIVE production Postgres via the
# tunnel, so every php artisan migrate IS a production change.
# This wrapper takes a pg_dump safety backup on the VPS first.
# Usage: scripts/dev-migrate-prod.sh
# ============================================================
set -euo pipefail
cd "$(dirname "$0")/.."

./scripts/dev-tunnel.sh start

TS=$(date +%Y%m%d-%H%M%S)
DUMP="ris-pre-migrate-${TS}.sql.gz"
echo "backing up production DB on VPS -> /opt/adc-portal-backups/${DUMP}"
ssh -o ConnectTimeout=10 vps "docker exec platform-postgres sh -c \
  'PGPASSWORD=\$POSTGRES_PASSWORD pg_dump -U platform_admin -d ris' | gzip > /opt/adc-portal-backups/${DUMP} \
  && ls -la /opt/adc-portal-backups/${DUMP}"

echo "--- pending migrations:"
php artisan migrate:status | grep -E "Pending|Y" >/dev/null 2>&1 || true
php artisan migrate:status | tail -n +2 | grep -i pending || echo "(none pending)"

echo "--- running migrate against production DB..."
php artisan migrate --force

echo "done. backup kept at /opt/adc-portal-backups/${DUMP} on the VPS."
