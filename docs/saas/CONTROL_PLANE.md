# CONTROL_PLANE — API + UI reference (implemented)

## API — `/api/v1/platform/*` (middleware: `auth` + `platform[:capability]`)

| Route | Capability | Purpose |
|---|---|---|
| `GET /overview` | `tenants.view` | tenant status counts, contracted monthly value (from real plan assignments), monthly usage, storage, trials expiring ≤7d, new tenants 30d, active support sessions, failed jobs, recent lifecycle events |
| `GET /tenants` | `tenants.view` | directory; `q`/`status`/`planId` filters |
| `POST /tenants` | `tenants.manage` | full provisioning (owner admin + tenant + bootstrap), returns one-time `initialAdminPassword` |
| `GET /tenants/{id}` | `tenants.view` | 360°: subscription, entitlements, lifecycle, users (metadata), memberships, facilities, feature overrides, audit tail, support sessions |
| `PATCH /tenants/{id}` | `subscriptions.manage` | tenant rename + plan assignment + trial/term dates (status changes only via lifecycle actions; tenant code is immutable) |
| `POST /tenants/{id}/users` | `tenants.manage` | create a tenant staff user (any tenant role); optional password — generated & returned once when omitted |
| `PATCH /tenants/{id}/users/{userId}` | `tenants.manage` | edit identity (name/email/phone), role (re-attaches the tenant's own laratrust role + membership), active / login flags; session revocation on access loss; last-active-admin protection |
| `POST /tenants/{id}/users/{userId}/reset-password` | `tenants.manage` | rotate password (returned exactly once), revoke live sessions |
| `POST /tenants/{id}/facilities` | `tenants.manage` | add a facility (location) to the tenant |
| `PATCH /tenants/{id}/facilities/{locationId}` | `tenants.manage` | edit facility metadata |
| `DELETE /tenants/{id}/facilities/{locationId}` | `tenants.manage` | delete only when no study references it (FK would cascade-delete studies) |
| `PUT /tenants/{id}/features` | `tenants.manage` | per-tenant feature overrides |
| `GET /tenants/{id}/usage` | `usage.view` | limits + current usage + 6-month meter series |
| `GET /tenants/{id}/audit` | `audit.view` | tenant-attributed audit stream |
| `GET /tenants/{id}/export` | `tenants.manage` | audited tenant data export |
| `POST /tenants/{id}/activate|suspend|reactivate|offboard` | `tenants.lifecycle` | lifecycle transitions (reason recorded) |
| `POST /tenants/{id}/terminate` | `tenants.lifecycle` | requires `confirmCode` = tenant code |
| `POST /tenants/{id}/provision-retry` | `provisioning.manage` | idempotent recovery for `provisioning` tenants |
| `GET/POST/PATCH /plans[...]` | `tenants.view` / `plans.manage` | plan catalog CRUD (limits + features) |
| `GET/POST/PATCH /users[...]` | `platform.users.*` | platform staff management (last-super-admin guarded) |
| `GET/POST /support-sessions`, `POST .../end` | `support.manage` | break-glass session lifecycle |
| `GET /audit` | `audit.view` | platform-wide audit stream (filters: `action`, `tenantId`) |

Tenant-plane context routes: `GET /api/v1/memberships`, `POST /api/v1/tenant/switch` (tenant members), `POST /api/v1/tenant/enter|leave` (platform + active session). `GET /api/v1/plans` is public (signup + gate views).

## UI — Platform Console (`src/components/PlatformConsole.tsx`)

Dark-shelled, deliberately distinct from the clinic app. Sections: Overview, Tenants (+ Provision modal + Tenant 360 with Users/Facilities/Lifecycle/Audit/Features tabs), Plans (CRUD), Usage, Support Sessions (open/enter/end), Platform Users (role matrix editing), Audit. Capability-aware: controls hidden per role, enforced server-side regardless. Enter-clinic renders the tenant app under a persistent red break-glass banner with one-click exit.

Tenant 360 is fully actionable: *Edit profile & subscription* modal (rename, plan, trial/term dates), user management (add/edit users in any tenant role, one-time password generation + rotation, login revoke/restore, activation, last-admin guarded), and facility CRUD with the study-reference delete guard. One-time passwords render in a copy-enabled modal that explicitly states they cannot be retrieved again. Covered by `PlatformTenantManageTest`.
