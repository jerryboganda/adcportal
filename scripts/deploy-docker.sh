#!/usr/bin/env bash
# ============================================================
# ADC Portal — production deploy (run on the shared VPS)
# Safe by design: pulls code, rebuilds image, migrates, optimizes.
# It NEVER touches /opt/adc-portal-data (patient data is preserved).
# ============================================================
set -euo pipefail

CODE_DIR="${CODE_DIR:-/opt/docker/adc-portal}"
cd "$CODE_DIR"

echo "== [1/5] Pull latest code =="
git fetch origin main
git reset --hard origin/main

echo "== [2/5] Pull prebuilt image from GHCR (builds run in GitHub Actions) =="
docker compose pull

echo "== [3/5] Start containers =="
docker compose up -d

echo "== [4/5] Migrate + optimize =="
docker exec adc-portal-app php artisan key:generate || true
docker exec adc-portal-app php artisan package:discover || true
docker exec adc-portal-app php artisan migrate --force
docker exec adc-portal-app php artisan config:clear
docker exec adc-portal-app php artisan route:clear
docker exec adc-portal-app php artisan view:clear
docker exec adc-portal-app php artisan storage:link || true

echo "== [5/5] Health check =="
HTTP=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8090/login || true)
echo "local /login -> $HTTP"
[ "$HTTP" = "200" ] || [ "$HTTP" = "302" ] || { echo "WARN: unexpected status"; exit 1; }

echo "DEPLOY OK"
