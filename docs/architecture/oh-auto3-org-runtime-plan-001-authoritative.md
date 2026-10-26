# OH-AUTO3-ORG-RUNTIME-PLAN-001 — Organization Authorization Runtime Integration and Staff Binding

**Date:** 2026-10-26
**Mode:** Consolidated owner-decision and implementation-readiness record
**Status:** **READY FOR OWNER REVIEW / BLOCKED FOR IMPLEMENTATION**
**Baseline:** `d2bd92fc78b067eccda8b3bfac3b6decbc30543f`
**Authority:** Documentation record only; no recommendation in this document is approved by its inclusion.

## 1. Purpose and provenance

This document is the authoritative repository record for the completed
organization authorization runtime plan and its dependency on SBAIO Staff
binding. A repository search found no existing file named
`OH-AUTO3-ORG-RUNTIME-PLAN-001`, no equivalent `ORG-RUNTIME` document, and no
owner-supplied revision available for exact reconciliation. This record therefore
consolidates the existing recommendations and evidence without claiming that a
missing source document was reviewed.

It reconciles:

- the accepted canonical organization authority contract;
- the verified Platform organization-context seam at `d2bd92f`;
- the Platform persistent-membership authorization package;
- the SBAIO identity, Leave, and Timecard architecture;
- the SBAIO decision closure and dependency-resolution records; and
- the independent `OH-AUTO3-ORG-CONTEXT-VERIFY-001` verification result.

This is not an implementation authorization, schema authorization, migration
authorization, runtime activation approval, or production release approval.

## 2. Decision-state vocabulary

The following labels are deliberately separate:

| State | Meaning |
|---|---|
| **Accepted policy** | An existing owner/architecture boundary that must not be reopened without contradictory evidence. |
| **Verified implementation** | Repository code or a probe result that exists and was verified; it does not authorize its next consumer. |
| **Proposed architecture** | A recommended design preserved for owner review; it is not approved merely because it is specific. |
| **Pending owner decision** | A decision that must be explicitly approved by the named owner before implementation or downstream use. |
| **Blocked** | Work that must not proceed under the current authorization state. |

## 3. Accepted policy and closed boundaries

These points are already established by the canonical organization contract and
related owner records. They are not duplicate approval requests:

1. Core/Auth remains the authority for authenticated `users.id`.
2. `sbaio_staff.id` remains the Staff identity authority.
3. Platform owns organization identity and user-membership semantics.
4. SBAIO owns Staff eligibility and the historical user-to-Staff binding policy.
5. ACL owns hard allow/deny and consumes organization evidence; membership does
   not grant application permission by itself.
6. Shell, Workspace Profile, and presentation layers must not resolve
   organization or Staff identity.
7. Email, name, employee code, branch, department, role, app assignment,
   `primaryCompany()`, first-row selection, and browser-supplied Staff identity
   are not identity or membership evidence.
8. Missing, ambiguous, duplicate, inactive, revoked, stale, contradictory,
   cross-user, or cross-organization evidence fails closed.
9. Historical Staff, Leave, and Timecard records must not be silently rewritten;
   Leave and Timecard integration are separate later stages.
10. Automatic legacy organization or Staff backfill is not permitted. Ambiguous
    records remain unbound for explicit review.

The accepted policy does not by itself approve the physical schema, runtime
caller, ACL query integration, Staff binding, Leave, Timecard, migration,
deployment, or activation.

## 4. Verified repository evidence

### 4.1 Platform DB-free milestone

At baseline `d2bd92f`, the following Platform components exist:

- `OrganizationMembershipContext` — immutable opaque context containing the
  authenticated user identity, selected organization, and evidence fingerprint;
- `OrganizationMembershipResolver` — pure fail-closed membership/lifecycle
  resolver;
- `AuthenticatedOrganizationContextResolver` — Platform composition seam that
  reads the existing Auth identity and repository contract, requires explicit
  organization selection, and delegates authoritative validation;
- the existing membership repository contract and persistent repository source;
- the database-free probes listed below.

The verified seam does not call ACL, resolve Staff, grant permissions, alter
Core/Auth/session behavior, or integrate Leave/Timecard.

### 4.2 Independent verification evidence

The verification milestone `OH-AUTO3-ORG-CONTEXT-VERIFY-001` reported:

| Evidence | Result | Meaning |
|---|---:|---|
| Authenticated organization-context probe | **16/16** | Identity, explicit selection, lifecycle, repository/authentication failures, cross-user denial, and evidence handling pass. |
| Pure membership authority probe | **30/30** | Membership lifecycle, validity windows, duplicates, cross-user evidence, contradictory evidence, and no-Staff-context rules pass. |
| Targeted PHP lint | **All selected files pass** | No syntax defect found in the reviewed Platform seam/probes. |
| Architecture gates | **PASS** | Repository architecture checks pass; this does not authorize runtime integration. |
| Deletion family gates | **PASS (28/28)** | Existing deletion-family checks pass. |
| `git diff --check` | **PASS** | No whitespace defect found. |
| Repository disposable probe in the independent run | **Blocked, exit 2** | The guard reported missing governed `OH_ORG` database entry-point evidence and refused bootstrap/SQL. |

