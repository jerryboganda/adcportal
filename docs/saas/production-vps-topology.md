# Production topology — VPS shared infrastructure (ris.polytronx.com)

> Status: **live**. This is the authoritative production record. The legacy
> remains the documented target for the plain-PHP deploy runbook only; the
> shared-VPS flow below is what actually serves https://ris.polytronx.com.

## Architecture

```
Cloudflare (DNS ris.polytronx.com → VPS origin)
  └─ nginx-proxy-manager  (shared reverse proxy for all polytronx.com sites)
       │   proxy_host 46 → http://adc-portal-app:80
       │   TLS: Let's Encrypt cert #48, expires 2026-12-16 (auto-renew)
       └─ adc-portal-app   ghcr.io/jerryboganda/adcportal:latest
            │  PHP 8.4-Apache + compiled SPA; entrypoint runs
            │  migrate --force + config/route cache + schedule:work
            ├─ platform-postgres  (SHARED PostgreSQL, pg17)  → db `ris`
            ├─ platform-redis     (SHARED Redis, ACL user `ris`, db 1)
            ├─ platform-minio     (SHARED S3, bucket `ris`)   [provisioned]
            └─ platform-soketi    (SHARED websockets)         [provisioned]
```

- App joins docker networks: `adc-portal_adc` + `platform` (+ NPM's network).
- Credentials live **only** in `/opt/platform/projects/ris.env` (root, 0600),
  mirrored into `/opt/adc-portal-data/.env.production` for the container.
- Provisioned via the canonical `provision-project.sh ris` — same pattern as
  gepa, insightlibrary, maternal-mind, ubag.

## Database: shared PostgreSQL

- `DB_CONNECTION=pgsql`, host `platform-postgres`, db/user `ris`.
- 74 tables, 84 migrations — schema built directly by Laravel migrations on PG.
- One PG-specific migration patch: `services.price` varchar→numeric needs an
  explicit `USING` cast (see `2026_08_25_100002_upgrade_services_for_radiology.php`).
- Legacy MySQL-only SQL (`SHOW TABLES`, `DATE()` groupings) exists only in
  unrouted legacy web controllers — not reachable in the SPA/API app.

### Old MySQL (decommissioned, data retained)

- Container `adc-portal-mysql` stopped (exit 0), restart policy `no`.
- Data volume + `/opt/adc-portal-data/mysql/` untouched; dumps in
  `/opt/adc-portal-backups/`. Drop for real only after a soak period.

## Data migration (MySQL → PG), 2026-09-17

- ETL ran **inside the app container** (both PDO drivers present) — no compute
  on the host, per the compute-placement rule.
- Topological FK-order copy of all tables except `migrations`; per-table count
  verification: **ALL COUNTS MATCH**; 65 id-sequences reset via `setval`.
- Pre-flight dump: `/opt/adc-portal-backups/adc_production-pre-migration-*.sql.gz`.

## Deploy flow (from now on)

1. Push to `main` → GitHub Actions builds/pushes `ghcr.io/jerryboganda/adcportal:latest`
   (compute in CI — the VPS never builds).
2. On the VPS:
   `cd /opt/docker/adc-portal && docker compose pull app && docker compose up -d app`
3. Entrypoint applies pending migrations + refreshes caches automatically.

## Local dev <-> production realtime link

Local dev can run directly against the LIVE production Postgres through an SSH
tunnel (user-approved 2026-09-18). Every local change reads/writes real prod
data in realtime; there is no copy or lag.

- `bash scripts/dev-tunnel.sh start|stop|status` — forwards
  `localhost:15433` → VPS `127.0.0.1:15432` (platform-postgres) and
  `localhost:16380` → `127.0.0.1:16379` (platform-redis, spare).
- `python scripts/dev-use-prod-db.py` — points local `.env` at the prod DB
  (fetches credentials server-side, backs up `.env` first).
- `python scripts/dev-use-local-db.py` — restores the backup, back to local DB.
- `bash scripts/dev-migrate-prod.sh` — pg_dump backup on the VPS, then
  `php artisan migrate --force` (the approved way to change prod schema from dev).
- **Guard**: while linked, `migrate:fresh` / `migrate:refresh` / `db:wipe` /
  `db:seed` are BLOCKED in `AppServiceProvider` (they would destroy prod data).
  Deliberate override: `ALLOW_PROD_DESTRUCTIVE=true php artisan db:seed`.
  Tests are unaffected (phpunit uses sqlite :memory:).
- Local PHP needs the pgsql extensions enabled (done: php.ini pdo_pgsql/pgsql).
- **Storage sync**: `bash scripts/dev-storage-sync.sh start` mirrors `uploads/`
  + `storage/app/` to `/opt/adc-portal-data` every ~2s (tar-over-ssh).
  New files propagate in both directions within seconds; for a same-file
  edit, prod is canonical locally (use the `push` command to force a local
  edit of an existing file up). Uses path+size manifests — immune to the
  laptop/VPS clock skew that breaks mtime-based deltas. `full` re-seeds,
  `stop` halts the loop; deletions are not propagated (remove both sides).

## Housekeeping facts

- Image: multi-stage (Node SPA build → php:8.4-apache), ships `pdo_mysql` +
  `pdo_pgsql` + `phpredis`; local dev stays MySQL, prod is PG.
- `/opt/adc-portal-data/` bind-mounts: `storage/`, `uploads/`, `backups/`.
- NPM admin account (`drfaisal@polytronx.com`) untouched; deploy-time proxy
  changes used a temporary bootstrap admin, deleted immediately after use.
- Secrets: never printed to logs; env transforms run server-side.
