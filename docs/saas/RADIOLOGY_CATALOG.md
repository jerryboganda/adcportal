# Radiology Catalog Foundation

## Current Status

This is an additive, staged foundation, not a completed clinical catalog rollout.
No production source dataset is bundled. No release is automatically published,
no tenant bootstrap calls the new initializer yet, and no reset command is added.
The existing starter catalog remains in use, with safer retry behavior.

Implemented in this slice:

- Shared releases, source manifests, stable modality/anatomy/procedure identities,
  immutable procedure revisions, anatomy relationships, aliases and mappings.
- Nullable canonical links on existing tenant services/modalities, price provenance,
  configuration versions, tenant anatomy overrides and a nullable study snapshot.
- Structural artifact validation, no-write previews, digest-bound transactional
  staging, import audit records and PostgreSQL advisory locking.
- An internal published-release initializer that inserts missing tenant rows and
  persists the requested owner prices without updating existing tenant settings.
- Strict nullable decimal/minor-unit conversion alongside the legacy money API.
- Regression tests included in the SQLite suite and PostgreSQL CI selection.

Local PHP syntax checks and editor diagnostics passed. PHPUnit, migration execution,
concurrency checks and browser tests have **not run** for these changes. Repository
rules require GitHub Actions; the local changes have not been committed or pushed.

## Ownership

Existing `services` rows remain the tenant-owned operational and pricing records.
There is no parallel price override table. A default activation is unique by
`business_id`, `canonical_procedure_id` and `local_variant_key`, including deleted rows.
Tenant aliases use a composite foreign key that prevents cross-tenant references.
A composite foreign key prevents a service from linking a revision of another concept.

Source labels and raw source fields are retained separately from tenant names.
Procedure revisions cannot be changed or deleted through their Eloquent model.
Later source imports create new revisions and preserve older release manifests.
Modality/anatomy identities retain their first imported label; each release manifest
preserves that release's representations. Current-release presentation still needs
to be connected by the operational resolver.

Schema migrations do not download sources, initialize tenants, change existing
prices, delete clinical rows, reset sequences, or touch files. Rolling the tenant
migration down deliberately does not shrink service names or make prices non-null:
doing either could discard valid values written after the upgrade.

## Artifact Contract

[CatalogNormalizer](../../app/Services/RadiologyCatalog/CatalogNormalizer.php) is the
current executable structural contract. It accepts normalized JSON, **not** a raw
LOINC ZIP, RadLex ontology, DICOM HTML page or arbitrary fee-schedule CSV.

Required top-level fields:

| Field | Meaning |
| --- | --- |
| `schema_version` | Currently `1`. |
| `release_key` | Stable, immutable application release identity. |
| `fixture` | Explicit boolean; synthetic imports are restricted to tests. |
| `sources` | System, version, date, HTTPS origin/license URLs, source/license SHA-256, attribution and retrieval time. |
| `modalities` | Stable keys, preserved source identity, acquisition/composite kind and ordered DICOM acquisition components. |
| `regions` | Stable keys, preserved source identity and structure/composite/group kind. |
| `region_relations` | Navigation, part-of or composite-member edges. |
| `procedures` | Source-backed concepts, clinical attributes, region roles, aliases and external mappings. |
| `exclusions`, `quarantines` | Source identities and explicit reasons. |
| `discovered_procedures` | Must equal included + excluded + quarantined identities. |
| `expected_counts` | Exact counts for sources, modalities, regions, edges, procedures, aliases, mappings, exclusions and quarantines. |

Every modality, region and procedure declares `source`, `source_code`, `name`,
`source_fields`, `source_code_field` and `source_name_field`. The code and name must
match the designated untouched source fields. Canonical keys are separate from
standard codes. Aliases are normalized only for search, never used as identities.
The synthetic fixture in [tests/Support](../../tests/Support/RadiologyCatalogFixture.php)
illustrates structure only; its entries are not validated clinical terminology.

Structural validation rejects duplicate identities, conflicting exact mappings,
unknown references, navigation ambiguity/cycles, contradictory contrast/laterality,
and unsupported standard-price classifications. Missing anatomy remains missing;
it cannot produce an orderable procedure. View order is retained, including when
raw sequence metadata is ambiguous. No source-name substring heuristic determines price.

The currently supported acquisition subset is CT, MR, US, CR, DX, MG, RF, XA, NM,
PT and BMD. Composite groups require real ordered components and no invented single
DICOM code. This is not the complete DICOM Modality value set or a PACS transport.