The persistent-membership authorization package separately records a prior
governed disposable authorizer result of **24/24** and repository probe result
of **17/17**. The independent verification run did not repeat that database
operation because current authorization evidence was unavailable. Neither result
authorizes installed-database use or runtime integration.

### 4.3 Evidence limits

The repository evidence verifies a Platform boundary, not an end-to-end
authorization path. The following remain unverified or separately blocked:

- authenticated HTTP/runtime caller acceptance;
- ACL organization intersection and query-scope enforcement;
- Staff-to-organization membership;
- user-to-Staff binding;
- Leave and Timecard use;
- installed database execution;
- deployment, activation, and production authorization readiness.

## 5. Proposed target architecture

The recommended runtime decision remains an intersection, never a broadening:

```text
authenticated account
AND active organization membership
AND application/module permission
AND owner-specific record scope
AND Staff eligibility/binding where required
```

The proposed resolution chain is:

1. Core/Auth supplies the authenticated `users.id`.
2. Platform loads membership evidence for that exact user.
3. The caller supplies an explicit organization selection.
4. Platform resolves and validates the opaque organization context.
5. ACL independently intersects the context with permission and record scope.
6. SBAIO resolves an active, eligible Staff binding only after the approved
   organization context exists.
7. Leave and Timecard consumers use only separately approved, provenance-safe
   contracts.

No layer may skip to Staff, Leave, or Timecard through a descriptive attribute or
presentation assignment. A missing or ambiguous prerequisite denies before broad
business reads or writes.

### 5.1 Organization identity recommendation

**Recommendation, pending approval:** approve `org_companies.id` as the canonical
organization identifier, subject to Platform confirmation of its identity,
lifecycle, deletion, and membership semantics. The persistent-membership package
already uses this key, but its use as policy authority must be explicitly
confirmed; the existence of the table alone is not sufficient.

Alternative: a separate Platform authority may wrap or replace the company table.
Rejected: SBAIO-local tenant identity or inferred organization values.

### 5.2 Membership recommendation

**Recommendation, pending approval:** Platform owns the sole server-side
user-membership relation and repository. It must support zero, one, or multiple
memberships; active/revoked lifecycle; validity evidence; attributable history;
one active membership per `(user, organization)`; and explicit selection.
Membership alone must not grant application permission.

### 5.3 Context-selection recommendation

**Recommendation, pending approval:** require a positive explicit organization
selection for every authenticated context resolution, including one eligible
membership. Selection must be checked against the authenticated user’s active
membership set. Missing, cross-user, non-member, stale, or inactive selection
denies.

Alternative: allow deterministic selection when exactly one membership exists.
This is less explicit and must not be adopted without an owner decision.

### 5.4 Staff organization and binding recommendation

**Recommendation, pending approval:** Platform supplies the canonical organization
key and validity contract; SBAIO owns Staff membership/eligibility and a separate
historical user-to-Staff binding relation. Conceptual fields are:

```text
organization_id, user_id, staff_id, binding_status,
effective_from, revoked_at, revoked_by_user_id, revocation_reason,
created_at, created_by_user_id, binding_version/audit evidence
```

Required invariants are one active `(organization, user)` binding, one active
`(organization, Staff)` binding, immutable revoked history, transactional
revoke-and-create replacement, server-derived actors, and no browser identity
input. The exact table name, column types, indexes, lock ordering, and migration
filename are implementation details after policy approval.

## 6. Consolidated owner decision register

The register removes duplicate requests by mapping related records to one
decision. Recommendations are not approvals.

