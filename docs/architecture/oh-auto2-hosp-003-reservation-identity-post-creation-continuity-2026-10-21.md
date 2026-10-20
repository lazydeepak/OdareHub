# OH-AUTO2-HOSP-003 — Reservation Identity and Post-Creation Continuity

**Date:** 2026-10-21
**Baseline:** `2438ae1042232f9c28c2f46e01b82b0a12951e2c`
**Authorization:** Bounded Hospitality-owned implementation and isolated offline verification
**Status:** Implemented and verified offline; live database/concurrency/browser acceptance remains blocked.

## 1. Before/after identity contract

Before this change, `ReservationsService::create()` inserted the row and then
used `SELECT id FROM hosp_reservations ORDER BY id DESC LIMIT 1`. That was a
global latest-row lookup and could return another connection's newer insert.

The service now reads `(int) DB::conn()->insert_id` immediately after the
current INSERT. A non-positive identity raises `HOSPITALITY_RES_ID_INVALID`.
The identity check occurs before COMMIT. Both assigned-room and unassigned-room
creation now use the same transaction boundary, so identity failure or any
later insert failure rolls back the reservation work.

The existing assigned-room row lock, overlap predicate, optional unassigned
room (`NULL` storage), date/count/rate/note validation, and lifecycle state are
unchanged.

## 2. Exact creation and confirmation graph

```text
POST /u/{username}/hospitality/reservations/create
  -> Shell authenticated operator POST resolution
  -> existing own-handle binding
  -> OperatorActions::reservationCreate()
  -> hospitality.manage + CSRF
  -> strict guest / optional-room identity normalization
  -> ReservationsService::create()
  -> START TRANSACTION
  -> optional room lock + overlap validation
  -> INSERT
  -> current DB connection insert_id
  -> positive-ID validation
  -> COMMIT
  -> one-time session reservation ID + localized success key
  -> jailed 302 to /u/{same-user}/hospitality
  -> view reads and unsets the session confirmation
  -> escaped localized "created reservation ID" confirmation
```

If creation, identity retrieval, or identity validation fails, the service does
not commit and the operator action clears any confirmation state, stores only
the existing generic error presentation path, and redirects without a success
confirmation.

## 3. Confirmation boundary

The confirmation stores only the positive generated reservation ID in the
authenticated operator's existing PHP session. The operator view consumes and
unsets it on the next render, so it is one-time. It does not perform an
arbitrary-ID lookup, expose guest/room/date/rate details, add management
controls, add a route, or bypass ACL/assignment/own-handle checks.

The ID is escaped at render time and localized through EN/JA/NE catalog keys.
The existing arrivals/in-house filters remain unchanged; past-date records may
still be absent from the active board, but the operator receives the exact
identity confirmation required by HOSP-003.

## 4. Verification

Safe individual offline probes passed:

- `probe_operator_behavioral.php`: **57/57**
- `probe_reservation_identity_continuity.php`: **6/6**
- `probe_reservation_modification_integrity.php`: **33/33**
- `probe_operator_input_identity.php`: **24/24**
- `probe_reservation_transition_authorization.php`: **62/62**
- PHP lint for all touched PHP files: pass
- `git diff --check`: pass
- architecture gates: pass; deletion family gates **28/28**
- read-only deployment readiness: pass

The identity continuity probe includes a mocked current-connection versus
concurrent-connection ID separation check. It does **not** claim real database
concurrency proof. The aggregate Hospitality runner was not executed because
it includes database-backed probes.

## 5. Changed files

- `apps/Hospitality/Services/ReservationsService.php`
- `apps/Hospitality/Controllers/OperatorActions.php`
- `apps/Hospitality/Views/operator/hospitality.php`
- `apps/Hospitality/Resources/lang/{en,ja,ne}.php`
- `apps/Hospitality/Tests/probe_operator_behavioral.php`
- `apps/Hospitality/Tests/probe_reservation_identity_continuity.php`
- `apps/Hospitality/Tests/probe_reservation_modification_integrity.php`
- this report and Hospitality work/decision/current/registry/compliance records

No Core, schema, migration, route, permission, database, browser/HTTP,
activation, deployment, Manufacturing, Material, SBAIO, or unrelated working
tree file was changed.

## 6. Remaining limitations

Live authenticated acceptance and actual concurrent database verification remain
blocked under OH-AUTO2-119 and the standing database restrictions. No claim is
made that mocked identity separation replaces real database concurrency proof.
