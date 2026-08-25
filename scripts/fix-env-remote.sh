#!/usr/bin/env bash
set -e
cd /opt/docker/laravel-app/src
python3 - <<'PY'
import re
p = '.env'
s = open(p).read()
s = re.sub(r'^APP_NAME=.*$', 'APP_NAME="ADC - Amad Diagnostic Centre"', s, flags=re.M)
s = re.sub(r'^MAIL_FROM_NAME=.*$', 'MAIL_FROM_NAME="ADC - Amad Diagnostic Centre"', s, flags=re.M)
open(p, 'w').write(s)
PY
head -1 .env
grep MAIL_FROM_NAME .env
cd ..
docker compose -f docker-compose.prod.yml restart php
sleep 12
docker ps | grep laravel-app-php || true
docker logs laravel-app-php-1 2>&1 | tail -4
