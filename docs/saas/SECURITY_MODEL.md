# SECURITY_MODEL

## Zero-trust boundaries

- **Tenant resolution is server-only.** `getActiveBusiness()` reads the authenticated user (and, for platform staff, the server session's verified support context). A client-supplied business/tenant id is never authorization input anywhere.
- **Authentication ≠ isolation.** Session auth (Sanctum SPA cookies) is step one; every request still passes the availability gate, tenant-scoped permission checks, ownership checks, and entitlement gates.
- **Existence hiding.** Cross-tenant model binding resolves 404 — no existence oracle for the other tenant's resources.
- **Safe denials.** Non-member tenant switch returns an identical 403 regardless of target existence.

## Controls implemented

| Control | Implementation |
|---|---|
| Platform/tenant privilege separation | `PlatformAuthorizer` vs `TenantAuthorizer`; disjoint capability sets; platform bootstrap carries no clinical payload |
| Break-glass instead of standing access | `support_sessions`: reason ≥10 chars, ≤240 min, one active per user, audited open/close, auto-expiry sweep, force-closed on termination |
| Quota & entitlement enforcement | 403 `quota_exceeded` / `feature_disabled` server-side; SPA mirrors only |
| Multi-tenant privilege containment | tenant-scoped permission union; role caches flushed on switch (tested) |
| Audit | every mutating controller action + lifecycle transitions + support sessions + login/logout, attributed to `user_id`, `business_id`, `ip`; masked inputs in `APILog` (passwords/tokens redacted); audit access itself capability-gated |
| Retention / destruction | offboarding export + retention clock; destruction only via operator CLI with double confirmation — no API path exists |
| Last-admin protection | platform users: cannot disable/demote the final active super admin |
| Input hardening | pre-existing `SetLang` script-stripping + Laravel validation on all endpoints retained |
| Rate limiting | login throttle (5/min, 30/hr per email+ip) and register throttle retained |

## Honest scope

Technical controls target OWASP ASVS/multi-tenant guidance; **no compliance certification is claimed** (HIPAA/GDPR/SOC 2 are formal processes, not code outcomes). Payment data never touches the system (manual activation; no gateway).
