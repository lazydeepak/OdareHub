# SBAIO Personnel Authorization and Functional Restoration Plan

**Milestone:** `OH-AUTO3-SBAIO-AUTHORIZATION-RESTORATION-PLAN-001`
**Assessment date:** 2026-10-28
**Verified baseline:** `2ca75b8cd3ebeb7d0d64a0e1f4c13236da52222c`
**Repository:** `/Users/lazydeepak/dev/OdareHub`
**Status:** **GO for owner review; BLOCKED for implementation**
**Mode:** Read-only source and architecture investigation

## 1. Decision boundary

This document completes the planning milestone only. It does not approve a
policy, migration, permission, identity binding, module activation, database
operation, or runtime restoration.

Existing SBAIO containment remains authoritative until the relevant owner
decisions and acceptance gates below are complete. No identity may be inferred
from email, employee code, name, role label, department, branch, app access,
module assignment, or generic workspace scope.

### Verified repository identity

```text
Git root:    /Users/lazydeepak/dev/OdareHub
Remote:      https://github.com/lazydeepak/OdareHub.git
Branch:      main
HEAD:        2ca75b8cd3ebeb7d0d64a0e1f4c13236da52222c
origin/main: 2ca75b8cd3ebeb7d0d64a0e1f4c13236da52222c
```

The working tree contains unrelated modified and untracked artifacts. They are
not part of this plan and must not be staged, reverted, or deleted.

## 2. Verified architecture and missing contracts

### 2.1 Verified implementations

| Area | Repository evidence | Verified fact |
|---|---|---|
| Authenticated identity | `app/Core/Auth.php`, existing Auth callers | The authenticated application actor is represented by `users.id`. |
| SBAIO Staff storage | `apps/SBAIO/modules/Staff/install.php` | `sbaio_staff.id` exists with employment/status, department, branch, email, and employee-code fields. |
| Staff identity binding | Staff install schema and reviewed queries | No `user_id`, organization, tenant, or authoritative supervisor relation is established. |
| Leave storage | `apps/SBAIO/modules/Leave/install.php` | Leave has nullable `staff_id`, free-form status, `approved_by`, and `approved_at`; no immutable transition history is established. |
| Existing app/module access | SBAIO routes, manifests, and existing ACL checks | App/module access is an entry boundary, not record-level Staff or Leave authority. |
| Personnel containment | `ModuleReportRegistryService` and containment admissions | Personnel indexes, HTML reports, parity, CSV, and POST mutations fail closed where owner contracts are absent. |
| Dashboard/widget privacy | `SbaioDashboardPrivacyAdmissionService`, `SbaioOperatorWidgetPrivacyService` | Personnel/payroll dashboard and operator widget data are suppressed before sensitive reads. |
| Bulk-I/O containment | `SbaioBulkIoAdmissionService` | Suite import/export/restore/backup operations are unavailable before side effects. |
| Data Exchange admission | `SbaioDataExchangeAdmissionService`, `DataExchangeService` | SBAIO personnel adapters and queued approvals remain blocked pending a mutation contract; current-definition admission and terminal finalization checks exist. |
| Shell composition | Existing Shell contribution boundaries | Shell discovery/rendering is not an authorization source and remains unchanged. |
| Organization implementation | Platform Organization sources reviewed in OH-AUTO3-006 | Organization/company structures exist, but authenticated membership and SBAIO Staff scope are not proven as one canonical authority. |

### 2.2 Missing contracts blocking restoration

1. Authenticated `users.id` to `sbaio_staff.id` binding ownership, cardinality,
   history, revocation, and eligibility.
2. Canonical organization/tenant identity and membership authority.
3. Explicit employee, supervisor, administrator, report, export, widget, and
   bulk-operation record predicates.
4. Authoritative effective-dated supervisor/subordinate relation.
5. Finite Leave lifecycle, concurrency, idempotency, and immutable audit history.
6. Approval provenance sufficient for Timecard eligibility.
7. Legacy approved Leave and derived Timecard reconciliation policy.
8. Payroll confidentiality and organization-wide access policy.
9. Package/file/record scope and approval policy for import, export, restore,
   and backup download.

## 3. Restoration matrix

