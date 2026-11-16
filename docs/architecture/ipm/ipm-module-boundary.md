# IPM Module Boundary — InjectionMolding

**Status:** Design baseline (IPM-002). No module is scaffolded, registered, or activated by this document.
**Date:** 2026-11-17
**Owner target:** `apps/Manufacturing/modules/InjectionMolding`
**Companion:** `schema-ownership-contract.md`, `schema-proposal.sql`, `architecture-verification.md`.

---

## 1. Boundary decision (D5)

A dedicated **Injection Molding domain boundary** is established as a Manufacturing-owned module, not a new top-level app.

**Why:** mold revisions, cavity structures, process recipes, mold setup and mold maintenance are cohesive IPM capabilities; generic product, machine, BOM, production and quality ownership must stay where their existing consumers and tests live. Creating a second product/machine/BOM master would violate the no-duplicate-master rule and break every existing reference.

**What stays Manufacturing-owned (no IPM re-mastering):**

- `products` (parts master), and its label/packaging/QC profiles
- `machines` (machine master with clamp/shot/mold-height specs — the IPM *consumer* of machine data)
- `part_molds` (mold identity master — see §4)
- `part_machine_map`, `part_material_map`
- BOM (`manufacturing_bom` / `manufacturing_bom_line`), `BomService`
- `production_entries` (the genealogy anchor — IPM extends via `ipm_production_context`)
- QC, Dispatch, Ledger, MaterialManagement, ProductionPlans/Queue, Assembly, DailyOrders, PreOrders

**What the IPM module owns (new):**

| Concern | Table(s) | Notes |
|---|---|---|
| Mold revisioning | `ipm_mold_revisions`, `ipm_mold_cavities` | 1:N off `part_molds`; released revisions are immutable; spec snapshot |
| Mold maintenance & shot history | `ipm_mold_maintenance` | PM/corrective/overhaul; shot-count anchor |
| Versioned process recipes | `ipm_process_recipes` | Draft/released/superseded; effective-version rule; approval fields |
| Production context | `ipm_production_context` | mold revision × recipe × operator × shift × cavity per entry |
| Lot genealogy | `ipm_lots`, `ipm_lot_relationships`, `ipm_lot_consumptions` | Split/merge/rework/scrap/quarantine; M:N consumption |
| Serial extension (optional) | `ipm_serials` | Feature-gated; lot identity remains primary |

**Explicit non-goals:**

- No quality-management, maintenance-management, or inventory system of general purpose. IPM maintenance is **mold tooling** maintenance only; machine PM remains Manufacturing's future decision.
- No procurement, sales, or costing behavior.
- No user/identity/ACL logic; authorization is exclusively via the existing Platform organization context + ACL app permissions.

## 2. Module manifest (target shape — not created)

```json
{
  "name": "InjectionMolding",
  "display_name": "Injection Molding",
  "suite": "manufacturing",
  "group": "ipm",
  "category": "business",
  "display_order": 25,
  "type": "business",
  "version": "0.1.0",
  "description": "Injection molding domain extension for OdareHub Manufacturing.",
  "requires": [
    "Products>=1.0.0",
    "Machines>=1.0.0",
    "Bom>=1.0.0",
    "ProductionEntries>=1.0.0",
    "MaterialManagement>=1.0.0",
    "QCEntries>=1.0.0",
    "DispatchEntries>=1.0.0"
  ],
  "entry": "routes.php",
  "package_type": "module",
  "owner_app": "manufacturing",
  "module_key": "InjectionMolding",
  "module_type": "business_domain",
  "target_maturity_level": "L2",
  "migrations_path": "migrations"
}
```

## 3. Integration contracts

- **Module lifecycle:** `install.php` / `uninstall.php` / `update.php` under the module (OdareHub module convention). Install registers `core_app_modules` + applied migrations in `core_app_migrations` keyed by `owner_app=manufacturing` (shared ledger; module-level app_key is `manufacturing`, not `ipm` — consistent with current module behavior).
- **Route family:** `/apps/manufacturing/ipm/...` behind Manufacturing app access + `ipm.*` permission keys (`ipm.view`, `ipm.manage`, `ipm.recipe.approve`, `ipm.mold.maintain`, `ipm.lot.manage`).
- **Authorization:** every mutation path resolves the Platform organization context (`AuthenticatedOrganizationContextResolver`) + caller's organization and rejects cross-organization writes. No IPM-local ACL.
- **Composer autoload:** `Apps\Manufacturing\Modules\InjectionMolding\` under the app namespace convention.
- **Dependencies:** declared in manifest only; never enforced at runtime by the module itself.

## 4. Existing `part_molds` reconciliation

**Finding (verified):** `part_molds` is Manufacturing Products-owned with CRUD routes (`/apps/manufacturing/products/molds/add|delete`) and Part360 integration. It is the natural mold identity master.

**Decision (this phase):** ownership stays with Products. The IPM module treats `part_molds.id` as a foreign key, not a re-master. Mold CRUD migration to IPM (routes, services, views) is a **future, separately authorized milestone** requiring: redirect compatibility (`/apps/manufacturing/products/molds/*` → `/apps/manufacturing/ipm/molds/*`), permission remap (`products.manage` vs `ipm.manage`), and Part360 compatibility testing. Until then, IPM and Products co-own the mold experience by boundary, not by duplication.

**Revision bridge (compatibility):** IPM revision snapshot fields mirror live `part_molds` column semantics (clamp/shot/dims/runner/cycle/machine group/type). A revision's released snapshot is authoritative for that revision; the live `part_molds` row remains the *current* working specification. No automatic overwrite of `part_molds` from revisions, and vice versa.

## 5. Data flow (target)

```text
Part360 / Products UI
   └─ part_molds (current spec) ──< ipm_mold_revisions (released history)
        └─ ipm_mold_cavities
        └─ ipm_mold_maintenance (shots/PM)

Production planning / entries
   └─ production_entries ──< ipm_production_context (revision, recipe, operator, shift, cavity)
        ├─ ipm_lot_consumptions >── ipm_lots (raw material inputs)
        └─ ipm_lots (output, produced_by_production_entry_id)
              ├─ ipm_lot_relationships (split/merge/rework/scrap/quarantine)
              └─ ipm_serials (optional)

QC / Dispatch
   └─ linked via ipm_lot_relationships.reference_type ('qc'|'dispatch')
```

## 6. Security and tenant isolation

- Every `ipm_*` table carries `organization_id INT NOT NULL` FK → `org_companies`.
- Every IPM query accepts `organization_id` as a mandatory parameter from the resolved Platform context; there is no "current organization" global inside IPM services.
- Membership validity (active, `valid_from`/`valid_until` window, not revoked) is re-checked at every mutation; reads may rely on cached context within the request only.
- Cross-organization FK references are impossible by construction (all tenant ids target `org_companies`); composite uniqueness (`uq_ipm_lot_code`, `uq_ipm_recipe`, etc.) is organization-scoped.

## 7. Rollout sequence (within the IPM program)

1. **Module skeleton + manifest + permissions** (docs, no runtime) — only after IPM-002 is accepted and owner authorizes scaffold.
2. **Migrations 001–006** per `identity-and-tenant-migration-plan.md` Phase 5.
3. **Read-only services + probes** (revision history, recipe lookup, lot trace read path).
4. **Mutation services** behind organization-context authorization + CSRF (pattern from Hospitality/Procurement operator surfaces).
5. **UI** under `/apps/manufacturing/ipm/*` and operator surface contributions — separately authorized.
6. **Part360 integration** (display released mold revision + active recipe; no duplication of Part360).
