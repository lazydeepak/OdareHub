# ThemeTool Create/Delete/Default Plan Map

Status: Deferred planning contract. Theme Doctor is currently diagnostic-only, and all mutation work waits for adoption of the Appearance domain contract.

No runtime behavior changes.
No DB writes.
No public asset writes.
No Core edits.

## Objective

Define ThemeTool v1 lifecycle mutation plan boundaries before implementing any mutation capability.

## Scope

1. Create ThemeTool lifecycle contract.
2. Define storage and ownership model for drafts vs approved registry.
3. Define create, duplicate, edit, delete, set-default, snapshot, rollback flows.
4. Define permission/policy/environment/risk gates.
5. Preserve preview-only runtime behavior until implementation slices begin.

## Contract Deliverables

1. `docs/architecture/theme-tool-lifecycle-contract.md`
2. `docs/architecture/studio-customization-tools-contract.md` alignment update
3. `docs/architecture/studio-tool-lifecycle-contract.md` alignment update
4. `scripts/architecture/check_theme_tool_lifecycle_contract.sh`

## Planned Ownership Outcomes

1. ThemeTool owns editing workflow and draft artifacts.
2. Platform/System owns approved instance theme registry truth.
3. Shell consumes resolved approved theme contract only.
4. Public assets remain delivery output only.

## Planned Phase Progression

1. v0: preview-only (existing)
2. v1: governed draft lifecycle operations — Deferred
3. v2: approved apply/delete/default/rollback operations — Deferred

## Follow-up Implementation Slices (Post-Contract)

1. v1.1 Add theme registry reader — Deferred beyond the current read-only reader
2. v1.2 Add create theme draft — Deferred
3. v1.3 Add preview from authored draft — Deferred
4. v1.4 Add approved apply — Deferred
5. v1.5 Add set default — Deferred
6. v1.6 Add delete with safety checks — Deferred

## Validation

1. `git diff --check`
2. `bash scripts/architecture/check_theme_tool_lifecycle_contract.sh`
3. `bash scripts/architecture/run_architecture_gates.sh`
4. `bash scripts/system/check_deployment_readiness.sh`
