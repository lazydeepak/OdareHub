# Registry Read Contract

**Status:** Architecture contract. No implementation authorized. No gate relaxation authorized.

**Date:** 2026-06-11

**Purpose:** Define the read-only contract that authorizes future `Platform\Style\ResolvedStyleConsumer` access to approved Platform Style Registry values.

**Builds on:**
- [ResolvedStyleConsumer Contract](resolved-style-consumer-contract.md) — consumer ownership, inputs/outputs, read-only guarantees
- [ResolvedStyleConsumer Registry Read Planning](resolved-style-consumer-registry-read-planning.md) — use-case analysis, risk assessment, access model evaluation
- [Read-Only Consumption Probe Contract](read-only-consumption-probe-contract.md) — probe diagnostics and readiness validation
- [Customization Studio Operating Contract](customization-studio-operating-contract.md) — governance layer and registry ownership
- [Style Registry Ownership Contract](style-registry-ownership-contract.md) — Platform registry source-of-record
- [Registry Read Boundary Gate Update Plan](registry-read-boundary-gate-update-plan.md) — gate relaxation plan, file scope rules, implementation sequence
- [Platform Style Consumption Surface Contract](platform-style-consumption-surface-contract.md) — Platform-owned consumption surface, typed accessors, PSC-* diagnostics, gate implications (planned gate #29)

---

## Part A — Purpose

### Allowed Purpose

Registry reads exist for exactly three purposes:

```
1. Approved value visibility
   — Read a single approved value so the consumer can report it in diagnostics.
   - Prove the governance-to-registry-to-consumer pipeline is intact.
   - Enable the runtime proof gate to demonstrate end-to-end resolution.

2. Runtime diagnostics
   — Report registry reachability, value availability, and resolution source.
   - Answer: Is the registry readable? Does a socket have an approved value?
     Did the consumer resolve from registry or fallback?
   - All diagnostics are read-only and side-effect-free.

3. Future runtime consumption preparation
   — Establish the read interface and adapter before Shell integration.
   - The read contract must exist and be proven before any runtime
     consumer code is wired to Shell rendering.
   - Registry reads are a prerequisite for Shell integration, not
     the integration itself.
```

### Forbidden Purpose

Registry reads must never be used for:

```
approval               — the consumer has no authority to approve values
draft creation         — drafting belongs to Studio Visual Customizer
theme editing          — theme source is authoritative for all source-driven styling
registry mutation      — the consumer is read-only by definition
theme compilation      — theme compilation belongs to Scripts Assets tooling
Shell mutation         — Shell CSS, data attributes, and rendering are Shell-owned
value authoring        — the consumer must not create or propose values
governance bypass      — the consumer must not read unapproved values
threshold crossing     — the consumer must not activate or enable runtime features
```

---

## Part B — Ownership

### Platform Style Registry Owns

| Concern | Detail |
|---|---|
| Approved values | Source of record for all approved style values |
| Registry persistence | File storage under `storage/platform/style-registry/approved-values/` |
| Registry lifecycle | Schema, storage path, cleanup, backup |
| Write authority | Only through `ApprovedStyleRegistry::setValue()` via Studio Apply workflow |
| Value validation | Allowlist enforcement, value type checking, provenance recording |

### `ResolvedStyleConsumer` Owns

| Concern | Detail |
|---|---|
| Read-only consumption preparation | Reads approved values through a segregated read-only contract |
| Read diagnostics | Reports reachability, value availability, and resolution source |
| Future runtime projection | Prepares the interface shape that Shell will eventually consume |

### Studio Owns

| Concern | Detail |
|---|---|
| Drafting | Unpublished value proposals in Visual Customizer |
| Approval | Request/review/approve/reject lifecycle |
| Governance | Validation, audit, snapshot, rollback |
| Apply | Writing approved values to the registry via `ApprovedStyleRegistry::setValue()` |

### What the Consumer Must Never Become

| Role | Why | Enforcement |
|---|---|---|
| Source of truth | Only the Platform Style Registry stores approved values | Consumer depends on reader contract, never owns storage |
| Approval engine | Studio approval lifecycle is separate governance | Consumer has no `approve()`, `reject()`, or `submit()` method |
| Draft engine | Studio drafting is a separate authoring workflow | Consumer has no `createDraft()`, `saveDraft()`, or `loadDraft()` method |
| Second registry | Duplicate storage would create trust ambiguity | Consumer never writes to any storage path |
| Theme source | Theme files are authoritative for source styling | Consumer never reads `resources/themes/` |
| Editor service | The consumer is a runtime diagnostic tool, not an editor | Consumer has no `edit()`, `preview()`, or `diff()` method |

### Ownership Enforcement

```
- Platform/Style/ResolvedStyleConsumer.php  — the consumer
- Platform/Style/Contracts/                — read-only contracts
- Platform/Style/Services/                 — reader adapter (wraps ApprovedStyleRegistry)
- apps/Platform/StyleRegistry/             — registry + full contract + write authority
- apps/Studio/Tools/CustomizationStudio/   — draft + approval + apply
```

The consumer lives under `platform/`, not `apps/`. This is intentional — the consumer is Platform infrastructure, not an app feature. The adapter bridges Platform/Style to apps/Platform/StyleRegistry through a segregated contract only.

---

## Part C — `ApprovedStyleReaderContract`

### Phase 1 Interface

```php
namespace Platform\Style\Contracts;

/**
 * Segregated read-only contract for resolved style consumption.
 *
 * This is the ONLY contract the ResolvedStyleConsumer may depend on.
 * Access to ApproveStyleRegistryContract (which includes setValue,
 * isWritable, and write provenance) is FORBIDDEN in consumer code.
 *
 * @see docs/architecture/registry-read-contract.md
 */
interface ApprovedStyleReaderContract
{
    /**
     * Read a single approved value from the Platform Style Registry.
     *
     * Returns null when:
     *   - the socket has no stored approved value
     *   - the socket is not in the registry allowlist
     *   - the registry storage is unreachable
     *
     * Must not throw exceptions for missing values or unreachable storage.
     * Null return + diagnostic code is the expected contract.
     *
     * @param string $socketKey e.g. 'radius.scale'
     * @return string|null The approved value, or null.
     */
    public function readValue(string $socketKey): ?string;

    /**
     * Quick reachability check for the registry storage.
     *
     * Returns true when the storage directory exists and is readable.
     * Returns false when the directory is missing, inaccessible, or the
     * read check fails for any reason.
     *
     * This check must not write to storage, modify state, or produce
     * side effects.
     *
     * @return bool True when the registry storage is reachable.
     */
    public function isReachable(): bool;
}
```

### Design Decisions

1. **Segregated interface.** `ApprovedStyleReaderContract` is a new interface, not an extension of `ApprovedStyleRegistryContract`. The consumer must never have access to `setValue()`, `deleteValue()`, or `isWritable()`. A separate interface enforces this at the type level.

2. **Null safety.** Missing values and unreachable storage return `null`, not exceptions. The consumer is a diagnostic tool — exceptions from the read path should not propagate into diagnostic output. The null return is paired with a diagnostic code (RSC-C103 for missing value, RSC-C201 for unreachable).

3. **Socket key, not socket ID.** The parameter is named `$socketKey` to match the probe convention (`--socket=radius.scale`) and differentiate from internal registry IDs. The key is the human-readable identifier used in the socket catalog and storage filenames.

4. **No batch reads in Phase 1.** `readValues()` is intentionally excluded. Phase 1 reads a single value for a single known socket (`radius.scale`). Batch reads introduce complexity (iteration order, partial failures, performance) that is unnecessary until Shell integration requires it.

5. **No metadata reads in Phase 1.** `readValueMetadata()` (provenance, timestamps, write context) is deferred. Phase 1 only needs the value and reachability. Metadata is a future enhancement for diagnostic depth.

### Explicitly Forbidden Methods

The following methods must NEVER appear in `ApprovedStyleReaderContract`:

```php
setValue(string $socketKey, string $value, array $context): array    // write
deleteValue(string $socketKey): bool                                 // delete
save(array $values): array                                           // batch write
approve(string $socketKey, string $value): bool                      // governance
isWritable(string $socketKey): bool                                  // governance
reset(): bool                                                        // destructive
clear(): bool                                                        // destructive
import(array $values): array                                         // bulk write
export(): array                                                      // out of scope
```

Any future evolution of the reader contract must maintain a strict read-only surface. Adding any write, governance, or destructive method is a breaking contract change and requires explicit authorization.

---

## Part D — Adapter Design

### `ApprovedStyleReaderAdapter`

The adapter lives in `platform/Style/Services/` and is the ONLY file in the consumer directory tree that imports `Apps\Platform\StyleRegistry`.

```php
namespace Platform\Style\Services;

use Platform\Style\Contracts\ApprovedStyleReaderContract;
use Apps\Platform\StyleRegistry\Services\ApprovedStyleRegistry;

/**
 * Adapter that wraps ApprovedStyleRegistry behind the read-only
 * ApprovedStyleReaderContract.
 *
 * This is the single bridge between Platform/Style and
 * apps/Platform/StyleRegistry. All registry reads go through this adapter.
 */
final class ApprovedStyleReaderAdapter implements ApprovedStyleReaderContract
{
    private ?ApprovedStyleRegistry $registry = null;

    public function readValue(string $socketKey): ?string
    {
        $registry = $this->loadRegistry();
        if ($registry === null) {
            return null;
        }
        return $registry->getValue($socketKey);
    }

    public function isReachable(): bool
    {
        // Independent reachability check — does not depend on registry instantiation
        $storagePath = $this->resolveStoragePath();
        return $storagePath !== null && is_dir($storagePath);
    }

    private function loadRegistry(): ?ApprovedStyleRegistry
    {
        if (!class_exists(ApprovedStyleRegistry::class)) {
            return null;
        }
        try {
            $this->registry ??= new ApprovedStyleRegistry();
        } catch (\Throwable) {
            return null;
        }
        return $this->registry;
    }

    private function resolveStoragePath(): ?string
    {
        $root = defined('APP_ROOT') ? APP_ROOT : (dirname(__DIR__, 4));
        $path = $root . '/storage/platform/style-registry/approved-values';
        $resolved = realpath($root) ? realpath($root) : $root;
        $expected = $resolved . '/storage/platform/style-registry/approved-values';

        // Path confinement: verify resolved path falls under expected root
        $candidate = realpath($path);
        if ($candidate === false || strncmp($candidate, $expected, strlen($expected)) !== 0) {
            return null;
        }
        return $candidate;
    }
}
```

### Design Rules

1. **Adapter may import `ApprovedStyleRegistry`.** This is the only exception to the "no ApprovedStyleRegistry in platform/Style/" rule. All other files in `platform/Style/` (consumer, contracts) must NOT import it.

2. **Adapter exposes only `ApprovedStyleReaderContract`.** The adapter's public API is exactly the two methods on the contract. No additional public methods. No write access exposed.

3. **Adapter must not call `setValue()` or `isWritable()`.** The boundary gate (`check_platform_style_consumer_boundary.sh`) must scan the adapter specifically for these method calls and fail if they appear.

4. **Lazy registry loading.** The adapter does not instantiate `ApprovedStyleRegistry` in its constructor. Instantiation happens on first read. This keeps the consumer constructable without autoloading the Platform app registry namespace.

5. **Independent reachability.** `isReachable()` checks the filesystem directly rather than calling the registry. This keeps the reachability check independent of registry autoloading and instantiation.

6. **Path confinement.** `resolveStoragePath()` verifies the resolved real path falls under the expected `storage/platform/style-registry/approved-values/` prefix. This is the same pattern used by the probe (`rsc_resolve_allowed()`).

### What the Consumer Must Depend On

```
ResolvedStyleConsumer
  -> ApprovedStyleReaderContract  (interface, platform/Style/Contracts/)
  -> NOT ApprovedStyleRegistry    (implementation, apps/Platform/StyleRegistry/)
```

The consumer constructor must type-hint the interface, not the implementation:

```php
// CORRECT
public function __construct(
    private ApprovedStyleReaderContract $reader
) {}

// FORBIDDEN
public function __construct(
    private ApprovedStyleRegistry $registry
) {}
```

---

## Part E — Phase 1 Read Scope

### Authorized

| Operation | Method | Scope |
|---|---|---|
| Read single approved value | `readValue('radius.scale')` | Returns `?string`. Socket fixed to `radius.scale` until multi-socket expansion is authorized. |
| Reachability check | `isReachable()` | Returns `bool`. Checks storage directory existence only. |
| Basic diagnostics | `diagnostics()` | Emits RSC-C* codes for each read/resolve attempt. See Part G. |

### Not Authorized (Phase 1)

| Operation | Why excluded |
|---|---|
| Batch reads (`readValues([])`) | No caller needs batch reads until Shell integration. Adds iteration/error complexity without use. |
| Catalog scans | Catalog alignment is the probe's job, not the consumer's. |
| Metadata reads (`readValueMetadata()`) | Provenance and timestamps add diagnostic depth but are not required for Phase 1 proof. |
| Draft reads | Never authorized (draft/runtime boundary). |
| Approval reads | Never authorized (governance boundary). |
| History reads | Registry does not maintain value history in Phase 1. |
| Version reads | Registry version is a single constant; not needed for single-value read. |
| Cross-socket validation | Each socket is resolved independently. Cross-socket consistency is not a consumer concern. |

### Socket Scope

Phase 1 reads are limited to sockets that satisfy ALL criteria:

1. Listed in the registry allowlist (`ApprovedStyleRegistry::ALLOWED_SOCKETS`)
2. Listed in the Shell socket catalog (under `apps/Shell/Style/Resources/socket-catalog/`)
3. Approved for consumption by the Customization Studio governance layer

Currently only `radius.scale` satisfies all three criteria. No additional sockets may be read until explicitly authorized through the full governance chain (Visual Customizer → approval → registry → consumer).

---

## Part F — Resolution Precedence

### Canonical Precedence Chain

```
Layer 1: Theme Source Values
  └── resources/themes/foundation.css
  └── resources/themes/semantic/semantic.css
  └── resources/themes/light.css / dark.css
  └── resources/themes/liquid-glass.css / paper.css
  └── Theme style variants (dark-liquid-glass, etc.)
      │
      ▼
Layer 2: Compiled Theme Artifact
  └── public/assets/theme.css (generated from source by compile_theme_sources.php)
      │
      ▼
Layer 3: Approved Registry Overlay (THIS CONTRACT)
  └── storage/platform/style-registry/approved-values/radius.scale.json
  └── Read by ResolvedStyleConsumer via ApprovedStyleReaderContract
      │
      ▼
Layer 4: Future Runtime Projection (Shell Integration — NOT authorized)
  └── CSS custom properties on :root or <body>
  └── data-* attributes on wrapper elements
  └── Shell rendering of resolved values
```

### Meaning of Each Layer

**Layer 1 (Theme source):** Authoritative source for all theme-driven styling. Foundation tokens, semantic tokens, style variant overrides. Owned by `resources/themes/`.

**Layer 2 (Compiled artifact):** Generated deliverable from Layer 1. Deterministic output of `compile_theme_sources.php`. Served at runtime as `/assets/theme.css`. Owned by the theme compilation pipeline.

**Layer 3 (Approved registry overlay — this contract):** Values that have passed through the full Studio governance lifecycle (Visual Customizer draft → validation → approval → apply → registry). Each value is an approved override for a specific socket. Registry values are additive overlays on top of theme defaults, not replacements for theme source.

**Layer 4 (Future runtime projection):** The eventual Shell-side consumption of resolved registry values as CSS variables, data attributes, or style sockets. Not authorized in this contract. Requires separate Shell Integration Contract.

### Registry Is Not a Second Theme Source

```
Theme source (resources/themes/)   — defines ALL style tokens for the entire system
Registry (storage/platform/style-registry/) — approves a FEW overlay values

The registry does NOT:
  - define token values
  - compile CSS
  - replace theme files
  - introduce new design concepts
  - override theme tokens without explicit socket definition

The registry ONLY:
  - stores approved values for named, catalog-listed sockets
  - enables Runtime to read "what value was approved for this socket?"
  - serves as the bridge between governance (Studio) and consumption (Shell)
```

### Overlay Scope

Registry values are overlays on specific named sockets. They are not bulk token replacements. Each socket in the registry corresponds to:

1. A specific design dimension (e.g., border radius)
2. A named entry in the Shell socket catalog
3. A draftable option in Visual Customizer
4. An approved value in the registry

This four-way correspondence (catalog ↔ draft ↔ approval ↔ registry) ensures every registry value has explicit governance provenance and a known consumption target.

---

## Part G — Diagnostics

### RSC-C* Family

All consumer diagnostics use the `RSC-C` sub-prefix within the `RSC-` diagnostic family. They are distinct from probe diagnostics (`RSC-P*`, `RSC-W*`, `RSC-F*`, `RSC-E*`).

### Diagnostic Table

| Code | Severity | Meaning |
|---|---|---|
| **RSC-C001** | PASS | Socket has approved value — `readValue()` returned a non-null string from the registry |
| **RSC-C002** | PASS | Effective value resolved — fallback chain produced a value (registry → catalog default → provided default) |
| **RSC-C003** | PASS | Registry reachable — `isReachable()` confirmed storage exists and is readable |
| **RSC-C101** | WARN | Socket not in catalog — `readValue()` was called with a socket key not found in the Shell socket catalog |
| **RSC-C102** | WARN | Value differs from catalog default — approved value does not match the socket catalog's default entry |
| **RSC-C103** | WARN | Fallback used — `readValue()` returned null and the consumer resolved via fallback chain |
| **RSC-C104** | WARN | Multiple values found — registry returned more than one value for a single socket (possible corruption) |
| **RSC-C201** | FAIL | Registry unreachable — `isReachable()` returned false; storage directory missing, permission denied, or path confinement failed |
| **RSC-C202** | FAIL | Approved value invalid — value does not pass catalog type constraint (e.g., not in `allowed_values` list) |
| **RSC-C203** | FAIL | Storage path confinement violation — resolved path escapes expected `storage/platform/style-registry/approved-values/` prefix |
| **RSC-C301** | ERROR | Reader service not initialized — `readValue()` or `isReachable()` called before the reader contract was injected |
| **RSC-C302** | ERROR | Read contract violation — write attempt detected in the consumer code path |
| **RSC-C303** | ERROR | Schema version mismatch — registry storage schema version does not match consumer contract version |

### Diagnostic Emission Points

| Consumer method | Conditions | Codes emitted |
|---|---|---|
| `isReachable()` | Storage reachable | RSC-C003 PASS |
| `isReachable()` | Storage unreachable | RSC-C201 FAIL |
| `isReachable()` | Path confinement fails | RSC-C203 FAIL |
| `readValue()` | Value returned | RSC-C001 PASS |
| `readValue()` | Value matches catalog default | RSC-C001 PASS only (no separate WARN) |
| `readValue()` | Value differs from catalog default | RSC-C001 PASS + RSC-C102 WARN |
| `readValue()` | Socket not in catalog | RSC-C101 WARN |
| `readValue()` | Null returned, fallback used | RSC-C103 WARN |
| `readValue()` | Value fails catalog type check | RSC-C202 FAIL |
| `readValue()` | Multiple values per socket | RSC-C104 WARN |
| `diagnostics()` | Any accumulated diagnostics | All RSC-C* codes emitted since last reset |
| Constructor | Reader not injected | RSC-C301 ERROR |
| Contract check | Write path detected | RSC-C302 ERROR |
| Version check | Schema version mismatch | RSC-C303 ERROR |

### Diagnostic Output Shape

```json
{
  "code": "RSC-C001",
  "severity": "PASS",
  "message": "Socket 'radius.scale' has approved value 'sharp'",
  "context": {
    "socketKey": "radius.scale",
    "value": "sharp",
    "source": "registry"
  }
}
```

```json
{
  "code": "RSC-C103",
  "severity": "WARN",
  "message": "Socket 'radius.scale' has no approved value; fallback 'soft' used",
  "context": {
    "socketKey": "radius.scale",
    "fallbackValue": "soft",
    "source": "fallback",
    "fallbackReason": "registry_value_missing"
  }
}
```

### Relationship to Probe Diagnostics

```
Probe scope:          Full system readiness (registry + catalog + alignment)
Consumer scope:       Individual resolution attempt (one socket, one read)

Probe codes:          RSC-P001 through RSC-E002 (8 codes, all levels)
Consumer codes:       RSC-C001 through RSC-C303 (13 codes, all levels)

The probe runs on demand (CLI). The consumer runs on each resolution
attempt (diagnostic or future runtime). They coexist at different
scope levels. Consumer diagnostics do not replace probe diagnostics.
```

---

## Part H — Gate Relaxation Plan

### Current Blocking Invariants

From `scripts/architecture/check_platform_style_consumer_boundary.sh`, the following invariants currently block registry reads in `platform/Style/`:

```bash
# Line ~258 — blocks storage path reads
'storage/platform/style-registry|approved-values'

# Line ~266 — blocks all getValue access
'ApprovedStyleRegistry|getValue\('
```

### Invariants That Must Remain Permanent

The following invariants must NEVER be relaxed:

```
1. isRuntimeConsumptionEnabled() !== true
2. $runtimeConsumptionEnabled !== true
3. diagnostics['runtime_consumption_enabled'] === false
4. No Studio coupling (non-comment)
5. No Shell coupling (non-comment) — until Shell integration is separately authorized
6. No filesystem writes
7. No shell/compiler calls
8. No route registration
9. No DB/HTTP side effects
10. No draft artifact access
11. No approval artifact access
```

### Invariants That May Be Relaxed (Future Phase)

The following relaxation is planned but NOT authorized in this contract:

```
RELAXATION SCOPE (future gate update, not implemented now):

REMOVE:
  'storage/platform-style-registry|approved-values'  (blanket block)
  'ApprovedStyleRegistry|getValue\('                  (blanket block)

REPLACE WITH:
  # BLOCK full contract import — only adapter may import it
  'use Apps\\Platform\\StyleRegistry'

  # BLOCK write methods
  'setValue\s*\('
  'isWritable\s*\('

  # ALLOW specific read methods through the adapter
  # ApprovedStyleReaderContract, readValue(, and isReachable are allowed
  # but require no explicit grep allowance — the absence of the above
  # blocks makes them implicitly allowed.
```

### Gate Relaxation Preconditions

The following must be true before the gate is relaxed:

```
[ ] ApprovedStyleReaderContract exists at platform/Style/Contracts/
[x] ApprovedStyleReaderAdapter exists at platform/Style/Adapters/
[ ] Adapter is the ONLY file importing Apps\Platform\StyleRegistry
[ ] Adapter does not call setValue() or isWritable()
[ ] ResolvedStyleConsumer constructor accepts ApprovedStyleReaderContract
[ ] ResolvedStyleConsumer does not import ApprovedStyleRegistry directly
[ ] Boundary gate scans adapter specifically for setValue/isWritable
[ ] Probe passes with consumer read test
[ ] This contract is committed
```

### Gate Relaxation Is Not Implementation Authorization

Relaxing the gate is a separate authorization from implementing registry reads. The sequence is:

```
1. This contract exists (NOW)
2. Registry Read Boundary Gate Update Plan (next slice — document the exact gate diff)
3. Gate relaxation (update check_platform_style_consumer_boundary.sh)
4. Implement ApprovedStyleReaderContract
5. Implement ApprovedStyleReaderAdapter
6. Wire adapter into ResolvedStyleConsumer
7. Test with existing probe
```

Steps 3-7 each require explicit authorization. This contract authorizes Step 1 only.

---

## Part I — Security & Safety

### Read-Only Access

```
- The consumer reads registry values only through ApprovedStyleReaderContract.
- The consumer must not write to any storage path.
- The consumer must not modify any file, database, or session state.
- All consumer methods are idempotent — calling them multiple times with
  the same registry state produces the same result.
```

### Path Confinement

```
- The adapter must resolve all storage paths under:
    storage/platform/style-registry/approved-values/
- Path traversal attempts must return null and emit RSC-C203.
- The confinement check must happen before any filesystem I/O.
- The adapter must use realpath() to resolve symbolic links and verify
  the resolved path falls under the expected prefix.
```

### No Writes

```
- No file_put_contents, fwrite, unlink, rename, mkdir, rmdir, copy, chmod, chown
- No PDO, mysqli, or database connections
- No curl, file_get_contents(URL), stream_context_create for HTTP
- No exec, shell_exec, proc_open, passthru, system
- No compile_theme_sources calls
- No session modifications
- No cache writes (APCu, Redis, file cache)
```

### No Studio Imports

```
- No use Apps\Studio\* in any platform/Style/ file except adapter tests
- No references to apps/Studio/Tools/ paths in non-comment code
- No calls to StudioController, VisualCustomizer*, CustomizationStudio*
- The probe already validates this (RSC-E002). The consumer must match.
```

### No Shell Imports

```
- No use Apps\Shell\* in any platform/Style/ file
- No references to apps/Shell/styles/ paths in non-comment code
- No calls to ShellStyleService, ThemePreferenceService, or Shell runtime services
- Shell integration is a separate authorization gate
```

### No DB or HTTP

```
- No database reads or writes (PDO, mysqli, ORM)
- No HTTP requests (curl, file_get_contents with URL, HTTP client)
- No external service calls
- Internal filesystem reads ONLY (path-confined to registry storage)
```

### No Route Registration

```
- No Route::, routes.php registration in platform/Style/ files
- No POST, GET, PUT, DELETE route handlers
- CLI-only entry points (scripts/ probe) for diagnostic use
```

### Type Safety

```
- readValue() returns string|null — never raw registry JSON
- isReachable() returns bool — no string status codes
- Null returns are paired with diagnostic codes for context
- No exceptions propagate through the consumer API
- Registry errors (permission, missing file, parse failure) are
  reported as null + diagnostic, not thrown exceptions
```

---

## Part J — Explicit Non-Goals

| Non-goal | Reason |
|---|---|
| Runtime consumption | Shell is not authorized to apply registry values in this phase |
| Shell integration | Separately gated and authorized — not part of this contract |
| Registry mutation | `setValue()` is forbidden; only Studio Apply may write to registry |
| Theme mutation | Theme source files must not be read or modified by the consumer |
| Approval workflows | Studio owns approval lifecycle; consumer has no approval authority |
| Draft workflows | Visual Customizer owns drafting; consumer must not read drafts |
| Theme compilation | `compile_theme_sources.php` is the sole theme compiler |
| Visual Customizer integration | Consumer references VC apply output (registry) but must not import VC code |
| CSS Token Editor integration | CTE is a standalone tool with its own save path — no overlap with registry reads |
| Batch value reads | `readValues()` is deferred to Phase 2 enhancement |
| Value metadata/provenance reads | `readValueMetadata()` is deferred to Phase 2 enhancement |
| Catalog alignment scans | Full catalog alignment is a probe concern, not a consumer concern |
| Cross-socket validation | Each socket is resolved independently |
| Value history or versioning | Registry does not maintain value history in Phase 1 |
| Caching layer | No APCu, Redis, file cache, or opcache for registry values |
| HTTP API | No REST endpoint for registry reads — CLI probe + in-process consumer only |
| CLI tool for consumers | The existing probe (`scripts/platform/probe_resolved_style_consumer.php`) is sufficient for diagnostic use |
| Performance optimization | Not scoped until Shell integration phase |
| Multi-tenant isolation | Registry is single-tenant in Phase 1 |
| Rollback support | Rollback is a Studio governance concern, not a consumer concern |

---

## Part K — Cross References

### This Contract Builds On

| Document | Relationship |
|---|---|
| `resolved-style-consumer-contract.md` | Consumer ownership, inputs/outputs, read-only guarantees. This contract defines the reader interface that the consumer contract anticipates. |
| `resolved-style-consumer-registry-read-planning.md` | Use-case analysis, risk assessment, access model evaluation. This contract formalizes Option 2 (Registry Service Injection) from the planning document. |
| `read-only-consumption-probe-contract.md` | Probe diagnostics (RSC-P*/W*/F*/E*) and readiness validation. This contract adds the RSC-C* consumer diagnostics family alongside the existing probe codes. |
| `customization-studio-operating-contract.md` | Governance layer, registry ownership, apply workflow. This contract defines the consumer side of the Studio→Registry→Consumer pipeline. |
| `style-registry-ownership-contract.md` | Platform registry source-of-record and ownership boundaries. This contract defines the segregated read contract that the registry provides. |
| `shell-style-socket-contract.md` | Socket catalog definitions that consumer values map to. |
| `shell-approved-style-consumption-boundary.md` | Shell consumption boundary plan that this contract prepares for. |
| `shell-consumption-contract.md` | Shell-facing consumption planning, Platform Consumption Surface model, RSC-S* diagnostics family. Builds on this contract's read interface. |
| `runtime-style-application-contract.md` | Layer 4 pipeline from consumption surface values to Shell rendering. Defines `ResolvedStyleValue` model, value resolution (identifier→CSS), pre-application validation, fallback chain, and RSC-S* diagnostics. Builds on this contract's read interface via the consumption surface. |
| `registry-read-boundary-gate-update-plan.md` | Gate relaxation plan specifying exact invariant changes, file scope rules, and implementation sequence for the boundary gate update. |

### Documents That Must Reference This Contract And Its Plan

The following documents require updates to add bidirectional references to this contract and its gate update plan:

1. **`resolved-style-consumer-contract.md`**
   - Add new Part M referencing the registry-read contract as the read contract specification
   - Update Part I (Registry Relationship) to reference the reader contract
   - Update Part L (Cross References) to list this contract

2. **`resolved-style-consumer-registry-read-planning.md`**
   - Deliverable Summary: update recommended next slice to point to this contract
   - Add forward reference in Part D (Access Model) to this contract as the formalization of Option 2

3. **`customization-studio-operating-contract.md`**
   - Section 13 (Related Documents): add this contract
   - Section 5 (Registry Layer): add forward reference to the reader contract

4. **`registry-read-boundary-gate-update-plan.md`** (new, future)
   - Full contents reference this contract as the source for relaxation rules

```text
Update list for each document:

resolved-style-consumer-contract.md:
  + Part M — Registry Read Contract
  + Part L: add registry-read-contract.md to cross references
  + Part I: note that registry reads flow through ApprovedStyleReaderContract
  + Recommended next slice → Registry Read Boundary Gate Update Plan

resolved-style-consumer-registry-read-planning.md:
  + Deliverable Summary: update recommended next slice
  + Part D: add forward reference to formalized contract

customization-studio-operating-contract.md:
  + Section 13: add registry-read-contract.md
  + Section 5: add forward reference for consumer-side read contract

registry-read-contract.md:
  + Part K: add registry-read-boundary-gate-update-plan.md to cross references
  + Deliverable Summary #9: update to reference the completed plan
```

---

## Deliverable Summary

### 1. Contract Path

`docs/architecture/registry-read-contract.md`

### 2. `ApprovedStyleReaderContract` Shape

```php
interface ApprovedStyleReaderContract {
    public function readValue(string $socketKey): ?string;
    public function isReachable(): bool;
}
```

Phase 1 only (single-value read + reachability). No batch, metadata, or catalog methods.

### 3. Adapter Design

`ApprovedStyleReaderAdapter` in `platform/Style/Adapters/` wraps `ApprovedStyleRegistry::getValue()` behind the read-only contract. The adapter is the ONLY bridge between `platform/Style/` and `apps/Platform/StyleRegistry/`. It enforces path confinement, lazy registry loading, and independent reachability checking.

### 4. Phase 1 Read Scope

| Operation | Authorized | Socket |
|---|---|---|
| `readValue('radius.scale')` | ✅ | `radius.scale` only |
| `isReachable()` | ✅ | N/A |
| `diagnostics()` | ✅ | All accumulated RSC-C* codes |
| Batch reads | ❌ | Deferred |
| Metadata reads | ❌ | Deferred |
| Catalog scans | ❌ | Probe scope |

### 5. Resolution Precedence

```
Layer 1: Theme source values (authoritative)
Layer 2: Compiled theme.css (generated)
Layer 3: Approved registry overlay (this contract) — additive only
Layer 4: Future runtime projection (not authorized)
```

Registry is NOT a second theme source. Registry values are approved overlays on named sockets only.

### 6. Diagnostics Family

13 consumer diagnostics (`RSC-C001` through `RSC-C303`):
- 3 PASS: value found, effective value resolved, reachable
- 4 WARN: socket not in catalog, value differs from default, fallback used, multiple values
- 3 FAIL: unreachable, invalid value, path confinement violation
- 3 ERROR: not initialized, contract violation, schema mismatch

### 7. Gate Relaxation Rules

| Current block | Future allowance | Condition |
|---|---|---|
| `ApprovedStyleRegistry\|getValue\(` | Block: `use Apps\\Platform\\StyleRegistry` (except adapter) | Gate update |
| (same line) | Block: `setValue\(`, `isWritable\(` | Gate update |
| (same line) | Allow: `readValue\(`, `isReachable\(` | After gate relaxation |
| `storage/platform/style-registry\|approved-values` | Allow read, block write | After gate relaxation |
| All other invariants | Remain blocked | Permanent |

### 8. Validation Proof

The contract is validated by cross-referencing the planning document's analysis:

- **Access model** → Part D of planning document (Option 2 selected)
- **Diagnostics** → Part F of planning document (RSC-C* family)
- **Gate changes** → Part G of planning document (exact invariant changes)
- **Risk assessment** → Part H of planning document (all 6 risks classified Medium or Low)
- **Ownership** → Part B of planning document (Platform source-of-record confirmed)
- **Readiness classification** → Part I of planning document (B — More Contracts Needed First; this contract fills the gap)

### 9. Recommended Next Slice

```
Registry Read Boundary Gate Update Plan
```

An architecture plan that specifies the exact `check_platform_style_consumer_boundary.sh` invariant changes, the adapter scan rules, and the sequence for relaxing the gate without enabling runtime consumption. This plan must exist before any gate or implementation work begins.

**Status:** ✅ Completed. See [Registry Read Boundary Gate Update Plan](registry-read-boundary-gate-update-plan.md) for the full plan with 10 invariant groups reviewed, exact relaxation proposal (2 blanket → 5+ targeted invariants), file scope rules, 22 still-forbidden pattern groups, diagnostics plan, 6-step implementation sequence, and 5 risk assessments.
