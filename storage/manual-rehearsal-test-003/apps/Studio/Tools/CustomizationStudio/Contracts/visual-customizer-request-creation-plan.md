# Visual Customizer Request Creation Plan

Status: planning only. No request creation implementation, approval workflow, apply, registry I/O, Shell consumption, runtime CSS generation, Core changes, ThemeTool changes, or public asset output in this slice.

This plan defines when and how a future "Request Approval" action may create a Studio-owned approval request artifact for the first Visual Customizer editable experiment (`radius.scale`). It builds on the existing request approval boundary plan and approval request artifact plan.

## 1. Purpose

"Request Approval" means packaging a validated Studio-local draft change for review. It does not apply style. It does not write Platform StyleRegistry. It does not activate runtime style. It is a governance handoff between Studio-local drafting and a future approval/review step.

The workflow boundary is:

```
Studio-local draft (editable)
  → Validation gate (pass required)
    → Request Approval creation (this plan)
      → Pending review (immutable package)
        → Future: approval, rejection, apply (not in this slice)
```

## 2. Enablement Rules

A future "Request Approval" button may become enabled only when **all** of the following are true:

- Selected socket is `radius.scale`.
- `proposed_value` exists and is non-empty.
- `proposed_value` is one of `sharp`, `soft`, `round`.
- Draft artifact is Studio-local (not Platform registry).
- Diff preview exists (summary text differs from "No draft change").
- Validation gate passes (see [validation gate doc](/docs/visual-customizer-request-validation-gate.md)).
- Apply remains disabled.
- Shell remains untouched.
- Platform StyleRegistry remains untouched.
- `public/assets` remains untouched.
- Core remains untouched.
- ThemeTool remains untouched.

If any rule fails, the Request Approval button must remain disabled and the failing rule must be surfaced as a clear inline diagnostic in the readiness panel.

## 3. Request Artifact Storage

Future Studio-owned storage location (example):

```
storage/studio/customization/visual-customizer/approval-requests/
```

Each request artifact is a JSON file named by `request_id`, e.g.:

```
storage/studio/customization/visual-customizer/approval-requests/vc-approval-request-{uuid}.json
```

The storage must remain **Studio-owned only**. It must not use:

- Platform StyleRegistry.
- Shell storage or runtime.
- `public/assets`.
- Core storage primitives.
- app/module owned CSS artifact paths.

## 4. Request Artifact Lifecycle

The request artifact follows these statuses:

| Status | Meaning |
|---|---|
| `draft_ready` | Draft exists and is eligible for request creation; no request submitted yet |
| `pending_review` | Request submitted; waiting for review (initial status after creation) |
| `review_rejected` | Reviewer determined the request should not proceed |
| `review_cancelled` | Requester or governance cancelled the review before decision |
| `approved_for_future_apply` | Reviewer approved; apply remains a separate future phase |

Important distinctions:

- `approved_for_future_apply` is **not** runtime activation. It means the review passed and the value is ready for a future apply workflow.
- Apply remains a separate, higher-risk phase that is **not** part of this plan.
- Status transitions beyond creation (`pending_review` → approval/rejection/cancellation) are **not** implemented by this plan.

## 5. Request Artifact Contents

The artifact follows the shape already planned in `visual-customizer-approval-request-artifact-plan.md`. Minimum required fields for the first creation:

```json
{
  "schema": "studio.visual_customizer.approval_request.v1",
  "request_id": "vc-approval-request-{uuid}",
  "draft_id": "visual-customizer-local-preview",
  "draft_fingerprint": "sha256:...",
  "requested_by": {
    "user_id": 1,
    "email": "admin@example.com",
    "handle": "admin"
  },
  "tool_id": "customization-studio.visual-customizer",
  "selected_socket_id": "radius.scale",
  "socket_label": "Corner scale",
  "advanced_token": "--radius-scale",
  "default_value": "soft",
  "current_value": "Not connected yet",
  "proposed_value": "sharp",
  "diff_summary": "Corner scale changed from soft to sharp",
  "validation_status": "passed",
  "status": "pending_review",
  "created_at": "2026-05-29T00:00:00Z",
  "non_runtime_flags": {
    "not_applied": true,
    "platform_registry_untouched": true,
    "shell_runtime_untouched": true,
    "public_assets_untouched": true,
    "core_untouched": true,
    "theme_tool_untouched": true
  }
}
```

## 6. Duplicate Prevention

Future implementation must prevent duplicate pending requests for the same user/draft/socket. The following checks must block creation:

- If a `pending_review` request already exists for the same `draft_id` and `selected_socket_id` and `requested_by.user_id`, a new request must not be created.
- If the existing `pending_review` request has the same `draft_fingerprint`, the creation must be blocked with a "pending request already exists" diagnostic.
- If the existing `pending_review` request has a different `draft_fingerprint`, the creation must either:
  - Cancel the old request and create a new one (with strong confirmation), **or**
  - Block creation with "update the existing request" guidance.
- A user may create a new request only after the previous request transitions to `review_rejected`, `review_cancelled`, or `approved_for_future_apply`.

This prevents request sprawl while keeping the review queue manageable.

## 7. Validation Before Creation

Before any request artifact is created, the implementation must run all rules defined in:

[docs/visual-customizer-request-validation-gate.md](/docs/visual-customizer-request-validation-gate.md)

The nine validation rules are:

1. `selected_socket` must be `radius.scale` only.
2. `proposed_value` must exist and be non-empty.
3. `proposed_value` must be one of: `sharp`, `soft`, `round`.
4. Draft artifact must be Studio-local (no Platform registry entry).
5. Diff summary must exist and be non-empty.
6. Apply remains disabled — no runtime mutation.
7. Platform registry untouched.
8. Shell untouched.
9. `public/assets` untouched.

If any rule fails:
- No request artifact is created.
- The failing rule is surfaced as a clear inline diagnostic in the readiness panel.
- The "Request Approval" button remains disabled.

Validation must be synchronous and run on the server side before any storage write. Client-side pre-validation is welcome for UX responsiveness but must not replace server-side validation.

## 8. User-Facing Behavior

Future UX must show:

- "Request Approval" button enabled only when eligibility rules (section 2) and validation gate (section 7) pass.
- A confirmation step before creation (e.g., "Submit draft change for review?" with a summary of the proposed value).
- After successful creation: status changes from `draft_ready` to `pending_review`.
- Readiness panel updates: "Request Approval: Pending review" instead of "Disabled".
- Apply remains disabled.
- Runtime remains untouched.
- A status message explains: "Pending review — not applied".
- Duplicate request diagnostics if a pending request already exists.
- The lifecycle strip updates: "Approval: Pending review".

What must not change:

- Apply must remain disabled.
- Runtime must remain untouched.
- No approval/rejection UI is added.
- No apply workflow is enabled.
- No Platform registry read or write occurs.
- No Shell consumption is activated.

## 9. Security / Ownership

- Only authorized Studio users may request approval. Authorization follows the Visual Customizer entry contract, currently `platform_admin`.
- Request creation belongs to Customization Studio. The creation endpoint lives under Studio route ownership.
- Approval review may later belong to Studio governance or Platform governance, but **not** in this slice.
- Platform StyleRegistry remains untouched throughout the creation flow.
- The request artifact must record the authenticated requester identity server-side; client-supplied identity must not be trusted.
- No self-approval or implied approval authority is granted by request creation.

## 10. Non-Goals

This plan explicitly does **not** authorize:

- Apply workflow (any form of runtime style activation).
- Approval implementation (review decision UI, status transitions beyond creation).
- Platform StyleRegistry reads or writes.
- Shell runtime consumption of the request artifact.
- Runtime CSS generation or publishing.
- `public/assets` output or mutation.
- Multi-socket request (only `radius.scale`).
- Socket selection beyond `radius.scale`.
- Approval queue UI or management.
- Rollback or restore request.
- Core changes.
- ThemeTool changes.
- app/module CSS mutation.
- Notification or webhook on request creation.

## 11. Future Validation Plan

Any future implementation of request creation must prove:

- Request Approval button is disabled when no valid `radius.scale` draft exists.
- Request Approval button is disabled when proposed value is missing or invalid.
- Request Approval button is disabled when diff is empty.
- Request artifact is created only under `storage/studio/customization/visual-customizer/approval-requests/`.
- Request artifact contains exactly one selected socket: `radius.scale`.
- Request artifact status starts as `pending_review`.
- `validation_status` is `passed` before persistence.
- `current_value` remains `Not connected yet`.
- `non_runtime_flags` all remain true.
- Duplicate pending request for same user/draft/socket is prevented.
- No Platform StyleRegistry read or write.
- No Shell runtime consumption activated.
- Apply remains disabled.
- Core remains untouched.
- ThemeTool remains untouched.
- `public/assets` remains untouched.
- `git diff --check` passes.
- `bash scripts/architecture/run_architecture_gates.sh` passes.
- `bash scripts/system/check_deployment_readiness.sh` passes.