| ID | Consolidated decision | Current state | Recommended decision | Required authority / dependency |
|---|---|---|---|---|
| D-001 | Canonical organization identity | Pending; `org_companies.id` is the implemented candidate | Approve `org_companies.id` subject to lifecycle/deletion/membership confirmation | Platform Organization + organization/data owner |
| D-002 | Platform user-membership authority | Platform boundary accepted; runtime use pending | Approve the existing Platform relation/repository as sole membership source | Platform identity/governance; follows D-001 |
| D-003 | Explicit context selection | Implemented and verified as strict behavior | Approve explicit selection even for one membership; no fallback | Platform/ACL owner; follows D-002 |
| D-004 | ACL intersection and enforcement | Concept accepted; runtime/query enforcement pending | Approve intersection model and fail-closed query interface; no permission broadening | ACL + Platform; follows D-003 |
| D-005 | Staff-to-organization membership owner | Unresolved | Platform key/validity boundary with SBAIO-owned Staff membership policy | Platform + SBAIO; follows D-001 |
| D-006 | User-to-Staff binding owner/shape | Recommended, not approved | SBAIO-owned separate historical relation with active uniqueness and audit | SBAIO + Platform/ACL; follows D-001/D-005 |
| D-007 | Staff eligibility/termination | Exact null/status/exit semantics unresolved | Explicit eligibility, revocation, rehire, stale-binding, and termination rules | SBAIO Staff + Platform/ACL; follows D-006 |
| D-008 | Binding administration authority | Unresolved | Dedicated SBAIO binding-management permission and organization-admin scope; no ACL presentation field | SBAIO + ACL; follows D-006/D-007 |
| D-009 | Legacy Leave/Timecard treatment | Direction accepted; procedure pending | Preserve records; quarantine unverifiable approval provenance; reconcile explicitly | SBAIO Leave/Timecards + data governance; later stage |
| D-010 | Migration/rollback/release authority | Safeguards proposed; execution blocked | New artifacts only, no automatic backfill, disposable proof, backup/rollback, separate release approval | SBAIO + Platform/ACL + release/data owners |

### 6.1 Existing SBAIO decisions mapped without duplication

The SBAIO closure identifiers remain authoritative and map as follows:

- `SBAIO-D-001` → D-006, separate historical binding relation and Staff-owned
  policy.
- `PLATFORM-D-001` → D-001/D-002, Platform identity reference and account
  validity.
- `ORG-D-001` → D-001, canonical organization/scope source.
- `ACL-D-001` → D-004/D-008, binding permission and no presentation field.
- `SBAIO-D-002` → D-007, Staff eligibility/revocation/reassignment/termination.
- `LEAVE-D-001`, `SBAIO-D-003`, `ACL-D-002`, and `SUPERVISOR-D-001` remain
  later Leave-stage decisions and are not prerequisites for the first Platform
  authorization request except where their scope is explicitly consumed.
- `TIMECARD-D-001` through `TIMECARD-D-003` remain Timecard-stage decisions.

## 7. Security test matrix

The following matrix preserves the required security proof. It is a planned
acceptance matrix, not evidence that the blocked runtime stages have been
implemented.

| Area | Required cases | Expected result |
|---|---|---|
| Auth identity | Missing, inactive, malformed, or contradictory authenticated identity | Deny before membership/Staff lookup; only exact `users.id` is accepted |
| Organization membership | None, one, multiple, active, inactive, revoked, future, expired, stale | Resolve only active valid evidence; missing/ambiguous/invalid denies |
| Selection | Missing explicit selection, non-member, cross-user, malformed, duplicate selection | Deny; never use first row, branch, role, assignment, or `primaryCompany()` |
| Evidence integrity | Duplicate active membership, contradictory lifecycle, descriptive-field changes | Deny invalid evidence; descriptive fields do not alter authority |
| ACL | Missing permission, missing scope, membership-only access, platform-admin without Staff binding | Intersection only; membership never broadens permission or record scope |
| Staff binding | Missing, duplicate user, duplicate Staff, revoked, inactive, terminated, stale, organization mismatch | Deny closed; no broad Staff or Leave query on failure |
| Identity fallback | Email, employee code, name, branch, department, browser-supplied Staff ID | Never resolve identity |
| Binding administration | Employee self-bind, unauthorized actor, replacement, revocation, reassignment | Deny unauthorized actor; server-derived actor; atomic revoke/create; history retained |
| Leave lifecycle | Client status, self-approval, illegal transition, stale version, repeated idempotency key | Server-owned submitted state; no self-approval; finite locked/idempotent history |
| Legacy data | Approved row without provenance, cancellation/reversal, finalized Timecard | Preserve; quarantine/reconcile explicitly; never silently rewrite/delete |
| Surface confinement | Anonymous/display/read-only profile, `/u/*` route and provider surfaces | No mutation authority or navigation escape; same resolved predicate everywhere |

## 8. Implementation stages and gates

### Stage 0 — owner contract decisions

Resolve D-001 through D-008 as applicable to the first authorization boundary.
Record named owner approvals and any chosen alternatives. No schema naming or
runtime caller is authorized before this stage is complete.

### Stage 1 — Platform organization authorization runtime integration

**Recommended first authorization request; not yet approved.** Scope only:

- use the existing Auth API to obtain `users.id`;
- load explicit membership evidence through the approved Platform repository;
- require explicit organization selection;
- call the existing resolver and return an opaque organization context;
- deny missing, duplicate, stale, revoked, contradictory, malformed, or
  cross-user evidence;
