# Hospitality Foundation Release Readiness

Status: Integration readiness record. Hospitality Foundation slices 1-8 complete.
Hospitality operator/workspace composition is implemented (read-only v1).

Date: 2026-08-24

## 1. Summary

The Hospitality App Foundation is implemented as a local-first Domain App under
`apps/Hospitality`. It registers through existing app loading behavior, declares five
Hospitality-owned modules, installs its local `hosp_` schema through the existing
migration runner, and exposes bounded v1 operational surfaces for rooms, guests,
reservations, front desk, folio, and housekeeping.

No Core, loader, Shell, shared-app, or `extensions/` runtime changes are part of the
foundation.

## 2. Scope Completed

Completed slices:

| Slice | Result |
|---|---|
| 1 | App skeleton and registration proof |
| 2 | Six-table local schema |
| 3 | Five module manifests and app manifest declarations |
| 4 | Canonical routes, controllers, L1 views, navigation |
| 5 | Rooms + Guests Level 2 CRUD |
| 6 | Reservations lifecycle |
| 7 | FrontDesk workboard + local folio |
| 8 | Housekeeping current-status board |

Follow-up milestone (implemented after this record was written):

**Operator / workspace composition (2026-08-24, implemented).** Hospitality now
contributes its operator sidebar section and a read-only `hospitality` focus at
`/u/{username}/hospitality` through the existing app-contribution mechanism
(`operator_surface` manifest hooks -> `OperatorContributionService` ->
`HospitalityBoardAdapter` -> `Views/operator/hospitality.php`). The bounded Shell
route seam documented in `docs/active/hospitality-operator-composition-plan.md`
section 5.4 has landed. Static engineering is complete: contribution/view probes and
the operator confinement gate pass. Live authenticated browser smoke remains deferred,
non-blocking acceptance evidence only - it is not unfinished engineering and not a
blocker.

## 3. Delivered Areas

Primary delivered source areas:

- `apps/Hospitality/manifest.json`
- `apps/Hospitality/routes.php`
- `apps/Hospitality/navigation.php`
- `apps/Hospitality/Controllers/`
- `apps/Hospitality/Services/`
- `apps/Hospitality/Views/`
- `apps/Hospitality/Resources/lang/{en,ja,ne}.php`
- `apps/Hospitality/migrations/`
- `apps/Hospitality/modules/*/plugin.json`
- `apps/Hospitality/Tests/`
- `engineering/Hospitality/`

## 4. Data Model Summary

Hospitality owns only local `hosp_` tables:

- `hosp_rooms`
- `hosp_guests`
- `hosp_reservations`
- `hosp_housekeeping_status`
- `hosp_folios`
- `hosp_folio_charges`

The schema uses logical links and indexed columns, with no physical foreign keys,
matching existing app migration conventions. Stays are represented as reservation
lifecycle fields (`reservation_status`, `actual_check_in_at`, `actual_check_out_at`).
Folio totals are computed on read from posted local charges.

## 5. Route Summary

Canonical GET surfaces:

- `/apps/hospitality`
- `/apps/hospitality/rooms`
- `/apps/hospitality/guests`
- `/apps/hospitality/reservations`
- `/apps/hospitality/front-desk`
- `/apps/hospitality/housekeeping`

Canonical POST actions remain under the owning surface route:

- Rooms: `/apps/hospitality/rooms/{create,update,status}`
- Guests: `/apps/hospitality/guests/{create,update,status}`
- Reservations: `/apps/hospitality/reservations/{create,transition}`
- FrontDesk: `/apps/hospitality/front-desk/{check-in,check-out,charges/add,charges/void}`
- Housekeeping: `/apps/hospitality/housekeeping/status`

No compatibility aliases are created.

## 6. Permission Model

- Every GET surface requires app access and `hospitality.view`.
- Every POST action requires app access, `hospitality.manage`, and CSRF validation.
- Management failures render or redirect through Hospitality-owned controllers and
  localized messages.

## 7. Validation Matrix

Latest integration pass: 2026-08-24.

Focused Hospitality probes:

