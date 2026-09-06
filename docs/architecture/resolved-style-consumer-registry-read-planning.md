# ResolvedStyleConsumer Registry Read Planning

**Status:** Planning document. No implementation authorized.

**Purpose:** Plan the first authorized registry-read capability for `Platform\Style\ResolvedStyleConsumer`.

---

## Part A — Registry Read Use Cases

### Required for Runtime Proof (Yes)

These reads unblock the runtime proof gate (currently gated by `check_platform_style_consumer_boundary.sh` invariants blocking `ApprovedStyleRegistry` / `getValue(` / `storage/platform/style-registry`):

| Use case | Why required | Success criterion |
|---|---|---|
| Read single approved value | Prove the consumer can resolve a value from the registry | `readValue('radius.scale')` returns `'soft'` when stored |
| Verify registry reachability | Diagnose whether the storage path exists and is parseable at read time | `isReachable()` returns `true` when storage exists |
| Verify value against catalog | Confirm the returned value matches the socket catalog's allowed values | Read value `'soft'` matches catalog entry `allowed_values: ['sharp','soft','round']` |
| Report effective value chain | Show what the consumer resolves from registry → fallback → default | Diagnostics include `source: 'registry'` or `source: 'fallback'` |

### Required for Shell Integration (Not Yet)

These reads belong to the future Shell integration phase, NOT registry-read planning:

| Use case | Why deferred |
|---|---|
| Read value for CSS variable injection | Requires Shell runtime wiring, which is a separate authorization gate |
| Read batch values for rendering context | Shell integration must be authorized before batch reads are useful |
| Map values to data attributes | Same Shell runtime dependency |

### Future Enhancement (Authorized for Contract Design)

| Use case | Priority |
|---|---|
| Read value metadata / provenance | Medium — useful for diagnostics but not blocking |
| Read version information | Low — registry schema version needed for upgrade safety |
| Read diagnostics snapshot | Medium — consumer-level reachability/value-validity reporting |

### Not Authorized (Hard Block)

| Use case | Reason |
|---|---|
| Read Studio draft values | Draft/Runtime boundary must never be crossed |
| Read approval request artifacts | Request lifecycle is Studio-owned editor governance |
| Read Visual Customizer snapshots | Snapshots are Studio-owned apply artifacts |
| Read user theme preferences | ThemePreferenceService is Shell-owned; registry must not duplicate |
| Read Theme source files | Theme compilation is separate from registry overlay |
| Write/approve/modify/create/delete values | Consumer is read-only by definition |

### Scope Conclusion

**First registry read scope:** Single-value read (`readValue`), reachability check (`isReachable`), value-validity diagnostics. Batch reads and metadata deferred to enhancement phase. Shell runtime integration is a separate gate entirely.

---

## Part B — Registry Ownership Review

### Source-of-Record Verification

`Platform\StyleRegistry` is the source-of-record for approved style values:

- `apps/Platform/StyleRegistry/Services/ApprovedStyleRegistry.php` — reads/writes `storage/platform/style-registry/approved-values/{socketId}.json`
- The storage directory exists but is empty (no values have been applied yet)
- Allowlist: only `radius.scale` with values `['sharp', 'soft', 'round']`
- Provenance recorded on write: `request_id`, `snapshot_id`, `applied_by_user_id`, `applied_at`

No other part of the system owns or duplicates approved values. The registry is the single source of record.

### Consumer Ownership Must Never Become

| Role | Owner | Must not become |
|---|---|---|
| Source of truth | Platform StyleRegistry | Consumer must not cache, persist, or serve as fallback truth |
| Approval engine | Studio Visual Customizer lifecycle | Consumer must not create/approve/reject values |
| Draft store | Studio Visual Customizer (file-based) | Consumer must not read or reference draft path |
| Theme store | `resources/themes/` | Consumer must not read, compile, or overlay theme source files |
| Value editor | Studio | Consumer must not write, modify, or transform values |

### Consumer Ownership Must Always Be

| Role | Owner | Documentation |
|---|---|---|
| Registry read entry point | `platform/Style/ResolvedStyleConsumer` | This planning document |
| Diagnostic reporter | `platform/Style/ResolvedStyleConsumer` | `diagnostics()` snapshots |
| Fallback chain resolver | `platform/Style/ResolvedStyleConsumer` | `approved_value → catalog default → provided default` |
| Boundary enforcer | Gate `check_platform_style_consumer_boundary.sh` | Read-allowed/write-blocked invariant enforcement |

### Ownership Boundary Cross-References

All 5 existing architecture contracts already document that Platform owns the registry and the consumer:

