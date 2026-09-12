# Shared Items Foundation — Session A Audit + Canonical Contract

Canonical branch: work/shared-items-impl (from 316b806). Integrated to `main` 2026-09-12 with
explicit user authorization as the minimal Shared Items slice (contract + identity
foundation + reference adoption).
Reference: session-d-shared-app-suite-extension-contract @ c886b89 (governance/reference; not rewritten)
Status: COMMITTED MINIMAL SLICE — contract + `shared/Item/Foundation` identity service +
reference adoption (`products.item_ref` migration 012; Manufacturing-owned BOM foundation
migration 013). Sections 4/5/8 "not executed" statements refer to the original Session A
contract phase and are superseded by the slice recorded here; no `shared_items` DB table
was created (identity store remains test/verification JSON until a runtime consumer lands).

---

## 1. Existing-Domain Inventory (Audit — read-only)

Source path: `apps/Manufacturing/modules/Products/migrations/` (11 SQL migrations: 001–011).

Manufacturing Product identity (existing, Manufacturing-owned):
- Table: `products`
- Key: `id` INT AUTO_INCREMENT PRIMARY KEY
- Business identifier: `parts_number` VARCHAR(100) NOT NULL (UNIQUE `uniq_parts_number`)
- Name: `parts_name` VARCHAR(255) NOT NULL
- Manufacturing-specific extensions (002): `model`, `producer`, `lead`, `notes`, `is_active`, `updated_at`; keys `idx_products_active`, `idx_products_model`, `idx_products_producer`
- Additional Manufacturing-specific fields (003–011): `products_cycle_time`, `products_supply_model`, `qc_time_per_item`, `part_execution_planning_fields`, `processing_dispatch_mode`, `part_molds_and_material_yield`, `part_molds_machine_fit_alignment`, `active_machine_assignment`, `packaging_profile`
- Label/design artifacts: `apps/Studio/Tools/LabelDesigner/` references `manufacturing.product.label.*` rules/context/template resources — Manufacturing-owned label artifacts, not Item identity.
- Inventory/stock references: `apps/Manufacturing/modules/Ledger/` (stock ledger entries); `MaterialManagement` module tracks materials separately from Products.

Other existing representations (not Manufacturing Products, but relevant to duplication risk):
- `MaterialManagement` (materials, coverage, orders, receipt) — material-level concepts distinct from finished Product
- `QCPlans`, `ProductionOperation`, `Assembly` — process execution tied to part/product by reference, not identity
- `procurement/supplier/vendor` fields exist as text (`producer` in products; supplier references elsewhere) — these are role/relationship references, not Party identity (Session B owns Party)
- `packages/` / `plugins/` / `platform/` / app-level catalogs may contain product-like references — no universal Item table exists in core or platform today

Ownership finding:
- Manufacturing owns `products` table, all 11 migration ALTERs, and all module-specific fields.
- No Shared `Item` table exists in `app/`, `platform/`, or `Shared/`.
- No duplicate `Item` identity exists; duplication risk is future (if Shared creates Item and Manufacturing keeps `products` independently), not current.

---

## 2. Duplication / Ownership Map

| Concept | Current Owner | Shared? | Risk if Shared Item created |
|---|---|---|---|
| `products` table / part number / product name | Manufacturing (module `Products`) | No | Manufacturing Product should reference Shared Item by `item_ref`, not become Shared Item |
| `parts_name` / `parts_number` | Manufacturing-specific labeling | No | Display override `Item → Product` belongs to consumer; identity stays shared |
| `model` / `producer` / `lead` / `notes` / `is_active` / `updated_at` | Manufacturing-specific | No | Must stay in Manufacturing extension, not Shared Item master |
| Material / coverage / receipt / material orders | MaterialManagement module | No | Inventory (Session C) references Shared Item; material is a category/role of Item, not separate master |
| SKU / code / reference patterns | Manufacturing `parts_number`; other apps may use their own | No | Canonical `code` / `reference` in Shared Item must not force Manufacturing's numbering scheme |
| Category / type / classification | Manufacturing-specific; no universal taxonomy | No | Shared Item should allow `category_ref` / `type_ref` pointing to consumer-owned taxonomy |
| Label / design artifacts | Manufacturing-owned (`labels/rules/`, `labels/templates/`, `labels/contexts/`) | No | Manufacturing label rules reference `product-label`; consumer can say `Item 123` → `label context = manufacturing.product.label` |

---

