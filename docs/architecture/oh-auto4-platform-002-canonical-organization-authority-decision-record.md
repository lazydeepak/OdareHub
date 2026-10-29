# OH-AUTO4-PLATFORM-002 — Canonical Organization Authority Decision Record

**Date:** 2026-10-30
**Status:** **Decision-ready proposal; not approved**
**Mode:** Documentation and Git reconciliation only

## 1. Assignment determination

No repository record, commit, or active work entry identifies
OH-AUTO4-PLATFORM-002 as already completed or actively executing. The
preceding OH-AUTO4-PLATFORM-001 reconciliation is present in the working tree
and establishes that the bounded Platform foundation already exists.

This record prepares the next owner decision. It does not authorize runtime
integration, schema execution, database use, migration execution, ACL changes,
or any application implementation.

## 2. Existing verified foundation

The following existing implementation is the baseline and must not be
duplicated:

- `apps/Platform/Services/OrganizationMembershipResolver.php`
- `apps/Platform/Services/OrganizationMembershipContext.php`
- `apps/Platform/Services/AuthenticatedOrganizationContextResolver.php`
- `apps/Platform/modules/Organization/Contracts/OrganizationMembershipRepositoryContract.php`
- `apps/Platform/modules/Organization/Services/OrganizationMembershipRepository.php`
- organization migrations `001` and `006`

Provenance culminates in `d2bd92f`.

Verified database-free evidence:

- Membership authority probe: **30/30**
- Authenticated context probe: **16/16**
- Focused PHP lint: **PASS**

The historical disposable evidence is authorizer **24/24** and repository
probe **17/17**. The guarded repository probe was not rerun for this decision
record because independently attested `OH_ORG` disposable-database evidence was
not available.

## 3. Proposed canonical contract

The following is the recommended decision shape. Every item remains
**PROPOSED** until its named owner approves it.

### 3.1 Identity

**PROPOSED:** Core/Auth remains authoritative for the authenticated account
identity, represented by positive `users.id`. Platform must never derive
organization membership from email, name, role, assignment, branch, employee
code, `primaryCompany()`, or Staff attributes.

**Approval owner:** Core/Auth owner for the identity invariant; Platform owner
for its consumption boundary.

### 3.2 Organization authority

**PROPOSED:** `org_companies.id` is the canonical organization identifier,
subject to Platform owner confirmation that the table’s identity, lifecycle,
deletion, and ownership semantics are authoritative.

If Platform does not approve this source, it must name the replacement authority
and define a bounded adapter before any downstream consumer is authorized. No
SBAIO-local tenant identity or inferred global single-organization fallback is
acceptable without a separate explicit security decision.

**Approval owner:** Platform Organization owner.

### 3.3 Membership authority

**PROPOSED:** Platform owns the sole server-side explicit relation represented
by `org_user_memberships`, keyed by `users.id` and organization ID. It must
support zero, one, or multiple memberships; explicit organization selection;
active/inactive/revoked lifecycle; validity windows; attributable lifecycle
actors; and at most one active membership per `(user_id, organization_id)`.

Membership evidence is necessary but does not grant application permission.
Missing parent organization evidence, malformed lifecycle state, duplicate
active evidence, stale validity, revoked/inactive membership, and contradictory
rows deny closed.

**Approval owner:** Platform Organization owner, with persistence/migration
review by the Platform database authority.

### 3.4 Resolved context

**PROPOSED:** The existing immutable `OrganizationMembershipContext` is the
only organization context object exposed to approved consumers. It contains
the authenticated user identity, explicitly selected organization, and
evidence fingerprint. It does not contain Staff identity or application
permissions.

**Approval owner:** Platform owner.

### 3.5 ACL intersection

**PROPOSED:** ACL enforcement must be an intersection, never a broadening:

```text
authenticated users.id
AND active selected organization membership
AND app/module permission
AND owner-specific record scope
AND Staff eligibility/binding where required
```

Organization membership alone must not grant an app, module, route, record,
Staff, Leave, or Timecard permission. ACL must reject missing or contradictory
organization context before broad business reads or writes.

**Approval owner:** ACL owner, with Platform providing authoritative context and
each app/module owner defining its record-scope contract.

## 4. Downstream ownership and dependencies

| Decision/dependency | Owner | Required before runtime closure |
|---|---|---|
| Authenticated `users.id` contract | Core/Auth | Confirm existing identity source remains authoritative |
| Organization identifier and lifecycle | Platform Organization | Approve `org_companies.id` or name replacement authority |
| Membership persistence and actors | Platform Organization/database authority | Approve relation, constraints, lifecycle, migration/recovery policy |
| Permission intersection | ACL owner | Define and verify organization-aware allow/deny behavior |
| Record-level scope | Each app/module owner | Supply owner-specific scope; no generic inference |
| Staff eligibility and user-to-Staff binding | SBAIO owner with Platform evidence | Separate approval; no Staff inference from membership |
| Leave/Timecard consumption | Their respective owners | Separate provenance-safe contracts after Staff binding |
| Disposable DB verification | Test/release governance | Fresh independently attested `OH_ORG` evidence |
| Installed/runtime acceptance | Release/runtime governance | Separate authorization; not implied by probes |

## 5. Security invariants

The decision must preserve all of the following:

1. Deny by default for missing, malformed, stale, revoked, inactive,
   contradictory, duplicate, cross-user, or cross-organization evidence.
2. Explicit organization selection is required, even with one eligible
   membership.
3. No browser-supplied Staff or organization value bypasses server validation.
4. Membership never widens ACL permissions or record scope.
5. No silent legacy backfill or historical Staff/Leave/Timecard rewrite.
6. Shell, Workspace Profile, and presentation layers remain consumers only.
7. Platform Option B and SBAIO D-01–D-04 remain proposals, not approvals.

## 6. Decision choices

| Choice | Recommendation | Consequence |
|---|---|---|
| Approve `org_companies.id` + explicit membership relation | **Recommended** | Enables a bounded Platform authority decision, followed by separately approved ACL integration |
| Require a replacement Platform authority | Available alternative | Platform must define identity/lifecycle/adapter semantics before consumers proceed |
| Defer canonical authority | Safe fallback | Keep all runtime organization, tenant, Staff, Leave, and Timecard consumers blocked |
| Infer from assignments, roles, branch, Staff, or primary company | **Rejected** | Violates existing fail-closed authority boundaries |

## 7. Explicit non-approvals

This record does not approve:

- Platform Option B;
- SBAIO D-01–D-04;
- Core/Auth/ACL modifications;
- schema or migration execution;
- installed or production database access;
- runtime route, permission, tenant, Staff, Leave, or Timecard integration;
- deployment, activation, or live/browser acceptance.

## 8. Recommended owner action

Platform Organization should approve or reject the proposed canonical authority
in a signed/recorded owner decision. If approved, ACL and affected app/module
owners must separately approve the intersection and record-scope contracts.
Only then should a narrowly scoped implementation plan be opened. Until those
decisions exist, preserve the current DB-free foundation and fail-closed
runtime boundary; do not add another resolver, infer organization ownership,
or execute database operations.

## 9. Reconciliation evidence

- `docs/architecture/oh-auto4-platform-001-organization-membership-foundation-reconciliation.md`
- `docs/architecture/oh-auto3-org-contract-001-canonical-organization-authority.md`
- `docs/architecture/oh-auto3-org-contract-001-persistent-membership-implementation-authorization-package.md`
- `docs/architecture/oh-auto3-org-runtime-plan-001-authoritative.md`
- `engineering/Platform/work.md`
- `engineering/Platform/decisions.md`
