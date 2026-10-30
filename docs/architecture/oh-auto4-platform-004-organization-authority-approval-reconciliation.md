# OH-AUTO4-PLATFORM-004 — Organization Authority Approval Reconciliation

**Date:** 2026-10-30
**Status:** Verification evidence preserved; approval request remains open
**Mode:** Documentation and selective Git reconciliation only

## Result

The OH-AUTO4-PLATFORM-003 verification evidence remains valid and is isolated
from the repository’s pre-existing dirty worktree. The existing Platform
foundation continues to pass its database-free fail-closed verification:

- `probe_organization_membership_authority.php`: **30/30**
- `probe_authenticated_organization_context.php`: **16/16**
- Focused Platform PHP lint: **PASS**

No defect was reproduced. No implementation hardening or organization-authority
activation is warranted by this verification.

## Selective staging boundary

The P003 compliance entry is a standalone final diff hunk in
`AGENT-COMPLIANCE-CHECKLIST.md`. The file also contains older uncommitted
history and unrelated records, so the whole file must not be staged. Only the
P003 hunk is attributable to this reconciliation.

The following remain intentionally unstaged and preserved:

- existing `AGENTS.md` changes;
- Platform, Procurement, and SBAIO workspace ledger changes;
- discussion documentation;
- lifecycle logs;
- caches, backups, PDFs, and untracked incident/architecture evidence.

## Approval request — proposed, not approved

### Decision 1 — canonical organization identity

**Request to Platform Organization owner:** approve or reject
`org_companies.id` as the canonical organization identifier for membership
evidence. If rejected, name the replacement Platform-owned authority and its
bounded adapter contract.

This decision must cover identity stability, active/inactive lifecycle,
deletion behavior, and ownership semantics. The existence of the table or
`primaryCompany()` fallback is not approval.

### Decision 2 — membership authority

**Request to Platform Organization/database authority:** approve or reject
`org_user_memberships` as the sole explicit server-side membership relation,
keyed by authenticated `users.id` and the approved organization identifier.

Approval must cover explicit selection, zero/one/multiple memberships,
active/inactive/revoked lifecycle, validity windows, attributable actors,
active uniqueness, contradictory evidence, and migration/recovery policy.

### Decision 3 — ACL intersection

**Request to ACL owner:** approve or reject the following enforcement contract:

```text
authenticated users.id
AND active selected organization membership
AND app/module permission
AND owner-specific record scope
AND Staff eligibility/binding where required
```

Membership alone must not grant application, route, record, Staff, Leave, or
Timecard access. Missing, stale, contradictory, or ambiguous context must deny
before broad reads or writes.

### Decision 4 — downstream dependencies

**Request to affected owners:** confirm separate contracts before implementation:

- SBAIO owner: Staff eligibility and historical user-to-Staff binding;
- each app/module owner: record-level scope and query confinement;
- Leave/Timecard owners: provenance-safe consumption after Staff binding;
- release/test governance: independently attested disposable `OH_ORG` proof;
- runtime/release governance: authenticated acceptance and activation policy.

These approvals are dependencies, not implied consequences of approving the
Platform membership source.

## Security-preserving conditions

Until all required approvals exist:

1. Keep the Platform resolver and authenticated seam read-only at their current
   boundaries.
2. Keep missing, malformed, stale, revoked, inactive, duplicate,
   cross-user, contradictory, and unselected evidence fail closed.
3. Do not infer tenant membership from assignments, roles, branches, email,
   names, employee codes, Staff, or `primaryCompany()`.
4. Do not modify Core/Auth/ACL, activate runtime authorization, execute schema
   or database operations, or bind Staff/Leave/Timecard consumers.
5. Continue treating Platform Option B and SBAIO D-01–D-04 as proposals only.

## Evidence and provenance

- Existing implementation provenance culminates in `d2bd92f`.
- Current branch baseline before this reconciliation:
  `HEAD == origin/main == c95f0257ac9bf71df8d4b273d78b08d826faabbe`.
- P003 evidence is recorded in `AGENT-COMPLIANCE-CHECKLIST.md`.
- The full foundation reconciliation is recorded in
  `docs/architecture/oh-auto4-platform-001-organization-membership-foundation-reconciliation.md`.
- The decision-ready proposal is recorded in
  `docs/architecture/oh-auto4-platform-002-canonical-organization-authority-decision-record.md`.

No production database, migration, deployment, activation, browser, or live
HTTP operation occurred.
