# Studio Capability Implementation Plan

## Objective

Move Studio from a set of individually useful tools toward one capability-driven engineering platform without discarding working tools or introducing a high-risk rewrite.

The implementation strategy is incremental:

1. identify useful behavior already present in tools;
2. define the reusable capability boundary;
3. extract or adapt the capability behind a stable service/action interface;
4. keep the existing tool page working through an adapter;
5. certify the capability with focused probes;
6. make larger Studio workflows reuse it; and
7. remove duplicate implementations only after parity is proven.

---

## Architectural Direction

### Capabilities are primary

The reusable unit is a Studio capability, not a page, route, controller, or tool directory.

A capability should be callable from PHP services/actions without requiring:

- a browser request;
- route state;
- HTML rendering;
- tool-specific form fields;
- global request variables; or
- duplication inside a larger builder.

### Pages are adapters

Existing tool pages remain valuable as focused operator interfaces. They should collect input, invoke capabilities, and render structured results.

### Large builders are orchestrators

App Creator, Module Creator, Owner Creator, page builders, and future larger tools should coordinate capability lifecycles. They should not own private copies of discovery, validation, generation, mutation, snapshot, or verification logic.

### Runtime remains owner-owned

The plan changes Studio implementation architecture only. It does not permit apps or modules to depend on Studio at runtime.

---

## Proposed Capability Contract

Do not introduce a heavy universal framework before proving the need. Start with a small shared contract that capability implementations can satisfy directly or through adapters.

Each capability should declare:

```text
capability key
responsibility
effect: read | plan | mutate | verify
target owner/scope
input schema
output schema
required permissions
required lifecycle checks
snapshot requirement
rollback support
probe reference
```

A structured result should be able to represent:

```text
status
summary
diagnostics
findings
proposed changes
changed artifacts
next action
can apply
snapshot reference
verification result
provenance
```

The exact PHP types should be introduced only after reviewing existing result shapes and selecting a low-risk pilot.

---

## Phase 0 — Portfolio Baseline

### Goal

Understand what capabilities already exist, where they live, and where implementation has diverged.

### Work

Create a read-only Studio capability inventory covering active tools and major Studio workflows.

For each tool, record:

- purpose and Studio-goal contribution;
- read-only or mutating behavior;
- reusable services already present;
- logic trapped in controllers or views;
- inputs and outputs;
- owner scope;
- diagnostics model;
- planning/diff behavior;
- apply behavior;
- snapshot and rollback behavior;
- verification behavior;
- probe coverage;
- likely capability families; and
- known duplicate behavior elsewhere.

### Deliverable

A deterministic capability map and migration priority list.

### Exit criteria

- every active Studio tool is classified;
- every mutating path is identified;
- obvious duplicate capabilities are grouped;
- candidates for first extraction are ranked; and
- no runtime or behavior change has occurred.

---

## Phase 1 — Establish the Capability Boundary

### Goal

Prove one reusable read capability and one reusable mutation lifecycle without designing the whole platform in advance.

### Recommended read-only pilot

Use an existing discovery or diagnosis behavior with strong probes, such as owner discovery, owner structure inspection, or reference discovery.

The pilot must:

- accept explicit structured input;
- return structured output;
- avoid controller/view dependencies;
- preserve existing tool-page behavior through delegation; and
- be callable by a second Studio workflow or probe.

### Recommended mutation pilot

Use an existing narrow, deterministic mutation with snapshot and validation support, such as a guarded locale-resource edit or another owner-owned text-resource update.

The pilot must prove:

```text
analyze -> diff -> approve -> snapshot -> apply -> verify
```

### Exit criteria

- two real capabilities operate outside their original pages;
- original pages delegate without behavior regression;
- focused probes cover direct invocation and page-adapter parity;
- capability inputs and results are concrete enough to guide a minimal shared contract.

---

## Phase 2 — Capability Registry and Invocation

### Goal

Make capabilities discoverable and composable inside Studio.

### Work

Introduce a lightweight Studio-owned registry or catalog that can answer:

- which capabilities exist;
- their keys and responsibilities;
- read/plan/mutate/verify effect;
- supported target types;
- required permissions and lifecycle checks;
- implementation service/action;
- availability state; and
- probe/certification status.

The registry must not become a service locator for arbitrary application runtime behavior. It is an internal Studio orchestration facility.

### Invocation rules

- callers use explicit capability keys or typed service dependencies;
- inputs are validated before execution;
- mutating capability invocation requires explicit authorization and lifecycle context;
- results are structured;
- capability failures become diagnostics rather than broken pages;
- no capability silently invokes an unapproved mutation; and
- orchestration records provenance for each invoked capability.

### Exit criteria

- pilots are registered;
- at least one tool page resolves and invokes a registered capability;
- at least one larger or composite workflow invokes multiple capabilities;
- direct service use remains possible for typed internal dependencies;
- registry integrity is protected by a focused gate.

---

## Phase 3 — Incremental Existing-Tool Migration

### Goal

Align existing tools with the capability architecture while preserving working behavior.

### Migration method per tool

