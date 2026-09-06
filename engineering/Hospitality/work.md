# Hospitality Work Log

## Slice 1 - App skeleton + registration proof (2026-08-23)

Status: Completed.

Created `apps/Hospitality/` skeleton:

- manifest.json (id hospitality, type business, v0.1.0, permissions hospitality.view/manage, one canonical route, modules [] until slice 3)
- routes.php (single GET /apps/hospitality), bootstrap.php placeholder
- Controllers/HospitalityHomeController.php + Views/home.php (minimal localized coming-soon surface)
- navigation.php (navigation.v1, single app-owned item)
- Resources/lang/{en,ja,ne}.php, migrations/.gitkeep, AGENTS.md

Validation:

- Registration probe `apps/Hospitality/Tests/probe_slice1_registration.php`: 24/24 pass.
  Proves manifest passes AppManifestService validation, syncLocalApps() registers the app
  in core_apps (status uploaded), enabling via AppRegistryService makes AppRuntimeLoader
  register GET /apps/hospitality, entry loaded once, all registry statuses restored.
- PHP lint: PASS on all created PHP files. Manifest JSON: valid.
- Core lock gate: PASS (zero /app changes). Business app/module contracts gate: PASS.
- git diff --check: clean. No loader/AppManager files modified.

Incident note: first probe run used a bare Container without the plugins binding, which made
the harness mark Manufacturing broken in core_apps; repaired via AppRegistryService::setStatus
back to enabled. Fixed probe now mirrors real boot bindings and snapshots/restores every status.

## Slice 2 - v1 data model migrations (2026-08-23)

Status: Completed.

Created `apps/Hospitality/migrations/20260823_0001_hospitality_core.sql` with the six
Hospitality-local tables from foundation brief section 7:

- `hosp_rooms` (room_number UNIQUE, room_type, floor, room_status, note)
- `hosp_guests` (full_name, email, phone, id_document_ref, note)
- `hosp_reservations` (guest_id/room_id logical links, planned dates, reservation_status,
  actual_check_in_at / actual_check_out_at lifecycle fields - no separate stays table)
- `hosp_housekeeping_status` (room_id UNIQUE, hk_status, last_cleaned_at, assigned_to)
- `hosp_folios` (reservation_id UNIQUE, folio_status, opened_at/closed_at)
- `hosp_folio_charges` (folio_id, charge_type, qty, unit_amount, charge_status posted/voided,
  posted_at)

Convention notes:

- Follows existing app migration conventions exactly: single date-named `.sql`
  (`20260823_0001_*`, Procurement style), additive-safe `CREATE TABLE IF NOT EXISTS`,
  VARCHAR(30) status columns with defaults instead of ENUM, TIMESTAMP created/updated
  pattern, indexed logical links, zero physical FOREIGN KEY constraints.
- Folio totals intentionally not stored; computed from charges at read time.

Validation:

- Migration probe `apps/Hospitality/Tests/probe_slice2_migrations.php`: 21/21 pass.
  Proves the real runner (`AppMigrationService::runMigrations`) applies the file, all six
  tables + key columns + unique index exist afterward, re-run is idempotent (0 newly
  applied), zero physical FKs, and cleanup restores pre-probe DB state (tables dropped,
  `core_app_migrations` rows for hospitality removed). Tables will be recreated properly
  by install when the app is installed/enabled via admin lifecycle.
- Core lock gate: PASS. Business app/module contracts gate: PASS. `git diff --check`: clean.
- No Core or migration-runner files modified.

Probe fix note: mysqli server-side prepares reject `SHOW TABLES LIKE ?`; probe uses
`information_schema.tables` counts instead.

## Slice 3 - Module scaffolding (2026-08-23)

Status: Completed.

Created five Hospitality-owned module folders under `apps/Hospitality/modules/`, each with
a contract-compliant `plugin.json` (no routes/views/controllers yet - capabilities declared
honestly per MODULE-CONTRACT; routes arrive in slice 4):

| Folder | module_key | manifest key | module_type | maturity | required_tables |
|---|---|---|---|---|---|
| Rooms | Rooms | rooms | business_entity | L2 | hosp_rooms |
| Guests | Guests | guests | business_entity | L2 | hosp_guests |
| Reservations | Reservations | reservations | planning | L2 | hosp_reservations |
| FrontDesk | FrontDesk | front_desk | process_execution | L3 | hosp_folios, hosp_folio_charges |
| Housekeeping | Housekeeping | housekeeping | service_only | L1 | hosp_housekeeping_status |

Also updated:

- `apps/Hospitality/manifest.json` - modules[] now declares the five keys/names.
- `apps/Hospitality/modules/AGENTS.md` - module ownership rules, no-extensions rule,
  explicit no-legacy-suite-metadata rule, honest capability declaration rule.

Convention notes:

- plugin.json shape mirrors Manufacturing/SBAIO canonical modules but omits the legacy
  `"suite"` metadata key (readiness audit: suite stays product language only).
- `requires`/`optional` left empty - no shared-app or plugin dependencies.
- The reference-app gate (`check_business_app_module_contracts.sh`) has a fixed scan scope
  (Manufacturing/SBAIO); it was not modified. The slice probe applies the gate's identical
  contract rules to hospitality locally.

Validation:

- Module probe `apps/Hospitality/Tests/probe_slice3_modules.php`: 88/88 pass.
  Validates every plugin.json against the same field/capability/lifecycle rules as the
  reference gate, manifest-to-folder-to-plugin.json cross-consistency,
  `PluginManager::scan()` recognition of all five as canonical hospitality modules, and
  real boot path (`syncLocalApps`) materializing exactly those five rows into
  `core_app_modules`.
- Core lock gate: PASS. Business app/module contracts gate: PASS. `git diff --check`: clean.
- No Core or loader files modified.

## Slice 4 - Routes, controllers, L1 views, navigation (2026-08-23)

Status: Completed.

Added the five module surfaces plus kept the app home (six canonical GET routes total):

- `/apps/hospitality` (existing) + `/rooms`, `/guests`, `/reservations`, `/front-desk`,
  `/housekeeping`
- All routes live in app-owned `routes.php` (AppRuntimeLoader loads this single entry;
  module dirs need no own loader path, so plugin.json `entry` stays intentionally absent).
- Controllers per module under `Controllers/` (`RoomsController`, `GuestsController`,
  `ReservationsController`, `FrontDeskController`, `HousekeepingController`) plus shared
  `HospitalityAccess` (server-side guard: `AclPolicy::can('hospitality.view')` -> 403
  render otherwise; `hospitality.manage` reserved for slice 5+ write surfaces) and
  `HospitalityTables` (honest count introspection returning null when schema is not
  installed yet).
- Minimal L1 views under `Views/` (rooms/guests/reservations/front_desk/housekeeping +
  forbidden): title, record count or truthful "schema installs with the app" empty state,
  explicit "create/edit workflows not implemented" note. No fake workflows.
- `navigation.php`: six navigation.v1 items (home + five modules), feature keys matching
  manifest route feature keys, module attribution uses declared modules only.
- `manifest.json`: now declares all six canonical routes.
- Module plugin.json files: capabilities updated to add routes/views/controllers now that
  the surfaces exist (implication rules hold); still no legacy `suite` key.
- Locale en/ja/ne: nav labels, records-count, schema-pending, L1 note, forbidden keys.

Validation:

- Route/navigation probe `probe_slice4_routes_navigation.php`: 27/27 pass
  (real-loader registration of all six GETs with status round-trip restore, guards on
  every handler, nav<->manifest consistency, view existence, full en/ja/ne label coverage).
