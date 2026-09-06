# Visual Customizer Request Validation Service Plan

Status: planning only. No request creation, approval, apply, registry I/O, Shell consumption, runtime CSS generation, Core changes, ThemeTool changes, or public asset output in this slice.

This plan defines a future server-side validation service that checks whether a Studio-local draft is eligible for a future Request Approval. It is a pre-creation validation gate only — it does not create requests, approve, apply, or activate anything.

## 1. Service Purpose

- Validate Studio-local draft eligibility for future Request Approval.
- Validation is not Apply.
- Validation is not approval.
- Validation is not runtime activation.

The service is a pure validation gate: it reads draft state, checks pre-defined rules, and returns a pass/fail result with per-check diagnostics. It must never mutate state, create artifacts, or initiate workflows.

## 2. Inputs

A future validator may read:

| Input | Source | Required |
|---|---|---|
| `selected_socket_id` | Draft artifact | Yes |
| `proposed_value` | Draft artifact `values.{socket}.proposed_value` | Yes |
| `default_value` | Socket catalog metadata | Yes |
| `draft_id` | Draft artifact | Yes |
| `diff_summary` | Draft diff preview | Yes |
| Current user context | Auth context | Yes |
| Request readiness conditions | Readiness panel state | Implicit |

All inputs must be read from Studio-local draft state. The service must not read Platform StyleRegistry, Shell runtime, Core, or `public/assets`.

## 3. Allowed Validation Checks

### Must Pass

| Check | Rule |
|---|---|
| `socket_allowed` | `selected_socket_id === 'radius.scale'` |
| `proposed_value_exists` | `proposed_value` is a non-empty string |
| `proposed_value_allowed` | `proposed_value` is one of `sharp`, `soft`, `round` |
| `studio_local_draft` | Draft artifact is Studio-local (status `local_preview_only`, no Platform registry entry) |
| `draft_shape_valid` | Draft `values` contains only `radius.scale` with only `proposed_value` |
| `diff_exists` | Diff preview summary exists and is non-empty |
| `apply_disabled` | Draft `constraints.apply_enabled` is `false` |
| `runtime_untouched` | `platform_registry_io_enabled`, `shell_consumption_enabled`, `runtime_activation_enabled` are all `false` |

### Must Not Check

- Approval status (not applicable yet)
- Registry state (must not read Platform StyleRegistry)
- Shell runtime (must not read Shell)
- Core state (must not read Core)
- `public/assets` state (must not read or write)

## 4. Validation Output Shape

The future validator must return a structured result:

```json
{
  "valid": true,
  "status": "eligible_for_request",
  "checks": {
    "socket_allowed": true,
    "proposed_value_exists": true,
    "proposed_value_allowed": true,
    "studio_local_draft": true,
    "draft_shape_valid": true,
    "diff_exists": true,
    "apply_disabled": true,
    "runtime_untouched": true
  },
  "errors": [],
  "warnings": []
}
```

When validation fails:

```json
{
  "valid": false,
  "status": "not_eligible",
  "checks": {
    "socket_allowed": true,
    "proposed_value_exists": false,
    "proposed_value_allowed": true,
    "studio_local_draft": true,
    "draft_shape_valid": true,
    "diff_exists": true,
    "apply_disabled": true,
    "runtime_untouched": true
  },
  "errors": [
    "proposed_value_exists: proposed_value is missing or empty"
  ],
  "warnings": []
}
```

### Fields

| Field | Type | Description |
|---|---|---|
| `valid` | bool | All checks pass |
| `status` | string | `eligible_for_request` or `not_eligible` |
| `checks` | object | Per-check boolean results |
| `errors` | string[] | Human-readable descriptions of failures |
| `warnings` | string[] | Non-blocking concerns |

## 5. Failure Cases

