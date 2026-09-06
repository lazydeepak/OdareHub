# Visual Customizer Apply Boundary Plan

Status: planning only. No Apply implementation in this slice.

This plan defines what happens when an approved, snapshot-bearing request transitions from `approved_for_future_apply` to a written Platform StyleRegistry entry. It does not authorize Apply UI, Shell runtime consumption, runtime CSS generation, public asset output, multi-socket apply, Core changes, or ThemeTool changes.

## Purpose

The Apply boundary is the **first execution step** in the customization chain. It exists to answer the question: "write the approved style value to the Platform source of truth."

Apply sits between approval/snapshot and Shell consumption:

```
radius.scale draft
  → validation
  → request artifact (pending_review)
  → approve (approved_for_future_apply)
  → snapshot (recovery point captured)
    → [APPLY BOUNDARY — this plan]
      → Platform StyleRegistry holds approved value
        → future: Shell consumption (separate phase)
```

## Separation of Concerns

Each phase has a distinct role:

| Phase | Role | Owner | Mutates runtime? |
|---|---|---|---|
| Approve | Governance permission | Studio | No |
| Snapshot | Recovery point | Studio | No |
| **Apply** | Write to Platform truth | Studio → Platform | **Yes** |
| Shell consume | Render from Platform truth | Shell | Yes |

Approve = governance permission. Snapshot = recovery point. Apply = write approved value to Platform registry. Shell consumption = later runtime phase.

None of these phases should be combined or bypassed.

## What Apply Means

Apply writes the `proposed_value` from an approved, snapshot-bearing request to the Platform StyleRegistry `ApprovedStyleRegistry` as the new active/default value for the target socket (`radius.scale`).

After Apply:
- The Platform StyleRegistry holds the new approved value for `radius.scale`
- The request artifact records `applied_at` timestamp
- The snapshot artifact records `applied_at` timestamp
- Shell does NOT consume the new value yet (separate phase)
- public/assets is NOT written yet (separate phase)
- Runtime CSS is NOT generated yet (separate phase)

## Preconditions

Apply MUST NOT proceed unless all of these are true:

| Precondition | Check | Who validates |
|---|---|---|
| Request status is `approved_for_future_apply` | Read request artifact | Studio Controller |
| Snapshot exists for this request | Read snapshot artifact | Studio Controller |
| Snapshot status is `snapshot_taken` (not already applied) | Read snapshot artifact | Studio Controller |
| Proposed value has not changed since approval | Compare snapshot.proposed_value with draft | Studio Controller |
| Socket is still valid (radius.scale exists in catalog) | Read shell socket catalog | Studio Controller |
| Current runtime value matches snapshot.previous_value | Read Platform StyleRegistry | Studio Controller |
| Platform StyleRegistry is writable | Check registry contract | Platform Service |

If ANY precondition fails, Apply must return an error and NOT proceed.

## Target: Platform StyleRegistry

Apply writes to `apps/Platform/StyleRegistry/Services/ApprovedStyleRegistry`. This class is currently empty. Apply implementation requires:

### Contract Definition (required before any Apply code)

The `ApprovedStyleRegistryContract` must be extended with:

```php
interface ApprovedStyleRegistryContract
{
    /** Read the current approved/active value for a socket. */
    public static function getValue(string $socketId): ?string;

    /** Write an approved value for a socket. Returns ok/error. */
    public static function setValue(string $socketId, string $value, array $provenance): array;

    /** Check whether a socket is registered and writable. */
    public static function isWritable(string $socketId): bool;
}
```

### Apply Calls `setValue`

The Studio Apply handler calls:

```php
ApprovedStyleRegistry::setValue(
    socketId: 'radius.scale',
    value: 'round', // the proposed_value from the approved request
    provenance: [
        'request_id' => 'vc-req-...',
        'snapshot_id' => 'vc-snap-...',
        'applied_by_user_id' => 9,
        'applied_at' => '2026-05-30T00:00:00Z',
    ]
);
```

### Storage Under `setValue`

The registry stores the approved value in a Platform-owned location. The location must:
- Be readable by Platform services only (not directly by Studio)
- Survive cache clears and deployments
- Support per-socket granularity
- Record provenance alongside the value

Recommended storage: `apps/Platform/StyleRegistry/Resources/approved-values/radius.scale.json`

Artifact shape:

```json
{
  "socket_id": "radius.scale",
  "approved_value": "round",
  "previous_value": "soft",
  "status": "applied",
  "applied_at": "2026-05-30T00:00:00Z",
  "applied_by_user_id": 9,
  "provenance": {
    "request_id": "vc-req-...",
    "snapshot_id": "vc-snap-..."
  }
}
```

### Post-Apply Artifact Updates

After successful `setValue`, Studio updates:

1. **Snapshot artifact** — set `applied_at` to the timestamp:

```json
{
  "snapshot_id": "vc-snap-...",
  "status": "applied",
  "applied_at": "2026-05-30T00:00:00Z"
}
```

2. **Request artifact** — set `applied_at` on request:

