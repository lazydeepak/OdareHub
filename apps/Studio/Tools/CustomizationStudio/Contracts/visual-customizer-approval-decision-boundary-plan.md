# Visual Customizer Approval Decision Boundary Plan

Status: planning only. No approval decision implementation in this slice.

This plan defines what happens when an approval request transitions from `pending_review` to a decision state (approve, reject, cancel). It does not authorize decision UI, Apply, Platform StyleRegistry reads or writes, Shell runtime consumption, runtime CSS generation, public asset output, Core changes, ThemeTool changes, or additional editable sockets.

## Purpose

The approval decision boundary is the **first post-request governance step**. It exists to answer the question: "should this proposed change be approved for future application?"

It is still not Apply.

The decision boundary sits between request creation and Apply:

```
radius.scale draft
  → validation
  → request artifact (pending_review)
    → [DECISION BOUNDARY — this plan]
      → approved_for_future_apply
        → future: Apply (separate phase)
```

## Who Can Approve / Reject / Cancel

Approval decisions require a **reviewer** who is independent from the **requester**.

### Candidate Roles

Future implementation should support at least these reviewer types:

| Role | Can approve | Can reject | Can cancel |
|---|---|---|---|
| Requester (self) | No | No | Yes |
| platform_admin (different user) | Yes | Yes | No |
| Studio governance delegate | Yes | Yes | No |
| App/module owner delegate | Yes | Yes | No |

Rules:
- Self-approval is disallowed. The requester cannot make the decision.
- The requester can cancel their own request at any time before apply.
- A cancel by the requester does not require reviewer authority.
- A reviewer with platform_admin authority can approve or reject any pending request.
- Future role delegation (e.g., "Studio governance delegate") is documented but not required for the first implementation.
- Review authority must be verified server-side on every decision action. Client-side role checks are not sufficient.
- The request artifact records both requester identity and reviewer identity as separate fields.

### Authority Assignment For First Implementation

The first implementation should use a simple rule:

- Reviewer authority == platform_admin AND reviewer user_id != requester user_id.
- Requester authority to cancel: always, for own pending requests only.

This avoids introducing new role types before the governance model stabilizes.

## Request Statuses

The approval lifecycle now extends the status set:

| Status | Meaning | Who sets it | Next valid transitions |
|---|---|---|---|
| `pending_review` | Request submitted; awaiting decision | System (on creation) | `approved_for_future_apply`, `review_rejected`, `review_cancelled` |
| `approved_for_future_apply` | Reviewer approved the proposed change; apply remains separate | Reviewer | `review_cancelled` (if apply not started) |
| `review_rejected` | Reviewer determined the request should not proceed | Reviewer | (terminal; new request may be created) |
| `review_cancelled` | Requester (or governance) cancelled before decision | Requester or governance | (terminal; new request may be created) |

### `pending_review` Semantics

- The request package is immutable except for the status field.
- The proposed value, diff, validation evidence, and non-runtime flags are frozen at creation time.
- No edits to the draft are reflected in the pending request.
- The Visual Customizer should indicate: "Pending review — not applied".
- The draft may still be editable for further iteration (creating a new request updates or cancels the old one).

### `approved_for_future_apply` Semantics

- The reviewer agrees the proposed change is acceptable.
- **The change is still not applied.** This status means "ready for Apply" but Apply is a separate, higher-risk phase that is not enabled by this boundary.
- Runtime, Platform registry, Shell, public/assets, Core, and ThemeTool remain untouched.
- The Visual Customizer should indicate: "Approved — not yet applied".
- Apply remains disabled.

### `review_rejected` Semantics

- The reviewer determined the request should not proceed.
- Reasons for rejection should be recorded (free-text field in the artifact).
- The requester may create a new request with a different proposed value.
- The Visual Customizer should indicate: "Review rejected — see reason".
- The draft may still be edited for a new request.

### `review_cancelled` Semantics

- The requester withdrew the request before a decision was made.
- A reviewer must not cancel a request they did not create.
- Governance/delegate may cancel on behalf of the requester (e.g., stale requests).
- The Visual Customizer should indicate: "Cancelled — no longer pending".
- The draft may still be edited for a new request.

## What Approve Means

`approved_for_future_apply` means:

- The reviewer has examined the proposed value, diff summary, and validation evidence.
- The reviewer has determined the change is acceptable for the target socket (`radius.scale`).
- The request package is now in the "approved" queue, awaiting a future Apply workflow.
- Platform StyleRegistry, Shell runtime, public/assets, Core, and ThemeTool remain untouched.
- Apply remains disabled.

Approve does NOT mean:
- The change is active on any surface.
- The change is visible to end users.
- The Platform registry has been written.
- Shell has consumed the value.
- Runtime CSS has been generated.
- The change is irrevocable (future apply/rollback may still undo it).

Approve moves the request from "undecided review queue" to "ready for apply queue". That is the only effect.

## What Reject Means

`review_rejected` means:

- The reviewer has examined the request and determined it should not proceed.
- The proposed value is not acceptable in its current form.
- The requester may create a new request with a revised proposed value.
- The rejection reason is recorded for audit and requester visibility.

Reject does NOT mean:
- The draft is deleted. The requester may still edit and submit a new request.
- The socket is locked. A different request for the same socket may be approved.
- The reviewer is banned from future approvals. Each request is evaluated independently.

## Why Approval Decision Is Still Not Apply

The separation between approval decision and Apply is intentional and governed:

| Concern | Approval decision boundary | Apply boundary (future) |
|---|---|---|
| Mutates runtime? | No | Yes |
| Writes Platform registry? | No | Yes (planned) |
| Activates Shell consumption? | No | Yes (planned) |
| Generates runtime CSS? | No | Yes (planned) |
| Writes public/assets? | No | Yes (planned) |
| Requires rollback plan? | No | Yes |
| Requires snapshot? | No | Yes |
| Requires validation gates? | Yes (re-check before decision) | Yes (fresh check before apply) |
| Changes what end users see? | No | Yes |
| Risk level | Low (documentation/governance) | Medium-to-high (runtime mutation) |

The approval decision is a **governance checkpoint**, not a deployment action. The Apply workflow is the deployment action and requires its own separate planning, implementation, and risk assessment.

Keeping Apply disabled after approval decision proves:
- The system is not accidentally deploying unapproved changes.
- The Apply workflow can be independently validated, tested, and rolled back.
- The separation of concerns between governance (approve) and operations (apply) is preserved.

## Why Platform Registry Remains Untouched

Platform/System owns the approved style registry truth.

The approval decision boundary:
- Does not read Platform StyleRegistry. The request package was created from Studio-local draft data and Shell socket catalog metadata (which is catalog_only_not_consumed).
- Does not write Platform StyleRegistry. The approved state is recorded in the Studio-owned approval request artifact only.
- Does not change what Shell considers active/default style truth.

The Platform registry must remain a **separate, deliberate target** for the future Apply workflow. Writing to it during the approval decision phase would:
- Make Studio artifacts into runtime truth prematurely.
- Bypass the Apply governance that requires snapshot, rollback, and validation evidence.
- Couple the approval workflow to Platform infrastructure that is not yet ready for runtime consumption.

## Why Shell / Runtime Remains Untouched

Shell runtime consumption is the **final activation step** in the style customization chain:

```
Studio draft → Request artifact → Approval decision → Apply → Platform registry → Shell consumption
```

The approval decision is at step 3. Shell consumption is at step 6. Steps 4–6 are not implemented yet.

Leaving Shell/runtime untouched during the decision phase:
- Prevents accidental style activation before Apply is governance-ready.
- Keeps the approval queue a Studio-owned governance artifact, not a runtime input.
- Ensures end users never see unapplied approved values.
- Preserves the architectural boundary that Studio is a worker/tool, not runtime source of truth.

## Decision Artifact Update

When a decision is made, the existing request artifact is updated (not replaced):

```json
{
  "request_id": "vc-req-...",
  "status": "approved_for_future_apply",
  "status_updated_at": "2026-05-30T00:00:00Z",
  "decision": {
    "reviewer_user_id": 2,
    "reviewer_email": "reviewer@example.com",
    "reviewer_handle": "reviewer",
    "decision": "approved",
    "reason": "",
    "decided_at": "2026-05-30T00:00:00Z"
  },
  "previous_status": "pending_review",
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

On rejection:

```json
{
  "status": "review_rejected",
  "decision": {
    "decision": "rejected",
    "reason": "Proposed value round does not match brand guidelines for corner radius. Please revise to sharp.",
    ...
  }
}
```

On cancellation:

```json
{
  "status": "review_cancelled",
  "decision": {
    "cancelled_by": "requester or governance",
    "reason": "Requester withdrew for revised draft.",
    ...
  }
}
```

The artifact fields:
- `status` — updated to the new decision status.
- `status_updated_at` — timestamp of the decision.
- `decision` — object containing reviewer identity, decision type, reason.
- `previous_status` — preserves the status before the decision for audit trail.
- `non_runtime_flags` — all remain `true`. No runtime boundary is crossed.

The artifact remains in Studio-owned storage (`storage/studio/customization/visual-customizer/approval-requests/`). It is still not Platform registry truth.

## What Remains Disabled

This boundary does not enable:

- Apply
- Platform StyleRegistry reads or writes
- Shell runtime draft consumption
- Runtime CSS generation
- public/assets generation or mutation
- Multi-socket approval
- Non-radius socket editing
- Core changes
- ThemeTool changes
- App/module CSS mutation
- Notification or webhook on decision
- Approval dashboard or queue management

## Future Validation Plan

Any future implementation of this plan must prove:

- Only authorized reviewers (platform_admin, not requester) can approve or reject.
- Requester can cancel own pending requests.
- Self-approval is blocked server-side.
- Decision transitions follow the valid status map (no `pending_review` → `review_rejected` → `approved_for_future_apply`).
- Decision updates the request artifact without changing non_runtime_flags.
- Approve does not enable Apply.
- Approve does not write Platform registry.
- Approve does not change Shell or runtime behavior.
- Reject does not delete the draft.
- Cancel does not require reviewer authority.
- Rejection reason is recorded and surfaces in the detail view.
- All six `non_runtime_flags` remain `true` after any decision.
- Platform StyleRegistry is not read or written.
- Shell does not consume the artifact.
- Core remains untouched.
- ThemeTool remains untouched.
- public/assets remains untouched.
- Architecture gates pass.
- Deployment readiness passes.

## Acceptance Criteria For Future Implementation

- A reviewer can approve a `pending_review` request and see `approved_for_future_apply` status.
- A reviewer can reject a `pending_review` request with a recorded reason.
- A requester can cancel their own `pending_review` request.
- A reviewer cannot cancel someone else's request.
- A requester cannot approve or reject their own request.
- After approval, Apply remains disabled and status reads "Approved — not yet applied".
- After rejection, the reason is visible in the request detail view.
- After cancellation, the status reads "Cancelled — no longer pending".
- The non_runtime_flags remain unchanged across all transitions.
- No runtime boundary is crossed.
