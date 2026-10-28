# SBAIO Owner Decision Package

**Milestone:** `OH-AUTO4-SBAIO-001`
**Related plan:** `engineering/SBAIO/personnel-authorization-restoration-plan.md`
**Verified source:** `f203ff3f4162f37d61bd0257bf8f23c32ce3d75c`
**Status:** **Decision-ready; implementation BLOCKED**
**Mode:** Documentation-only owner preparation

## 1. Authority boundary

This package converts the 17 restoration proposals into an owner-review
surface. It approves no policy and authorizes no implementation, migration,
permission, identity binding, database operation, module activation, or runtime
restoration.

**Safe technical recommendation** means engineering can recommend a security-
preserving default, but the named owner must still accept it. **Business-owner
authority required** means engineering must not choose the business meaning,
access breadth, payroll treatment, workflow, or historical-data policy.

Until a decision is explicitly approved, existing SBAIO containment remains in
force and missing or contradictory evidence fails closed.

## 2. Verified starting point

- `users.id` is the authenticated application identity candidate.
- `sbaio_staff.id` exists, but Staff has no verified `user_id`, organization,
  tenant, or supervisor relation.
- Leave stores nullable `staff_id`, free-form `leave_status`, `approved_by`, and
  `approved_at`, without verified immutable approval history.
- App/module access is an entry boundary, not record-level personnel authority.
- Personnel indexes, reports, exports, mutations, dashboards, widgets, parity,
  bulk I/O, and SBAIO Data Exchange paths are contained where contracts are
  absent.
- Platform organization structures exist, but canonical authenticated
  membership and SBAIO Staff scope are not proven.

Evidence reviewed includes current SBAIO install schemas, routes, admission
services, recorded probes, the restoration plan, and the OH-AUTO3-002/004/005
architecture records. No installed database or live runtime was used.

## 3. The 17 owner decisions

| ID and classification | Recommended default | Alternatives and security consequence | Dependencies and approving authority |
|---|---|---|---|
| **D-01 Binding ownership** — Safe technical recommendation | Platform owns `users.id` validity; SBAIO Staff owns the historical user-to-Staff relation. Use a separate binding relation. | Email, code, name, or ACL-owned binding is rejected: ambiguous identity or ownership transfer. A single mutable Staff column is not recommended because it weakens history. | D-02–D-04; Platform identity/governance, SBAIO Staff, and ACL if permissions change. |
| **D-02 Active cardinality** — Safe technical recommendation | Exactly one active binding per organization/user and organization/Staff; replacement is revoke-plus-create. | Multiple active bindings or application-only uniqueness can disclose or mutate the wrong record under concurrency. | D-01, database-enforced uniqueness, revocation audit; SBAIO Staff and Platform. |
| **D-03 Organization authority** — Business-owner authority required | Reuse a verified Platform organization/membership authority; otherwise define that Platform contract before SBAIO organization-bound records. | SBAIO-local tenant, nullable organization, branch/department/email inference, or assignment JSON creates competing or false scope. | D-01, all predicates, payroll, reports, exports, bulk I/O; Platform organization and data owners with SBAIO acceptance. |
| **D-04 Staff eligibility** — Business-owner authority required | Bind only explicitly approved active employment states; inactive, terminated, revoked, duplicate, stale, or ambiguous candidates remain unbound. | Binding any `active` row without confirmed semantics risks overbinding; retaining terminated access risks disclosure. | D-01–D-03 and Staff lifecycle semantics; SBAIO Staff and organization owner. |
| **D-05 Employee visibility** — Safe technical recommendation | Employee sees only the active organization-bound Staff record and records explicitly owned by it; target IDs resolve server-side. | App-wide visibility or browser-selected `staff_id` is rejected because app access and client presentation do not establish ownership. | D-01–D-03 and module predicates; SBAIO Staff/Leave and Platform/ACL. |
| **D-06 Supervisor scope** — Business-owner authority required | Use an explicit organization-bound, effective-dated subordinate relation; delegation requires separate bounded audit evidence. | Role labels, department, branch, `leader`, or generic assignment inference is rejected as overbroad and non-deterministic. | D-03, permissions, Leave approval; SBAIO Staff/Leave, organization owner, Platform/ACL. |
| **D-07 Organization-wide/admin access** — Business-owner authority required | Require explicit organization-scoped permission and audited predicate; `platform_admin` is not an automatic business-record bypass. | Universal platform-admin or app-admin access silently broadens sensitive data. | D-03, D-06, D-08, payroll policy; SBAIO, Platform/ACL, data governance. |
| **D-08 Permission composition** — Safe technical recommendation | Effective access is the intersection of authenticated identity, organization scope, capability permission, record predicate, and route/surface authority. Profiles shape presentation only. | Permission-only, role-only, or Workspace Profile authority cannot identify records and is rejected. | D-01–D-07 and existing ACL/experience contracts; Platform/ACL and SBAIO. |
| **D-09 Payroll confidentiality** — Business-owner authority required | Classify salary, payroll runs, fixed profiles, and parity as restricted financial/personnel data; separate read, calculate, approve, export, modify, and period/run permissions. | Treating payroll as ordinary Staff data or granting supervisor access by relation alone risks financial disclosure. | D-03, D-07, D-08, export/audit contracts; Payroll, data governance, Platform/ACL. |
| **D-10 Leave mutation authority** — Business-owner authority required | Employee creates only a server-owned `submitted` request for the bound Staff identity; browser cannot choose Staff, organization, status, approver, or actor. | Client-selected status or direct approved creation permits self-approval and illegal transitions. | D-01–D-08, D-11, date/overlap policy; Leave and Platform/ACL. |
| **D-11 Leave lifecycle/approval** — Business-owner authority required | Use finite `draft`, `submitted`, `approved`, `rejected`, `cancelled` states with legal transitions, version/lock checks, one transaction, idempotency, and append-only actor history. | Free-form status, inferred supervisor approval, self-approval, or rewriting approved state destroys authorization evidence. | D-03, D-06, D-07, D-10 and audit; Leave, supervisor/business owner, Platform/ACL. |
| **D-12 Timecard provenance** — Safe technical recommendation | Only Leave with verified approval history, active binding, organization/date scope, current approved state, and stable source/revision identity affects Timecards. | Trusting `leave_status = approved` or free-form `approved_by` text permits unauthorized payroll/time effects. | D-01–D-03, D-11, D-13; Leave, Timecards, Platform audit. |
| **D-13 Legacy reconciliation** — Business-owner authority required | Preserve no-provenance approved rows as `legacy_unverified`; exclude them from new automatic eligibility; preserve finalized Timecards and use governed adjustments. | Trusting, deleting, rewriting, or auto-approving legacy rows either preserves unsafe authority or destroys evidence. | D-12, source/revision identity, replay/reversal; Leave, Timecards, data governance. |
| **D-14 Reports/exports** — Business-owner authority required | Each report/export has explicit row predicate, field projection, organization scope, confidentiality classification, permission, and audit history; consolidated reports use scope intersection. | Reusing index access, app access, or unrestricted projection can disclose fields or rows not visible in the source surface. | D-03, D-07–D-09, module projections; module owners, Platform/ACL, data governance. |
| **D-15 Dashboards/widgets** — Safe technical recommendation | Use the underlying module predicate; denied data renders unavailable, not an inferred empty state; click-through requires route authority and `/u/*` confinement. | Aggregate-only widgets and profile-controlled access can create side channels. | D-05–D-09, D-14, operator/display contracts; SBAIO owners, Platform/ACL, Shell only for rendering. |
| **D-16 Bulk I/O** — Business-owner authority required | Require actor, organization, package/file, record, approval, audit, and rollback authority before import, export, restore, or backup download. | App access or platform-admin status alone is rejected; bulk errors amplify scope mistakes. | D-03, D-07–D-09, package trust, backup/restore ownership; SBAIO, Platform/ACL, data governance, release owner. |
| **D-17 Data Exchange** — Technical recommendation plus business approval | Retain current owner admission, current-definition re-admission, atomic claim ownership, and verified terminal finalization. Uncertainty never reports success or retries automatically. | `supports_import`, queued definitions, in-memory success, or automatic recovery alone are rejected. | D-03, D-08, D-14, D-16, mutation/provenance/audit; SBAIO owners, Shell execution owner, Platform/ACL. |

