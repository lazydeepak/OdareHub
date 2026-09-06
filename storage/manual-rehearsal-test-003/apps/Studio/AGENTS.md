# /apps/Studio/AGENTS.md

> Susankhya Studio is the optional System App for governed system engineering and no-code composition.
> Studio diagnoses and repairs the system, and creates, edits, migrates, and deletes owners and owner-owned components through governed workflows.
> Studio owns builder/editor workflows; other apps must not depend on Studio at runtime.

---

## Governing Goal

Studio exists to:

- diagnose system, owner, component, resource, configuration, and runtime-integration problems;
- plan, preview, validate, apply, verify, and roll back supported repairs;
- create, inspect, edit, migrate, and delete owners and owner-owned components safely;
- create and maintain the artifacts required for apps and modules to run correctly; and
- compose larger engineering workflows from reusable Studio capabilities.

Every Studio tool and feature must directly advance this goal.

**Studio capabilities are the product. Tool pages are only one interface for invoking those capabilities.**

Follow `STUDIO-CHARTER.md` for the governing product and architecture direction, and `STUDIO-IMPLEMENTATION-PLAN.md` for incremental migration and delivery rules.

---

## Ownership Scope

Studio owns:
- no-code app/module/view/navigation composition
- Studio routes, views, controllers, services, navigation, and admin entry surfaces
- analyze, changes/diff, apply, preview, upgrade, and rollback-support workflows
- reusable Studio capabilities for discovery, diagnosis, planning, generation, editing, validation, mutation, snapshot, rollback, verification, and composition
- migration bridges from legacy GUI Studio surfaces while extraction is in progress

Studio is a governed tooling app hired by app/module owners. It may create, modify, analyze, diff, and apply artifacts on behalf of owners, then hand those artifacts back to the owning app/module. Studio keeps diagnostics and provenance, but it does not own runtime business capabilities.

CSS follows the same handover rule: Studio may generate, modify, preview, diff, and apply CSS for an owner, but runtime CSS for an app/module view remains owned by the target app/module and must be written/recorded under that owner's artifact path.

---

## Capability Architecture Rules

- Reusable engineering behavior must live in Studio services/actions, not only in tool controllers, views, routes, or request handlers.
- A capability must be callable without rendering or entering its tool page.
- Tool pages are adapters that collect input, invoke capabilities, and present structured results.
- App Creator, Module Creator, Owner Creator, component editors, repair workflows, and other larger Studio tools must compose existing capabilities instead of reimplementing them.
- Capability implementations must have explicit responsibility, effect, target scope, structured inputs/outputs, diagnostics, and focused probe coverage.
- Read-only, planning, mutating, and verification effects must be declared and must match observable behavior.
- Mutating capabilities must enforce the required analyze, diff, approval, snapshot, apply, and verification lifecycle.
- Capability failures must return controlled diagnostics; they must not break the entire Studio workspace when safe degradation is possible.
- Do not create a shared abstraction without a real capability and a real consumer proving the boundary.
- Extract one bounded authority at a time and preserve existing page behavior through adapters until parity is certified.
- A service moved out of a controller but not independently callable, testable, or reusable is not yet a completed Studio capability.

---

## System App Rules

- Studio is optional and installable per instance.
- Canonical routes must live under `/apps/studio/...`.
- The app must respect installed/enabled, disabled, and not-installed lifecycle states.
- Platform may link to Studio conditionally, but Platform must not own Studio logic.
- Core, Platform, Manufacturing, SBAIO, LazyPOS, and other business apps must run normally when Studio is absent or disabled.
- Do not create hidden runtime dependencies from other apps back into Studio.
- Reuse of Studio capabilities by other Studio workflows does not permit owner runtime code to call Studio.

---

## Composition Rules

- Compose and edit registered apps, modules, views, navigation, components, and owner resources only through platform contracts, registries, manifests, packages, and lifecycle/governance conventions.
- Preserve registry/package/governance conventions already used by generated artifacts.
- Keep create vs upgrade modes separate.
- Preserve the safe Analyze -> Changes -> Apply direction; do not bypass analysis, diff, approval, rollback, or lifecycle gates.
- Deletion must include dependency discovery, impact classification, explicit approval, backup/snapshot where supported, and orphan verification.
- Reuse existing discovery, diagnosis, generation, validation, mutation, snapshot, rollback, and verification capabilities before adding local implementations.
- Do not expand Studio feature scope until the legacy Platform/Base/`/ops` implementation is extracted or bridged behind Studio ownership.
- Follow `../../docs/experience-composition-architecture-plan.md` for ACL, Workspace Profile, per-user override, and `ResolvedExperience` work.
- Studio may edit Workspace Profile and per-user override artifacts through governed workflows, but it must not grant permissions or expose capabilities denied by ACL.
- Studio-generated or Studio-modified runtime artifacts must remain owned by their target app/module, not by Studio.
- Studio-generated or Studio-modified CSS must remain owned by the target app/module view, not by Studio, Platform, Base, Shell, or Core.

---

## Tool Admission Rules

Before creating or expanding a Studio tool, identify:

- the Studio goal it advances;
- the owner, component, system state, or runtime artifact it acts on;
- the capabilities it adds, reuses, or composes;
- how its core behavior is invoked without the page;
- its read, plan, mutate, and verify effects;
- its lifecycle and rollback requirements;
- its target-owner and runtime boundary; and
- its focused probe plan.

A tool that cannot satisfy these requirements is mis-scoped, incomplete, or does not belong in Studio.

---

## Required References

Follow these with priority:
- `../../AGENTS.md`
- `STUDIO-CHARTER.md`
- `STUDIO-IMPLEMENTATION-PLAN.md`
- `../../docs/experience-composition-architecture-plan.md`
- `../../docs/architecture/APP-CONTRACT.md`
- `../../docs/architecture/ROUTING-STANDARD.md`
- `../../docs/architecture/CLEANUP-AND-LIFECYCLE.md`
- `../../docs/contracts/erp-app-studio/`