| Contract | States Registry Ownership? | States Consumer Ownership? |
|---|---|---|
| `resolved-style-consumer-contract.md` | ✅ Part B | ✅ Part B |
| `style-registry-ownership-contract.md` | ✅ Platform/System | ✅ Shell via consumer |
| `shell-style-socket-contract.md` | ✅ Platform/System | ✅ Platform provides contracts |
| `customization-studio-operating-contract.md` | ✅ Source-of-Truth Map | ✅ Runtime consumer boundary |
| `shell-approved-style-consumption-boundary.md` | ✅ Platform | ✅ Shell receives resolved values |

**Finding:** Ownership documentation is complete and consistent. No new ownership contract needed.

---

## Part C — Read Contract Shape

### Read-Only Contract Interface

The consumer needs a **segregated read-only contract** — separate from `ApprovedStyleRegistryContract` (which includes `setValue` and `isWritable`):

```php
namespace Platform\Style\Contracts;

/**
 * Segregated read-only contract for the ResolvedStyleConsumer.
 *
 * This is the ONLY contract the consumer may use.
 * Access to ApprovedStyleRegistryContract is FORBIDDEN.
 */
interface ApprovedStyleReaderContract
{
    /**
     * Read a single approved value from the registry.
     * Returns null if the socket has no stored value, is not in the
     * registry allowlist, or the registry is unreachable.
     */
    public function readValue(string $socketId): ?string;

    /**
     * Quick reachability check for the registry storage.
     * Returns true when the storage directory exists and is readable.
     */
    public function isReachable(): bool;

    /**
     * Read batch of approved values, keyed by socketId.
     * Non-existent or unset sockets return null.
     * Empty array returns all known values (see scope note).
     */
    public function readValues(?array $socketIds = null): array;

    /**
     * Read metadata/provenance for a specific socket.
     * Returns null if no value exists or socket unknown.
     */
    public function readValueMetadata(string $socketId): ?array;
}
```

### Contract Design Decisions

1. **Separate interface, not shared.** The consumer must never receive the full `ApprovedStyleRegistryContract`. A segregated contract prevents write capability at the type level.

2. **Null safety.** Errors are reported as null returns + diagnostics, not exceptions. The consumer is a diagnostic tool first; exceptions during read should not crash the diagnostic path.

3. **No `isWritable()` equivalent.** The consumer must never know whether a socket is writable — that's governance information, not consumption information.

4. **Batch scope.** `readValues(null)` returning ALL known values is useful for diagnostics/dashboard features but could be expensive. Consider limiting batch read to an explicit socket list in Phase 1.

### Forbidden at Contract Level

| Operation | Why forbidden | Enforcement |
|---|---|---|
| `setValue` | Consumer is read-only | Segregated interface has no write method |
| `isWritable` | Consumer must not know governance state | Not in `ApprovedStyleReaderContract` |
| `approveValue` | Not the consumer's role | Same |
| `activateValue` | Shell integration is a separate gate | Same |
| Any write to file, DB, HTTP, or Shell CSS | Side-effect free guarantee | Gate invariant + contract design |

---

## Part D — Registry Access Model

### Options Evaluated

#### Option 1: Direct Filesystem Reads (Consumer reads JSON directly)

```
platform/Style/ResolvedStyleConsumer.php
  -> file_get_contents('storage/platform/style-registry/approved-values/{socketId}.json')
  -> json_decode()
  -> return value
```

**Advantages:**
- Zero runtime dependencies — no service injection, no container wiring
- Works without any Platform StyleRegistry autoloading
- Path confinement is trivial to enforce (prefix check on resolved path)
- Probe already demonstrates this pattern safely

**Risks:**
- Bypasses `ApprovedStyleRegistry` allowlist validation
- No provenance metadata available (must re-parse raw JSON separately)
- Duplicates filesystem I/O logic already in `ApprovedStyleRegistry`
- Any schema change in registry files requires updating consumer in parallel

#### Option 2: Registry Service Injection (Consumer receives ApprovedStyleReaderContract)

```
platform/Style/ResolvedStyleConsumer.php
  -> $this->reader->readValue('radius.scale')  // reader implements ApprovedStyleReaderContract
  -> ApprovedStyleRegistry::getValue() behind the scenes
  -> returns ?string
```

**Advantages:**
- Typesafe, validated reads through the existing `getValue()` path
- Allowlist validation happens automatically
- Provenance metadata available through the same service
- Single source of filesystem I/O logic
- Easy to mock for unit testing

**Risks:**
- Requires service injection — `ResolvedStyleConsumer` needs a constructor or setter
- Requires PSR-4 autoloading for `Platform\Style\Contracts\` namespace
- Could accidentally receive the FULL `ApprovedStyleRegistryContract` instead of the segregated reader
- Tightens coupling between Platform/Style and apps/Platform/StyleRegistry

#### Option 3: Value Object Projection (Pre-resolved objects from Registry)

```
Platform StyleRegistry
  -> produces array<string, ResolvedApprovedStyleContract>
  -> Consumer receives resolved objects

