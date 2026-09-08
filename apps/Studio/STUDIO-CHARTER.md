# OdareHub Studio Charter

## Purpose

OdareHub Studio is the governed engineering environment for changing OdareHub without bypassing ownership, lifecycle, validation, or runtime contracts.

Studio exists to:

1. diagnose system, owner, component, configuration, resource, and runtime-integration problems;
2. propose, preview, validate, apply, verify, and roll back repairs;
3. create, inspect, edit, migrate, and delete owners and owner-owned components through governed workflows;
4. create and maintain the artifacts required for apps and modules to run correctly; and
5. compose larger engineering workflows from reusable Studio capabilities.

Studio is not merely a collection of tool pages. **Studio capabilities are the product. Tool pages are only one interface for invoking those capabilities.**

---

## Target State

Studio provides a coherent capability layer that can be invoked by:

- a focused tool page;
- App Creator, Module Creator, Owner Creator, and other larger builders;
- owner and component editors;
- diagnostics and repair workflows;
- migration planners;
- batch and automation workflows; and
- future governed Studio orchestration surfaces.

A capability must not be trapped inside a controller, view, or tool-specific request flow when another Studio workflow could legitimately reuse it.

The target interaction model is:

```text
Studio capability -> reusable service/action
Tool page         -> focused interface for one or more capabilities
Large builder     -> composition of multiple capabilities
Owner runtime     -> consumes only owner-owned output artifacts
```

---

## Studio Responsibilities

Studio may act on behalf of a target owner to:

- discover owners and owner components;
- inspect owner structure, contracts, manifests, routes, schemas, resources, dependencies, and runtime integration;
- diagnose defects, drift, missing artifacts, invalid configuration, and lifecycle violations;
- generate deterministic findings and repair proposals;
- create owners, apps, modules, views, navigation, schemas, reports, labels, locale resources, styles, templates, and other governed artifacts;
- edit, migrate, rename, reorganize, or delete those artifacts when safety requirements are satisfied;
- preview changes and runtime effects;
- produce diffs, impact analysis, dependency analysis, and deletion consequences;
- validate proposed and applied states;
- snapshot and roll back supported mutations;
- record provenance, diagnostics, decisions, and results; and
- hand completed runtime artifacts back to their target owner.

Studio owns the engineering workflow. The target app, module, or other owner continues to own the resulting runtime capability and artifacts.

---

## Capability-First Architecture

### Capability definition

A Studio capability is a reusable, testable operation that performs one bounded engineering responsibility.

Examples include:

- discover owners;
- inspect an owner contract;
- scan references;
- validate a manifest;
- create a locale key;
- generate an owner component;
- calculate a safe deletion plan;
- preview a style change;
- create a snapshot;
- apply an approved mutation;
- verify runtime consumption; and
- restore a prior state.

A capability may be read-only or mutating. It must declare which it is.

### Required capability characteristics

Every capability must have:

- a single, explicit responsibility;
- structured inputs and outputs;
- deterministic behavior where practical;
- an explicit target owner or target scope;
- authorization and lifecycle checks appropriate to its effect;
- structured diagnostics and failure states;
- direct probe or contract coverage;
- no dependency on a particular view or HTML request shape;
- no hidden mutation;
- no hidden runtime ownership transfer; and
- a stable invocation boundary that other Studio workflows can call.

### Tool-page rule

A tool page may coordinate presentation, input collection, and result display. It must not become the only place where its engineering capability exists.

Controllers and views must remain adapters. Reusable analysis, generation, validation, planning, mutation, and verification logic belongs in Studio capability services/actions.

### Composition rule

Larger Studio workflows must compose existing capabilities rather than reimplement them.

For example, App Creator should reuse existing owner discovery, manifest generation, schema planning, localization, style, navigation, validation, snapshot, apply, and verification capabilities. It should orchestrate them into an app-creation lifecycle instead of owning duplicate implementations.

---

## Governed Change Lifecycle

Mutating Studio workflows follow this direction unless a stricter owner contract applies:

```text
Discover -> Analyze -> Diagnose -> Plan -> Preview/Diff -> Approve
-> Snapshot -> Apply -> Verify -> Hand back -> Roll back when required
```

Deletion additionally requires:

```text
Dependency discovery -> impact classification -> removal plan
-> explicit approval -> snapshot/backup -> delete -> orphan verification
```

No UI convenience, builder, automation, or batch operation may bypass the required lifecycle stages.

---

## Ownership and Runtime Boundary

Studio is optional. Apps and modules must run when Studio is absent or disabled.

Therefore:

- runtime business code must not call Studio services;
- generated or modified runtime artifacts remain under the target owner's canonical paths;
- Studio may retain diagnostics, provenance, plans, snapshots, and workflow metadata;
- Studio must use platform and owner contracts rather than invent private runtime channels;
- Studio capabilities may be reused by other Studio workflows, but not become hidden runtime dependencies of business owners; and
- disabling a tool page must not invalidate owner runtime artifacts already handed back correctly.

---

## Common Capability Families

Studio capabilities should converge into these families:

1. **Discovery** — locate owners, components, resources, references, routes, schemas, contracts, and runtime consumers.
2. **Diagnosis** — identify defects, drift, missing artifacts, invalid states, and lifecycle violations.
3. **Planning** — produce deterministic creation, repair, migration, rename, and deletion plans.
4. **Generation** — create valid owner-owned artifacts from governed definitions.
5. **Editing** — change owner-owned artifacts with validation, diff, and provenance.
6. **Validation** — validate proposed state, stored state, and runtime-consumed state.
7. **Mutation** — apply approved file, registry, metadata, or schema changes safely.
8. **Snapshot and rollback** — preserve and restore supported states.
9. **Verification** — confirm that applied artifacts are registered, consumed, reachable, and operational.
10. **Composition** — orchestrate multiple capabilities into larger builders and repair workflows.

These are shared architectural families, not mandatory page categories.

---

## Tool Admission Test

A proposed Studio tool or feature belongs in Studio only when it answers all of the following:

1. What Studio goal does it advance?
2. Which owner, component, system state, or runtime artifact does it act on?
3. Which reusable capability does it add or compose?
4. Can its core behavior be invoked without its page?
5. Does it reuse existing capabilities where they already exist?
6. Does it preserve target-owner runtime ownership?
7. Does it follow the governed change lifecycle?
8. Does it have focused validation and probe coverage?

A feature that cannot answer these questions is mis-scoped, incomplete, or does not belong in Studio.

---

## Non-Goals

Studio must not become:

- an unrelated collection of admin utilities;
- a second runtime owner for business capabilities;
- a set of pages with duplicated private implementations;
- a shortcut around app, module, platform, schema, routing, ACL, or lifecycle contracts;
- an automatic mutation engine without preview, approval, evidence, and recovery;
- a replacement for target-owner runtime services; or
- a generic visual builder detached from valid OdareHub owner artifacts.

---

## Success Criteria

Studio is succeeding when:

- every tool clearly contributes to the Studio purpose;
- reusable capability logic is independent of tool pages;
- larger builders compose smaller capabilities;
- equivalent engineering operations do not diverge across tools;
- all mutations are attributable, reviewable, validated, and recoverable where supported;
- generated artifacts remain correctly owned and runtime-consumed;
- Studio can diagnose both authored state and actual runtime state; and
- adding a new builder increases orchestration, not duplication.

---

## Governing Decision

When choosing between a locally convenient tool implementation and a reusable Studio capability, choose the reusable capability unless doing so would violate an owner, lifecycle, security, or runtime boundary.
