# Parties Data Model — Canonical Data Owner v1 (Slice 4 Skeleton)

Status: Landed v1 skeleton. No consumer migration. No domain-role migration. No UI.

Builds on: `business-app-module-ownership-contract.md` (Shared App contract), `susankhya-productization-roadmap.md`, Slice 2 tenant/company distinction, Slice 3 identity investigation.

## Canonical Parties Owns

**Table `parties` (DB-wide, instance scope via `storage/db_config.php`):**

- `id` BIGINT PK
- `party_type` VARCHAR(20) NULL CHECK `person|organization` — classification hint, NULL = not yet classified (uncertainty-safe, per Slice 4 correction). No workflow depends on it.
- `display_name` VARCHAR(190) NOT NULL — canonical human-readable label, **not** a uniqueness key.
- `email` VARCHAR(190) NULL — canonical contact attribute, nullable, non-unique, **not** an identity key.
- `phone` VARCHAR(80) NULL — canonical contact attribute, nullable, non-unique, **not** an identity key.
- `created_at`, `updated_at` TIMESTAMP

**Explicitly not in v1:** `company_id`, `branch_id`, `tenant_id`, generic `role`, `Customer/Guest/Supplier` enum, `status`, `note`, `id_document_ref`, `supplier_code`, `contact_name`, tax/legal, address framework, merge fields, source-app provenance.

**Semantics:** `display_name` is label only; `email`/`phone` are contact attributes; `party_type` is hint. No deduplication constraints.

## Hospitality Owns

- Guest semantics, `guest_status`, `id_document_ref` (sensitive PII), reservation/stay relationship (`hosp_reservations.guest_id`), Hospitality-specific notes/data.
- Future: `hosp_guest_roles(party_id FK→parties, guest_status, id_document_ref, ...)` — not in this slice.
- Current `hosp_guests` remains sole source until migration; no `party_id` column yet.

## SBAIO Owns

- Customer semantics, relationship `contact_name`, Customer `status`, future sales-specific properties.
- Future: `sbaio_customer_roles(party_id FK→parties, contact_name, status, ...)`
- Current `sbaio_customers` remains sole source.

## Procurement Owns

- Supplier semantics, `supplier_code`, relationship `contact_name`, `supplier_status`, procurement-specific properties.
- Future: `procurement_supplier_roles(party_id FK→parties, supplier_code, contact_name, status, ...)`
- Current `procurement_suppliers` remains sole source.

## What v1 Does Not Do

- No automatic dedupe; no `UNIQUE(email)` or `UNIQUE(phone)`; migration will be `NO_AUTOMATIC_DEDUPE` (preserve IDs, one Party per existing row).
- No Company/Branch on Party v1 (canonical identity is DB-wide; Slice 2: Tenant = DB, Company = organizational inside DB, scope is entity-specific).
- No domain-role enum in Parties (Guest/Customer/Supplier stay domain-owned).
- No generic Party `status` yet (domain-role status remains domain-owned).
- No generic Party `note` yet (domain notes stay domain-owned).
- No standalone Parties UI (`/apps/parties` has no worker page; `routes.php` is inert entry).
- No consumer migration (`hosp_guests`/`sbaio_customers`/`procurement_suppliers` untouched, no `party_id` FK added).

## Service Boundary

`Apps\Parties\Services\PartyService` — canonical owner only:

- `create(display_name*, party_type? person|organization|null, email?, phone?)` — validates `display_name` non-blank, `party_type` only `person|organization|null`, normalizes blank email/phone to NULL, inserts one Party, **never searches-and-reuses**. No `findOrCreate()`.
- `getById(id)` — canonical ID lookup.
- `searchCandidates(display_name?, email?, phone?, limit)` — read-only OR search for possible reuse, exact/partial display_name LIKE, exact email/phone, ordered by `updated_at DESC`, **never auto-merge**.
- `updateCanonical(id, display_name?, party_type?, email?, phone?)` — canonical fields only, no role/domain writes.

Consuming Apps never write `parties` directly; they call service. Domain services own role tables and reference `party_id` later.

## Migration Compatibility (Not Executed)

Future incremental sequence (preserved for next slice, not run now):

1. Add `party_id` nullable FK to role tables (additive).
2. Compat view `v_*_compat` keeps old reads working.
3. Backfill `party_id` via `INSERT INTO parties(...) SELECT ...` + `UPDATE ... SET party_id`.
4. Switch reads to `JOIN parties`.
5. Verification + rollback via `DROP COLUMN party_id` + `DELETE FROM parties`.

Requires `mysqldump` + filesystem ZIP per `engineering/Platform/decisions.md` before step 2. Not executed in this slice — repo now contains migration/runtime code only for new `parties` table; no existing table altered.

## Validation

- `check_shared_app_extension_contract.sh` validates Parties as valid Shared App (`kind shared_app`, `consumers` hospitality/sbaio/procurement, no App-level scope fields, `type business`, no `extensions/`).
- No `company_id`/`branch_id`/`tenant_id` on `parties` (checked by Parties v1 contract).
- No `findOrCreate` in `PartyService.php` (grep `findOrCreate` must be 0).
- `party_type` nullable check `CHECK (party_type IS NULL OR IN ('person','organization'))` in migration.

## Non-Goals Reaffirmed

No CRM, loyalty, marketing consent, payment identities, accounting parties, global cross-tenant identity, cloud sync, fuzzy automated merge engine, multi-address framework, tax/legal registration, Employee/User migration, Shared Billing/Items, Procurement promotion, runtime extension loader.

## Next Work

Consumer adoption (adding `party_id` to one domain role) is **not** this slice. Next investigation is consumer-role adoption design, not Parties table expansion.
