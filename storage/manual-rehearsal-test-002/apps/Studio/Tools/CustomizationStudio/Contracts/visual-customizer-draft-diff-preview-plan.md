# Visual Customizer Draft Diff Preview Plan

Status: planning only. No diff implementation in this slice.

This plan defines a read-only draft diff preview for the first Visual Customizer editable experiment. It does not enable apply, approval, registry I/O, Shell consumption, runtime CSS generation, public asset output, or additional editable sockets.

## Purpose

The draft diff preview should show users what changed before any apply or runtime action exists.

The first version is informational only:

- It explains the pending Studio-local draft change.
- It helps users distinguish a saved local draft from live runtime style.
- It keeps Apply disabled.
- It does not create approved style truth.

## Scope

The first diff preview is limited to one socket:

- socket id: `radius.scale`
- user label: `Corner scale`
- owner catalog: Shell socket catalog metadata
- draft source: Studio-local Visual Customizer draft

No other socket may appear in the diff preview for this phase.

## Value Comparison Model

The diff preview compares three values without connecting to runtime:

- `default_value`: read from Shell socket catalog metadata.
- `current_value`: displayed as `Not connected yet`.
- `proposed_value`: read from the Studio-local draft value for `values.radius.scale.proposed_value`.

The user-facing diff label should be plain:

- With a proposed value: `Corner scale changed from soft to sharp`
- Without a proposed value: `No draft change`

Rules:

- If `proposed_value` is absent, the diff preview must show `No draft change`.
- If `proposed_value` equals the catalog `default_value`, the preview may show the baseline reset as a local draft, but it must not imply runtime has changed.
- `current_value` must not be inferred from runtime, Shell, Platform StyleRegistry, public assets, or CSS.
- The diff model must ignore any legacy draft fields other than `values.radius.scale.proposed_value`.

## UI Placement Plan

Simple Mode should show a compact user-facing preview:

- title: `Draft change preview`
- empty state: `No draft change`
- changed state example: `Corner scale changed from soft to sharp`
- supporting note: `This is a Studio-local draft only. The live system is not changed.`

Advanced Details may show the technical socket/token view:

- socket id: `radius.scale`
- token: `--radius-scale`
- default value source: Shell socket catalog
- current value: `Not connected yet`
- proposed value source: Studio-local draft
- runtime status: not applied

Advanced Details must remain secondary and collapsed by default. Simple Mode must not expose draft file paths.

## Reset And Discard Relation

Reset and discard must remain Studio-local draft actions.

Reset this control:

- Sets `values.radius.scale.proposed_value` to the default baseline.
- The diff preview should reflect the local draft baseline value.
- The diff preview must not imply runtime style changed.

Reset this section:

- Same behavior as Reset this control while only `radius.scale` is editable.

Discard draft:

- Removes `values.radius.scale` from the Studio-local draft artifact.
- The diff preview should return to `No draft change`.
- The proposed value should disappear from the Simple Mode diff.

Restore last approved:

- Remains unavailable in this phase.
- Must not read Platform snapshots or registry values.

## Non-Goals

This plan does not authorize:

- apply activation
- approval workflow
- Platform StyleRegistry write or read
- Shell runtime consumption
- runtime CSS generation
- `public/assets` output
- multi-socket diff
- Core changes
- ThemeTool changes
- app/module CSS mutation

## Future Validation Plan

Any future implementation of this plan must prove:

- Only `radius.scale` appears in the diff preview.
- The diff reads Studio-local draft state only.
- Legacy extra draft fields are not exposed as diff inputs.
- Non-radius sockets remain read-only and absent from the diff.
- No runtime activation occurs.
- Apply remains disabled.
- Shell does not consume Studio-local drafts.
- Platform StyleRegistry is not read or written.
- `public/assets` remains untouched.
- Core remains untouched.
- `git diff --check` passes.
- `bash scripts/architecture/run_architecture_gates.sh` passes.
- `bash scripts/system/check_deployment_readiness.sh` passes.

## Acceptance Criteria For Future Implementation

- With no proposed value, Simple Mode shows `No draft change`.
- Selecting Sharp, Soft, or Round for Corner scale updates the read-only diff preview.
- Refreshing the page preserves the diff preview from the Studio-local draft.
- Discarding the draft returns the diff preview to `No draft change`.
- Apply remains disabled on every path.
- No non-radius socket appears in the diff preview.
- No runtime, registry, Shell, Core, ThemeTool, or public asset boundary is crossed.
