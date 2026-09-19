# OPERATIONS — running the platform (implemented tooling)

## Control plane (no DB access needed)

- **Overview**: tenant health counters, needs-attention list, trials expiring ≤7d, failed jobs, active break-glass sessions, recent lifecycle events.
- **Operations** (§80/§81 — the surface an operator opens when something is wrong):
  - **System facts**, measured live: database reachability, cache store, queue connection, pending job depth and the age of the oldest pending job, failed-job driver and count, storage writability, app env + version, last lifecycle event.
  - **Tenant health**, worst first, every verdict carrying its reasons: failing integrations → *critical*; quota exceeded → *critical*; unconfigured integrations, approaching quota, non-active subscription, trial ending ≤3d → *degraded*. A quota that cannot be measured cheaply reports **`null` ("not measured")**, never a comfortable zero.
  - **Placement rollup** answering *"which region or stamp is affected?"*, plus an explicit **provisioning-stuck** list.
  - **Failed jobs**: job class, queue, attempts, exception type + first line, payload **size** and property **names**. The payload is never returned and is never unserialized. **Retry** genuinely re-queues (`queue:retry`) and **Discard** removes it (`queue:forget`) — both audited.
  - **Entitlement reconciliation**: reports drift (`stale` / `redundant` / `overrides-plan` / `override-without-plan`) and, on request, prunes **only** stale overrides — inert today, but they would silently come back to life if a deploy reintroduced the key. Overrides that contradict the plan are reported, never deleted: that is a commercial decision, not a defect.
- **Tenant ops**: provision, retry provisioning, activate/reactivate/suspend/offboard/terminate (typed confirmation), change plan/terms, feature overrides, deployment re-placement, branding + custom domains, integration registry, usage drill-down, audited JSON export.
- **Support**: open break-glass session (reason + duration) → "Enter clinic" (banner visible in the tenant app) → end session. All audited.
- **Platform staff**: create/role/disable platform users (last-super-admin protected).
- **Audit**: platform-wide stream with action/tenant filters.

## CLI operators (`php artisan`)

| Command | Purpose |
|---|---|
| `ris:subscription-sweep` | lapses finished trials/terms → `expired`; closes expired support sessions (scheduled daily 03:10 via `schedule:run`) |
| `ris:tenant-destroy {tenant_code} --confirm={code}` | destroys a **terminated** tenant's retained data after the retention window (`--force` overrides the clock; interactive confirmation required). Not exposed via any API. |
| `ris:purge-demo --force` | removes the demo tenant + its data (pre-existing, demo-mode only) |

## Scheduler (existing cron `schedule:run`)

`ris:subscription-sweep` (daily), `app:appointment-reminder` (5 min), `sanctum:prune-expired` (hourly), `model:prune` (daily, non-sync queues), API-log trim (daily).

## Health

- `GET /api/v1/health` — DB reachability + version (`config('ris.app_version')`; one source of truth, so the health endpoint and the operations dashboard can never disagree).
- `GET /up` — framework health.
- `GET /api/v1/platform/operations` — the full operational picture, including queue posture and failed-job depth.

## Queue posture (corrected 2026-09-15)

`config/queue.php` defaults to the **`database`** driver with the `database-uuids` failer. Until this date only `failed_jobs` was migrated, so the configured queue could not actually accept a job — nothing had exercised it (no queued work yet), so the gap was invisible. Migration `2026_09_15_000400_create_jobs_table.php` adds the missing `jobs` table; a retry that cannot enqueue would otherwise be a fake success. `sync` remains the connection used by the test suite, and job execution inside a web request is deliberately avoided — the retry endpoint re-queues, it does not run.

## Deploy

CI (`GitHub Actions`) is the only compute path: tests → SPA build → gated production delivery (`git pull`, `composer install --no-dev`, `migrate --force`, `config:cache`). See `DEPLOYMENT_GUIDE.md` + AGENTS.md compute rule.

The gated delivery step is **skipped with an explicit notice** when the VPS SSH secrets are not configured for the repository (`notice | VPS SSH secrets not configured — skipping deploy step.`) — it never silently pretends to have deployed.

### Migrations run on boot, so a deploy's schema work is not free

The container entrypoint runs `php artisan migrate --force` under `set -e` *before* it serves, which is what makes a failed migration fatal instead of half-applied: the app will not come up with a schema its code does not match. Two consequences worth knowing before a release:

- **A migration that locks a large table holds up the deploy, not just the query.** Adding indexes to `appointments` (the reporting worklist does this) takes a write lock for the duration of the index build; on a clinic with millions of studies that is noticeable, and the health check will time out if it runs too long. Run releases that touch wide tables outside reporting hours, and prefer additive columns with defaults (metadata-only on PostgreSQL 11+) over rewrites.
- **A migration that fails leaves the previous container running.** The deploy step reports success for the *pull and restart*; if the new container never becomes healthy, check `docker logs adc-portal-app --since 5m` for the migration error rather than assuming the release landed. The post-deploy health probe (`/api/v1/health`) is the authority on whether it did.

Migrations are additive by policy: a clinic's existing clinical records are never rewritten by a release. The reporting and preferences migrations follow that rule (new tables/columns plus a data backfill that only fills NULLs).

## Rate-limit posture (noisy-neighbor guard)

| Limiter | Scope | Budget |
|---|---|---|
| `api` | per authenticated user (IP when anonymous) | 60/min |
| `tenant` | aggregate per **active** tenant (all users of one clinic share it) | 2400/min, `RIS_TENANT_API_RATE_LIMIT_PER_MINUTE` |
| `login` | per email+IP | 5/min, 30/h |
| `register` | per IP | 6/h |
| `booking` (legacy public form) | per IP | 10/min |

The `tenant` limiter keys on the server-resolved active business (`getActiveBusiness()`): switched members and support sessions consume the budget of the clinic they are operating on, never their home one. Exceeding it returns HTTP 429 for that tenant only; neighbours are unaffected. Public routes never consume tenant budget.

## Deploy

CI (`GitHub Actions`) is the only compute path: tests → SPA build → gated production delivery (`git pull`, `composer install --no-dev`, `migrate --force`, `config:cache`). See `DEPLOYMENT_GUIDE.md` + AGENTS.md compute rule.
