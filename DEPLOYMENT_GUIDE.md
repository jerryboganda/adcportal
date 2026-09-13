# DEPLOYMENT GUIDE — ADC Portal (SaaS) on Hostinger Business Plan

Architecture: **Laravel 11 (PHP 8.3) + MySQL + React/Vite SPA**, deployed as one
application on Hostinger shared hosting (Business plan). The SPA is compiled by
**GitHub Actions** and served by Laravel; all heavy compute stays in CI.

```
Browser ──> Apache (Hostinger, docroot = repo root)
              |-- static assets (public/, app build in public/app)
              |-- /api/v1/*        -> Laravel API (Sanctum cookie sessions)
              |-- /sanctum/csrf-cookie
              `-- everything else  -> SPA (public/app/index.html)
```

## 1. One-time Hostinger setup

1. **PHP version** - hPanel -> PHP Configuration -> **8.3**; enable extensions:
   `pdo_mysql`, `mbstring`, `openssl`, `curl`, `gd`, `zip`, `fileinfo`, `intl`.
2. **MySQL database** - hPanel -> Databases: create DB + user, note credentials.
3. **SSH** - hPanel -> SSH access: enable; you will run composer/migrate here
   (lightweight commands only - builds run in GitHub Actions).
4. **Document root** - keep the site's docroot at the repo root; the shipped
   `.htaccess` routes requests into `public/index.php` (Laravel) and serves the
   SPA catch-all. Protect `.env` (already denied in `.htaccess`).
5. **Cron** - hPanel -> Cron jobs:

   ```
   * * * * * cd /home/<user>/domains/<domain>/app && php artisan schedule:run >> /dev/null 2>&1
   ```

## 2. One-time application setup (SSH)

```bash
cd ~/domains/<domain>            # the app directory
git clone https://github.com/jerryboganda/adcportal.git .   # or git pull

cp .env.example .env             # then edit with hPanel DB credentials
php artisan key:generate --force

# edit .env: DB_DATABASE / DB_USERNAME / DB_PASSWORD / APP_URL / MAIL_*
# RIS_SUPER_ADMIN_PASSWORD and RIS_DEMO_PASSWORD: set real values, never commit.

composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --force      # plans + super admin + demo clinic
php artisan storage:link
php artisan config:cache
```

Default seeded accounts (set real passwords via `.env` BEFORE first seed, or
change them from the UI right after first login):

| Account | Email | Purpose |
|---|---|---|
| Platform super admin | `RIS_SUPER_ADMIN_EMAIL` | Tenant management (`/platform/*`) |
| Demo clinic admin | `RIS_DEMO_ADMIN_EMAIL` | Amad Diagnostic Centre demo tenant |

## 3. Deploying an update (fast cycle)

1. Push to `main` - GitHub Actions runs tests + builds the SPA bundle.
2. CI deploy job (uses secrets `HOSTINGER_SSH_HOST`, `HOSTINGER_SSH_USER`,
   `HOSTINGER_SSH_PORT`, `HOSTINGER_SSH_KEY`) uploads the built SPA into
   `public/app` and runs:

   ```bash
   git pull --ff-only
   composer install --no-dev --optimize-autoloader
   php artisan migrate --force
   php artisan config:cache
   ```

   These are the only production-side commands; no builds/tests run on the host.

## 4. SaaS (multi-tenant) notes

- A tenant = one clinic row (`businesses`); every table is `business_id`-scoped.
- New clinics self-register on the login screen (**Register Clinic**, 14-day
  trial) or are provisioned by the super admin.
- Subscription state (`trialing | active | suspended | expired`) is enforced by
  the `EnsureTenantActive` middleware. Activation is manual (super admin) - no
  payment gateway is wired yet; connect one before selling plans publicly.
- The demo tenant is the only one with "factory reset"; guarded server-side by
  `RIS_DEMO_TENANT_CODE`.

## 5. Data safety

- Never commit `.env` or secrets. Backups: hPanel -> Backups (daily). Test a
  restore before going live.
- The old VPS/docker-compose flow (`185.252.233.186`, ghcr image) is superseded
  by this Hostinger deployment; keep it only as a cold-standby option.
