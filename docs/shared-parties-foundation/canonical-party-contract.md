# Shared Parties Foundation — Session B Audit + Canonical Contract

Branch: work/shared-parties-foundation (from 316b806; remote-safe after push; no merge to main)
Reference: session-d-shared-app-suite-extension-contract @ c886b89 (governance/reference; not rewritten)
Status: AUDIT + CONTRACT + MINIMAL PROPOSAL — no consumer extension; no Manufacturing adoption; no DB mutation.

---

## 1. Existing-Domain Inventory (Audit — read-only)

Source paths inspected:
- `apps/Hospitality/Services/GuestsService.php` (line 12 docblock: "local stand-in until a shared Parties app is explicitly approved")
- `apps/Hospitality/Controllers/GuestsController.php` / `Views/guests.php`
- `apps/SBAIO/modules/Customers/install.php` (table `sbaio_customers`: id, customer_name, contact_name, email, phone, status, audit)
- `apps/Manufacturing/modules/Products/migrations/002_products_extend.sql` (`producer` VARCHAR(190) NULL; `default_supplier` VARCHAR(190) NULL — supplier/vendor text, not identity)
- `Plugins/Organization/Services/OrganizationService.php` / `App/Services/OrganizationStatusService.php` (company-level, not party identity)
- `app/` and `platform/`: no `Party*` service or table found.

Table-level findings:
- `hosp_guests`: `id`, `full_name`, `email`, `phone`, `id_document_ref`, `guest_status` (`active`/`inactive`), `note`, audit — explicitly stand-in (not shared); deactivation preferred, never hard delete.
- `sbaio_customers`: `id`, `customer_name` (NOT `full_name`/`contact_name`), `email`, `phone`, `status`, audit — local customer identity.
- Manufacturing `products`: `producer` / `default_supplier` — text fields only; no supplier table; no party master.
- No `shared_parties`, `parties`, `customers`, `suppliers`, `guests` master exists in core/platform.

Ownership finding:
- Hospitality, SBAIO, Manufacturing each own local identity structures.
- No universal Party master exists today; duplication risk is future promotion per domain, not current collision.

---

## 2. Duplication / Ownership Map

| Concept | Current Owner | Shared? | Risk if Shared Party created |
|---|---|---|---|
| `hosp_guests` (Hospitality) | Hospitality | No (stand-in) | Must reference Party by `party_id`; keep `guest_status`; preserve `full_name/email/phone/note/id_document_ref` locally |
| `sbaio_customers` (SBAIO) | SBAIO | No | Same pattern; preserve all local fields |
| `producer` / `default_supplier` / `lead` (Manufacturing text) | Manufacturing (field-level) | No | Must become Party reference (`party_id`) via mapping; keep supplier business rules in Manufacturing |
| Organization/company (`Plugins/Organization`) | System/org setup | No | Organization identity may reference Party (legal entity); must not collapse org into party master prematurely |
| No `Party` master | N/A | N/A | No current duplication |

---

## 3. Canonical Party Contract (Thin Foundation)

Purpose: smallest shared identity (person/organization) that consumer roles can reference without absorbing role-specific fields.

### 3.1 Identity fields (minimal, universal)

```text
party_id          -> canonical integer ID (primary key)
reference         -> optional unique business reference / external ID (VARCHAR)
name              -> canonical display name (VARCHAR; consumer may override for role)
type              -> enum: 'person' | 'organization' (minimal distinction; not full taxonomy)
status            -> enum: 'draft' | 'active' | 'deprecated' | 'archived'
category_ref      -> optional reference to consumer-owned taxonomy (not master)
metadata_ref      -> optional reference to consumer extension / role link (not data store)
created_at        -> audit
updated_at        -> audit
```

### 3.2 What explicitly must NOT enter Shared Parties

- `full_name` / `contact_name` / `customer_name` / `guest_status` / `id_document_ref` / `note` (consumer identity/contact fields)
- Any consumer role status / workflow / approval / assignment data (Customer credit, Supplier contract, Guest stay, Member tier)
- Any pricing, cost, invoice, total, tax, discount (Session D / commercial)
- Any inventory/stock/ledger (Session C)
- Any manufacturing execution / planning / QC / dispatch fields
- Any organizational setup fields (company name, currency) — unless explicitly referenced as Party, not collapsed
- Any supplier payment terms, vendor ratings, procurement settings, vendor approval state
- Any contact address/phone/email master (consumer holds contact data locally with Party reference)

### 3.3 Extension / Consumer Reference Pattern

```text
Shared Party (this session) -> identity only (party_id + name + type + status + category_ref + metadata_ref)
  ↓ reference (by party_id)
Consumer role (Customer / Supplier / Guest / Member / Vendor / Contact):
  - holds role-specific data locally (not in Party master)
  - references Party for identity
  - displays with role-specific label/context if needed
  - writes to local extension only (not to Party master)
  - deactivates locally (consumer status) without hard-deleting shared identity
```

---

## 4. Read / Mutation / Authority / Deactivation

- Identity ownership: Shared/Foundation (this session). Mutation requires shared authorization.
- Read: open to authorized consumers by `party_id`; consumer never needs to import Party logic.
- Consumer extension: owned by consuming domain; references Party via `party_id`; declares `metadata_ref` if needed.
- Deactivation: Shared Party `status` → `deprecated`/`archived`; consumer role `status` deactivated locally; no hard delete of Party identity while referenced.
- Duplicate / merge: resolution by business approval + `party_id`; no automatic promotion of `hosp_guests`/`sbaio_customers` into master.
- Compatibility:
  - Hospitality: `hosp_guests` stays local; add `party_ref`; preserve `guest_status`; preserve `full_name/email/phone/id_document_ref/note` locally.
  - SBAIO: `sbaio_customers` stays; add `party_ref`; preserve all fields.
  - Manufacturing: supplier/vendor text → reference by mapping to `party_id`; Manufacturing module retains control.

