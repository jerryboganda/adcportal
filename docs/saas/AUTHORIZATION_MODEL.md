# AUTHORIZATION_MODEL

## Two disjoint authorization domains

1. **Platform capabilities** (`PlatformAuthorizer`) — control-plane powers. Held by `users.type ∈ {super_admin, platform_admin}`. `super_admin` ⇒ `['*']`; `platform_admin` ⇒ `config('ris.platform_roles')[platform_role]`. Enforced by `middleware('platform:capability')` on every `/api/v1/platform/*` route. Platform capabilities **never** grant clinical data access.
2. **Tenant permissions** (`TenantAuthorizer`) — clinical/operational powers. **Tenant-scoped**: the effective permission set for a request is the union of permissions carried by the laratrust roles that belong to the **active tenant only** (a role belongs to the tenant of the admin who created it — `roles.created_by → users.business_id`). Implemented with direct role/pivot queries (deterministic, no stale laratrust cache), per-request memoized, flushed on tenant switch.

Consequences:

- A user with roles in two tenants can never spend tenant A's privileges inside tenant B (tested: `TenantSwitchTest::test_privileges_do_not_travel_across_tenants`).
- A platform user without an active support session has an **empty** tenant permission set → 403 on every tenant mutation and an empty data plane (tested).
- Break-glass: inside an active, unexpired `support_sessions` row for tenant T, the platform user is granted T's **full operational permission set** — the session is reason-mandated, time-boxed, audited at open/close, and revoked on expiry/offboarding/termination.
- **Effective set** (since the RBAC access-control release): role permissions ∪ tenant-scoped per-user allow overrides − tenant-scoped per-user deny overrides (`user_permission_overrides`, `business_id`-scoped). Full taxonomy, module views, defaults, invalidation (`businesses.permissions_version` + SPA auto-refresh) and the tenant admin API: see `RBAC_ADMIN.md`.
- **Data plane follows the permission plane**: `/bootstrap` ships only the collections the session's effective set authorizes (invoices require billing permissions, the staff directory requires `user manage`, audit logs require `user logs history`, …), so an over-exposed role no longer downloads data it cannot act on.

## Request authorization flow (tenant route)

```text
authenticated (Sanctum cookie session)
→ tenant.active (availability gate, 402)
→ controller: tenantId() = getActiveBusiness()   [server-side; client ids never trusted]
→ controller: denyUnless('permission')           [TenantAuthorizer, tenant-scoped]
→ controller: EntitlementService/FeatureResolver [quota + module gates]
→ ownership checks on route-bound models (business_id equality → 404, no existence leak)
→ business logic (transactional, audited with business_id)
```

## Safe-denial policy

Cross-tenant ID references resolve to **404** (not 403) so resource existence is never revealed across tenants. Membership/switch denials use 403 with a non-revealing message.
