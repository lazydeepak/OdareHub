# OH-AUTO3-ORG-CONTRACT-001 — Canonical Organization Authority Contract

**Date:** 2026-10-25
**Mode:** Bounded decision specification
**Status:** Governing policy accepted; DB-free first slice authorized; persistence and runtime integration remain separately gated

## 1. Purpose and boundary

This contract defines the minimum organization authority required before an
authenticated SBAIO user can be bound to a Staff record. It is intentionally
limited to the first self-service consumer and does not authorize Leave,
Timecard, Core, ACL-runtime, schema, migration, or database changes.

The contract separates four concerns:

1. Platform owns organization identity and membership semantics.
2. Core remains the authority for authenticated `users.id`.
3. ACL consumes authoritative organization evidence for allow/deny decisions.
4. SBAIO owns Staff eligibility and the historical user-to-Staff binding policy.

Shell, Workspace Profile, and presentation layers must not resolve organization
or Staff identity.

## 2. Existing evidence versus proposed authority

| Area | Existing evidence | Proposed contract status |
|---|---|---|
| Authenticated identity | Core `Auth` resolves `users.id`, account status, and session validity | Remains authoritative and unchanged |
| Organization identity | `org_companies.id`, company lifecycle, branches, hierarchy, fiscal and branding | Candidate Platform identity reference; semantic approval still required |
| Organization management | `OrganizationService` lists/resolves companies and scopes organization-owned records | Management capability only; not currently membership authority |
| User membership | No verified user-to-company relation | Platform-owned authoritative relation required |
| Staff membership | Staff has no organization key or membership resolver | Platform/SBAIO-owned relation required |
| User-to-Staff identity | No source-backed join; email/code are nullable and nonunique | SBAIO-owned historical binding relation required |
| App assignment | `user_dashboard_assignments` grants app/module capability | Not organization membership and must not be promoted |
| Operational scope | Machine/part/task/department/branch-code fields | Operational scope only; not legal organization identity |
| Leave ownership | Browser-selected `staff_id`; broad reads | Remains blocked until this contract and later Leave policy exist |

`OrganizationService::primaryCompany()` is a display/configuration fallback and
must not resolve authenticated organization context.

## 3. Canonical organization identity proposal

**Recommended direction:** use the Platform Organization identity represented by
`org_companies.id`, subject to owner verification of its meaning, lifecycle, and
membership relationship. The identifier must not be treated as canonical merely
because the table exists.

Platform owners must decide whether the existing company record becomes the
canonical authority directly, is extended with membership relations, or is
wrapped by a separately owned Platform authority. SBAIO must not create a
competing organization table or invent a local organization identifier.

Required organization properties:

- stable opaque identifier;
- active/inactive lifecycle;
- explicit membership source;
- server-resolvable effective context;
- no inference from primary-company ordering, branch code, app assignment, or
  role labels;
- unavailable, inactive, contradictory, or deleted organization evidence fails
  closed.

## 4. Proposed authoritative relations

The following relations are contract concepts, not authorized schema names.

### 4.1 User-to-organization membership — Platform-owned

Platform should own an explicit revocable relation from Core `users.id` to the
canonical organization identity. Minimum semantics:

- a user may have zero, one, or multiple organization memberships;
- each membership has an active/revoked lifecycle and validity evidence;
- duplicate active membership for the same user and organization is invalid;
- membership changes are attributable and historically recoverable;
- membership does not itself grant an application permission;
- missing or ambiguous active membership cannot resolve an organization context.

### 4.2 Staff-to-organization membership — Platform/SBAIO boundary

Staff must have explicit organization ownership or membership. Email, employee
code, full name, department, and branch are descriptive fields only.

The owner decision must identify whether Platform owns the relation as part of
organization authority or SBAIO owns the relation while consuming a Platform
organization identifier. Either model must provide:

- active/inactive and revoked semantics;
- organization membership evidence;
- Staff employment eligibility evidence;
- duplicate and cross-organization rejection;
- historical preservation on reassignment or termination.

