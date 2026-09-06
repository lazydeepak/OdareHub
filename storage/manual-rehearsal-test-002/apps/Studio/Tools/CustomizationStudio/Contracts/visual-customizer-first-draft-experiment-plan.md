# Visual Customizer First Draft Experiment Plan

Status: planning only. No mutation implementation.

This document plans the first safe editable draft experiment for Visual Customizer before any mutation code is written.

## Socket Discovery And Selection

Card corner/radius candidate review was performed against existing Shell Style socket catalogs.

Selected first editable socket:

- selected_socket_id: `radius.scale`
- catalog file: `apps/Shell/Style/Resources/socket-catalog/core-tokens.json`
- label: `Corner scale`
- allowed_values: `sharp`, `soft`, `round`
- default_value: `soft`
- simple_controls: `Sharp`, `Soft`, `Round`
- advanced_token: `--radius-scale`

Why this socket is safe for first experiment:

- user-friendly mental model (sharp/soft/round corners)
- low business/process risk compared to colors, layout density, or motion
- narrow blast radius for a single-socket preview experiment
- compatible with existing Simple Mode control affordances
- can remain fully Studio-local without runtime activation

Note: `card.radius` does not exist as an exact socket id in current catalogs, so this plan intentionally uses existing `radius.scale` and does not introduce a new socket.

## 1. Plan Summary

The first editable experiment will permit draft-only updates for exactly one socket (`radius.scale`) inside Visual Customizer.

- exactly one selected socket only
- Studio-local draft only
- `proposed_value` changes only inside Visual Customizer draft context
- no runtime style activation

## 2. Architecture Law Check

Architecture laws protected:

- Studio draft state is not runtime truth.
- Shell does not consume Studio draft state.
- Platform StyleRegistry remains disconnected for this experiment.
- `public/assets` remains generated delivery output only.
- Core remains decoupled from the style customization chain.

Owner of planned fix:

- Studio / Customization Studio planning and future implementation slice.

Why this is platform tooling work, not sample-app polish:

- this work defines governance and safety boundaries for Studio as a system tool
- no business app UX/runtime polish is targeted
- no reference app behavior is modified

Expected validation evidence for future implementation:

- `git diff --check`
- `bash scripts/architecture/run_architecture_gates.sh`
- `bash scripts/system/check_deployment_readiness.sh`
- focused proof that only one socket draft value is editable and all runtime boundaries remain disconnected

## 3. Interaction Model

Friendly labels and value mapping for selected socket:

- `Sharp` -> `sharp`
- `Soft` -> `soft`
- `Round` -> `round`

Where proposed value appears:

- selected-socket inspector in Simple Mode
- Value Safety panel `proposed_value`
- optional disabled-action status text (read-only confirmation only)

How Value Safety changes in future implementation slice:

- `default_value` remains from socket catalog metadata
- `current_value` remains `Not connected yet`
- `proposed_value` moves from `No draft yet` to selected mapped value after user choice
- source becomes Studio-local draft indicator for selected socket

What remains disabled in future implementation slice:

- Apply
- Approval
- Runtime activation
- Platform registry write
- Shell consumption

## 4. Draft State Model

Studio-local draft shape for one socket only (illustrative contract shape):

```json
{
  "draft_id": "visual-customizer-local-preview",
  "status": "local_preview_only",
  "scope": "studio_customization_preview",
  "values": {
    "radius.scale": {
      "default_value": "soft",
      "current_value": null,
      "proposed_value": null,
      "source": "studio_local_draft"
    }
  }
}
```

Rules:

- `default_value` comes from Shell socket catalog metadata.
- `current_value` remains `Not connected yet` in UI.
- `proposed_value` starts as `No draft yet` in UI.
- `proposed_value` changes only after user selection in the future implementation.
- draft is not approved style.
- draft is not runtime truth.

## 5. Reset And Discard Semantics

- Reset this control:
  - return `proposed_value` for `radius.scale` to current/default baseline
- Reset this section:
  - if section scope is enabled later, reset only values in selected section scope
- Discard draft:
  - remove Studio-local draft value/artifact for this experiment scope
- Restore last approved:
  - reserved for future Platform snapshot rollback integration
  - out of scope for first editable experiment

## 6. File-Level Implementation Plan For Future Slice

Likely Studio-owned files to touch later:

- `apps/Studio/Tools/CustomizationStudio/Views/visual-customizer.php`
- `apps/Studio/Tools/CustomizationStudio/Views/inspector-panel.php`
- `apps/Studio/Tools/CustomizationStudio/Views/disabled-actions.php`
- `apps/Studio/Tools/CustomizationStudio/Assets/visual-customizer.js`
- `apps/Studio/Tools/CustomizationStudio/Services/` (Studio-local draft helper, if needed)
- `apps/Studio/Tools/CustomizationStudio/Contracts/` (contract updates only)

Forbidden files/areas for future first editable experiment:

- `app/Core`
- `apps/Shell` runtime/UI/CSS behavior
- `apps/Platform/StyleRegistry` runtime behavior
- `public/assets`
- app/module CSS files under business/system apps
- ThemeTool surfaces and behavior

## 7. Future Validation Plan

Future implementation must prove all of the following:

- only one selected socket is editable (`radius.scale`)
- `proposed_value` updates only in Studio-local draft context
- reset/discard affect only Studio draft
- no Shell consumption
- no Platform registry writes/reads
- no `public/assets` writes
- no Core changes
- architecture gates pass
- deployment readiness passes

Required checks:

- `git diff --check`
- `bash scripts/architecture/run_architecture_gates.sh`
- `bash scripts/system/check_deployment_readiness.sh`

## 8. Acceptance Criteria For Future Implementation

- selected socket is the only editable socket
- `current_value` still displays `Not connected yet`
- `proposed_value` updates in Simple Mode after selecting Sharp/Soft/Round
- `default_value` remains visible from catalog metadata
- reset/discard are limited to Studio draft state
- Apply remains disabled
- no runtime activation occurs

## 9. Explicit Non-Goals

This plan is not:

- a runtime style engine
- an apply system
- a registry writer
- a Shell consumer
- a CSS generator
- a ThemeTool replacement
- public asset output
- app/module CSS mutation

## Planning Exit Condition

No mutation code is allowed in this slice. This plan only authorizes a future implementation slice once architecture validation and boundaries remain intact.
