# OH-AUTO2-GOV-003 — Cross-Workstream Product Readiness Prioritization

**Date:** 2026-10-21
**Baseline:** `29bea8f117c2d8f96c8aa8531c79d94f0d91a755`
**Authorization:** Read-only assessment, documentation, and isolated non-database verification
**Status:** Prioritization complete; proposal subsequently approved as `OH-AUTO2-HOSP-001`

## 1. Mission and boundary

This assessment reconciles the current product roadmap, assignment registry,
owner work/decision records, active briefs, and existing offline evidence across
Manufacturing, Hospitality, Procurement, Shell, Platform, SBAIO, and active
Studio/shared tooling.

It does not repeat completed audits, reopen accepted corrections, or treat
missing browser/database evidence as proof of an implementation defect. No
database, SQL, schema, migration, browser/HTTP runtime, activation, deployment,
Core, Fulfillment, Material, or SBAIO incident operation was performed.

Unrelated Procurement logs, backup, PHPUnit cache, and incident evidence remain
preserved.

## 2. Readiness classification

| Workstream/capability | Classification | Evidence-supported state |
|---|---|---|
| Hospitality operator reservation creation | **NEEDS DECISION** | The app-owned `ReservationsService::create()` has date, active-guest, optional-room, overlap-lock, transaction, commit, and rollback behavior; the admin controller already reuses it with manage + CSRF. However, operator reservation creation is explicitly deferred in `docs/active/hospitality-operator-actions-plan.md` and requires an approved operator UX/authority boundary. |
| Manufacturing Fulfillment dispatch lifecycle (`markReady`, `prepare`, `complete`) | **BLOCKED** | OH-AUTO2-MFG-002 records missing operator record scope, incomplete legal transitions, runtime schema provisioning, transaction/rollback boundaries, and audit integration. Existing admin actions are not an operator contract. |
| Manufacturing Material aggregate restoration | **BLOCKED** | OH-AUTO2-112 proved that module authority does not establish whole-domain record scope across Material, ledger, reservation, order, capacity, demand, and plan sources. OH-AUTO2-111 containment remains active. |
| Hospitality authenticated live acceptance | **BLOCKED** | Offline operator actions and composition are implemented and evidenced, but assigned-user browser/HTTP/database acceptance requires a provisioned isolated runtime and explicit database authorization. Existing DB-backed Hospitality probes remain prohibited. |
| SBAIO personnel/payroll/watchlist operational summaries | **BLOCKED** | OH-AUTO2-105/105R/105B remain under incident governance; the prior DB-backed probe impact is undetermined and no complete record-level authorization contract exists for sensitive summaries. |
| Shell account-panel extraction | **READY**, but deprioritized | `engineering/Shell/work.md` identifies an exact payload/parity extraction with no new route, schema, ACL, or capability authority. It is safe structural cleanup, not a meaningful unfinished customer/operator product capability. |
| LocalizationScanExtraction multi-locale migration | **READY for separate tooling planning** | The scanner, planner, English apply path, snapshots, rollback, and 43/43 probe exist; multi-locale support is listed as future tooling work. It is lower product value than an operator reservation workflow and has no current user-facing deficiency evidence. |
| Platform release/update apply | **BLOCKED** | Disposable release-cycle proof is complete, but production trust, approval, installed apply authority, and deployment remain explicitly gated. |

## 3. Ranked top five unfinished capabilities

### 1. Hospitality operator reservation creation — highest-value candidate

**User value:** High. A front-desk operator currently has an operator workboard
and several lifecycle/folio actions, but reservation creation remains on the
admin surface. Reservation creation is the natural intake step for the
Hospitality workflow and has an existing owner service rather than requiring a
new domain engine.

**Readiness:** Not independently authorized yet. The active operator action plan
explicitly lists reservation creation as deferred. The product decision must
define whether `hospitality.manage` is sufficient, whether the operator may
select active guests and rooms, how unassigned-room bookings are presented, and
whether the existing local reservation contract is the intended operator truth.

**Existing evidence:**

- `ReservationsController::create()` already requires Hospitality manage access
  and CSRF before delegating to `ReservationsService::create()`.
- `ReservationsService::create()` validates dates and active guests, locks an
  assigned room before overlap evaluation, and commits/rolls back its write.
- `probe_reservation_modification_integrity.php` proves the canonical create
  method, transaction/lock/rollback behavior, and absence of alternate direct
  reservation writers.
- Existing Hospitality operator action slices prove the assignment, own-handle,
  manage, CSRF, jailed redirect, and single-service-delegation pattern.

