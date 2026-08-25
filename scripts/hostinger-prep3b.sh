#!/usr/bin/env bash
set -euo pipefail
D=~/domains/amaddiagnosticcentre.com.pk/app
B=~/domains/amaddiagnosticcentre.com.pk
cd $D

echo "== dirs first =="
mkdir -p storage/framework/{cache/data,sessions,views,testing} storage/logs storage/app/public bootstrap/cache uploads
chmod -R ug+rwX storage bootstrap/cache uploads 2>/dev/null || true

echo "== key =="
grep -q '^APP_KEY=base64' .env || php artisan key:generate --force --no-interaction
grep -E '^APP_KEY' .env | sed 's/^\(APP_KEY=base64.\{6\}\).*/\1.../'

echo "== discover check =="
php artisan package:discover 2>&1 | tail -2

echo "== composer final =="
/usr/local/bin/composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction 2>&1 | tail -2

echo "== stage docroot =="
rm -rf $B/public_laravel
mkdir -p $B/public_laravel
sed -e "s|__DIR__.'/../vendor/autoload.php'|__DIR__.'/../app/vendor/autoload.php'|" \
    -e "s|__DIR__.'/../bootstrap/app.php'|__DIR__.'/../app/bootstrap/app.php'|" \
    -e "s|__DIR__.'/../storage/framework/maintenance.php'|__DIR__.'/../app/storage/framework/maintenance.php'|" \
    public/index.php > $B/public_laravel/index.php
cp public/.htaccess $B/public_laravel/.htaccess
ln -sfn $B/app/public/build         $B/public_laravel/build
ln -sfn $B/app/public/assets        $B/public_laravel/assets
ln -sfn $B/app/public/form_layouts  $B/public_laravel/form_layouts
ln -sfn $B/app/public/module_assets $B/public_laravel/module_assets
ln -sfn $B/app/public/images        $B/public_laravel/images
ln -sfn $B/app/public/js            $B/public_laravel/js
ln -sfn $B/app/public/css           $B/public_laravel/css
ln -sfn $B/app/uploads              $B/public_laravel/uploads
ls -la $B/public_laravel/
echo PREP3B_DONE
