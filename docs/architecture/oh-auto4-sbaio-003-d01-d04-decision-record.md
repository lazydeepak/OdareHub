# OH-AUTO4-SBAIO-003 — D-01–D-04 Authorization Decision Record

**Date:** 2026-10-30
**Mode:** Read-only evidence reconciliation and documentation
**Status:** **PROPOSED — READY FOR OWNER APPROVAL; NOT APPROVED**
**Scope:** SBAIO authorization decisions D-01, D-02, D-03, and D-04 only

## 1. Authority boundary

This record evaluates the existing contracts and recommends bounded policy
outcomes. It does not approve policy, create authorization authority, enable
SBAIO personnel surfaces, or authorize implementation.

Planner authorization permits this evidence and decision analysis. It is not
Platform, ACL, organization/data-governance, or SBAIO business-owner approval.
Until the approving owners explicitly accept the decisions below, existing
SBAIO fail-closed containment remains the effective security behavior.

No code, Core/Auth/ACL behavior, schema, migration, database, route, provider,
identity binding, Staff eligibility, activation, deployment, or runtime
authorization change is included.

## 2. Evidence baseline

### 2.1 Platform organization and membership

- Platform Organization currently owns company, branch, fiscal, branding, and
  hierarchy management. Its permission boundary (`organization.view` and
  `organization.manage`) proves administrative access, not authenticated
  membership or business-record scope.
- The proposed `org_user_memberships` table explicitly binds `users.id` to
  `org_companies.id`, has lifecycle states (`active`, `inactive`, `revoked`),
  validity windows, actor fields, active uniqueness, and restrictive foreign
  keys. The migration comments explicitly reject importing assignments, roles,
  branches, Staff, email, or name data.
- `OrganizationMembershipRepositoryContract` and its implementation are
  intentionally evidence/persistence boundaries. They state that they do not
  resolve authentication, grant ACL access, or infer Staff identity.
- The Platform resolver and disposable repository proof establish a technical
  seam, including lifecycle, duplicate, cross-user, concurrency, and rollback
  evidence. They do **not** establish owner approval for SBAIO business-record
  authorization or production/runtime integration.

### 2.2 SBAIO Staff identity and eligibility

The Staff schema currently contains `id`, nullable `employee_code` and
`email`, free-form `employment_status` and `status` values, optional
`hire_date`/`join_date`/`exit_date`, and no `user_id`, organization, tenant, or
supervisor relation. The controller accepts Staff fields as ordinary module
data. Consequently:

- email, employee code, name, role labels, department, branch name, and current
  assignment data are not authoritative identity bindings;
- the current `active` defaults are storage defaults, not an approved
  employment-eligibility policy;
- an `exit_date` or free-form status cannot independently establish the full
  active/revoked/terminated semantics required for authorization;
- duplicate, stale, cross-organization, and ambiguous Staff candidates must
  remain denied until an owner-approved eligibility contract exists.

### 2.3 ACL and experience boundaries

- ACL owns server-side hard authorization: identity, roles, assigned apps,
  permissions, and scopes. The architecture plan explicitly says that layout
  or Workspace Profile decisions must never grant authorization.
- Current app grants and assignment/profile data can establish SBAIO/module
  capability access, but they do not identify which Staff record belongs to the
  authenticated user.
- Permission and profile resolution therefore remains one input to a future
  effective predicate, not a substitute for organization membership or Staff
  identity. The proposed intersection is:

  `authenticated identity ∩ organization membership ∩ Staff binding/eligibility
  ∩ capability permission ∩ record predicate ∩ route/surface authority`.

- Existing SBAIO containment correctly suppresses sensitive personnel, Leave,
  Timecard, Payroll, widget, export, and bulk-I/O behavior where this complete
  predicate is not proven.

## 3. Platform Option B evaluation

**Option B:** establish and approve a canonical Platform organization and
membership contract before SBAIO organization-bound Staff identity binding.

### Benefits

1. Keeps organization identity and membership evidence Platform-owned rather
   than creating a competing SBAIO tenant relation.
2. Uses explicit `users.id` membership evidence instead of email, employee code,
   assignment JSON, branch labels, or primary-company display defaults.
3. Preserves a clean boundary: Platform supplies organization evidence; SBAIO
   owns Staff binding and personnel meaning; ACL enforces hard access; Shell
   only renders authorized results.
4. Supports active uniqueness, revocation, lifecycle checks, transaction
   boundaries, and audit actor preservation already represented in the Platform
   design.

### Limitations and unresolved conditions

1. `org_companies.id` is a candidate canonical organization key, not an
   automatically approved business authority.
2. The membership repository is not, by itself, an ACL intersection or
   organization-wide query-scope mechanism.
3. No Staff-to-organization membership or user-to-Staff binding exists yet.
4. The current Staff status fields do not define eligible employment states.
5. Existing SBAIO tables and Timecard queries do not demonstrate complete
   organization enforcement or approval provenance.
6. Platform persistence/runtime integration is separately gated even though
   the DB-free resolver and disposable proof exist.

