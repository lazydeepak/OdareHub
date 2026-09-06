# Visual Customizer Recheck Readiness Flow Plan

Status: planning only. No request creation, approval, apply, registry I/O, Shell consumption, runtime CSS generation, Core changes, ThemeTool changes, or public asset output in this slice.

This plan defines a future recheck flow that allows the Visual Customizer readiness panel to re-run server-side validation on the current persisted Studio draft without a full page refresh. It solves the staleness problem identified in the readiness panel: server validation currently runs only at page-load, so draft changes made client-side are not reflected until refresh.

## 1. Problem

The readiness panel renders `validationResult` baked into the PHP payload at page load. When the user edits a draft (propose Sharp, discard, reset), the JS updates `localDraftState` and persists to server via `persistDraftAction`, but `validationResult` is never reassigned. The panel stays frozen at page-load state.

Until a recheck mechanism exists, a Request Approval button would be risky because the UI can show stale eligibility.

## 2. Purpose

- Allow the readiness panel to re-run server-side validation on the current persisted Studio draft.
- Return validation result only — no request artifact creation, no approval, no apply.
- The recheck endpoint or action is a read-only validation gate, not a mutation.

## 3. Future Recheck Flow

### Option A: Dedicated Endpoint

```
POST /apps/studio/tools/customization-studio/visual-customizer/readiness/recheck
CSRF-protected
Input: user session only (reads persisted draft from storage)
Output: VisualCustomizerRequestValidationService::validate() result
No request artifact creation
No approval
No apply
No registry/Shell/public assets writes
```

### Option B: Recheck Button in JS

```
Button: "Recheck readiness" in the readiness panel
On click:
  - Call existing draft update endpoint with action=recheck or new lightweight endpoint
  - Server reads persisted draft, runs validation, returns result
  - JS replaces validationResult with new result
  - Readiness panel re-renders from fresh data
```

### Option C: Implicit Recheck After Persist

```
After persistDraftAction completes (fetch returns ok):
  - Follow up with a readiness recheck call
  - Update validationResult with server response
  - Readiness panel auto-updates
```

## 4. Server-Side Behavior

The recheck must:

1. Read the current persisted draft from Studio-local storage (same as `VisualCustomizerDraftStorageService::readDraftForUser()`).
2. Read radius.scale socket config from metadata.
3. Call `VisualCustomizerRequestValidationService::validate()` with the current draft and socket config.
4. Return the validation result as JSON.
5. Perform no writes, no request creation, no approval, no apply.
6. Touch no registry, Shell runtime, Core, public/assets, or ThemeTool.

The existing `VisualCustomizerRequestValidationService` already implements the validation logic. The recheck flow only needs a way to invoke it on demand and return the result to the client.

## 5. Response Shape

Same as the existing validation service output:

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

## 6. Client-Side Behavior

After receiving a fresh validation result:

- JS replaces `validationResult` variable with the new result.
- JS calls `updateReadinessPanel()` to re-render all check items and overall status.
- If result changes from not_eligible to eligible, the status text updates from "Not eligible" to "Eligible" with the green CSS class.
- The staleness note in the readiness hint may update or disappear once a recheck has been performed.

## 7. Non-Goals

This plan explicitly does not authorize:

- Request Approval button enablement.
- Request artifact creation.
- Approval workflow.
- Apply workflow (any form of runtime style activation).
- Platform StyleRegistry reads or writes.
- Shell runtime consumption.
- Runtime CSS generation or publishing.
- `public/assets` output or mutation.
- Multi-socket recheck (only `radius.scale`).
- Socket selection beyond `radius.scale`.
- Approval queue UI or management.
- Core changes.
- ThemeTool changes.
- App/module CSS mutation.
- Notification or webhook.
- Automatic polling or websocket-based recheck.

## 8. Required Before Request Approval Enablement

The following must be true before a Request Approval button may become enabled:

1. [ ] Recheck endpoint or action exists and returns fresh validation result.
2. [ ] Readiness panel reflects current server state (not stale page-load snapshot).
3. [ ] User can trigger recheck and see updated eligibility.
4. [ ] All architecture gates pass.
5. [ ] Deployment readiness passes.

Until recheck is implemented, the Request Approval button must remain disabled and the staleness note in the readiness hint must remain visible.

## 9. Validation Evidence for Future Implementation

Future implementation must prove:

- Recheck returns correct validation result after draft change (propose → recheck → eligible).
- Recheck returns correct validation result after discard (discard → recheck → not eligible).
- Recheck performs no writes (check storage, registry, public/assets are untouched).
- No request artifact is created.
- No approval or apply behavior.
- Architecture gates pass.
- Deployment readiness passes.
