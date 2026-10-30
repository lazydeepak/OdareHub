# OH-AUTO4-PLATFORM-005A — Approved Platform Option B Reconciliation

**Date:** 2026-10-31
**Status:** Platform Option B owner-approved; implementation scope remains bounded
**Approval source:** Explicit owner instruction in the OH-AUTO4-PLATFORM-005A assignment

## Approval meaning

The owner instruction explicitly approves **Platform Option B**: establish the
canonical Platform organization and membership authority before downstream
organization-scoped consumers proceed.

This approval supersedes the stale “recommendation pending owner approval”
wording in earlier proposal records only for the Platform direction. It does
not approve every contract or consumer that depends on that direction.

## Approved Platform boundary

Within Platform, Option B permits the existing bounded authority foundation to
remain the source of implementation:

- Core/Auth remains authoritative for authenticated positive `users.id`.
- Platform Organization owns organization identity and explicit membership
  semantics.
- The existing `org_companies.id` and `org_user_memberships` implementation is
  the proposed Platform persistence boundary subject to its existing migration
  and disposable verification evidence.
- `OrganizationMembershipResolver` remains pure and fail-closed.
- `AuthenticatedOrganizationContextResolver` remains a composition seam, not
  an Auth replacement or ACL grant.
- Missing, malformed, stale, revoked, inactive, duplicate, contradictory,
  cross-user, and unselected evidence remains denied.

The existing Platform implementation was already committed in the history
culminating at `d2bd92f`; it must not be duplicated. Historical disposable
verification recorded authorizer **24/24** and repository **17/17**. No new
database execution is authorized by this reconciliation.

## Explicitly not approved

This approval does **not** authorize:

- Core/Auth changes or replacement of the `users.id` authority;
- ACL implementation, organization-aware permission intersection, or query
  scope changes;
- SBAIO D-01–D-04, Staff eligibility, or user-to-Staff binding;
- app/module record-level scope or sensitive mutation policy;
- Leave or Timecard consumption;
- inferred memberships, tenant backfill, or historical ownership rewriting;
- production database operations, deployment, activation, or live acceptance.

The required enforcement shape remains proposed for separate ACL approval:

```text
authenticated users.id
AND active selected organization membership
AND app/module permission
AND owner-specific record scope
AND Staff eligibility/binding where required
```

## Implementation status

The meaningful Platform-owned implementation slice is already present and
historically verified:

- pure membership authority resolver and immutable context;
- authenticated organization context seam;
- repository contract and transactional repository;
- organization and membership migrations;
- database-free probes **30/30** and **16/16**;
- historical disposable authorizer **24/24** and repository proof **17/17**.

No new code slice is added here because doing so would duplicate completed
work or cross into separately unapproved Core/Auth/ACL/downstream policy. A
future implementation continuation requires an explicitly scoped Platform
task and, where persistence behavior is changed or requalified, fresh governed
disposable-database evidence.

## Required next approvals and dependencies

1. Platform Organization owner confirms the concrete organization identity and
   lifecycle/deletion semantics.
2. Platform database authority confirms migration, actor, uniqueness,
   rollback, and recovery policy.
3. ACL owner separately approves the non-broadening intersection contract.
4. SBAIO and each affected app owner separately approve Staff and record-scope
   contracts before any consumer integration.

Until those approvals exist, preserve the current fail-closed Platform
boundary and do not activate downstream organization authority.

## Reconciled records

- `engineering/Platform/decisions.md`
- `engineering/Platform/work.md`
- `engineering/SBAIO/decisions.md`
- `docs/architecture/oh-auto4-platform-002-canonical-organization-authority-decision-record.md`
- `docs/architecture/oh-auto4-platform-004-organization-authority-approval-reconciliation.md`
