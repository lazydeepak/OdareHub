# ResolvedStyleConsumer Registry Reader Injection Plan

## Part A — Injection Goal

**Goal:** `ResolvedStyleConsumer` may receive `ApprovedStyleReaderContract` as a dependency while remaining a read-only, diagnostic-only placeholder with `runtime_consumption_enabled === false`.

### Must preserve

| Invariant | Enforced by |
|---|---|
| read-only | No `setValue`, `isWritable`, or file writes |
| diagnostic-only | No public resolved-value method; reader data emitted only in `diagnostics()` |
| `runtime_consumption_enabled = false` | Property stays `false`; no code path returns `true` |
| non-mutating | No registry/Shell/CSS/theme writes |
| not Shell-connected | No `use Apps\Shell`, no Shell import paths |

### Must not become

A runtime style resolver. The consumer is a diagnostic bridge only. Runtime consumption — where `readValue()` output changes CSS, attributes, or rendered markup — is a separate gate that must be explicitly enabled later with its own contract, gate, and audit.

---

## Part B — Constructor Injection Design

### Recommendation: nullable constructor injection with diagnostic-only guard

```php
private ?ApprovedStyleReaderContract $reader = null;

public function __construct(?ApprovedStyleReaderContract $reader = null)
{
    $this->reader = $reader;
    $this->runtimeConsumptionEnabled = false;
}
```

### Rationale

| Approach | Assessment |
|---|---|
| **Nullable injection (recommended)** | Registry may not exist in all deployments. Nullable allows graceful degradation. The consumer still produces valid diagnostics — just reports reader as unavailable. |
| Required reader | Forces registry to exist. Unacceptable for environments where Platform registry is not installed or enabled. |
| Factory method | Extra indirection with no benefit. The adapter already lazy-loads registry internally. |
| Diagnostic-only reader mode | Over-engineered. Nullable injection + `!$reader` check in diagnostics is simpler and equally safe. |

### Key constraint

`ResolvedStyleConsumer` must not call `new ApprovedStyleReaderAdapter()` itself. The adapter must be injected by the caller (Shell route handler, probe script, future CLI tool). This keeps the consumer decoupled from adapter instantiation and registry autoloading.

---

## Part C — Allowed Reader Use

### Phase 1 allowed (diagnostics only)

| Call | Purpose | Frequency |
|---|---|---|
| `$this->reader?->isReachable()` | Report registry storage availability | Once per diagnostics call |
| `$this->reader?->readValue('radius.scale')` | Report approved value for the sole Phase 1 socket | Once per diagnostics call |

### Explicitly forbidden

| Pattern | Why |
|---|---|
| Batch reads / catalog iteration | No multi-socket read loop. Phase 1 is single-socket. |
| `readValue()` output outside diagnostics | No method that returns `readValue()` to callers. Must stay inside diagnostics array. |
| CSS generation from read values | Would be runtime consumption. Needs separate gate. |
| Style application / attribute emission | Would be runtime consumption. Needs separate gate. |
| Shell mutation or rendering | Shell must not receive registry values at runtime yet. |

### Binding rule

`readValue()` may only be called inside the `diagnostics()` method body. No public or private helper may expose the raw result to external code.

---

## Part D — Diagnostics Changes

### New diagnostic codes

| Code | Severity | Condition |
|---|---|---|
| `RSC-C001` | INFO | Reader is reachable (`isReachable() === true`) |
| `RSC-C002` | INFO | `radius.scale` approved value found (`readValue !== null`) |
| `RSC-C003` | WARN | `radius.scale` approved value absent (`readValue === null`) |
| `RSC-C004` | WARN | Reader unavailable (`reader is null or isReachable() === false`) |
| `RSC-C005` | INFO | Reader contract version matches consumer contract version |

### Diagnostics emission pattern

```php
'diagnostics' => [
    // existing placeholder codes stay
    ['code' => 'RSC-PLACEHOLDER-001', 'severity' => 'INFO', 'message' => '...'],
    // reader diagnostics appended when reader is present
]
```

When reader is null: emit only `RSC-C004`, skip `RSC-C001/002/003/005`.

When reader is reachable but socket absent: emit `RSC-C001 + RSC-C003`.

When reader is reachable and socket present: emit `RSC-C001 + RSC-C002 + RSC-C005`.

### Runtime consumption flag

`runtime_consumption_enabled` remains `false` in diagnostics output. The reader injection does not change this value. The flag is the explicit gate for future consumption activation — it must not be coupled to reader existence.

---

## Part E — API Surface Rules

### Decision: No public resolved-value method yet

