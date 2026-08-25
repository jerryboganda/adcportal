#!/usr/bin/env bash
# ADC PMS — Hostinger prep 3: env FIRST, then composer, then stage docroot
set -euo pipefail
DOMAIN=amaddiagnosticcentre.com.pk
BASE=~/domains/$DOMAIN
cd "$BASE/app"
git log --oneline -1

echo "== [A] .env (DB placeholders — creds filled at cutover) =="
if [ ! -f .env ]; then
  cp .env.example .env
fi
python3 - <<'PY' 2>/dev/null || sed -i \
  -e 's|^APP_NAME=.*|APP_NAME="ADC - Amad Diagnostic Centre"|' \
  -e 's|^APP_ENV=.*|APP_ENV=production|' \
  -e 's|^APP_DEBUG=.*|APP_DEBUG=false|' \
  -e 's|^APP_URL=.*|APP_URL=https://amaddiagnosticcentre.com.pk|' \
  -e 's|^APP_TIMEZONE=.*|APP_TIMEZONE=Asia/Karachi|' \
  -e 's|^DB_CONNECTION=.*|DB_CONNECTION=mysql|' \
  -e 's|^DB_HOST=.*|DB_HOST=127.0.0.1|' \
  -e 's|^DB_DATABASE=.*|DB_DATABASE=u776151780_adc|' \
  -e 's|^DB_USERNAME=.*|DB_USERNAME=u776151780_adc|' \
  -e 's|^SESSION_DRIVER=.*|SESSION_DRIVER=file|' \
  -e 's|^CACHE_STORE=.*|CACHE_STORE=file|' \
  -e 's|^QUEUE_CONNECTION=.*|QUEUE_CONNECTION=sync|' \
  -e 's|^MAIL_MAILER=.*|MAIL_MAILER=log|' .env
import re
p='.env'
s=open(p).read()
subs={
 'APP_NAME':'"ADC - Amad Diagnostic Centre"',
 'APP_ENV':'production',
 'APP_DEBUG':'false',
 'APP_URL':'https://amaddiagnosticcentre.com.pk',
 'APP_TIMEZONE':'Asia/Karachi',
 'DB_CONNECTION':'mysql',
 'DB_HOST':'127.0.0.1',
 'DB_DATABASE':'u776151780_adc',
 'DB_USERNAME':'u776151780_adc',
 'DB_PASSWORD':'CHANGE_ME',
 'SESSION_DRIVER':'file',
 'CACHE_STORE':'file',
 'QUEUE_CONNECTION':'sync',
 'MAIL_MAILER':'log',
}
for k,v in subs.items():
    if re.search(r'^%s=.*$'%k, s, re.M):
        s=re.sub(r'^%s=.*$'%k, '%s=%s'%(k,v), s, flags=re.M)
    else:
        s+='\n%s=%s\n'%(k,v)
open(p,'w').write(s)
PY
grep -q '^APP_KEY=base64' .env || php artisan key:generate --force --no-interaction
grep -E '^(APP_KEY|DB_DATABASE)' .env | sed 's/^\(APP_KEY=base64.\{6\}\).*/\1.../'

echo "== [B] Runtime dirs =="
mkdir -p storage/framework/{cache/data,sessions,views,testing} storage/logs storage/app/public bootstrap/cache uploads
chmod -R ug+rwX storage bootstrap/cache uploads 2>/dev/null || true

echo "== [C] Composer (hook must pass now) =="
/usr/local/bin/composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction 2>&1 | tail -3
php artisan package:discover 2>&1 | tail -2

echo "== [D] Stage docroot =="
BASE="$BASE" # ensure
rm -rf "$BASE/public_laravel"
mkdir -p "$BASE/public_laravel"
sed -e "s|__DIR__.'/../vendor/autoload.php'|__DIR__.'/../app/vendor/autoload.php'|" \
    -e "s|__DIR__.'/../bootstrap/app.php'|__DIR__.'/../app/bootstrap/app.php'|" \
    -e "s|__DIR__.'/../storage/framework/maintenance.php'|__DIR__.'/../app/storage/framework/maintenance.php'|" \
    public/index.php > "$BASE/public_laravel/index.php"
cp public/.htaccess "$BASE/public_laravel/.htaccess"
ln -sfn "$BASE/app/public/build"         "$BASE/public_laravel/build"
ln -sfn "$BASE/app/public/assets"        "$BASE/public_laravel/assets"
ln -sfn "$BASE/app/public/form_layouts"  "$BASE/public_laravel/form_layouts"
ln -sfn "$BASE/app/public/module_assets" "$BASE/public_laravel/module_assets"
ln -sfn "$BASE/app/public/images"        "$BASE/public_laravel/images"
ln -sfn "$BASE/app/public/js"            "$BASE/public_laravel/js"
ln -sfn "$BASE/app/public/css"           "$BASE/public_laravel/css"
ln -sfn "$BASE/app/uploads"              "$BASE/public_laravel/uploads"
echo "== staged =="
ls -la "$BASE/public_laravel/"
echo "PREP3 DONE"