```json
{
  "request_id": "vc-req-...",
  "status": "approved_for_future_apply",
  "applied_at": "2026-05-30T00:00:00Z"
}
```

## What Apply Does NOT Include

This boundary does NOT include:

- Shell runtime consumption (Shell reads Platform registry separately)
- Runtime CSS generation (CSS generation is a Shell concern)
- public/assets output (asset publishing is a separate lifecycle)
- Multi-socket apply (radius.scale only)
- Non-radius socket editing (no new editable sockets)
- Core changes (Core remains locked)
- ThemeTool changes (ThemeTool is unrelated)
- App/module CSS mutation (module CSS is owned by each module)
- Notification or webhook on apply
- Apply dashboard or queue management
- Bulk/rollback apply

## What Apply Updates

| Artifact | Field | Before Apply | After Apply |
|---|---|---|---|
| Platform registry | `approved_value` | `soft` (default) | `round` (approved) |
| Platform registry | `status` | `not_applied` | `applied` |
| Snapshot artifact | `applied_at` | `null` | `2026-05-30T00:00:00Z` |
| Snapshot artifact | `status` | `snapshot_taken` | `applied` |
| Request artifact | `applied_at` | not set | `2026-05-30T00:00:00Z` |
| Shell socket catalog | no change | — | — |
| public/assets | no change | — | — |
| Core | no change | — | — |

## Rollback Contract (read-only plan)

Apply must be reversible. The snapshot artifact is the recovery contract:

```text
Rollback = write snapshot.previous_value back to Platform StyleRegistry
         → update snapshot with rollback_at
         → update request with rollback_at
```

Rollback is NOT included in this boundary. It is documented here to ensure the snapshot captures the data needed for future rollback.

The snapshot artifact already stores `previous_value` (the `default_value` from before Apply). This is the value that rollback would restore.

## Sequence Diagram

```
Studio Controller                  Platform StyleRegistry        Storage
     │                                    │                        │
     │  verify preconditions              │                        │
     │  (approved + snapshot exists)      │                        │
     │                                    │                        │
     │ ── isWritable('radius.scale') ────>│                        │
     │ <── true                            │                        │
     │                                    │                        │
     │  setValue(                          │                        │
     │    socketId: 'radius.scale',        │                        │
     │    value: 'round',                  │                        │
     │    provenance: {...}                │                        │
     │  ) ───────────────────────────────>│                        │
     │                                    │ ── write radius.scale.json ──>│
     │ <── {ok: true}                      │                        │
     │                                    │                        │
     │  update snapshot.applied_at ───────│────────────────────────>│
     │  update request.applied_at ───────│────────────────────────>│
     │                                    │                        │
```

## Risk Assessment

| Risk | Impact | Mitigation |
|---|---|---|
| Registry write fails mid-apply | Stale snapshot, no apply | Pre-check isWritable before setValue; atomic write |
| Applied value breaks Shell (future) | Broken UI | Snapshot enables rollback; Shell consumption is gated separately |
| Race condition (two applies for same socket) | Last-write-wins | Precondition checks snapshot status; duplicate apply blocked |
| Registry data loss | Lost approved values | Registry storage should be in version-controlled or backed-up path |
| Apply bypasses snapshot requirement | No rollback possible | Snapshot precondition is server-enforced |

## Validation Plan

Any future implementation of this plan must prove:

- Apply is blocked when request is not `approved_for_future_apply`
- Apply is blocked when no snapshot exists for the request
- Apply is blocked when snapshot status is already `applied`
- Apply is blocked when Platform StyleRegistry `isWritable` returns false
- Apply writes the correct `proposed_value` to the registry
- Apply updates snapshot with `applied_at` and status `applied`
- Apply updates request with `applied_at`
- Apply does NOT write to Shell, public/assets, Core, ThemeTool
- Apply does NOT enable Shell consumption
- Apply does NOT generate runtime CSS
- Apply records provenance (request_id, snapshot_id, applied_by, applied_at)
- Architecture gates pass (registry boundaries, confinement)
- Rollback is not required yet but data is sufficient for future rollback
- All six `non_runtime_flags` remain `false` on the request artifact

## What Remains Disabled After Apply

After Apply succeeds:

- Shell runtime consumption remains disabled
- Runtime CSS generation remains disabled
- public/assets output remains disabled
- Multi-socket apply remains disabled
- Rollback remains disabled (snapshot has the data, but no rollback UI or action)
- ThemeTool remains unrelated
- Core remains untouched

## Acceptance Criteria For Future Implementation

- An approved, snapshot-bearing request can be applied via a server-side action.
- Apply writes `proposed_value` to Platform StyleRegistry for `radius.scale`.
- Snapshot `applied_at` is set and status changes to `applied`.
- Request `applied_at` is set.
- Applying a non-approved request is blocked.
- Applying a request without a snapshot is blocked.
- Applying a request whose snapshot is already `applied` is blocked.
- The apply action is server-side CSRF-guarded.
- No Shell, public/assets, Core, ThemeTool, or CSS generation occurs.
- Architecture gates pass.
- Deployment readiness passes.