platform/Style/ResolvedStyleConsumer.php
  -> $this->resolvedContracts['radius.scale']
  -> ->value() returns ?string
```

**Advantages:**
- Cleanest boundary — consumer never touches raw registry
- `ResolvedApprovedStyleContract` already exists with fallback, source, error
- Maximum testability (inject resolved objects)

**Risks:**
- Complex initialization — someone must populate the projection before the consumer runs
- Stale projection if registry changes after initialization
- Over-engineered for a single-value read use case
- `ResolvedApprovedStyleContract` lives in `apps/Platform/StyleRegistry/Contracts/`, which means the consumer would depend on the Platform app namespace

#### Option 4: Snapshot Export (Registry produces JSON file, consumer reads at boot)

```
ApprovedStyleRegistry
  -> exportSnapshot('storage/platform/style-registry/snapshots/consumer-snapshot.json')
  -> platform/Style/ResolvedStyleConsumer reads snapshot at init time

platform/Style/ResolvedStyleConsumer.php
  -> reads pre-computed snapshot JSON
  -> returns values from snapshot
```

**Advantages:**
- Fully decoupled — consumer never calls registry at runtime
- Snapshot can be validated at export time
- Good for diagnostic dashboard (point-in-time report)

**Risks:**
- Stale — snapshot reflects state at export time, not read time
- Complex lifecycle — who triggers the export? How fresh is fresh enough?
- Over-engineered for Phase 1 (single socket, empty registry)

### Recommendation

**Option 2 — Registry Service Injection** with the following constraints:

1. **Segregated interface required.** `Platform\Style\Contracts\ApprovedStyleReaderContract` must be created before any injection. The consumer constructor receives this interface, never `ApprovedStyleRegistryContract`. This interface is formalized in the [Registry Read Contract](registry-read-contract.md).

2. **Implementation adapter.** `Platform\Style\Services\ApprovedStyleReaderAdapter` wraps `ApprovedStyleRegistry::getValue()` behind the read-only interface. The adapter lives in `platform/Style/` (not `apps/Platform/`) so the consumer directory has no dependency on the Platform app's service namespace.

3. **Null safety.** The adapter returns null whenever `getValue()` would return null or the registry is unreachable. No exceptions propagate through the reader interface.

4. **Reachability check.** `isReachable()` in the adapter checks `is_dir()` on the storage root. This is intentionally separate from the registry's own health — the consumer needs its own reachability check that doesn't depend on registry instantiation.

5. **No batch reads in Phase 1.** `readValues()` is defined in the contract but Phase 1 should only implement `readValue()` and `isReachable()`. Batch reads are enhancement scope.

6. **Path confinement.** The adapter must enforce that all resolved storage paths fall under `storage/platform/style-registry/approved-values/`. This mirrors the probe's `rsc_resolve_allowed()` pattern.

### Access Model Diagram (Phase 1)

```
platform/Style/ResolvedStyleConsumer
  -> constructor(ApprovedStyleReaderContract $reader)
  -> readValue('radius.scale')
    -> ApprovedStyleReaderAdapter.readValue('radius.scale')
      -> ApprovedStyleRegistry::getValue('radius.scale')
        -> file_get_contents('storage/platform/style-registry/approved-values/radius.scale.json')
        -> json_decode -> return 'soft'
      -> return 'soft'
    -> return 'soft'
  -> diagnostics() includes reachability + value source
```

---

## Part E — Socket Catalog Alignment

### Three-Way Alignment Model

```
┌─────────────────────┐     ┌──────────────────────┐     ┌───────────────────┐
│  Shell Socket       │     │  Platform Style      │     │  Approved         │
│  Catalog            │     │  Registry            │     │  Values           │
│  (20 JSON files,    │     │  (allowlist:         │     │  (storage JSON)   │
│   140+ sockets)     │     │   radius.scale only) │     │  (currently empty)│
└─────────┬───────────┘     └──────────┬───────────┘     └────────┬──────────┘
          │                            │                          │
          │  "What sockets exist?"     │  "Which sockets are      │  "What values are
          │                            │   governable?"           │   actually stored?"
          └──────────────┬─────────────┴──────────────┬────────────┘
                         │                           │
                         ▼                           ▼
                   Alignment                    Alignment
                   (probe does this)             (consumer does this)
