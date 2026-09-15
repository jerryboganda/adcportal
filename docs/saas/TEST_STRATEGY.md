# TEST_STRATEGY

## Where tests run

GitHub Actions only (`.github/workflows/ci.yml` — AGENTS.md compute rule): `php artisan test --testsuite=Feature` on SQLite in-memory, SPA `tsc --noEmit` + production build, Playwright E2E, gated delivery.

## Suites (tests/Feature)

| Suite | Covers |
|---|---|
| `PlatformAccessTest` | platform guard (guest 401 / tenant staff 403); capability matrix: super_admin all, auditor read-only, billing plans-only, support sessions-only; platform user CRUD restricted to super_admin; last-super-admin protection; password hashing |
| `TenantLifecycleTest` | platform provisioning (usable tenant + one-time password + membership + lifecycle events); suspension → 402 with data intact; reactivation; offboarding (login revocation, retention export, clock); termination (typed confirmCode, login blocked, data retained); provisioning-retry idempotency; `ris:subscription-sweep`; transactional rollback on provisioning failure |
| `EntitlementAndUsageTest` | study quota 403 `quota_exceeded`; seat quota; transactional usage counters; tenant-scoped meters; plan-feature module gating (`dicom` off → 403 `feature_disabled`); platform override precedence; bootstrap entitlements mirror; public plan catalog |
| `SupportSessionTest` | no-session platform user = zero tenant data/permissions; open→enter→operate→leave; refused entry without/outside session; expiry blocks; sweep closes stale rows; audited; reason validation |
| `TenantSwitchTest` | membership required; safe denial; context moves + membership list; post-switch data isolation; cross-tenant ID 404; **privileges do not travel across tenants**; memberships reachable while suspended (escape hatch); platform staff blocked from switching |
| `SaaSAcceptanceScenarioTest` | the charter's acceptance scenario end-to-end: two tenants with distinct data; platform bootstrap metadata-only; cross-tenant attacks (ID manipulation, tenant-id injection, search, export, storage-cache keys); same-worker context reuse A→B→A; suspension cycle; entitlement change enforced by backend; audited break-glass; final leak sweep |
| `PlatformTenantManageTest` | tenant rename + subscription editing; tenant user administration (create/edit/role change/password rotation/login revoke) with last-active-admin protection and session revocation; facility CRUD with the study-reference delete guard; cross-tenant 404s |
| `TenantRateLimitTest` | 429 isolation across tenants; the aggregate budget is shared by all users of one tenant; switched-member keying follows the operated clinic (§52) |
| `PlatformDeploymentTest` | placement catalog comes from config; provisioning stamps the declared default; re-placement is capability-gated (`infrastructure.manage`) and audited; unknown placement values are rejected; placement survives and is visible in the fleet summary (§23/§59/§60) |
| `TenantBrandingTest` | branding is presentation-only; the **host is never an authorization input**; custom-host registration requires DNS TXT ownership proof (`_polytronx-ris.<host>`) before a host may serve a brand; one primary host; entitlement-gated (`branding` / `custom_domains`) (§37/§38) |
| `TenantIntegrationTest` | registry is tenant/facility-scoped; secrets are encrypted at rest and **never returned** (presence + mask only); probes are real (`tcp` → `fsockopen`, `http` → `Http::get`, `config` → completeness, explicitly not a delivery assertion); a failing probe records `error`; rotation is audited (§33/§44) |
| `PlatformOperationsTest` | §80/§81: system facts are real measurements; a failing integration → *critical* **with its reasons**; a quota breach → *critical* naming the breached key; capability gating (ops/support 200; billing/auditor and tenant staff 403); **payload values never leak** (a planted `PATIENT-SECRET-9c1f` must not appear anywhere in the response, and `leakedSecret` must not appear in the reflected property names); a retry **really re-queues** onto the `jobs` table with `attempts = 0` and clears the failed row; unknown/unauthorised job operations are safe denials; reconciliation reports before it prunes, never deletes a plan-contradicting override, and is tenant-scoped |
| `RouteIntegrityTest` | walks `Route::getRoutes()` and fails if any action's class or method does not exist; asserts every `api/v1/platform` route carries the `platform` middleware. This is the regression net for the run-#114 defect class — a controller wired without a `use` import resolved to the global namespace and 500'd an entire surface, invisible to both `php -l` and `tsc` |
| `UnauthenticatedResponseTest` | pins the *unauthorised* failure mode: an unauthenticated request to a protected API route is `401` **whether or not** the client sends `Accept: application/json`, is never a redirect, and never renders a routing exception. Covers control plane, identity/context and tenant-plane routes, plus a positive control that public routes (`/plans`, `/health`) stay reachable. Exists because every other guest test used `getJson()`, which sets the Accept header and so never reached the broken branch — see `COMPLETION_EVIDENCE.md` |
| (pre-existing) `TenantIsolationTest`, `RbacTest`, `StudyWorkflowTest`, `BillingTest`, `InventoryAndAuthTest` | original RIS guarantees — all kept green (regression proof that SaaS work broke no clinical workflow) |

## Test context-reuse coverage

The suite repeatedly authenticates as different tenants within one process (`actingAs` A → B → A) and asserts both the identity resolution and payload isolation survive — the regression net for stale-context leakage through static caches (`getActiveBusiness`), per-request authorizer memoization, and tenant-keyed caches.

## CI diagnostics (why failures are readable)

GitHub publishes **at most ~10 `::error::` annotations per step**. A single fat diagnostic step therefore silently swallows its own output — which is exactly how CI run #113 reported ten identical `Expected response status code [200] but received 500.` lines with no cause. `ci.yml` now runs **one step per diagnostic** (application log, failing tests, assertion lines, PHPUnit warnings, and five tail windows), so each gets a fresh annotation budget. That change is what turned run #113's opaque 500 into run #114's actual cause (`Target class [PlatformBrandingController] does not exist.`).

`TenantBrandingTest` additionally calls `withoutExceptionHandling([HttpException::class, ValidationException::class])`: deliberate 403/404/422 aborts still render as responses, while genuinely unexpected exceptions surface with a stack trace instead of being masked as a 500.