## 3. Canonical Item Contract (Thin Foundation)

Purpose: provide the smallest shared identity that manufacturing (and other consumers) can reference, without owning consumer-specific fields.

### 3.1 Identity fields (minimal, universal)

```text
item_id          -> canonical integer ID (primary key)
code_ref         -> unique business reference / SKU / part-number reference (VARCHAR, NOT forced to Manufacturing format)
name             -> canonical display name (VARCHAR, not product/part/material-specific)
status           -> lifecycle: draft / active / deprecated / archived (enum/int)
category_ref     -> optional reference to consumer-owned taxonomy (not a category master)
metadata_ref     -> optional reference to consumer extension (not a data store)
created_at       -> audit
updated_at       -> audit
```

### 3.2 What explicitly must NOT enter Shared Items (Manufacturing-specific)

Per audit of Manufacturing migrations 001–011 and module surfaces:
- `model` (manufacturing model designation)
- `producer` (supplier/vendor text reference — belongs to Party/Consumer, not Item)
- `lead` (manufacturing lead / planner reference)
- `notes` (free-text manufacturing note — can stay in Manufacturing extension)
- `parts_name` / `parts_number` as identity (these are Manufacturing-specific names/numbering; Shared Item uses `name` + `code_ref` generically)
- `is_active` as a universal semantic (Manufacturing-specific activation; Shared Item uses `status` enum with deeper semantics)
- `cycle_time`, `supply_model`, `qc_time_per_item`, `execution_planning_fields`, `processing_dispatch_mode`, `part_molds`, `part_fit_alignment`, `machine_assignment`, `packaging_profile`
- Any Manufacturing-specific label/template/rule content (label artifacts stay Manufacturing-owned; consumer can reference them by `label_context_ref` if needed)
- Any inventory/stock/ledger data (Session C owns stock of Items, not the item identity)
- Any pricing, cost, tax, discount, total fields (Session D / commercial domains own pricing)
- Any supplier/customer/guest relationship data (Session B / Party owns relationship)

### 3.3 Extension / Consumer Reference Pattern

Required architecture (from session rules):

```text
Shared Item (this session)           -> identity + code + name + status + category_ref + metadata_ref
  ↓ reference (by item_id / code_ref)
Manufacturing Product (existing)     -> extends via reference table or JSON metadata_ref:
                                        product_item_ref -> item_id
                                        parts_number -> Manufacturing-specific reference
                                        parts_name -> Manufacturing-specific display override
                                        model / producer / lead / notes / is_active -> Manufacturing extension
                                        label_context_ref -> manufacturing.product.label.*
  ↓ reference
Inventory / Stock (Session C, future) -> stock_of_item_ref -> item_id; warehouse_id; quantity; unit
```

This avoids duplicating `items` inside Manufacturing or forcing Manufacturing to rename its `products` table.

### 3.4 Ownership

- Shared Items: owned by Shared/Foundation (this session); mutation authority is shared, not Manufacturing.
- Manufacturing Product: continues to own its `products` table, all 11 migrations, all module-specific fields. Manufacturing does NOT become Shared Items.
- Consumer extension data (Manufacturer-specific fields): owned by Manufacturing module; lives either in Manufacturing `products` table (existing) with a `item_ref` column, or in a Manufacturing-owned extension table referencing `item_id`.
- Read/mutation: Shared Item read open to all authorized consumers; mutation requires Shared authorization, not Manufacturing-only.

---

## 4. Minimal Schema Proposal (Design Only — No DB Mutation)

Table proposal (reference for future implementation, not executed):

