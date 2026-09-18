# DEPLOYMENT GUIDE — PolytronX - Enterprise PACS & RIS (SaaS) on the production VPS

Architecture: **Laravel 11 (PHP 8.4) + PostgreSQL 17 + React/Vite SPA**, shipped
as a single Docker image (`ghcr.io/jerryboganda/adcportal:latest`) running on
the production VPS **185.252.233.186** at **https://ris.polytronx.com**
(Cloudflare DNS → VPS). All heavy compute stays in **GitHub Actions**; the
server only pulls the published image and serves the live app.

```
Browser ──> Cloudflare ──> VPS 185.252.233.186
                             |-- nginx-proxy-manager (TLS, ris.polytronx.com)
                             |-- adc-portal-app   (GHCR image, Apache + PHP 8.4)
                             |     |-- /api/v1/*  -> Laravel API (Sanctum cookies)
                             |     |-- /sanctum/* -> CSRF cookie
                             |     `-- everything else -> SPA (public/)
                             `-- platform-postgres (shared PostgreSQL 17)
```

## 1. One-time server setup (done)

1. Compose project lives at `/opt/docker/adc-portal` (`docker-compose.yml` with
   the `app` service + persistent volumes under `/opt/adc-portal-data`).
2. The app connects to the shared `platform-postgres` container
   (db/user `ris`); `DB_CONNECTION=pgsql`.
3. TLS terminates at nginx-proxy-manager (Let's Encrypt, auto-renew).

## 2. Release flow (automated)

1. Push to `main` → **CI** runs the PHP feature suite, SPA typecheck + build,
   and the Playwright E2E journey. The SPA bundle is archived as an artifact.
2. **Publish — GHCR image** builds and pushes
   `ghcr.io/jerryboganda/adcportal:latest` for the release commit.
3. **Deploy to production VPS** job SSHes into the server and runs:

   ```
   cd /opt/docker/adc-portal
   docker compose pull app
   docker compose up -d app
   ```

   The container entrypoint applies pending migrations (`php artisan migrate
   --force`) and refreshes config/route caches automatically before Apache
   starts serving.

## 3. Required GitHub secrets

| Secret | Value |
|---|---|
| `VPS_SSH_HOST` | `185.252.233.186` |
| `VPS_SSH_USER` | `root` |
| `VPS_SSH_PORT` | `22` |
| `VPS_SSH_KEY` | private SSH key allowed to log in as `root` |
| `VPS_APP_PATH` | `/opt/docker/adc-portal` |

If these are absent the deploy job reports a notice and skips (CI still runs).

## 4. Manual release / rollback

```bash
ssh vps                                   # uses ~/.ssh/id_ed25519
cd /opt/docker/adc-portal
docker compose pull app && docker compose up -d app   # release
docker compose exec app php artisan migrate:status    # verify schema
# rollback: point docker-compose.yml at the previous image digest, then up -d
```

## 5. Health checks

- API: `curl -fsS https://ris.polytronx.com/api/v1/health` → `{"ok":true,...}`
- Entrypoint log: `docker logs adc-portal-app --since 5m`
- Container: `docker ps | grep adc-portal-app` (Up, not Restarting)

## 6. Data safety

- Clinical data is never auto-deleted; the platform Postgres volume is the
  system of record. `migrate --force` only applies versioned migrations.
- Never run destructive artisan commands against the production database
  outside the versioned migration path.
