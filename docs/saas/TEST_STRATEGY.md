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
| (pre-existing) `TenantIsolationTest`, `RbacTest`, `StudyWorkflowTest`, `BillingTest`, `InventoryAndAuthTest` | original RIS guarantees — all kept green (regression proof that SaaS work broke no clinical workflow) |

## Test context-reuse coverage

The suite repeatedly authenticates as different tenants within one process (`actingAs` A → B → A) and asserts both the identity resolution and payload isolation survive — the regression net for stale-context leakage through static caches (`getActiveBusiness`), per-request authorizer memoization, and tenant-keyed caches.
