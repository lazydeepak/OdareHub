# Visual Customizer Persisted Draft Artifact Plan

Status: planning only. No persisted-draft implementation in this slice.

This document defines the planning contract for the next safe phase after client-only draft preview: Studio-local persisted draft artifacts.

## Objective

Define how Studio-local persisted draft artifacts should be stored, shaped, reset, discarded, and deleted without enabling runtime coupling.

This plan does not implement persistence.

## Architecture Laws Protected

- Studio owns draft tooling state only.
- Draft artifacts are not runtime style truth.
- Shell must not consume Studio draft artifacts.
- Platform StyleRegistry must not read or write Studio draft artifacts.
- public/assets must remain delivery output only.
- Core must remain untouched.

Owner of planned work:

- Studio / Customization Studio.

Why this is platform tooling work:

- It defines governance and lifecycle handling for Studio-owned draft artifacts.
- It does not modify business app runtime behavior.

## Storage Location Plan

Planned persisted draft home (Studio-local only):

- storage root: storage/studio/customization
- Visual Customizer draft root: storage/studio/customization/visual-customizer/drafts

Planned per-user path:

- storage/studio/customization/visual-customizer/drafts/user-{user_id}/visual-customizer-local-preview.json

Optional future extension path for multiple drafts:

- storage/studio/customization/visual-customizer/drafts/user-{user_id}/{draft_id}.json

Rules:

- No writes outside storage/studio/customization.
- No files in public/assets.
- No files in apps/Shell.
- No files in apps/Platform/StyleRegistry.
- No files in app/Core.

## Draft JSON Shape Plan

Planned single-file JSON shape:

```json
{
  "draft_id": "visual-customizer-local-preview",
  "status": "local_preview_only",
  "owner": "studio_customization_studio",
  "scope": "studio_customization_preview",
  "created_at": "2026-05-27T00:00:00Z",
  "updated_at": "2026-05-27T00:00:00Z",
  "selected_socket_id": "radius.scale",
  "values": {
    "radius.scale": {
      "default_value": "soft",
      "current_value": null,
      "proposed_value": "round",
      "source": "studio_local_draft"
    }
  },
  "constraints": {
    "editable_socket_ids": ["radius.scale"],
    "apply_enabled": false,
    "runtime_activation_enabled": false,
    "shell_consumption_enabled": false,
    "platform_registry_io_enabled": false
  }
}
```

Shape rules:

- selected_socket_id must be radius.scale for first persisted phase.
- current_value remains null and displayed as Not connected yet in UI.
- proposed_value remains draft-only and never indicates approved style.
- constraints flags are explicit and false for forbidden coupling.

## Reset, Discard, And Delete Semantics

Reset this control:

- scope: only radius.scale proposed_value
- behavior: set proposed_value to baseline default_value
- persistence: update same draft file

Reset this section:

- first persisted phase behavior: equivalent to reset this control because only one editable socket exists
- persistence: update same draft file

Discard draft:

- behavior: remove values.radius.scale proposed_value from draft content
- result in UI: proposed value shows No draft yet
- persistence: write updated file with draft metadata preserved

Delete draft artifact:

- behavior: remove the entire draft file from storage/studio/customization/visual-customizer/drafts/user-{user_id}/
- trigger: explicit delete action (future, separate from discard)
- out of scope in first persisted implementation unless explicitly approved

Restore last approved:

- remains out of scope
- no Platform snapshot integration in this phase

## Safety Proof Requirements For Future Implementation

Future persisted-draft implementation must prove all of the following:

1. Storage confinement proof
- Draft file is created only under storage/studio/customization/visual-customizer/drafts.
- No writes in Shell, Platform, Core, or public/assets.

2. Single-socket boundary proof
- Only radius.scale is persisted and editable.
- Non-radius sockets remain read-only.

3. No runtime coupling proof
- Shell does not read persisted draft file.
- Platform StyleRegistry does not read or write persisted draft file.
- Apply remains disabled.
- Runtime activation remains disabled.

4. Reset/discard correctness proof
- Reset this control sets proposed_value to baseline default.
- Reset this section matches single-socket semantics.
- Discard clears proposed_value only.

5. Governance gate proof
- git diff --check passes.
- bash scripts/architecture/run_architecture_gates.sh passes.
- bash scripts/system/check_deployment_readiness.sh passes.

## Forbidden Areas For Future Persisted-Draft Slice

- app/Core
- apps/Shell runtime behavior
- apps/Shell style socket consumption paths
- apps/Platform/StyleRegistry runtime behavior
- public/assets
- app/module CSS mutation
- ThemeTool behavior

## Acceptance Criteria For Future Persisted-Draft Implementation

- Draft file is created and updated only under planned Studio storage path.
- radius.scale remains the only editable and persisted socket.
- proposed_value survives page reload for same user.
- Apply remains disabled.
- current_value remains Not connected yet.
- No runtime style activation occurs.
- All architecture and deployment checks pass.

## Non-Goals

This plan is not:

- a runtime style engine
- an apply system
- a registry writer
- a Shell consumer
- a CSS generator
- a ThemeTool replacement
- public asset output
- app/module CSS mutation
