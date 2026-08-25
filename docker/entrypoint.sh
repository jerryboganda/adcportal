#!/usr/bin/env bash
set -e

# Link public/storage -> storage/app/public (idempotent; safe if already linked)
php artisan storage:link || true

exec apache2-foreground
