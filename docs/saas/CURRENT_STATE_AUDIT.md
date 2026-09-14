# CURRENT_STATE_AUDIT — PolytronX - RIS (pre/post SaaS re-engineering)

## What the system was (evidence-backed)

- **Stack**: Laravel 11 (PHP 8.4, Sanctum SPA cookie sessions, laratrust) + MySQL prod / SQLite CI; React 19 + Vite SPA served by Laravel from `public/`.
- **Tenancy model found**: **Model B — pooled shared database + `business_id` tenant column.** A tenant = one row in `businesses` (`business_id` scoping on every domain table; `getActiveBusiness()` resolves the tenant from the authenticated user; middleware `EnsureTenantActive` gates non-subscribable tenants with 402).
- **Existing SaaS layer before this program**: `plans` table (with `max_users`/`max_studies_per_month` **displayed but never enforced**), denormalized subscription state on `businesses` (`plan_id`, `subscription_status` ∈ {trialing, active, suspended, expired}, `trial_ends_at`, `subscription_ends_at`, unique `tenant_code`), manual activation by super admin (no payment gateway — deliberately), `AuditLog` hand-rolled audit table, per-tenant laratrust roles seeded by `TenantBootstrap`, public signup (`POST /api/v1/register`) creating a trialing tenant.
- **Identity**: single `users` table; `users.type ∈ {super_admin, admin, staff, customer}`; super admin identified by `users.type === 'super_admin'` with `business_id = 0`; **one user belonged to exactly one tenant** (`users.business_id`, legacy `active_business`).
- **Super admin experience (the core defect)**: the SPA had **no router** — `App.tsx` tab-switcher rendered the same single-clinic dashboard for everyone; a `super_admin` landed inside a clinic's operational dashboard. Backend platform surface was 4 routes (`GET/PUT /platform/tenants`, `GET /platform/plans`, `GET /platform/stats`) with **zero frontend consumers**.
- **Known runtime bug found in audit**: `Business::users()` relation was called by `ApiShape::tenant()` and `PlatformAdminController::tenants()` (`withCount(['users'])`) but never defined → any platform tenant listing would throw `BadMethodCallException` at runtime.

## What changed (summary — details in sibling docs)

A real **control plane** (platform console UI + `/api/v1/platform/*` API with capability guard), a **tenant lifecycle engine** (provisioning → trial/active → suspended → expired → offboarding → terminated, audited), **server-enforced entitlements** (quotas + module feature flags + usage metering), **multi-tenant memberships with explicit switching**, **break-glass support sessions**, **platform roles**, tenant-attributed audit, and the mandated **isolation test suite**. Pooled tenancy (Model B) was retained and hardened — see `TENANCY_MODEL.md`.
