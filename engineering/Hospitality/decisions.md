# Hospitality — Decisions

## 2026-10-26 — OH-AUTO3-HOSP-OP-LOOKUP-001 persistent reservation lookup

**Decision: Use the existing operator Hospitality view authority and canonical reservation read.**

The persistent operator lookup accepts only a canonical positive decimal
reservation ID from the existing `/u/{username}/hospitality` GET query; leading,
trailing, signed, fractional, scientific, and overflow values are rejected.
The view's existing `hospitality.view` check and operator assignment/provider
admission remain the authority boundary; no creator ownership is inferred and
no new permission or route is introduced. The adapter delegates to
`ReservationsService::findById()`, which performs one exact-ID bounded joined
read and returns no details for a missing ID. Malformed input is rejected
before service access, and service/schema failure fails closed.

The lookup intentionally does not broaden the arrivals projection or lifecycle
state machine. It can therefore retrieve valid past-date reservations that the
arrivals board excludes, while preserving all existing mutation controls and
same-user operator navigation.

## 2026-10-25 — OH-AUTO3-HOSP-001 created-reservation continuation

**Status: Implemented and verified within the bounded Hospitality owner boundary.**

The operator create redirect may continue only through the exact positive
reservation ID retained in the server-side session. Hospitality now performs a
bounded joined read for that identity and renders a localized continuation card
before the normal arrivals board. This preserves valid past-date reservations
that the arrivals projection intentionally excludes (`check_in_date >=
CURDATE()`) without changing date validation or lifecycle semantics.

The card reuses the existing jailed check-in, cancel, and no-show routes and
the existing `hospitality.manage`/CSRF controls. No browser reservation
selector, new permission, route, schema, shared-app dependency, or Shell
business logic was added. Missing, invalid, unknown, or unavailable exact
reads fail closed.

Evidence: database-free continuation probe **21/21**, operator behavioral
probe **57/57**, input-identity probe **24/24**, PHP lint, operator confinement,
business-app/module contracts, architecture gates, deletion-family gates
**28/28**, deployment readiness, and diff hygiene passed. No database, browser,
HTTP, activation, deployment, commit, or push operation occurred.

## 2026-10-21 — OH-AUTO2-HOSP-004 disposable runtime acceptance

**Status:** Runtime acceptance passed in an independently attested disposable
development MySQL instance; authenticated browser/HTTP acceptance remains
unverified and production deployment remains a separate decision.

The configured `127.0.0.1:3306` / `os.susankhya.com` / `root` target was not
used because it was not independently attested as development-only and no
server was listening. A fresh temporary MySQL instance with no-defaults,
loopback-only networking, disabled X Plugin, generated test credentials, a
runner-owned source root, and a recoverable pre-fixture backup was used instead.

Real reservation lifecycle **27/27**, Front Desk/local folio **26/26**,
reservation-lock concurrency **3/3**, and two-process current-connection ID
separation passed. Fixtures were confined to and removed with the disposable
instance. No product defect was independently reproduced; no code correction
was authorized or made.

## 2026-10-21 — OH-AUTO2-HOSP-003 reservation identity continuity

**Status:** Implemented and accepted within the bounded non-Core assignment.

`ReservationsService::create()` now returns the ID from the current connection's
`insert_id`, rejects non-positive generated identities before commit, and uses a
transaction for both assigned and unassigned-room creation so identity failure
rolls back. The unsafe global latest-row query is removed.

The operator action stores only the exact positive returned ID in the existing
session, sets the existing localized success key, and redirects to the same
jailed Hospitality workspace. The view consumes/unsets the ID once and renders
an escaped localized confirmation. No arbitrary lookup, extra details, action,
route, permission, or authorization authority was added.

Verification is isolated offline only. Actual database concurrency,
browser/HTTP acceptance, activation, deployment, and live acceptance remain
blocked; mocked concurrency evidence is explicitly not treated as real DB
proof.

## 2026-10-21 — OH-AUTO2-HOSP-002 create-to-manage assessment

**Status:** Read-only assessment complete; **INCOMPLETE** for one valid input
class. No implementation is authorized by this assessment.

The existing operator workflow preserves reservation identity and status-action
continuity for today/future booked reservations. A valid past-date reservation
can also be created by the canonical service, but the operator board only
projects booked reservations with `check_in_date >= CURDATE()`. The operator
action discards the service-returned ID, so that valid record has no operator
focus/list continuation.

Recommend a separate bounded implementation decision for an owner-owned
post-create identity/focus or narrow lookup projection. Do not change the
canonical reservation write service, broaden Shell ownership, or alter date
semantics speculatively. The aggregate Hospitality runner remains prohibited
under current restrictions; safe individual probes only were used.

## 2026-10-21 — OH-AUTO2-HOSP-001 operator reservation creation

**Status:** Accepted and implemented within the bounded non-Core assignment.

Hospitality reservation creation is an intended v1 operator workflow for
assigned users with explicit `hospitality.manage`. The operator edge must reuse
`ReservationsService::create()` exclusively; it must not duplicate reservation
validation, overlap handling, SQL, transaction, or rollback behavior.

The operator form uses the existing active guest and eligible-room sources and
existing guest, optional room, planned dates, occupancy, rate, and note fields.
An empty room selection preserves the canonical service's allowed unassigned
reservation behavior. Assignment, operator-view eligibility, own-handle
confinement, ACL, and CSRF remain mandatory. Success and failure return to the
same user's jailed Hospitality workspace with localized feedback and no internal
error disclosure.

Verification is offline and isolated only. Database-backed probes, browser/HTTP
acceptance, activation, deployment, and live acceptance remain excluded; live
acceptance remains blocked under OH-AUTO2-119. Room/guest CRUD, reservation
modification/reassignment, new permissions, schema changes, shared-app
dependencies, audit redesign, and generic operator framework work remain out of
scope.
