# Contracts

Placeholder for Customization Studio draft, preview, diff, and handover contracts.

Customization Studio is Studio-owned governed tooling. It edits drafts and previews only; approved active style registry truth belongs to Platform/System. Shell owns style sockets and runtime consumption, apps/modules own scoped CSS and consume sockets, organization owns brand identity, and `public/assets` is generated delivery output only.

## Planned Contracts

- `visual-customizer-entry-contract.md` documents the disabled/read-only Visual Customizer route and Customization Studio landing entry rules. It permits preview skeleton routing only and does not enable runtime consumption, saving, applying, registry writes, Shell connections, or public asset output.
- `visual-customizer-draft-model-contract.md` defines the first safe Studio-local draft model boundary. It formalizes owner scope, storage limits, value semantics, reset semantics, first editable experiment limits, validation requirements, and explicit non-goals. It does not enable editable controls, draft persistence, apply workflows, registry writes, Shell/runtime consumption, or public asset output.
- `visual-customizer-first-draft-experiment-plan.md` plans the first safe editable experiment using one existing harmless socket (`radius.scale`) in Studio-local draft preview context only. It defines interaction mapping, draft shape, reset/discard semantics, future file touch boundaries, validation evidence, and acceptance criteria while keeping runtime, Shell, Platform registry, Core, and public assets disconnected.
- `visual-customizer-persisted-draft-artifact-plan.md` plans the next phase where Studio may persist draft artifacts under Studio-local storage only. It defines path strategy, JSON shape, reset/discard/delete semantics, and safety proofs that Shell, Platform registry, public assets, Core, and ThemeTool remain untouched.
- `visual-customizer-draft-diff-preview-plan.md` plans a read-only draft diff preview for `radius.scale` only. It defines the default/current/proposed comparison model, Simple Mode and Advanced Details placement, reset/discard relation, non-goals, and future validation proof requirements. The plan keeps apply, approval, registry I/O, Shell consumption, runtime CSS generation, public asset output, Core changes, and ThemeTool changes out of scope.
- `visual-customizer-request-approval-boundary-plan.md` plans the future Request Approval boundary for the first Visual Customizer editable experiment. It defines requester authorization, packaged draft data, pre-request validation, approval request storage, disabled capabilities, and why Request Approval is not Apply while keeping Platform registry truth untouched.
- `visual-customizer-approval-request-artifact-plan.md` plans the future Studio-owned approval request artifact shape for `radius.scale`. It defines required fields, field rules, validation inputs, status boundaries, and non-runtime flags while keeping request UI, approval, Apply, registry I/O, Shell runtime consumption, Core, ThemeTool, and public assets out of scope.
- `visual-customizer-request-creation-plan.md` plans the future request creation workflow for the first Visual Customizer editable experiment (`radius.scale`). It defines enablement rules, duplicate prevention, validation-before-creation requirements, user-facing behavior, security/ownership rules, and explicit non-goals while keeping Apply, approval, registry I/O, Shell consumption, Core, ThemeTool, and public assets out of scope.
- `visual-customizer-request-validation-service-plan.md` plans the future server-side request validation service that checks draft eligibility before any Request Approval artifact is created. It defines service purpose, inputs, allowed checks, validation output shape, failure cases, service boundary, and relationship to future request creation while keeping request creation, approval, apply, registry I/O, Shell consumption, Core, ThemeTool, and public assets out of scope.
- `visual-customizer-recheck-readiness-flow-plan.md` plans a future recheck flow that allows the readiness panel to re-run server-side validation on the current persisted Studio draft instead of requiring a full page refresh. It solves the staleness problem — server validation currently runs only at page-load, so client-side draft changes remain invisible until refresh. The plan defines recheck options, server behavior, client behavior, and preconditions for Request Approval enablement while keeping request creation, approval, apply, registry I/O, Shell consumption, Core, ThemeTool, and public assets out of scope.
- `visual-customizer-apply-boundary-plan.md` defines the Apply boundary as write approved proposed_value to Platform StyleRegistry. It documents preconditions, separation of concerns (approve vs snapshot vs apply vs shell), recovery model, risk assessment, and acceptance criteria. It does not authorize Apply implementation.
- `visual-customizer-first-apply-implementation-review.md` reviews and finalizes the exact implementation boundary before Apply code is written. Builds on the committed Platform registry contract and probe. Defines hard preconditions, exact operation sequence, UI rules, recovery model, validation plan, implementation file list, and commit boundary.

## V1 Foundation Checkpoint

Feature work is paused. See the comprehensive checkpoint document:
- `docs/architecture/visual-customizer-v1-foundation-checkpoint.md`

## Downstream Boundary

Shell consumption of approved Platform StyleRegistry values is planned at:
- `docs/architecture/shell-approved-style-consumption-boundary.md` (architecture level)
- `apps/Shell/Style/Contracts/approved-style-consumption-boundary-plan.md` (Shell level)

Studio's lifecycle ends at Apply + registry status. Shell consumption is a separate Shell-owned phase. Studio contracts do not authorize Shell, runtime, Core, or public/assets changes.
