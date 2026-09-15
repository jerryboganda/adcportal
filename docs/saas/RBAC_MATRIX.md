# RBAC_MATRIX — actors × resources (enforced server-side, mirrored in the SPA)

## Platform actors

| Capability | super_admin | ops | billing | support | auditor |
|---|---|---|---|---|---|
| `tenants.view` (directory, 360 metadata) | ✓ | ✓ | ✓ | ✓ | ✓ |
| `tenants.manage` (provision, features, export) | ✓ | ✓ | — | — | — |
| `tenants.lifecycle` (activate/suspend/offboard/terminate) | ✓ | ✓ | — | — | — |
| `provisioning.manage` (retry) | ✓ | ✓ | — | — | — |
| `plans.manage` | ✓ | — | ✓ | — | — |
| `subscriptions.manage` (plan/term changes) | ✓ | — | ✓ | — | — |
| `usage.view` | ✓ | ✓ | ✓ | — | ✓ |
| `health.view` | ✓ | ✓ | — | ✓ | ✓ |
| `audit.view` | ✓ | ✓ | ✓ | ✓ | ✓ |
| `support.manage` (break-glass sessions) | ✓ | — | — | ✓ | — |
| `platform.users.view` / `.manage` | ✓ / ✓ | ✓ / — | — | — | — |
| `infrastructure.manage` (tenant re-placement) | ✓ | ✓ | — | — | — |
| `integrations.manage` (registry, secrets, probes) | ✓ | ✓ | — | — | — |
| `operations.manage` (§80 dashboard, job retry/discard, entitlement reconciliation) | ✓ | ✓ | — | ✓ | — |

Clinical data access for platform actors: **none** without an active support session (then: full operational set for that one tenant, audited, expiring).

## Tenant actors (per-tenant laratrust role bundles — `TenantBootstrap`)

| Action / permission | admin | receptionist | technician | radiologist | billing |
|---|---|---|---|---|---|
| Book studies (`appointment create`) | ✓ | ✓ | — | — | — |
| Check-in / screening / acquire (`study *`) | ✓ | ✓ (checkin/screen/cancel) | ✓ (checkin/screen/acquire) | — | — |
| Author reports (`report create/edit`) | ✓ | — | — | ✓ | — |
| Sign / release (`report sign/release`) | ✓ | ✓ (release only) | — | ✓ | — |
| Invoices (`invoice *`) | ✓ | ✓ (create/payment) | — | — | ✓ |
| Staff RBAC (`user *`) | ✓ | — | — | — | — |
| Settings / DICOM (`setting manage`) | ✓ | — | — | — | — |

SPA tab visibility (`ROLE_TABS` in `Navbar.tsx`) mirrors these bundles; the server remains the only enforcement point. Suspended users, revoked memberships, expired sessions and non-subscribable tenants are refused regardless of role (402/401/403 — see `RbacTest` and the acceptance suite).
