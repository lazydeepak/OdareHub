# OH-AUTO4-PLATFORM-001 — Organization/Membership Foundation Reconciliation

**Date:** 2026-10-30
**Status:** **Foundation verified; end-to-end authority closure blocked**
**Mode:** Read-only reconciliation; no implementation authorization inferred

## Result

OH-AUTO4-PLATFORM-001 is not an unstarted implementation. The repository
already contains a bounded Platform organization/membership foundation:

- `OrganizationMembershipResolver` is a pure, fail-closed evidence resolver.
- `OrganizationMembershipContext` is an immutable opaque context containing
  the authenticated `users.id`, selected organization, and evidence fingerprint.
- `AuthenticatedOrganizationContextResolver` composes existing Auth identity,
  explicit organization selection, the repository contract, and the pure
  resolver without changing Core/Auth or calling ACL.
- The Platform Organization repository and migrations define a persistent
  membership source, but their use as the final owner-approved authority and
  runtime enforcement path remains separately gated.

Therefore the safe classification is: **the first DB-free Platform foundation
is complete and independently evidenced; the broader organization authority
milestone remains blocked rather than being repeated.**

## Source evidence

### Repository and remote

- `HEAD == origin/main == 93dcaeab8ef4c4b934cfbb678583e5b8dbdc432d` at the
  start of this reconciliation.
- The implementation history is present in the following commits:
  - `b04d985` — DB-free membership resolver
  - `9d45ad7` — persistent membership source
  - `0d96049` — persistence verification correction
  - `3fc09f2` — organization lifecycle evidence enforcement
  - `d2bd92f` — authenticated organization context resolver

The working tree contains unrelated existing modifications and untracked
artifacts. They were preserved and not reverted or overwritten.

### Governing records

- `docs/architecture/oh-auto3-org-contract-001-canonical-organization-authority.md`
  accepts the ownership boundary but separately gates persistence and runtime
  integration.
- `docs/architecture/oh-auto3-org-contract-001-persistent-membership-implementation-authorization-package.md`
  records the persistent source and its disposable verification boundary.
- `docs/architecture/oh-auto3-org-runtime-plan-001-authoritative.md` records
  the verified implementation and the unresolved runtime decisions.
- `engineering/Platform/work.md` and `engineering/Platform/decisions.md`
  explicitly preserve the separation between DB-free foundation, disposable
  evidence, and Auth/ACL/runtime authorization.

## Authorization invariants verified

The implementation and probes preserve these boundaries:

1. Core/Auth remains the authority for authenticated `users.id`.
2. Organization selection is explicit; no primary-company, role, assignment,
   branch, email, name, employee-code, or Staff inference is used.
3. Membership evidence is user-bound, lifecycle-checked, validity-checked,
   duplicate-checked, and organization-lifecycle-checked.
4. Missing, ambiguous, stale, revoked, inactive, contradictory, malformed,
   cross-user, and unowned evidence fails closed.
5. Membership does not grant ACL permission by itself.
6. No Staff, Leave, or Timecard identity or authorization is inferred.
7. Shell and presentation layers do not resolve organization or Staff identity.

## Verification

Executed in this reconciliation:

| Check | Result | Scope |
|---|---:|---|
| `probe_organization_membership_authority.php` | **30/30** | Pure DB-free resolver behavior |
| `probe_authenticated_organization_context.php` | **16/16** | Authenticated Platform seam and failure closure |
| Focused PHP lint | **PASS** | Resolver, context, authenticated seam, repository |

The repository probe
(`probe_organization_membership_repository.php`) is explicitly
disposable-database-only. It was not rerun: its `OH_ORG` authorization guard
requires independently attested isolated source-root/database evidence that
was not present for this session. The historical governed result remains
recorded as authorizer **24/24** and repository probe **17/17**; it does not
authorize installed-database use or runtime integration.

## Affected files and database effects

The verified implementation consists of the existing Platform files:

- `apps/Platform/Services/OrganizationMembershipResolver.php`
- `apps/Platform/Services/OrganizationMembershipContext.php`
- `apps/Platform/Services/AuthenticatedOrganizationContextResolver.php`
- `apps/Platform/modules/Organization/Contracts/OrganizationMembershipRepositoryContract.php`
- `apps/Platform/modules/Organization/Services/OrganizationMembershipRepository.php`
- `apps/Platform/modules/Organization/migrations/001_create_organization_tables.sql`
- `apps/Platform/modules/Organization/migrations/006_create_user_organization_memberships.sql`

This reconciliation changed no runtime PHP, Core/Auth/ACL code, routes, schema,
database state, migration execution, deployment, activation, or production
configuration. No database connection or SQL was executed.

## Exact unresolved owner-policy dependency

The remaining blocker is not missing resolver code. An explicit owner decision
is still required to approve, as policy authority:

1. whether `org_companies.id` is the canonical organization identity, or what
   approved Platform authority replaces/wraps it;
2. the persistent membership relation and lifecycle/actor semantics as the
   authoritative source; and
3. the runtime integration contract in which ACL independently intersects
   authenticated identity, active organization membership, application/module
   permission, and owner-specific record scope.

After that decision, separate authorization is still required for authenticated
runtime acceptance, ACL query-scope enforcement, Staff binding, Leave/Timecard
consumption, installed-database execution, deployment, and activation.

Platform Option B and SBAIO D-01–D-04 remain proposals and are not treated as
approval. No organization identifier, tenant mapping, Staff binding, or ACL
grant is inferred from them.

## Recommendation

Close only the **DB-free foundation sub-slice** as already satisfied by the
existing implementation and evidence. Keep OH-AUTO4-PLATFORM-001 open or
blocked for the owner decision above. The next eligible action is a decision
record confirming the canonical organization authority and ACL intersection
contract—not another resolver, repository, migration, or database probe.