| Scenario | Failing Check | Error Message |
|---|---|---|
| Socket is not `radius.scale` | `socket_allowed` | `selected_socket_id must be radius.scale` |
| `proposed_value` missing or empty | `proposed_value_exists` | `proposed_value is missing or empty` |
| `proposed_value` not `sharp`/`soft`/`round` | `proposed_value_allowed` | `proposed_value must be one of: sharp, soft, round` |
| Draft has Platform registry coupling | `studio_local_draft` | `Draft is not Studio-local` |
| Draft `values` contains extra keys | `draft_shape_valid` | `Draft shape contains unexpected keys` |
| Diff preview is empty or missing | `diff_exists` | `Diff preview does not exist or is empty` |
| `apply_enabled` is `true` | `apply_disabled` | `Apply must remain disabled` |
| `platform_registry_io_enabled`/`shell_consumption_enabled`/`runtime_activation_enabled` is `true` | `runtime_untouched` | `Runtime coupling detected — must not read/write Platform, Shell, or Core` |

## 6. Future Service Boundary

### Service May

- Read Studio-local draft state.
- Read socket catalog metadata (allowed values, default value).
- Read current user context for identity.
- Return structured validation results.
- Be called by future request creation flow.

### Service Must Not

- Create approval requests.
- Approve or reject requests.
- Apply style changes.
- Write to Platform StyleRegistry.
- Read from Platform StyleRegistry.
- Read or write Shell runtime.
- Generate or publish CSS.
- Write to `public/assets`.
- Touch Core.
- Mutate app/module CSS.
- Send notifications.
- Log to non-diagnostic systems.
- Create files outside Studio-local draft storage.
- Mutate draft state.

## 7. Relationship to Future Request Creation

```
User proposes value
  → Draft persisted (Studio-local)
    → Diff preview generated
      → Readiness panel shows state
        → Request Approval button clicked
          → Validation service called
            → Pass: create request artifact
            → Fail: show diagnostics, no artifact
```

The validation service is the final gate before any request artifact is created. If validation fails, no request artifact may be created. The service exists to prevent invalid, malformed, or out-of-bounds drafts from entering the approval queue.

Future request creation must call this validator first. The creation endpoint must reject the request if the validator returns `valid: false`.

## 8. Validation Evidence for Future Implementation

Future implementation must prove:

- Valid `radius.scale` draft with allowed proposed value passes.
- Invalid socket (`padding.md` or empty) fails.
- Invalid proposed value (`square` or empty) fails.
- Missing proposed value fails.
- Malformed draft shape (extra fields) fails.
- Missing diff fails.
- Apply enabled by accident fails.
- Runtime coupling detected fails.
- No Platform StyleRegistry read or write.
- No Shell runtime consumption.
- No `public/assets` read or write.
- No Core coupling.
- Architecture gates pass.
- Deployment readiness passes.

## 9. Placeholder Service

The companion placeholder file `VisualCustomizerRequestValidationService.php` is an empty future-only class. It:

- Contains no request creation logic.
- Contains no approval/apply behavior.
- Contains no registry, Shell, public, Core, or ThemeTool access.
- Contains no routes.
- Contains no side effects.
- Is safe to commit as documentation-only scaffolding.

## 10. Non-Goals

This plan explicitly does not authorize:

- Request creation or approval workflow.
- Apply workflow (any form of runtime style activation).
- Platform StyleRegistry reads or writes.
- Shell runtime consumption.
- Runtime CSS generation or publishing.
- `public/assets` output or mutation.
- Multi-socket validation (only `radius.scale`).
- Socket selection beyond `radius.scale`.
- Approval queue UI or management.
- Core changes.
- ThemeTool changes.
- App/module CSS mutation.
- Notification or webhook.
- Rate limiting or quota enforcement.
- Audit logging beyond service diagnostics.

## 11. Service Location

- Plan: `apps/Studio/Tools/CustomizationStudio/Contracts/visual-customizer-request-validation-service-plan.md`
- Placeholder class: `apps/Studio/Tools/CustomizationStudio/Services/VisualCustomizerRequestValidationService.php`

The service lives in Customization Studio, following Studio's governed tooling ownership. It must never migrate to Core, Shell, Platform, or business apps.
