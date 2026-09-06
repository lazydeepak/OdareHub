# Visual Customizer Draft Model Contract

Status: draft model contract only. No runtime connection.

This contract defines the first safe draft model for Visual Customizer. It is governance documentation only and does not enable editable controls, draft saving, apply workflows, registry writes, or runtime style activation.

## Owner

- Owner: Studio / Customization Studio.
- Studio owns draft state only.
- Draft state is not runtime truth.
- Draft state is not approved style.

## Storage Boundary

The first draft artifact must remain Studio-local only.

- Allowed owner home: Studio-local artifact under `apps/Studio/Tools/CustomizationStudio/...`.
- Forbidden: Platform StyleRegistry ownership for draft state.
- Forbidden: Shell ownership for draft state.
- Forbidden: writes to `public/assets`.
- Forbidden: direct mutation of app/module CSS files.
- Forbidden: any Core touch.

## Value Meanings

Visual Customizer must keep value semantics explicit and stable:

- `default_value`: value from Shell socket catalog metadata.
- `current_value`: approved Platform registry value in a future phase.
- `proposed_value`: Studio draft value.

Until intentional future integration is approved:

- `current_value` must remain `Not connected yet`.
- `proposed_value` must remain `No draft yet`.

## Reset Semantics

Reset behavior names are reserved now so future implementation does not drift:

- Reset this control:
  - Returns the control `proposed_value` to current/default baseline.
- Reset this section:
  - Returns all selected-scope `proposed_value` entries to current/default baseline.
- Discard draft:
  - Removes Studio-local draft artifact/value.
- Restore last approved:
  - Future Platform snapshot rollback path.
  - Out of scope for first editable experiment.

## First Editable Experiment Boundary

Allowed first experiment:

- Exactly one harmless socket only.
- Recommended candidates:
  - `core-tokens.shape_level`, or
  - `card.radius`.
- Studio-local draft only.
- `proposed_value` may update in Studio draft preview only.

Forbidden in first experiment:

- No Shell consumption.
- No Platform StyleRegistry write.
- No `public/assets` output.
- No global CSS write.
- No app/module CSS mutation.
- No Core change.
- No apply action.
- No approval workflow.
- No runtime style activation.

## Validation Rules For Future Implementation

Before enabling the first editable slice, implementation must prove all of the following:

- Draft artifact is Studio-owned only.
- Shell remains disconnected from Studio draft state.
- Platform StyleRegistry remains untouched by draft updates.
- `public/assets` remains untouched.
- Core remains untouched.
- Reset/discard actions affect only Studio draft state.
- Architecture gates pass.

## Non-Goals

This contract explicitly does not define or authorize:

- a runtime style engine
- an apply system
- a registry writer
- a Shell consumer
- a CSS generator
- a ThemeTool replacement
- public asset output

## Notes

This document is a boundary contract for safe sequencing. It is intentionally stricter than current UI affordances and must remain authoritative until a separately approved editable implementation slice is introduced.
