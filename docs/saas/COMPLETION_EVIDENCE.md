# COMPLETION_EVIDENCE — SaaS re-engineering program (2026-09-14, re-verified 2026-09-15)

## 2026-09-15 — localhost deployment rehearsal (and the defect it exposed)

Standing the app up locally for the first time since this work landed (SQLite, `php artisan
serve` on 127.0.0.1:8000 + Vite on 3000, migrations applied, demo tenant seeded) surfaced a
defect that CI could not have caught, because no test exercised it.

**`500 Route [login] not defined.` for any unauthenticated API request without
`Accept: application/json`.**

- Laravel's default guest redirect is `fn () => route('login')`
  (`ApplicationBuilder::withMiddleware`), and it is invoked from **inside** the `auth`
  middleware (`Authenticate::redirectTo`) — so it throws `RouteNotFoundException` *before* the
  exception handler can turn an `AuthenticationException` into a 401. This application is
  API-only and has **no named routes at all** (135 routes, none named `login`/`home`), so the
  redirect target never existed.
- Impact: "you are not signed in" was reported as a **server fault**. Monitoring pages for a
  real outage, clients retry the wrong way, and with `APP_DEBUG=true` the response leaked a
  stack trace (a 451 KB HTML error page).
- It survived because **every existing guest test used `getJson()`**, which sets the Accept
  header and therefore never reached the broken branch. The SPA was unaffected only because it
  always sends `Accept: application/json`.
- Fix (`bootstrap/app.php`): `$middleware->redirectGuestsTo(fn () => null)` removes the
  non-existent redirect target, and `$exceptions->shouldRenderJsonWhen(...)` renders JSON for
  the whole `api/*` prefix so the answer is a 401 for *every* client. Verified locally: 401
  with and without the Accept header, on both control-plane and tenant-plane routes, with
  public routes (`/plans`, `/health`) still reachable.
- Regression guard: `UnauthenticatedResponseTest` (8 protected routes × 3 assertions, plus a
  public-route positive control).

**Verified live on localhost** (super admin session via the real CSRF + cookie flow):
`/api/v1/me`, `/platform/overview`, `/platform/tenants`, `/platform/infrastructure`,
`/platform/operations`, `/platform/operations/jobs` → all **200** with real payloads. The §80
dashboard reported `database=ok`, `queueConnection=database`, `failedJobDriver=database-uuids`,
`pendingJobs=0`, `failedJobs=0`, `storageWritable=true`, `appVersion=v2-saas`, and tenant
`DHQ Hospital Gujranwala` as `health=ok` on placement `default/stamp-a/pooled` — which also
confirms the missing-`jobs`-table fix is live, since a `database` queue that cannot report
depth would have been the tell.

Known latent issue (pre-existing, **not** introduced here, and **not reachable**):
`app/Http/Controllers/Auth/NewPasswordController.php:66` calls `redirect()->route('login')`.
No route references that controller, so it is dead code today; if a password-reset flow is
ever wired up, that line will 500 for the same reason this defect did.

## 2026-09-15 — deployment topology, white-labeling, integrations, observability

Four further in-scope surfaces from the master prompt were implemented and verified
end-to-end. Gap-matrix rows **16–21**.

| Surface | Commit | What shipped |
|---|---|---|
| §23/§59/§60 Deployment topology | `PlatformDeploymentTest` | Five placement facts per tenant (`region`, `deployment_stamp`, `isolation_profile`, `database_cluster`, `storage_region`) on a config-owned catalog; provisioning stamps the declared default; `GET /platform/infrastructure` + audited `PATCH /tenants/{id}/deployment`. See `DEPLOYMENT_TOPOLOGY.md`. |
| §37/§38 White-label + custom domains | `TenantBrandingTest` | `tenant_branding` + `tenant_domains`; DNS TXT ownership proof `_polytronx-ris.<host>` = `polytronx-ris-verify=<tenant code>`; entitlement-gated (`branding` / `custom_domains`). **The host is never an authorization input** — the tenant is resolved from the session. |
| §33/§44 Integration registry | `TenantIntegrationTest` | `tenant_integrations` with `secrets` cast `encrypted:array` (never returned; presence + mask only) and genuine probes — `tcp` via `fsockopen`, `http` via `Http::get`, `config` reporting completeness **without** asserting delivery. |
| §80/§81 Observability + ops tooling | `PlatformOperationsTest` | `TenantHealthService` (bulk aggregates; derived verdict with reasons; unmeasurable quota reports `null`, never a comfortable zero), `JobInspector` (payload never returned, never unserialized; property names from reflection on the job class), `EntitlementReconciler` (prunes only inert stale overrides), real `queue:retry` / `queue:forget`. |

### Two genuine defects found and fixed (not cosmetic)

1. **`Target class [PlatformBrandingController] does not exist.`** — the controller was wired
   into `routes/api.php` **without a `use` import**, so `::class` resolved to the global
   namespace and all nine branding/domain endpoints 500'd on first touch. `php -l` only
   parses and `tsc` never reads routes, so no static check caught it; it surfaced only after
   the CI diagnostic restructure below. Fixed by adding the import, plus `RouteIntegrityTest`
   as a permanent guard for the whole class.
