# INTEGRATION_TENANCY

## What exists today (all tenant-scoped)

| Integration | Scoping | Gate |
|---|---|---|
| **DICOM node registry** (AE title, host, port, worklist/storage SCP flags, TCP reachability probe — labelled as a probe, not C-ECHO) | `dicom_nodes.business_id`; connections belong to one clinic only | `dicom` feature entitlement + `setting manage` |
| **WhatsApp/SMS/Email notifications** (template-based; channel delivery currently logs `pending` — no gateway credentials exist and none are faked) | templates per tenant (`ris_notification_templates.business_id`); dispatch logs per tenant | `dispatch` feature entitlement for dispatches |
| **Report release channels** (hand/email/portal) | per-tenant reports + `report_releases` | `report release` permission |
| **Backup export** (JSON) | strictly the active tenant | `setting manage` |

## Unified registry (`tenant_integrations`, §33/§44)

Alongside the feature-specific surfaces above, a tenant (or one of its facilities) can register any integration in one place: type, facility scope, non-secret `config`, and `secrets`.

- **Secrets are write-only.** `secrets` is cast `encrypted:array` — encrypted at rest with the application key, never serialized to any response. The API reports which secret *keys* are present plus a fixed `••••••••` mask; there is no read-back path. Rotation is audited (`tenant_integration_secret_rotated`).
- **Probes are honest.** `tcp` opens a real socket (`fsockopen`), `http` makes a real request (`Http::get`), and `config` reports completeness of the declared config/secrets — explicitly **not** an assertion that the third party will accept or deliver anything. A probe never claims more than it measured, and the result (including `last_error`) is persisted.
- **Scoping.** `business_id` is always set; `location_id` narrows to a facility when given. Every read/write path resolves through the active tenant, so a registry entry owned by hospital A can never be probed, rotated or read in hospital B's context.
- **Entitlements.** The registry is gated by the `integrations.manage` platform capability on the control plane; the tenant-plane features it feeds remain gated by their own entitlements (`dicom`, `interop`, `notifications`).

## Rules

1. A connection or template owned by hospital A can never process hospital B's data: every read/write path resolves the record through the active tenant (`business_id` equality or `forClinic()`), with 404 on mismatch (tested).
2. Integration writes are additionally subject to module entitlements — disabling `dicom` platform-wide for a tenant blocks node registration **and** probes even for admins.
3. There is no cross-tenant system credential. If a future PACS gateway requires shared secrets, they must be modeled as explicit platform credentials with per-tenant routing — not as global config.

## Not implemented (honest inventory)

No live DICOM C-STORE/C-FIND/MWL, HL7 v2, or FHIR endpoints exist in the product today; none were stubbed to appear functional. The node registry + probe, the unified `tenant_integrations` registry with real tcp/http probes, and the template layer are the real, working surfaces. Future protocol work must start tenant/facility-routed from its first commit (AE-title ↔ tenant binding is already the natural key).
