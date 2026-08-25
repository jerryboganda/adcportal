#!/usr/bin/env bash
set -e

# Ensure storage + uploads structure exists. When /var/www/html/storage is a
# host bind-mount (production), this populates the (initially empty) host dir.
# Idempotent — safe to run on every container start.
mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/testing \
    storage/logs \
    storage/app/public \
    public/uploads
chown -R www-data:www-data storage public/uploads 2>/dev/null || true

# Link public/storage -> storage/app/public (idempotent; safe if already linked)
php artisan storage:link || true

exec apache2-foreground
