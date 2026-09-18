# RBAC_ADMIN — Tenant Roles & Permissions control center

Status: implemented (this release). Backend authority + SPA mirror.

## Permission taxonomy (single source of truth)

`app/Support/PermissionCatalog.php` is the canonical catalog. Permission
**names are a stable security contract** — enforcement strings are checked
verbatim by controllers; never rename an entry, only add.

Every catalog entry carries:

- `name` — stable key (space-delimited, e.g. `report sign`);
- `label` — human-readable text for the admin UI (decoupled from the key);
- `group` — display category (`Modules`, `Scheduling`, `Patients`,
  `Study Workflow`, `Reporting`, `Billing`, `Catalog & Forms`,
  `Administration`, `Access Control`);
- `dangerous` — sensitive actions (delete/void/manage-access) are visually
  flagged in the editor and matrix;
- `implies` — dependency closure (granting `report sign` auto-grants
  `report edit` + `report manage`; revoking a dependency cascades to
  dependents).

The catalog also owns the **default role bundles** (`defaultBundles()`) used
by `TenantBootstrap` (new tenants) and the RBAC migration backfill (existing
tenants) so both paths are byte-identical. Day-one behavior for the five
system roles is exactly the pre-RBAC behavior, with module views added to
mirror the previous SPA role tabs.

### Module views

Navigation modules are first-class permissions so the tenant admin gets true
module-level toggles (`*_view` group `Modules`): `reception view`,
`technologist view`, `reports view`, `billing view`, `queue view`,
`inventory view`, `catalog view`, `doctors view`. Feature gates
(`inventory`, `dispatch`) remain server-enforced **on top of** the view
permission. Default mapping (identical to the legacy `ROLE_TABS`):

| Module permission | admin | radiologist | technician | receptionist | billing |
|---|---|---|---|---|---|
| reception view | ✓ | — | — | ✓ | — |
| technologist view | ✓ | — | ✓ | — | — |
| reports view | ✓ | ✓ | — | — | — |
| billing view | ✓ | — | — | ✓ | ✓ |
| queue view | ✓ | ✓ | ✓ | ✓ | ✓ |
| inventory view | ✓ | — | — | — | — |
| catalog view | ✓ | — | — | — | — |
| doctors view | ✓ | — | — | — | — |

The Settings module has no view permission: its tab is visible when the
session holds **any** settings-section permission (`setting manage`,
`user manage`, `user logs history`, `role view`). Roles holding none —
radiologist, technician, billing by default — no longer see a Settings tab
they could never use.

## Effective permissions

```
effective = (role permissions of the ACTIVE tenant
             ∪ user_permission_overrides[mode=allow])
            − user_permission_overrides[mode=deny]
```

- Tenant scope is structural: roles belong to the tenant via
  `roles.created_by → users.business_id`; overrides carry a `business_id`
  column (`user_permission_overrides`, unique per business+user+permission).
  An override granted in tenant A can never surface in tenant B.
- Break-glass support sessions keep their special status: the full
  operational permission set of the tenant, no denial subtraction.
- Resolution lives only in `TenantAuthorizer` (per-request memoized; the
  laratrust checker is bypassed by design).

## Cache invalidation / session refresh

- **Server:** permission resolution is re-derived from the database on every
  request (per-request memoization only) — a change is effective on the
  next request with no cache to purge. Laratrust's own per-user cache is
  flushed after every role/permission mutation.
- **Client:** every RBAC mutation increments `businesses.permissions_version`.
  `/me` and `/bootstrap` carry `user.permissionsVersion`; the SPA polls `/me`
  (60 s while visible + on window focus) and re-runs the full bootstrap when
  the counter or permission set moves. A structured 403
  (`error: permission.denied`) also triggers a refresh. No reload, no
  developer intervention.

## API surface (`/api/v1/access/*`, tenant plane)

| Endpoint | Permission | Purpose |
|---|---|---|
| `GET /access/catalog` | `role view` | grouped catalog (labels, danger, dependencies) |
| `GET /access/roles` | `role view` | tenant roles + user counts + permissions |
| `POST /access/roles` | `role manage` | create custom role (name unique per tenant) |
| `PATCH /access/roles/{role}` | `role manage` | rename / describe |
| `PUT /access/roles/{role}/permissions` | `role manage` | replace permission set (dependency-expanded) |
| `POST /access/roles/{role}/duplicate` | `role manage` | duplicate role + permissions |
| `DELETE /access/roles/{role}` | `role manage` | delete (admin: 403; in use: 422) |
| `GET /access/users/{user}/effective` | `role view` | effective set + provenance (role/allow/deny) |
| `PUT /access/users/{user}/overrides` | `role manage` | replace allow/deny overrides |

Cross-tenant role/user ids resolve to **404** (no existence leak). Permission
names are validated against the catalog — unknown names are 422, and since
platform capabilities live only in config (never in the `permissions` table),
no tenant endpoint can grant platform power. **Lockout guard:** the `admin`
role can never lose `role view`, `role manage`, `user manage`,
`setting manage`, `user logs history`, and can never be deleted.

## Audit

Every mutation writes to `audit_logs` (tenant-attributed): `role_created`,
`role_updated`, `role_duplicated`, `role_deleted`,
`role_permissions_updated` (old[] → new[]), `user_role_assigned`,
`user_permissions_overridden` (old/new/denied). These render in the existing
Settings → Audit view via `ApiShape::AUDIT_MODULES`.

## SPA mirror

- `src/services/permissions.ts` — the frontend gating vocabulary: `can`,
  `canAny`, `canAll`, `NAV_ITEMS` (module keys + feature gates),
  `SETTINGS_SECTIONS`, `DASHBOARD_GATES`, `ACTION_GATES`. The permission
  strings mirror the catalog; the server remains the only enforcement point.
- `src/components/access/AccessManager.tsx` — Roles & Permissions control
  center inside Settings → Users & RBAC: role list (system/custom badges,
  user counts), grouped permission editor (search, all/clear, tri-state
  group headers, dependency cascade, danger flags, unsaved-changes guard),
  cross-role matrix, per-user effective-access + allow/deny overrides, and a
  safe **Preview as role** simulation (re-runs the real visibility maps
  client-side — no impersonation).
- Every view, dashboard widget, settings section and in-page action renders
  from the same permission set the API enforces.

## Tests

`AccessControlApiTest`, `UserPermissionOverridesTest`,
`BootstrapPermissionFilteringTest`, `RbacDynamicRevocationTest` (plus the
pre-existing `RbacTest`, `TenantIsolationTest`, `SupportSessionTest`) cover:
catalog/role CRUD guards, dependency expansion, lockout floor, in-use and
protected deletion, cross-tenant 404s, escalation attempts, tenant-scoped
overrides, bootstrap data-plane filtering, dynamic revoke/grant latency, and
the version counter's tenant isolation.