| Capability | Existing functionality | Current containment | Required authority | Missing dependencies | Restoration prerequisites |
|---|---|---|---|---|---|
| **Staff** | Staff table, admin routes, Staff fields, and Staff-owned service paths exist. | Personnel index, report, export, and mutation admissions fail closed. | Active authenticated-user binding, organization scope, explicit Staff read/write permission. | Binding relation; organization membership; Staff eligibility/revocation policy. | Approve Gate A; implement only binding governance; prove deny matrix and no fallback identity. |
| **Attendance** | Attendance schema/routes/services and daily operations exist. | Index, report, export, POST mutation, widgets, and Data Exchange contribution are contained. | Employee/self, supervisor, and admin predicates; organization and date/record scope; mutation audit. | Binding, supervisor relation, permission catalog, attendance ownership/provenance. | Gate B plus attendance-specific read/write contract; restore one surface at a time. |
| **Leave** | Leave records, create/holiday routes, status field, and Timecard consumption exist. | Index, report, export, and mutations are blocked; host data suppressed. | Employee self-scope; explicit supervisor/admin approval; server-owned lifecycle. | Binding, organization, finite transitions, history, overlap/cancellation, approval actor. | Approve Gates A–C; database-free lifecycle/concurrency proof; then disposable acceptance. |
| **Schedules** | Schedule templates/assignments and routes exist. | Index, report, export, and POST mutation are contained. | Organization, Staff/machine assignment scope, explicit planner/admin permissions. | Binding, organization relation, assignment semantics, audit and conflict rules. | Gate B; define schedule visibility and mutation predicates separately from Leave. |
| **Timecards** | Timecard generation/detail/approval services and routes exist; Leave status feeds generation. | Index, report, export, mutations, widgets, and Data Exchange are contained. | Verified Staff binding, organization scope, approved Leave provenance, approval authority. | Provenance/history, source/revision identity, replay/reversal policy, legacy quarantine. | Approve Gate D; prove only verified approved Leave is eligible; preserve finalized history. |
| **Payroll** | Payroll records/runs, salary references, parity, reports, and routes exist. | Index, report, parity, export, mutation, dashboard, and widgets are contained. | Confidential payroll scope, organization boundary, explicit payroll permissions, audit. | Binding, organization, confidentiality classification, period/run access policy. | Gate B plus payroll confidentiality decision; explicit projection and export review. |
| **Consolidated reports** | App-level report registry and suite report routes exist. | Personnel/payroll report descriptors return unavailable before SQL. | Cross-module record predicates with least-common-scope intersection. | All contributing module contracts; organization and payroll confidentiality. | Approve per-report owner and projection; no report restoration from app access alone. |
| **Dashboards** | SBAIO dashboard cards and summary providers exist. | Sensitive hero statistics/cards and central personnel/payroll report surface are suppressed; business-operation cards remain. | Same record-level visibility as underlying modules; no “empty” substitution for denied data. | Provider-specific contracts and resolved experience visibility. | Gate B plus provider proof; restore only approved cards with unavailable states. |
| **Exports** | Module CSV projections and shared export/history service exist. | Personnel/payroll CSV report keys are blocked before source lookup; formula and delivery safety remain. | Export-specific permission, row predicate, field projection, audit/history. | Record scope, payroll confidentiality, bulk approval policy. | Approve per-report export contract; test scope parity and formula/delivery regressions. |
| **Bulk I/O** | Suite import/export/restore/backup routes and shared services exist. | Eleven suite-level routes fail closed before session/controller/SQL/filesystem/package work. | Actor, organization, package/record scope, approval, audit, rollback authority. | Organization, package trust, backup/restore ownership, approval queue policy. | Gate E; disposable proof only; never restore by broad platform-admin status. |
| **Operator widgets** | Attendance, Timecard, Leave, Payroll widget registries and Shell adapters exist. | Summary cards/watchlists reject sensitive data before count/detail SQL. | Same employee/supervisor/admin read predicates and safe click-through authority. | Binding, scope, provider-specific projection, `/u/*` route authorization. | Gate B; provider proof; no widget may imply authorized empty data. |
| **Data Exchange** | Generic queue, adapters, approval flow, stored-file boundary, and finalization integrity exist. | SBAIO Attendance/Timecard adapters fail closed before upload/parse/source mutation; queued approval re-admits current owner definition. | Mutation permission, Staff/organization target scope, approval actor, claim ownership, terminal persistence. | Personnel mutation contract, bulk-I/O policy, provenance/audit, adapter-specific projection. | Approve E-003; restore only after execution admission, race, finalization, and actual disposable import proof. |