1. State the tool's contribution to the Studio goal.
2. List its current capabilities.
3. Identify reusable logic and page-bound logic.
4. Reuse an existing capability where one already exists.
5. Extract only one bounded authority at a time.
6. Delegate the existing controller/tool flow to the extracted capability.
7. Add direct capability probes and adapter-parity probes.
8. Preserve routes, UI behavior, and output compatibility.
9. Remove duplicate implementation only after certification.
10. Record follow-up composition opportunities.

### Priority order

Prefer migrations that unlock broad reuse:

1. owner and component discovery;
2. reference and dependency discovery;
3. contract and manifest validation;
4. diagnostics/result normalization;
5. diff and change-plan generation;
6. snapshot/provenance operations;
7. guarded file/resource mutation;
8. runtime-consumption verification;
9. schema planning and migration analysis; and
10. deletion impact and orphan verification.

### Exit criteria

- migrated tool pages are thin adapters around reusable services/actions;
- duplicate capability implementations are declining;
- no tool loses focused probes;
- all mutating migrations retain or improve safety.

---

## Phase 4 — Compose Larger Builders

### Goal

Use proven capabilities to build larger Studio workflows.

### App Creator target composition

App Creator should orchestrate capabilities such as:

1. owner-key and namespace validation;
2. collision and dependency discovery;
3. owner skeleton planning;
4. manifest generation and validation;
5. module/component generation;
6. route and navigation planning;
7. schema planning;
8. localization resource creation;
9. style/resource registration;
10. lifecycle and permission validation;
11. full change-set preview;
12. snapshot and apply;
13. registration and runtime verification; and
14. rollback or failed-apply recovery.

App Creator owns the orchestration state and operator experience. It does not reimplement the individual capabilities.

### Other composite workflows

The same model should support:

- Module Creator;
- Owner Creator and owner migration;
- component editors;
- Home/Page editors;
- repair-all workflows;
- upgrade assistants;
- governed deletion workflows; and
- instance certification workflows.

### Exit criteria

- one larger builder composes at least three independently certified capabilities;
- capability results can feed subsequent capability inputs explicitly;
- a composite dry-run produces one coherent change plan;
- apply remains gated and attributable at both orchestration and capability levels.

---

## Phase 5 — Governance and Enforcement

### Goal

Prevent new work from returning to isolated tool implementations.

### Required gates

Add or extend Studio architecture checks to assert:

- active tools declare their purpose and effect;
- mutating behavior matches manifest capability declarations;
- tool controllers do not own prohibited filesystem or schema mutation logic;
- registered capabilities resolve to valid implementations;
- each capability has focused probe coverage;
- larger builders use registered/shared capabilities for governed operations already available;
- owner runtime code does not depend on Studio;
- generated runtime artifacts remain under target-owner ownership;
- mutation paths include required approval, snapshot, and verification declarations; and
- orphaned tool views, routes, capability registrations, and implementations are detected.

### Review rule for new tools

A new tool proposal must include:

- Studio-goal contribution;
- capabilities added or reused;
- composition consumers;
- lifecycle effect;
- owner/runtime boundary;
- probe plan; and
- reason a new page is needed.

### Exit criteria

- architecture gates enforce capability-first development;
- tool review templates require capability mapping;
- new builders demonstrate reuse rather than duplicate behavior.

---

## Immediate Execution Sequence

### Step 1 — Document and gate the direction

- adopt `STUDIO-CHARTER.md`;
- reference it from `apps/Studio/AGENTS.md`;
- add capability-first rules to the Studio workspace contract.

### Step 2 — Produce the capability inventory

Start read-only. Do not refactor during inventory.

### Step 3 — Select two pilots

Choose one strongly tested discovery capability and one narrow guarded mutation capability.

### Step 4 — Prove direct invocation and adapter parity

Keep current tool pages working unchanged from the operator's perspective.

### Step 5 — Introduce only the minimum shared registry/result contracts justified by pilots

Avoid speculative abstraction.

### Step 6 — Use the pilots inside one composite workflow

A small App Creator preparation/diagnosis flow is sufficient before full app generation.

### Step 7 — Migrate tools by reuse value, not page order

Prioritize capabilities needed by many future builders.

---

## Working Rules

- No big-bang Studio rewrite.
- No new framework without a proven capability consumer.
- No extraction solely to move files; extraction must establish reuse or authority.
- No page redesign required for capability migration.
- No removal of compatibility paths before parity certification.
- No automatic mutation added during a read-only migration.
- No broad builder implementation before required small capabilities are available.
- No business-runtime dependency on Studio.
- One authority consolidation at a time.
- Every slice ends with focused validation and a clean ownership result.

---

## Definition of Done for a Capability

A capability is complete when:

- its responsibility and effect are explicit;
- its target owner/scope is explicit;
- inputs and outputs are structured;
- it is callable independently of a tool page;
- its page adapter, when present, delegates to it;
- it has diagnostics and controlled failure behavior;
- mutating lifecycle requirements are enforced;
- owner and runtime boundaries are preserved;
- focused probes pass;
- at least one real consumer uses it; and
- documentation identifies composition opportunities and limitations.

A service extracted but not reusable, not invoked, or not independently testable is not yet a completed Studio capability.