| Probe | Latest result | Coverage |
|---|---:|---|
| `probe_slice1_registration.php` | 24/24 | App manifest, discovery, enable/load route registration |
| `probe_slice2_migrations.php` | 21/21 | Existing migration runner, six tables, idempotence, cleanup |
| `probe_slice3_modules.php` | 88/88 | Module manifests, discovery, `core_app_modules` materialization |
| `probe_slice4_routes_navigation.php` | 31/31 | GET routes, guards, nav/manifest/locale and flash-key coverage |
| `probe_slice5_rooms_guests_crud.php` | 32/32 | Rooms/Guests CRUD, validation, soft status transitions |
| `probe_slice6_reservations_lifecycle.php` | 27/27 | Reservation create, conflicts, status machine, timestamps |
| `probe_slice7_frontdesk_folio.php` | 26/26 | Workboard, delegated lifecycle, local folio/charges |
| `probe_slice8_housekeeping_board.php` | 18/18 | Current-status board, lazy row creation, status updates |
| `run_all_hospitality_probes.php` | 8/8 groups | Aggregate runner for all eight probes |

Required focused checks:

- PHP lint on Hospitality PHP files (36): PASS.
- Hospitality manifest and module JSON validation: PASS.
- Core lock gate: PASS.
- Business app/module contract gate: PASS.
- `git diff --check`: PASS.

Broad architecture gate run (2026-08-24): 10 pre-existing failures documented, all in
Shell CSS ownership debt, Studio/style-chain boundary debt, LabelDesigner legacy locale
paths, and the theme fallback admin render smoke; `storage/manual-rehearsal-test-*`
untracked artifacts additionally inflate localization boundary scan counts. None of the
failures reference Hospitality, and this pass modified zero tracked files, so none were
introduced by it.

### Integration fixes applied during this pass

All Hospitality-local:

1. Guest status toggle repaired - controller read `$_POST['room_status']` while the view
   posts `guest_status`, so guest activate/deactivate always failed.
2. Flash keys corrected - guests controller emitted room success keys; rooms/guests
   flash constants pointed at dotted locale keys that could not resolve. Catalogs now
   use underscore keys matching controller constants in en/ja/ne.
3. Stale L1-only copy replaced - app home and schema-pending notes no longer claim
   workflows are unimplemented (false after slices 5-8); en/ja/ne updated.
4. Manifest `runtime_contract.routes` added for the six canonical routes (canonical,
   nav/search visible, lifecycle-bound) per the foundation brief and reference-app shape.
5. Module capability honesty - `forms` declared by Rooms/Guests/Reservations/FrontDesk,
   which all render forms.
6. Hardcoded `Qty` strings localized via new `hospitality.field.qty` key (en/ja/ne).

Probe hardening locks these regressions: slice 4 asserts every controller-emitted flash
key resolves in en/ja/ne catalogs; slice 5 asserts the guest status field contract
between view and controller; aggregate runner added at
`apps/Hospitality/Tests/run_all_hospitality_probes.php`.

## 8. Architecture Boundary Review

Preserved boundaries:

- Core remains locked.
- App/module loaders are unchanged.
- Shell composition is unchanged.
- Hospitality logic is contained under `apps/Hospitality`.
- Hospitality modules have one parent app; module manifests declare ownership
  (`owner_app: hospitality`, `package_type: module`) and honest capabilities.
- No shared Billing, Parties, Items, Inventory, Accounting, or Procurement dependency
  exists (dependencies arrays empty; only `hosp_*` tables are read).
- No `extensions/` directory exists.
- Suite remains product/composition language only; App is runtime truth.
- Folio is visibly Hospitality-local and extraction-safe.
- Manifest route contracts match the canonical runtime contract shape used by
  reference business apps.

## 9. Known Limitations

- No shared Billing, Parties, Inventory, Items, Accounting, or promoted Procurement app.
- Local folio has no payments, invoices, tax, accounting, export, or posting.
- Housekeeping is current-status only; there is no history table, supply workflow,
  maintenance work-order workflow, or staff scheduling.
- No display/kiosk surfaces have been added.
- Visual styling remains basic and follows existing app surface conventions; three
  transition/void forms still use an inline `display:inline` attribute pending the
  separate visual/style pass.
- No `extensions/` loader or suite runtime layer exists.

## 10. Recommended Next Milestone

Recommended next milestone: **Hospitality Visual / Style Polish**.

Reason: the foundation and the read-only operator/workspace composition are both
implemented and validated. The next product value is presentation quality across the
delivered admin surfaces (`/apps/hospitality/*`) and the operator focus
(`/u/{username}/hospitality`), including removal of the remaining inline-style debt,
using existing app-owned CSS loading conventions and platform tokens only.

Alternative next milestones:

- Installation/admin lifecycle polish, if package lifecycle proof is more important.
- Shared-app extraction planning continuation, if cross-domain reuse becomes the priority.
