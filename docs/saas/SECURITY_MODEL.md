# SECURITY_MODEL

## Zero-trust boundaries

- **Tenant resolution is server-only.** `getActiveBusiness()` reads the authenticated user (and, for platform staff, the server session's verified support context). A client-supplied business/tenant id is never authorization input anywhere.
- **Authentication ≠ isolation.** Session auth (Sanctum SPA cookies) is step one; every request still passes the availability gate, tenant-scoped permission checks, ownership checks, and entitlement gates.
- **Existence hiding.** Cross-tenant model binding resolves 404 — no existence oracle for the other tenant's resources.
- **Safe denials.** Non-member tenant switch returns an identical 403 regardless of target existence.
- **Unauthenticated is always a 401.** An unauthenticated request to any protected API route returns `401 {"message":"Unauthenticated."}` — regardless of the `Accept` header, and never a redirect. (This application has no named routes, so the framework's default guest redirect to `route('login')` had nowhere to go and answered 500 instead; `redirectGuestsTo(null)` plus JSON rendering for `api/*` fixed it. Pinned by `UnauthenticatedResponseTest`.)
- **Presentation is never authorization.** A custom host, a tenant's brand, and a tenant's deployment placement are all presentation/operational metadata. The tenant is resolved from the session and nothing else; a request arriving on `clinic.example.com` gets no more access than the same request arriving on the platform host.
- **Secrets are write-only.** Tenant integration credentials are encrypted at rest (`encrypted:array`) and never serialized to any response — the API reports which secret *keys* exist plus a fixed mask. There is no read-back path, by design.

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
| PHI-safe failure inspection | a failed job's payload may contain patient data, so it is never returned and **never unserialized** (so inspection cannot execute a payload or trigger a gadget chain). Property *names* come from `ReflectionClass` on the job class — a regex over the serialized command would have captured property *values*, which is precisely the leak this avoids. Enforced by a test that plants a secret in the payload and asserts it appears nowhere in the response |
| Integration secrets | `tenant_integrations.secrets` cast `encrypted:array`; rotation is audited; the API exposes presence + `••••••••` only |
| Route action integrity | `RouteIntegrityTest` fails the build if any routed action's class or method does not exist, and asserts every `api/v1/platform` route carries the `platform` guard — closing the class of defect where an unimported controller silently 500s a whole surface |
| Operational tooling is audited | failed-job retry/discard and entitlement reconciliation are capability-gated on `operations.manage` and recorded in `audit_logs`; a reconciliation *report* changes nothing and is not audited as a change |
| Input hardening | pre-existing `SetLang` script-stripping + Laravel validation on all endpoints retained |
| Rate limiting | login throttle (5/min, 30/hr per email+ip) and register throttle retained |

## Honest scope

Technical controls target OWASP ASVS/multi-tenant guidance; **no compliance certification is claimed** (HIPAA/GDPR/SOC 2 are formal processes, not code outcomes). Payment data never touches the system (manual activation; no gateway).
