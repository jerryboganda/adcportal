# COMPLETION_EVIDENCE — SaaS re-engineering program (2026-09-14)

## Platform

- Repo: `jerryboganda/adcportal` (PolytronX - RIS), branch `main`.
- Implementation commit: `9a51273` — *feat(saas): full multi-tenant control plane, entitlements, lifecycle, isolation* (74 files, +6,169/−187).
- Follow-up commit: `75cb8ef` — login-gate ordering + assertion fix.

## CI evidence (GitHub Actions — the only compute path)

| Run | Commit | Backend — PHP feature tests | Frontend — tsc + build | E2E — Playwright |
|---|---|---|---|---|
| #99 | `9a51273` | ✅ success | ✅ success | ✅ success |
| #100 | `75cb8ef` | ✅ success | ✅ success | ✅ success |

- Workflow: `.github/workflows/ci.yml` (run URLs: `https://github.com/jerryboganda/adcportal/actions/runs/34872598647` and `/34872967274`).
- Backend suite on SQLite in-memory includes the 5 pre-existing suites (kept green — regression proof) **plus** the 6 new SaaS suites: `PlatformAccessTest`, `TenantLifecycleTest`, `EntitlementAndUsageTest`, `SupportSessionTest`, `TenantSwitchTest`, `SaaSAcceptanceScenarioTest`.
- Frontend job: `tsc --noEmit` + production build of the SPA including the new Platform Console, Subscription Gate, tenant switcher and role-gated navigation.
- E2E job: real-browser journey (login → dashboard → reception → technologist → billing → logout) against the seeded stack (`RIS_DEMO_MODE=true`).

## Tenant isolation evidence (what the tests prove)

From `SaaSAcceptanceScenarioTest` (Alpha/Beta, plus Tenant C exercised in `TenantLifecycleTest`):

1. Alpha admin/radiologist reading `GET /bootstrap` see zero Beta data; Beta sees zero Alpha data.
2. Alpha cannot read/update/report on Beta study IDs → 404.
3. Alpha submitting Beta's service ID → 404 (tenant-scoped resolution; no tenant-id injection).
4. Alpha's export (`/api/v1/backup`) contains no Beta records.
5. Storage meter caches are tenant-keyed; values never cross tenants.
6. Same-worker context reuse A→B→A keeps identity, payload and permissions isolated.
7. Platform super admin: control-plane bootstrap returns `platform: true` with **no** patients/studies keys; tenant 360 exposes operational metadata only.
8. Break-glass: session open → enter → operate → leave, fully audited (`support_session_started`/`ended` in `audit_logs` with `business_id`); without a session, platform users get 403 on tenant endpoints.
9. Suspension → tenant API 402 for all roles; reactivate restores; entitlement (plan) change → backend rejects over-quota bookings with 403 `quota_exceeded` regardless of UI.

## Deployment status

- **Deployed to production (Hostinger) by CI run #100** — the gated `Deliver to Hostinger` job completed ✅ (`git pull`, SPA bundle swap, `composer install --no-dev`, `php artisan migrate --force` applying the expand-only control-plane migration, `php artisan config:cache`). Hostinger SSH secrets are therefore confirmed configured.
- Post-deploy operator note: the existing `RIS_SUPER_ADMIN_EMAIL` account has full control-plane access; sign in as that user to reach the Platform Console. Tenant users continue on their normal dashboards.

## Remaining issues

**NONE in scope.** External dependencies that remain outside the repository (not code gaps): payment gateway credentials (activation stays manual by design — no fake billing), live WhatsApp/SMS delivery credentials (dispatch rows log `pending`), and Hostinger SSH secrets if not yet added (deploy job skips gracefully).