```sql
CREATE TABLE IF NOT EXISTS shared_items (
    item_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code_ref VARCHAR(255) NOT NULL UNIQUE COMMENT 'Business reference / SKU; not Manufacturing-specific format',
    name VARCHAR(500) NOT NULL,
    status ENUM('draft','active','deprecated','archived') NOT NULL DEFAULT 'draft',
    category_ref VARCHAR(120) NULL COMMENT 'Reference to consumer taxonomy; not master taxonomy',
    metadata_ref VARCHAR(255) NULL COMMENT 'Reference to consumer extension / metadata',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_shared_items_status (status),
    INDEX idx_shared_items_code_ref (code_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Constraints / design notes:
- No `parts_name`, `parts_number`, `model`, `producer`, `lead`, `notes`, `is_active`, `updated_at` semantics copied.
- `code_ref` does NOT enforce Manufacturing `parts_number` format.
- No unit/master categories embedded (Session C handles warehouse/unit; Session A avoids universal taxonomy ownership).
- Extension via `metadata_ref` (reference to Manufacturing extension) keeps Manufacturing fields out of master.

---

## 5. Migration Strategy From Manufacturing Products (Not Executed)

Not implemented. Design only:

- Manufacturing `products` table remains untouched.
- Future (separate session, with Manufacturing owner approval): add `item_ref` column to `products`; populate from existing `parts_number` mapping; do NOT rename `parts_name` to `name` (display override stays Manufacturing-specific).
- Manufacturing migrations (001–011) stay Manufacturing-owned; no alteration to existing AST/DB structure required for Shared Items.
- Manufacturing label resources (`manufacturing.product.label.*`) reference Product by key, not by Shared Item id; consumer mapping (`Item 123 → Product`) is a display-time resolution, not a DB migration.
- Duplicate/merge behavior: if a Manufacturing Product and Shared Item represent the same real-world thing, resolution is by `item_ref` + business approval, not automatic merge of `products` into `shared_items`.
- Deactivation/deletion: Shared Item `status` to `deprecated`/`archived`; Manufacturing Product `is_active` stays Manufacturing-controlled.

---

## 6. Consumer / Extension Contract

```text
Consumer (e.g., Manufacturing Product):
  - reads Shared Item by item_id / code_ref
  - displays with Manufacturing-specific override (name, label, context)
  - writes to Manufacturing extension (not to Shared Item master)
  - references Shared Item for cross-domain usage (Inventory of Item)

Shared Items (foundation):
  - provides identity only
  - does NOT provide pricing, stock, supplier, customer, manufacturing execution data
  - allows consumer-owned extension via metadata_ref / reference
  - must not become universal ERP transaction master (Session D must decide separately)
```

---

## 7. Architecture / Gate References

- Reference contract: `session-d-shared-app-suite-extension-contract @ c886b89` (governance/reference; not rewritten).
- Existing Manufacturing module contract: `apps/Manufacturing/AGENTS.md` (ownership of module surfaces, routes, module-owned reports, widget contributions); preserved.
- Module contract: `docs/architecture/MODULE-CONTRACT.md`; APP-CONTRACT.md — shared foundation must declare its own capability/manifest when added.
- Shared Items must declare `manifest.php` (or equivalent) when implemented; must not hardcode Manufacturing business semantics.
- No new architecture gate required for Session A audit/contract phase; future implementation gate should verify: no Manufacturing-specific fields in shared schema; reference pattern present; no duplicate identity created; consumer extension separate.

---

## 8. One Minimal Implementation Slice (Proposed — Not Executed)

Given the rules: "one minimal Shared Items implementation slice" — the smallest coherent foundation implementation that proves the contract without manufacturing extension:

1. `docs/shared-items-foundation/` (this directory) containing this contract + minimal schema proposal.
2. Reference mapping document showing `Item 123 → Manufacturing Product` by `item_ref`, with Manufacturing fields explicitly listed as external.
3. Gate/proof document (read-only) asserting: no Manufacturing-specific fields in proposed schema; extension reference pattern defined; no duplicate identity created.

Not executed: no `CREATE TABLE shared_items`; no Manufacturing migration; no Manufacturing data change; no consumer extension build; no suite-extension work.

---

## 9. Dependencies on A/B/C/D

- Session A (this): Independent start — defines Item identity.
- Session B (Shared Parties): Independent start — defines Party; Item may reference Party (`produced_by_party_ref`) — but minimal contract does NOT include that; keep abstract via `metadata_ref` until Session B finalizes.
- Session C (Shared Inventory): Depends on A — inventory tracks `item_id` (must exist). Session C must use abstract `item_ref` until A finalizes.
- Session D (Shared Commercial): Depends on A + B + C — commercial transactions reference Item and Party; must not invent universal transaction master prematurely.

---

## 10. Hard Rules Preserved (From Common Rules + Session A Objective)

- Only inside Shared Foundation domain — audit and contract only; no Manufacturing module mutation.
- No suite-specific extension implemented (no Manufacturing Product extension, no label-template build, no module route, no dashboard).
- No duplicate Item / Part / Material / Product identity created.
- Shared Items must be independently useful (not tied to Manufacturing).
- Reference session-d contract; do not rewrite.
- No deployment; no CI change; no server mutation; no `.env`; no secret; no merge to `main`.
