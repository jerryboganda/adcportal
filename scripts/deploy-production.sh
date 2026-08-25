#!/usr/bin/env bash
# ============================================================
# ADC PMS — Production Deploy (CloudPanel VPS)
# Usage (on the server, from SITE_ROOT):
#   bash scripts/deploy-production.sh
# Or from your machine:
#   ssh root@SERVER_IP "cd /home/amaddiagnosticcentre-portal/htdocs/portal.amaddiagnosticcentre.com.pk && bash scripts/deploy-production.sh"
#
# Safe by design: DB backup -> maintenance -> pull -> install ->
# migrate -> optimize -> restart workers -> health check -> rollback hint.
# ============================================================
set -euo pipefail

SITE_ROOT="${SITE_ROOT:-/home/amaddiagnosticcentre-portal/htdocs/portal.amaddiagnosticcentre.com.pk}"
cd "$SITE_ROOT"

echo "== [0/8] Pre-flight =="
php -v | head -1
test -f artisan || { echo "FATAL: not a Laravel root: $SITE_ROOT"; exit 1; }

echo "== [1/8] Backup database =="
STAMP=$(date +%Y%m%d_%H%M%S)
mkdir -p backups/db
# Pull DB creds from .env (no hardcoded secrets)
DB_CONNECTION=$(grep -E '^DB_CONNECTION=' .env | cut -d= -f2)
if [ "$DB_CONNECTION" = "mysql" ]; then
  DB_DATABASE=$(grep -E '^DB_DATABASE=' .env | cut -d= -f2)
  DB_USERNAME=$(grep -E '^DB_USERNAME=' .env | cut -d= -f2)
  DB_PASSWORD=$(grep -E '^DB_PASSWORD=' .env | cut -d= -f2)
  DB_HOST=$(grep -E '^DB_HOST=' .env | cut -d= -f2)
  mysqldump -h"${DB_HOST:-127.0.0.1}" -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" | gzip > "backups/db/pre_deploy_${STAMP}.sql.gz"
else
  cp database/database.sqlite "backups/db/pre_deploy_${STAMP}.sqlite" 2>/dev/null || true
fi
echo "backup: backups/db/pre_deploy_${STAMP}.*"

echo "== [2/8] Maintenance mode =="
php artisan down --retry=60 || true

cleanup() { php artisan up || true; }
trap cleanup EXIT

echo "== [3/8] Pull latest code =="
git fetch origin main
git reset --hard origin/main
git log --oneline -1

echo "== [4/8] Composer (no dev) =="
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

echo "== [5/8] Migrate (safe, forced) =="
php artisan migrate --force

echo "== [6/8] Optimize caches =="
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache 2>/dev/null || true

echo "== [7/8] Storage link + restart workers =="
php artisan storage:link >/dev/null 2>&1 || true
php artisan queue:restart 2>/dev/null || true

echo "== [8/8] Health check =="
php artisan up
HTTP=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1/login || true)
echo "local /login -> $HTTP"
if [ "$HTTP" != "200" ] && [ "$HTTP" != "302" ]; then
  echo "WARNING: unexpected status. Rollback with:"
  echo "  git reset --hard ORIG_HEAD  && composer install --no-dev && php artisan migrate:rollback --step=1"
  exit 1
fi

echo "DEPLOY OK @ $(git log --oneline -1)"