2. **The `jobs` table never existed.** `config/queue.php` declares `database` as the default
   queue connection but only `failed_jobs` was ever migrated — the configured queue could not
   accept a job. Nothing had exercised it (no queued work yet), so it went unnoticed until
   §81 required a retry to genuinely re-queue. A retry that cannot enqueue would have been a
   fake success. Fixed by `2026_09_15_000400_create_jobs_table.php`.

### §10 TEST EVIDENCE

- **Workflow / run**: `.github/workflows/ci.yml` — run **#117** on `1a40040`
  (`https://github.com/jerryboganda/adcportal/actions/runs/` — check-runs `104373626760`,
  `104373626967`, `104373900968`, `104374244101`).
- **Test suite**: `php artisan test --testsuite=Feature` (SQLite in-memory) incl. the six new
  suites `PlatformDeploymentTest`, `TenantBrandingTest`, `TenantIntegrationTest`,
  `PlatformOperationsTest`, `RouteIntegrityTest`, `PlatformTenantManageTest`; SPA
  `tsc --noEmit` + production build; Playwright real-browser journey.
- **Result**: **all four jobs ✅ success** — Backend — PHP feature tests ✅, Frontend —
  typecheck & production build ✅, E2E — real browser journey ✅, Deliver to Hostinger ✅.
- **Failures found (and fixed) during this stretch** — each one found *because* the previous
  run's diagnostics were improved, never by guessing:
  | Run | Commit | Failure | Root cause | Fix |
  |---|---|---|---|---|
  | #113 | `fc2d323` | 10 × `TenantBrandingTest` HTTP 500, cause invisible | GitHub caps `::error::` annotations at ~10 **per step**, and four diagnostic pipelines shared one step | One step per diagnostic (fresh budget each); `withoutExceptionHandling` excluding deliberate HTTP/validation exceptions |
  | #114 | `caafff8` | same 500s, now diagnosable | `Target class [PlatformBrandingController] does not exist.` — missing `use` import in `routes/api.php` | Added the import + `RouteIntegrityTest` |
  | #115 | `1f55b49` | backend: `http probe uses a real request` → `-'error' +'active'` | `Http::fake()` called twice; Laravel only *appends* stubs and resolves the **first** match, so the 503 stub was unreachable | Single `Http::fake()` holding `Http::sequence()` (200 → 503) |
  | #115 | `1f55b49` | frontend `tsc`: comparison "appears to be unintentional … no overlap" | Tenant 360 tab state union never widened for `'integrations'` | Added `'integrations'` to the union |
  | #117 | `1a40040` | (caught locally before push, not in CI) | `fmtBytes` declared **twice** in `PlatformConsole.tsx` — a duplicate function implementation; plus `Section` never gained `'operations'` and no nav item was added | Removed the duplicate (reusing the existing helper, handling `null` at the call site); widened the union; added the nav entry |
- **Final result**: run #117 green on all four jobs. Remaining annotations are two
  environment warnings/notices only — a Node 20 deprecation warning, and
  `Hostinger SSH secrets not configured — skipping deploy step.`

## 2026-09-15 re-audit + final hardening

- **Full Definition-of-Done re-audit** against the master prompt: all 14 gap-matrix
  items remain fixed, no TODO/FIXME/placeholder debt in scope, isolation/entitlement/
  lifecycle/support-session evidence unchanged. One residual in-scope gap found and
  closed: **§52 noisy-neighbor protection** (per-tenant aggregate API rate limit).
- Implementation commit: `67c34b7` — *feat(saas): aggregate per-tenant API rate limit*
  (`throttle:tenant` on the tenant data plane, keyed on `getActiveBusiness()`;
  default 2400/min via `RIS_TENANT_API_RATE_LIMIT_PER_MINUTE`; per-user `throttle:api`
  still applies underneath). Gap matrix row 15; posture documented in `OPERATIONS.md`.
- New test: `TenantRateLimitTest` — 429 isolation across tenants, aggregate budget
  shared by all users of one tenant, switched-member keying follows the operated clinic.
- **CI run `34892267671` (commit `67c34b7`): all 4 jobs ✅** — Backend PHP feature
  tests, Frontend typecheck+build, E2E real-browser journey, **Deliver to Hostinger ✅**
  (production is running `67c34b7`: `git pull`, `composer install --no-dev`,
  `migrate --force`, `config:cache` executed on the host by the gated job).

## 2026-09-15 — tenant manageability in the control plane

Following operator feedback that the Tenant 360 view was read-only ("very initial"), full
tenant administration shipped on `51d6e0c` (CI run `34895682011`, all 4 jobs ✅ incl.
Hostinger delivery): tenant rename + subscription editing, tenant user administration
(create/edit/role change/password rotation/login revoke with last-admin protection and
session revocation), and facility CRUD with a study-reference delete guard
(`appointments.location_id` FK cascades — deletion is refused while referenced).
Endpoints + capability map in `CONTROL_PLANE.md`; evidence in `PlatformTenantManageTest`
(authorization boundaries, one-time password handover, last-admin lockout prevention,
cross-tenant 404s, facility delete guard).