```php
// DO NOT ADD:
public function approvedValuePreview(string $socketKey): ?string
```

### Justification

1. **Any public value-returning method is a consumption vector.** Even named "preview", a public method invites callers to check it and conditionally change behavior. That is runtime consumption by proxy.
2. **Diagnostics-only keeps the consumer honest.** All reader data lives inside the `diagnostics()` array. The only way to observe it is through the diagnostic snapshot — which is already the intended contract.
3. **Adding the method later is non-breaking.** Adding a public method to a final class at a later phase is backward-compatible. Removing it (if we add it now and regret it later) is not.
4. **Diagnostics are sufficient for Phase 1.** Probe scripts can parse diagnostics JSON to validate that `radius.scale` is approved.

---

## Part F — Boundary Gate Impact

### Current gate behavior (already implemented)

| File | Allowed | Blocked |
|---|---|---|
| `ResolvedStyleConsumer.php` | `use Platform\Style\Contracts` ✅ | `use Apps\Platform\StyleRegistry` ❌ |
| `ResolvedStyleConsumer.php` | `readValue(` ✅ (not yet allowed — gate uses `getValue` check, not `readValue`) | `getValue(` ❌ |
| `ResolvedStyleConsumer.php` | — | `storage/platform/style-registry` ❌ |
| `ResolvedStyleConsumer.php` | — | `setValue(`, `isWritable(` ❌ |

### Gate change required

| Check | Current | Needs |
|---|---|---|
| Consumer calling `readValue` | Not explicitly checked (gate only blocks `getValue`). | Must explicitly allow `readValue` in consumer while still blocking `getValue`. **No change needed** — the existing pattern `getValue\s*\(` already distinguishes. |
| Consumer importing reader contract | Not explicitly allowed. The pattern `'use Apps\\Platform\\StyleRegistry'` blocks StyleRegistry, but consumer now needs `use Platform\\Style\\Contracts\\ApprovedStyleReaderContract`. | Add explicit allow rule: consumer may `use Platform\\Style\\Contracts\\ApprovedStyleReaderContract`. The current gate should already pass because it blocks `Apps\\Platform\\StyleRegistry`, not `Platform\\Style\\Contracts`. |
| Consumer calling `isReachable` | Not checked. | Add explicit allow: consumer may call `isReachable`. No gate change needed — `isReachable` is not in any block list. |

### Gate must still block in consumer

| Pattern | Block? |
|---|---|
| `use Apps\\Platform\\StyleRegistry` | ✅ yes |
| `getValue\s*\(` | ✅ yes |
| `storage/platform/style-registry\|approved-values` | ✅ yes |
| `setValue\|isWritable` | ✅ yes |
| `exec\|shell_exec\|proc_open\|passthru\|system` | ✅ yes |
| `runtime_consumption_enabled.*true` | ✅ yes |
| `return.*true` in `isRuntimeConsumptionEnabled` | ✅ yes |

### Summary

The current gate already allows the planned injection with zero changes to the consumer-specific checks. The consumer file imports `Platform\Style\Contracts\*` (allowed) and calls `readValue(` / `isReachable(` (not blocked). Only `getValue(` is blocked — and that's the exact distinction we want.

**Gate update scope for this phase:** minimal. Add one explicit invariant that consumer `isRuntimeConsumptionEnabled()` still returns false after the import, to catch accidental toggling. This is already covered by existing grep checks but can be hardened.

---

## Part G — Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Consumer evolves into runtime resolver | Low | High — premature consumption would bypass the planned consumption gate | `runtime_consumption_enabled` flag is a hard invariant checked by the boundary gate. No public value method. Diagnostic-only prevents any caller from relying on consumer for style values. |
| Registry becomes theme source | Low | Medium — theme should own values, not registry | Registry is single-socket (`radius.scale`) and explicitly documented as Phase 1 only. Gate blocks multi-socket reads and batch iteration. |
| Public method encourages application | Low (no public method) | High if added | We chose NOT to add `approvedValuePreview()`. Diagnostics-only keeps surface area minimal. Revisit only with explicit contract change. |
| Reader nullability hides failures | Medium | Medium — silent null means "reader not available" could mask deployment errors | `RSC-C004` WARN is emitted when reader is null. This appears in diagnostics. Probe scripts can monitor for unexpected RSC-C004. |
| Diagnostics accidentally expose runtime state | Low | Medium — diagnostics array could leak sensitive data if not scoped | Reader data is limited to reachability + single `radius.scale` string value. Socket values are simple enum strings (`sharp`, `soft`, `round`). No secrets, no user data, no internal paths exposed. |
| Gate misses new consumption pattern | Low | Medium | Gate is explicitly scoped to known patterns. Any new public method or import would be caught by `diff --check` review. Adding a `resolved_` method would also fail the "no public resolved value method" principle enforced by code review. |

