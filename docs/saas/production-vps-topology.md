# Production topology — VPS shared infrastructure (ris.polytronx.com)

> Status: **live**. This is the authoritative production record. Hostinger
> remains the documented target for the plain-PHP deploy runbook only; the
> shared-VPS flow below is what actually serves https://ris.polytronx.com.

## Architecture

```
Cloudflare (DNS ris.polytronx.com → 185.252.233.186)
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

## Housekeeping facts

- Image: multi-stage (Node SPA build → php:8.4-apache), ships `pdo_mysql` +
  `pdo_pgsql` + `phpredis`; local dev stays MySQL, prod is PG.
- `/opt/adc-portal-data/` bind-mounts: `storage/`, `uploads/`, `backups/`.
- NPM admin account (`drfaisal@polytronx.com`) untouched; deploy-time proxy
  changes used a temporary bootstrap admin, deleted immediately after use.
- Secrets: never printed to logs; env transforms run server-side.