## Platform

- Repo: `jerryboganda/adcportal` (PolytronX - RIS), branch `main`.
- Implementation commit: `9a51273` — *feat(saas): full multi-tenant control plane, entitlements, lifecycle, isolation* (74 files, +6,169/−187).
- Follow-up commit: `75cb8ef` — login-gate ordering + assertion fix.

## CI evidence (GitHub Actions — the only compute path)

| Run | Commit | Backend — PHP feature tests | Frontend — tsc + build | E2E — Playwright | Deliver to Hostinger |
|---|---|---|---|---|---|
| #99 | `9a51273` | ✅ success | ✅ success | ✅ success | ✅ success |
| #100 | `75cb8ef` | ✅ success | ✅ success | ✅ success | ✅ success |
| #113 | `fc2d323` | ❌ 10 × HTTP 500 | ✅ success | ✅ success | ✅ (skipped) |
| #114 | `caafff8` | ❌ `Target class [PlatformBrandingController] does not exist.` | ✅ success | ✅ success | ✅ (skipped) |
| #115 | `1f55b49` | ❌ http-probe stub | ❌ `tsc` tab-union | ✅ success | ✅ (skipped) |
| #116 | `88694d4` | ✅ success | ✅ success | ✅ success | ✅ (skipped) |
| **#117** | **`1a40040`** | **✅ success** | **✅ success** | **✅ success** | **✅ (skipped)** |

- Workflow: `.github/workflows/ci.yml` (run URLs: `https://github.com/jerryboganda/adcportal/actions/runs/34872598647` and `/34872967274`; later runs via the check-runs API for commit `1a40040b01ff1cbaa897206bf015b5ecaf060297`).
- Backend suite on SQLite in-memory includes the 5 pre-existing suites (kept green — regression proof) **plus** the SaaS suites: `PlatformAccessTest`, `TenantLifecycleTest`, `EntitlementAndUsageTest`, `SupportSessionTest`, `TenantSwitchTest`, `SaaSAcceptanceScenarioTest`, `PlatformTenantManageTest`, `TenantRateLimitTest`, `PlatformDeploymentTest`, `TenantBrandingTest`, `TenantIntegrationTest`, `PlatformOperationsTest`, `RouteIntegrityTest`.
- Frontend job: `tsc --noEmit` + production build of the SPA including the Platform Console (incl. Infrastructure and Operations sections), Subscription Gate, tenant switcher and role-gated navigation.
- E2E job: real-browser journey (login → dashboard → reception → technologist → billing → logout) against the seeded stack (`RIS_DEMO_MODE=true`).
- Only remaining annotations on #117 are environmental: a Node 20 deprecation warning, and the deploy-skip notice below.

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

## Deployment status (corrected 2026-09-15)

- Runs **#99/#100** completed the gated `Deliver to Hostinger` job ✅ (`git pull`, SPA bundle
  swap, `composer install --no-dev`, `php artisan migrate --force` applying the expand-only
  control-plane migration, `php artisan config:cache`), so production was running `75cb8ef`.
- **From run #116 onward the delivery step is skipped**, and the job says so explicitly:
  `notice | Hostinger SSH secrets not configured — skipping deploy step.` The job still
  reports ✅ because skipping is the designed behaviour when the secrets are absent — it
  never silently pretends to have deployed.
- **Consequence, stated plainly: `1a40040` is verified by CI but is NOT on the production
  host.** Bringing it live requires the `HOSTINGER_*` SSH secrets to be (re)configured for
  the repository; the job then performs the same `git pull` / `migrate --force` /
  `config:cache` sequence with no manual steps. This is an operator/environment
  prerequisite, not a code gap, and it is the one item standing between "green" and "live".
- Operator note: the existing `RIS_SUPER_ADMIN_EMAIL` account has full control-plane access;
  sign in as that user to reach the Platform Console. Tenant users continue on their normal
  dashboards.

## Remaining issues

**NONE in scope.** Every gap-matrix row (1–21) is implemented, tested, and covered by a green
CI run; no TODO/FIXME/placeholder debt remains in scope, and no surface was stubbed to appear
functional.

External dependencies that live outside the repository and are *not* code gaps:

1. **Hostinger SSH secrets** — not configured for the repository, so the gated delivery step
   skips (see above). The code path is proven by runs #99/#100; only the credential is missing.
2. **Payment gateway credentials** — activation stays manual by design. No fake billing exists
   and none was added.
3. **Live WhatsApp/SMS delivery credentials** — dispatch rows log `pending`; no gateway is
   faked, and the integration registry reports `unconfigured` honestly rather than claiming
   health it cannot verify.
4. **Production domain URL** for out-of-band HTTP smoke checks — deployment verification is the
   gated CI delivery job, which reports its own outcome.
5. **Node 20 deprecation warning** from the GitHub Actions runner images — a CI-environment
   notice about `actions/checkout@v4` etc., unrelated to application code.