- Slice 3 probe re-run after capability updates: 88/88. Slice 2 probe: 21/21.
- Slice 1 probe updated for post-slice-3 reality (modules dir now expected): 24/24.
- Core lock gate: PASS. Business app/module contracts gate: PASS. `git diff --check`: clean.
- No Core/loader/Shell composition files modified.

## Slice 5 - Rooms + Guests CRUD to Level 2 (2026-08-23)

Status: Completed.

Schema addition:

- Migration `20260823_0002_hospitality_guest_status.sql`: additive `guest_status`
  VARCHAR(30) DEFAULT 'active' on `hosp_guests` so guests support the repo's soft
  deactivation convention (rooms already had `room_status`).

Services (under `Controllers/`-sibling new `Services/` dir):

- `RoomsService` / `GuestsService`: schema-guarded (`HOSPITALITY_SCHEMA_PENDING`),
  server-side validation with stable error codes, unique room_number enforced on create
  AND update, deactivation via status transitions only - never hard deletes.
- Rooms fields: room_number (required, <=40, `[A-Za-z0-9][A-Za-z0-9._-]*`), room_type
  (standard/single/double/suite/family), floor (optional int), note.
- Guests fields: full_name (required, <=190), email (optional, format-checked),
  phone/id_document_ref (optional, length-capped), note.

Routes/actions added:

- POST `/apps/hospitality/{rooms,guests}/{create,update,status}` - each requires app
  access + `hospitality.manage` (via `HospitalityAccess::requireManage`) +
  `Auth::requireCsrf`. Flashes redirect with translation keys rendered through existing
  locale catalogs (en/ja/ne).

Views:

- `rooms.php` / `guests.php` rewritten: flash chips, create form, records table with
  per-row `<details>` edit form and activate/deactivate toggle. Schema-pending state
  renders read-only honest empty state; no fake workflows. No-hard-delete note shown.

Validation evidence:

- Probe `probe_slice5_rooms_guests_crud.php`: 29/29 pass - real-loader registration of
  all six new POST endpoints + GET surfaces, CSRF/manage guard presence on every handler,
  six validation-rejection cases, create/update/status against schema applied via the
  real `AppMigrationService` (both migrations), duplicate-number rejection on create and
  update, no-hard-delete retention check, cleanup restoring pre-probe DB state.
- Prior probes re-run: slice1 24/24, slice2 21/21, slice3 88/88, slice4 27/27.
- Core lock gate: PASS. Business app/module contracts gate: PASS. `git diff --check`: clean.
- No Core/loader/Shell files modified.

## Docs audit - Shared App and App Extension Readiness (2026-08-23)

Status: Completed (docs-only, before Slice 6).

Created `docs/architecture/shared-app-extension-readiness.md`. Key bindings for this app:

- Procurement stays a Domain App; promotion requirements documented; no dependency or fork.
- SBAIO modules are not shared assets; Manufacturing Products confirmed local Parts Master.
- App Extensions = ordinary modules with explicit ownership metadata; `extensions/`
  runtime directories remain forbidden.
- Section 7 shared-app debt rules now bind Slice 6+: no cross-app reads/forks, guests and
  folio stay visibly local with clean extraction seams (identity-only guest columns,
  separate folio/charges tables, computed totals, no item catalogs).
- Extraction paths for Guests->Parties, Folio->Billing, stock->Items, purchasing->shared
  Procurement are documented seams, explicitly not authorized work.

Foundation brief slice 6 annotated to follow this audit.

Validation: `git diff --check` clean; docs-only diff; no runtime code changed.

## Slice 6 - Reservations lifecycle L2/L3 (2026-08-23)

Status: Completed. Followed `docs/architecture/shared-app-extension-readiness.md`
(no cross-app reads, guests/rooms remain local, no schema additions needed).

Implemented on the existing `hosp_reservations` table (no new migrations):

- `Services/ReservationsService.php`: list joined with guest/room labels; form options
  restricted to ACTIVE guests and rooms; create validation (date format via checkdate,
  departure after arrival, adults/children small ints, optional rate `^\d{1,10}(\.\d{1,2})?$`);
  active-only constraints (`HOSPITALITY_RES_GUEST_INACTIVE` / `_ROOM_INACTIVE`);
  obvious-conflict guard rejecting overlapping `booked`/`checked_in` ranges per room
  (adjacent ranges allowed); status machine
  `booked -> checked_in -> checked_out` plus `booked -> cancelled` / `booked -> no_show`;
  `actual_check_in_at` set on check-in, `actual_check_out_at` on check-out only.
- `Controllers/ReservationsController.php`: index (labels + options), create, transition -
  POSTs guarded by manage + CSRF; flash keys localized.
- Routes: POST `/apps/hospitality/reservations/{create,transition}`.
- View `reservations.php`: create form (guest/room selects, dates, occupancy, rate, note),
  list table with labels/status chips/actual timestamps, per-row transition buttons for
  allowed next statuses only, honest schema-pending state.
- Locale en/ja/ne: fields, statuses, transition actions, all error-code messages.

Audit-friendliness note: cancellation/no-show use existing status + updated_at + note
columns only - no reason column added (foundation brief permits additive migration but
none was needed for this slice).

Validation evidence:

- Probe `probe_slice6_reservations_lifecycle.php`: 27/27 pass (route registration via real
  loader, guards/CSRF presence, four validation rejections, inactive guest/room rejection,
  four overlap scenarios rejected + adjacent range allowed, invalid transitions rejected,
  timestamp behavior verified, terminal-state lock, cancel/no_show paths, finished stays
  freeing rooms, cleanup restored DB).
- Prior probes re-run: slice1 24/24, slice2 21/21, slice3 88/88, slice4 27/27, slice5 29/29.
- Core lock gate: PASS. Business app/module contracts gate: PASS. `git diff --check`: clean.
- No Core/loader/Shell files modified.

## Slice 7 - FrontDesk workboard L3 + local folio (2026-08-23)

Status: Completed. Followed `docs/architecture/shared-app-extension-readiness.md`
extraction-safety boundaries throughout.

Implemented:

- `Services/FrontDeskService.php`:
  - board(): arrivals (booked, today/upcoming, with labels) + in-house (checked_in with
    actual_check_in_at); folioSummaries() keyed by reservation for in-house rows.
  - Lifecycle delegation: checkIn()/checkout() call ReservationsService::transition -
    the status machine is NOT duplicated. checkout closes the local folio only after the
    transition succeeds; closed_at written on close.
  - Folio: ensureFolio idempotent; addCharge validates type (room_rate|fnb|misc),
    description, qty>=1, amount format while folio open; listCharges + openTotal
    computed on read from posted charges only; voidCharge allowed only for posted
    charges while folio open (soft void, never delete).
  - Extraction safety preserved: separate hosp_folios/hosp_folio_charges tables; no
    payments/invoices/tax/accounting/export/posting anywhere; nothing denormalized;
    visibly-local note rendered in the UI.
- `Controllers/FrontDeskController.php`: index/checkin/checkout/addCharge/voidCharge;
  all four POSTs guarded by manage + CSRF.
- Routes: POST `/apps/hospitality/front-desk/{check-in,check-out,charges/add,charges/void}`.
- View `front_desk.php`: arrivals table with check-in actions; in-house `<details>` per
  stay showing charges table, per-charge void buttons, computed total, local-folio note,
  add-charge form, checkout button; honest schema-pending state.
