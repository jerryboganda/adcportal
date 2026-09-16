# Architecture Audit — Layering & Bounded Contexts

Audit performed against the `architecture-patterns` skill (Clean Architecture /
Hexagonal / DDD lens) on 2026-09-16. Every finding below was verified against
the code and re-verified after the fixes with the full test suite
(177 tests / 814 assertions, green) and a live strict-mode MySQL booking E2E.

## 1. Bounded contexts (as-built)

| Context | Owner code | Notes |
|---|---|---|
| Identity & Access | `AuthController`, `TwoFactorController`, `Totp`, `TwoFactorService`, `PasswordPolicy` | Session (Sanctum SPA) authN; TOTP 2FA for platform identities; password policy shared by all credential-minting endpoints |
| Authorization | `TenantAuthorizer`, `PlatformAuthorizer`, `EnsurePlatformAccess`, `EnsureTenantActive` | All checks tenant-scoped server-side; SPA mirrors decisions only |
| Tenancy & Lifecycle | `TenantLifecycleService`, `EntitlementService`, `FeatureResolver`, `TenantContextController` | Provision → activate → suspend → offboard; quotas enforced server-side |
| Clinical Workflow | `StudyController`, `StudyWorkflowService`, `StudyTokenAllocator`, `StudyState` (enum) | Booking, tokens, state machine, check-in → delivery |
| Billing | Billing controllers + invoice pipeline | Auto-invoice at booking (see `StudyWorkflowTest`) |
| Reporting / Inventory / Dispatch | respective controllers & services | `business_id`-scoped like everything else |
| Patient Portal | portal controllers, OTP service | Login disabled by default per tenant |
| Platform control plane | `Platform/*` controllers, `EnsurePlatformAccess` | No tenant context; 2FA-enforced; break-glass support session via `tenant/enter` |

**Contract boundary**: `app/Http/Resources/ApiShape.php` ↔ `src/types.ts`
(AGENTS.md designates ApiShape as the single source of truth; keep in sync).

## 2. Dependency-rule findings (verified) & disposition

### F1 — Domain rule duplicated in two adapters (fixed)
Token allocation (the "next token per modality per day" domain rule) existed
twice, and the two adapters disagreed:
- `StudyController::nextToken()` (API booking path) generated `'DX-01'`-style
  strings and `LIKE`-matched them against `appointments.token_number`.
- Legacy `AppointmentController` allocated plain integers in its own
  transaction.

The API variant wrote strings into the **integer** column — SQLite (CI)
silently coerced; strict-mode MySQL (production) rejects with error 1366, so
**production booking would have 500'd**. The same latent mismatch had already
broken the demo seeder earlier in this workspace.

**Fix (hexagonal)**: single domain service `app/Services/StudyTokenAllocator.php`
owns the rule (per-tenant, per-modality, per-day integer sequence, allocated
inside the booking transaction under lock). Both adapters now delegate:
`StudyController` and `AppointmentController` hold no allocation logic.

**Evidence**: new `tests/Feature/StudyTokenAllocatorTest.php` (4 tests) and
`tests/Feature/StudyBookingTokenTest.php` (API-level contract: integer tokens
returned by `POST /api/v1/studies`). `StudyWorkflowTest` assertions updated
from the legacy `'DX-01'` string contract to `'1'`/`'2'` — those tests had been
codifying the bug. Live strict-mode MySQL E2E: two bookings → tokens `9`,`10`,
stored as integers.

### F2 — Session fixation on API login (fixed, earlier in session)
`AuthController::login/register` performed `Auth::attempt()` without
`$request->session()->regenerate()` (Laravel's own Breeze web controllers do
regenerate). Fixed on both endpoints, guarded with `hasSession()` for
stateless clients; verified live (session id rotates on login).

### F3 — Password policy scattered across four controllers (fixed)
Four credential-minting sites each carried inline rules. Extracted to
`app/Services/PasswordPolicy::rules()` (12+ chars, mixed case, digit, symbol)
and applied at: clinic registration, staff creation, platform-user creation,
platform tenant-user creation. Only *new* passwords affected.

### F4 — 2FA state machine now a first-class service (fixed)
Enrollment/challenge state lived nowhere; implemented as `TwoFactorService`
(session-scoped challenge state, stateless-client safe) + `Totp` (RFC 6238,
validated against all RFC 4226 vectors). Enforcement at the two sensitive
doors: `EnsurePlatformAccess` and break-glass `tenant/enter`.

## 3. Remaining roadmap (prioritized, not yet done)

1. **Retire the legacy `AppointmentController`** — it is now a thin delegating
   adapter, but its route surface overlaps `StudyController`. Merge routes and
   delete the legacy controller to remove the last duplicated adapter.
2. ~~Extract notification fan-out~~ **DONE (backend-engineering pass)**: workflow
   facts (booked / checked-in / acquired / sent-to-reading / rejected) are
   dispatched INSIDE the unit of work (`StudyWorkflowService::transition` and the
   booking transaction) and projected to the notification center by
   `App\Listeners\ProjectStudyNotification`. State + audit row + fact now commit
   or roll back atomically (crash between commit and notify previously lost the
   fact); the legacy web controller inherits the behavior automatically.
   Verified by `StudyFactAtomicityTest` (rollback proofs, projection counts,
   screening N+1 removal, 403/404 JSON error-shape locks).
3. **Contract sync test**: a CI test that diffs `ApiShape` serialization
   against `src/types.ts` to fail the build when they drift.
4. **`StudyWorkflowService` state machine ownership** — transitions are
   validated in-controller + enum; move the full transition table into the
   service so the enum is the only state vocabulary.

## 4. Verification record

- Full suite: `177 tests, 814 assertions, OK` (1 pre-existing env skip).
- SPA: `tsc --noEmit` clean, `vite build` clean (auth hardening turn).
- Live strict-mode MySQL: booking via `POST /api/v1/studies` returns integer
  tokens (`9`, `10`); rows verified in `appointments` (no 1366, no coercion).
