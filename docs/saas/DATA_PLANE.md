# DATA_PLANE — tenant operational surface (implemented)

The pre-existing RIS remains the product core, now strictly tenant-scoped and membership-aware:

- **Clinical workflow**: booking → check-in → preparing → acquisition (+ dose log, screening) → reading → reported → delivered; every transition permission-checked per tenant and audited (`StudyWorkflowService`).
- **Reporting**: draft/addendum/sign/release with immutable signed reports; PDF via dompdf.
- **Billing**: server-authoritative invoices, payments, voids; POS flows.
- **Masters**: modalities, services, referrers, screening forms, report templates — all `business_id`-scoped.
- **Inventory & safety**: SKU/stock/batches, contrast adverse reactions; writes gated by the `inventory` feature entitlement.
- **DICOM node registry**: per-tenant AE titles/nodes with TCP reachability probe; writes gated by the `dicom` feature entitlement.
- **Doctor dispatch**: gated by the `dispatch` feature entitlement.
- **Bootstrap**: one-round-trip hydration per tenant, now including `entitlements` (plan, limits, usage, features) so the SPA mirrors — never decides — entitlement state.
- **Audit view**: tenant-attributed (`audit_logs.business_id`, plus legacy staff-authored rows), surfaced in Settings.

Tenant admin sees **zero** other tenants (asserted by `TenantIsolationTest` and the acceptance suite).
