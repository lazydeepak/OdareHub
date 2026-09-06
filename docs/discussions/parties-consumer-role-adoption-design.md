# Parties Consumer-Role Adoption Investigation

Status: Investigation only — NOT APPROVED FOR IMPLEMENTATION

Date: 2026-08-27

This note is a discussion/planning artifact (per `docs/discussions/README.md`). It records
tentative design questions and the current decision boundary for a future Hospitality→Parties
consumer-role adoption. It is **not** an architecture contract and **does not authorize**:
any consumer migration, a Hospitality dependency on Parties, adding `party_id` anywhere, any
migration/backfill, any compatibility view, any role table, any SBAIO/Procurement adoption, or
any change to the current architecture gates.

## Status

- No consumer migration is authorized.
- Hospitality must not depend on Parties yet (see `apps/Hospitality/AGENTS.md`).
- `party_id` must not be added to Hospitality yet.
- The current Parties architecture gate (`scripts/architecture/check_parties_contract.sh`)
  remains authoritative and deliberately rejects premature `party_id` outside Parties.
- This document records unresolved design questions only.

## Current Ground Truth

Confirmed from the repository at `main` (3076301f).

### Parties (canonical owner)

- `apps/Parties/manifest.json` — Shared App v0.1.0, `shared_contract.consumers = [hospitality, sbaio, procurement]`, empty `dependencies`.
- Location: `apps/Parties/`. No `apps/Parties/AGENTS.md` exists.
- Service surface (`apps/Parties/Services/PartyService.php`): `create(display_name*, party_type? person|organization|null, email?, phone?)`, `getById(id)`, `searchCandidates(display_name?, email?, phone?, limit)` (read-only OR search, no auto-merge), `updateCanonical(id, display_name?, party_type?, email?, phone?)`. Explicitly **no `findOrCreate()`**.
- Canonical schema constraints (`apps/Parties/migrations/001_create_parties.sql` + `docs/architecture/parties-data-model.md`):
  - no `UNIQUE(email)` / `UNIQUE(phone)`; migration is `NO_AUTOMATIC_DEDUPE` (preserve IDs, one Party per existing row);
  - no company/branch on Party v1 (canonical identity is DB-wide; scoping is entity-specific);
  - no domain-role enum in Parties (Guest/Customer/Supplier stay domain-owned);
  - no generic Party `status` or `note` yet;
  - no consumer migration (`hosp_guests`/`sbaio_customers`/`procurement_suppliers` untouched, no `party_id` FK).
- `apps/Parties/routes.php` is an inert entry; no standalone Parties UI.

### Hospitality (consumer candidate)

- Hospitality-local guest ownership: `apps/Hospitality/modules/Guests/plugin.json` —
  "Hospitality-local guest records; local stand-in until a shared Parties app is approved",
  `required_tables: ["hosp_guests"]`.
- Current `hosp_guests` shape (`apps/Hospitality/migrations/20260823_0001_hospitality_core.sql`):
  `id`, `full_name NOT NULL`, `email NULL`, `phone NULL`, `id_document_ref NULL`, `note NULL`,
  `created_at`, `updated_at`, index on `email`. This is the identity-only seam referenced by the
  foundation brief.
- Lifecycle: `apps/Hospitality/migrations/20260823_0002_hospitality_guest_status.sql` adds
  `guest_status VARCHAR(30) NOT NULL DEFAULT 'active'` (soft status; no hard deletes).
- `apps/Hospitality/Services/GuestsService.php` — `listGuests`, `create`, `update`,
  `setStatus` (statuses `active|inactive`), `requireSchema`; validates name/email/phone/id_document_ref.
- Hospitality **does not currently consume Parties**: zero `party_id` matches in
  `apps/Hospitality/`, `apps/SBAIO/`, `apps/Procurement/` (asserted by the architecture gate and
  verified by repo grep).

### Dependency restriction

- `apps/Hospitality/AGENTS.md` (rule): "Do NOT depend on shared Billing, Items, Inventory,
  Parties, Accounting, or `apps/Procurement`. Local stand-ins must stay visibly local and must
  not masquerade as shared apps."

### Architecture gate

- `scripts/architecture/check_parties_contract.sh` contains the "No premature party_id outside
  Parties" group: it fails if `party_id` appears under `apps/Hospitality/`, `apps/SBAIO/`, or
  `apps/Procurement/`, and asserts `hosp_guests`/`sbaio_customers`/`procurement_suppliers`
  remain untouched. This gate is part of the aggregate architecture gate runner.

### Baseline checks (PASS at time of writing)

- `bash scripts/architecture/check_parties_contract.sh` — PASS
- `bash scripts/architecture/check_shared_app_extension_contract.sh` — PASS
- `php apps/Parties/Tests/probe_parties_service.php` — 28/28 PASS

## Already-Documented Future Direction

Only concepts already present in repository docs are listed. Each is classified per its actual
documented status — nothing here is approved by this note.

