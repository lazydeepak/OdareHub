# Plugin — Work

## Current Focus

OH-AUTO2-101 retains Base as a removable compatibility boundary while keeping
notification reads read-only and escalation execution explicit.

## In Progress

- [x] User assignment preflight reads existing evidence without invoking
  `ensureSchema()`; full legacy resolution remains available for unrestricted
  compatibility behavior.
- [x] Notification summary/recent reads no longer invoke `ensureTable()` or
  escalation sweeps; missing storage returns explicit unavailable read results.

## Next

- [x] OH-AUTO2-101 — unrestricted notification/escalation read safety.

## Blocked

- None recorded.

## Completed

- No completed work recorded yet.

## Evidence

- Notification read proof: **16/16**; explicit escalation entry point remains
  callable, with no scheduler/route added.
- Base contributed-operator-focus catalog bridge + probe (see Hospitality work.md cross-reference)
