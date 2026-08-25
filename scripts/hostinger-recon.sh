#!/usr/bin/env bash
# Read-only recon on Hostinger (via VPS jump). Nothing is modified.
set -e
R="ssh -o BatchMode=yes -o StrictHostKeyChecking=accept-new -i /root/.ssh/hostinger_key -p 65002 u776151780@145.79.25.8"

$R 'bash -s' <<'EOS'
echo "== whoami/home =="
whoami
echo $HOME
echo "== domains =="
ls ~/domains/
echo "== adc domain folder =="
ls ~/domains/amaddiagnosticcentre.com.pk/ 2>/dev/null | head -20
echo "== adc public_html (first 20) =="
ls ~/domains/amaddiagnosticcentre.com.pk/public_html/ 2>/dev/null | head -20
echo "== wp DB name (from wp-config) =="
grep -E "DB_NAME|DB_USER|DB_HOST" ~/domains/amaddiagnosticcentre.com.pk/public_html/wp-config.php 2>/dev/null | sed "s/PASSWORD.*/PASSWORD=***/"
echo "== php binaries =="
ls /opt/alt/ 2>/dev/null | grep -i php
which php php83 2>/dev/null
/opt/alt/php83/usr/bin/php -v 2>/dev/null | head -1 || true
echo "== composer =="
which composer 2>/dev/null || ls ~/bin/composer* /usr/local/bin/composer* 2>/dev/null || echo "composer not on PATH"
composer -V 2>/dev/null || true
echo "== git =="
which git && git --version
echo "== mysql client =="
which mysql mysqldump 2>/dev/null || echo "no mysql client"
echo "== symlink test =="
ln -s /tmp ~/symlink_test 2>&1 && echo "SYMLINK_OK" && rm -f ~/symlink_test || echo "SYMLINK_DISABLED"
echo "== disk =="
df -h ~ | tail -1
echo "== .my.cnf? =="
ls -la ~/.my.cnf 2>/dev/null || echo "no .my.cnf"
echo "== existing dbs hint (wp config) done =="
EOS