## 4. Numbered owner decisions

These are proposals for approval, not approved policies.

### Identity and organization

1. **Binding ownership:** Platform owns the contract for `users.id` validity;
   SBAIO Staff owns the business binding policy and historical relation.
   Recommended approach: a separate binding relation, not email matching or a
   single mutable Staff column.
2. **Active cardinality:** Permit exactly one active binding per organization
   and user, and exactly one active binding per organization and Staff record.
   Revoke-and-create replaces an active binding; ordinary update does not.
3. **Organization authority:** Reuse a verified Platform organization/membership
   authority if one is proven. Otherwise, define that Platform contract before
   adding SBAIO organization-bound records. Do not infer organization from
   branch, department, email domain, or current assignment JSON.
4. **Staff eligibility:** Approve employment/status states that may be bound;
   inactive, terminated, revoked, duplicate, stale, or ambiguous bindings fail
   closed.

### Visibility, supervision, and confidentiality

5. **Employee visibility:** Employee self-service may see only the active,
   organization-bound Staff record and records explicitly owned by it.
6. **Supervisor scope:** Use an explicit effective-dated subordinate relation;
   do not infer supervision from `role_label`, department, branch, `leader`, or
   generic app assignment.
7. **Organization-wide access:** Permit it only through an explicit
   organization-scoped permission and audited administrative predicate.
   `platform_admin` is not automatically sufficient.
8. **Payroll confidentiality:** Treat salary, payroll runs, fixed profiles, and
   parity fields as restricted financial/personnel data; approve separate read,
   export, mutation, and period/run permissions.
9. **Cross-surface parity:** Require indexes, reports, CSV, dashboards, widgets,
   and click-through links to use explicit predicates no broader than the
   underlying record authority.

### Mutations and approvals

10. **Leave creation:** Employee submission is server-owned as `submitted`; the
    browser cannot choose Staff, organization, status, approver, or actor.
11. **Leave lifecycle:** Use finite states (`draft`, `submitted`, `approved`,
    `rejected`, `cancelled`), legal transitions, version/lock checks, one
    transaction, idempotency, and append-only actor-attributed history.
12. **Approval authority:** Employee self-approval is forbidden. Supervisor and
    administrator approval require distinct permissions and proven scope.
13. **Timecard provenance:** Only approved Leave with verifiable approval
    history, active binding, organization/date scope, and stable source identity
    may affect Timecards.
14. **Legacy rows:** Preserve approved rows without provenance as
    `legacy_unverified` reconciliation findings; do not delete, rewrite,
    auto-approve, or silently treat them as newly eligible.

### Bulk and operational authority

15. **Bulk export:** Require explicit actor, organization, record, field, and
    audit predicates; CSV delivery success remains distinct from authorization.
16. **Import/restore/backup:** Require explicit package/file scope, approval,
    rollback, audit, and organization authority. Fail before file read/upload,
    parse, SQL, restore, or download when any evidence is missing.
17. **Data Exchange:** Require current owner admission, atomic claim ownership,
    target record scope, and verified terminal finalization. Uncertain execution
    must never report success or trigger automatic retry/recovery.

## 5. Prioritized implementation roadmap

Each item is intended to be a separate future assignment. None is authorized by
this plan.