**Decision required:** Approve a new bounded Hospitality operator reservation
creation slice, or keep reservation intake admin-only. Approval must define the
operator form fields, active guest/room source, optional-room behavior, manage
authority, jailed redirect/flash contract, and offline proof scope. This is the
single decision that unlocks the highest-value candidate.

### 2. Manufacturing operator dispatch lifecycle — blocked

This is valuable because the operator can create dispatch entries but cannot
continue them through ready/prepared/completed states. It is not ready: MFG-002
found no operator record scope, complete state machine, transaction/audit
boundary, or safe reuse contract. It requires a new qualified Manufacturing
implementation decision, not a route exposure patch.

### 3. Manufacturing Material aggregate restoration — blocked

Restoring the aggregate would improve `/me` usefulness, but the existing
composite source crosses records without a complete scope mapping. The current
fail-closed suppression is the correct behavior. A future task needs a complete
owner-approved source-to-record-scope contract before implementation.

### 4. Hospitality live acceptance — environment-blocked

The Hospitality operator surface and actions have strong offline evidence, but
live acceptance is a separate value-producing activity only when an assigned
user, isolated HTTP/database environment, and explicit database authorization
exist. Repeating offline probes cannot close this blocker.

### 5. SBAIO sensitive operational summaries — incident/authority-blocked

Personnel, payroll, and watchlist summaries could provide product value, but
the existing record-level authorization contract is absent and OH-AUTO2-105R
has undetermined impact from an earlier DB-backed probe incident. No feature
restoration is safe until the incident and authority decisions are separately
resolved.

## 4. Selection decision

No meaningful product implementation is currently independently authorized.

The highest-value next implementation **proposal** is:

> **Hospitality operator reservation creation** — a bounded app-owned operator
> action that reuses `ReservationsService::create()`, existing
> `hospitality.manage` and CSRF authority, active guest/room options, own-handle
> `/u/{username}/*` confinement, and the established localized flash pattern.

This is not issued as an implementation assignment by GOV-003 because the
active plan explicitly defers it and the operator product contract has not been
approved. The exact unlock is a product/authority decision, not another audit.

Until that decision is made, the only technically ready item is Shell account
panel extraction, which is intentionally not selected because it is structural
cleanup rather than meaningful unfinished product functionality. No speculative
Manufacturing dispatch, Material, Hospitality live, SBAIO, or release work
should begin.

## 5. Proposed future implementation boundary after approval

If reservation creation is approved, the next task should be one bounded
Hospitality implementation with:

1. app-owned operator form and localized fields for active guest, optional active
   room, check-in/out dates, adults, children, rate, and note;
2. one jailed `/u/{username}/*` POST seam using the existing operator identity
   and assignment pipeline;
3. `hospitality.manage` and CSRF checks before `ReservationsService::create()`;
4. no duplicated validation, SQL, overlap logic, transaction, or lifecycle
   semantics;
5. localized success/error feedback and same-user redirect;
6. offline rendered-form and action-boundary coverage for invalid dates,
   inactive guest/room, overlap rejection, malformed IDs, missing permission,
   invalid CSRF, cross-handle requests, and read-only/display profiles;
7. reuse of the existing foundation/lifecycle probes without database execution.

Explicit exclusions: room/guest CRUD, reservation modification/reassignment,
new permissions, shared Billing/Parties dependencies, schema changes, audit
redesign, browser acceptance, live database verification, and generic operator
action framework work.

## 6. Acceptance criteria for the future decision

The product decision is sufficient to unlock implementation only when it states:

- operator reservation creation is an intended v1 workflow, not admin-only;
- `hospitality.manage` is the required mutation authority;
- active guest and room options are the canonical owner sources;
- unassigned-room booking remains allowed or is explicitly disallowed;
- existing `ReservationsService::create()` remains the sole write authority;
- own-handle operator confinement and CSRF are mandatory;
- offline proof is sufficient for the implementation slice while live acceptance
  remains a separate environment-bound prerequisite.

## 7. GOV-003 disposition

**Recommendation:** Record the Hospitality reservation-creation decision as the
next product decision. Do not issue another implementation assignment until that
decision is approved. Keep Manufacturing dispatch, Material restoration,
Hospitality live acceptance, SBAIO incident work, and production release apply
in their existing blocked states.

GOV-003 itself made no production-code, database, runtime, activation, or
deployment change.

## 8. Subsequent product decision

The planner subsequently accepted the reservation-creation proposal as
`OH-AUTO2-HOSP-001`. The approved contract keeps the boundary defined here:
assigned Hospitality operators with explicit `hospitality.manage`, active guest
and eligible-room sources, optional unassigned rooms where the canonical service
supports them, existing reservation fields, the existing service as sole write
authority, jailed own-handle routing, and offline isolated verification only.
Live acceptance remains blocked under legacy OH-AUTO2-119.
