# Hospitality App Foundation

Status: Foundation complete (slices 1-8 + integration pass, 2026-08-24). This brief is
the historical implementation sequence; current state and next milestone live in
`docs/architecture/hospitality-foundation-release-readiness.md` and
`engineering/Hospitality/work.md`.

Owner: Architecture / Product planning -> next: Hospitality App owner

Created: 2026-08-23

Builds on:

- `docs/active/hospitality-readiness.md` (completed readiness gate)
- `docs/architecture/hospitality-readiness-audit.md` (ownership classification, legacy wording, loader evidence)
- `docs/architecture/app-ownership-classification-and-hospitality-readiness.md` (planning baseline)
- `docs/discussions/hospitality-suite-preimplementation-notes.md` (discussion note)
- `ARCHITECTURE.md`, `docs/architecture/SUSANKHYA-OS-ARCHITECTURE-CHARTER-V1.md`
- `docs/architecture/APP-CONTRACT.md`, `docs/architecture/MODULE-CONTRACT.md`

## 1. Objective

Define the first Hospitality App skeleton as a Domain App with local-first scope:

```text
apps/Hospitality/
  manifest.json
  bootstrap.php
  routes.php
  navigation.php
  migrations/
  modules/
    Rooms/
    Guests/
    Reservations/
    FrontDesk/
    Housekeeping/
```

The first coding task delivers an installable, disable-able app with its five modules at
MODULE-CONTRACT Level 1 (UI Shell) minimum, with schema in place for Level 2 (CRUD) growth.
No business polish, no dashboards beyond a minimal landing surface, no cross-domain wiring.

## 2. Architecture Laws Involved

From the Charter and contracts:

1. Core is locked. No Core change for registration, loading, or schema.
2. Shell is generic. Apps contribute; Shell composes. No Hospitality chrome in Shell.
3. Apps own business logic. All Hospitality domain meaning lives under `apps/Hospitality`.
4. One feature = one source. No duplicate definitions across modules.
5. Modules belong to exactly one parent app and register through the app.
6. All business routes remain under `/apps/hospitality/...`.
7. Runtime consumes owner-declared contracts; no hidden duplicate source of truth.
8. Suite is product/composition language only ("Hospitality Suite"). App is runtime truth.
9. Studio does not generate or own Hospitality runtime truth.

## 3. Owner and Source Areas

Runtime owner once created:

```text
Owner layer:  Hospitality App
Code root:    apps/Hospitality
Route space:  /apps/hospitality/...
Workspace:    engineering/Hospitality/ (created with the first coding slice)
```

Planning source areas for implementers:

| Area | Use |
|---|---|
| `apps/Manufacturing`, `apps/SBAIO`, `apps/Procurement` | Reference only for manifest/bootstrap/routes/navigation conventions |
| `app/Core/PackageManager.php`, `PluginManager.php`, `SidebarBuilder.php`, `RouteRuntimeAuthority.php` | Read-only loader behavior (globs `apps/*/modules*`) - do not modify |
| `docs/architecture/MODULE-CONTRACT.md` | Module types, maturity levels, capability declarations |
| `docs/architecture/business-app-module-ownership-contract.md` | Minimal ownership declaration fields |

## 4. Confirmed Runtime Identity

```text
Product name:  Hospitality Suite      (product/composition language)
Runtime owner: Hospitality App        (installable unit)
App key:       hospitality
Code location: apps/Hospitality
Route prefix:  /apps/hospitality
Table prefix:  hosp_
Permissions:   hospitality.view, hospitality.manage
```

No `suites/` directory, registry, manifest, or loader is created. The word "Suite"
appears only in product-facing labels where appropriate.

## 5. v1 Module List

Five modules, each owned solely by the Hospitality App:

| Module folder | Module key | Type | Target maturity (first slice) | Owns |
|---|---|---|---|---|
| `Rooms` | rooms | business_entity | L2 | Room inventory and room attributes |
| `Guests` | guests | business_entity | L2 | Local guest records |
| `Reservations` | reservations | planning | L2 (L3 later) | Booking lifecycle and status transitions |
| `FrontDesk` | front_desk | process_execution | L1 first, L3 target | Check-in / check-out flow and stay operations incl. folio |
| `Housekeeping` | housekeeping | service_only first | L1 | Current housekeeping status per room |

