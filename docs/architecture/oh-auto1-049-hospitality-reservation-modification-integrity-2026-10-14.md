# OH-AUTO1-049 — Hospitality Reservation Modification Integrity

Date: 2026-10-14
Status: **Static/mock investigation complete; no database execution**

## Implemented modification entry points

The authoritative reservation service exposes only:

- `ReservationsService::create(array $post)`
- `ReservationsService::transition(int $id, string $to)`

`ReservationsController` exposes only POST create and transition handlers,
both protected by Hospitality manage authorization and CSRF. FrontDesk
check-in, checkout, cancellation, and no-show actions delegate to the same
status service. Checkout additionally locks the reservation row and closes the
local folio in one transaction.

No implemented room reassignment, date-change, extension, reservation update,
or cancelled/no-show reactivation operation was found.

## Existing integrity contracts

- Assigned creation locks the room row before evaluating availability.
- The overlap predicate considers only `booked` and `checked_in` reservations.
- The predicate is adjacent-range safe: `check_in_date < requested_checkout`
  and `check_out_date > requested_checkin`.
- Create commits and rolls back as one transaction.
- Lifecycle transitions are explicit and terminal states cannot reactivate.
- Check-in, checkout, cancellation, and no-show do not change room or planned
  dates, so they cannot bypass the room overlap predicate.
- Transition updates use compare-and-set status predicates.

## Result

No verified application-owned modification bypass was found. No runtime
correction was necessary. Static proof is not database-level proof of concurrent
modification behavior; no such modification API currently exists to test.

## Verification

- Reservation modification integrity probe: **27/27**
- PHP lint: changed probe and inspected reservation files pass
- Existing database-backed reservation probes: **not run**
- No database connection, SQL execution, module activation, or runtime request
  was performed

## Scope and remaining limits

- No routes, migrations, schemas, or reservation policies changed.
- Future room/date modification features must reuse the existing overlap and
  room-locking contract rather than introduce a parallel check.
- OH-AUTO1-041 create-concurrency protection remains the only verified
  database-level concurrency evidence; database-backed requalification remains
  suspended.