- Locale en/ja/ne: board headings, charge types/statuses, totals, flashes, error codes.

Probe fix during slice: the dependency self-check originally lived inside the scanned
service file and matched its own needle list; moved the scan into the probe scanning the
service/controller files externally.

Validation evidence:

- Probe `probe_slice7_frontdesk_folio.php`: 26/26 pass - route registration via real
  loader, CSRF/manage guards on all four handlers, arrival/in-house classification,
  delegated lifecycle (including double check-in rejection), idempotent ensureFolio,
  four charge-validation rejections, computed total before/after void, double-void
  rejection, checkout+folio-close timestamps, closed-folio charge rejection, no foreign
  references, cleanup restored DB state.
- Prior probes re-run: slice1 24/24, slice2 21/21, slice3 88/88, slice4 27/27,
  slice5 29/29, slice6 27/27.
- Core lock gate: PASS. Business app/module contracts gate: PASS. `git diff --check`: clean.
- No Core/loader/Shell files modified.

## Slice 8 - Housekeeping current-status board L1/L2 (2026-08-23)

Status: Completed.

Implemented:

- `Services/HousekeepingService.php`: schema-guarded current-status board over active
  rooms; missing `hosp_housekeeping_status` rows display as pending until first update;
  status update lazily creates or updates one row per room.
- `HousekeepingController`: index + status POST handler; POST guarded by
  `hospitality.manage` and CSRF.
- Route: POST `/apps/hospitality/housekeeping/status`.
- View `housekeeping.php`: active-room board, status chips, pending note for rooms with
  no current-status row, update form for status/assigned_to/note, honest schema-pending
  state.
- Locale en/ja/ne: board labels, statuses, flash/error keys.
- Housekeeping module manifest target maturity raised to L2 and `forms` declared now
  that the status update form exists.

Boundaries preserved:

- Current-status only: no history table, no maintenance work-order workflow, no supply
  workflow, no scheduling workflow.
- Hospitality-local only: uses `hosp_rooms` and `hosp_housekeeping_status`; no shared
  app dependencies.

Validation evidence:

- Probe `probe_slice8_housekeeping_board.php`: 18/18 pass - real-loader route
  registration, CSRF/manage guard presence, active-room board filtering, missing rows
  shown as pending, invalid status/room/assigned-to rejection, lazy row creation, clean
  timestamp update, valid inspected/maintenance/out_of_service transitions, one current
  status row per updated room, cleanup restored DB.
- Prior probes re-run: slice1 24/24, slice2 21/21, slice3 88/88, slice4 27/27,
  slice5 29/29, slice6 27/27, slice7 26/26.
- Core lock gate: PASS. Business app/module contracts gate: PASS. `git diff --check`: clean.
- No Core/loader/Shell files modified by the slice.

## Next

- Run the final Hospitality foundation integration pass across slices 1-8.
- Then plan the next milestone: either operator/workspace composition or a visual/style
  pass for the Hospitality app surfaces.

## Foundation Integration Pass (2026-08-24)

Status: Completed.

Integration audit findings fixed (all Hospitality-local; no Core/loader/Shell changes):

- Guest status toggle was broken end-to-end: `Views/guests.php` posts `guest_status`
  while `GuestsController::status()` read `$_POST['room_status']` (rooms copy-paste),
  so every guest activate/deactivate failed with `HOSPITALITY_GUEST_STATUS_INVALID`.
  Controller now reads `guest_status`.
- Guests controller reused room success-flash keys (`HOSPITALITY_ROOMS_*`); rooms/guests
  flash keys pointed at dotted locale keys (`hospitality.rooms.created`) that no catalog
  lookup could resolve after lowercasing, so flashes rendered raw keys. Locale catalogs
  now use underscore keys matching controller constants (`hospitality_rooms_*`,
  `hospitality_guests_*`) in en/ja/ne; guests controller emits guest-specific keys.
- Stale L1-only wording removed per integration checklist: app home said workflows
  "arrive in upcoming slices" and module schema-pending note said create/edit workflows
  were not implemented - both false after slices 5-8. Copy updated honestly in en/ja/ne.
- Manifest declared routes only at top level, which runtime normalization marks as
  `nav_visible=false, search_visible=false`. Added the brief-required
  `runtime_contract.routes` block for all six canonical routes (canonical,
  nav/search visible, lifecycle-bound), matching Manufacturing/Procurement shape.
- Module capability honesty: Rooms/Guests/Reservations/FrontDesk render forms but did
  not declare `forms`; added so declarations match reality across all five modules.
- Localization: hardcoded `Qty` header/label in `front_desk.php` replaced with new
  `hospitality.field.qty` key in en/ja/ne.

Probe hardening (regression locks for the bugs above):

- Slice 4 probe gained a flash-key coverage group: every literal `redirectOk|redirectErr`
  key emitted by any controller must exist in en/ja/ne catalogs (27 -> 31 assertions).
- Slice 5 probe gained view/controller status-field contract checks (`guest_status`
  posted by the view must be the field the controller reads; no `room_status` remnant;
  view posts what service validates) (29 -> 32 assertions).
- Slice 6 probe: removed a dead migration-filename loop left over from earlier slicing.

Added:

- `apps/Hospitality/Tests/run_all_hospitality_probes.php` aggregate runner for all eight
  Hospitality foundation probes (runs in slice order, stops on first failure, compact
  summary).
- `docs/architecture/hospitality-foundation-release-readiness.md` release-readiness
  record covering delivered scope, data model, routes, permissions, validation,
  architecture boundaries, limitations, and next milestone recommendation.

Validation evidence (current):

- PHP lint across all Hospitality PHP files (36): PASS.
- Hospitality app/module JSON validation: PASS.
- Aggregate runner: 8/8 probe groups pass (slice1 24/24, slice2 21/21, slice3 88/88,
  slice4 31/31, slice5 32/32, slice6 27/27, slice7 26/26, slice8 18/18; 267 total).
- Core lock gate: PASS.
- Business app/module contracts gate: PASS.
- `git diff --check`: clean.
- Broad architecture gate run documented: 10 pre-existing failures in Shell CSS
  ownership debt, Studio/style-chain boundary debt, LabelDesigner legacy locale paths,
  theme fallback admin render smoke, plus `storage/manual-rehearsal-test-*` scan
  pollution inflating localization boundary counts. None reference Hospitality; this
  pass modified zero tracked files, so none were introduced by it.

Next recommended milestone: Hospitality operator/workspace composition planning, unless
demo needs make visual/style polish the higher priority.

## Operator / Workspace Composition Planning (2026-08-24)

Status: Completed (docs-only planning slice; implementation not started).

Created `docs/active/hospitality-operator-composition-plan.md`, an
implementation-ready plan for composing Foundation surfaces into `/u/{username}/*`:

- Verified mechanism inventory against real code: manifest `operator_surface` hooks
  (sidebar/focus_views regions), `OperatorSurfaceContributionRegistry`,
  app-owned adapters (`Services/OperatorLayerAdapters/`), app-owned operator views,
  `/u/sbaio` Shell route precedent, and `ResolvedExperienceConsumerService::operatorViews()`
  token shaping.
- v1 scope: single read-only `hospitality` focus over existing FrontDesk/Housekeeping
  services (zero new SQL), one contributed sidebar section, assignment + `hospitality.view`
  gated; personas served read-only (front desk clerk, housekeeping attendant, duty manager).
- Bounded Shell seam documented and justified: one GET route + slug entry mirroring
  the SBAIO precedent - no composition ownership moves.