### Proposed conclusion

Option B is the **PROPOSED preferred direction**, subject to explicit approval
by Platform organization/governance, Platform identity/governance, ACL,
organization/data-governance, SBAIO Staff, and the relevant SBAIO business/data
owners. It must not be described as an approved tenant or authorization policy
before those owners decide.

## 4. Decision register — proposed outcomes

### D-01 — Binding ownership and identity authority

**Proposed policy:** Platform owns the contract for the authenticated
`users.id` reference and account-validity evidence. SBAIO Staff owns the
historical user-to-Staff binding policy, Staff eligibility, revocation, and
domain audit meaning. Use a separate historical binding relation; do not add a
single mutable identity shortcut to the Staff row.

**Authoritative implementation owner:** Platform owns the identity-reference
contract and organization evidence boundary. SBAIO Staff owns the binding
relation and Staff-domain policy. ACL owns any permission-token changes.

**Rejected alternatives:** email/employee-code matching, name matching,
browser-selected Staff IDs, assignment/profile inference, ACL-owned business
identity, or a mutable `sbaio_staff.user_id` shortcut as the sole history model.

**Security invariants:** exact authenticated `users.id`; no fallback identity;
one unambiguous active binding; server-resolved target IDs; revoked/inactive,
missing, duplicate, stale, or contradictory evidence denies; binding changes
are actor-attributed and auditable.

**Dependencies:** D-02 active uniqueness; D-03 organization authority; D-04
Staff eligibility; account-validity semantics; binding administration and audit
authority.

**Exact approval required:** Platform identity/governance owner, SBAIO Staff
owner, and ACL owner if the effective permission catalog changes. Organization/
data-governance approval is required because the binding is organization-bound.

### D-02 — Active cardinality and replacement

**Proposed policy:** Permit at most one active binding for each
`(organization, user)` and at most one active binding for each
`(organization, Staff)`. Replacement is revoke-plus-create, never an ordinary
update. Enforce uniqueness transactionally and at the database boundary once
implementation is separately authorized.

**Authoritative implementation owner:** Platform owns membership cardinality
for Platform user-organization memberships. SBAIO Staff owns cardinality and
history for the user-to-Staff binding relation, using the same organization
authority.

**Alternatives:** application-only duplicate checks, multiple active bindings,
nullable organization during migration, or silent reassignment. These are not
recommended because concurrent requests or stale evidence could select the
wrong Staff record.

**Security invariants:** active uniqueness survives concurrency; failed
replacement rolls back; old bindings remain revoked/history-preserving; no
successful response is emitted without committed identity/read-back evidence;
actor and reason are retained for revocation.

**Dependencies:** D-01 identity keys; D-03 canonical organization; D-04
eligibility; migration/recovery authority; disposable concurrency proof before
any runtime activation.

**Exact approval required:** Platform persistence owner for Platform membership
cardinality; SBAIO Staff/data owner for binding cardinality; organization/
data-governance owner for cross-domain uniqueness; ACL owner for any access
effect.

### D-03 — Organization authority

**Proposed policy:** Select Platform Option B. Treat organization identity and
explicit user membership as Platform-owned evidence only after the relevant
owners approve the contract. Do not infer organization from primary-company
display configuration, branch code/name, email domain, department, assignment
JSON, or existing Staff rows. A missing, inactive, revoked, parent-missing,
cross-user, duplicate, or contradictory membership denies.

**Authoritative implementation owner:** Platform Organization/governance owns
the organization and membership contract. Platform identity/governance owns the
relationship to authenticated `users.id`. ACL owns enforcement intersection;
SBAIO accepts and consumes the approved organization evidence for Staff/Leave.

**Alternatives:** reuse current `org_companies.id` without a membership contract
(unsafe and rejected); create an SBAIO-local tenant (competing authority and
rejected); organization-independent binding (requires an explicit global
security decision and is not proposed here).

**Security invariants:** explicit organization selection; membership belongs to
the authenticated user; parent organization is active; validity windows are
honored; organization evidence alone never grants SBAIO access; every sensitive
query/write/export/report predicate uses the same approved context.

**Dependencies:** Platform membership repository/runtime integration; ACL
intersection; Staff organization relation; D-01/D-02; existing database and
legacy-data reconciliation policy; separate OH-AUTO2-105B/105R boundaries.

**Exact approval required:** Platform Organization owner, Platform identity/
governance owner, ACL owner, organization/data-governance owner, and SBAIO owner.
Timecards and Payroll owners must approve downstream consumption before their
surfaces are restored.

### D-04 — Staff eligibility

**Proposed policy:** Bind only to an explicitly owner-approved eligible Staff
state with verified organization membership and no revocation, termination,
ambiguity, or stale evidence. Treat current free-form `employment_status` and
`status` values as evidence requiring policy interpretation, not as an
authorization contract. Until the state vocabulary and effective-date rules
are approved, unknown or contradictory values deny.

**Authoritative implementation owner:** SBAIO Staff owns Staff lifecycle and
eligibility semantics. Platform owns organization membership evidence. ACL
enforces the resulting hard intersection; data governance approves sensitive
personnel treatment.