```

### How Alignment Is Verified

| Check | Who does it now | Who does it after reads | Diagnostic |
|---|---|---|---|
| Registry values exist | Probe: Stage 1 | Consumer: `isReachable()` | PASS / RSC-C001 |
| Catalog sockets defined | Probe: Stage 2 | Consumer: catalog read (future) | PASS / RSC-C002 |
| Registered socket in catalog? | Probe: Stage 3 alignment | Consumer: `readValue()` + catalog check | WARN / RSC-C101 |
| Catalog socket has approved value? | Probe: Stage 3 alignment | Consumer: null return from `readValue()` | PASS (expected) / RSC-C201 |
| Approved value valid per catalog? | Probe: Stage 4 | Consumer: validate type constraints | PASS/WARN / RSC-C102 |

### Orphan Value Detection

**Definition:** An approved value exists in the registry for a socket that does NOT appear in the Shell socket catalog.

**Current status:** The probe already detects this (RSC-W001). With zero approved values in storage, no orphans exist yet.

**Future detection path:** The consumer does NOT need to detect orphans. Orphan detection is a probe/diagnostics concern, not a consumer concern. The consumer reads values for specific known sockets. If it receives a request for an orphan socket, it returns null (value not found) — the same behavior as a missing value. The catalog alignment is handled by the probe layer.

**Conclusion:** Move orphan detection OUT of consumer scope. Consumer only needs `readValue()` null return behavior. Catalog alignment stays in the probe layer.

### Missing Value Detection

**Definition:** A catalog socket has NO approved value in the registry.

**Consumer behavior:** `readValue('radius.scale')` returns `null` when no value exists. This is correct — the consumer does not distinguish between "socket not in registry" and "value not set." The fallback chain resolves: null → catalog default → caller-provided default.

**Diagnostic question:** Should the consumer emit a WARN when `readValue()` returns null? Yes — RSC-C201 (Registry value missing, fallback used). This is a mild diagnostic, not a blocking failure.

### Summary — Consumer's Alignment Role

| Alignment concern | Consumer responsibility | Delegated to probe |
|---|---|---|
| Registry reachable | ✅ `isReachable()` | — |
| Single value read | ✅ `readValue()` returns ?string | — |
| Value validity | ✅ Validate catalog type (Phase 2) | — |
| Full catalog alignment scan | ❌ Not consumer scope | ✅ Probe alignment pass |
| Orphan detection | ❌ Not consumer scope | ✅ RSC-W001 |
| Cross-socket consistency | ❌ Not consumer scope | ✅ RSC-W002 / RSC-P001 |

---

## Part F — Diagnostics Expansion

### Diagnostic Family Decision

The existing `RSC-` prefix is shared by the probe (`RSC-P*`, `RSC-W*`, `RSC-F*`, `RSC-E*`). Consumer diagnostics must be distinguishable from probe diagnostics while remaining in the same `RSC-` family.

**Recommendation:** Sub-prefix `RSC-C` (Consumer) for consumer-side diagnostics, keeping `RSC-P` (Probe) for probe-only diagnostics.

### Proposed Consumer Diagnostics

```
PASS:
  RSC-C001 — Socket has approved value
  RSC-C002 — Effective value resolved (fallback chain complete)
  RSC-C003 — Registry reachable and readable

WARN:
  RSC-C101 — Socket not found in catalog (requested unknown socket)
  RSC-C102 — Approved value differs from catalog default
  RSC-C103 — Fallback used (registry value missing for known socket)
  RSC-C104 — Multiple values found for single socket (corruption)

FAIL:
  RSC-C201 — Registry unreachable at read time
  RSC-C202 — Approved value invalid per catalog type constraint
  RSC-C203 — Storage path confinement violation

ERROR:
  RSC-C301 — Reader service not initialized
  RSC-C302 — Read contract violation (write attempt detected)
  RSC-C303 — Schema version mismatch
```

### Diagnostic Emission Points

| Point | Diagnostics emitted |
|---|---|
| `isReachable()` | RSC-C003 (PASS) or RSC-C201 (FAIL) |
| `readValue()` found value | RSC-C001 (PASS) |
| `readValue()` value differs from catalog default | RSC-C102 (WARN) |
| `readValue()` returns null, fallback used | RSC-C103 (WARN) |
| `readValue()` catalog check fails type constraint | RSC-C202 (FAIL) |
| `diagnostics()` snapshot | All accumulated diagnostics |
| Path confinement violation | RSC-C203 (FAIL) |

### Relationship to Probe Diagnostics

```
Probe diagnostics:              Consumer diagnostics:
  RSC-P001 (registry readable)    RSC-C003 (reachable)
  RSC-P002 (catalog readable)     RSC-C001 (value found)
  RSC-W001 (orphan)               RSC-C102 (value differs)
  RSC-W002 (missing)              RSC-C103 (fallback used)
  RSC-F001 (registry unreadable)  RSC-C201 (unreachable)
  RSC-F002 (catalog unreadable)   RSC-C202 (invalid value)
  RSC-E001 (path traversal)       RSC-C203 (confinement)
  RSC-E002 (Studio dependency)    RSC-C302 (contract violation)
