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
| Booking-time payment capture (`invoice payment`, layered on booking) | ✓ | ✓ | — | — | — |
| Booking-time cash discount (`invoice edit`, layered on booking) | ✓ | — | — | — | — |
| Check-in / screening / acquire (`study *`) | ✓ | ✓ (checkin/screen/cancel) | ✓ (checkin/screen/acquire) | — | — |
| Author reports (`report create/edit`) | ✓ | — | — | ✓ | — |
| Sign / release (`report sign/release`) | ✓ | ✓ (release only) | — | ✓ | — |
| Invoices (`invoice *`) | ✓ | ✓ (create/payment) | — | — | ✓ |
| Imaging suites / rooms (`room *`) | ✓ | — | — | — | — |
| Payment methods (`payment method *`) | ✓ | — | — | — | — |
| Staff RBAC (`user *`) | ✓ | — | — | — | — |
| Settings / DICOM (`setting manage`) | ✓ | — | — | — | — |

Booking-time financials are LAYERED on top of `appointment create` (`StudyController@store`): a payment with a non-zero amount additionally requires `invoice payment`, and a cash discount additionally requires `invoice edit` — a user holding only the booking permission can still book, but never touch money. Radiologists hold no booking, payment or configuration permissions: the SPA mirrors this via the server-issued `permissions[]` in the bootstrap payload (no booking affordance renders), and the API answers 403 before any resource resolution, including crafted cross-tenant payloads (`RbacTest`).

SPA tab visibility (`ROLE_TABS` in `Navbar.tsx`) mirrors these bundles; the server remains the only enforcement point. Suspended users, revoked memberships, expired sessions and non-subscribable tenants are refused regardless of role (402/401/403 — see `RbacTest` and the acceptance suite).


## Tenant module views (navigation) — RBAC access-control release

Navigation is permission-driven: each SPA module maps to one `*_view`
permission the tenant admin can toggle per role (Settings → Roles &
Permissions). Defaults reproduce the legacy per-role tabs exactly; the
Settings tab appears only for sessions holding any settings-section
permission. Full matrix and semantics: `RBAC_ADMIN.md`.

| Module view | admin | receptionist | technician | radiologist | billing |
|---|---|---|---|---|---|
| reception view | ✓ | ✓ | — | — | — |
| technologist view | ✓ | — | ✓ | — | — |
| reports view | ✓ | — | — | ✓ | — |
| billing view | ✓ | ✓ | — | — | ✓ |
| queue view | ✓ | ✓ | ✓ | ✓ | ✓ |
| inventory view | ✓ | — | — | — | — |
| catalog view | ✓ | — | — | — | — |
| doctors view | ✓ | — | — | — | — |

`queue view` is enforced SERVER-SIDE on the Live Queue TV feed
(`GET /api/v1/queue/display`, `QueueDisplayController`) — a session without
it receives 403, not just a hidden nav tab. The public waiting-room kiosk
(`/tv?key=…`) carries no session and no role: the rotatable display key is
its only credential (see `QUEUE_TV.md`).

Roles are no longer limited to the five system bundles: tenant admins can
create custom roles, edit permission sets (dependency-checked), duplicate
roles and apply per-user allow/deny overrides — all server-enforced and
audited (`AccessControlController`, `TenantAuthorizer`,
`user_permission_overrides`).