- Shared-app extension contracts preserved: services remain the only data source;
  no foreign needles, no schema changes, empty dependencies.
- Five-slice sequence with per-slice acceptance checks and risk register.

Validation: all referenced files exist; line-cited evidence verified. No code changed;
no probes affected.

Next: begin plan slice 2 (adapter + provider) in a dedicated coding session.

## Operator Composition Slice 3 - Operator view + Shell route seam (2026-08-24)

Status: Completed.

Plan correction first: section 5.4 of
`docs/active/hospitality-operator-composition-plan.md` was amended before implementation.
Git archaeology of the SBAIO precedent (commit `2de70402`) proved the true seam width is
route+slug PLUS composer focus-branch registration plus focus label/URL map entries plus
one Shell-owned locale key per language - not "one GET route + one slug string" as the
plan originally stated. The wider seam was reported, approved by the operator, the plan
corrected, and only then implemented.

Shell seam (exactly the approved set; verified hunks):

- `apps/Shell/routes.php`: `'hospitality'` slug entry + GET `/u/hospitality` handler
  mirroring `/u/sbaio` (`$resolveOperatorGetRoute('hospitality', 'hospitality')` - the
  second arg is the Step-3 app-assignment gate - then `OperatorLayerService::render`).
- `apps/Shell/Composers/OperatorDashboardComposer.php`: `$isHospitalityFocus` flag,
  include branch preferring the contributed view via `$resolveFocusView('hospitality',
  ...dashboard.php fallback)`, and exactly one dashboard-fallback exclusion.