```

The consumer diagnostics are NOT a replacement for probe diagnostics. The probe validates full system readiness (registry + catalog + alignment). The consumer validates individual resolution attempts at runtime/diagnostic time. They coexist at different scope levels.

### Update Scope

| Artifact | Update needed |
|---|---|
| `read-only-consumption-probe-contract.md` | Document RSC-C* family exists alongside RSC-P*/W*/F*/E* |
| `resolved-style-consumer-contract.md` | Add Part F.2 for consumer-side diagnostics |
| `check_platform_style_consumer_boundary.sh` | No change — diagnostics are documentation, not invariant |
| Existing probe scripts | No change — probe and consumer diagnostics are independent |

---

## Part G — Boundary Gate Changes

### Invariants That Must Remain (Permanent)

These invariants stay even AFTER registry reads are authorized. They protect the consumer from becoming something it must not be:

```
Must remain:
  1. isRuntimeConsumptionEnabled() !== true
  2. $runtimeConsumptionEnabled !== true
  3. diagnostics['runtime_consumption_enabled'] === false
  4. No Studio coupling (non-comment)
  5. No Shell coupling yet (non-comment) — UNTIL Shell integration is separately authorized
  6. No filesystem writes
  7. No shell/compiler calls
  8. No route registration
  9. No DB/HTTP side effects
  10. No draft artifact access
  11. No approval artifact access
```

### Invariants That May Be Relaxed (Registry-Read Phase)

Current blocking patterns:

```bash
'storage/platform/style-registry|approved-values'
'ApprovedStyleRegistry|getValue\('
```

**Proposed relaxation** — replace blanket block with targeted read-allowed / write-blocked rules:

```bash
# STILL BLOCKED: write access, full registry contract, setValue
'file_put_contents|fwrite|unlink|rename|mkdir|rmdir'  # already exists, stays
'ApprovedStyleRegistry'  # BLOCK full contract import (keep)
'setValue\('              # BLOCK write operation (NEW — replaces getValue block)
'isWritable\('            # BLOCK governance check (NEW)

# ALLOWED after relaxation:
# - ApprovedStyleReaderContract import
# - ApprovedStyleReaderAdapter::readValue()
# - file_get_contents for storage paths (path-confined)
# - is_dir check on allowed storage root
```

The relaxation would look like this in the gate:

```bash
# BEFORE (block everything):
check_no_matches \
  "must not import or call ApprovedStyleRegistry" \
  'ApprovedStyleRegistry|getValue\(' \
  "${platform_style_files[@]}"

# AFTER (block full contract + write; allow reader contract):
check_no_matches \
  "must not import full ApprovedStyleRegistry contract" \
  'use Apps\\Platform\\StyleRegistry' \
  "${platform_style_files[@]}"

check_no_matches \
  "must not call setValue or isWritable" \
  'setValue\s*\(|isWritable\s*\(' \
  "${platform_style_files[@]}"

# New invariant — verify reader contract is used instead
check_reader_contract_exists()  # NEW positive check
```

### Sequence of Gate Relaxation

```
Phase 1 — Registry Read Contract (this planning):
  No gate changes. Document what must change.

Phase 2 — Implement Reader Contract:
  Create Platform\Style\Contracts\ApprovedStyleReaderContract
  Create Platform\Style\Services\ApprovedStyleReaderAdapter
  Update ResolvedStyleConsumer to accept reader via constructor

