#!/bin/bash
# ============================================================
# PolytronX RIS container entrypoint — light release commands
# ONLY (migrate/config cache). No heavy compute on the host,
# no destructive operations, never touches bind-mounted data.
# ============================================================
set -e

cd /var/www/html

if [ "${APP_MIGRATE_ON_BOOT:-true}" = "true" ]; then
    echo "[entrypoint] php artisan migrate --force"
    php artisan migrate --force
fi

if [ "${APP_OPTIMIZE_ON_BOOT:-true}" = "true" ]; then
    echo "[entrypoint] caching config + routes"
    php artisan config:cache || true
    php artisan route:cache || true
fi

echo "[entrypoint] handing off to: $*"
exec "$@"