**Alternatives:** accept any row with `status = active`, accept either of the
two current status fields independently, infer eligibility from `exit_date`, or
bind the first matching Staff row. These alternatives are unsafe because the
fields are free-form, duplicated, nullable in related identity fields, and not
organization-bound.

**Security invariants:** explicit allowlist of eligible states; effective-date
validation; terminated/revoked/inactive/unknown/duplicate candidates deny;
eligibility changes are auditable; no browser-selected Staff ID; no personnel
or Leave data is exposed merely because a Staff row is active-looking.

**Dependencies:** D-01/D-03 binding and organization; Staff lifecycle
vocabulary; supervisor/admin policy; legacy row treatment; Leave and Timecard
provenance decisions.

**Exact approval required:** SBAIO Staff owner and SBAIO personnel/data owner;
organization/data-governance owner for organization-bound eligibility; Platform
identity/governance and ACL owners for enforcement consequences. Payroll and
Timecards approve downstream use of eligibility where applicable.

## 5. Cross-module contradictions and resolution

| Contradiction | Evidence | Proposed reconciliation |
|---|---|---|
| Platform membership is described as an “approved organization-authority policy,” while SBAIO dependency records say Option B is pending owner approval. | `engineering/Platform/decisions.md` DBC-free resolver entry versus the OH-AUTO3-SBAIO-006 and OH-AUTO4-SBAIO-001 records. | Interpret Platform acceptance as limited to pure evidence resolution and disposable technical verification. It is not approval of cross-domain SBAIO authorization. |
| Platform migration references `org_companies.id`, while current architecture says it is not yet canonical for SBAIO. | `006_create_user_organization_memberships.sql` plus Platform/SBAIO decision records. | Treat the key as a proposed Platform persistence relationship. Do not treat it as SBAIO authorization truth until D-03 is approved. |
| ACL app grants include SBAIO capability access, while SBAIO requires record-level Staff authority. | `AclPolicy`, assignment/profile resolution, and SBAIO containment decisions. | Keep app/permission access as a necessary but insufficient predicate. Do not restore sensitive providers from app access alone. |
| Staff storage defaults use `active`, but D-04 requires an eligibility contract. | `apps/SBAIO/modules/Staff/install.php` and Staff controller fields. | Treat storage values as untrusted policy input until SBAIO Staff owners define the explicit eligible-state vocabulary and temporal rules. |
| A technically complete disposable membership proof could be mistaken for runtime approval. | Platform work/decision records and the owner-decision package. | Keep technical proof, owner policy approval, authenticated integration, and production activation as separate gates. |

## 6. Approval matrix and next gate

| Approval area | Required owner(s) | What must be explicitly accepted |
|---|---|---|
| Identity reference | Platform identity/governance | `users.id` authority, account-validity semantics, no fallback identity |
| Organization/membership | Platform Organization + organization/data governance | Option B, canonical organization meaning, membership lifecycle and scope |
| Hard authorization | ACL owner | Intersection of identity, membership, permission, record predicate, route/surface authority |
| Staff binding/eligibility | SBAIO Staff + SBAIO business/data owner | D-01/D-02 binding policy and D-04 eligible states/effective dates |
| Downstream data use | Leave, Timecards, Payroll owners | Whether and how approved identity/eligibility may affect each domain |

The next gate is an explicit owner response for D-01–D-04 with one of
`APPROVE`, `REJECT`, or `DEFER` per decision, including rationale and any
replacement policy. A Planner recommendation or technical acceptance must not
be recorded as that response.

## 7. Non-authorizations preserved

- No Staff identity binding or organization membership runtime integration.
- No Leave self-service, Leave read/write, approval, or lifecycle restoration.
- No Timecard, Payroll, report, export, widget, dashboard, bulk-I/O, or Data
  Exchange restoration.
- No ACL permission or role change, schema/migration, database operation, route,
  module activation, browser/HTTP test, deployment, or production operation.
- Existing SBAIO sensitive-surface containment and OH-AUTO2-105B/105R incident
  boundaries remain unchanged.

## 8. Source references

- `engineering/SBAIO/owner-decision-package.md`
- `engineering/SBAIO/personnel-authorization-restoration-plan.md`
- `engineering/SBAIO/decisions.md`
- `engineering/Platform/decisions.md`
- `engineering/Platform/work.md`
- `apps/Platform/modules/Organization/migrations/006_create_user_organization_memberships.sql`
- `apps/Platform/modules/Organization/Contracts/OrganizationMembershipRepositoryContract.php`
- `apps/Platform/modules/Organization/Services/OrganizationMembershipRepository.php`
- `apps/SBAIO/modules/Staff/install.php`
- `app/Core/AclPolicy.php`
- `plugins/Base/Services/UserDashboardAssignmentService.php`
- `docs/experience-composition-architecture-plan.md`
- `docs/architecture/SECURITY-POLICY.md`
- `docs/architecture/planner-worker-communication-governance.md`
