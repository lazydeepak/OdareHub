# OH-AUTO2-HOSP-002 — Reservation Create-to-Manage Workflow Assessment

**Date:** 2026-10-21
**Baseline:** `224f0bec26217c5d5c2f3a105ca49a8b73086a41`
**Authorization:** Read-only source assessment and documentation only
**Classification:** **INCOMPLETE**
**Recommended decision:** **IMPLEMENT** a separately authorized narrow follow-up; no implementation is authorized by this assessment.

## 1. Exact execution graph

The implemented path is:

```text
POST /u/{username}/hospitality/reservations/create
  -> Shell resolveOperatorPostRoute('hospitality', false, 'hospitality')
  -> Shell own-handle binding via WorkspaceWrapperRegistry::handleFromIdentity()
  -> Apps\Hospitality\Controllers\OperatorActions::reservationCreate()
  -> AclPolicy::can('hospitality.manage', Auth::user())
  -> Auth::requireCsrf()
  -> strict guest and optional-room identity normalization
  -> ReservationsService::create($payload)
  -> session success/error flash
  -> 302 /u/{same-user}/hospitality
  -> HospitalityBoardAdapter::summary()
  -> FrontDeskService::board()
  -> operator arrivals/in-house projection
```

The canonical service returns the inserted reservation identity from its
`SELECT id ... ORDER BY id DESC LIMIT 1` result, but the operator action does
not place that identity in the flash or redirect. Discoverability therefore
depends on the board projection, not on a direct post-create identity handoff.

## 2. Identity continuity

For a reservation that appears in the board, continuity is preserved:

- `FrontDeskService::board()` selects `r.id` for booked arrivals and checked-in
  stays.
- The operator view uses that exact `id` in hidden `reservation_id` fields.
- Check-in, checkout, cancellation, no-show, and add-charge forms remain jailed
  under `/u/{username}/hospitality/...`.
- `OperatorActions` strictly normalizes each reservation identity before
  delegating to the existing FrontDesk service methods.
- Folio summaries key charges by `reservation_id`; void-charge controls use the
  charge identity while the service resolves its reservation identity.

No source-level identity loss was found for records present in the operator
projection.

## 3. Discoverability and status actions

The board projection is deliberately narrow:

- `booked` with `check_in_date >= CURDATE()` appears in Arrivals.
- Arrivals expose check-in, cancellation, and no-show actions to `manage`
  users, each carrying the exact reservation ID.
- `checked_in` appears in In-house and exposes checkout and add-charge actions.
- Open posted folio charges expose the existing void action.
- Terminal `cancelled`, `no_show`, and `checked_out` records are not shown by
  the operator board, which is consistent with the active-workboard design.

The create service accepts syntactically valid dates in the past; it validates
date format and ordering but does not require `check_in_date >= CURDATE()`.
Those valid `booked` records are excluded by the arrivals predicate and have no
operator reservation-list fallback or returned-ID focus path. This is a
reproducible source-level discoverability gap for an accepted creation input.

For today/future dates, including an unassigned room (`room_id = 0`/empty), the
created `booked` record is discoverable through the existing board query and its
status actions are rendered.

## 4. Authorization evidence

- The form is rendered only when `hospitality.view` and explicit
  `hospitality.manage` are present.
- The POST route resolves the authenticated operator context and rejects
  cross-user workspace mutation through the existing own-handle binding.
- The action re-checks `hospitality.manage` and validates CSRF before service
  dispatch.
- Read-only users receive no reservation mutation form or action columns.
- Existing status routes repeat the same manage, CSRF, own-handle, and strict
  identity protections.

## 5. Verification performed

Safe offline probes were run individually:

- `probe_operator_behavioral.php`: **55/55**
- `probe_operator_input_identity.php`: **24/24**
- `probe_reservation_transition_authorization.php`: **62/62**
- `git diff --check`: pass

The aggregate Hospitality runner was **not executed** because it includes
database-backed probes. No database/SQL, browser/HTTP, activation, deployment,
Core, schema, Manufacturing, Material, or SBAIO operation occurred.

## 6. Finding and smallest justified proposal

**Finding:** The journey is complete for the board's today/future arrival
contract but incomplete for all inputs accepted by `ReservationsService::create()`.
A valid past-date reservation can be created successfully and then disappear
from the operator workspace because no operator projection includes it and the
returned identity is discarded.

The smallest justified future correction is an app-owned operator continuity
contract, chosen and separately authorized before implementation: either retain
the returned reservation ID in a bounded post-create focus path, or add a
narrow owner-owned reservation lookup/list projection that includes the newly
created record. The correction must preserve `ReservationsService` as the sole
write authority and must not broaden Shell or domain-service responsibilities.
Changing date validation or silently broadening the arrivals query is not
recommended without a separate product decision.

## 7. Decision

**Recommended decision: IMPLEMENT** a separately authorized OH-AUTO2-HOSP-002
follow-up for the bounded discoverability gap. This assessment itself made no
production-code changes.
