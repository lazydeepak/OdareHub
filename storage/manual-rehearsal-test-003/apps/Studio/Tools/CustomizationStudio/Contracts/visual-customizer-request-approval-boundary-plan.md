# Visual Customizer Request Approval Boundary Plan

Status: planning only. No approval implementation in this slice.

This plan defines what a future `Request Approval` action means for the first Visual Customizer editable experiment. It does not enable approval, apply, Platform StyleRegistry reads or writes, Shell runtime consumption, runtime CSS generation, public asset output, Core changes, ThemeTool changes, or additional editable sockets.

## Purpose

`Request Approval` is a future Studio workflow step that packages a reviewed Studio-local draft for governance review.

It is not Apply.

The request boundary exists to keep three states separate:

- Studio-local draft: editable preview data owned by Studio tooling.
- Approval request: immutable review package and evidence record owned by Studio workflow.
- Runtime truth: approved active/default style registry truth owned by Platform/System and not touched by this boundary.

## Who Can Request Approval

Future implementation may allow a request only when all of these are true:

- The actor is authenticated.
- The actor is authorized to use Studio Customization Studio tooling.
- The actor has the minimum authority required by the Visual Customizer entry contract, currently `platform_admin`.
- The actor has permission to request review for the target owner/scope.
- The actor is not bypassing required validation or ownership checks.

Requesting approval does not grant apply permission. A requester may be different from a future approver, and future policy should disallow self-approval wherever risk policy requires separation.

## Draft Data Included

The first request package is limited to the current Visual Customizer experiment:

- tool id: `customization-studio.visual-customizer`
- draft id: `visual-customizer-local-preview`
- selected socket id: `radius.scale`
- user label: `Corner scale`
- advanced token: `--radius-scale`
- default value from Shell socket catalog metadata
- current value displayed as `Not connected yet`
- proposed value from `values.radius.scale.proposed_value`
- diff summary, such as `Corner scale changed from soft to sharp`
- source marker: Studio-local draft
- runtime marker: not applied
- requester id/email/handle
- request timestamp
- validation evidence captured before request

No other socket may be included in this first request boundary. Extra draft fields, legacy fields, or unknown socket values must be ignored or rejected rather than packaged for approval.

## Validation Before Request

A future implementation must validate before an approval request can be created:

- The draft shape matches the hardened Visual Customizer persisted draft contract.
- The only requested editable socket is `radius.scale`.
- `proposed_value` is present and is one of the allowed catalog choices for `radius.scale`.
- The proposed value is read from Studio-local draft storage only.
- The Shell socket catalog metadata can identify the socket and default value.
- The draft diff preview can be regenerated from the same draft data.
- The actor passes Studio and route authorization.
- Apply remains disabled.
- Platform StyleRegistry is not read or written.
- Shell runtime is not connected to the Studio draft.
- `public/assets`, Core, ThemeTool, and runtime CSS output remain untouched.

If validation fails, no approval request should be created. The UI may explain the validation failure later, but that is outside this planning slice.

## What The Approval Request Stores

A future approval request should store a review package under Studio-owned workflow storage, not Platform registry truth.

The package should be append-only or immutable after submission except for review status fields. The minimum record should include:

- request id
- request status, initially `requested`
- tool id and version
- draft id and draft fingerprint
- requester identity
- requested at timestamp
- target owner/scope metadata
- socket id, label, token, default value, proposed value
- regenerated diff summary
- validation results and validation timestamp
- risk classification
- non-apply marker
- runtime untouched marker
- links or references to the Studio-local draft artifact and review evidence

The request record must not become runtime style truth. It is review evidence only until a separate future approval/apply workflow is explicitly implemented.

## What Remains Disabled

This boundary does not enable:

- approval decision UI
- approval status transitions beyond a future submitted/requested package
- Apply
- restore last approved
- Platform StyleRegistry reads or writes
- Shell runtime draft consumption
- runtime CSS generation
- `public/assets` generation or mutation
- multi-socket editing
- non-radius socket editing
- Core changes
- ThemeTool changes
- app/module CSS mutation

## Why This Is Not Apply

Requesting approval only asks for review of a Studio-local draft package.

It must not:

- write active/default style values
- publish CSS
- update runtime tokens
- update Shell consumption contracts
- change Platform registry truth
- change app/module owned runtime artifacts
- make the preview value visible to end users outside Studio

Apply is a later, separate, higher-risk workflow that requires approval evidence, validation gates, ownership handoff rules, rollback readiness, and explicit registry/runtime write authority.

## Why Platform Registry Remains Untouched

Platform/System owns approved active/default style registry truth. The request boundary only packages Studio draft evidence for review.

Keeping Platform registry untouched proves:

- Studio drafts are not runtime source of truth.
- Approval requests are not active style values.
- The live system remains unchanged while review is pending.
- Shell and runtime cannot accidentally consume unapproved draft values.
- Future apply can remain a deliberate, separately validated transition.

## Future Validation Plan

Any future implementation of this plan must prove:

- Request Approval is unavailable when no valid `radius.scale` draft exists.
- Request Approval packages only `radius.scale`.
- The stored request contains draft fingerprint, requester, diff, validation evidence, and non-apply/runtime-untouched markers.
- Platform StyleRegistry is not read or written.
- Shell does not consume the draft or request.
- Apply remains disabled.
- Non-radius sockets remain read-only.
- Core remains untouched.
- ThemeTool remains untouched.
- `public/assets` remains untouched.
- `git diff --check` passes.
- `bash scripts/architecture/run_architecture_gates.sh` passes.
- `bash scripts/system/check_deployment_readiness.sh` passes.

## Acceptance Criteria For Future Implementation

- A user with a valid `radius.scale` local draft can submit a request package for approval review.
- A user without a valid draft cannot submit a request package.
- The request package clearly says it is not applied.
- Refreshing the Visual Customizer after request still shows runtime as not applied.
- Apply remains disabled after request.
- No registry, Shell runtime, Core, ThemeTool, runtime CSS, or public asset boundary is crossed.
