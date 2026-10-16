# OH-AUTO2-113 — Scope Blocker Closure and Next-Milestone Selection

**Date:** 2026-10-16
**Status:** Documentation closure complete
**Baseline:** `bb8c57524c74d871116946e75d3a4f113ac49294`

## Documentation reconciliation

The pending OH-AUTO2-112 changes were reviewed and are narrowly scoped to
documentation and engineering records. They preserve:

- **OPTION C — BLOCKED**;
- the Material source-to-scope mapping limitations;
- rejection of whole-domain aggregate authority;
- continued OH-AUTO2-111 containment;
- the required future owner-approved data-ownership/scope-mapping decision;
- prohibition on Material aggregate reactivation; and
- the historical OH-AUTO2-109 → OH-AUTO2-110 → OH-AUTO2-111 sequence.

No application source changes were present. Lifecycle logs and
`vendor/bin/.phpunit.result.cache` were preserved unstaged.

## Ranked executable milestone candidates

### 1. Recommended — Shell `OperatorSurfaceComposer` account-panel extraction

**Objective:** Extract the existing non-rendering `buildAccountPanelData()` block
into a focused Shell composer/service while leaving
`renderAccountPanelMarkup()`, routes, URLs, session-flash semantics, and output
unchanged.

- **Benefit:** Reduces the active 2,700-line Shell facade’s regression surface
  and continues an already-established decomposition program.
- **State:** Explicitly listed as the next Shell extraction direction; multiple
  prior composer extractions and parity probes already exist.
- **Owner:** Shell composition layer.
- **Dependencies:** Existing `MyAccountService`, current context, session flash,
  and extracted-composer probe conventions.
- **Core/schema/database:** None required; the implementation should use fake or
  injected data for verification and must not broaden reads.
- **Authorization risk:** No policy change; preserve existing caller boundary
  and account URL confinement.
- **Verification:** PHP lint, extracted-composer parity probe, source ownership
  checks, operator confinement, Shell rendering, and diff hygiene.
- **Scope:** Small-to-medium, one data block plus delegation and focused proof.

### 2. Hospitality operator acceptance hardening

**Benefit:** Confirms the already-landed read-only Hospitality operator surface.
The implementation and static probes are complete, but remaining authenticated
browser smoke requires a provisioned local stack and approved browser executor.
It is not independently executable under this assignment’s restrictions.

### 3. Procurement receipt over-quantity/status audit

**Benefit:** Could close a downstream transaction-integrity question.
However, the policy is not fully specified and the strongest verification is
database-backed. The current workspace explicitly keeps this blocked; no
speculative validation rule should be invented.

### 4. Platform release coordinator/apply work

**Benefit:** High deployment value, but release authority, trust integration,
installed identity, migration execution, and human approval remain separately
gated. It is not an independently executable product milestone now.

### 5. Manufacturing integration baseline/source reconciliation

**Benefit:** Useful housekeeping for later integration, but it produces no
current product capability and is lower value than the active Shell extraction
path. It must not reopen Material scope work.

### Excluded candidates

- Material aggregate restoration: prohibited by OH-AUTO2-112 and contained by
  OH-AUTO2-111.
- SBAIO personnel/payroll/attendance/leave/watchlist restoration: record-level
  authority remains unproven; OH-AUTO2-105 containment remains active.
- OH-AUTO2-105B: database incident assessment remains separately blocked.
- Shared services: no active `engineering/Shared` workspace or independently
  authorized milestone was found; future Shared/Foundation work remains subject
  to the future-architecture brief.

## Recommended next assignment

**Proposed OH-AUTO2-114 — Shell OperatorSurfaceComposer account-panel data
extraction.**

Do not implement it in OH-AUTO2-113. The next implementation session should:

1. preserve the existing `OperatorSurfaceComposer` public contract;
2. move only `buildAccountPanelData()` and its direct dependencies into a
   focused Shell-owned composer/service;
3. retain account markup rendering, route generation, session flash consumption,
   localization, and authorization boundaries exactly as-is;
4. avoid new app-specific capability catalogs, ACL fields, routes, schema, or
   database behavior; and
5. prove output parity with isolated fixtures before considering any further
   decomposition.

## Acceptance criteria for OH-AUTO2-114

- Delegated and pre-extraction account-panel payloads are equivalent for valid,
  missing-identity, and service-failure fixtures.
- Account URLs remain same-user `/u/{username}/account` destinations.
- Success/error flash values are consumed with the existing one-shot semantics.
- No new database/schema/write/route authority is introduced.
- `OperatorSurfaceComposer` remains an orchestrator and does not gain business
  domain logic.
- PHP lint, focused parity probe, operator confinement, Shell rendering,
  architecture gates, and diff hygiene pass.

## Outstanding architectural decisions

- Material data ownership and scope mapping remain unresolved; no aggregate
  restoration is permitted.
- SBAIO personnel record authorization remains unresolved.
- Procurement manual supplier ownership and downstream receipt policy remain
  unresolved.
- Platform release authority and installed-state apply eligibility remain gated.
- Live authenticated browser acceptance remains environment-bound where noted
  by Hospitality records.

## Delivery state

OH-AUTO2-112 documentation is ready for documentation-only commit and push.
OH-AUTO2-113 makes no runtime implementation change and leaves the development
cycle open for the selected next milestone.