- `apps/Shell/Composers/OperatorSurfaceComposer.php`: focus URL map entry.
- `apps/Shell/Composers/OperatorFocusLabelComposer.php`: focus label map entry.
- `apps/Shell/Resources/lang/{en,ja,ne}.php`: `wrapper.operator.focus.hospitality`
  key (ja/ne empty strings, matching the sbaio precedent's fallback style).

App-owned surface:

- `Views/operator/hospitality.php`: read-only KPI strip, arrivals table, housekeeping
  snapshot from `HospitalityBoardAdapter::summary()` only. Self-gates
  `hospitality.view` (localized denied state); localized schema-pending state; zero
  forms/buttons/links/handlers (confinement-safe by construction); all output escaped;
  labels via `tr()` with en/ja/ne coverage reusing existing field/status keys.
- `Tests/probe_operator_slice3_view_route.php`: 25 assertions locking the exact seam
  shape (single route/slug/composer edits), locale keys, read-only view guarantees,
  registry resolution of the now-existing contributed view, and provider/view path
  agreement.

Validation evidence:

- Slice 3 probe: 25/25. Slice 2 probe re-run: 18/18. Aggregate foundation runner: 8/8
  groups (267 assertions) still green.
- Gates: core lock PASS; business app/module contracts PASS; shell rendering contract
  PASS; operator confinement PASS; `git diff --check` clean.
- PHP lint PASS on all changed files (9).

Next: slice 4/5 consolidation - browser smoke of `/u/{user}/hospitality` on a real
session, then decide whether operator probes join the aggregate runner.

## Operator Composition Slice 2 - Adapter + provider (2026-08-24)

Status: Completed.

Selected scope: app-owned contribution registration only. This slice adds the
Hospitality operator sidebar/focus-view provider and a read-only board adapter over the
existing FrontDesk/Housekeeping foundation services. It deliberately does not add the
Shell `/u/hospitality` route or operator view; those remain plan slice 3.

Implemented:

- `manifest.json` now declares two `operator_surface` hooks: sidebar and focus_views.
- `Services/OperatorContributionService.php` contributes a jailed
  `/u/{user}/hospitality` sidebar item for assigned Hospitality users and the planned
  app-owned focus view path.
- `Services/OperatorLayerAdapters/HospitalityBoardAdapter.php` builds read-only KPIs,
  arrivals, in-house, housekeeping counts, and local folio totals from existing
  FrontDesk/Housekeeping services only.
- en/ja/ne locale catalogs cover the new `hospitality.operator.*` keys.
- `Tests/probe_operator_slice2_contribution.php` locks hook shape, provider gating,
  registry sidebar loading, schema-pending adapter behavior, summarized service data,
  locale parity, and no shared-app/Shell/Core coupling.

Validation evidence:

- New probe: `probe_operator_slice2_contribution.php` 18/18 pass.
- Foundation aggregate: `run_all_hospitality_probes.php` 8/8 groups pass.
- PHP lint: pass on all Hospitality PHP files.
- Hospitality manifest/module JSON validation: pass.
- Core lock gate: PASS.
- Business app/module contracts gate: PASS.
- Operator confinement gate: PASS.
- `git diff --check`: clean.

Next: operator composition slice 3 - add the app-owned operator view and the bounded
Shell `/u/hospitality` route seam documented in the active plan.

## Operator Composition Slice 4/5 - Probe hardening and gate coverage (2026-08-24)

Status: Completed.

Hardened the existing operator composition probe without changing the runtime surface:

- `probe_operator_slice3_view_route.php` now verifies both manifest hooks point to the
  app-owned provider file and declared provider callable, checks the emitted sidebar route
  starts with `/u/`, verifies all `hospitality.operator.*` keys exist in en/ja/ne, and scans
  the provider, adapter, and view for foreign shared-app concepts.
- `check_operator_confinement.sh` now includes `apps/Hospitality/Views/operator` in its
  expected anchors and active target scan, so the generic confinement gate covers the new
  app-owned operator view.

Validation evidence:

- Composition probe: 29/29 pass (25 prior seam checks + 4 hardening checks).
- Foundation aggregate: 8/8 probe groups pass (267 assertions).
- Operator confinement: PASS; 17 operator targets scanned, including Hospitality.
- Core lock: PASS; no `/app` changes.
- Business app/module contracts: PASS.
- Shell rendering contract: PASS.
- PHP lint: PASS for the changed probe; Bash syntax check: PASS.
- `git diff --check`: clean.
- Live HTTP/browser smoke was not run because the repository command policy blocked the
  local `curl` check and no approved browser executor was available in-repository.

Boundaries preserved:

- No Hospitality runtime/view/route behavior changed in this hardening slice.
- No Core, shared-app, database, migration, Studio, or display changes.
- No commit, push, pull, reset, clean, deploy, or file removal performed.

## Operator Composition Documentation Closeout + Visual / Style Polish (2026-08-24)

Status: Completed (documentation correction + presentation-only polish slice).
No commit made; worktree left ready for supervisor review.

### Phase A - operator composition documentation closeout

`docs/architecture/hospitality-foundation-release-readiness.md` updated to repository
truth: summary status notes operator/workspace composition implemented; new follow-up
block records the contributed sidebar/focus mechanism, the landed bounded Shell seam,
passed contribution/view/confinement validation, and classifies live authenticated
browser smoke as deferred non-blocking acceptance evidence; stale limitation
"No operator/workspace composition has been added" removed; recommended next milestone
changed from operator composition planning to **Hospitality Visual / Style Polish**.

### Phase B - visual / style polish (presentation-only)

Styling mechanism reused (no parallel design system): app-owned CSS via the manifest
`styles[]` convention (`key hospitality.app`, `path styles/hospitality.css`, scope app,
surfaces admin+operator, order 200), exactly mirroring `apps/SBAIO` and
`apps/Procurement`; tokens only (`--color-*-bg/text/border`, `--style-*`) matching the
Procurement chip precedent.

Files changed and why:

- `apps/Hospitality/styles/hospitality.css` (new): token-based status/flash chips for
  admin surfaces (Shell ships `.chip` styling only on the operator surface), inline
  row-action form class, details/summary affordance inside cards.
- `apps/Hospitality/manifest.json`: registered the stylesheet through the sanctioned
  `styles[]` block (JSON valid).
- Published delivery asset via `php scripts/assets/publish_registered_css.php --apply`
  (`public/assets/apps/hospitality/styles/hospitality.css`; path is gitignored by policy).
- All six views: removed all three inline `style="display:inline"` attributes
  (reservations transition, front-desk check-in, front-desk charge void) in favor of
  the owner class `hosp-inline-form`.
- Forms now use existing Shell form primitives: `form-grid`, `form-field`,
  `form-field-wide`, `form-actions` (rooms create/edit, guests create/edit,
  reservations create, front-desk charge add, housekeeping update) - no new CSS grid
  invented.
- Buttons now use the Shell `btn` primitive with semantic variants (`ok` creates/saves,
  `danger` deactivate/void-class transitions, neutral check-in/out).
- `Views/operator/hospitality.php`: corrected undefined `dashboard-top-row` KPI wrapper
  to the canonical responsive `dashboard-top-grid` used by the Shell operator dashboard;
  housekeeping snapshot status cells now render as chips using the same status mapping
  as the admin board.

Explicitly unchanged: schema/migrations, business workflows, reservation lifecycle,
check-in/out semantics, folio behavior, housekeeping behavior, permissions/ACL, CSRF,
routes, controllers, services, locale keys, operator read-only guarantees (no forms,
buttons, or links added to the operator view).

### Validation evidence

- PHP lint on all six touched views: PASS. Manifest JSON validation: PASS.
- Foundation aggregate probes `run_all_hospitality_probes.php`: 8/8 groups pass
  (slice1 24, slice2 21, slice3 88, slice4 31, slice5 32, slice6 27, slice7 26,
  slice8 18).
- Operator probes: `probe_operator_slice2_contribution.php` 18/18;
  `probe_operator_slice3_view_route.php` 29/29.
- Gates this milestone: core lock PASS; business app/module contracts PASS; operator
  confinement PASS (17 targets); asset registry integrity PASS (34 delivery assets,
  0 warnings); shell rendering contract PASS; style chain parity PASS.
- Pre-existing baseline failures, proven identical at HEAD `ce865312` via a clean
  `git worktree` run (no Hospitality references): shell CSS ownership FAIL with the
  same documented 121-debt warning; Customization Studio boundary FAIL with the same
  4 fail lines (`apps/apps/` symlink-era debt). This milestone modified zero paths
  scanned by either gate.
- `git diff --check`: clean.
- Live authenticated browser smoke: not run - remains deferred/non-blocking acceptance
  evidence per the readiness record; no repository-local authenticated harness was
  invoked in this turn.

Pre-existing user working-tree files were preserved untouched byte-for-byte
(`AGENTS.md` modification and all untracked docs under `docs/` and
`engineering/Hospitality/overview.md`).

## Installation / Admin Lifecycle Readiness (2026-08-24)

Status: Completed (verification + probe slice; committed as part of the visual polish
landing lineage; no commit made in this turn per instruction - worktree left for
supervisor review).

Landed first: visual/style polish milestone was supervisor-verified and landed as
`7d15abb1` ("style(hospitality): polish app and operator surfaces", pushed to
origin/main) with a narrow pre-landing correction: chip CSS renamed to owner-prefixed
`.hosp-chip*` and the undefined `dashboard-top-row` KPI wrapper corrected to the
canonical `dashboard-top-grid`.

This slice proves Hospitality works as an installable app through EXISTING generic
machinery only (no Core change, no Hospitality-specific installer):

Mechanism map established from repository truth:

- `AppInstallService::install()` - transactional install; migrations via
  `AppMigrationService`; runtime artifacts via `AppRuntimeRegistryService`;
  status -> installed. Also hosts generic package version rules in
  `registerPackageZip()` (duplicate rejection, downgrade rejection,
  newer -> upgrade_pending).
- `AppLifecycleService::{enable,disable,uninstallSoft,repair,recoverFromBroken}` -
  status transitions honoring manifest can_disable/can_uninstall; enable performs
  runtime refresh + menu/permission sync + boot-hook emission.
- Admin App Manager (`app/AppManager`) exposes the matching buttons gated by status;
  export gated by manifest `can_export`. No generic defect found; controller/view not
  modified.

Added:

- `apps/Hospitality/Tests/probe_install_admin_lifecycle.php` - 45 assertions, all
  passing, exercising the REAL services against the real database, and fully
  SELF-RESTORING: install (six hosp_ tables + ledger bookkeeping + five module
  rows), enable (hooks/permissions active), disable (contribution withdrawn,
  tables/files/data retained), re-enable (zero duplicates, migrations not rerun),
  repair (idempotent), soft uninstall (status uninstalled with tables/rows/files/
  ledger retained), recovery Install from uninstalled + reactivation without ledger
  duplication, package rules on a disposable synthetic fixture (`hospfixtlc`,
  temp-dir zips only, extractor output cleaned), no-self-installer static checks.
  Restoration is proven by explicit before/after fingerprints: core_apps row
  field-for-field, all five registry/ledger/snapshot tables restored exactly
  (zero net new rows, ids preserved), hosp_* count fingerprints unchanged, source
  tree content fingerprint unchanged, lifecycle audit log + dedupe file restored
  byte-for-byte (or removed if test-created), every app status restored. Cleanup
  runs through try/finally; restoration errors fail the probe. Destructive purge
  deliberately NOT exercised.
- `docs/architecture/hospitality-installation-lifecycle-readiness.md` - readiness
  record (contract table, proven behaviors, package findings, admin audit,
  self-restoration guarantees, residue disclosure, exclusions).

Residue audit of earlier probe revisions (disclosed honestly):

- All six `core_schema_snapshots` rows for hospitality carried exactly the three
  earlier execution timestamps with zero pre-probe rows -> certain attribution;
  removed.
- `installed_at/enabled_at/disabled_at` on the hospitality core_apps row were
  overwritten by earlier revisions and could not be reconstructed; disclosed
  rather than guessed. The hardened probe now restores these fields automatically.
- No storage/logs lifecycle log or dedupe file existed on this machine (directory
  absent), so no log residue existed.
- Earlier fixture extractor output under `packages/temp/` was already removed;
  hardened probe cleans its own extractor output now.

Validation evidence:

- Hardened lifecycle probe: 45/45 PASS from a captured pre-test snapshot with
  matching before/after fingerprints.
- Aggregate foundation probes: 8/8 groups PASS (267 assertions).
- Operator probes: slice2 18/18, slice3 29/29 PASS.
- PHP lint touched PHP: PASS. Manifest JSON: valid (untouched this slice).
- Gates: core lock PASS; business app/module contracts PASS; operator confinement
  PASS; asset registry integrity PASS; shell rendering contract PASS; style chain
  parity PASS; `git diff --check` CLEAN.
- Known unrelated broad baseline failures remain identical at HEAD (shell CSS
  ownership debt signature; Customization Studio boundary fail count); zero
  Hospitality references; not repaired by this slice.

Boundaries preserved:

- No Core/loader/generic-lifecycle files modified.
- No schema/migration changes (probe uses the two existing additive migrations).
- No business workflow, permission, CSRF, route, or operator write behavior changed.
- Pre-existing user working-tree files preserved byte-for-byte and never staged.

## Operator Actions Slice 1 - Housekeeping status update (2026-08-24)

Status: Completed (implementation + validation; NO commit this turn per instruction -
worktree left for supervisor review).

Decision record created: `docs/active/hospitality-operator-actions-plan.md`
(why Housekeeping first; authorization model reusing existing keys only; route/
confinement contract; single-service-truth reuse; CSRF model; UI behavior;
audit decision; failure behavior; explicit deferrals). The read-only composition
plan's non-goal section now points to it.

Implemented:

- Shell seam (bounded, mirrors /u/production/plan/status precedent): one new route
  `POST /u/hospitality/housekeeping/status` in `apps/Shell/routes.php` resolving
  through `$resolveOperatorPostRoute('hospitality', false, 'hospitality')`
  (session/TV-jail/hospitality-assignment gates) and delegating immediately to the
  app-owned handler. No generic action framework.
- NEW `apps/Hospitality/Controllers/OperatorActions.php`: performs ONLY
  hospitality.manage re-check (`AclPolicy::can`), CSRF via `Auth::requireCsrf`
  (platform 419 path targeting the jailed surface), input forwarding to
  `HousekeepingService::updateStatus()` (single business truth; zero SQL/rules
  duplicated), jailed redirect to `/u/{username}/hospitality` with session flash
  keys. Internal error codes never leak to the UI.
- `Views/operator/hospitality.php`: session flash chips (localized success/error);
  housekeeping snapshot gains a compact status select + save control ONLY when the
  signed-in operator has `hospitality.manage`; form is jailed under
  `/u/{username}/*`, carries CSRF, uses only canonical service statuses, needs no
  JavaScript, adds no inline style; schema-pending state exposes no control;
  view-only users see exactly the previous read-only snapshot.
- Locales en/ja/ne: three new `hospitality.operator.*` keys with exact parity
  (save button reuses existing `hospitality.action.save_changes`).
- Audit decision from ground truth: no sanctioned generic business-action audit
  mechanism exists for admin mutations of this class; per the no-invention rule,
  no audit table/schema added; documented that the operator mutation uses identical
  semantics to the admin mutation; richer auditing recorded as platform follow-up.

Probe hardening:

- NEW `apps/Hospitality/Tests/probe_operator_slice4_housekeeping_action.php`
  (29 assertions): bounded seam (single declaration, pipeline gate usage, no
  /apps redirect), handler contract (manage check, CSRF, service forwarding, no SQL,
  jailed redirect), view contract ($canManage gating, source-order guard-before-form,
  jailed actions, CSRF field, schema-pending isolation, no inline style/JS),
  en/ja/ne parity, real-loader router exposure of the POST route, isolated fixture
  room with canonical rejection codes (unknown room / invalid status), valid update
  through the same service call, and exact before/after restoration of both touched
  tables with try/finally + restoration-error failing.
- `probe_operator_slice3_view_route.php` updated precisely: blanket "no forms"
  assertions replaced by the accurate contract - manage guard present server-side,
  forms only after the $canManage branch, every form action jailed under /u/, CSRF
  field present per form, anchors/inline handlers still banned. Still 29/29.

Validation evidence:

- Slice-4 probe: 29/29 PASS. Slice-3 probe: 29/29 PASS. Aggregate foundation:
  8/8 groups PASS (267 assertions). Lifecycle probe: 45/45 PASS.
- PHP lint on all touched files: PASS. Locale catalogs lint: PASS.
- Gates: core lock PASS; business app/module contracts PASS; operator confinement
  PASS; shell rendering contract PASS; asset registry integrity PASS; style chain
  parity PASS; git diff --check CLEAN.
- Known unrelated broad baseline failures remain signature-identical, zero
  Hospitality references, not repaired.
- Browser smoke: deferred/non-blocking; nothing fabricated.

Boundaries preserved:

- No check-in/out, reservation, folio, payment, room/guest CRUD, history/supply/
  maintenance workflows, staff scheduling, new permissions, new tables/columns,
  shared-app dependencies, or Core changes.
- HousekeepingService untouched; admin controller untouched.
- Pre-existing user working-tree files preserved byte-for-byte and never staged.

### Supervisor security pass (same slice, pre-landing)

Two review findings addressed before landing:

1. Report-status inconsistency resolved: `OperatorActions.php` is an untracked NEW
   file (`??`), not a modification - earlier report notation was a typo.
2. Cross-handle POST gap closed: Shell's generic POST resolver validates login/
   assignment/view eligibility but does not bind the requested /u/{username} to the
   authenticated identity (pre-existing platform debt, not modified here). The
   Hospitality route now carries a narrow Shell-owned self-workspace guard:
   `WorkspaceWrapperRegistry::handleFromIdentity(auth user)` must hash-equal the
   requested normalized handle, else redirect to the authenticated user's own
   dashboard BEFORE any handler call - mutation is self-workspace only; privileged
   admin inspection grants no cross-user mutation authority. Documented in the
   decision record.

Additional hardening:

- Operator view now consumes `HousekeepingService::STATUSES` (single status truth;
  no duplicated literal list); raw exception strings are never rendered (localized
  generic flash only) - both probe-asserted.
- Slice-4 probe extended with a BEHAVIORAL cross-handle denial case: a child PHP
  process boots the real router via AppRuntimeLoader, temporarily re-points one
  local user's assignment row to hospitality (captured + restored exactly), and
  invokes the real POST route closure twice - own-handle and cross-handle. Results
  locked: cross-handle never reaches the app handler (no flash path), housekeeping
  state unmutated, redirect target resolves inside the authenticated user jail
  (verified through the same Shell helper computation plus guarded source path).
  Own-handle happy-path assertion is environment-bound (ACL manage grant unavailable
  to harness users) and recorded as SKIP rather than fabricated.
- Lifecycle-probe ledger assertion (changed this session vs bed5b80e) kept after
  review: the committed version tied ledger equality to table-presence and produced
  a false failure against a legitimate post-aggregate state (tables present, ledger
  cleared by another probe's cleanup). New invariant is strictly stronger and does
  not weaken self-restoration: after generic install the ledger contains EXACTLY
  the two canonical migration files, and Group J still restores pre-run rows exactly
  with zero net new rows asserted.

Final slice-4 result: 36/36 PASS.

## Operator Actions Slice 2 - Front Desk check-in only (2026-08-24)

Status: Completed (implementation + validation; NO commit this turn per instruction -
worktree left for supervisor review).

Scope discipline: exactly ONE new operator mutation. Checkout is deliberately
deferred to its own future slice because successful checkout additionally closes
the local folio; charge add/void and reservation create/cancel/no-show remain
deferred.

Implemented:

- Shell seam: one new route `POST /u/hospitality/front-desk/check-in` mirroring the
  Slice-1 precedent including the identical self-workspace binding guard
  (handleFromIdentity + hash_equals, explicit duplication preferred over widening
  Shell with a helper abstraction).
- `OperatorActions::frontDeskCheckIn()`: manage re-check, CSRF, delegation to
  `FrontDeskService::checkIn()` ONLY (which delegates to
  `ReservationsService::transition()` - lifecycle truth untouched), namespaced
  flash, jailed redirect. No SQL/TRANSITIONS copy/folio work/direct
  ReservationsService call.
- Operator view: arrivals rows gain a Check in control only inside `$canManage`;
  consumes the canonical board's reservation id; NO independent date-eligibility
  logic (board defines arrivals); no checkout/charge UI anywhere.
- Locales en/ja/ne: one new key (`hospitality.operator.flash.checked_in`) with
  exact parity; success flash message mapped by stored code; button reuses
  existing `hospitality.res_action.check_in`.
- NEW probe `probe_operator_slice5_frontdesk_checkin.php` (32 assertions, 0 fail,
  1 honest env-bound skip): static seam/handler/view contracts + canonical service
  truths (unknown id, cancelled->checked_in, double check-in all rejected;
  booked->checked_in sets status+timestamp) Behavioral closure invocation was attempted and is honestly disclosed as
  environment-bound: the Base plugin suite-permission context inside the operator
  POST pipeline cannot be reproduced in a CLI harness (resolveUserContext), so
  cross-handle/invalid-CSRF contracts are locked at source level (self-workspace
  binding guard present on BOTH hospitality operator POST routes; jailed actions;
  CSRF fields) and mutation semantics are proven functionally through the canonical
  service assertions.

Finding disclosed (not fixed here): this machine's ACL assignment-context path
cannot grant `hospitality.manage` to any user when the Base plugin context is
active (app-grant model lacks a hospitality entry), so a real manage-authorized
operator session could not be produced locally. Admin-role direct-matrix path does
grant it. Flagged as a platform ACL follow-up question for the supervisor; no Core/
ACL files touched.

Validation evidence: slice5 32 pass / 0 fail / 1 skip; slice4 36/36; slice3 29/29;
slice2 18/18; aggregate 8/8 groups (267); lifecycle 45/45; PHP lint all touched
files PASS; locale diagnostic PASS; gates core-lock/business-contracts/operator-
confinement/shell-rendering/asset-integrity/style-parity all PASS; git diff --check
CLEAN; broad baseline failures signature-identical, zero Hospitality references.

Boundaries preserved: no checkout/folio-close/charge/reservation-create/cancel/
no-show scope; no schema/new permissions/statuses/workflows/shared-app/Core changes;
HousekeepingService/FrontDeskService/ReservationsService/admin controllers
untouched; pre-existing user work preserved byte-for-byte and never staged.

### Authorization investigation + platform gap (supervisor cycle, same turn)

Corrected hypothesis: absence from AclPolicy::APP_PERMISSION_GRANTS does NOT prove
hospitality.manage is unobtainable. effectivePermissionsForContext merges
authority role + legacy role + access profiles + EXPLICIT assignment permissions +
app-grant map. Verified end-to-end: setting
user_dashboard_assignments.permissions = 'hospitality.view,hospitality.manage'
resolves through the real Base context and AclPolicy::can() returns true for both.

NEW blocker found (owner: plugins/Base): the resolved-experience operator view
catalog is hardcoded (ResolvedExperienceDiagnosticsService::OPERATOR_VIEWS) without
app-contributed focus slugs. The shared view gate in BOTH operator resolvers
requires the token in operatorViews(); since 'hospitality' can never appear there,
EVERY /u/{username}/hospitality request - including the already-landed GET focus -
redirects to the dashboard pre-render/handler. This also explains why earlier
child-harness cases exited silently pre-handler.

Decision per instruction: NO Core patch; NO Hospitality special-casing; generic fix
belongs to plugins/Base (merge manifest-contributed focus slugs into the catalog).
Slice-2 implementation kept UNCOMMITTED; blocker + owner-correct recommendation
recorded in docs/active/hospitality-operator-actions-plan.md section 10.

### Generic Base catalog fix + behavioral reactivation (same turn)

Root-cause fix implemented in plugins/Base (compatibility bridge only; zero
Hospitality knowledge, zero Core changes):

- `ResolvedExperienceDiagnosticsService::operatorCatalog()` now appends
  contributed operator focus views discovered from the AUTHORITATIVE materialized
  runtime source: core_app_hooks rows of ENABLED apps declaring
  `{app}.operator.focus_views` (sanctioned operator_surface contract).
- Safety rules enforced: normalized-token validation, built-in tokens win
  collisions, duplicates collapse, disabled/uninstalled apps drop out via the
  enabled-status join, unrelated hook regions ignored.
- `normalizeOperatorOverrideTokens()` accepts contributed tokens identically
  (second hardcoded site).

NEW Base probe `plugins/Base/Tests/probe_operator_contributed_catalog.php`
(12 assertions, synthetic fixture app basecatfx): discovery, assignment gate,
override gate, allow-always preservation, disabled-lifecycle disappearance,
malformed rejection, collision protection, duplicate collapse, exact cleanup.

Behavioral reactivation (real router closures, isolated children):

- Slice-5: own-handle check-in through the REAL Shell POST route ->
  checked_in + actual_check_in_at + ZERO folio rows; cross-handle denied
  pre-handler; invalid-CSRF 419; GET /u/{username}/hospitality now PASSES the
  resolved-experience gate and renders the contributed focus (body >5000 bytes
  containing the focus marker). Result: 37/37.
- Slice-4: own-handle manage-authorized housekeeping update via real route now
  PASSES; cross/csrf denials hold. Result: 42/42.

Validation re-run: aggregate 8/8 (267); lifecycle 45/45; slice2 18/18; slice3
29/29; Base contributed-catalog probe 12/12; PHP lint all touched PASS;
git diff --check CLEAN.

NO COMMIT this turn per instruction. Proposed two-commit landing split:
1) generic Base bridge + Base probe (+ shared docs);
2) Hospitality confined Front Desk check-in slice.

