# SBAIO/Customers — Decisions

## Decision Log

### 2026-10-22 — OH-AUTO3-004 bounded admin create/list scope

**Status:** Accepted for implementation

**Context**

The existing Customers routes use SBAIO app assignment only, while the
approved milestone requires administrator-only server-side access. Creation
also forwards browser values without field validation.

**Decision**

Keep the existing Customers routes, table, service ownership, and persistence
shape. Enforce the existing `platform_admin`/`app_admin` authority roles using
`Auth` and the current authenticated user, validate only schema-supported field
lengths and optional email syntax, preserve arbitrary existing status values
with the existing active default, and keep list ordering bounded by descending
ID. Do not add ownership, duplicate,
schema, route, Core, Staff, Leave, Payroll, operator, display, or host-provider
behavior.

**Consequences**

Customers records and report/export surfaces become unavailable to assigned
non-administrators. Invalid create input is rejected before persistence; valid
input is normalized and passed to the existing insert contract.

**Evidence**

OH-AUTO3-004 planner acceptance and the existing Customers schema/form/report
contract.

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
