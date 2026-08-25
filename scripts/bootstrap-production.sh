#!/usr/bin/env bash
# ADC PMS — post-deploy bootstrap (run once after containers are up)
set -euo pipefail
cd /opt/docker/laravel-app

C="docker compose -f docker-compose.prod.yml exec -T php"

echo "== wait for php container =="
for i in $(seq 1 60); do
  $C php -v >/dev/null 2>&1 && break
  sleep 5
done
$C php -v | head -1

echo "== composer install =="
$C bash -lc 'cd /var/www && composer install --no-interaction --optimize-autoloader --no-dev 2>&1 | tail -2'

echo "== key + migrations + seed =="
$C bash -lc 'cd /var/www && php artisan key:generate --force && php artisan migrate --force && php artisan db:seed --force'

echo "== storage link + caches =="
$C bash -lc 'cd /var/www && php artisan storage:link && php artisan config:cache && php artisan route:cache && php artisan view:cache'

echo "== fix clinic record (name/layout/active) =="
$C bash -lc 'cd /var/www && php artisan tinker --execute="
\$b = \App\Models\Business::first();
\$b->name = \"ADC - Amad Diagnostic Centre\";
\$b->slug = \"adc-amad-diagnostic-centre\";
\$b->form_type = \"form-layout\";
\$b->layouts = \"Formlayout11\";
\$b->theme_color = \"color1-Formlayout11\";
\$b->is_disable = 0;
\$b->save();
echo \"business: \".\$b->name.\" | \".\$b->layouts.PHP_EOL;
"'

echo "== secure admin password =="
ADMINPASS=$(openssl rand -base64 12 | tr -d '/+=' )
$C bash -lc "cd /var/www && php artisan tinker --execute=\"\\\$u = \App\Models\User::where('type','admin')->first(); \\\$u->password = bcrypt('$ADMINPASS'); \\\$u->email = 'admin@amaddiagnosticcentre.com.pk'; \\\$u->email_verified_at = now(); \\\$u->save(); echo 'admin email: '.\\\$u->email.PHP_EOL;\""
echo "$ADMINPASS" > .adminpass.initial
chmod 600 .adminpass.initial

echo "== settings sanity =="
$C bash -lc 'cd /var/www && php artisan tinker --execute="echo \App\Models\Setting::where(\"key\",\"footer_text\")->where(\"business\",0)->value(\"value\").PHP_EOL; echo \"businesses: \".\App\Models\Business::count().\" appointments: \".\App\Models\Appointment::count().PHP_EOL;"'

echo "== local health through nginx =="
sleep 2
HTTP=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1/login -H "Host: portal.amaddiagnosticcentre.com.pk" || true)
echo "origin /login -> $HTTP"
echo "BOOTSTRAP DONE"