---

## Part H — Implementation Sequence

### Step 1: Gate update (docs + invariant)

Update `check_platform_style_consumer_boundary.sh` to explicitly verify that `readValue(` appears only in diagnostic context inside the consumer. Add an invariant ensuring consumer's `isRuntimeConsumptionEnabled()` still returns false after the import change.

See `docs/architecture/resolved-style-consumer-reader-injection-gate-update.md` (planned).

### Step 2: Inject nullable reader into consumer

```php
use Platform\Style\Contracts\ApprovedStyleReaderContract;

final class ResolvedStyleConsumer
{
    private ?ApprovedStyleReaderContract $reader = null;

    public function __construct(?ApprovedStyleReaderContract $reader = null)
    {
        $this->reader = $reader;
    }
}
```

### Step 3: Add diagnostics-only reader checks

Inside `diagnostics()`, append reader diagnostics block:

```php
if ($this->reader !== null) {
    $reachable = $this->reader->isReachable();
    $diagnostics[] = [
        'code' => $reachable ? 'RSC-C001' : 'RSC-C004',
        'severity' => $reachable ? 'INFO' : 'WARN',
        'message' => $reachable
            ? 'Style Registry reader is reachable'
            : 'Style Registry reader is not reachable',
    ];

    if ($reachable) {
        $value = $this->reader->readValue('radius.scale');
        if ($value !== null) {
            $diagnostics[] = [
                'code' => 'RSC-C002',
                'severity' => 'INFO',
                'message' => "Approved radius.scale value: {$value}",
            ];
        } else {
            $diagnostics[] = [
                'code' => 'RSC-C003',
                'severity' => 'WARN',
                'message' => 'No approved radius.scale value found',
            ];
        }
    }
}
```

### Step 4: Keep runtime disabled

No changes to `isRuntimeConsumptionEnabled()`. It remains `false`.

### Step 5: Validate

| Scenario | Expected |
|---|---|
| No reader injected | Diagnostics show RSC-C004, no C001/002/003 |
| Reader injected, registry empty | Diagnostics show RSC-C001 + RSC-C003 |
| Reader injected, radius.scale approved | Diagnostics show RSC-C001 + RSC-C002 |
| Boundary gate | Consumer imports `Platform\Style\Contracts` allowed; `getValue(` still blocked |

---

## Part I — Success Criteria

| # | Criterion | Verification |
|---|---|---|
| 1 | Consumer can report reader reachability | `RSC-C001` or `RSC-C004` in diagnostics output |
| 2 | Consumer can report radius.scale present/absent | `RSC-C002` or `RSC-C003` in diagnostics output |
| 3 | Consumer remains disabled | `runtime_consumption_enabled === false` in diagnostics and via `isRuntimeConsumptionEnabled()` |
| 4 | No direct registry dependency | Consumer imports `Platform\Style\Contracts`, not `Apps\Platform\StyleRegistry` |
| 5 | No Shell dependency | Consumer has no `use Apps\Shell` imports |
| 6 | No writes | Consumer has no `file_put_contents`, `fwrite`, `mkdir` etc. |
| 7 | No runtime style application | No CSS, attribute, or markup generation from readValue output |

---

## Deliverable Summary

1. **Plan path:** `docs/architecture/resolved-style-consumer-reader-injection-plan.md`
2. **Recommended injection style:** Nullable constructor injection — `?ApprovedStyleReaderContract $reader = null`. Graceful degradation when registry is absent.
3. **Allowed reader use:** `isReachable()` and `readValue('radius.scale')` only, only inside `diagnostics()`.
4. **Diagnostics plan:** 5 new codes (RSC-C001–C005), severity INFO/WARN, no ERROR or FAIL in Phase 1.
5. **Public API decision:** No public resolved-value method. Diagnostics-only. Adding `approvedValuePreview()` is deferred to a future explicit consumption phase.
6. **Gate impact:** Minimal — current gate already allows `use Platform\Style\Contracts` and `readValue(` calls. Add one hardened invariant ensuring `isRuntimeConsumptionEnabled` still returns false.
7. **Risks and mitigations:** 6 risks assessed. Highest-risk (consumer becoming runtime resolver) mitigated by diagnostic-only surface + `runtime_consumption_enabled` gate invariant.
8. **Recommended next slice:** `ResolvedStyleConsumer Reader Injection Gate Update` — update the boundary gate invariants before implementing the injection.
