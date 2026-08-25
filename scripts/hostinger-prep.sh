#!/usr/bin/env bash
# ADC PMS — Hostinger prep (NON-DESTRUCTIVE: WP site untouched, only backed up)
set -euo pipefail
DOMAIN=amaddiagnosticcentre.com.pk
BASE=~/domains/$DOMAIN
REPO=https://github.com/jerryboganda/adcportal.git

echo "== [1] Backup current WordPress docroot =="
mkdir -p ~/backups
STAMP=$(date +%Y%m%d_%H%M%S)
tar -czf ~/backups/adc_wp_public_html_${STAMP}.tar.gz -C "$BASE" public_html 2>/dev/null
ls -lh ~/backups/adc_wp_public_html_${STAMP}.tar.gz

echo "== [2] Clone / update Laravel app =="
if [ -d "$BASE/app/.git" ]; then
  cd "$BASE/app" && git fetch origin main && git reset --hard origin/main
else
  git clone --depth 1 $REPO "$BASE/app"
  cd "$BASE/app"
fi
git log --oneline -1

echo "== [3] Composer (no dev) =="
/usr/local/bin/composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction 2>&1 | tail -3

echo "== [4] Env + key =="
if [ ! -f .env ]; then
  cp .env.example .env
  # placeholders; DB creds filled at cutover
  sed -i "s|^APP_NAME=.*|APP_NAME=\"ADC - Amad Diagnostic Centre\"|" .env
  sed -i "s|^APP_ENV=.*|APP_ENV=production|" .env
  sed -i "s|^APP_DEBUG=.*|APP_DEBUG=false|" .env
  sed -i "s|^APP_URL=.*|APP_URL=https://amaddiagnosticcentre.com.pk|" .env
  sed -i "s|^APP_TIMEZONE=.*|APP_TIMEZONE=Asia/Karachi|" .env
  sed -i "s|^DB_CONNECTION=.*|DB_CONNECTION=mysql|" .env
  sed -i "s|^DB_HOST=.*|DB_HOST=127.0.0.1|" .env
  sed -i "s|^SESSION_DRIVER=.*|SESSION_DRIVER=file|" .env
  sed -i "s|^CACHE_STORE=.*|CACHE_STORE=file|" .env
  sed -i "s|^QUEUE_CONNECTION=.*|QUEUE_CONNECTION=sync|" .env
  sed -i "s|^MAIL_MAILER=.*|MAIL_MAILER=log|" .env
fi
grep -q "^APP_KEY=base64" .env || php artisan key:generate --force --no-interaction
grep -E "^APP_KEY=" .env | cut -c1-16

echo "== [5] Runtime dirs + uploads =="
mkdir -p storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache uploads
chmod -R ug+rwX storage bootstrap/cache uploads 2>/dev/null || true

echo "== [6] Stage new docroot (public_laravel — NOT live yet) =="
rm -rf "$BASE/public_laravel"
mkdir -p "$BASE/public_laravel"
# adapted index.php
sed -e "s|__DIR__.'/../vendor/autoload.php'|__DIR__.'/../app/vendor/autoload.php'|" \
    -e "s|__DIR__.'/../bootstrap/app.php'|__DIR__.'/../app/bootstrap/app.php'|" \
    -e "s|__DIR__.'/../storage/framework/maintenance.php'|__DIR__.'/../app/storage/framework/maintenance.php'|" \
    public/index.php > "$BASE/public_laravel/index.php"
# laravel htaccess
cp public/.htaccess "$BASE/public_laravel/.htaccess"
# static assets via symlinks (into app tree)
ln -sfn "$BASE/app/public/build"         "$BASE/public_laravel/build"
ln -sfn "$BASE/app/public/assets"        "$BASE/public_laravel/assets"
ln -sfn "$BASE/app/public/form_layouts"  "$BASE/public_laravel/form_layouts"
ln -sfn "$BASE/app/public/module_assets" "$BASE/public_laravel/module_assets"
ln -sfn "$BASE/app/public/images"        "$BASE/public_laravel/images"
ln -sfn "$BASE/app/public/js"            "$BASE/public_laravel/js"
ln -sfn "$BASE/app/public/css"           "$BASE/public_laravel/css"
ln -sfn "$BASE/app/uploads"              "$BASE/public_laravel/uploads"
# favicon at root (asset('uploads/logo/favicon.png') covered by uploads link)
ls -la "$BASE/public_laravel/"
echo "PREP DONE"
