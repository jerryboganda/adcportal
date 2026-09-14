# TENANT_DATA_CLASSIFICATION

| Classification | Tables / artifacts |
|---|---|
| **Platform (global)** | `plans`, `currencies`, `languages`, `permissions` (catalog), `password_reset_tokens`, `sessions`, `personal_access_tokens`, `failed_jobs`, `cache` |
| **Platform control-plane (vendor-owned)** | `users` rows with `type ∈ {super_admin, platform_admin}` (`business_id = 0`), `support_sessions`, `tenant_lifecycle_events` (per-tenant but vendor-visible) |
| **Tenant-owned (clinical PHI)** | `customers` (patients incl. MRN/DOB/history), `appointments` + workflow columns, `radiology_reports`, `report_releases`, `study_screening_answers`, `dose_logs`, `appointment_reports`, `invoices`/`invoice_items`/`invoice_payments`, `adverse_reactions`, `doctor_dispatch_logs` |
| **Tenant-owned (operational)** | `modalities`, `services`, `referrers`, `screening_forms`/`questions`, `report_templates`, `rooms`, `locations`, `business_hours`/`holidays`, `inventory_items`/`transactions`, `dicom_nodes`, `ris_notification_templates`, `app_notifications`, `custom_fields`/`custom_statuses`, `files`, `categories` |
| **Tenant identity/config** | `businesses`, `settings` (`business` column; `business = 0` rows are platform defaults), `theme_settings` |
| **Audit** | `audit_logs` (`business_id` attribution + platform actions), `login_details` |
| **Memberships (relationship data)** | `tenant_memberships`, laratrust `roles`/`role_user`/`permission_role` (roles per-tenant via `created_by`) |

Rules enforced by review + tests:

1. New tables must declare `business_id` at creation or be explicitly classified global/platform here.
2. Clinical PHI is **never** serialized into control-plane payloads (`GET /bootstrap` platform branch, platform tenant 360) — asserted by `SaaSAcceptanceScenarioTest`.
3. Uniqueness constraints are tenant-scoped where clinically appropriate (e.g. screening form slug is unique per `business_id`; `businesses.tenant_code` is the one deliberately global identifier).