Capability declarations per module follow MODULE-CONTRACT (`declared_capabilities`).
Missing capabilities in the first slice must be intentional and listed.

## 6. Folio Decision

Decision: basic folio/charges lives **inside FrontDesk** for v1, not as its own module.

Why:

1. In v1 a folio has no workflow independent of the stay: charges are attached to a
   reservation/stay and totaled at check-out. There is no separate screen set, no
   payments integration, no posting/export. A standalone module would be a folder
   without an owned capability surface.
2. Fewer top-level units keeps the first skeleton small and honest about maturity
   (FrontDesk L3 target already covers queue/workboard semantics that folio needs).
3. It makes the local-first stance explicit: folio-in-FrontDesk cannot be mistaken for
   the future shared Billing app, matching the audit rule that local stand-ins must not
   masquerade as shared apps.

Migration safety clause (mandatory): the data model still keeps folio in its own tables
(`hosp_folios`, `hosp_folio_charges`) keyed to reservations, never smeared across stay
columns. A later promotion to a Billing-shaped structure is then a clean extraction of
two tables plus routes, not a rewrite. If folio ever graduates to a module, it takes its
tables and `/front-desk/folio` surfaces with it.

## 7. Minimum Data Model

Six tables, all `hosp_` prefixed, all owned by Hospitality migrations. Lean columns only;
additions come later per module maturity.

```text
hosp_rooms
  id PK, room_number UNIQUE, room_type VARCHAR (enum-ish text for v1), floor INT NULL,
  notes TEXT NULL, created_at, updated_at

hosp_guests
  id PK, full_name, email NULL, phone NULL, id_document_ref NULL,
  notes TEXT NULL, created_at, updated_at

hosp_reservations
  id PK, guest_id FK->hosp_guests, room_id FK->hosp_rooms NULL (unassigned allowed),
  check_in_date DATE, check_out_date DATE, adults TINYINT, children TINYINT DEFAULT 0,
  rate DECIMAL NULL, status ENUM(booked,checked_in,checked_out,cancelled,no_show),
  source VARCHAR NULL, notes TEXT NULL,
  actual_check_in_at DATETIME NULL, actual_check_out_at DATETIME NULL,
  created_at, updated_at

hosp_housekeeping_status
  id PK, room_id FK->hosp_rooms UNIQUE,
  status ENUM(clean,dirty,inspected,maintenance,out_of_service),
  last_cleaned_at DATETIME NULL, notes TEXT NULL, updated_at

hosp_folios
  id PK, reservation_id FK->hosp_reservations UNIQUE,
  status ENUM(open,closed), opened_at, closed_at NULL

hosp_folio_charges
  id PK, folio_id FK->hosp_folios, charge_type ENUM(room_rate,fnb,misc),
  description VARCHAR, qty INT DEFAULT 1, unit_amount DECIMAL,
  posted_at DATETIME, created_by VARCHAR NULL
```

Deliberate choices:

- Stays/check-in/check-out are lifecycle fields on `hosp_reservations`
  (`status` + `actual_*_at`), not a separate stays table. v1 has one stay per
  reservation; a separate table would add joins without new information. Revisit only if
  multi-room or split stays become real requirements.
- Housekeeping keeps current status only (one row per room). History/log tables deferred.
- Folio totals are computed on read; nothing cached.
- No shared-app foreign keys. Nothing references Billing/Items/Inventory/Parties/
  Accounting/Procurement because none exist as shared apps.

## 8. Proposed Routes

Minimal, canonical, app-owned, kebab-case per ROUTING-STANDARD conventions:

| Route | Module | Notes |
|---|---|---|
| `/apps/hospitality` | app home | Minimal landing/dashboard surface |
| `/apps/hospitality/rooms` | rooms | List + CRUD |
| `/apps/hospitality/guests` | guests | List + CRUD |
| `/apps/hospitality/reservations` | reservations | List + create/cancel + status view |
| `/apps/hospitality/front-desk` | front_desk | Arrivals/stays workboard; check-in/out actions; per-stay folio view |
| `/apps/hospitality/housekeeping` | housekeeping | Per-room status board + status update |