| Assignment | Scope and dependency | Acceptance criteria | Required regressions |
|---|---|---|---|
| P0 — Owner decision closure | Record decisions 1–4 and identify canonical organization authority. | Signed owner decisions; unresolved alternatives explicitly rejected or deferred. | Static source reconciliation; no policy inferred from existing roles. |
| P1 — Identity-binding contract | After P0, define physical model and resolver/admin boundaries only. | One-active cardinality, revocation/history, server actor IDs, no fallback identity, no Leave imports. | Pure identity matrix: missing/duplicate/revoked/stale/inactive/mismatch and concurrency cases. |
| P2 — Disposable identity acceptance | After P1 and separate database authorization. | Isolated source/database, actual uniqueness under concurrency, rollback and cleanup evidence. | No installed DB; source/data fingerprints and owned-resource cleanup. |
| P3 — Record authorization predicates | After P1; supervisor relation decision required for supervisor paths. | Central predicates for employee/supervisor/admin/read/report/export; deny-first behavior. | Cross-surface deny matrix and no broad reads on missing scope. |
| P4 — Leave lifecycle | After P3 and lifecycle decisions 10–12. | Server-owned submission, legal transitions, history, idempotency, stale-version rejection. | Self-approval denial, overlap/cancellation/rejection, concurrent transitions, audit atomicity. |
| P5 — Timecard provenance/reconciliation | After P4 and decisions 13–14. | Verified approval eligibility; legacy quarantine; replay/reversal evidence. | Duplicate/replay, cancellation, finalized-history preservation, legacy exclusion. |
| P6 — Individual read surfaces | After P3; each owner approves its projection. | Restore only selected Staff/Attendance/Leave/Schedules/Timecard/Payroll indexes/reports. | Projection privacy, route authority, no inferred empty state, HTML parity. |
| P7 — Exports and dashboards/widgets | After P6 plus payroll/export decisions. | Explicit fields, same row predicate, restricted financial data, safe click-through. | CSV formula/delivery, export history, operator confinement, responsive host regressions. |
| P8 — Mutations and approvals | After P4 and P6; no bulk restoration implied. | Per-module POST authority, CSRF, ownership, lifecycle, audit, transaction behavior. | Malformed IDs/types, cross-user/organization denial, duplicate/idempotency, rollback. |
| P9 — Bulk I/O and Data Exchange | After P3/P8 and decisions 15–17. | Per-operation actor/package scope, approval, claim/finalization, rollback. | Admission before side effects, race, uncertain finalization, disposable import/restore. |
| P10 — Release/activation | After all selected surfaces pass disposable acceptance. | Independent backup/restore, monitoring, release authority, explicit activation approval. | Authenticated disposable HTTP matrix; production remains separate and explicit. |

## 6. Global regression and safety requirements

Every future assignment must preserve:

- current SBAIO personnel, payroll, dashboard, report, export, widget, bulk-I/O,
  and Data Exchange containment probes;
- existing Customers, Tasks, Notices, Expenses, and Sales behavior;
- Shell operator confinement, display read-only behavior, and app-owned route
  ownership;
- Core lock and absence of Studio runtime dependencies;
- no installed database contact and no implicit production target;
- no automatic retry, recovery mutation, legacy rewrite, or destructive cleanup;
- explicit unavailable states rather than broad reads or falsely empty results.

## 7. Independent verification record

The plan was checked against:

- `apps/SBAIO/AGENTS.md` and `apps/SBAIO/modules/AGENTS.md` ownership rules;
- `engineering/SBAIO/overview.md`, `rules.md`, `work.md`, and `decisions.md`;
- `docs/architecture/oh-auto3-002-sbaio-staff-identity-leave-authorization-contract-2026-10-22.md`;
- `docs/architecture/oh-auto3-004-sbaio-identity-leave-architecture-2026-10-24.md`;
- `docs/architecture/oh-auto3-005-sbaio-architecture-decision-closure-2026-10-24.md`;
- current SBAIO admission/containment services, module routes, install schemas,
  and recorded database-free regression evidence.

No installed database, schema inspection through a live connection, runtime
execution, browser/HTTP acceptance, module activation, deployment, or policy
implementation was performed.

## 8. Final verdict

**GO:** Documentation is complete for owner review and decision reconciliation.

**BLOCKED:** Implementation and functional restoration remain blocked by the
canonical organization authority, Staff identity binding, supervisor scope,
permission/record predicates, payroll confidentiality, Leave lifecycle and
approval provenance, legacy Timecard treatment, and bulk-I/O authority.

The next valid action is Planner-led owner review. Recovery and continuation
must begin with Planner; this Worker plan does not initiate implementation.
