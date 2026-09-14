# PolytronX - RIS

A multi-tenant **Radiology Information System (RIS)** — radiology study workflow,
patient management, MRI/contrast safety screening, dose tracking, radiologist
reporting with e-signature, billing/POS, inventory, doctor network dispatch and a
public queue board. Built as **Laravel (PHP 8.4) + MySQL/SQLite backend** with a
**React 19 + Vite + TypeScript SPA** frontend, served from a single origin.

## Repository layout

```
app/                  Laravel API (controllers in app/Http/Controllers/Api/V1)
database/             migrations + seeders
resources/views/      PDF blade templates (invoices, reports)
src/                  React SPA (entry: src/main.tsx)
tests/                PHPUnit feature tests + Playwright e2e (portal.spec.js)
```

## Local development

Requirements: PHP 8.4, Composer, Node 20+.

```bash
composer install
cp .env.example .env        # then set DB + RIS_SUPER_ADMIN_EMAIL/PASSWORD
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --force # baseline only: plans, super admin, catalogs
npm install
```

Run both processes:

- `php artisan serve` → API on :8000
- `npm run dev` → Vite dev server on :3000 (proxies `/api` + `/sanctum` to :8000)

Default test suite: `php artisan test` (in-memory SQLite, fully isolated).

### Demo data (local dev / CI e2e ONLY)

`RIS_DEMO_MODE=true` opts a local install into the demo tenant with fake
patients/studies (`database/seeders/RisDemoData.php`). **Never enable it in
production** — the seeder skips the demo block and `/api/v1/backup/reset-demo`
returns 404 unless the flag is on. To remove a demo tenant that was seeded by
mistake: `php artisan ris:purge-demo --force`.

## Production deployment

Deployed to Hostinger via GitHub Actions (tests + SPA build in CI; the host only
runs `git pull`, `composer install --no-dev`, `migrate`, `config:cache`).
See [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md) for the one-time setup.