**Structural validation is not source authenticity, license clearance, clinical
certification or proof of full-source coverage.** Source checksums and classifications
are supplied metadata at this layer. Authorized source adapters, raw-file verification,
license evidence and independent coverage gates must precede publication. Never mark
a release published directly in a real database to bypass those missing gates.

Source intake remains pending for the authorized LOINC/RSNA release and RadLex export.
Use the official [LOINC download API](https://loinc.org/kb/api/download/),
[LOINC license](https://loinc.org/license/), [RadLex](https://radlex.org/) and
[DICOM CID 29](https://dicom.nema.org/medical/dicom/current/output/chtml/part16/sect_CID_29.html).
Keep credentials in CI secrets or approved private artifact storage, never in chat,
the repository, or public logs.

## CI Commands

The `ris:catalog` command is restricted to GitHub Actions outside tests. Run against
an isolated CI/staging database. Do not set environment flags locally to bypass
the repository compute-placement rule.

```bash
php artisan ris:catalog validate "$CATALOG_JSON"
php artisan ris:catalog dry-run "$CATALOG_JSON"
php artisan ris:catalog import "$CATALOG_JSON" --sha256="$EXPECTED_NORMALIZED_SHA256"
php artisan ris:catalog report --release="$CATALOG_RELEASE_KEY"
```

The expected digest is the normalized artifact hash reported by validation/preview,
not the original JSON file hash. Source file hashes are separate evidence. Review
the preview before supplying the digest. `import` stages only; it does not publish,
activate, reset or overwrite a release. Repeating the same import returns the same
release. A conflicting release key/source identity fails. Any staging transaction
failure rolls back its new rows and audit record together.

Focused CI verification:

```bash
php artisan test --filter='RadiologyCatalog|MastersConfigTest|BookingMoneyTest'
```

Run on SQLite and PostgreSQL 17. The full feature/unit suite remains required before
deployment. No test success, restore success or release readiness is implied by
the presence of test cases.

## Tenant Initialization

[TenantCatalogService](../../app/Services/RadiologyCatalog/TenantCatalogService.php)
has an internal `initialize` operation, with no CLI or HTTP activation route yet.
It requires a published release, explicit tenant and currency/precision, and a real
tenant owner. The existing clinic currency and initialization currency cannot be
changed through this operation. Currency is never inferred from a display symbol.

For new PKR services, verified standard categories persist these amounts:

| Category | PKR |
| --- | ---: |
| CT without contrast | 2,000 |
| CT with / with-and-without contrast | 3,500 |
| MR without contrast | 3,000 |
| MR with / with-and-without contrast | 4,000 |
| Standard ultrasound | 200 |
| Standard conventional radiography | 300 |
| Specialized, unknown or unsupported price category | Unconfigured (`null`) |

Combined contrast is non-additive. Non-PKR tenants receive no relabelled or converted
PKR defaults. The existing two-decimal service column currently prevents initialization
with currencies requiring greater precision; no value is rounded to work around it.

Retries preserve names, codes, explicit zero, cleared prices, durations, disabled
anatomy, deleted modalities/services, and subscription state. Initialization does not
guess legacy-to-canonical equivalence. Local catalog codes remain separate from
verified DICOM codes. Source aliases stay shared; tenant aliases stay tenant-owned.

No rooms, equipment, durations, preparation instructions or confirmed slots are
invented. New procedures are enabled but not online-bookable by this initializer.
The complete effective bookability resolver is still pending. Changing an already
initialized tenant to another release is refused until the reconciliation path is
implemented; staged future releases do not silently repoint operational rows.

## Remaining Gates

- Obtain licensed source artifacts and implement/verify the actual source adapters.
- Execute CI migrations and regression tests; add PostgreSQL concurrency and full
  artifact performance evidence, not only synthetic fixture checks.
- Implement source-gated publication and non-destructive release reconciliation.
- Connect tenant provisioning, operational search, RBAC, nullable money API/UI,
  quote validation, billing guards and immutable clinical snapshots together.
- Complete scheduling, screening, reporting/template and interoperability contracts.
- Build the separately authorized reset manifest, identifier reservations, worker
  fence, private database/file backup and restore rehearsal before any deletion.
- Harden tested-image deployment and health gates before automatic production rollout.

Do not run a production reset, bulk seed or manual status update as a substitute
for these steps. Initial reset authorization does not authorize later data deletion.