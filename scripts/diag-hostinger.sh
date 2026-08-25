#!/usr/bin/env bash
D=~/domains/amaddiagnosticcentre.com.pk/app
echo "=== prep2 log around failure ==="
grep -B8 "error code 1" /tmp/prep2.log | head -24 || true
echo "=== env state ==="
grep -E '^(APP_KEY|DB_|APP_ENV)' $D/.env | sed 's/^\(APP_KEY=base64.\{6\}\).*/\1.../' 
echo "=== vendor? ==="
ls $D/vendor/laravel >/dev/null 2>&1 && echo VENDOR_OK || echo VENDOR_MISSING
echo "=== manual package:discover error ==="
cd $D && php artisan package:discover 2>&1 | head -12
echo "=== prep2 still running? ==="
pgrep -f hostinger-prep2 >/dev/null && echo STILL_RUNNING || echo SCRIPT_EXITED