Phase 3 — Relax Gate:
  Replace blanket ApprovedStyleRegistry/getValue block with:
    - Block: full contract import (use Apps\Platform\StyleRegistry)
    - Block: setValue( / isWritable(
    - Allow: ApprovedStyleReaderContract, readValue(
  Verify consumer still has no Studio/Shell/write/route/DB coupling
  Verify registry write path still blocked
```

### Gate Evolution Diagram

```
CURRENT:
  [consumer file] --blocked--> ApprovedStyleRegistry, getValue(, storage/platform/style-registry

PHASE 3 (after relaxation):
  [consumer file] --allowed--> ApprovedStyleReaderContract, readValue(, isReachable()
  [consumer file] --blocked--> ApprovedStyleRegistry (full), setValue(, isWritable(
  [consumer file] --blocked--> file_put_contents, fwrite, mkdir (unchanged)
  [consumer file] --blocked--> Studio, Shell, routes, DB, drafts, approvals (unchanged)
```

---

## Part H — Risk Assessment

### Risk 1: Registry Becoming Second Theme Source

| Dimension | Value |
|---|---|
| **Severity** | High |
| **Likelihood** | Low |
| **Classification** | Medium |
| **Scenario** | Consumer reads approved values and Shell applies them as CSS variables. Registry values inadvertently override theme-compiled values, creating an implicit second theme system alongside `resources/themes/**`. |
| **Mitigation** | Resolution precedence rule: `theme source → compiled theme.css → approved registry overlay → user preference`. Consumer must emit `source` in diagnostic output for every read. Gate checks that consumer never writes to theme paths. Registry is explicitly scoped to approved overlay values only (e.g., `radius.scale`), not theme tokens. |

### Risk 2: Runtime Consumption Accidentally Enabled

| Dimension | Value |
|---|---|
| **Severity** | High |
| **Likelihood** | Low |
| **Classification** | Medium |
| **Scenario** | A misconfigured flag (`$runtimeConsumptionEnabled = true`) causes Shell to begin consuming registry values before governance is ready, before the Shell consumption boundary is authorized, or before the consumer has passed its own readiness checks. |
| **Mitigation** | Two independent gates: (1) consumer registry-read gate, (2) Shell consumption enable gate. Both must pass independently. The `runtimeConsumptionEnabled` flag defaults to `false` and is enforced by the boundary gate invariant. Shell integration is a separate explicit authorization. |

### Risk 3: Draft/Approved Confusion

| Dimension | Value |
|---|---|
| **Severity** | Medium |
| **Likelihood** | Low |
| **Classification** | Low |
| **Scenario** | Consumer or reader adapter accidentally reads from Studio draft storage instead of approved registry, exposing unapproved values at runtime. |
| **Mitigation** | Path confinement: consumer adapter must resolve all paths under `storage/platform/style-registry/approved-values/`. Gate enforces that `storage/studio/` and any draft-related paths are not referenced. The existing gate already blocks `storage/studio/customization`, `studio_draft`, `approval-request`. These remain blocked after relaxation. |

### Risk 4: Theme Bypass

| Dimension | Value |
|---|---|
| **Severity** | Medium |
| **Likelihood** | Low |
| **Classification** | Low |
| **Scenario** | Registry values are used to set CSS properties that bypass the Theme system's versioned compilation pipeline, creating inconsistency between theme-compiled values and registry-applied values for the same CSS property. |
| **Mitigation** | Registry scope is limited to overlay values that are explicitly OUTSIDE theme tokens. `radius.scale` (border radius) is not a theme-compiled token — it's a component-level preference. Any future socket added to the registry must be explicitly reviewed against the theme token catalog. The customization studio operating contract already forbids expanding beyond `radius.scale` without explicit approval. |

### Risk 5: Shell Ownership Drift

| Dimension | Value |
|---|---|
| **Severity** | Medium |
| **Likelihood** | Medium |
| **Classification** | Medium |
| **Scenario** | Platform-owned `ResolvedStyleConsumer` begins to absorb Shell-specific consumption patterns (CSS variable mapping, data-attribute rendering, selector resolution) instead of remaining a pure data reader. |
| **Mitigation** | Clear ownership boundary: Platform owns data resolution + diagnostics. Shell owns CSS rendering + consumption. The consumer must return raw values, not CSS-ready output. Diagnostics show source and value, not rendered CSS. Gate enforces no Shell coupling in Platform consumer. Shell's own `apps/Shell/Style/Services/ResolvedStyleConsumer` is responsible for Shell-side value-to-CSS mapping — this is a separate file in a separate namespace. |

### Risk 6: Reader Adapter Coupling to Registry Service

| Dimension | Value |
|---|---|
| **Severity** | Low |
| **Likelihood** | Medium |
| **Classification** | Low |
| **Scenario** | The `ApprovedStyleReaderAdapter` depends on `ApprovedStyleRegistry` (which has `setValue`). The adapter must only call `getValue()`, but if the adapter has access to the full service, a future code change could accidentally introduce a write path through the adapter. |
| **Mitigation** | The adapter is the ONLY file in `platform/Style/` that imports `Apps\Platform\StyleRegistry`. The gate must specifically scan the adapter (not the consumer) for `setValue`/`isWritable` usage. The adapter is a thin wrapper with at most 3 public methods (`readValue`, `isReachable`, `readValues`). Code review must verify no write methods are exposed. |

### Risk Summary

| Risk | Classification | Mitigation |
|---|---|---|
| Second theme source | Medium | Resolution precedence rule + scope limitation |
| Accidental runtime consumption | Medium | Two independent gates |
| Draft/approved confusion | Low | Path confinement + existing gate blocks |
| Theme bypass | Low | Scope limited to non-theme overlay values |
| Shell ownership drift | Medium | Namespace separation + gate enforcement |
| Reader adapter coupling | Low | Thin wrapper + targeted gate scan |

---

## Part I — Readiness Classification

### Classification: B — More Contracts Needed First

**Rationale:**

| Criterion | Assessment |
|---|---|
| Probe exists and passes | ✅ `scripts/platform/probe_resolved_style_consumer.php`: 5/5 scenarios |
| Consumer placeholder exists | ✅ `platform/Style/ResolvedStyleConsumer.php`: disabled, side-effect-free |
| Probe boundary gate exists | ✅ `check_read_only_consumption_probe_boundaries.sh`: 18 invariants |
| Consumer boundary gate exists | ✅ `check_platform_style_consumer_boundary.sh`: 14 invariants |
| Registry contract exists | ✅ `ApprovedStyleRegistryContract` + `ApprovedStyleRegistry` |
| Architecture contracts exist | ✅ 5 contracts covering ownership, consumption, sockets, rendering, probe |
| Runtime consumption enabled | ❌ Must remain disabled until Shell integration is authorized |
| Registry reads authorized | ❌ Blocked by boundary gate invariants |
| **Read contract exists** | **❌ This is the gap. No segregated read-only contract exists.** |
| **Reader adapter exists** | **❌ No adapter wraps `ApprovedStyleRegistry::getValue()` behind read-only interface.** |
| **Gate relaxation specified** | **⚠️ Planned in this document, not implemented.** |

**Verdict:** The probe infrastructure, placeholders, ownership docs, and boundary gates are all in place. The missing piece is the read contract itself — a segregated `ApprovedStyleReaderContract` interface + `ApprovedStyleReaderAdapter` implementation — before the gate can be safely relaxed.

### Readiness Checklist for Registry-Read Implementation

```
Required before registry-read implementation:
  [ ] ApprovedStyleReaderContract created at platform/Style/Contracts/
  [ ] ApprovedStyleReaderAdapter created at platform/Style/Services/
  [ ] ResolvedStyleConsumer constructor accepts reader contract
  [ ] Reader contract specifies: readValue, isReachable, readValues, readValueMetadata
  [ ] Phase 1 scope: readValue('radius.scale') + isReachable() only
  [ ] Adapter has no write method (setValue, isWritable forbidden)
  [ ] Gate invariant blocklist updated per Part G
  [ ] Gate invariant: reader adapter only imports ApprovedStyleRegistry
  [ ] Probe updated: consumer read test added to multi-scenario
  [ ] Consumer diagnostics RSC-C* documented in probe contract
  [ ] This planning document committed as architecture contract
```

### Classification Decision Tree

```
Is the probe ready?                         ✅
Is the consumer placeholder ready?          ✅
Are the boundary gates in place?            ✅
Is the registry contract implemented?       ✅
Are the architecture docs complete?         ✅
Is the READ CONTRACT designed?              ⚠️ Planned (this document)
Is the reader adapter implemented?          ❌
Is the gate relaxation specified?           ⚠️ Planned (Part G)

Result: B — More contracts needed first.
Next slice: Registry Read Contract.
```

---

## Deliverable Summary

### 1. Recommended Access Model

**Registry Service Injection with Segregated Read-Only Interface** (Option 2, Part D).

A new `Platform\Style\Contracts\ApprovedStyleReaderContract` interface is injected into `ResolvedStyleConsumer` via constructor. An `ApprovedStyleReaderAdapter` wraps `ApprovedStyleRegistry::getValue()` behind the read-only contract. This gives the consumer typesafe, validated reads without write capability.

### 2. Ownership Findings

Ownership documentation is complete and consistent across all 5 architecture contracts. The Platform Style Registry is confirmed as the sole source of record for approved style values. No new ownership contract is needed — only a read-time segregated interface within the existing Platform/Style namespace.

### 3. Registry-Read Scope

**Phase 1 scope (single socket):**
- `readValue('radius.scale')` → returns `?string`
- `isReachable()` → returns `bool`

**Deferred to enhancement:**
- `readValues()` batch reads
- `readValueMetadata()` provenance reads
- Catalog alignment diagnostics (probe scope, not consumer scope)

**Forbidden:**
- Any write, approve, modify, create, delete operation
- Any Studio draft, approval request, or snapshot access
- Any Shell CSS, data-attribute, or DOM rendering
- Any theme file or compilation access

### 4. Diagnostics Expansion Plan

New consumer-side `RSC-C*` diagnostic family (Part F):

- **PASS:** RSC-C001 (value found), RSC-C002 (effective value resolved), RSC-C003 (reachable)
- **WARN:** RSC-C101 (unknown socket), RSC-C102 (value differs from default), RSC-C103 (fallback used), RSC-C104 (value corruption)
- **FAIL:** RSC-C201 (unreachable), RSC-C202 (invalid value), RSC-C203 (confinement violation)
- **ERROR:** RSC-C301 (not initialized), RSC-C302 (contract violation), RSC-C303 (schema mismatch)

Update `resolved-style-consumer-contract.md` and `read-only-consumption-probe-contract.md` to document the RSC-C family.

### 5. Gate Changes Required Later

| Current invariant | Action | After relaxation |
|---|---|---|
| `ApprovedStyleRegistry|getValue\(` blocked | → | `ApprovedStyleRegistry` blocked, `ApprovedStyleReaderContract\|readValue\(` ALLOWED |
| — | → | NEW: `setValue\(|isWritable\(` blocked in adapter |
| `storage/platform/style-registry|approved-values` blocked | → | `storage/platform/style-registry` blocked for write, ALLOWED for read |
| All remaining invariants | → | UNCHANGED (no Studio, no Shell, no routes, no DB, no drafts, no approvals) |

### 6. Risks and Mitigations

| Risk | Classification | Primary Mitigation |
|---|---|---|
| Second theme source | Medium | Resolution precedence rule, scope limited to overlay values |
| Accidental runtime consumption | Medium | Two independent gates (consumer reads ≠ Shell integration) |
| Draft/approved confusion | Low | Path confinement + existing draft/approval gate blocks |
| Theme bypass | Low | Overlay-only scope (radius.scale is not a theme token) |
| Shell ownership drift | Medium | Namespace separation (Platform/Style vs Shell/Style) |
| Reader adapter coupling | Low | Thin adapter with targeted gate scan |

### 7. Readiness Classification

**B — More Contracts Needed First.**

The probe, placeholder, gates, and ownership docs are complete. The gap is the read contract itself — a segregated read-only interface + adapter. Once those exist and the gate relaxation is applied, consumer registry reads can be safely implemented.

### 8. Recommended Next Slice

**Registry Read Contract** — the architecture contract defining `ApprovedStyleReaderContract` interface, `ApprovedStyleReaderAdapter` design, Phase 1 scope, diagnostic codes, and the gate relaxation plan. This is the bridge between this planning document and implementation.

**Status:** ✅ Completed. See [Registry Read Contract](registry-read-contract.md) for the formalized contract that implements this planning document's recommendations (Option 2 — Registry Service Injection, RSC-C* diagnostics family, gate relaxation plan, resolution precedence chain).

---

## Appendices

### A. Reference — Current Consumer Diagnostic Snapshot

From `platform/Style/ResolvedStyleConsumer.php::diagnostics()`:

```json
{
  "consumer_status": "placeholder",
  "contract_version": "1.0.0",
  "ownership_verified": true,
  "runtime_consumption_enabled": false,
  "diagnostics": [{
    "code": "RSC-PLACEHOLDER-001",
    "severity": "INFO",
    "message": "ResolvedStyleConsumer is a read-only placeholder. Runtime consumption not enabled."
  }],
  "boundary_limits": {
    "No Studio imports": true,
    "No Shell imports": true,
    "No registry value reads": true,
    "No registry value writes": true,
    "No Shell CSS reads": true,
    ...
  }
}
```

After Phase 1 implementation, the diagnostics would show `"No registry value reads": false` (replaced by "ApprovedStyleReaderContract only") and would include RSC-C* diagnostics in the diagnostics array.

### B. Reference — Key File Paths

| Artifact | Path |
|---|---|
| Platform consumer | `platform/Style/ResolvedStyleConsumer.php` |
| Future reader contract | `platform/Style/Contracts/ApprovedStyleReaderContract.php` |
| Future reader adapter | `platform/Style/Services/ApprovedStyleReaderAdapter.php` |
| Registry contract | `apps/Platform/StyleRegistry/Contracts/ApprovedStyleRegistryContract.php` |
| Registry implementation | `apps/Platform/StyleRegistry/Services/ApprovedStyleRegistry.php` |
| Registry storage | `storage/platform/style-registry/approved-values/` |
| Shell consumer stub | `apps/Shell/Style/Services/ResolvedStyleConsumer.php` |
| Socket catalog | `apps/Shell/Style/Resources/socket-catalog/` (20 files) |
| Platform probe | `scripts/platform/probe_resolved_style_consumer.php` |
| Consumer boundary gate | `scripts/architecture/check_platform_style_consumer_boundary.sh` |

### C. Reference — Gate Relaxation Diff (Proposed)

This is a planning artifact only — no file will be changed in this slice.

```diff
--- current gate invariant (lines 258-271)
+++ relaxed gate invariant (Phase 3)
@@ -258,15 +258,21 @@
-  check_no_matches \
-    "must not read registry storage paths" \
-    'storage/platform/style-registry|approved-values' \
+  # Allow read patterns; block write patterns
+  check_no_matches \
+    "must not write to registry storage paths" \
+    'file_put_contents.*style-registry|fwrite.*style-registry' \
     "${platform_style_files[@]}"

   check_no_matches \
-    "must not import or call ApprovedStyleRegistry" \
-    'ApprovedStyleRegistry|getValue\(' \
+    "must not import full ApprovedStyleRegistry" \
+    'use Apps\\Platform\\StyleRegistry' \
+    "${platform_style_files[@]}"
+
+  check_no_matches \
+    "must not call setValue or isWritable" \
+    'setValue\s*\(|isWritable\s*\(' \
     "${platform_style_files[@]}"

   check_no_matches \
     "must not access draft artifacts" \
     'storage/studio/customization|studio_draft|visual-customizer.*draft' \
     "${platform_style_files[@]}"
```
