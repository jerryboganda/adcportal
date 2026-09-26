# AGENTS.md — PolytronX - Enterprise PACS & RIS (Radiology Clinic Management SaaS)

Multi-tenant Radiology Information System (RIS) for diagnostic clinics:
study workflow, safety screening, reporting, invoicing, inventory and doctor
dispatch. Built as **Laravel 11 (PHP 8.4) + PostgreSQL backend** exposing
`/api/v1` (Sanctum cookie sessions) + **React 19/Vite SPA frontend**. There is
no patient-facing surface: `patient` accounts exist in the users table but
expose no route, and no portal page is served (see `docs/saas/QUEUE_TV.md`).

## HARD ENFORCED RULE — Compute Placement (do not violate)

- **ALL heavy compute runs in GitHub Actions, never on the production server
  (ris.polytronx.com on the production VPS) and never on local dev machines**
  unless a human explicitly approves a strict technical requirement.
  - PHP feature tests, SPA typecheck/build, E2E (Playwright) → `.github/workflows/ci.yml`.
  - `composer install`, `npm ci`, `npm run build`, data processing → CI only.
- The production host **only serves the live app**. Deploy-time commands are
  limited to: `git pull`, `composer install --no-dev`, `php artisan migrate
  --force`, `php artisan config:cache`.
- Exception requires explicit human approval.

## Architecture (source of truth)

- **Tenancy**: a tenant = one clinic row in `businesses`; every domain table is
  `business_id`-scoped. `getActiveBusiness()` resolves the tenant from the
  authenticated user — never trust client-provided business ids. Multi-tenant
  members hold `tenant_memberships` rows and switch explicitly via
  `POST /api/v1/tenant/switch` (server-verified). Platform staff hold no
  tenant context except inside an audited break-glass support session.
- **Auth**: Laravel session cookies (Sanctum SPA mode, same-origin). Roles
  (admin/radiologist/technician/receptionist/billing + tenant-created custom
  roles) are per-tenant laratrust roles; permission checks are TENANT-SCOPED
  via `App\Services\TenantAuthorizer` (effective set = role permissions ∪
  per-user allow overrides − deny overrides; a user's privileges in one
  clinic never apply in another). The taxonomy lives in
  `app/Support/PermissionCatalog.php`; tenant admins manage roles/permissions
  at `Settings → Users & RBAC` (see `docs/saas/RBAC_ADMIN.md`). The React app
  derives ALL navigation/actions from the server-issued `permissions[]`
  (`src/services/permissions.ts`) and must never pick roles client-side;
  `/bootstrap` ships only collections the session is authorized to act on.
- **Control plane**: `/api/v1/platform/*` is guarded by the `platform`
  middleware (`App\Http\Middleware\EnsurePlatformAccess`) with explicit
  capabilities mapped from `users.platform_role` in `config/ris.php`
  (super_admin = all). The SPA renders the separate Platform Console for
  platform identities (`isPlatformAdmin`), never a clinic dashboard. Platform
  bootstrap payloads carry operational metadata only — no patient PHI.
- **Entitlements**: quotas (study volume, user seats) and module features
  (inventory/dicom/dispatch) are enforced SERVER-SIDE by
  `App\Services\EntitlementService` + `App\Services\FeatureResolver`
  (precedence: tenant override → plan features → config default). The SPA only
  mirrors what the server decides.
- **Lifecycle**: `App\Services\TenantLifecycleService` owns provisioning,
  activation, suspension, expiry sweep (`ris:subscription-sweep`, scheduled),
  offboarding (export + retention clock) and termination (typed confirmation).
  Clinical data is never auto-deleted; `ris:tenant-destroy` is the explicit,
  operator-only destruction command after the retention window.
- **API contract**: `app/Http/Resources/ApiShape.php` is the single source of
  truth for the frontend TypeScript contract (`src/types.ts`). Keep them in sync.
- **SPA serving**: the Vite build (`dist/`) is deployed into `public/`;
  `routes/web.php` serves it for `/` and deep links; `/api/v1` + `/sanctum`
  go to Laravel. Root `.htaccess` routes static assets into `public/`.
- **SaaS billing**: plans + subscription status enforced by
  `EnsureTenantActive` middleware. Activation is manual by the platform
  administrator — no payment gateway is wired (do not fake payment success).
- **Docs**: the SaaS architecture set lives in `docs/saas/` (audit, gap
  matrix, tenancy model, authorization, lifecycle, operations, evidence).

## Deployment & Data Safety

- Target: **the production VPS** (`ris.polytronx.com`): the GHCR
  image (`ghcr.io/jerryboganda/adcportal:latest`) runs under Docker Compose at
  `/opt/docker/adc-portal` on the shared Postgres (`platform-postgres`). See
  `DEPLOYMENT_GUIDE.md` for the full runbook.
- Never commit `.env`, secrets, or runtime logs.
- DB: shared PostgreSQL 17 (`platform-postgres`). Releases: CI publishes the
  image, then the deploy job SSHes in and runs `docker compose pull app &&
  docker compose up -d app` (entrypoint applies migrations + caches).
- Do not weaken server-side authorization to make frontend work easier.

## Local development

`npm run dev` (Vite on :3000, proxies /api + /sanctum to 127.0.0.1:8000) with
`php artisan serve` alongside. Nothing heavier locally — tests and builds run
in GitHub Actions.