### 4.3 User-to-Staff binding — SBAIO-owned

SBAIO should own a separate historical binding relation rather than silently
adding identity meaning to the existing Staff table. The binding must include
server-derived `users.id`, Staff identity, approved organization context, active
state, validity/revocation evidence, and audit actor.

For each resolved organization, the self-service invariant is:

- exactly one eligible active Staff mapping for the authenticated user;
- zero mappings means unavailable/deny;
- more than one mapping means ambiguous/deny;
- inactive, revoked, stale, cross-organization, or contradictory mappings deny;
- no browser-supplied Staff ID participates in identity resolution.

## 5. Organization-context resolution

The resolver must return an opaque context containing at least the approved
organization identity, authenticated `users.id`, active membership evidence, and
an authority/fingerprint reference. It must not return a context from display
defaults or presentation profiles.

For users with multiple organizations:

1. resolve only memberships owned by the authenticated `users.id`;
2. require an explicit, server-authorized active organization context;
3. verify that the selected context is currently active and revocable;
4. reject missing selection when multiple memberships are eligible;
5. reject a browser organization ID unless it is resolved against the user's
   server-side membership set;
6. never fall back to `primaryCompany()`, first row, branch code, or app scope.

For the first SBAIO consumer, a single eligible membership may be selected
deterministically only if the owner approves that behavior. Otherwise, even a
single membership must pass the same active-membership resolver.

## 6. Relation and ownership matrix

| Relation/evidence | Owner | Consumer | Must not provide |
|---|---|---|---|
| `users.id` and account validity | Core/Auth | Platform resolver, ACL | Organization or Staff meaning |
| Organization identity/lifecycle | Platform Organization | Membership resolver, ACL | Business-record ownership by itself |
| User membership | Platform identity/governance | ACL, SBAIO resolver | Application permission grant |
| Staff membership/eligibility | SBAIO with approved Platform boundary | Binding resolver | Authenticated identity inference |
| User-to-Staff binding | SBAIO | SBAIO Leave/Timecard stages | Generic ACL presentation policy |
| Hard allow/deny | ACL | Routes/services/query authorities | Membership data ownership |
| Leave meaning/lifecycle | SBAIO Leave | Future self-service | Organization identity invention |
| Timecard consumption | Timecards | Future verified provenance | Silent legacy reinterpretation |

## 7. ACL consumption and non-broadening rule

ACL may consume an organization context only after validating its authority,
active membership, and binding evidence. Organization membership is an input to
authorization, not a replacement for application permissions, module authority,
record scope, or Staff eligibility.

The effective decision must remain an intersection:

```text
authenticated account
AND active organization membership
AND application/module permission
AND owner-specific record scope
AND Staff eligibility/binding where required
```

Missing organization evidence, missing Staff binding, or contradictory evidence
must deny before broad business reads or writes. The contract must not grant
access merely because the user has `sbaio` assigned or `organization.view`.

## 8. Positive and negative authorization scenarios

| Scenario | Expected result |
|---|---|
| Active user, one active organization membership, one eligible Staff binding | Resolve context and permit only separately authorized SBAIO capability |
| Active user with no organization membership | Deny/unavailable before Staff or Leave lookup |
| Active user with two eligible organizations and no explicit context | Deny as ambiguous |
| Explicit organization context not in server membership set | Deny |
| Organization inactive or revoked | Deny |
| Staff absent from resolved organization | Deny |
| Two active Staff mappings in one organization | Deny as ambiguous |
| Staff inactive, terminated, or revoked | Deny self-service |
| User-to-Staff binding belongs to another organization | Deny |
| Email/name/employee-code match only | Do not resolve; deny |
| App assignment without organization membership | Do not promote assignment; deny organization-scoped capability |
| Platform administrator without explicit Staff binding | Preserve administrative authority only; do not infer employee self-service identity |
| Existing Leave row lacks new provenance | Preserve row; do not silently authorize, rewrite, or reinterpret it |