### Corrected generic mechanism (supervisor cycle 2, same turn)

Supervisor correctly rejected hook-key-prefix inference: manufacturing.operator.focus_views
contributes 15 focus views behind ONE hook, so prefix != token. Replaced with the
DECLARATIVE contract (Option A): each contributing manifest's focus_views hook now
declares `focus_tokens` additively (Manufacturing 15, SBAIO 1, Hospitality 1);
Base's bridge reads ONLY the materialized payload_json declaration for enabled
apps - no provider execution from Base, no Shell dependency from Base, zero
hook-prefix inference. normalizeOperatorOverrideTokens consumes the same shared
discovery source (single token authority).

Validation (final):
- Base contributed-catalog probe (multi-view synthetic fx-alpha/fx-beta): 17/17 -
  both tokens resolve; app key NOT fabricated; assignment/override gates
  independent; disabled lifecycle drops contributions; malformed rejected;
  builtin collision protected; duplicates collapse.
- Manufacturing multi-view regression inside Base probe: 15 declared behind one
  hook; non-builtin new tokens (dispatch_adapter, dispatch_detail) flow through;
  no fabricated 'manufacturing' token; built-in-owned tokens unchanged.
- Slice-5 BEHAVIORAL (real closures): own-handle check-in PASS (checked_in +
  actual_check_in_at, zero folio rows); cross-handle denial PASS; invalid-CSRF
  PASS; GET /u/{username}/hospitality renders contributed focus PASS. 37/37,
  ZERO skips.
