# Plugin — Decisions

## Decision Log

### 2026-10-16 — OH-AUTO2-101 notification read and escalation boundary

**Status:** Accepted — reads are non-mutating; execution remains explicit

`NotificationService` summary, recent, list, and unread-count retrieval no
longer initializes notification schema or invokes escalation. My Work consumes
availability-aware summary/recent results and does not render unavailable
storage as verified notification success. Notification creation and status
mutation methods retain their existing schema authority.

Escalation remains behind the existing public `EscalationService::runSweep()`
and Manufacturing contribution entry points. No scheduler, route, schema, or
new execution integration was added; no in-repository scheduler caller was
found, so a future execution trigger remains a separate planner decision.

### YYYY-MM-DD — Decision title
**Status:** Proposed / Accepted / Superseded

**Context**
Why the decision was needed.

**Decision**
What was decided.

**Consequences**
What this enables, constrains, or requires next.

**Evidence**
Relevant validation, source references, or implementation links.
