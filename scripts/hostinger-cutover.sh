#!/usr/bin/env bash
# ADC PMS — Hostinger CUTOVER (run after DB is created in hPanel)
# Usage: bash hostinger-cutover.sh '<DB_PASSWORD>'
set -euo pipefail
DOMAIN=amaddiagnosticcentre.com.pk
BASE=~/domains/$DOMAIN
D=$BASE/app
DBPASS="${1:?Usage: hostinger-cutover.sh <DB_PASSWORD>}"

echo "== [1] Wire DB creds into .env =="
cd $D
python3 - "$DBPASS" <<'PY'
import re, sys
p = '.env'
s = open(p).read()
pw = sys.argv[1]
s = re.sub(r'^DB_PASSWORD=.*$', 'DB_PASSWORD=%s' % pw, s, flags=re.M)
open(p, 'w').write(s)
PY
grep -E '^DB_(DATABASE|USERNAME)=' .env
grep -qE '^DB_PASSWORD=.' .env && echo "DB_PASSWORD set"

echo "== [2] Test DB connection =="
php artisan tinker --execute="try { DB::select('select 1'); echo 'DB_OK'; } catch (Throwable \$e) { echo 'DB_FAIL: '.\$e->getMessage(); }" | tail -1
php artisan tinker --execute="echo (DB::select('select 1') ? 'DB_OK' : 'DB_FAIL');" 2>/dev/null | grep -q DB_OK || { echo "FATAL: DB connection failed — check hPanel DB name/user/password"; exit 1; }

echo "== [3] Migrate + seed =="
php artisan migrate --force
php artisan db:seed --force

echo "== [4] Clinic record + admin credentials =="
php artisan tinker --execute="
\$b = \App\Models\Business::first();
\$b->name = 'ADC - Amad Diagnostic Centre';
\$b->slug = 'adc-amad-diagnostic-centre';
\$b->form_type = 'form-layout';
\$b->layouts = 'Formlayout11';
\$b->theme_color = 'color1-Formlayout11';
\$b->is_disable = 0;
\$b->save();
echo 'business: '.\$b->name.' | '.\$b->layouts.PHP_EOL;
"
ADMINPASS=$(openssl rand -base64 12 | tr -d '/+=')
php artisan tinker --execute="
\$u = \App\Models\User::where('type','admin')->first();
\$u->email = 'admin@amaddiagnosticcentre.com.pk';
\$u->password = bcrypt('$ADMINPASS');
\$u->email_verified_at = now();
\$u->save();
echo 'admin email: '.\$u->email.PHP_EOL;
"
# settings already seeded from APP_NAME; enforce footer branding
php artisan tinker --execute="
\$v = 'Copyright © ADC - Amad Diagnostic Centre | Powered By PolytronX - Business Digitalized';
\App\Models\Setting::where('key','footer_text')->update(['value' => \$v]);
echo 'footer set'.PHP_EOL;
"
echo "$ADMINPASS" > "$BASE/.adminpass.initial"
chmod 600 "$BASE/.adminpass.initial"

echo "== [5] Caches + storage link =="
php artisan storage:link || true
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize:clear >/dev/null 2>&1 || true
php artisan config:cache && php artisan route:cache && php artisan view:cache

echo "== [6] Cutover docroot (WP -> Laravel) =="
cd $BASE
if [ ! -d public_html_wp_backup ]; then
  mv public_html public_html_wp_backup
fi
rm -rf public_html
mv public_laravel public_html
# keep a redirect-free clean state
echo "docroot swapped:"
ls -la "$BASE/public_html/" | head -6

echo "== [7] Live verification =="
sleep 2
CODE=$(curl -s -o /dev/null -w "%{http_code}" -L https://amaddiagnosticcentre.com.pk/login || true)
echo "https://amaddiagnosticcentre.com.pk/login -> $CODE"
curl -s -L https://amaddiagnosticcentre.com.pk/login | grep -o "ADC - Amad Diagnostic Centre" | head -1 || true
curl -s -o /dev/null -w "health: %{http_code}\n" https://amaddiagnosticcentre.com.pk/api/v1/health
ASSET=$(curl -s -L -o /dev/null -w "%{http_code}" https://amaddiagnosticcentre.com.pk/build/manifest.json || true)
echo "build asset -> $ASSET"
echo "CUTOVER DONE — admin password in ~/domains/$DOMAIN/.adminpass.initial"
