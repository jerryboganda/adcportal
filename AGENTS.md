# AGENTS.md — PolytronX - RIS (Radiology Clinic Management SaaS)

Multi-tenant Radiology Information System (RIS) for diagnostic clinics:
study workflow, safety screening, reporting, invoicing, inventory, doctor
dispatch, patient self-service. Built as **Laravel 11 (PHP) + MySQL backend**
exposing `/api/v1` (Sanctum cookie sessions) + **React/Vite SPA frontend**.

## HARD ENFORCED RULE — Compute Placement (do not violate)

- **ALL heavy compute runs in GitHub Actions, never on the production host
  (Hostinger Business plan) and never on local dev machines** unless a human
  explicitly approves a strict technical requirement.
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
  (admin/radiologist/technician/receptionist/billing) are per-tenant laratrust
  roles; permission checks are TENANT-SCOPED via `App\Services\TenantAuthorizer`
  (a user's privileges in one clinic never apply in another). The React app
  receives its role + capability flags from the server and must never pick
  roles client-side.
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

- Target: **Hostinger Business plan** (Apache, PHP 8.4, MySQL). See
  `DEPLOYMENT_GUIDE.md` for the full runbook.
- Never commit `.env`, secrets, or runtime logs.
- DB: MySQL 8 via hPanel. The old VPS/docker flow (185.252.233.186, ghcr image)
  is superseded; keep as cold-standby only.
- Do not weaken server-side authorization to make frontend work easier.

## Local development

`npm run dev` (Vite on :3000, proxies /api + /sanctum to 127.0.0.1:8000) with
`php artisan serve` alongside. Nothing heavier locally — tests and builds run
in GitHub Actions.
