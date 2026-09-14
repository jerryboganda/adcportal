# TENANCY_MODEL — decision + evidence

## Decision: pooled tenancy retained (Model B), hardened

The audit found a consistent pooled implementation: one shared database, `business_id` on every domain table, server-side tenant resolution. Rewriting to schema-per-tenant or DB-per-tenant would buy nothing at current scale and would multiply migration/backup complexity, so the pooled model was kept and the isolation **inside** it was made enforceable and tested.

## Hierarchy

```text
Platform (PolytronX vendor — control plane)
  └── Tenant / Customer Organization = businesses row (tenant_code unique)
        ├── Facility/Site = locations rows (business_id)
        ├── Department/room = rooms rows
        └── Modality/operational unit = modalities + services
```

A tenant may operate many facilities via `locations`; a small clinic simply has one. Tenant ↔ facility are not conflated: plan limit `max_locations` caps facility growth.

## Why not other models

- **Schema/DB per tenant**: no requirement today; would break single-transaction cross-table constraints (the study workflow writes across 6+ tables), complicate backups/restore and the export path. The control plane keeps `isolation_profile` extensible via `subscription_status` + plan entitlements if a dedicated-deployment tier is ever sold.
- **Hybrid**: the `ris:tenant-destroy` command enumerates every `business_id`-scoped table via `Schema::getTables()`, so a future dedicated-DB tenant can be onboarded without changing the model.

## Isolation inventory (verified by tests)

| Layer | Isolation mechanism |
|---|---|
| Database | `business_id` scoping on every query via `forClinic()`/`where('business_id')`; tenant-scoped authorization prevents privilege bleed |
| Identity | memberships pivot; active tenant only via server-verified switch / support session |
| Cache | all runtime caches tenant-keyed: `company_settings_business_{id}`, `tenant:{id}:storage_bytes`, per-user laratrust cache |
| Storage | tenant-attributed files (`files.business_id`, report paths joined through tenant-owned appointments); storage meter sums per-tenant only |
| Background work | queue is synchronous; scheduler loops are per-business with explicit tenant context; support sessions expire via sweep |
| Realtime | none (polling); no cross-tenant channels exist |
| Search | none (client-side filtering of tenant-scoped payloads) |
| Exports | `/api/v1/backup` and platform export read strictly within the active/selected tenant |