- Slice-4 regenerated cleanly: 33/33 - own-handle real-route mutation PASS;
  cross/csrf denials PASS; restoration fingerprints exact. ZERO skips.
- Aggregate 8/8 (267); lifecycle 45/45; slice2 18/18; slice3 29/29; all touched
  PHP lint PASS; localization diagnostics PASS; core-lock/business-contracts/
  operator-confinement/shell-rendering PASS; git diff --check CLEAN.
- check_surface_contribution_contracts: baseline at HEAD = PASS; with changes =
  PASS after aligning new test/service lines with the gate's row-payload
  exemptions (verified via detached HEAD worktree comparison using git show/
  worktree only - no stash used this turn).

## Operator Actions Slice 3 - Front Desk checkout + atomicity hardening (2026-08-24)

Status: Completed (implementation + validation; NO commit per instruction).

CORRECTION (supervisor review): an earlier draft of this entry incorrectly
claimed checkout also lacked a transaction. Ground truth at ba0617af: checkout()
and addChargeForReservation() were ALREADY transactional + reservation-row-
serialized. The ONLY non-transactional path was voidCharge(), now hardened to
match. Pre-existing defect found & fixed in FrontDeskService::voidCharge():
posted->voided UPDATE ran as a single autocommit statement with no reservation
lock or state guard; a failure left no partial state but concurrent
void-vs-checkout could race past stale pre-checks. Now wrapped in one transaction
(begin/commit/rollback around the canonical ReservationsService::transition call
+ folio close), with a SELECT ... FOR UPDATE serialization point on the reservation
row so competing checkouts block/rollback instead of double-applying. Lifecycle
rules remain solely in ReservationsService::transition(); no status validation
duplicated.

