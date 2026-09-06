# Visual Customizer First Apply Implementation Review

Status: Implementation review. No Apply code in this slice.

This document reviews and finalizes the exact implementation boundary for the first Visual Customizer Apply before any code is written. It builds on:

- `visual-customizer-apply-boundary-plan.md` — high-level Apply architecture
- `apps/Platform/StyleRegistry/Contracts/ApprovedStyleRegistryContract.php` — `getValue()`/`setValue()`/`isWritable()` contract
- `apps/Platform/StyleRegistry/Services/ApprovedStyleRegistry.php` — implementation supporting `radius.scale` only
- `scripts/platform/probe_approved_style_registry_contract.php` — 37-passing probe validating the registry

The registry contract is implemented and tested. Apply calls it but does not yet exist.

## 1. First Apply Scope

Apply is **one action**: write the approved `proposed_value` to `ApprovedStyleRegistry::setValue()` for `radius.scale` only.

### Hard Preconditions (ALL must be true)

| # | Precondition | Source | Server-side check |
|---|---|---|---|
| 1 | Request status is `approved_for_future_apply` | Request artifact | `$request['status'] === 'approved_for_future_apply'` |
| 2 | Snapshot exists for the request | Snapshot artifact | `VisualCustomizerSnapshotService::getSnapshotByRequestId($requestId) !== null` |
| 3 | Snapshot status is `snapshot_taken` (not already `applied`) | Snapshot artifact | `$snapshot['status'] === 'snapshot_taken'` |
| 4 | Proposed value is `sharp`, `soft`, or `round` | Request artifact | `in_array($proposedValue, ['sharp', 'soft', 'round'], true)` |
| 5 | Socket `radius.scale` is writable | Platform StyleRegistry | `(new ApprovedStyleRegistry)->isWritable('radius.scale') === true` |

If ANY precondition fails, Apply MUST return an error and NOT call `setValue()`.

### Soft Precondition (logged, not blocking)

| # | Precondition | Source | Behavior |
|---|---|---|---|
| 6 | Snapshot `previous_value` matches current registry `getValue()` | Platform StyleRegistry | Log mismatch warning, proceed with Apply |

Precondition 6 is advisory. The snapshot is the authoritative before-state. A mismatch means something else wrote to the registry between snapshot and apply — log it but do not block.

## 2. Apply Operation

### Sequence (exact)

```
1. Validate preconditions 1-5. If any fail → return 422 error.
2. Read snapshot artifact for provenance (snapshot_id, previous_value).
3. Read request artifact for proposed_value, request_id.
4. Call ApprovedStyleRegistry::setValue(
     socketId: 'radius.scale',
     value: $proposedValue,
     context: [
       'request_id' => $requestId,
       'snapshot_id' => $snapshotId,
       'applied_by_user_id' => $currentUserId,
     ]
   )
5. If setValue returns ok → update snapshot + request artifacts.
6. If setValue returns error → return 422; do NOT update artifacts.
```

### Post-Apply Artifact Updates

**Snapshot artifact** — set `applied_at` and status:

```json
{
  "snapshot_id": "vc-snap-...",
  "status": "applied",
  "applied_at": "2026-05-30T00:00:00Z"
}
```

**Request artifact** — set `applied_at`:

```json
{
  "request_id": "vc-req-...",
  "status": "approved_for_future_apply",
  "applied_at": "2026-05-30T00:00:00Z"
}
```

The request `status` stays `approved_for_future_apply`. Apply does not change governance status. The `applied_at` field is the record that Apply executed.

### Error Handling

| Failure mode | HTTP code | Error key | Artifact state |
|---|---|---|---|
| Request not `approved_for_future_apply` | 422 | `invalid_status` | Unchanged |
| Snapshot not found | 422 | `snapshot_not_found` | Unchanged |
| Snapshot already applied | 422 | `snapshot_already_applied` | Unchanged |
| Proposed value invalid | 422 | `invalid_value` | Unchanged |
| Socket not writable | 422 | `socket_not_writable` | Unchanged |
| Registry write fails | 422 | `registry_write_failed` | Unchanged |
| Snapshot/request artifact write fails after successful registry write | 500 | `artifact_update_failed` | Registry written but snapshot/request not updated |

The last failure mode is the worst case. Mitigation: write snapshot before request, log the partial state. A future reconciliation tool could detect `applied_at` mismatch between registry and snapshot.

## 3. What Apply Is NOT

| Concern | Apply does this? | Owned by |
|---|---|---|
| Shell runtime consumption | No | Shell (future) |
| Runtime CSS generation | No | Shell (future) |
| public/assets output | No | Publishing lifecycle |
| Core change | No | Core (locked) |
| ThemeTool change | No | ThemeTool (separate) |
| Multi-socket apply | No | radius.scale only |
| Direct CSS mutation | No | Module/app owner |
| Notification or webhook | No | Future |
| Apply dashboard or queue | No | Future |
| Bulk/rollback apply | No | Future |

## 4. Recovery Model

### Pre-Apply Recovery

If any precondition fails before `setValue()`, nothing has changed. The request and snapshot are untouched. The caller can fix the issue and retry.

### Mid-Apply Recovery

If `setValue()` succeeds but the snapshot/request artifact update fails, the registry has been written but the Studio artifacts are inconsistent. This is detected by:

- Snapshot status still `snapshot_taken` (should be `applied`)
- Request has no `applied_at` (should be set)

