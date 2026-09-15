# DEPLOYMENT_TOPOLOGY — where a tenant physically lives (§23/§59/§60)

## The problem

Before this work, "where is tenant X hosted?" had no answer the software could give. Every
tenant was implicitly on one undisclosed placement. That is tolerable at two clinics and
unworkable at two hundred: you cannot answer a data-residency question, you cannot reason
about blast radius when a stamp degrades, and you cannot move a single tenant to a quieter
cluster without touching every other one.

## The model

Five placement facts are recorded per tenant, each expand-only (no data rewrite, no
downtime):

| Column on `businesses` | Meaning |
|---|---|
| `region` | geographic region — the data-residency unit |
| `deployment_stamp` | the running deployment instance the tenant is served by |
| `isolation_profile` | `pooled` / `schema` / `database` / `dedicated` |
| `database_cluster` | the cluster holding the tenant's data |
| `storage_region` | where the tenant's files live (may differ from `region`) |

`storage_region` is deliberately separate from `region`: a clinic can be served from one
region while its imaging archive sits in another, and collapsing the two would make that
common arrangement unrepresentable.

## The catalog is config-owned, not tenant-owned

Regions, stamps and isolation profiles are declared in `config/ris.php`
(`regions`, `deployment_stamps`, `isolation_profiles`), so:

- the console can only offer placements the operator has actually declared;
- the API validates the same list **again** server-side — the SPA is never the authority;
- adding a region is a config change and a deploy, not a data migration.

The shipped catalog is deliberately minimal — one region, one stamp, the four isolation
profiles — because inventing infrastructure the operator does not have would be worse than
admitting there is one placement. The shape is what matters; the operator fills it in.

## Provisioning stamps the default

`TenantLifecycleService::provision` places every new tenant on the operator's declared
default (`array_key_first(config('ris.regions'))` / `…deployment_stamps…`), so no tenant is
ever created unplaced. The test fixture mirrors this exactly, which is why placement is
asserted rather than assumed.

## Re-placement is an operator action, and it is audited

`PATCH /api/v1/platform/tenants/{id}/deployment` (`infrastructure.manage`) moves one tenant.
Unknown values are rejected. The transition is recorded as `tenant_deployment_updated` with
both the previous and the new placement, because "who moved this tenant and when" is exactly
the question asked after an incident.

## Fleet visibility

`GET /api/v1/platform/infrastructure` returns the catalog plus a placement summary,
including tenants that are **unplaced** (a state that should not exist, and is therefore
reported rather than hidden). The §80 operations dashboard adds the operational half:
a per-placement rollup of `tenants / degraded / critical`, which answers *"is this one
clinic, or is the whole stamp down?"* — the question that decides whether you debug a tenant
or fail over a region.

## What this is not

Recording placement does not by itself provide multi-region failover, cross-region
replication, or automated tenant migration. Those are infrastructure capabilities that
depend on the operator's actual platform. What this provides is the **record and the
control surface** they need: the software now knows where each tenant is, can validate a
move, can audit it, and can tell an operator what is affected when something breaks.