## 9. Decisions requiring owner approval

1. Whether `org_companies.id` becomes the canonical organization identity or
   remains an administrative source supporting another Platform authority.
2. The exact user-membership relation, cardinality, lifecycle, revocation, and
   audit owner.
3. The exact Staff-membership relation and whether Platform or SBAIO owns it.
4. Whether users may belong to multiple organizations and how active context is
   selected and persisted.
5. The exact definition of eligible Staff, including employment and termination
   semantics.
6. The binding relation's historical/reassignment policy and concurrency
   uniqueness rules.
7. ACL's organization-scope enforcement interface and deny behavior.
8. Legacy Leave and Timecard preservation, quarantine, and reconciliation rules.
9. Migration ownership, rollback authority, backup requirements, and production
   release gates.

## 10. Incremental implementation and migration plan

### Stage 0 — owner contract approval

Approve this contract with Platform Organization, Platform identity/governance,
ACL, SBAIO, Timecards, and data-governance owners. Resolve all Section 9
decisions before schema naming.

### Stage 1 — Platform authority implementation

Separately authorize only the organization identity/membership resolver and its
read-only authority contract. No Leave or Staff binding writes. Prove missing,
active, inactive, revoked, duplicate, multi-organization, and contradictory
membership behavior.

### Stage 2 — SBAIO binding implementation

After Stage 1 acceptance, separately authorize the SBAIO historical binding
relation and server-owned create/revoke/replacement policy. Do not backfill
ambiguous Staff records. Preserve existing rows and provide rollback for only
new binding artifacts.

### Stage 3 — Leave self-service

Only after identity binding, Leave lifecycle, audit, and record-scope decisions
are separately approved: server-bound pending submission and own-request reads.
Approval, rejection, cancellation, supervisor workflows, broad reports, and
Timecard integration remain separate stages.

### Migration safeguards

- no automatic organization or Staff matching;
- no email/name/code backfill;
- ambiguous records remain unbound;
- verify uniqueness under concurrency in an authorized disposable database;
- snapshot and reconcile before any approved migration;
- preserve legacy rows and classify unverifiable provenance explicitly;
- production migration requires independent backup, rollback, audit retention,
  and release authority.

## 11. Required tests

Before Stage 1 implementation approval:

- database-free resolver fixtures for all Section 8 scenarios;
- exact `users.id` identity binding tests;
- multi-organization context selection and ambiguity tests;
- active/revoked/inactive/stale membership tests;
- duplicate and cross-organization Staff binding tests;
- proof that app assignment and primary company cannot resolve authority;
- ACL intersection tests proving no permission broadening;
- no broad Staff/Leave query before identity denial;
- legacy Leave/Timecard rows remain unchanged in read-only fixtures.

Before any schema migration:

- disposable database migration and rollback proof;
- concurrent uniqueness proof;
- backup and restoration evidence;
- migration inventory and ambiguous-row report;
- post-migration read-back and audit verification.

## 12. Verdict

**Contract direction:** GO for owner review.
**Runtime/schema implementation:** OWNER DECISION REQUIRED; not authorized.
**SBAIO Staff binding:** BLOCKED until the Platform/ACL authority is accepted.
**Leave self-service:** BLOCKED until later identity, lifecycle, scope, and audit
contracts are accepted.

This package changes no code, schema, database state, route, ACL runtime,
SBAIO behavior, deployment, activation, or working-tree artifact.

## References

- `docs/architecture/oh-auto3-006-sbaio-identity-foundation-dependency-resolution-2026-10-24.md`
- `docs/architecture/oh-auto3-005-sbaio-architecture-decision-closure-2026-10-24.md`
- `docs/architecture/oh-auto3-002-sbaio-staff-identity-leave-authorization-contract-2026-10-22.md`
- `engineering/Platform/decisions.md`
- `engineering/SBAIO/decisions.md`