- preserve Core/Auth/session behavior and existing ACL behavior until its
  separate interface is approved.

Explicit exclusions: Core/Auth changes; ACL schema or permission changes; Staff
resolution/binding; Leave/Timecard; broad business query changes; Shell,
operator, display, deployment, activation, or production changes.

**Gate:** database-free integration fixtures, authenticated HTTP acceptance in an
approved environment, ACL interface approval, and proof that organization
membership cannot broaden existing authority.

### Stage 2 — Staff organization and historical binding

After Stage 1 acceptance and D-005 through D-008 approval, separately authorize
the SBAIO binding relation, resolver, administration service, and migration
contract. No automatic backfill. No Leave or Timecard changes.

**Gate:** pure binding matrix, database-enforced uniqueness/concurrency proof,
server-derived actors, rollback proof, and explicit organization-admin
authorization.

### Stage 3 — Leave self-service and authorization

Only after binding, Leave lifecycle, audit, permission, subordinate-scope, and
record-predicate decisions are approved. Server-owned `submitted` state,
finite transitions, locking/versioning, idempotency, and append-only history are
required. Approval and supervisor workflows remain distinct gates.

### Stage 4 — Supervisor approval and audit

Requires an approved authoritative subordinate relation, independent approval
actor, permission scope, immutable transition history, and audit retention.

### Stage 5 — Timecard eligibility and reconciliation

Only verified approved Leave with valid binding, organization scope, date scope,
and provenance may affect generation. Legacy approved rows without provenance
are quarantined/reported; finalized Timecards are preserved.

### Stage 6 — Disposable acceptance and rollout

Use a staged source root and independently attested disposable database only
after separate authorization. Verify schema constraints, concurrency, rollback,
source preservation, cleanup, and authenticated surfaces. Production backup,
release, activation, and deployment approvals remain independent.

## 9. Implementation details that do not need duplicate policy approval

Once the relevant policy decisions are approved, the following are engineering
choices subject to normal implementation review:

- exact relation/table name and column order;
- SQL types, indexes, nullable active-key representation, and migration filename;
- repository and service method names;
- lock ordering, transaction mechanics, and typed result-code names;
- immutable context/fingerprint object shape;
- PHP probe filenames and fixture organization;
- `TIMESTAMP` versus `DATETIME` where approved audit semantics are preserved.

These details must still satisfy the approved invariants and compatibility
gates. Treating them as implementation details must not be used to bypass a
pending ownership, lifecycle, ACL, legacy-data, or release decision.

## 10. Exact authorization boundary requested from the owner

The first request should authorize **only Stage 1 Platform organization
authorization runtime integration** as described in §8. It should not authorize
Staff binding, ACL schema changes, Leave, Timecard, migration, deployment, or
activation.

Suggested approval text:

> Authorize Platform to integrate the existing authenticated organization
> context seam into the approved runtime boundary using exact Core/Auth
> `users.id`, the approved Platform membership repository, explicit organization
> selection, and the existing fail-closed resolver. This authorization excludes
> Core/Auth changes, ACL schema/permission changes, Staff resolution or binding,
> Leave, Timecard, migration, deployment, activation, and production use. Any
> additional caller, query scope, or permission change requires separate owner
> approval.

This wording is a proposal for owner convenience, not an approval.

## 11. Cross-references

- [Canonical organization authority contract](oh-auto3-org-contract-001-canonical-organization-authority.md)
- [First-slice Platform implementation plan](oh-auto3-org-contract-001-first-slice-implementation-plan.md)
- [Persistent membership authorization package](oh-auto3-org-contract-001-persistent-membership-implementation-authorization-package.md)
- [SBAIO identity/Leave architecture](oh-auto3-004-sbaio-identity-leave-architecture-2026-10-24.md)
- [SBAIO architecture decision closure](oh-auto3-005-sbaio-architecture-decision-closure-2026-10-24.md)
- [SBAIO identity-foundation dependency resolution](oh-auto3-006-sbaio-identity-foundation-dependency-resolution-2026-10-24.md)
- [Platform decisions](../../engineering/Platform/decisions.md)
- [Platform work record](../../engineering/Platform/work.md)
- [SBAIO decisions](../../engineering/SBAIO/decisions.md)
- [SBAIO work record](../../engineering/SBAIO/work.md)
- Verified milestone: `d2bd92fc78b067eccda8b3bfac3b6decbc30543f`

## 12. Final disposition

**DOCUMENTED / BLOCKED.** The organization runtime integration plan and Staff
binding dependency are now preserved in one reviewable repository document.
Accepted policy and verified implementation are distinguished from proposed
architecture and pending owner decisions. No implementation authorization has
been inferred. All runtime, schema, database, Staff, Leave, Timecard,
deployment, and activation work remains blocked pending explicit approval at
the stated gates.