## 4. Recommended decision order

1. **D-01–D-04:** identity, active cardinality, organization, and Staff eligibility.
2. **D-05–D-09:** employee/supervisor/admin scope, permission composition, and
   payroll confidentiality.
3. **D-10–D-13:** Leave mutation/lifecycle, approval provenance, and legacy
   Timecard treatment.
4. **D-14–D-15:** reports, exports, dashboards, widgets, and click-throughs.
5. **D-16–D-17:** bulk I/O and Data Exchange execution/finalization.

Later decisions must not be treated as settled while earlier identity or scope
decisions remain unresolved. A rejection, deferral, or contradiction keeps all
dependent functionality blocked.

## 5. Implementation dependencies after approval

These are future assignments, not current authorization:

| Assignment | Prior decisions | Narrow output | Acceptance gate |
|---|---|---|---|
| Identity-binding contract | D-01–D-04 | Resolver/admin and physical-model proposal | Pure deny matrix, uniqueness, revocation, no fallback identity. |
| Disposable identity proof | Approved identity contract plus separate DB authorization | Isolated migration/runtime proof | Concurrent uniqueness, rollback, cleanup, no installed DB. |
| Record predicates | D-01–D-09 | Employee/supervisor/admin/read/report/export predicates | Missing or ambiguous evidence denies before broad reads. |
| Leave lifecycle | D-05–D-11 | Server-owned lifecycle/history contract | Transition, concurrency, idempotency, self-approval denial. |
| Timecard provenance | D-12–D-13 | Eligibility/reconciliation contract | Legacy quarantine, replay, reversal, finalized-history preservation. |
| Surface restoration | D-05–D-15 | One approved read/report/export/widget slice | Projection privacy, route, host-surface, and unavailable-state regressions. |
| Bulk/Data Exchange | D-08, D-14, D-16–D-17 | One operation/adapter contract | Admission before side effects, race, finalization, rollback. |
| Activation | All applicable decisions and disposable evidence | Explicit release decision | Backup, monitoring, rollback, and separate production approval. |

## 6. Final status

**Package:** Ready for owner review.
**Authorization contract:** Not approved.
**Implementation and sensitive SBAIO restoration:** BLOCKED.
**Planner continuity:** Further work must begin with Planner direction; this
package does not authorize implementation or activation.