Recovery: re-run the Apply handler for the same request. It will:
1. Detect existing registry write (check `getValue('radius.scale')` against `proposed_value`)
2. If match found → skip registry write, retry artifact updates
3. If no match → proceed with registry write normally

This makes Apply **idempotent** — the second call detects the partial state and completes it.

### Post-Apply Recovery

Rollback remains future. The snapshot stores `previous_value` which is the value to restore. Rollback is explicitly NOT part of this slice.

### Registry Write Failure

If `setValue()` returns `ok: false`, the request and snapshot MUST remain untouched. The caller sees the error, fixes the underlying issue, and retries.

## 5. UI Rules

### Apply Button Visibility

The Apply button appears only when ALL are true:
- Request status is `approved_for_future_apply`
- Snapshot exists with status `snapshot_taken`
- Current user is the requester (same rule as snapshot)

Server-side preconditions are rechecked on button click.

### Apply Button State

| State | Condition | Button |
|---|---|---|
| Not applicable | Not approved, no snapshot | Hidden |
| Ready | `approved_for_future_apply` + `snapshot_taken` + requester | Enabled |
| Processing | Clicked, not yet complete | Disabled, "Applying..." |
| Applied | Post-apply (applied_at set) | Removed, status shown |
| Error | Apply failed | Re-enabled, error message shown |

### Post-Apply Detail View

After successful Apply, the request detail page shows:

```
Status: Approved for future apply ✓ Applied

Platform registry: Updated (radius.scale = round)
Runtime: Not consuming
Shell: Not consuming
public/assets: Untouched
Snapshot: Applied (provenance recorded)
Rollback: Not available (future)
```

The "Registry updated" indicator is the primary signal that Apply succeeded. All other indicators remain "Not consuming/Untouched/Not available".

## 6. Validation Plan

### Automated Validation (must pass)

| # | Test | Expected |
|---|---|---|
| 1 | Apply valid approved request with snapshot | Writes radius.scale to Platform registry |
| 2 | Apply non-approved request | Blocked (422) |
| 3 | Apply without snapshot | Blocked (422) |
| 4 | Apply with already-applied snapshot | Blocked (422) |
| 5 | Apply with invalid proposed value | Blocked (422) |
| 6 | Apply duplicate (same request twice) | Blocked (snapshot already applied) |
| 7 | Apply when socket not writable | Blocked (422) |
| 8 | Post-apply snapshot status | `applied` |
| 9 | Post-apply request `applied_at` | Set |
| 10 | Post-apply registry `getValue()` | Matches `proposed_value` |
| 11 | Shell files unchanged | `git diff -- apps/Shell` empty |
| 12 | public/assets unchanged | `git diff -- public/assets` empty |
| 13 | Core unchanged | `git diff -- app` empty |
| 14 | ThemeTool unchanged | `git diff -- apps/Studio/Tools/CustomizationStudio/Diagnose/ThemeDoctor` empty |
| 15 | Architecture gates pass | `run_architecture_gates.sh` PASS |
| 16 | Deployment readiness passes | `check_deployment_readiness.sh` PASS |

### Manual Validation

- Apply button visible only for approved + snapshot + requester
- Apply button hidden for non-requester
- Apply button hidden after successful apply
- Error message shown on precondition failure
- Page reload after apply shows updated status

### Existing Probe Coverage

The probe at `scripts/platform/probe_approved_style_registry_contract.php` already covers registry-level validation (37 assertions). Apply testing must not duplicate those tests — it must focus on the Studio-to-Platform bridge.

## 7. Implementation Files (future)

The future Apply implementation will touch these files:

| File | Change |
|---|---|
| `Services/VisualCustomizerApprovalRequestService.php` | Add `applyRequest()` method |
| `Controllers/VisualCustomizerController.php` | Add `handleApplyRequest()`, update `requestDetailModel()` |
| `Controllers/StudioController.php` | Add `visualCustomizerApplyRequest()` guard |
| `routes.php` | Add POST route for `/request/apply` |
| `visual-customizer-request-detail.php` | Add Apply button + post-apply status indicators |
| `visual-customizer.css` | Apply button styles + status indicators |
| `scripts/platform/probe_approved_style_registry_contract.php` | No change (probe is registry-level only) |

No Shell, public/assets, Core, ThemeTool, or non-radius files will be touched.

## 8. Commit Boundary

### Allowed in Apply implementation commit

- Files listed in section 7 above
- New `handleApplyRequest()` handler
- New `applyRequest()` service method
- Apply button in detail view
- Post-apply status indicators
- CSRF-guarded POST route for apply
- Architecture gate updates if needed
- Probe/validation updates for Apply-specific tests

### NOT allowed in Apply implementation commit

- Changes to Platform StyleRegistry files (already committed)
- Changes to Shell Style files
- Changes to public/assets
- Changes to Core
- Changes to ThemeTool
- Multi-socket support
- Shell consumption logic
- Rollback implementation
- Runtime CSS generation

### Suggested Commit Title

```
feat(studio): apply approved radius scale to platform registry
```

## 9. Pre-Implementation Checklist

Before writing Apply code, confirm:

- [ ] `ApprovedStyleRegistryContract` is committed with `getValue()`/`setValue()`/`isWritable()`
- [ ] `ApprovedStyleRegistry` is committed with allowlist validation (radius.scale only)
- [ ] Registry probe passes (37/37)
- [ ] Architecture gates pass
- [ ] Deployment readiness passes
- [ ] This review document is committed

All six items are complete. Apply implementation can proceed when authorized.
