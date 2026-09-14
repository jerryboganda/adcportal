# INTEGRATION_TENANCY

## What exists today (all tenant-scoped)

| Integration | Scoping | Gate |
|---|---|---|
| **DICOM node registry** (AE title, host, port, worklist/storage SCP flags, TCP reachability probe — labelled as a probe, not C-ECHO) | `dicom_nodes.business_id`; connections belong to one clinic only | `dicom` feature entitlement + `setting manage` |
| **WhatsApp/SMS/Email notifications** (template-based; channel delivery currently logs `pending` — no gateway credentials exist and none are faked) | templates per tenant (`ris_notification_templates.business_id`); dispatch logs per tenant | `dispatch` feature entitlement for dispatches |
| **Report release channels** (hand/email/portal) | per-tenant reports + `report_releases` | `report release` permission |
| **Backup export** (JSON) | strictly the active tenant | `setting manage` |

## Rules

1. A connection or template owned by hospital A can never process hospital B's data: every read/write path resolves the record through the active tenant (`business_id` equality or `forClinic()`), with 404 on mismatch (tested).
2. Integration writes are additionally subject to module entitlements — disabling `dicom` platform-wide for a tenant blocks node registration **and** probes even for admins.
3. There is no cross-tenant system credential. If a future PACS gateway requires shared secrets, they must be modeled as explicit platform credentials with per-tenant routing — not as global config.

## Not implemented (honest inventory)

No live DICOM C-STORE/C-FIND/MWL, HL7 v2, or FHIR endpoints exist in the product today; none were stubbed to appear functional. The node registry + probe and the template layer are the real, working surfaces. Future protocol work must start tenant/facility-routed from its first commit (AE-title ↔ tenant binding is already the natural key).