All six are `kind: canonical`, lifecycle-bound, nav_visible, search_visible in the
manifest `runtime_contract.routes`. No compatibility aliases in v1. No route outside the
app namespace.

## 9. Manifest / Metadata Pattern

App manifest follows existing app conventions (`SBAIO` shape) with the legacy-but-
consistent packaging vocabulary documented in the readiness audit:

```json
{
  "id": "hospitality",
  "app_key": "hospitality",
  "package_type": "bundle",
  "name": "Hospitality",
  "version": "0.1.0",
  "type": "business",
  "min_core_version": "3.0.0",
  "dependencies": [],
  "entry": "routes.php",
  "migrations_path": "migrations",
  "permissions": ["hospitality.view", "hospitality.manage"],
  "modules": [
    { "key": "rooms", "name": "Rooms" },
    { "key": "guests", "name": "Guests" },
    { "key": "reservations", "name": "Reservations" },
    { "key": "front_desk", "name": "Front Desk" },
    { "key": "housekeeping", "name": "Housekeeping" }
  ],
  "runtime_contract": {
    "routes": [ "...six canonical routes..." ]
  }
}
```

Module-level metadata: each module declares MODULE-CONTRACT fields
(`package_type: module`, `owner_app: hospitality`, `module_key`, `module_type`,
`target_maturity_level`, `declared_capabilities`). The `ownership_type` field from the
readiness audit is reserved for future extension-shaped modules (e.g. a local Items
stand-in labeled `ownership_type: app_extension`). No v1 module needs it because all
five are plain Hospitality-owned modules and folio lives inside FrontDesk. If any future
local stand-in module appears, it must carry explicit ownership metadata per audit
section 6.

Navigation contributions follow the app-owned pattern (`navigation.php` per app/module,
discovered via existing globs). Styles: single `styles/hospitality.css` app-scoped entry;
no Shell CSS edits.

## 10. Non-Goals

This brief, and the coding task it defines, do not include:

- Any Core change (registration must work through existing loaders untouched)
- Any `suites/` loader, registry, manifest, or directory
- Any real `extensions/` directory (documentation-level convention only)
- Shared Billing, Items, Inventory, Parties, Accounting, or shared Procurement
- Any dependency on `apps/Procurement`
- Studio generation of Hospitality or any new Studio tool behavior
- Payments, invoicing, tax engines, channel managers, pricing engines
- Multi-property/multi-hotel scoping
- Compatibility route aliases
- Polishing Manufacturing/SBAIO/Procurement

## 11. Implementation Sequence (Next Coding Task)

Ordered slices, each independently verifiable. Slice progress is tracked in
`engineering/Hospitality/work.md`.

1. App skeleton - create `apps/Hospitality/` with `manifest.json`, `bootstrap.php`,
   `routes.php`, `navigation.php`, empty `migrations/`;
   verify the app registers/enables/disables via existing AppManager without code change.
   **Status: completed 2026-08-23** (probe `apps/Hospitality/Tests/probe_slice1_registration.php`
   24/24; styles entry intentionally deferred until first real styling slice to avoid
   registering unpublished assets).
2. Schema slice - migrations creating the six `hosp_` tables (section 7); verify
   install/uninstall round-trip leaves schema clean.
   **Status: completed 2026-08-23** (probe `apps/Hospitality/Tests/probe_slice2_migrations.php`
   21/21; applied via real `AppMigrationService`, idempotent, cleanup restores state).
3. Module scaffolding - five module folders with module metadata and navigation
   contributions; verify nav appears under the app group only.
   **Status: completed 2026-08-23** (probe `apps/Hospitality/Tests/probe_slice3_modules.php`
   88/88; five plugin.json + manifest declarations; module-level navigation contributions
   deferred to slice 4 when module routes exist - app-level navigation already in place).
