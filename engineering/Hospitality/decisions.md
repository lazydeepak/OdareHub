# Hospitality — Decisions

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
