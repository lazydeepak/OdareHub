# Visual Customizer — Request Validation Gate

> Documentation-only. No request creation implemented yet.

## Purpose

Define the mandatory validation gate that a "Request Approval" action must pass
**before** it is allowed to create a draft request artifact.

This gate exists purely in Studio-local state. It must not touch Platform
registry, Shell, or `public/assets`. Apply remains disabled throughout.

---

## Validation Rules (all must pass)

| # | Rule | Type |
|---|------|------|
| 1 | `selected_socket` must be `radius.scale` only | strict equality |
| 2 | `proposed_value` must exist and be non-empty | presence |
| 3 | `proposed_value` must be one of: `sharp`, `soft`, `round` | enum allowlist |
| 4 | Draft artifact must be Studio-local (no Platform registry entry) | scope check |
| 5 | Diff summary must exist and be non-empty | presence |
| 6 | Apply remains disabled — no runtime mutation | invariant |
| 7 | Platform registry untouched | invariant |
| 8 | Shell untouched | invariant |
| 9 | `public/assets` untouched | invariant |

## Failure behavior

If any rule fails, the "Request Approval" action must **not** create a draft
artifact and must surface the failing rule to the user as a clear inline
diagnostic.

## Future

When "Request Approval" is implemented, this gate must be the first synchronous
check run before any artifact creation logic executes.
