# MIGRATION_PLAN — single-tenant era → control plane (executed)

## Approach: expand → backfill → (contract never needed)

Migration `2026_09_14_000100_create_saas_control_plane.php` is **additive only** — no column is dropped, no row is rewritten destructively:

1. **New columns**: `users.platform_role`, `plans.max_locations`/`max_storage_mb`, `businesses.offboarded_at`/`terminated_at`/`data_retention_until`, `audit_logs.business_id` (+index).
2. **New tables**: `tenant_memberships`, `tenant_lifecycle_events`, `tenant_feature_overrides`, `usage_counters`, `support_sessions`.
3. **Backfills (same migration)**:
   - **Memberships**: every staff/admin user (`business_id > 0`, non-customer) receives an active `tenant_memberships` row; role derived from `users.type` for owners and from their dominant tenant laratrust role otherwise (priority admin → radiologist → technician → receptionist → billing).
   - **Lifecycle history**: every existing `businesses` row receives a `tenant_imported` lifecycle event stamped with its current status — the pooled tenants become first-class control-plane records.
   - **Usage baseline**: current-month `studies`/`reports` counters seeded from real persisted rows so quota enforcement never retro-penalizes existing volume.
4. **Rollback**: `down()` drops the new tables/columns; because nothing pre-existing was mutated, rollback is clean.

## Production rollout (Hostinger)

Deploy runs `php artisan migrate --force` (already in the CI deploy job). After migration:

- Existing tenants keep working unchanged (`active`/`trialing` semantics untouched; `EnsureTenantActive` behaves identically for subscribable tenants).
- The existing super admin (`RIS_SUPER_ADMIN_EMAIL`) automatically has full control-plane access (`type = 'super_admin'` ⇒ all capabilities).
- `ris:subscription-sweep` activates on the existing schedule cron (`schedule:run`); first run simply records any genuinely lapsed trials.
- Optional per-tenant role corrections can be made from the platform console (Tenant 360 → Users) — the backfill is deliberately conservative.

## Data safety

- No PHI is transformed; only relationship metadata is inserted.
- The migration is idempotent-safe under `migrate` (single run) and was validated against SQLite in CI (fresh + seeded) before shipping.