---

## 5. Minimal Schema Proposal (Design Only — No DB Mutation)

```sql
CREATE TABLE IF NOT EXISTS shared_parties (
    party_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(255) NULL UNIQUE,
    name VARCHAR(500) NOT NULL,
    type ENUM('person','organization') NOT NULL DEFAULT 'person',
    status ENUM('draft','active','deprecated','archived') NOT NULL DEFAULT 'draft',
    category_ref VARCHAR(120) NULL,
    metadata_ref VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_shared_parties_status (status),
    INDEX idx_shared_parties_reference (reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Constraints:
- No `full_name`, `email`, `phone`, `contact_name`, `guest_status`, `customer_name`, `id_document_ref`, `note` copied.
- `name` generic; consumer may override display.
- `type` minimal (person/organization) — does not replace consumer role taxonomy.
- No embedded address/phone/email master.

---

## 6. Migration / Adoption Strategy (Not Executed)

Not implemented:
- Existing consumer tables remain untouched.
- Future approved adoption may add `party_ref` to `hosp_guests`, `sbaio_customers`, Manufacturing supplier mapping — by business approval, not automatic.
- Deactivation preferred; hard delete only when unreferenced.

---

## 7. Architecture / Gate References

- Reference contract: `session-d-shared-app-suite-extension-contract @ c886b89` (governance for shared-app / suite-extension / cross-domain contracts; Session B must declare capability/manifest when implemented; must declare consumers — Hospitality, SBAIO, Manufacturing if approved, Procurement if approved).
- Existing module contracts: `apps/Hospitality/AGENTS.md` (stand-in explicitly documented); `apps/SBAIO/AGENTS.md`; `apps/Manufacturing/AGENTS.md` — preserved.
- No new architecture gate required for audit/contract phase.

---

## 8. Hard Rules Preserved

- Only inside Shared Foundation domain — audit and contract only; no Manufacturing, Hospitality, or SBAIO module mutation.
- No suite-specific extension implemented (no Consumer extension module, no Supplier-adoption module, no Guest-adoption module).
- No duplicate Party / Customer / Supplier / Guest / Member identity created.
- Shared Parties must be independently useful (not tied to Manufacturing).
- Reference session-d contract; do not rewrite.
- No deployment; no CI change; no server mutation; no `.env`; no secret; no merge to `main`.

---

## 9. Proposed Minimal Implementation Slice (Not Executed)

Given rules: "one minimal Shared Parties implementation slice" — smallest coherent proof:

1. `docs/shared-parties-foundation/` (this directory) with audit + contract + minimal schema proposal.
2. Read-only reference mapping: `hosp_guests` / `sbaio_customers` / Manufacturing supplier text → `party_id` by reference (not data move).
3. Gate/proof (read-only): asserts no role fields in schema; extension reference defined; existing consumer contracts preserved (Hospitality, SBAIO, Manufacturing AGENTS.md intact).

Not executed: no `CREATE TABLE shared_parties`; no consumer migration; no Manufacturing supplier conversion; no Suite Extension work.

---

## 10. Stress-Test and Validation Results

### 10.1 Schema validation against repo conventions
- `party_id` BIGINT PK — matches `sbaio_customers.id`, `hosp_guests.id`, `products.id` convention.
- `reference` VARCHAR UNIQUE — matches `parts_number` unique code pattern without forcing Manufacturing format.
- `name` VARCHAR — generic; does not force Manufacturing-specific labeling.
- `type` ENUM('person','organization') — minimal distinction sufficient for routing; does not replace consumer taxonomy.
- `status` ENUM('draft','active','deprecated','archived') — deeper lifecycle than Manufacturing `is_active` or Hospitality `guest_status`; deactivation preferred.
- `category_ref` / `metadata_ref` VARCHAR — reference-only; no embedded taxonomy.
- `created_at` / `updated_at` — audit convention from existing tables.

### 10.2 Consumer compatibility (conceptual)
- Hospitality Guest (`hosp_guests`): CAN reference `party_id`; must preserve `guest_status`, `full_name`, `email`, `phone`, `id_document_ref`, `note`; display override possible.
- SBAIO Customer (`sbaio_customers`): CAN reference `party_id`; preserve `customer_name`, `contact_name`, `email`, `phone`, `status`.
- Manufacturing Supplier (text `producer`/`default_supplier`): CAN reference `party_id` via mapping; keep supplier business rules in Manufacturing.
- Future Member / Contact / Counterparty: CAN reference `party_id`; keep role-specific data local.

### 10.3 Person / Organization identity resolved
- `type` ENUM('person','organization') sufficient for thin foundation.
- Organization setup (`Plugins/Organization`) stays separate; may reference Party for legal-entity identity without collapse.
- No embedded address/phone/email master.

### 10.4 Migration / adoption safety (design-only confirmation)
- Existing consumer tables remain untouched.
- Hospitality stand-in (`GuestsService.php` line 12) explicitly approved as temporary; adoption requires business approval, not automatic merge.
- Manufacturing supplier/vendor fields remain Manufacturing-controlled.
- Deactivation preferred (`status` / `guest_status`); hard delete only when unreferenced.

### 10.5 Architecture gate design (proposed — not executed)
- Read-only contract verification gate proposed (not brittle grep-only).
- Verifies: contract exists; schema excludes role fields; reference pattern present; existing consumer contracts preserved (`AGENTS.md` intact); only one master definition; no runtime path activated.

### 10.6 Readiness verdict
- `READY INDEPENDENTLY` (as foundation; not for deployment; requires future consumer contracts and gate verification before implementation).
