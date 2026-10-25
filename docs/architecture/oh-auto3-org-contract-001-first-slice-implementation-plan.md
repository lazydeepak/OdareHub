# OH-AUTO3-ORG-CONTRACT-001 — First Slice Implementation Plan

**Status:** DB-free slice implemented; runtime authority integration remains separately gated.

## Scope

The first slice is a pure Platform Organization boundary over already-authoritative
membership evidence. It does not load the current user, query `org_companies`, call
ACL, resolve Staff, write rows, or alter existing permission behavior.

## Exact file scope

### Implemented

- `apps/Platform/Services/OrganizationMembershipContext.php`
  - immutable resolved context containing only `users.id`, selected organization
    ID, and an evidence fingerprint;
  - no Staff ID, permission, route, or grant fields.
- `apps/Platform/Services/OrganizationMembershipResolver.php`
  - pure resolver for normalized membership evidence;
  - strict positive IDs and lifecycle booleans;
  - active/revoked/validity-window filtering;
  - explicit multi-organization selection;
  - duplicate active membership rejection;
  - deterministic fail-closed result codes.
- `apps/Platform/Tests/probe_organization_membership_authority.php`
  - database-free positive/negative acceptance probe.

### Deliberately not changed

- `OrganizationService.php`: remains DB-backed management/configuration service;
  `primaryCompany()` is not used for authenticated context.
- organization migrations: no membership schema is created;
- ACL/Base interfaces and runtime checks: no authorization broadening or wiring;
- Core/Auth: no authentication changes;
- SBAIO Staff/Leave/Timecards: no identity or historical-data changes;
- routes, controllers, deployment, activation, and database state.

## Input contract

The resolver accepts an explicit evidence array with `user_id`, `organization_id`,
`is_active`, and `is_revoked`; optional `valid_from`, `valid_until`, and
`membership_key` are preserved only in the fingerprint. The caller remains
responsible for obtaining this evidence from a separately approved authority.

The resolver never treats email, name, employee code, branch, primary company,
app assignment, role, or Staff identifiers as membership evidence.

## Acceptance criteria

### Positive

1. One active membership resolves a context bound to the supplied `users.id`.
2. Multiple active organizations resolve only with an explicit selected ID that
   is present in the evidence set.
3. Validity-window evidence resolves at the supplied observation time.
4. The result includes a deterministic evidence fingerprint and no Staff identity.

### Negative

1. Missing, invalid, inactive, revoked, future, or expired evidence denies.
2. Multiple active organizations without selection deny.
3. A requested organization outside the membership set denies.
4. Duplicate active membership for one organization denies as ambiguous.
5. Malformed lifecycle evidence denies rather than becoming truthy.
6. Invalid authenticated identity denies.
7. Evidence for another user cannot resolve the requested user.
8. No result grants application permission, ACL access, Staff identity, Leave
   ownership, or database authority.

## Separately gated components

| Component | Decision |
|---|---|
| DB-backed membership repository | BLOCKED pending schema, migration, and database approval |
| `org_companies` lifecycle adapter | BLOCKED pending explicit lifecycle verification and read authority |
| authenticated-session resolver | BLOCKED pending approved Platform/Auth integration boundary |
| ACL organization intersection | BLOCKED pending ACL runtime approval and query-scope contract |
| Staff organization relation/binding | BLOCKED pending SBAIO schema and ownership approval |
| Leave/Timecard use | BLOCKED; no historical reinterpretation |

## Verification

Run:

```text
php -l apps/Platform/Services/OrganizationMembershipContext.php
php -l apps/Platform/Services/OrganizationMembershipResolver.php
php apps/Platform/Tests/probe_organization_membership_authority.php
```

The probe must remain database-free and must not be registered as a route or
application runtime dependency.

## Decision

**GO:** pure DB-free membership evidence validation/context resolution.
**BLOCKED:** persistence, authenticated integration, ACL runtime integration,
Staff binding, Leave, migration, and deployment.