| Concept | Where documented | Classification |
|---|---|---|
| Canonical Party identity (DB-wide, neutral) | `docs/architecture/parties-data-model.md` (schema + service boundary) | DOCUMENTED DIRECTION |
| Consumer-owned role/projection table (future `*_roles(party_id FK→parties, ...)`) | `docs/architecture/parties-data-model.md` (future role-table sketches for hosp/sbaio/procurement) | DOCUMENTED DIRECTION (non-binding sketch, not a final schema) |
| Future Hospitality guest role relationship to canonical Party | `docs/architecture/parties-data-model.md`; `engineering/Hospitality/work.md` ("Extraction paths for Guests->Parties ... explicitly not authorized work") | PREREQUISITE / NOT AUTHORIZED |
| Compatibility boundary/view (`v_*_compat`) | `docs/architecture/parties-data-model.md` ("Migration Compatibility (Not Executed)") | DOCUMENTED PROPOSAL / NOT AUTHORIZED |
| Staged migration/backfill (`INSERT INTO parties SELECT ...` + `UPDATE ... SET party_id`) | `docs/architecture/parties-data-model.md` (§ Migration Compatibility (Not Executed)) | DOCUMENTED PROPOSAL / NOT AUTHORIZED |
| Second committed consumer requirement before Shared-App promotion | `docs/architecture/shared-app-extension-readiness.md` (§9 / §2); `docs/active/hospitality-readiness.md` (open question) | PREREQUISITE / OPEN DECISION |
| Orchestrated transition / write-path / cutover | Not described in the repository beyond the additive `party_id` FK sketch; no commitless dual-write exists in docs | INVESTIGATION ITEM |

## Ownership Boundary

The already-documented separation principle (from `docs/architecture/parties-data-model.md`
and `shared-app-extension-readiness.md`):

- Parties owns neutral canonical identity (display name + optional person/organization type +
  optional contact identifiers) and is DB-wide (no company/branch at v1).
- Hospitality owns Hospitality-specific role/state/lifecycle: `guest_status`, `id_document_ref`,
  `note`, reservation linkage — all local to `hosp_guests`.
- Company/branch/operational scope stays consumer-owned unless a later architecture decision
  explicitly moves it.
- `guest_status` and equivalent Hospitality lifecycle state must not be promoted into canonical
  Parties merely to support adoption (Parties stays domain-role-free at v1).

No final table schema is invented here. `docs/architecture/parties-data-model.md` contains
non-binding role-table sketches; that file itself labels them future and out of scope for its
slice.

## Open Decisions Before Implementation

These are unresolved questions consolidated from repository evidence (primarily
`docs/architecture/parties-data-model.md`, `docs/architecture/shared-app-extension-readiness.md`,
`docs/active/hospitality-readiness.md`, `docs/active/hospitality-app-foundation.md`,
`docs/active/hospitality-operator-composition-plan.md`, `engineering/Hospitality/work.md`).
None are resolved by this note.

- Explicit authorization of Hospitality as a real (first) consumer.
- Whether a second committed consumer is required before promotion
  (`shared-app-extension-readiness.md` states a shared app promotion requires an approved
  contract and a second real consuming domain or committed plan).
- Exact consumer-role ownership model and whether the role table is Hospitality-owned.
- Location of `party_id` when adoption is authorized (role table vs. identity table), and FK
  semantics (nullable? add-only? constraints?).
- Company/branch scoping of parties and role rows.
- Compatibility strategy (compat view shape; read-path keep-alive).
- Write-path transition (single-writer; no dual-write defined anywhere).
- Backfill strategy (sequencing; dedupe policy; `NO_AUTOMATIC_DEDUPE` preserved).
- Rollback criteria and procedure.
- Cutover conditions/ownership transition.
- Deletion/retention behavior (soft status vs. hard delete interplay with canonical parties).
- Canonical-vs-consumer field precedence (e.g., email/phone updated in both — which is source).

## Current No-Go Rules

Until authorization changes, autonomous implementation must NOT:

- Add `party_id` to Hospitality.
- Add a Hospitality → Parties runtime dependency.
- Change the current Parties gate to permit adoption.
- Add Hospitality-specific fields to Parties.
- Add a generic Party `status`.
- Add `company_id`/`branch_id` to canonical Parties merely for a consumer.
- Add `findOrCreate()`.
- Add global `UNIQUE(email)` or `UNIQUE(phone)`.
- Backfill Hospitality guest records into Parties.
- Delete or rename existing Hospitality guest identity columns.
- Implement compatibility views.
- Implement dual-write.

## Entry Conditions for a Future Code Slice

Unchecked checklist — nothing below is satisfied yet:

- [ ] consumer-adoption architecture explicitly approved
- [ ] Hospitality dependency rule intentionally updated
- [ ] current `party_id` gate intentionally superseded/replaced (not bypassed)
- [ ] consumer-role ownership contract defined
- [ ] scope semantics defined
- [ ] compatibility strategy defined
- [ ] migration/backfill sequence defined
- [ ] rollback criteria defined
- [ ] cutover criteria defined
- [ ] additional-consumer prerequisite resolved
- [ ] focused contract/probe expectations for the new boundary defined

## Smallest Human Decision Required

Before any code work can begin, the smallest decision needed is:

> Explicit approval of a Hospitality → Parties consumer-role adoption planning contract,
> including whether the second-real-consumer prerequisite is satisfied or intentionally waived.

Until that approval exists, the current architecture state (Parties canonical owner only;
Hospitality fully guest-local; gate rejecting premature `party_id`) remains correct and is
not changed by this note.