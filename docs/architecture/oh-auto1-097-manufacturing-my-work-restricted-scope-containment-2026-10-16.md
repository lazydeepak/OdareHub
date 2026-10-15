# OH-AUTO1-097 — Manufacturing My Work Restricted-Scope Containment

**Status:** Complete — bounded fail-closed containment
**Date:** 2026-10-16
**Owner:** Manufacturing My Work projection boundary

## Architecture law

The existing broad My Work sources cannot be presented as scope-safe for a
user whose machine, part, task-type, department, branch, or ownership scope is
restricted. The shared query applies status filters and result limits before
those operational predicates, while the enrichment adapter does not add them.

The Manufacturing-owned fix is containment, not post-limit filtering: after
the existing context resolution and before any handoff, approval, or
operational queue collection, restricted or malformed scope returns a
shape-compatible unavailable result with no records, counts, notifications, or
role links. The existing explicit empty-scope meaning remains unrestricted.

## Implementation

- `MyWorkContributionService::scopeContainmentState()` strictly classifies the
  resolved scope without granting or inventing authority.
- `MyWorkContributionService::containedWorkspace()` returns the existing My
  Work payload shape with zero records and explicit containment diagnostics.
- `MyWorkContributionService::build()` invokes containment before
  `collectHandoffItems()` and `collectOperationalQueue()`.
- The Core `MyWorkEntityQuery`, Core adapter, Base resolver, routes, schema, and
  scope authority remain unchanged.

Restricted values include positive machine/part IDs, task types, and
organizational scope values because the existing queue does not prove any of
them before its limits. Denied sentinels and malformed values fail closed.

## Proof

- Restricted-scope containment probe: **21/21**
- Existing work/process summary proof: **23/23**
- Existing Processing proof: **35/35**
- Existing Parts proof: **14/14**
- Existing machine projection proof: **27/27**
- PHP lint and `git diff --check`: PASS

No database, SQL, schema, browser, activation, deployment, production, or
Core operation was performed. The pre-existing Base
`resolveUserContext() -> ensureSchema()` dependency remains unresolved and
unchanged.