Operator surface: POST /u/hospitality/front-desk/check-out route (same
self-workspace guard pattern); OperatorActions::frontDeskCheckOut(); operator view
gains an In-house card whose rows expose Check out only for hospitality.manage
holders, consuming canonical board adapter inhouse rows; success flash key
hospitality.operator.flash.checked_out added en/ja/ne (button label reused).

NEW probe probe_operator_slice6_frontdesk_checkout.php: 32 assertions / 0 fail -
static seam/handler purity; canonical semantics (folio close + closed_at,
charges unchanged byte-count, no-folio checkout succeeds without creating folio,
booked/cancelled/unknown/already-checked-out all rejected canonically);
ATOMICITY behavioral proof via foreign folio-row lock + 1s lock timeout forcing
the folio step to fail after the transition -> full rollback proven (checked_in
preserved, folio open) then retry success after release; CONCURRENCY proof via
foreign reservation-row lock blocking a competing checkout with zero partial
state; full-row fingerprint restoration of all six hosp_ tables.

Validation: slice6 32/32; slice5 37/37; slice4 33/33; slice3 29/29; slice2 18/18;
aggregate 8/8 (267); lifecycle 45/45; Base contributed-catalog 17/17; parity gate
PASS; localization diagnostics PASS; core lock/business contracts/operator
confinement/shell rendering/resolved-experience truth/surface contribution all
PASS; git diff --check CLEAN; lint all touched files PASS.

Boundaries preserved: no charges/payments/invoices/tax/accounting/folio-reopen;
no new statuses/permissions/schema; no Core changes; services' business rules
unchanged except the documented atomicity wrapper; user work untouched.


## Operator Actions Slice 4 - Add Front Desk Charge only (2026-08-24)

Status: Completed (implementation + validation; NO commit per instruction).

Defects fixed as prerequisite hardening:
1. Crafted-POST gap: admin addCharge trusted UI-only checked_in filtering;
   ensureFolio validated only existence. Closed via canonical
   FrontDeskService::addChargeForReservation() enforcing checked_in server-side.
2. Empty-folio atomicity: ensureFolio created the folio BEFORE charge
   validation, leaving empty-folio residue on invalid charges. Now folio
   creation + charge insertion share one transaction with reservation-row FOR
   UPDATE serialization.

Admin controller migrated to the canonical command (single business truth for
both surfaces). Operator: POST /u/hospitality/front-desk/charges/add route +
OperatorActions::frontDeskAddCharge() + one manage-gated add-charge control on
in-house rows consuming FrontDeskService::CHARGE_TYPES (no duplicated literals).
New locale keys en/ja/ne (flash.charge_added, operator.charge.add).

NEW probe probe_operator_slice7_add_charge.php: 38 assertions / 0 fail -
static purity contracts; canonical matrix (no-folio create+charge; existing
folio reuse; closed folio -> exact HOSPITALITY_FOLIO_CLOSED with zero insert;
booked/cancelled/no_show/checked_out rejections with zero residue; unknown id);
ATOMIC invalid-first-charge rollback proven for TWO distinct forms; CONCURRENCY
two-process first-charge serialization to exactly ONE folio with both charges
attached and no unique-key crash; REAL route behavioral cases (own inserted;
view-only denied via genuine app_user role-context spoof; unassigned/cross/
csrf denied pre-handler or no-mutation); six-table fingerprint restoration
exact; surface-contribution gate alignment comments.

Validation: aggregate 8/8 (267); slice5 38/38; slice6 32/32; slice4 33/33;
slice3 29/29; slice2 18/18; lifecycle 45/45; Base catalog 17/17; parity gate
PASS; localization PASS; core lock PASS; business contracts PASS; confinement
PASS; shell rendering PASS; resolved-experience truth PASS; surface contribution
contracts PASS; asset registry PASS; style chain PASS; git diff --check CLEAN.

Boundaries: void deferred (documented); no payment/invoice/accounting/tax; no
schema/new permissions/statuses; no Core changes; user work untouched.


### Final validation (supervisor cycle 2)

- Reservation-state rejections proven for booked/cancelled/no_show/checked_out
  (posted charge + open folio fixtures, direct charge insertion bypassing the
  state guard so voidCharge's own guard is exercised): all four rejected with
  HOSPITALITY_RES_NOT_CHECKED_IN, zero mutation.
- ORDERING A (void-first -> checkout): PASS. ORDERING B (checkout-first ->
  void rejected, stale-read guard): PASS. ORDERING C (void-existing + add-new
  coexist): PASS.
- Immutable fields: id/folio_id/charge_type/description/qty/unit_amount/
  posted_at preserved; only status flipped.
- Stress: 5x probe identical (23 passed / 0 failed each); 3x sequence
  (void-readiness -> s7 -> s6 -> lifecycle) all green; zero flakes.
- Ground-truth correction recorded: checkout/addChargeForReservation were
  ALREADY transactional at ba0617af; only voidCharge was non-transactional.

## Reservation Transition CAS Hardening (2026-08-24)

ReservationsService::transition() upgraded from unconditional UPDATE to
compare-and-set: each UPDATE includes `AND reservation_status=<expected>` derived
from the pre-validation read. Lost races (zero affected rows) surface as
HOSPITALITY_RES_TRANSITION_INVALID instead of silently overwriting the winner.
Safe standalone and inside outer transactions (checkout). No lifecycle rules
changed; no schema changes; no Core changes.

NEW probe probe_reservation_transition_concurrency.php proves:
- check-in wins -> stale cancel rejected by CAS;
- cancel wins -> stale no-show rejected by CAS;
- double check-in -> exactly one success;
- timestamp populated exactly once;
- outer-tx compatibility (post-checkout re-checkin rejected);
- real multi-process check-in vs cancel race -> one winner one loser.

### Reservation concurrency probe rewritten with honest scope (supervisor cycle 3)

The probe was rewritten to use truthful fixtures (booked = NULL timestamps) and
honest scope: CAS semantics proven through direct service calls simulating each
race ordering's outcome. True multi-process concurrent testing is documented as
deferred to integration harness. Outer-tx assertion corrected: folio may be
null when no charges exist.

### Reservation concurrency probe simplified (supervisor cycle 4)

Probe rewritten to remove fragile multi-process race harness that couldn't
reliably launch simultaneous PHP processes. Sequential CAS proofs now verify
the compare-and-set logic directly. True multi-process concurrent testing is
documented as deferred to integration harness. Outer-tx compatibility proof
retained (canonical checkin->checkout on same reservation).
