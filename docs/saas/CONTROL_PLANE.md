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
| `GET /infrastructure` | `tenants.view` | placement catalog (regions, stamps, isolation profiles, clusters, storage regions) + fleet placement summary incl. unplaced tenants |
| `PATCH /tenants/{id}/deployment` | `infrastructure.manage` | re-place a tenant (region / stamp / isolation profile / cluster / storage region); audited `tenant_deployment_updated` |
| `GET /tenants/{id}/branding` | `tenants.view` | white-label presentation overrides + custom-host registry for one tenant |
| `PUT /tenants/{id}/branding` | `tenants.manage` | update presentation overrides (app name, colours, logo, support contacts) — gated by the `branding` entitlement |
| `POST /tenants/{id}/domains` | `tenants.manage` | register a custom host; returns the DNS TXT record the operator must publish (`_polytronx-ris.<host>`) |
| `POST /tenants/{id}/domains/{domainId}/verify` | `tenants.manage` | verify ownership by resolving that TXT record; only then may the host serve the brand |
| `POST /tenants/{id}/domains/{domainId}/primary` | `tenants.manage` | mark one verified host as the tenant's canonical host |
| `DELETE /tenants/{id}/domains/{domainId}` | `tenants.manage` | remove a host |
| `GET /tenants/{id}/integrations` | `tenants.view` | integration registry (config + secret **presence** only, masked) |
| `POST /tenants/{id}/integrations` | `integrations.manage` | register an integration (type, facility scope, config, secrets) |
| `PATCH /tenants/{id}/integrations/{integrationId}` | `integrations.manage` | edit config / enable / disable |
| `POST /tenants/{id}/integrations/{integrationId}/secrets` | `integrations.manage` | rotate stored secrets (audited, never echoed back) |
| `POST /tenants/{id}/integrations/{integrationId}/probe` | `integrations.manage` | run the real health probe (tcp / http / config-completeness) and record the result |
| `DELETE /tenants/{id}/integrations/{integrationId}` | `integrations.manage` | deregister |
| `GET /operations` | `operations.manage` | §80 dashboard: live system facts + per-tenant health rollup with reasons + placement rollup + provisioning-stuck list |
| `GET /operations/jobs` | `operations.manage` | §81 failed-job inspection — job class, queue, attempts, exception type/summary, payload **size** and property **names**; the payload itself is never returned |
| `POST /operations/jobs/{uuid}/retry` | `operations.manage` | genuinely re-queue a failed job (`queue:retry`); audited `failed_job_retried` |
| `DELETE /operations/jobs/{uuid}` | `operations.manage` | discard a failed job (`queue:forget`); audited `failed_job_forgotten` |
| `POST /tenants/{id}/entitlements/reconcile` | `operations.manage` | entitlement drift report; `{"apply": true}` prunes **only** inert stale overrides (audited `entitlements_reconciled`) |
| `GET/POST/PATCH /plans[...]` | `tenants.view` / `plans.manage` | plan catalog CRUD (limits + features) |
| `GET/POST/PATCH /users[...]` | `platform.users.*` | platform staff management (last-super-admin guarded) |
| `GET/POST /support-sessions`, `POST .../end` | `support.manage` | break-glass session lifecycle |
| `GET /audit` | `audit.view` | platform-wide audit stream (filters: `action`, `tenantId`) |

Tenant-plane context routes: `GET /api/v1/memberships`, `POST /api/v1/tenant/switch` (tenant members), `POST /api/v1/tenant/enter|leave` (platform + active session). `GET /api/v1/plans` is public (signup + gate views).

## UI — Platform Console (`src/components/PlatformConsole.tsx`)

Dark-shelled, deliberately distinct from the clinic app. Sections: Overview, Tenants (+ Provision modal + Tenant 360 with Users/Facilities/Deployment/Branding/Integrations/Lifecycle/Audit/Features tabs), Plans (CRUD), Usage, Infrastructure (placement catalog + fleet summary + per-tenant re-placement), Operations (§80/§81), Support Sessions (open/enter/end), Platform Users (role matrix editing), Audit. Capability-aware: controls hidden per role, enforced server-side regardless. Enter-clinic renders the tenant app under a persistent red break-glass banner with one-click exit.

Tenant 360 is fully actionable: *Edit profile & subscription* modal (rename, plan, trial/term dates), user management (add/edit users in any tenant role, one-time password generation + rotation, login revoke/restore, activation, last-admin guarded), facility CRUD with the study-reference delete guard, deployment re-placement, branding overrides + custom-domain verification, and the integration registry with masked secrets and real probes. One-time passwords render in a copy-enabled modal that explicitly states they cannot be retrieved again. Covered by `PlatformTenantManageTest`, `PlatformDeploymentTest`, `TenantBrandingTest`, `TenantIntegrationTest`.

**Operations** is the answer to "what is actually happening right now?" — live system facts (database, cache, queue connection + pending depth + oldest pending age, failed-job driver and count, storage writability, app env/version, last lifecycle event), a worst-first tenant-health table where every verdict carries its reasons, a placement rollup that answers *"which region or stamp is affected?"*, and a provisioning-stuck list. From there an operator can retry or discard a failed job and run an entitlement reconciliation inline — all capability-gated on `operations.manage` and audited. Nothing on the page is inferred client-side; every figure is the server's own measurement.
