# Core Lock Gate Contract

This note defines non-runtime expectations for `scripts/architecture/check_core_lock_scope.sh`.

## Required Scan Scope

The gate must inspect current git working state for Core Engine path changes under `app/` using both:

- `git diff --name-only HEAD -- app`
- `git ls-files --others --exclude-standard -- app`

The scan scope must not silently narrow to subpaths or staged-only subsets.

## Required Behavior

- If no Core files changed, the gate passes.
- If Core files changed and `ARCHITECTURE_GATE_ALLOW_CORE` is not `1`, the gate fails.
- If Core files changed and `ARCHITECTURE_GATE_ALLOW_CORE=1`, the gate passes with explicit override output.
- Override usage must be visible in output and treated as exceptional approval, not default behavior.

## Required Output Clarity

The gate must print:

- scanned Core change summary
- changed Core files when present
- explicit failure reason when approval is missing
- explicit pass reason when override is used

## Architecture Boundaries Reinforced

Core remains locked platform law and primitives owner only. Core must not absorb:

- Shell runtime chrome/composition ownership
- Platform governance ownership
- Studio authoring/workflow ownership
- app/module business logic, routes, views, CSS, or capability semantics

Core changes are exceptional and require explicit approval.