4. Routes + controllers - six canonical routes with server-side permission checks
   (`hospitality.view` read, `hospitality.manage` write); minimal L1 index views.
   **Status: completed 2026-08-23** (probe `apps/Hospitality/Tests/probe_slice4_routes_navigation.php`
   27/27; all six GET routes register via real loader, guarded by app access plus
   `hospitality.view`; `hospitality.manage` reserved for slice 5+ write surfaces).
5. Rooms + Guests CRUD (L2) - forms, validation, permissions, localization keys
   (en/ja/ne) per UI localization rules.
   **Status: completed 2026-08-23** (probe `probe_slice5_rooms_guests_crud.php` 29/29;
   soft-deactivation via status transitions; guest_status added additively in migration
   0002; unique room_number enforced on create and update).
6. Reservations lifecycle (L2) - create/cancel/status transitions with audit-friendly
   timestamps.
   **Note: Slice 6 must follow `docs/architecture/shared-app-extension-readiness.md`** -
   in particular the shared-app debt avoidance rules (section 7): no cross-app reads or
   forks, guests/folio stay visibly local with clean extraction seams, no entity
   promotion toward Parties/Billing.
   **Status: completed 2026-08-23 following the readiness audit** (probe
   `probe_slice6_reservations_lifecycle.php` 27/27; status machine booked -> checked_in ->
   checked_out plus cancelled/no_show; actual timestamps on existing lifecycle fields;
   overlap conflicts rejected; no schema additions needed).
7. FrontDesk check-in/check-out + folio (L1 -> L3 path) - arrivals workboard, status
   transitions writing `actual_*_at`, folio open/add-charge/close-at-checkout.
   **Status: completed 2026-08-23** (probe `probe_slice7_frontdesk_folio.php` 26/26;
   lifecycle delegated to ReservationsService; local folio extraction-safe: separate
   tables, computed totals, no payments/invoices; visibly-local labeling).
8. Housekeeping board (L1/L2) - current status update per room.
   **Status: completed 2026-08-23** (probe `probe_slice8_housekeeping_board.php` 18/18;
   current-status board over active rooms, lazy status-row creation, clean/dirty/
   inspected/maintenance/out_of_service updates, no history/supply/work-order/staff
   workflow, no shared-app dependencies).
9. Gate pass - run business app/module contract gates, asset/CSS ownership checks,
   operator confinement (no operator links yet), `git diff --check`.
   **Status: completed 2026-08-24.** Foundation integration pass finished: slices 1-8
   verified through the aggregate probe runner (`run_all_hospitality_probes.php`,
   8/8 groups, 267 assertions), focused gates PASS (core lock, business app/module
   contracts, `git diff --check`), integration fixes recorded in
   `engineering/Hospitality/work.md`, readiness record at
   `docs/architecture/hospitality-foundation-release-readiness.md`.

**Foundation status: complete.** The next milestone is separate from Foundation; see
the release-readiness record's recommendation (operator/workspace composition planning).

Each slice updates `engineering/Hospitality/work.md` when the workspace is created.

## 12. Validation Plan

For this brief (current task):

- Docs-only diff confirmed; `git diff --check` clean.
- No runtime tests required; no code changed here.

For the future coding task (each slice):

- App/module ownership gates (`check_business_app_module_contracts.sh`) after skeleton
  and module slices.
- Manifest checks: JSON validity, required fields, module declarations match folders.
- Route checks: all routes inside `/apps/hospitality/*`; registered in manifest
  `runtime_contract.routes`; no duplicates anywhere in the repo.
- CSS ownership: `styles/hospitality.css` published via the standard publisher; no
  Hospitality selectors in Shell CSS.
- Localization self-check before commit (no hardcoded labels).
- Full architecture gates (`scripts/architecture/run_architecture_gates.sh`) only if a
  slice proposes architecture-relevant runtime changes (e.g., anything touching shared
  contribution contracts). Pure app-local CRUD slices need the focused gates above.
- Core lock gate implicitly green: zero files under `/app` changed.

## Open Items Carried From Readiness

- Whether a future shared Parties app should absorb guests (evaluation trigger: second
  domain needing guest/customer identity).
- Whether Procurement promotion to Shared App happens before any Hospitality purchasing
  need (v1 has none).
- Whether `extensions/` ever earns loader support (not needed for v1).
