# OPERATIONS — running the platform (implemented tooling)

## Control plane (no DB access needed)

- **Overview**: tenant health counters, needs-attention list, trials expiring ≤7d, failed jobs, active break-glass sessions, recent lifecycle events.
- **Tenant ops**: provision, retry provisioning, activate/reactivate/suspend/offboard/terminate (typed confirmation), change plan/terms, feature overrides, usage drill-down, audited JSON export.
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

- `GET /api/v1/health` — DB reachability + version.
- `GET /up` — framework health.
- Platform overview surfaces failed-job count (`failed_jobs`) and queue posture; queue driver is `sync` by design at this scale.

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

CI (`GitHub Actions`) is the only compute path: tests → SPA build → gated Hostinger delivery (`git pull`, `composer install --no-dev`, `migrate --force`, `config:cache`). See `DEPLOYMENT_GUIDE.md` + AGENTS.md compute rule.
